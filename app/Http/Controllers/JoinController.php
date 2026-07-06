<?php

namespace App\Http\Controllers;

use App\Actions\InviteLinks\JoinWorkspaceViaInviteLink;
use App\Models\InviteLink;
use App\Models\WorkspaceMember;
use App\Services\InstanceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JoinController extends Controller
{
    public function show(Request $request, InviteLink $inviteLink, InstanceSettings $settings): Response
    {
        $inviteLink->load('workspace:id,name');
        $user = $request->user();

        $isMember = $user && WorkspaceMember::query()
            ->where('workspace_id', $inviteLink->workspace_id)
            ->where('user_id', $user->id)
            ->exists();

        return Inertia::render('Join', [
            'invite' => [
                'token' => $inviteLink->token,
                'workspace' => $inviteLink->workspace->name,
                'role' => $inviteLink->role,
                'usable' => $inviteLink->isUsable(),
            ],
            'isMember' => $isMember,
            'canRegister' => $settings->get('registration_mode') !== 'closed',
        ]);
    }

    public function store(Request $request, InviteLink $inviteLink, JoinWorkspaceViaInviteLink $joiner): RedirectResponse
    {
        $member = $joiner->handle($request->user(), $inviteLink);

        $request->session()->put('workspace_id', $member->workspace_id);

        return redirect()->route('dashboard');
    }
}
