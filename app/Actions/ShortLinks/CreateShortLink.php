<?php

namespace App\Actions\ShortLinks;

use App\Actions\Routing\SyncRoutingRules;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ShortLinks\ShortUrlComposition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateShortLink
{
    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly ShortUrlComposition $shortUrls,
        private readonly SyncRoutingRules $routingRules,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Workspace $workspace, User $user, array $data, ?Domain $fallbackDomain = null): ShortLink
    {
        abort_unless($this->access->canEditWorkspace($user, $workspace), 403);

        $domain = $this->shortUrls->requireUsableDomain($data['domain_id'] ?? null, $workspace, $fallbackDomain);

        $folder = $this->folderForWorkspace($workspace, $data['folder_id'] ?? null);
        abort_if($folder && ! $this->access->canEditFolder($user, $folder), 403);

        $slug = $this->shortUrls->slugForCreate($domain, $data);

        $this->shortUrls->assertNoLoop($domain, $slug, $data['destination_url']);

        return DB::transaction(function () use ($workspace, $folder, $domain, $slug, $data): ShortLink {
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
            $this->routingRules->handle($shortLink, $data['routing_rules'] ?? []);

            return $shortLink;
        });
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
