<?php

namespace App\Services\ShortLinks\History;

use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use App\Models\ShortLink;
use App\Models\Tag;

class LinkSnapshot
{
    public const SCHEMA_VERSION = 1;

    public const RELATIONS = ['domain', 'folder', 'tags', 'routingRules.variants'];

    public function capture(ShortLink $shortLink): array
    {
        $link = ShortLink::query()->with(self::RELATIONS)->findOrFail($shortLink->id);

        return [
            'short_url' => $link->shortUrl(),
            'destination_url' => $link->destination_url,
            'fallback_url' => $link->fallback_url,
            'is_enabled' => $link->is_enabled,
            'is_archived' => $link->isArchived(),
            'activates_at' => $link->activates_at?->toIso8601String(),
            'expires_at' => $link->expires_at?->toIso8601String(),
            'visit_limit' => $link->visit_limit,
            'has_password' => $link->hasPassword(),
            'folder' => $link->folder?->only(['id', 'name']),
            'tags' => $link->tags
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn (Tag $tag) => $tag->only(['id', 'name']))
                ->values()
                ->all(),
            'routing_rules' => $link->routingRules->map(fn (RoutingRule $rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'type' => $rule->type,
                'is_enabled' => $rule->is_enabled,
                'match_mode' => $rule->match_mode,
                'conditions' => $rule->conditions ?? [],
                'destination_url' => $rule->destination_url,
                'variants' => $rule->variants->map(fn (RoutingVariant $variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'is_enabled' => $variant->is_enabled,
                    'destination_url' => $variant->destination_url,
                    'weight' => $variant->weight,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    public function diff(?array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $value) {
            $previous = $before[$field] ?? null;

            if ($before === null ? $this->isBlank($value) : $previous === $value) {
                continue;
            }

            $changes[] = ['field' => $field, 'before' => $previous, 'after' => $value];
        }

        return $changes;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === false || $value === [];
    }
}
