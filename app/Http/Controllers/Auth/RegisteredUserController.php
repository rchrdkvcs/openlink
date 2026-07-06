<?php

namespace App\Http\Controllers\Auth;

use App\Actions\InviteLinks\JoinWorkspaceViaInviteLink;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\InviteLink;
use App\Models\User;
use App\Services\InstanceSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request, InstanceSettings $settings): Response
    {
        $inviteLink = $this->usableInviteLink($request->query('invite'));

        $inviteAllowsRegistration = $inviteLink && $settings->get('registration_mode') !== 'closed';

        if (! $inviteAllowsRegistration && User::query()->exists() && $settings->get('registration_mode') !== 'open') {
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
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, JoinWorkspaceViaInviteLink $joiner): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'invite_token' => ['nullable', 'string'],
        ]);

        $isFirstUser = ! User::query()->exists();
        $inviteLink = $request->filled('invite_token')
            ? InviteLink::query()->where('token', $request->string('invite_token'))->first()
            : null;

        $registrationMode = app(InstanceSettings::class)->get('registration_mode');
        abort_if(! $isFirstUser && $registrationMode === 'closed', 403);
        abort_if(! $isFirstUser && ! $inviteLink && $registrationMode !== 'open', 403);
        abort_if($inviteLink && ! $inviteLink->isUsable(), 410);

        $user = DB::transaction(function () use ($request, $isFirstUser, $inviteLink, $joiner) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'is_instance_admin' => $isFirstUser,
            ]);

            if ($isFirstUser) {
                Domain::query()->firstOrCreate([
                    'hostname' => parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost',
                ], [
                    'workspace_id' => null,
                    'status' => Domain::STATUS_VERIFIED,
                    'verification_token' => Str::random(40),
                    'is_default' => true,
                    'verified_at' => now(),
                ]);
            }

            if ($inviteLink) {
                $member = $joiner->handle($user, $inviteLink);
                $request->session()->put('workspace_id', $member->workspace_id);
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

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
