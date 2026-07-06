<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Invitations\AcceptWorkspaceInvitation;
use App\Actions\Invitations\InviteWorkspaceMember;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access): JsonResponse
    {
        $workspace = $access->requireCurrent($request);

        return response()->json(['data' => $workspace->invitations()->latest()->get()]);
    }

    public function store(Request $request, InviteWorkspaceMember $invitations): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in([
                WorkspaceMember::ROLE_ADMIN,
                WorkspaceMember::ROLE_EDITOR,
                WorkspaceMember::ROLE_VIEWER,
            ])],
        ]);

        $result = $invitations->handle($request, $data['email'], $data['role']);

        if ($result['member']) {
            return response()->json([
                'message' => 'User added to the workspace.',
                'data' => ['member' => $result['member']->load('user:id,name,email')],
            ], 201);
        }

        return response()->json([
            'message' => 'Invitation sent.',
            'data' => ['invitation' => $result['invitation']],
        ], 201);
    }

    public function accept(Request $request, Invitation $invitation, AcceptWorkspaceInvitation $invitations): JsonResponse
    {
        $member = $invitations->handle($request->user(), $invitation);

        return response()->json([
            'message' => 'Invitation accepted.',
            'data' => ['member' => $member],
        ]);
    }
}
