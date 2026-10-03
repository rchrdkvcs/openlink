<?php

namespace App\Http\Controllers\Auth;

use App\Actions\InviteLinks\JoinWorkspaceViaInviteLink;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Controllers\Controller;
use App\Models\InviteLink;
use App\Models\User;
use App\Services\OAuth\OAuthProviderRegistry;
use App\Services\Registration\AccountRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(Request $request, AccountRegistration $registration, OAuthProviderRegistry $providers): Response
    {
        $inviteLink = $this->usableInviteLink($request->query('invite'));

        $inviteAllowsRegistration = $registration->acceptsInvite($inviteLink);
        $registrationAllowsOAuth = $registration->allowsNewUser($inviteLink);

        if (! $registrationAllowsOAuth) {
            redirect()
                ->route('login')
                ->with('status', 'Registration is invite-only. Use an invite link or sign in.')
                ->throwResponse();
        }

        return Inertia::render('Auth/Register', [
            'invite' => $inviteAllowsRegistration ? [
                'token' => $inviteLink->token,
                'workspace' => $inviteLink->workspace->name,
                'role' => $inviteLink->role,
            ] : null,
            'oauthProviders' => $registrationAllowsOAuth ? $providers->availableProviders() : [],
        ]);
    }

    public function store(Request $request, JoinWorkspaceViaInviteLink $joiner, AccountRegistration $registration, CurrentWorkspace $current): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'invite_token' => ['nullable', 'string'],
        ]);

        $inviteLink = $request->filled('invite_token')
            ? InviteLink::query()->where('token', $request->string('invite_token'))->first()
            : null;

        abort_if($inviteLink && ! $inviteLink->isUsable(), 410);
        abort_unless($registration->allowsNewUser($inviteLink), 403);

        $user = DB::transaction(function () use ($request, $inviteLink, $joiner, $registration, $current) {
            $user = $registration->createUser([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            if ($inviteLink) {
                $member = $joiner->handle($user, $inviteLink);
                $current->select($member->workspace);
            }

            return $user;
        });

        Auth::login($user);
        $registration->dispatchRegisteredAfterResponse($user);

        return redirect(route('dashboard', absolute: false));
    }

    private function usableInviteLink(?string $token): ?InviteLink
    {
        if (! $token) {
            return null;
        }

        $inviteLink = InviteLink::query()->where('token', $token)->first();

        return $inviteLink && $inviteLink->isUsable()
            ? $inviteLink->load('workspace:id,name')
            : null;
    }
}
