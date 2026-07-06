<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\WorkspaceMember;
use App\Services\InvitationManager;
use App\Services\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function index(Request $request, WorkspaceContext $context): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace, 403);

        return response()->json(['data' => $workspace->invitations()->latest()->get()]);
    }

    public function store(Request $request, WorkspaceContext $context, InvitationManager $invitations): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $context->canManageWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in([
                WorkspaceMember::ROLE_ADMIN,
                WorkspaceMember::ROLE_EDITOR,
                WorkspaceMember::ROLE_VIEWER,
            ])],
        ]);

        $result = $invitations->invite($workspace, $request->user(), $data['email'], $data['role']);

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

    public function accept(Request $request, Invitation $invitation, InvitationManager $invitations): JsonResponse
    {
        $member = $invitations->accept($request->user(), $invitation);

        return response()->json([
            'message' => 'Invitation accepted.',
            'data' => ['member' => $member],
        ]);
    }
}
