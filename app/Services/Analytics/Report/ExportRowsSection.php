<?php

namespace App\Services\Analytics\Report;

use Generator;

class ExportRowsSection
{
    /** @return Generator<int, list<string|null>, void, void> */
    public function rows(AnalyticsEventSlice $slice): Generator
    {
        $query = $slice->ordered()
            ->with(['shortLink:id,slug', 'qrCode:id,name', 'domain:id,hostname', 'routingRule:id,name', 'routingVariant:id,name']);

        foreach ($query->lazy(1000) as $event) {
            yield [
                $event->occurred_at->toIso8601String(),
                $event->metric,
                $event->outcome,
                $event->shortLink?->slug,
                $event->qrCode?->name,
                $event->domain?->hostname,
                $event->routingRule?->name,
                $event->routingVariant?->name,
                $event->referrer_host,
                $event->referrer_channel,
                $event->country,
                $event->language,
                $event->device_type,
                $event->browser,
                $event->os,
                $event->is_bot ? '1' : '0',
                $event->utm_source,
                $event->utm_medium,
                $event->utm_campaign,
                $event->utm_term,
                $event->utm_content,
            ];
        }
    }
}
