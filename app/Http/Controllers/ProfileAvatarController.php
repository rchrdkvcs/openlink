<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Services\OAuth\ConnectedIdentityManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class ProfileAvatarController extends Controller
{
    public function update(Request $request, ConnectedIdentityManager $identities): RedirectResponse
    {
        $data = $request->validate([
            'profile_avatar_social_account_id' => ['nullable', 'integer'],
        ]);

        $account = isset($data['profile_avatar_social_account_id'])
            ? SocialAccount::query()
                ->where('user_id', $request->user()->id)
                ->findOrFail($data['profile_avatar_social_account_id'])
            : null;

        $identities->selectAvatar($request->user(), $account);

        return Redirect::route('profile.edit', ['tab' => 'profile']);
    }
}
