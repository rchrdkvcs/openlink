<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\InviteLinks\CreateInviteLink;
use App\Actions\InviteLinks\JoinWorkspaceViaInviteLink;
use App\Actions\InviteLinks\RevokeInviteLink;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Actions\Workspaces\WorkspacePayloads;
use App\Http\Controllers\Controller;
use App\Models\InviteLink;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InviteLinkController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access, WorkspacePayloads $data): JsonResponse
    {
        $workspace = $access->requireManagedWorkspace($request);

        return response()->json(['data' => $data->inviteLinks($workspace)]);
    }

    public function store(Request $request, CreateInviteLink $inviteLinks, WorkspacePayloads $payloads): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([
                WorkspaceMember::ROLE_ADMIN,
                WorkspaceMember::ROLE_EDITOR,
                WorkspaceMember::ROLE_VIEWER,
            ])],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $link = $inviteLinks->handle(
            $request,
            $data['role'],
            $data['expires_in_days'] ?? null,
            $data['max_uses'] ?? null,
        );

        return response()->json([
            'message' => 'Invite link created.',
            'data' => ['invite_link' => $payloads->inviteLinkPayload($link)],
        ], 201);
    }

    public function destroy(Request $request, InviteLink $inviteLink, RevokeInviteLink $revoker): JsonResponse
    {
        $revoker->handle($request, $inviteLink);

        return response()->json(['message' => 'Invite link revoked.']);
    }

    public function join(Request $request, InviteLink $inviteLink, JoinWorkspaceViaInviteLink $joiner): JsonResponse
    {
        $member = $joiner->handle($request->user(), $inviteLink);

        return response()->json([
            'message' => 'Workspace joined.',
            'data' => ['member' => $member],
        ]);
    }
}
