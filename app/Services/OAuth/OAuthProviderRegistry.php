<?php

namespace App\Services\OAuth;

class OAuthProviderRegistry
{
    /** @var array<int, string> */
    private const PROVIDERS = ['google', 'discord'];

    /**
     * @return array<string, bool>
     */
    public function availableProviders(): array
    {
        return collect(self::PROVIDERS)
            ->filter(fn (string $provider): bool => $this->isConfigured($provider))
            ->mapWithKeys(fn (string $provider): array => [$provider => true])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function scopes(string $provider): array
    {
        return match ($provider) {
            'google' => ['openid', 'profile', 'email'],
            'discord' => ['identify', 'email'],
            default => [],
        };
    }

    public function isSupported(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true);
    }

    public function isConfigured(string $provider): bool
    {
        if (! $this->isSupported($provider)) {
            return false;
        }

        $config = config("services.$provider", []);

        return match ($provider) {
            'google', 'discord' => filled($config['client_id'] ?? null)
                && filled($config['client_secret'] ?? null)
                && filled($config['redirect'] ?? null),
            default => false,
        };
    }
}
