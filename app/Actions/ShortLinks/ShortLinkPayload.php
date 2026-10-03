<?php

namespace App\Actions\ShortLinks;

use App\Enums\AnalyticsMetric;
use App\Models\ShortLink;
use App\Services\ShortLinks\ShortLinkLifecycle;

class ShortLinkPayload
{
    public const RELATIONS = ['domain', 'folder', 'tags', 'routingRules.variants'];

    public function __construct(private readonly ShortLinkLifecycle $lifecycle) {}

    public static function analyticsCounts(): array
    {
        return [
            'analyticsEvents as visits_count' => fn ($query) => $query->successful()->where('metric', AnalyticsMetric::Visit->value),
            'analyticsEvents as scans_count' => fn ($query) => $query->successful()->where('metric', AnalyticsMetric::Scan->value),
        ];
    }

    public function handle(ShortLink $link): array
    {
        $this->loadMissing($link);

        return [
            'id' => $link->id,
            'slug' => $link->slug,
            'short_url' => $link->shortUrl(),
            'destination_url' => $link->destination_url,
            'fallback_url' => $link->fallback_url,
            'status' => $this->lifecycle->status($link),
            'domain' => $link->domain?->only(['id', 'hostname', 'status', 'is_default']),
            'folder' => $link->folder?->only(['id', 'name']),
            'tags' => $link->tags->map->only(['id', 'name'])->values(),
            'qr_code_count' => (int) $link->qr_codes_count,
            'visits' => (int) $link->visits_count,
            'scans' => (int) $link->scans_count,
            'is_enabled' => $link->is_enabled,
            'archived_at' => $link->archived_at,
            'created_at' => $link->created_at,
            'activates_at' => $link->activates_at,
            'expires_at' => $link->expires_at,
            'visit_limit' => $link->visit_limit,
            'successful_visits' => $link->successful_visits,
            'has_password' => $link->hasPassword(),
            'routing_rules' => $link->routingRules->map(fn ($rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'type' => $rule->type,
                'position' => $rule->position,
                'is_enabled' => $rule->is_enabled,
                'match_mode' => $rule->match_mode,
                'conditions' => $rule->conditions ?? [],
                'destination_url' => $rule->destination_url,
                'variants' => $rule->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'position' => $variant->position,
                    'is_enabled' => $variant->is_enabled,
                    'destination_url' => $variant->destination_url,
                    'weight' => $variant->weight,
                ])->values(),
            ])->values(),
        ];
    }

    private function loadMissing(ShortLink $link): void
    {
        $link->loadMissing(self::RELATIONS);

        if (! isset($link->qr_codes_count)) {
            $link->loadCount('qrCodes');
        }

        if (! isset($link->visits_count) || ! isset($link->scans_count)) {
            $link->loadCount(self::analyticsCounts());
        }
    }
}
