<?php

namespace App\Actions\ShortLinks;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Services\SlugService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UpdateShortLink
{
    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly SlugService $slugs,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Request $request, ShortLink $shortLink, array $data): ShortLink
    {
        $workspace = $this->access->requireEditableShortLink($request, $shortLink);

        $folder = $this->folderForWorkspace($workspace->id, $data['folder_id'] ?? null);
        abort_if($folder && ! $this->access->canEditFolder($request->user(), $folder), 403);

        $shortLink->loadMissing('domain');

        $domain = $shortLink->domain;

        if (isset($data['domain_id']) && (int) $data['domain_id'] !== $shortLink->domain_id) {
            $domain = $this->domainForWorkspace((int) $data['domain_id'], $workspace->id);
            abort_unless($domain, 422, 'Domain does not belong to this workspace.');
            abort_unless($domain->isUsable(), 422, 'Domain is not active or is disabled.');
        }

        // Re-validate the slug only when the short URL actually changes; a changed
        // slug (or domain) can never collide with the link's own current address.
        $slug = $shortLink->slug;
        $submitted = trim((string) ($data['slug'] ?? $shortLink->slug), '/');

        if ($submitted !== $shortLink->slug || $domain->id !== $shortLink->domain_id) {
            $slug = $this->slugs->validateCustom($domain, $submitted);
        }

        $this->assertNoLoop($domain, $slug, $data['destination_url']);

        $shortLink->fill([
            'folder_id' => $folder?->id,
            'domain_id' => $domain->id,
            'slug' => $slug,
            'destination_url' => $data['destination_url'],
            'fallback_url' => $data['fallback_url'] ?? null,
            'is_enabled' => $data['is_enabled'],
            'activates_at' => $data['activates_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'visit_limit' => $data['visit_limit'] ?? null,
        ]);

        if (array_key_exists('password', $data)) {
            $shortLink->password_hash = filled($data['password'] ?? null)
                ? Hash::make($data['password'])
                : null;
        }

        $shortLink->save();

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

    private function folderForWorkspace(int $workspaceId, mixed $folderId): ?Folder
    {
        if (! filled($folderId)) {
            return null;
        }

        return Folder::query()
            ->whereKey((int) $folderId)
            ->where('workspace_id', $workspaceId)
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
}
