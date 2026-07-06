<?php

namespace App\Http\Controllers;

use App\Actions\InviteLinks\CreateInviteLink;
use App\Actions\InviteLinks\RevokeInviteLink;
use App\Models\InviteLink;
use App\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InviteLinkController extends Controller
{
    public function store(Request $request, CreateInviteLink $inviteLinks): RedirectResponse
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

        $inviteLinks->handle(
            $request,
            $data['role'],
            $data['expires_in_days'] ?? null,
            $data['max_uses'] ?? null,
        );

        return back();
    }

    public function destroy(Request $request, InviteLink $inviteLink, RevokeInviteLink $revoker): RedirectResponse
    {
        $revoker->handle($request, $inviteLink);

        return back();
    }
}
