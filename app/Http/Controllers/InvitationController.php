<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Notifications\WorkspaceInvitationNotification;
use App\Services\InstanceSettings;
use App\Services\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function store(Request $request, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
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

        if ($user = User::query()->where('email', $data['email'])->first()) {
            WorkspaceMember::query()->updateOrCreate([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
            ], ['role' => $data['role']]);

            return back();
        }

        $invitation = Invitation::query()->updateOrCreate([
            'workspace_id' => $workspace->id,
            'email' => $data['email'],
            'accepted_at' => null,
        ], [
            'role' => $data['role'],
            'token' => Str::random(48),
            'invited_by_id' => $request->user()->id,
            'expires_at' => now()->addDays(14),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation->load('workspace')));

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

    public function accept(Request $request, Invitation $invitation): \Illuminate\Http\RedirectResponse
    {
        abort_unless($request->user()?->email === $invitation->email, 403);
        abort_if($invitation->accepted_at || ($invitation->expires_at && $invitation->expires_at->isPast()), 410);

        WorkspaceMember::query()->updateOrCreate([
            'workspace_id' => $invitation->workspace_id,
            'user_id' => $request->user()->id,
        ], ['role' => $invitation->role]);

        $invitation->update(['accepted_at' => now()]);
        $request->session()->put('workspace_id', $invitation->workspace_id);

        return redirect()->route('dashboard');
    }
}
