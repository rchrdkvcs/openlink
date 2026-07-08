<?php

namespace App\Services\OAuth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConnectedIdentityManager
{
    /**
     * @throws ValidationException
     */
    public function link(User $user, OAuthProfile $profile): SocialAccount
    {
        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'oauth' => 'Verify your email before connecting this sign-in method.',
            ]);
        }

        if (! $profile->email || ! $profile->emailVerified) {
            throw ValidationException::withMessages([
                'oauth' => 'This provider did not return a verified email address.',
            ]);
        }

        if (! hash_equals((string) $user->email, (string) $profile->email)) {
            throw ValidationException::withMessages([
                'oauth' => 'This provider email must match your Openlink email address.',
            ]);
        }

        return DB::transaction(function () use ($user, $profile): SocialAccount {
            $account = SocialAccount::query()
                ->where('provider', $profile->provider)
                ->where('provider_user_id', $profile->providerUserId)
                ->lockForUpdate()
                ->first();

            if ($account && $account->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'oauth' => 'This sign-in method is already linked to another account.',
                ]);
            }

            $existingProvider = $user->socialAccounts()
                ->where('provider', $profile->provider)
                ->lockForUpdate()
                ->first();

            if ($existingProvider && (! $account || $existingProvider->id !== $account->id)) {
                throw ValidationException::withMessages([
                    'oauth' => 'This provider is already connected.',
                ]);
            }

            if (! $account) {
                $account = $user->socialAccounts()->create([
                    'provider' => $profile->provider,
                    'provider_user_id' => $profile->providerUserId,
                    'email' => $profile->email,
                    'email_verified' => $profile->emailVerified,
                    'avatar_url' => $profile->avatarUrl,
                ]);
            } else {
                $account->forceFill([
                    'email' => $profile->email,
                    'email_verified' => $profile->emailVerified,
                    'avatar_url' => $profile->avatarUrl,
                ])->save();
            }

            $user->refresh();

            if (! $user->profile_avatar_social_account_id && $account->avatar_url) {
                $user->forceFill(['profile_avatar_social_account_id' => $account->id])->save();
            } else {
                $user->refreshProfileAvatarSource();
            }

            return $account;
        });
    }

    /**
     * @throws ValidationException
     */
    public function unlink(User $user, SocialAccount $account): void
    {
        if ($account->user_id !== $user->id) {
            abort(404);
        }

        if ($user->validSignInMethodCount($account->id) < 1) {
            throw ValidationException::withMessages([
                'identity' => 'You must keep at least one valid sign-in method.',
            ]);
        }

        $wasAvatarSource = $user->profile_avatar_social_account_id === $account->id;

        $account->delete();

        if ($wasAvatarSource) {
            $user->refresh()->refreshProfileAvatarSource();
        }
    }

    /**
     * @throws ValidationException
     */
    public function selectAvatar(User $user, ?SocialAccount $account): void
    {
        if (! $account) {
            $user->forceFill(['profile_avatar_social_account_id' => null])->save();

            return;
        }

        if (! $user->isValidConnectedIdentity($account) || ! $account->avatar_url) {
            throw ValidationException::withMessages([
                'profile_avatar_social_account_id' => 'Choose a valid connected identity with an avatar.',
            ]);
        }

        $user->forceFill(['profile_avatar_social_account_id' => $account->id])->save();
    }
}
