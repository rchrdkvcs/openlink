<?php

namespace App\Enums;

enum DomainStatus: string
{
    case Pending = 'pending_verification';
    case Failed = 'failed_verification';
    case OwnershipVerified = 'ownership_verified';
    case Active = 'active';
    case Disabled = 'disabled';

    public static function awaitingChecks(): array
    {
        return [self::Pending, self::Failed, self::OwnershipVerified];
    }

    public function provesOwnership(): bool
    {
        return $this === self::OwnershipVerified || $this === self::Active;
    }
}
