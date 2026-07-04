<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
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
    public function create(InstanceSettings $settings): Response
    {
        if (User::query()->exists() && $settings->get('registration_mode') !== 'open') {
            redirect()
                ->route('login')
                ->with('status', 'Registration is invite-only. Use an invitation link or sign in.')
                ->throwResponse();
        }

        return Inertia::render('Auth/Register', [
            'invitation' => null,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'invitation_token' => ['nullable', 'string'],
        ]);

        $isFirstUser = ! User::query()->exists();
        $invitation = $request->filled('invitation_token')
            ? Invitation::query()->where('token', $request->string('invitation_token'))->first()
            : null;

        $registrationMode = app(InstanceSettings::class)->get('registration_mode');
        abort_if(! $isFirstUser && $registrationMode === 'closed', 403);
        abort_if(! $isFirstUser && ! $invitation && $registrationMode !== 'open', 403);
        abort_if($invitation && (! $invitation->isUsable() || $invitation->email !== $request->email), 403);

        $user = DB::transaction(function () use ($request, $isFirstUser, $invitation) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'is_instance_admin' => $isFirstUser,
            ]);

            if ($isFirstUser) {
                $workspace = Workspace::create([
                    'owner_id' => $user->id,
                    'name' => 'Personal',
                    'slug' => 'personal',
                    'settings' => [],
                ]);

                WorkspaceMember::create([
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                    'role' => WorkspaceMember::ROLE_OWNER,
                ]);

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

            if ($invitation) {
                WorkspaceMember::query()->updateOrCreate([
                    'workspace_id' => $invitation->workspace_id,
                    'user_id' => $user->id,
                ], ['role' => $invitation->role]);

                $invitation->update(['accepted_at' => now()]);
                $request->session()->put('workspace_id', $invitation->workspace_id);
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
