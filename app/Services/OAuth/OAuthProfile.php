<?php

namespace App\Services\OAuth;

use Laravel\Socialite\Contracts\User as SocialiteUser;

class OAuthProfile
{
    public function __construct(
        public readonly string $provider,
        public readonly string $providerUserId,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly ?string $name,
        public readonly ?string $avatarUrl,
    ) {
        //
    }

    public static function fromSocialiteUser(string $provider, SocialiteUser $user): self
    {
        $raw = method_exists($user, 'getRaw') ? $user->getRaw() : [];
        $email = $user->getEmail();

        return new self(
            provider: $provider,
            providerUserId: (string) $user->getId(),
            email: is_string($email) && $email !== '' ? strtolower($email) : null,
            emailVerified: self::emailIsVerified($provider, is_array($raw) ? $raw : []),
            name: self::cleanString($user->getName()),
            avatarUrl: self::cleanString($user->getAvatar()),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function emailIsVerified(string $provider, array $raw): bool
    {
        $value = match ($provider) {
            'google' => $raw['email_verified'] ?? false,
            'discord' => $raw['verified'] ?? false,
            default => false,
        };

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
