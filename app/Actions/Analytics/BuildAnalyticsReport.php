<?php

namespace App\Actions\Analytics;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Actions\Workspaces\WorkspacePayloads;
use App\Models\AnalyticsEvent;
use App\Models\QrCode;
use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Analytics\Outcome;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read side of analytics: turns raw analytics_events rows into the report
 * consumed by the dashboard, the analytics page, and the API. Every figure
 * is computed against the same AnalyticsFilters slice.
 */
class BuildAnalyticsReport
{
    private const BREAKDOWN_LIMIT = 12;

    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly WorkspacePayloads $workspacePayloads,
    ) {}

    /**
     * @param  list<int>|null  $accessibleLinkIds  Restricts the report to these
     *                                             short links (folder-scoped members); null means no restriction.
     * @return array<string, mixed>
     */
    public function report(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null): array
    {
        return [
            'range' => [
                'preset' => $filters->range,
                'from' => $filters->from->toIso8601String(),
                'to' => $filters->to->toIso8601String(),
                'bucket' => $filters->bucketUnit(),
            ],
            'summary' => $this->summary($workspace, $filters, $accessibleLinkIds),
            'timeseries' => $this->timeseries($workspace, $filters, $accessibleLinkIds),
            'breakdowns' => [
                'referrers' => $this->breakdown($workspace, $filters, $accessibleLinkIds, 'referrer_host'),
                'channels' => $this->breakdown($workspace, $filters, $accessibleLinkIds, 'referrer_channel'),
                'countries' => $this->breakdown($workspace, $filters, $accessibleLinkIds, 'country'),
                'languages' => $this->breakdown($workspace, $filters, $accessibleLinkIds, 'language'),
                'devices' => $this->breakdown($workspace, $filters, $accessibleLinkIds, 'device_type'),
                'browsers' => $this->breakdown($workspace, $filters, $accessibleLinkIds, 'browser'),
                'os' => $this->breakdown($workspace, $filters, $accessibleLinkIds, 'os'),
                'utm_sources' => $this->breakdown($workspace, $filters, $accessibleLinkIds, 'utm_source'),
                'utm_mediums' => $this->breakdown($workspace, $filters, $accessibleLinkIds, 'utm_medium'),
                'utm_campaigns' => $this->breakdown($workspace, $filters, $accessibleLinkIds, 'utm_campaign'),
            ],
            'outcomes' => $this->outcomes($workspace, $filters, $accessibleLinkIds),
            'routing' => $this->routingPerformance($workspace, $filters, $accessibleLinkIds),
            'top_links' => $this->topLinks($workspace, $filters, $accessibleLinkIds),
            'top_qr_codes' => $this->topQrCodes($workspace, $filters, $accessibleLinkIds),
        ];
    }

    /** @return array<string, mixed> */
    public function summary(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null): array
    {
        $current = $this->totals($workspace, $filters, $accessibleLinkIds);
        $previous = $this->totals($workspace, $filters->previous(), $accessibleLinkIds);

        $resolved = $current['visits'] + $current['scans'];
        $attempts = $resolved + $current['blocked'];

        return [
            ...$current,
            'success_rate' => $attempts > 0 ? round($resolved / $attempts * 100, 1) : null,
            'visits_change' => $this->change($current['visits'], $previous['visits']),
            'scans_change' => $this->change($current['scans'], $previous['scans']),
            'visitors_change' => $this->change($current['visitors'], $previous['visitors']),
            'blocked_change' => $this->change($current['blocked'], $previous['blocked']),
        ];
    }

    /**
     * Time series of successful traffic plus blocked attempts, with every
     * bucket of the range present (zero-filled) so charts never skip time.
     *
     * @return list<array{bucket: string, visits: int, scans: int, visitors: int, blocked: int}>
     */
    public function timeseries(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null): array
    {
        $unit = $filters->bucketUnit();
        $expression = $this->bucketExpression($unit);

        $successful = $this->events($workspace, $filters, $accessibleLinkIds)
            ->successful()
            ->selectRaw("{$expression} as bucket")
            ->selectRaw("sum(case when metric = 'visit' then 1 else 0 end) as visits")
            ->selectRaw("sum(case when metric = 'scan' then 1 else 0 end) as scans")
            ->selectRaw('count(distinct visitor_hash) as visitors')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $blocked = $this->events($workspace, $filters, $accessibleLinkIds)
            ->where('outcome', '!=', Outcome::SUCCESS)
            ->where('is_bot', false)
            ->selectRaw("{$expression} as bucket, count(*) as blocked")
            ->groupBy('bucket')
            ->pluck('blocked', 'bucket');

        $series = [];

        foreach ($this->buckets($filters->from, $filters->to, $unit) as $bucket) {
            $row = $successful->get($bucket);

            $series[] = [
                'bucket' => $bucket,
                'visits' => (int) ($row->visits ?? 0),
                'scans' => (int) ($row->scans ?? 0),
                'visitors' => (int) ($row->visitors ?? 0),
                'blocked' => (int) ($blocked[$bucket] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Top values of one dimension across successful traffic, with each
     * value's share of the dimension's total.
     *
     * @return list<array{label: string, count: int, share: float}>
     */
    public function breakdown(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds, string $column): array
    {
        $rows = $this->events($workspace, $filters, $accessibleLinkIds)
            ->successful()
            ->whereNotNull($column)
            ->selectRaw("{$column} as label, count(*) as count, count(distinct visitor_hash) as visitors")
            ->groupBy($column)
            ->orderByDesc('count')
            ->limit(self::BREAKDOWN_LIMIT)
            ->get();

        $total = (int) $this->events($workspace, $filters, $accessibleLinkIds)
            ->successful()
            ->whereNotNull($column)
            ->count();

        return $rows->map(fn ($row) => [
            'label' => (string) $row->label,
            'count' => (int) $row->count,
            'visitors' => (int) $row->visitors,
            'share' => $total > 0 ? round($row->count / $total * 100, 1) : 0.0,
        ])->all();
    }

    /**
     * How resolution attempts ended (human traffic only), most common first.
     *
     * @return list<array{outcome: string, count: int, share: float}>
     */
    public function outcomes(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null): array
    {
        $rows = $this->events($workspace, $filters, $accessibleLinkIds)
            ->where('is_bot', false)
            ->selectRaw('outcome, count(*) as count')
            ->groupBy('outcome')
            ->orderByDesc('count')
            ->get();

        $total = (int) $rows->sum('count');

        return $rows->map(fn ($row) => [
            'outcome' => (string) $row->outcome,
            'count' => (int) $row->count,
            'share' => $total > 0 ? round($row->count / $total * 100, 1) : 0.0,
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    public function topLinks(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null, int $limit = 10): array
    {
        $rows = $this->events($workspace, $filters, $accessibleLinkIds)
            ->successful()
            ->whereNotNull('short_link_id')
            ->selectRaw('short_link_id, count(*) as total')
            ->selectRaw("sum(case when metric = 'visit' then 1 else 0 end) as visits")
            ->selectRaw("sum(case when metric = 'scan' then 1 else 0 end) as scans")
            ->selectRaw('count(distinct visitor_hash) as visitors')
            ->groupBy('short_link_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $links = ShortLink::query()->with('domain')->findMany($rows->pluck('short_link_id'))->keyBy('id');

        return $rows->map(function ($row) use ($links) {
            $link = $links->get($row->short_link_id);

            return [
                'id' => $row->short_link_id,
                'slug' => $link?->slug ?? '(deleted link)',
                'short_url' => $link && $link->domain ? 'https://'.$link->domain->hostname.'/'.$link->slug : null,
                'destination_url' => $link?->destination_url,
                'visits' => (int) $row->visits,
                'scans' => (int) $row->scans,
                'visitors' => (int) $row->visitors,
                'total' => (int) $row->total,
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    public function topQrCodes(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null, int $limit = 10): array
    {
        $rows = $this->events($workspace, $filters, $accessibleLinkIds)
            ->successful()
            ->whereNotNull('qr_code_id')
            ->selectRaw('qr_code_id, count(*) as scans, count(distinct visitor_hash) as visitors')
            ->groupBy('qr_code_id')
            ->orderByDesc('scans')
            ->limit($limit)
            ->get();

        $qrCodes = QrCode::query()->with('shortLink')->findMany($rows->pluck('qr_code_id'))->keyBy('id');

        return $rows->map(function ($row) use ($qrCodes) {
            $qrCode = $qrCodes->get($row->qr_code_id);

            return [
                'id' => $row->qr_code_id,
                'name' => $qrCode?->name ?? '(deleted QR code)',
                'link_slug' => $qrCode?->shortLink?->slug,
                'scans' => (int) $row->scans,
                'visitors' => (int) $row->visitors,
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    public function routingPerformance(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null): array
    {
        $rows = $this->events($workspace, $filters, $accessibleLinkIds)
            ->successful()
            ->selectRaw('routing_rule_id, routing_variant_id, count(*) as total')
            ->selectRaw("sum(case when metric = 'visit' then 1 else 0 end) as visits")
            ->selectRaw("sum(case when metric = 'scan' then 1 else 0 end) as scans")
            ->selectRaw('count(distinct visitor_hash) as visitors')
            ->groupBy('routing_rule_id', 'routing_variant_id')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $rules = RoutingRule::query()->findMany($rows->pluck('routing_rule_id')->filter())->keyBy('id');
        $variants = RoutingVariant::query()->findMany($rows->pluck('routing_variant_id')->filter())->keyBy('id');

        return $rows->map(function ($row) use ($rules, $variants) {
            $rule = $rules->get($row->routing_rule_id);
            $variant = $variants->get($row->routing_variant_id);

            return [
                'routing_rule_id' => $row->routing_rule_id ? (int) $row->routing_rule_id : null,
                'routing_variant_id' => $row->routing_variant_id ? (int) $row->routing_variant_id : null,
                'rule_name' => $rule?->name ?? 'Default destination',
                'variant_name' => $variant?->name,
                'visits' => (int) $row->visits,
                'scans' => (int) $row->scans,
                'visitors' => (int) $row->visitors,
                'total' => (int) $row->total,
            ];
        })->all();
    }

    /**
     * Short link ids a member may see analytics for, or null when the member
     * manages the workspace and sees everything.
     *
     * @return list<int>|null
     */
    public function accessibleLinkIds(Workspace $workspace, User $user): ?array
    {
        if ($this->access->canManageWorkspace($user, $workspace)) {
            return null;
        }

        $accessibleFolderIds = $this->workspacePayloads->folders($workspace, $user)->pluck('id');

        return $workspace->shortLinks()
            ->where(fn ($query) => $query->whereNull('folder_id')->orWhereIn('folder_id', $accessibleFolderIds))
            ->pluck('id')
            ->all();
    }

    /**
     * @return Generator<int, list<string|null>, void, void>
     */
    public function exportRows(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null): Generator
    {
        $query = $this->eventsQuery($workspace, $filters, $accessibleLinkIds)
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

    private function eventsQuery(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null): Builder
    {
        return $this->events($workspace, $filters, $accessibleLinkIds)->orderBy('occurred_at');
    }

    private function events(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds): Builder
    {
        return AnalyticsEvent::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('occurred_at', [$filters->from, $filters->to])
            ->when($accessibleLinkIds !== null, fn (Builder $query) => $query->whereIn('short_link_id', $accessibleLinkIds))
            ->when($filters->shortLinkId, fn (Builder $query, int $id) => $query->where('short_link_id', $id))
            ->when($filters->qrCodeId, fn (Builder $query, int $id) => $query->where('qr_code_id', $id))
            ->when($filters->domainId, fn (Builder $query, int $id) => $query->where('domain_id', $id))
            ->when($filters->routingRuleId, fn (Builder $query, int $id) => $query->where('routing_rule_id', $id))
            ->when($filters->routingVariantId, fn (Builder $query, int $id) => $query->where('routing_variant_id', $id))
            ->when($filters->metric, fn (Builder $query, string $metric) => $query->where('metric', $metric))
            ->when($filters->folderId, fn (Builder $query, int $id) => $query->whereIn(
                'short_link_id',
                ShortLink::query()->where('workspace_id', $workspace->id)->where('folder_id', $id)->select('id'),
            ))
            ->when($filters->tagId, fn (Builder $query, int $id) => $query->whereIn(
                'short_link_id',
                DB::table('short_link_tag')->where('tag_id', $id)->select('short_link_id'),
            ));
    }

    /** @return array{visits: int, scans: int, visitors: int, blocked: int, bots: int, active_links: int} */
    private function totals(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds): array
    {
        $row = $this->events($workspace, $filters, $accessibleLinkIds)
            ->selectRaw("sum(case when outcome = 'success' and is_bot = false and metric = 'visit' then 1 else 0 end) as visits")
            ->selectRaw("sum(case when outcome = 'success' and is_bot = false and metric = 'scan' then 1 else 0 end) as scans")
            ->selectRaw("count(distinct case when outcome = 'success' and is_bot = false then visitor_hash end) as visitors")
            ->selectRaw("sum(case when outcome != 'success' and is_bot = false then 1 else 0 end) as blocked")
            ->selectRaw('sum(case when is_bot = true then 1 else 0 end) as bots')
            ->selectRaw("count(distinct case when outcome = 'success' and is_bot = false then short_link_id end) as active_links")
            ->first();

        return [
            'visits' => (int) ($row->visits ?? 0),
            'scans' => (int) ($row->scans ?? 0),
            'visitors' => (int) ($row->visitors ?? 0),
            'blocked' => (int) ($row->blocked ?? 0),
            'bots' => (int) ($row->bots ?? 0),
            'active_links' => (int) ($row->active_links ?? 0),
        ];
    }

    private function change(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    private function bucketExpression(string $unit): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'pgsql' => match ($unit) {
                'hour' => "to_char(occurred_at, 'YYYY-MM-DD HH24:00')",
                'month' => "to_char(occurred_at, 'YYYY-MM')",
                default => "to_char(occurred_at, 'YYYY-MM-DD')",
            },
            'sqlite' => match ($unit) {
                'hour' => "strftime('%Y-%m-%d %H:00', occurred_at)",
                'month' => "strftime('%Y-%m', occurred_at)",
                default => "strftime('%Y-%m-%d', occurred_at)",
            },
            default => match ($unit) {
                'hour' => "date_format(occurred_at, '%Y-%m-%d %H:00')",
                'month' => "date_format(occurred_at, '%Y-%m')",
                default => "date_format(occurred_at, '%Y-%m-%d')",
            },
        };
    }

    /** @return list<string> */
    private function buckets(CarbonImmutable $from, CarbonImmutable $to, string $unit): array
    {
        [$step, $format] = match ($unit) {
            'hour' => ['addHour', 'Y-m-d H:00'],
            'month' => ['addMonth', 'Y-m'],
            default => ['addDay', 'Y-m-d'],
        };

        $buckets = [];
        $cursor = $from->startOf($unit === 'hour' ? 'hour' : ($unit === 'month' ? 'month' : 'day'));

        while ($cursor->lessThanOrEqualTo($to) && count($buckets) < 800) {
            $buckets[] = $cursor->format($format);
            $cursor = $cursor->{$step}();
        }

        return $buckets;
    }
}
