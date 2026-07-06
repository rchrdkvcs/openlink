<?php

namespace App\Services\Analytics;

/**
 * Resolution outcomes recorded with every analytics event. `success` means
 * the visitor reached the destination URL; everything else explains why the
 * short link did not resolve.
 */
final class Outcome
{
    public const SUCCESS = 'success';

    public const PASSWORD_FAILED = 'password_failed';

    public const EXPIRED = 'expired';

    public const DISABLED = 'disabled';

    public const SCHEDULED = 'scheduled';

    public const NOT_FOUND = 'not_found';

    public const DOMAIN_UNAVAILABLE = 'domain_unavailable';

    public const VISIT_LIMIT_REACHED = 'visit_limit_reached';

    public const ARCHIVED = 'archived';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::SUCCESS,
            self::PASSWORD_FAILED,
            self::EXPIRED,
            self::DISABLED,
            self::SCHEDULED,
            self::NOT_FOUND,
            self::DOMAIN_UNAVAILABLE,
            self::VISIT_LIMIT_REACHED,
            self::ARCHIVED,
        ];
    }

    /** @return list<string> */
    public static function blocked(): array
    {
        return array_values(array_diff(self::all(), [self::SUCCESS]));
    }
}
