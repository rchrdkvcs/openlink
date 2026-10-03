<?php

namespace App\Services\ShortLinks;

use App\Enums\LinkStatus;
use App\Models\ShortLink;
use App\Services\Analytics\Outcome;
use Illuminate\Contracts\Database\Eloquent\Builder;

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

    public function statusOf(ShortLink $shortLink): LinkStatus
    {
        return match ($this->unavailableOutcome($shortLink)) {
            Outcome::ARCHIVED => LinkStatus::Archived,
            Outcome::DISABLED => LinkStatus::Disabled,
            Outcome::SCHEDULED => LinkStatus::Scheduled,
            Outcome::EXPIRED, Outcome::VISIT_LIMIT_REACHED => LinkStatus::Expired,
            default => LinkStatus::Active,
        };
    }

    public function status(ShortLink $shortLink): string
    {
        return $this->statusOf($shortLink)->value;
    }

    public function whereStatus(Builder $query, LinkStatus $status): void
    {
        $now = now()->toDateTimeString();
        $expression = "CASE WHEN archived_at IS NOT NULL THEN 'archived' "
            ."WHEN is_enabled = false THEN 'disabled' "
            ."WHEN activates_at IS NOT NULL AND activates_at > ? THEN 'scheduled' "
            .'WHEN (expires_at IS NOT NULL AND expires_at < ?) '
            ."OR (visit_limit IS NOT NULL AND successful_visits >= visit_limit) THEN 'expired' "
            ."ELSE 'active' END";

        $query->whereRaw("{$expression} = ?", [$now, $now, $status->value]);
    }

    public function reserveVisit(ShortLink $shortLink): bool
    {
        return ShortLink::query()
            ->whereKey($shortLink->id)
            ->where(fn ($query) => $query
                ->whereNull('visit_limit')
                ->orWhereColumn('successful_visits', '<', 'visit_limit'))
            ->increment('successful_visits') === 1;
    }
}
