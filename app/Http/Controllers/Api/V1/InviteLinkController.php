<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\InviteLinks\CreateInviteLink;
use App\Actions\InviteLinks\InviteLinkPayload;
use App\Actions\InviteLinks\JoinWorkspaceViaInviteLink;
use App\Actions\InviteLinks\RevokeInviteLink;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\InviteLinks\StoreInviteLinkRequest;
use App\Models\InviteLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InviteLinkController extends Controller
{
    public function index(CurrentWorkspace $current): JsonResponse
    {
        return response()->json(['data' => InviteLinkPayload::active($current->require('manage'))]);
    }

    public function store(StoreInviteLinkRequest $request, CurrentWorkspace $current, CreateInviteLink $inviteLinks): JsonResponse
    {
        $link = $inviteLinks->handle(
            $request->user(),
            $current->require(),
            $request->role(),
            $request->expiresInDays(),
            $request->maxUses(),
        );

        return response()->json([
            'message' => 'Invite link created.',
            'data' => ['invite_link' => InviteLinkPayload::make($link)],
        ], 201);
    }

    public function destroy(Request $request, InviteLink $inviteLink, RevokeInviteLink $revoker): JsonResponse
    {
        $revoker->handle($request->user(), $inviteLink);

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
