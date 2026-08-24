<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Actions\Workspaces\WorkspacePayloads;
use App\Actions\Workspaces\WorkspaceViewFactory;
use App\Http\Controllers\Controller;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access, WorkspacePayloads $data, WorkspaceViewFactory $views): JsonResponse
    {
        $workspace = $access->requireCurrent($request);

        return response()->json(['data' => $data->folders($views->make($workspace, $request->user()))]);
    }

    public function store(Request $request, WorkspaceAccess $access): JsonResponse
    {
        $workspace = $access->requireManagedWorkspace($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder = Folder::create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
        ]);

        return response()->json(['data' => $folder], 201);
    }

    public function update(Request $request, Folder $folder, WorkspaceAccess $access): JsonResponse
    {
        $access->requireManagedFolder($request, $folder);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder->update(['name' => $data['name']]);

        return response()->json(['data' => $folder]);
    }

    public function destroy(Request $request, Folder $folder, WorkspaceAccess $access): JsonResponse
    {
        $access->requireManagedFolder($request, $folder);

        // short_links.folder_id is nullOnDelete: links in the folder become unfiled.
        $folder->delete();

        return response()->json(['message' => 'Folder deleted.']);
    }
}
