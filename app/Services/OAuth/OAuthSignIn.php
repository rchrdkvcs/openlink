<?php

namespace App\Services\OAuth;

use App\Actions\InviteLinks\JoinWorkspaceViaInviteLink;
use App\Models\InviteLink;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Registration\AccountRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OAuthSignIn
{
    public function __construct(
        private readonly JoinWorkspaceViaInviteLink $joiner,
        private readonly AccountRegistration $registration,
    ) {}

    public function userFor(OAuthProfile $profile, array $context = []): User
    {
        if (! $profile->email || ! $profile->emailVerified) {
            throw ValidationException::withMessages([
                'oauth' => 'This provider did not return a verified email address.',
            ]);
        }

        $inviteLink = $this->usableInviteLink($context['invite_token'] ?? null);

        [$user, $created] = DB::transaction(function () use ($profile, $inviteLink): array {
            $account = SocialAccount::query()
                ->where('provider', $profile->provider)
                ->where('provider_user_id', $profile->providerUserId)
                ->lockForUpdate()
                ->first();

            if ($account) {
                $user = $account->user()->firstOrFail();

                $matchingEmailUser = User::query()
                    ->where('email', $profile->email)
                    ->whereKeyNot($user->id)
                    ->first();

                if ($matchingEmailUser) {
                    throw ValidationException::withMessages([
                        'oauth' => 'This sign-in method is already linked to another account.',
                    ]);
                }

                if (! hash_equals((string) $user->email, (string) $profile->email)) {
                    throw ValidationException::withMessages([
                        'oauth' => 'This sign-in method no longer matches this account email.',
                    ]);
                }

                if (! $user->hasVerifiedEmail()) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                $this->syncSocialAccount($account, $profile);
                $user->refreshProfileAvatarSource();
                $this->joinViaInviteIfPresent($user, $inviteLink);

                return [$user, false];
            }

            $user = User::query()
                ->where('email', $profile->email)
                ->lockForUpdate()
                ->first();

            if ($user) {
                if (! $user->hasVerifiedEmail()) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }

                $this->createSocialAccount($user, $profile);
                $user->refreshProfileAvatarSource();
                $this->joinViaInviteIfPresent($user, $inviteLink);

                return [$user, false];
            }

            if (! $this->registration->allowsNewUser($inviteLink)) {
                throw ValidationException::withMessages([
                    'oauth' => 'Registration is not available. Use an invite link or sign in with an existing account.',
                ]);
            }

            $user = $this->registration->createUser([
                'name' => $this->nameForNewUser($profile),
                'email' => $profile->email,
                'email_verified_at' => now(),
                'password' => null,
            ]);

            $this->createSocialAccount($user, $profile);

            if ($inviteLink) {
                $member = $this->joiner->handle($user, $inviteLink);
                session()->put('workspace_id', $member->workspace_id);
            }

            return [$user, true];
        });

        if ($created) {
            $this->registration->dispatchRegisteredAfterResponse($user);
        }

        return $user;
    }

    private function usableInviteLink(?string $token): ?InviteLink
    {
        if (! $token) {
            return null;
        }

        $inviteLink = InviteLink::query()->where('token', $token)->first();

        return $inviteLink && $inviteLink->isUsable() ? $inviteLink : null;
    }

    private function joinViaInviteIfPresent(User $user, ?InviteLink $inviteLink): void
    {
        if (! $inviteLink) {
            return;
        }

        $member = $this->joiner->handle($user, $inviteLink);
        session()->put('workspace_id', $member->workspace_id);
    }

    private function createSocialAccount(User $user, OAuthProfile $profile): SocialAccount
    {
        return $user->socialAccounts()->create([
            'provider' => $profile->provider,
            'provider_user_id' => $profile->providerUserId,
            'email' => $profile->email,
            'email_verified' => $profile->emailVerified,
            'avatar_url' => $profile->avatarUrl,
        ]);
    }

    private function syncSocialAccount(SocialAccount $account, OAuthProfile $profile): void
    {
        $account->forceFill([
            'email' => $profile->email,
            'email_verified' => $profile->emailVerified,
            'avatar_url' => $profile->avatarUrl,
        ])->save();
    }

    private function nameForNewUser(OAuthProfile $profile): string
    {
        if ($profile->name) {
            return $profile->name;
        }

        return Str::of($profile->email ?? 'user')
            ->before('@')
            ->replace(['.', '_', '-'], ' ')
            ->headline()
            ->value();
    }
}
