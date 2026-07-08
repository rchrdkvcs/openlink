<?php

namespace App\Services\ShortLinks;

use App\Models\ShortLink;
use App\Services\Analytics\Outcome;

class ShortLinkLifecycle
{
    public function unavailableOutcome(ShortLink $shortLink): ?string
    {
        if ($shortLink->isArchived()) {
            return Outcome::ARCHIVED;
        }

        if (! $shortLink->is_enabled) {
            return Outcome::DISABLED;
        }

        if ($shortLink->activates_at && $shortLink->activates_at->isFuture()) {
            return Outcome::SCHEDULED;
        }

        if ($shortLink->expires_at && $shortLink->expires_at->isPast()) {
            return Outcome::EXPIRED;
        }

        if ($shortLink->visit_limit !== null && $shortLink->successful_visits >= $shortLink->visit_limit) {
            return Outcome::VISIT_LIMIT_REACHED;
        }

        return null;
    }

    public function status(ShortLink $shortLink): string
    {
        return match ($this->unavailableOutcome($shortLink)) {
            Outcome::ARCHIVED => 'archived',
            Outcome::DISABLED => 'disabled',
            Outcome::SCHEDULED => 'scheduled',
            Outcome::EXPIRED, Outcome::VISIT_LIMIT_REACHED => 'expired',
            default => 'active',
        };
    }

    public function passwordSessionKey(ShortLink $shortLink): string
    {
        return 'openlink_protected_link_'.$shortLink->id;
    }
}
