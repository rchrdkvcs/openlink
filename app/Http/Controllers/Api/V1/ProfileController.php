<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use PragmaRX\Google2FA\Google2FA;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'email_verified_at', 'is_instance_admin']),
            'two_factor_enabled' => (bool) $user->two_factor_confirmed_at,
            'workspaces' => $user->workspaces()
                ->orderBy('name')
                ->get(['workspaces.id', 'workspaces.name', 'workspaces.slug'])
                ->map(fn ($workspace) => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                    'role' => $workspace->pivot->role,
                ]),
        ]);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email', 'email_verified_at', 'is_instance_admin']),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => 'Password updated.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted.']);
    }

    public function prepareTwoFactor(Request $request): JsonResponse
    {
        $secret = (new Google2FA)->generateSecretKey();

        $request->user()->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => (new Google2FA)->getQRCodeUrl(config('app.name'), $request->user()->email, $secret),
        ]);
    }

    public function confirmTwoFactor(Request $request): JsonResponse
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

        return response()->json(['message' => 'Two-factor authentication enabled.']);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }
}
