<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\FolderPermission;
use App\Services\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FolderPermissionController extends Controller
{
    public function store(Request $request, Folder $folder, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
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

        FolderPermission::query()->updateOrCreate([
            'folder_id' => $folder->id,
            'user_id' => $data['user_id'],
        ], ['permission' => $data['permission']]);

        return back();
    }
}
