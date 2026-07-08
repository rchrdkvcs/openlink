<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Services\OAuth\ConnectedIdentityManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class ProfileConnectedIdentityController extends Controller
{
    public function destroy(
        Request $request,
        SocialAccount $socialAccount,
        ConnectedIdentityManager $identities,
    ): RedirectResponse {
        $identities->unlink($request->user(), $socialAccount);

        return Redirect::route('profile.edit', ['tab' => 'connected-identities']);
    }
}
