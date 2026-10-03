<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Members\RemoveWorkspaceMember;
use App\Actions\Members\UpdateMemberRole;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\UpdateMemberRoleRequest;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(CurrentWorkspace $current): JsonResponse
    {
        return response()->json([
            'data' => $current->require()->members()->with('user:id,name,email')->orderBy('role')->get(),
        ]);
    }

    public function update(UpdateMemberRoleRequest $request, WorkspaceMember $member, UpdateMemberRole $roles): JsonResponse
    {
        $member = $roles->handle($request->user(), $member, $request->role());

        return response()->json([
            'message' => 'Member role updated.',
            'data' => ['member' => $member->load('user:id,name,email')],
        ]);
    }

    public function destroy(Request $request, WorkspaceMember $member, RemoveWorkspaceMember $remover): JsonResponse
    {
        $remover->handle($request->user(), $member);

        return response()->json(['message' => 'Member removed from the workspace.']);
    }
}
