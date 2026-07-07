<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $pendingUser = $request->authenticate();

        if ($pendingUser) {
            $request->session()->put('login.two_factor', [
                'user_id' => $pendingUser->id,
                'remember' => $request->boolean('remember'),
            ]);

            return redirect()->route('login.two-factor');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Display the two-factor challenge view.
     */
    public function createTwoFactor(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.two_factor.user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /**
     * Complete a pending two-factor authentication challenge.
     */
    public function storeTwoFactor(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('login.two_factor');

        if (! is_array($pending) || ! isset($pending['user_id'])) {
            return redirect()->route('login');
        }

        $request->validate([
            'one_time_password' => ['required', 'string'],
        ]);

        $user = User::query()->find($pending['user_id']);

        if (! $user || ! $user->two_factor_confirmed_at || ! $user->two_factor_secret) {
            $request->session()->forget('login.two_factor');

            return redirect()->route('login');
        }

        $this->ensureTwoFactorIsNotRateLimited($request, $user);

        $secret = Crypt::decryptString($user->two_factor_secret);
        $valid = (new Google2FA)->verifyKey($secret, (string) $request->input('one_time_password'));

        if (! $valid) {
            RateLimiter::hit($this->twoFactorThrottleKey($request, $user));

            throw ValidationException::withMessages([
                'one_time_password' => __('auth.two_factor'),
            ]);
        }

        RateLimiter::clear($this->twoFactorThrottleKey($request, $user));
        $request->session()->forget('login.two_factor');

        Auth::login($user, (bool) ($pending['remember'] ?? false));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * @throws ValidationException
     */
    private function ensureTwoFactorIsNotRateLimited(Request $request, User $user): void
    {
        if (! RateLimiter::tooManyAttempts($this->twoFactorThrottleKey($request, $user), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->twoFactorThrottleKey($request, $user));

        throw ValidationException::withMessages([
            'one_time_password' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function twoFactorThrottleKey(Request $request, User $user): string
    {
        return 'two-factor-login|'.$user->id.'|'.$request->ip();
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
