<?php

namespace App\Actions\ShortLinks;

use App\Actions\Routing\SyncRoutingRules;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Services\ShortLinks\ShortUrlComposition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpdateShortLink
{
    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly ShortUrlComposition $shortUrls,
        private readonly SyncRoutingRules $routingRules,
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
            $domain = $this->shortUrls->requireUsableDomain($data['domain_id'], $workspace);
        }

        $slug = $this->shortUrls->slugForUpdate($shortLink, $domain, $data);

        $this->shortUrls->assertNoLoop($domain, $slug, $data['destination_url']);

        return DB::transaction(function () use ($shortLink, $folder, $domain, $slug, $data): ShortLink {
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

            if (array_key_exists('routing_rules', $data)) {
                $this->routingRules->handle($shortLink, $data['routing_rules'] ?? []);
            }

            return $shortLink;
        });
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
}
