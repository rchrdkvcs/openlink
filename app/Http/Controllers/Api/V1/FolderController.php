<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Services\WorkspaceContext;
use App\Services\WorkspaceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FolderController extends Controller
{
    public function index(Request $request, WorkspaceContext $context, WorkspaceData $data): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace, 403);

        return response()->json(['data' => $data->folders($workspace, $request->user())]);
    }

    public function store(Request $request, WorkspaceContext $context): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $context->canManageWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder = Folder::create([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
        ]);

        return response()->json(['data' => $folder], 201);
    }

    public function update(Request $request, Folder $folder, WorkspaceContext $context): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $folder->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder->update(['name' => $data['name']]);

        return response()->json(['data' => $folder]);
    }

    public function destroy(Request $request, Folder $folder, WorkspaceContext $context): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $folder->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);

        // short_links.folder_id is nullOnDelete: links in the folder become unfiled.
        $folder->delete();

        return response()->json(['message' => 'Folder deleted.']);
    }

    public function storePermission(Request $request, Folder $folder, WorkspaceContext $context): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $folder->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            'user_id' => ['required', Rule::exists('workspace_members', 'user_id')->where('workspace_id', $workspace->id)],
            'permission' => ['required', Rule::in([
                FolderPermission::CAN_VIEW,
                FolderPermission::CAN_EDIT,
                FolderPermission::CAN_MANAGE,
            ])],
        ]);

        $permission = FolderPermission::query()->updateOrCreate([
            'folder_id' => $folder->id,
            'user_id' => $data['user_id'],
        ], ['permission' => $data['permission']]);

        return response()->json(['data' => $permission], 201);
    }
}
