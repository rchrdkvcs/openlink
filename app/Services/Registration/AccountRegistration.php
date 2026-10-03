<?php

namespace App\Services\Registration;

use App\Actions\Domains\DomainLifecycle;
use App\Models\InviteLink;
use App\Models\User;
use App\Services\InstanceSettings;
use Illuminate\Auth\Events\Registered;
use Throwable;

class AccountRegistration
{
    public function __construct(
        private readonly InstanceSettings $settings,
        private readonly DomainLifecycle $domains,
    ) {}

    public function acceptsInvite(?InviteLink $inviteLink): bool
    {
        return $inviteLink !== null
            && $inviteLink->isUsable()
            && $this->settings->get('registration_mode') !== 'closed';
    }

    public function allowsNewUser(?InviteLink $inviteLink = null): bool
    {
        return ! User::query()->exists()
            || $this->settings->get('registration_mode') === 'open'
            || $this->acceptsInvite($inviteLink);
    }

    public function createUser(array $attributes): User
    {
        $isFirstUser = ! User::query()->exists();
        $user = User::create([
            ...$attributes,
            'is_instance_admin' => $isFirstUser,
        ]);

        if ($isFirstUser) {
            $this->domains->ensureDefaultDomain(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost');
        }

        return $user;
    }

    public function dispatchRegisteredAfterResponse(User $user): void
    {
        app()->terminating(function () use ($user): void {
            try {
                event(new Registered($user));
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
