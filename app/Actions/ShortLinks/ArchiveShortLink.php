<?php

namespace App\Actions\ShortLinks;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\ShortLink;
use Illuminate\Http\Request;

class ArchiveShortLink
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, ShortLink $shortLink): ShortLink
    {
        $this->access->requireEditableShortLink($request, $shortLink);
        $shortLink->update(['archived_at' => now()]);

        return $shortLink;
    }
}
