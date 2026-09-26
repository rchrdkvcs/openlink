<?php

namespace App\Http\Controllers;

use App\Services\InstanceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class ProfileApiTokenController extends Controller
{
    /**
     * Create an API token from the web profile.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        if (app(InstanceSettings::class)->get('require_email_verification') && ! $request->user()->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'name' => 'Verify your email before creating API tokens.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $token = $request->user()->createToken($data['name']);

        return Redirect::route('profile.edit', ['tab' => 'api-tokens'])
            ->with('newApiToken', [
                'name' => $data['name'],
                'token' => $token->plainTextToken,
            ]);
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()->tokens()->whereKey($tokenId)->delete();

        return Redirect::route('profile.edit', ['tab' => 'api-tokens']);
    }
}
