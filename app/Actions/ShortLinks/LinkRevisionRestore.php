<?php

namespace App\Actions\ShortLinks;

use App\Http\Requests\ShortLinks\ShortLinkRequest;
use App\Models\AnalyticsEvent;
use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use App\Models\ShortLink;
use App\Models\ShortLinkRevision;
use App\Models\User;
use App\Services\ShortLinks\History\LinkSnapshot;
use App\Services\ShortLinks\History\UrlRedaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LinkRevisionRestore
{
    private const RESTORABLE = [
        'destination_url',
        'fallback_url',
        'is_enabled',
        'is_archived',
        'activates_at',
        'expires_at',
        'visit_limit',
        'folder',
        'tags',
        'routing_rules',
        LinkSnapshot::DIGESTS,
    ];

    public function __construct(
        private readonly LinkSnapshot $snapshots,
        private readonly UrlRedaction $urls,
        private readonly ShortLinkMutation $mutation,
    ) {}

    public function preview(ShortLink $shortLink, ShortLinkRevision $revision): array
    {
        if ($revision->schema_version !== LinkSnapshot::SCHEMA_VERSION) {
            return ['restorable' => false, 'reason' => $this->unsupported($revision), 'changes' => [], 'notes' => []];
        }

        $current = $this->snapshots->capture($shortLink);
        $target = $this->resolvedTarget($shortLink, $revision->snapshot);
        $changes = $this->snapshots->diff(
            array_intersect_key($current, array_flip(self::RESTORABLE)),
            array_intersect_key($this->withoutMarkers($target), array_flip(self::RESTORABLE)),
        );
        $missing = $this->unresolvedUrls($shortLink, $target);
        $blocker = match (true) {
            $revision->number === $shortLink->revision => 'This is the current revision.',
            $changes === [] => 'This revision matches the current configuration.',
            $missing !== [] => 'History keeps no query strings or fragments, and this revision used '
                .implode(', ', $missing)
                .' with one the link no longer has. Edit the link to restore that URL by hand.',
            default => null,
        };

        return [
            'restorable' => $blocker === null,
            'reason' => $blocker,
            'changes' => $changes,
            'notes' => $blocker === null ? $this->notes($current, $revision->snapshot, $target) : [],
        ];
    }

    public function restore(User $user, ShortLink $shortLink, ShortLinkRevision $revision, int $expectedRevision): ShortLink
    {
        $preview = $this->preview($shortLink, $revision);

        if (! $preview['restorable']) {
            throw ValidationException::withMessages(['revision' => $preview['reason']]);
        }

        $target = $this->resolvedTarget($shortLink, $revision->snapshot);
        $urls = $this->urls->currentUrls($shortLink);
        $raw = fn (string $path) => $this->rawUrl($target, $path, $urls);
        $data = [
            'destination_url' => $raw('destination_url'),
            'fallback_url' => $raw('fallback_url'),
            'is_enabled' => $target['is_enabled'],
            'is_archived' => $target['is_archived'],
            'activates_at' => $target['activates_at'],
            'expires_at' => $target['expires_at'],
            'visit_limit' => $target['visit_limit'],
            'folder_id' => $target['folder']['id'] ?? null,
            'tags' => collect($target['tags'])->pluck('name')->implode(', '),
            'routing_rules' => array_map(fn (array $rule, int $index) => [
                ...$rule,
                'destination_url' => $raw("routing_rules.$index.destination_url"),
                'variants' => array_map(fn (array $variant, int $position) => [
                    ...$variant,
                    'destination_url' => $raw("routing_rules.$index.variants.$position.destination_url"),
                ], $rule['variants'], array_keys($rule['variants'])),
            ], $target['routing_rules'], array_keys($target['routing_rules'])),
        ];

        Validator::make($data, ShortLinkRequest::configurationRules())->validate();

        return $this->mutation->restore($user, $shortLink, $revision->number, $expectedRevision, $data);
    }

    private function unsupported(ShortLinkRevision $revision): string
    {
        return "Revision {$revision->number} was recorded in a format this version of Openlink can't restore. Copy its settings from the history and edit the link instead.";
    }

    private function resolvedTarget(ShortLink $shortLink, array $snapshot): array
    {
        $folder = isset($snapshot['folder']['id'])
            ? $shortLink->workspace->folders()->whereKey($snapshot['folder']['id'])->first()?->only(['id', 'name'])
            : null;
        $tags = $shortLink->workspace->tags()
            ->whereIn('name', array_column($snapshot['tags'] ?? [], 'name'))
            ->pluck('id', 'name');
        $current = $shortLink->routingRules()->with('variants')->get()->keyBy('id');
        $digests = $snapshot[LinkSnapshot::DIGESTS] ?? [];

        $rules = array_map(function (array $rule) use ($current): array {
            $existing = $current->get($rule['id'] ?? 0);
            $existing = $existing?->type === ($rule['type'] ?? RoutingRule::TYPE_CONDITIONAL) ? $existing : null;
            $variants = $existing?->variants->keyBy('id');

            return [
                ...$rule,
                'id' => $existing?->id,
                'variants' => array_map(fn (array $variant) => [
                    ...$variant,
                    'id' => $variants?->get($variant['id'] ?? 0)?->id,
                ], $rule['variants'] ?? []),
            ];
        }, array_values($snapshot['routing_rules'] ?? []));

        $attributed = $this->attributed($current);

        foreach ($rules as $index => $rule) {
            $kept = collect($rule['variants'])->pluck('id')->filter();

            foreach ($current->get($rule['id'] ?? 0)?->variants ?? [] as $variant) {
                if (! $kept->contains($variant->id) && $attributed['variants']->contains($variant->id)) {
                    $position = count($rules[$index]['variants']);
                    $rules[$index]['variants'][] = $this->retainedVariant($variant, "routing_rules.$index.variants.$position.destination_url", $digests);
                }
            }
        }

        $keptRules = collect($rules)->pluck('id')->filter();

        foreach ($current as $rule) {
            $referenced = $attributed['rules']->contains($rule->id)
                || $rule->variants->pluck('id')->intersect($attributed['variants'])->isNotEmpty();

            if (! $keptRules->contains($rule->id) && $referenced) {
                $index = count($rules);
                $rules[] = [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'type' => $rule->type,
                    'is_enabled' => false,
                    'match_mode' => $rule->match_mode,
                    'conditions' => $rule->conditions ?? [],
                    'destination_url' => $this->retainedUrl($rule->destination_url, "routing_rules.$index.destination_url", $digests),
                    'variants' => $rule->variants->values()->map(fn (RoutingVariant $variant, int $position) => $this->retainedVariant(
                        $variant,
                        "routing_rules.$index.variants.$position.destination_url",
                        $digests,
                        $variant->is_enabled,
                    ))->all(),
                    'retained' => true,
                ];
            }
        }

        return [
            ...$snapshot,
            'folder' => $folder,
            'tags' => array_map(fn (array $tag) => ['id' => $tags->get($tag['name']), 'name' => $tag['name']], $snapshot['tags'] ?? []),
            'routing_rules' => $rules,
            LinkSnapshot::DIGESTS => $digests,
        ];
    }

    private function withoutMarkers(array $target): array
    {
        $target['routing_rules'] = array_map(fn (array $rule) => [
            ...Arr::except($rule, 'retained'),
            'variants' => array_map(fn (array $variant) => Arr::except($variant, 'retained'), $rule['variants']),
        ], $target['routing_rules']);

        return $target;
    }

    private function attributed(Collection $rules): array
    {
        $variantIds = $rules->flatMap(fn (RoutingRule $rule) => $rule->variants->pluck('id'));

        return [
            'rules' => AnalyticsEvent::query()->whereIn('routing_rule_id', $rules->keys())->distinct()->pluck('routing_rule_id'),
            'variants' => AnalyticsEvent::query()->whereIn('routing_variant_id', $variantIds)->distinct()->pluck('routing_variant_id'),
        ];
    }

    private function retainedVariant(RoutingVariant $variant, string $path, array &$digests, bool $enabled = false): array
    {
        return [
            'id' => $variant->id,
            'name' => $variant->name,
            'is_enabled' => $enabled,
            'destination_url' => $this->retainedUrl($variant->destination_url, $path, $digests),
            'weight' => $variant->weight,
            'retained' => true,
        ];
    }

    private function retainedUrl(?string $url, string $path, array &$digests): ?string
    {
        if (($digest = $this->urls->digest($url)) !== null) {
            $digests[$path] = $digest;
        }

        return $this->urls->mask($url);
    }

    private function rawUrl(array $target, string $path, array $current): ?string
    {
        $digest = $target[LinkSnapshot::DIGESTS][$path] ?? null;

        return $digest === null ? Arr::get($target, $path) : ($current[$digest] ?? null);
    }

    private function unresolvedUrls(ShortLink $shortLink, array $target): array
    {
        $current = $this->urls->currentUrls($shortLink);

        return collect($target[LinkSnapshot::DIGESTS] ?? [])
            ->reject(fn (string $digest) => isset($current[$digest]))
            ->keys()
            ->map(fn (string $path) => Arr::get($target, $path))
            ->unique()
            ->values()
            ->all();
    }

    private function notes(array $current, array $snapshot, array $target): array
    {
        $notes = [];

        if (($snapshot['folder'] ?? null) !== null && $target['folder'] === null) {
            $notes[] = "The folder “{$snapshot['folder']['name']}” no longer exists. The link will be left without a folder.";
        }

        $recreatedTags = collect($target['tags'])->whereNull('id')->pluck('name');

        if ($recreatedTags->isNotEmpty()) {
            $notes[] = 'These tags were deleted and will be created again: '.$recreatedTags->implode(', ').'.';
        }

        $recreated = collect($target['routing_rules'])->whereNull('id')->pluck('name');

        if ($recreated->isNotEmpty()) {
            $notes[] = 'Routing rules deleted since this revision come back as new rules: '.$recreated->implode(', ').'.';
        }

        $retained = collect($target['routing_rules'])->flatMap(fn (array $rule) => ($rule['retained'] ?? false)
            ? [$rule['name']]
            : collect($rule['variants'])->where('retained', true)->pluck('name'));

        if ($retained->isNotEmpty()) {
            $notes[] = 'These routing rules and variants have analytics, so they stay on the link turned off instead of being deleted: '.$retained->implode(', ').'.';
        }

        if (($snapshot['has_password'] ?? false) !== $current['has_password']) {
            $notes[] = $current['has_password']
                ? 'Passwords are never restored. The link keeps its current password.'
                : 'Passwords are never restored. The link stays without a password.';
        }

        if (($snapshot['short_url'] ?? null) !== $current['short_url']) {
            $notes[] = "The short URL stays {$current['short_url']}. Restoring never changes the address.";
        }

        return $notes;
    }
}
