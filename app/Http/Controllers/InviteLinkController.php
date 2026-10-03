<?php

namespace App\Http\Controllers;

use App\Actions\InviteLinks\CreateInviteLink;
use App\Actions\InviteLinks\InviteLinkPayload;
use App\Actions\InviteLinks\RevokeInviteLink;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Requests\InviteLinks\StoreInviteLinkRequest;
use App\Models\InviteLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InviteLinkController extends Controller
{
    public function store(StoreInviteLinkRequest $request, CurrentWorkspace $current, CreateInviteLink $inviteLinks): JsonResponse|RedirectResponse
    {
        $link = $inviteLinks->handle(
            $request->user(),
            $current->require(),
            $request->role(),
            $request->expiresInDays(),
            $request->maxUses(),
        );

        if ($request->wantsJson()) {
            return response()->json(InviteLinkPayload::make($link), 201);
        }

        return back();
    }

    public function destroy(Request $request, InviteLink $inviteLink, RevokeInviteLink $revoker): RedirectResponse
    {
        $revoker->handle($request->user(), $inviteLink);

        return back();
    }
}
