<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Services\WorkspaceContext;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    public function store(Request $request, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $context->canManageWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        Folder::create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
        ]);

        return back();
    }
}
