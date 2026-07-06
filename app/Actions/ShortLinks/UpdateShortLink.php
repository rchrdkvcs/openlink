<?php

namespace App\Actions\ShortLinks;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UpdateShortLink
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Request $request, ShortLink $shortLink, array $data): ShortLink
    {
        $workspace = $this->access->requireEditableShortLink($request, $shortLink);

        $folder = $this->folderForWorkspace($workspace->id, $data['folder_id'] ?? null);
        abort_if($folder && ! $this->access->canEditFolder($request->user(), $folder), 403);

        $shortLink->loadMissing('domain');
        $this->assertNoLoop($shortLink->domain, $shortLink->slug, $data['destination_url']);

        $shortLink->fill([
            'folder_id' => $folder?->id,
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
