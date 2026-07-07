<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ApplicationHost;
use App\Services\OAuth\OAuthProfile;
use App\Services\OAuth\OAuthProviderRegistry;
use App\Services\OAuth\OAuthSignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class OAuthController extends Controller
{
    public function redirect(
        Request $request,
        string $provider,
        OAuthProviderRegistry $providers,
        ApplicationHost $applicationHost,
    ): RedirectResponse {
        if (! $applicationHost->isApplicationHost($request->getHost())) {
            return $this->backToLogin('We could not complete sign-in with this provider.');
        }

        if (! $providers->isConfigured($provider)) {
            return $this->backToLogin('This sign-in method is not available.');
        }

        $request->session()->put('oauth.context', [
            'provider' => $provider,
            'intent' => $request->query('intent') === 'register' ? 'register' : 'login',
            'invite_token' => $request->query('invite'),
            'url_intended' => $request->session()->get('url.intended'),
        ]);

        return Socialite::driver($provider)
            ->scopes($providers->scopes($provider))
            ->redirect();
    }

    public function callback(
        Request $request,
        string $provider,
        OAuthProviderRegistry $providers,
        OAuthSignIn $signIn,
        ApplicationHost $applicationHost,
    ): RedirectResponse {
        if (! $applicationHost->isApplicationHost($request->getHost())) {
            return $this->backToLogin('We could not complete sign-in with this provider.');
        }

        if (! $providers->isConfigured($provider)) {
            return $this->backToLogin('This sign-in method is not available.');
        }

        $context = $request->session()->pull('oauth.context', []);

        if (($context['provider'] ?? null) !== $provider) {
            return $this->backToLogin('We could not complete sign-in with this provider.');
        }

        try {
            $socialiteUser = Socialite::driver($provider)->user();
            $user = $signIn->userFor(OAuthProfile::fromSocialiteUser($provider, $socialiteUser), $context);
        } catch (Throwable $exception) {
            Log::warning('OAuth sign-in failed.', [
                'provider' => $provider,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $message = method_exists($exception, 'errors')
                ? (collect($exception->errors())->flatten()->first() ?: 'We could not complete sign-in with this provider.')
                : 'We could not complete sign-in with this provider.';

            return $this->backToLogin((string) $message);
        }

        if ($user->two_factor_confirmed_at) {
            $request->session()->put('login.two_factor', [
                'user_id' => $user->id,
                'remember' => false,
                'via' => 'oauth',
            ]);

            return redirect()->route('login.two-factor');
        }

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        return redirect()->intended($context['url_intended'] ?? route('dashboard', absolute: false));
    }

    private function backToLogin(string $message): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->with('status', $message);
    }
}
