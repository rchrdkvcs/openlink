<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redirect;
use PragmaRX\Google2FA\Google2FA;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $pendingSecret = $user->two_factor_secret && ! $user->two_factor_confirmed_at
            ? Crypt::decryptString($user->two_factor_secret)
            : null;

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'twoFactor' => [
                'enabled' => (bool) $user->two_factor_confirmed_at,
                'pendingSecret' => $pendingSecret,
                'otpauthUrl' => $pendingSecret
                    ? (new Google2FA())->getQRCodeUrl(config('app.name'), $user->email, $pendingSecret)
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

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

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
        $secret = (new Google2FA())->generateSecretKey();

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
        abort_unless((new Google2FA())->verifyKey($secret, $data['code']), 422);

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
