<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\WorkspaceMember;
use App\Services\InstanceSettings;
use App\Services\InvitationManager;
use App\Services\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function store(Request $request, WorkspaceContext $context, InvitationManager $invitations): RedirectResponse
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

        $invitations->invite($workspace, $request->user(), $data['email'], $data['role']);

        return back();
    }

    public function show(Invitation $invitation, InstanceSettings $settings): Response
    {
        abort_if($settings->get('registration_mode') === 'closed', 403);
        abort_unless($invitation->isUsable(), 410);

        return Inertia::render('Auth/Register', [
            'invitation' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'workspace' => $invitation->workspace->name,
                'role' => $invitation->role,
            ],
        ]);
    }

    public function accept(Request $request, Invitation $invitation, InvitationManager $invitations): RedirectResponse
    {
        $invitations->accept($request->user(), $invitation);

        $request->session()->put('workspace_id', $invitation->workspace_id);

        return redirect()->route('dashboard');
    }
}
