<?php

namespace App\Actions\ShortLinks;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\ShortLink;
use Illuminate\Http\Request;

class DeleteShortLink
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function handle(Request $request, ShortLink $shortLink): void
    {
        $this->access->requireManageableShortLink($request, $shortLink);
        $shortLink->delete();
    }
}
