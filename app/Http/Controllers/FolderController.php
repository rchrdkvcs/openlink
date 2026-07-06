<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Folder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    public function store(Request $request, WorkspaceAccess $access): RedirectResponse
    {
        $workspace = $access->requireManagedWorkspace($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        Folder::create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
        ]);

        return back();
    }

    public function update(Request $request, Folder $folder, WorkspaceAccess $access): RedirectResponse
    {
        $access->requireManagedFolder($request, $folder);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder->update(['name' => $data['name']]);

        return back();
    }

    public function destroy(Request $request, Folder $folder, WorkspaceAccess $access): RedirectResponse
    {
        $access->requireManagedFolder($request, $folder);

        // short_links.folder_id is nullOnDelete: links in the folder become unfiled.
        $folder->delete();

        return back();
    }
}
