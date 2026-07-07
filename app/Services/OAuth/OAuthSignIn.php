<?php

namespace App\Services\OAuth;

use App\Actions\InviteLinks\JoinWorkspaceViaInviteLink;
use App\Models\Domain;
use App\Models\InviteLink;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\InstanceSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OAuthSignIn
{
    public function __construct(private readonly JoinWorkspaceViaInviteLink $joiner)
    {
        //
    }

    /**
     * @param  array{invite_token?: string|null}  $context
     *
     * @throws ValidationException
     */
    public function userFor(OAuthProfile $profile, array $context = []): User
    {
        if (! $profile->email || ! $profile->emailVerified) {
            throw ValidationException::withMessages([
                'oauth' => 'This provider did not return a verified email address.',
            ]);
        }

        $inviteLink = $this->usableInviteLink($context['invite_token'] ?? null);

        return DB::transaction(function () use ($profile, $inviteLink): User {
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

                $this->syncSocialAccount($account, $profile);
                $this->joinViaInviteIfPresent($user, $inviteLink);

                return $user;
            }

            $user = User::query()
                ->where('email', $profile->email)
                ->lockForUpdate()
                ->first();

            if ($user) {
                $this->createSocialAccount($user, $profile);
                $this->joinViaInviteIfPresent($user, $inviteLink);

                return $user;
            }

            $this->ensureRegistrationIsAllowed($inviteLink);

            $isFirstUser = ! User::query()->exists();
            $user = User::create([
                'name' => $this->nameForNewUser($profile),
                'email' => $profile->email,
                'email_verified_at' => now(),
                'password' => null,
                'is_instance_admin' => $isFirstUser,
            ]);

            $this->createSocialAccount($user, $profile);

            if ($isFirstUser) {
                $this->createDefaultDomain();
            }

            if ($inviteLink) {
                $member = $this->joiner->handle($user, $inviteLink);
                session()->put('workspace_id', $member->workspace_id);
            }

            event(new Registered($user));

            return $user;
        });
    }

    private function usableInviteLink(?string $token): ?InviteLink
    {
        if (! $token) {
            return null;
        }

        $inviteLink = InviteLink::query()->where('token', $token)->first();

        return $inviteLink && $inviteLink->isUsable() ? $inviteLink : null;
    }

    /**
     * @throws ValidationException
     */
    private function ensureRegistrationIsAllowed(?InviteLink $inviteLink): void
    {
        if (! User::query()->exists()) {
            return;
        }

        $mode = app(InstanceSettings::class)->get('registration_mode');

        if ($mode === 'open' || ($mode !== 'closed' && $inviteLink)) {
            return;
        }

        throw ValidationException::withMessages([
            'oauth' => 'Registration is not available. Use an invite link or sign in with an existing account.',
        ]);
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

    private function createDefaultDomain(): void
    {
        Domain::query()->firstOrCreate([
            'hostname' => parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost',
        ], [
            'workspace_id' => null,
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => Str::random(40),
            'is_default' => true,
            'verified_at' => now(),
            'dns_pointed_at' => now(),
        ]);
    }
}
