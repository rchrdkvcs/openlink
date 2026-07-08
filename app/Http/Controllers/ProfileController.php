<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\OAuth\OAuthProviderRegistry;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, OAuthProviderRegistry $providers): Response
    {
        $user = $request->user();
        $user->refreshProfileAvatarSource();
        $pendingSecret = $user->two_factor_secret && ! $user->two_factor_confirmed_at
            ? Crypt::decryptString($user->two_factor_secret)
            : null;
        $socialAccounts = $user->socialAccounts()
            ->orderBy('provider')
            ->get()
            ->map(fn ($account) => [
                'id' => $account->id,
                'provider' => $account->provider,
                'email' => $account->email,
                'email_verified' => $account->email_verified,
                'avatar_url' => $account->avatar_url,
                'is_valid' => $user->isValidConnectedIdentity($account),
                'is_avatar_source' => $user->profile_avatar_social_account_id === $account->id,
                'created_at' => $account->created_at,
            ]);

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'profileAvatar' => [
                'url' => $user->profileAvatarUrl(),
                'source_id' => $user->profile_avatar_social_account_id,
            ],
            'connectedIdentities' => $socialAccounts,
            'oauthProviders' => $providers->availableProviders(),
            'apiTokens' => $user->tokens()
                ->latest()
                ->get(['id', 'name', 'last_used_at', 'created_at']),
            'newApiToken' => session('newApiToken'),
            'canCreateApiTokens' => $user->hasVerifiedEmail(),
            'twoFactor' => [
                'enabled' => (bool) $user->two_factor_confirmed_at,
                'pendingSecret' => $pendingSecret,
                'otpauthUrl' => $pendingSecret
                    ? (new Google2FA)->getQRCodeUrl(config('app.name'), $user->email, $pendingSecret)
                    : null,
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());
        $emailChanged = $request->user()->isDirty('email');

        if ($emailChanged) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();
        $request->user()->refreshProfileAvatarSource();

        if ($emailChanged && $request->user() instanceof MustVerifyEmail) {
            $user = $request->user();

            app()->terminating(function () use ($user): void {
                try {
                    $user->sendEmailVerificationNotification();
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        }

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function prepareTwoFactor(Request $request): RedirectResponse
    {
        $secret = (new Google2FA)->generateSecretKey();

        $request->user()->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => null,
        ])->save();

        return Redirect::route('profile.edit');
    }

    public function confirmTwoFactor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        abort_unless($user->two_factor_secret, 422);

        $secret = Crypt::decryptString($user->two_factor_secret);
        abort_unless((new Google2FA)->verifyKey($secret, $data['code']), 422);

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        return Redirect::route('profile.edit');
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return Redirect::route('profile.edit');
    }
}
