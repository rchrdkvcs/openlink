<?php

namespace Tests\Support;

use App\Models\SocialAccount;
use App\Models\User;

trait CreatesSocialAccounts
{
    protected function googleAccount(User $user, array $attributes = []): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-1',
            'email' => $user->email,
            'email_verified' => true,
            ...$attributes,
        ]);
    }
}
