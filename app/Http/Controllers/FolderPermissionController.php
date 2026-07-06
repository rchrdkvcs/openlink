<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Folder;
use App\Models\FolderPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FolderPermissionController extends Controller
{
    public function store(Request $request, Folder $folder, WorkspaceAccess $access): RedirectResponse
    {
        $workspace = $access->requireManagedFolder($request, $folder);

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
