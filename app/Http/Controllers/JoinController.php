<?php

namespace App\Http\Controllers;

use App\Actions\InviteLinks\JoinWorkspaceViaInviteLink;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Models\InviteLink;
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

        return Inertia::render('Join', [
            'invite' => [
                'token' => $inviteLink->token,
                'workspace' => $inviteLink->workspace->name,
                'role' => $inviteLink->role,
                'usable' => $inviteLink->isUsable(),
            ],
            'isMember' => $request->user()?->roleIn($inviteLink->workspace) !== null,
            'canRegister' => $settings->get('registration_mode') !== 'closed',
        ]);
    }

    public function store(Request $request, InviteLink $inviteLink, JoinWorkspaceViaInviteLink $joiner, CurrentWorkspace $current): RedirectResponse
    {
        $member = $joiner->handle($request->user(), $inviteLink);

        $current->select($member->workspace);

        return redirect()->route('dashboard');
    }
}
