<?php

namespace App\Services\Registration;

use App\Models\Domain;
use App\Models\InviteLink;
use App\Models\User;
use App\Services\InstanceSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;
use Throwable;

class AccountRegistration
{
    public function __construct(private readonly InstanceSettings $settings) {}

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
