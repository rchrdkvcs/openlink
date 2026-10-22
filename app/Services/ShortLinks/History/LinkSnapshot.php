<?php

namespace App\Services\ShortLinks\History;

use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use App\Models\ShortLink;
use App\Models\Tag;
use Illuminate\Support\Arr;

class LinkSnapshot
{
    public const SCHEMA_VERSION = 1;

    public const DIGESTS = 'url_digests';

    public const RELATIONS = ['domain', 'folder', 'tags', 'routingRules.variants'];

    public function __construct(private readonly UrlRedaction $urls) {}

    public function capture(ShortLink $shortLink): array
    {
        $link = ShortLink::query()->with(self::RELATIONS)->findOrFail($shortLink->id);
        $digests = [];
        $url = function (string $path, ?string $value) use (&$digests): ?string {
            if (($digest = $this->urls->digest($value)) !== null) {
                $digests[$path] = $digest;
            }

            return $this->urls->mask($value);
        };

        $snapshot = [
            'short_url' => $link->shortUrl(),
            'destination_url' => $url('destination_url', $link->destination_url),
            'fallback_url' => $url('fallback_url', $link->fallback_url),
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
            'routing_rules' => $link->routingRules->values()->map(fn (RoutingRule $rule, int $index) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'type' => $rule->type,
                'is_enabled' => $rule->is_enabled,
                'match_mode' => $rule->match_mode,
                'conditions' => $rule->conditions ?? [],
                'destination_url' => $url("routing_rules.$index.destination_url", $rule->destination_url),
                'variants' => $rule->variants->values()->map(fn (RoutingVariant $variant, int $position) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'is_enabled' => $variant->is_enabled,
                    'destination_url' => $url("routing_rules.$index.variants.$position.destination_url", $variant->destination_url),
                    'weight' => $variant->weight,
                ])->values()->all(),
            ])->all(),
        ];

        return [...$snapshot, self::DIGESTS => $digests];
    }

    public function diff(?array $before, array $after): array
    {
        $changes = [];

        foreach (Arr::except($after, self::DIGESTS) as $field => $value) {
            $previous = $before[$field] ?? null;
            $unchanged = $previous === $value
                && $this->digestsOf($before ?? [], $field) === $this->digestsOf($after, $field);

            if ($before === null ? $this->isBlank($value) : $unchanged) {
                continue;
            }

            $changes[] = ['field' => $field, 'before' => $previous, 'after' => $value];
        }

        return $changes;
    }

    private function digestsOf(array $snapshot, string $field): array
    {
        return array_filter(
            $snapshot[self::DIGESTS] ?? [],
            fn (string $path) => $path === $field || str_starts_with($path, "$field."),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === false || $value === [];
    }
}
