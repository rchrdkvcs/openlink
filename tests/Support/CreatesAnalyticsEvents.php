<?php

namespace Tests\Support;

use App\Models\AnalyticsEvent;
use App\Models\ShortLink;
use App\Services\Analytics\Outcome;
use Illuminate\Support\Str;

trait CreatesAnalyticsEvents
{
    protected function analyticsEvent(ShortLink $link, array $attributes = []): AnalyticsEvent
    {
        return AnalyticsEvent::create([
            'workspace_id' => $link->workspace_id,
            'short_link_id' => $link->id,
            'domain_id' => $link->domain_id,
            'occurred_at' => now(),
            'metric' => 'visit',
            'outcome' => Outcome::SUCCESS,
            'is_bot' => false,
            'visitor_hash' => Str::random(32),
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'referrer_channel' => 'direct',
            ...$attributes,
        ]);
    }
}
