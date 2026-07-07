<?php

namespace App\Actions\ShortLinks;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SlugService;
use Illuminate\Support\Facades\Hash;

class CreateShortLink
{
    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly SlugService $slugs,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Workspace $workspace, User $user, array $data, ?Domain $fallbackDomain = null): ShortLink
    {
        abort_unless($this->access->canEditWorkspace($user, $workspace), 403);

        $domain = $this->domainForWorkspace((int) ($data['domain_id'] ?? $fallbackDomain?->id), $workspace->id);
        abort_unless($domain, 422, 'Domain does not belong to this workspace.');
        abort_unless($domain->isUsable(), 422, 'Domain is not active or is disabled.');

        $folder = $this->folderForWorkspace($workspace, $data['folder_id'] ?? null);
        abort_if($folder && ! $this->access->canEditFolder($user, $folder), 403);

        $slug = filled($data['slug'] ?? null)
            ? $this->slugs->validateCustom($domain, $data['slug'])
            : $this->slugs->generate($domain);

        $this->assertNoLoop($domain, $slug, $data['destination_url']);

        $shortLink = ShortLink::create([
            'workspace_id' => $workspace->id,
            'folder_id' => $folder?->id,
            'domain_id' => $domain->id,
            'slug' => $slug,
            'destination_url' => $data['destination_url'],
            'fallback_url' => $data['fallback_url'] ?? null,
            'is_enabled' => $data['is_enabled'] ?? true,
            'activates_at' => $data['activates_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'visit_limit' => $data['visit_limit'] ?? null,
            'password_hash' => filled($data['password'] ?? null) ? Hash::make($data['password']) : null,
        ]);

        $this->syncTags($shortLink, $data['tags'] ?? '');

        return $shortLink;
    }

    private function domainForWorkspace(int $domainId, int $workspaceId): ?Domain
    {
        if ($domainId <= 0) {
            return null;
        }

        return Domain::query()
            ->whereKey($domainId)
            ->where(fn ($query) => $query->where('workspace_id', $workspaceId)->orWhere('is_default', true))
            ->first();
    }

    private function folderForWorkspace(Workspace $workspace, mixed $folderId): ?Folder
    {
        if (! filled($folderId)) {
            return null;
        }

        return Folder::query()
            ->whereKey((int) $folderId)
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();
    }

    private function assertNoLoop(Domain $domain, string $slug, string $destinationUrl): void
    {
        $targetHost = parse_url($destinationUrl, PHP_URL_HOST);
        $targetPath = trim((string) parse_url($destinationUrl, PHP_URL_PATH), '/');

        if ($targetHost === $domain->hostname && $targetPath === trim($slug, '/')) {
            abort(422, 'Destination URL cannot point to the same short URL.');
        }
    }

    private function syncTags(ShortLink $shortLink, string $tags): void
    {
        $tagIds = collect(explode(',', $tags))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique()
            ->map(function (string $name) use ($shortLink) {
                return Tag::query()->firstOrCreate([
                    'workspace_id' => $shortLink->workspace_id,
                    'name' => $name,
                ])->id;
            });

        if ($tagIds->isNotEmpty()) {
            $shortLink->tags()->sync($tagIds->all());
        }
    }
}
