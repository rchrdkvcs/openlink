<?php

namespace App\Actions\ShortLinks;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Folder;
use App\Models\ShortLink;
use Illuminate\Http\Request;

class MoveShortLink
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, ShortLink $shortLink, mixed $folderId): ShortLink
    {
        $workspace = $this->access->requireEditableShortLink($request, $shortLink);
        $folder = null;

        if (filled($folderId)) {
            $folder = Folder::query()
                ->whereKey((int) $folderId)
                ->where('workspace_id', $workspace->id)
                ->firstOrFail();
        }

        abort_if($folder && ! $this->access->canEditFolder($request->user(), $folder), 403);

        $shortLink->update(['folder_id' => $folder?->id]);

        return $shortLink;
    }
}
