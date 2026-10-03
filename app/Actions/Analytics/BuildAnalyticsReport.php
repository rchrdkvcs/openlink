<?php

namespace App\Actions\Analytics;

use App\Models\Workspace;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Analytics\Report\AnalyticsEventSlice;
use App\Services\Analytics\Report\BreakdownSection;
use App\Services\Analytics\Report\EntityRankingSection;
use App\Services\Analytics\Report\ExportRowsSection;
use App\Services\Analytics\Report\SummarySection;
use App\Services\Analytics\Report\TimeSeriesSection;
use Generator;

class BuildAnalyticsReport
{
    private const BREAKDOWNS = [
        'referrers' => 'referrer_host',
        'channels' => 'referrer_channel',
        'countries' => 'country',
        'languages' => 'language',
        'devices' => 'device_type',
        'browsers' => 'browser',
        'os' => 'os',
        'utm_sources' => 'utm_source',
        'utm_mediums' => 'utm_medium',
        'utm_campaigns' => 'utm_campaign',
    ];

    public function __construct(
        private readonly SummarySection $summary,
        private readonly TimeSeriesSection $timeSeries,
        private readonly BreakdownSection $breakdowns,
        private readonly EntityRankingSection $rankings,
        private readonly ExportRowsSection $exports,
    ) {}

    public function report(Workspace $workspace, AnalyticsFilters $filters): array
    {
        $slice = new AnalyticsEventSlice($workspace, $filters);

        return [
            'range' => [
                'preset' => $filters->range,
                'from' => $filters->from->toIso8601String(),
                'to' => $filters->to->toIso8601String(),
                'bucket' => $filters->bucketUnit(),
            ],
            'summary' => $this->summary($workspace, $filters),
            'timeseries' => $this->timeSeries->build($slice),
            'breakdowns' => array_map(fn (string $column) => $this->breakdowns->dimension($slice, $column), self::BREAKDOWNS),
            'outcomes' => $this->breakdowns->outcomes($slice),
            'routing' => $this->rankings->routingPerformance($slice),
            'top_links' => $this->rankings->topLinks($slice),
            'top_qr_codes' => $this->rankings->topQrCodes($slice),
        ];
    }

    public function summary(Workspace $workspace, AnalyticsFilters $filters): array
    {
        return $this->summary->build(
            new AnalyticsEventSlice($workspace, $filters),
            new AnalyticsEventSlice($workspace, $filters->previous()),
        );
    }

    public function timeseries(Workspace $workspace, AnalyticsFilters $filters): array
    {
        return $this->timeSeries->build(new AnalyticsEventSlice($workspace, $filters));
    }

    public function topLinks(Workspace $workspace, AnalyticsFilters $filters, int $limit = 10): array
    {
        return $this->rankings->topLinks(new AnalyticsEventSlice($workspace, $filters), $limit);
    }

    public function exportRows(Workspace $workspace, AnalyticsFilters $filters): Generator
    {
        return $this->exports->rows(new AnalyticsEventSlice($workspace, $filters));
    }
}
