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

    public function update(Request $request, Folder $folder, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $folder->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder->update(['name' => $data['name']]);

        return back();
    }

    public function destroy(Request $request, Folder $folder, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $folder->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);

        // short_links.folder_id is nullOnDelete: links in the folder become unfiled.
        $folder->delete();

        return back();
    }
}
