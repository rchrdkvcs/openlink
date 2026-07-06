<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Members\RemoveWorkspaceMember;
use App\Actions\Members\UpdateMemberRole;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Http\Controllers\Controller;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access): JsonResponse
    {
        $workspace = $access->requireCurrent($request);

        return response()->json([
            'data' => $workspace->members()->with('user:id,name,email')->orderBy('role')->get(),
        ]);
    }

    public function update(Request $request, WorkspaceMember $member, UpdateMemberRole $roles): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([
                WorkspaceMember::ROLE_ADMIN,
                WorkspaceMember::ROLE_EDITOR,
                WorkspaceMember::ROLE_VIEWER,
            ])],
        ]);

        $member = $roles->handle($request, $member, $data['role']);

        return response()->json([
            'message' => 'Member role updated.',
            'data' => ['member' => $member->load('user:id,name,email')],
        ]);
    }

    public function destroy(Request $request, WorkspaceMember $member, RemoveWorkspaceMember $remover): JsonResponse
    {
        $remover->handle($request, $member);

        return response()->json(['message' => 'Member removed from the workspace.']);
    }
}
