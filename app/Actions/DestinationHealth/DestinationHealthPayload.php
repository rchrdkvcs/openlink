<?php

namespace App\Actions\DestinationHealth;

use App\Enums\AnalyticsMetric;
use App\Models\AnalyticsEvent;
use App\Models\DestinationCheck;
use App\Models\ShortLink;
use App\Models\Workspace;
use App\Services\DestinationHealth\DestinationReferences;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DestinationHealthPayload
{
    public const RECOVERY_VISIBLE_DAYS = 7;

    public const TRAFFIC_DAYS = 7;

    public function __construct(private readonly DestinationReferences $references) {}

    public function forLinks(Workspace $workspace, array $links): array
    {
        $urls = collect($links)
            ->flatMap(fn (array $link) => [
                $link['destination_url'] ?? null,
                $link['fallback_url'] ?? null,
                ...collect($link['routing_rules'] ?? [])->flatMap(fn (array $rule) => [
                    $rule['destination_url'] ?? null,
                    ...collect($rule['variants'] ?? [])->pluck('destination_url'),
                ]),
            ])
            ->filter()
            ->unique()
            ->values();

        if ($urls->isEmpty()) {
            return [];
        }

        return $workspace->destinationChecks()
            ->whereIn('url_hash', $urls->map(fn (string $url) => DestinationCheck::hashFor($url)))
            ->get()
            ->map(fn (DestinationCheck $check) => $this->check($check))
            ->values()
            ->all();
    }

    public function issues(Workspace $workspace): array
    {
        $checks = $workspace->destinationChecks()
            ->referenced()
            ->where(fn (Builder $query) => $query
                ->whereNotNull('incident_opened_at')
                ->orWhere('recovered_at', '>=', now()->subDays(self::RECOVERY_VISIBLE_DAYS)))
            ->get();

        $links = $this->references->linksReferencing($workspace, $checks->pluck('url')->all());
        $traffic = $this->traffic($workspace, $links->pluck('id')->all());
        $referencesByUrl = $this->referencesByUrl($links, $traffic);

        $rows = $checks
            ->map(function (DestinationCheck $check) use ($referencesByUrl) {
                $references = $referencesByUrl->get($check->url, collect())->values();

                return [
                    ...$this->check($check),
                    'id' => $check->id,
                    'recent_traffic' => $references->unique('short_link.id')->sum('recent_traffic'),
                    'references' => $references->all(),
                ];
            })
            ->filter(fn (array $row) => $row['references'] !== []);

        return [
            'open' => $rows->where('has_incident', true)
                ->sortBy([['recent_traffic', 'desc'], ['incident_opened_at', 'asc']])
                ->values()
                ->all(),
            'recovered' => $rows->where('has_incident', false)
                ->sortByDesc('recovered_at')
                ->values()
                ->all(),
        ];
    }

    public function openCount(Workspace $workspace): int
    {
        return $workspace->destinationChecks()->referenced()->whereNotNull('incident_opened_at')->count();
    }

    public function check(DestinationCheck $check): array
    {
        return [
            'url' => $check->url,
            'status' => $check->status->value,
            'http_status' => $check->http_status,
            'detail' => $check->detail,
            'consecutive_failures' => $check->consecutive_failures,
            'checked_at' => $check->checked_at,
            'failing_since' => $check->failing_since,
            'incident_opened_at' => $check->incident_opened_at,
            'recovered_at' => $check->recovered_at,
            'has_incident' => $check->hasIncident(),
            'pending' => $check->isPending(),
            'stale' => $check->isStale(),
        ];
    }

    private function referencesByUrl(Collection $links, Collection $traffic): Collection
    {
        return $links
            ->flatMap(fn (ShortLink $link) => collect($this->references->forShortLink($link))
                ->map(fn (array $reference) => [
                    ...$reference,
                    'short_link' => ['id' => $link->id, 'slug' => $link->slug, 'short_url' => $link->shortUrl()],
                    'qr_codes' => $link->qrCodes->map->only(['id', 'name', 'token'])->values()->all(),
                    'recent_traffic' => (int) $traffic->get($link->id, 0),
                ]))
            ->groupBy('url');
    }

    private function traffic(Workspace $workspace, array $shortLinkIds): Collection
    {
        if ($shortLinkIds === []) {
            return collect();
        }

        return AnalyticsEvent::query()
            ->successful()
            ->where('workspace_id', $workspace->id)
            ->whereIn('short_link_id', $shortLinkIds)
            ->whereIn('metric', [AnalyticsMetric::Visit->value, AnalyticsMetric::Scan->value])
            ->where('occurred_at', '>=', now()->subDays(self::TRAFFIC_DAYS))
            ->groupBy('short_link_id')
            ->selectRaw('short_link_id, count(*) as aggregate')
            ->pluck('aggregate', 'short_link_id');
    }
}
