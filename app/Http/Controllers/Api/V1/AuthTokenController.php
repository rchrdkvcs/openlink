<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthTokenController extends Controller
{
    /**
     * Exchange credentials for a personal access token (API login).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'one_time_password' => ['nullable', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($data['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if ($user->two_factor_confirmed_at) {
            $secret = Crypt::decryptString($user->two_factor_secret);
            $valid = (new Google2FA)->verifyKey($secret, (string) ($data['one_time_password'] ?? ''));

            if (! $valid) {
                RateLimiter::hit($throttleKey);

                throw ValidationException::withMessages([
                    'one_time_password' => __('auth.two_factor'),
                ]);
            }
        }

        if (! $user->hasVerifiedEmail()) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Verify your email address before creating API tokens.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $token = $user->createToken($data['device_name']);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $user->only(['id', 'name', 'email', 'is_instance_admin']),
        ], 201);
    }

    /**
     * List the authenticated user's tokens.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->tokens()
                ->latest()
                ->get(['id', 'name', 'last_used_at', 'created_at']),
        ]);
    }

    /**
     * Revoke the token used for the current request (API logout).
     */
    public function destroyCurrent(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Token revoked.']);
    }

    /**
     * Revoke a specific token by id.
     */
    public function destroy(Request $request, int $tokenId): JsonResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return response()->json(['message' => 'Token revoked.']);
    }
}
