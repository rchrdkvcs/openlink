<?php

namespace Tests\Support;

use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;

trait FakesOAuthProviders
{
    protected function configureGoogle(): void
    {
        config()->set('services.google.client_id', 'google-client');
        config()->set('services.google.client_secret', 'google-secret');
        config()->set('services.google.redirect', 'http://localhost/auth/google/callback');
    }

    protected function configureDiscord(): void
    {
        config()->set('services.discord.client_id', 'discord-client');
        config()->set('services.discord.client_secret', 'discord-secret');
        config()->set('services.discord.redirect', 'http://localhost/auth/discord/callback');
    }

    protected function mockSocialiteUser(string $provider, array $attributes): void
    {
        $user = SocialiteUser::fake([
            'id' => $attributes['id'],
            'name' => $attributes['name'] ?? 'OAuth User',
            'email' => $attributes['email'] ?? null,
            'avatar' => $attributes['avatar'] ?? null,
            ...$attributes,
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($user);

        Socialite::shouldReceive('driver')
            ->once()
            ->with($provider)
            ->andReturn($driver);
    }

    protected function googleCallback(array $context = []): TestResponse
    {
        return $this->withSession(['oauth.context' => ['provider' => 'google', ...$context]])
            ->get(route('oauth.callback', ['provider' => 'google']));
    }
}
