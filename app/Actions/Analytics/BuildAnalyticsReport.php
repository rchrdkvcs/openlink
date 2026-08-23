<?php

namespace App\Actions\Analytics;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Actions\Workspaces\WorkspaceViewFactory;
use App\Models\User;
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
    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly WorkspaceViewFactory $views,
        private readonly SummarySection $summary,
        private readonly TimeSeriesSection $timeSeries,
        private readonly BreakdownSection $breakdowns,
        private readonly EntityRankingSection $rankings,
        private readonly ExportRowsSection $exports,
    ) {}

    /**
     * @param  list<int>|null  $accessibleLinkIds
     * @return array<string, mixed>
     */
    public function report(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null): array
    {
        $slice = $this->slice($workspace, $filters, $accessibleLinkIds);

        return [
            'range' => [
                'preset' => $filters->range,
                'from' => $filters->from->toIso8601String(),
                'to' => $filters->to->toIso8601String(),
                'bucket' => $filters->bucketUnit(),
            ],
            'summary' => $this->summary($workspace, $filters, $accessibleLinkIds),
            'timeseries' => $this->timeSeries->build($slice),
            'breakdowns' => [
                'referrers' => $this->breakdowns->dimension($slice, 'referrer_host'),
                'channels' => $this->breakdowns->dimension($slice, 'referrer_channel'),
                'countries' => $this->breakdowns->dimension($slice, 'country'),
                'languages' => $this->breakdowns->dimension($slice, 'language'),
                'devices' => $this->breakdowns->dimension($slice, 'device_type'),
                'browsers' => $this->breakdowns->dimension($slice, 'browser'),
                'os' => $this->breakdowns->dimension($slice, 'os'),
                'utm_sources' => $this->breakdowns->dimension($slice, 'utm_source'),
                'utm_mediums' => $this->breakdowns->dimension($slice, 'utm_medium'),
                'utm_campaigns' => $this->breakdowns->dimension($slice, 'utm_campaign'),
            ],
            'outcomes' => $this->breakdowns->outcomes($slice),
            'routing' => $this->rankings->routingPerformance($slice),
            'top_links' => $this->rankings->topLinks($slice),
            'top_qr_codes' => $this->rankings->topQrCodes($slice),
        ];
    }

    /** @return array<string, mixed> */
    public function summary(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null): array
    {
        return $this->summary->build(
            $this->slice($workspace, $filters, $accessibleLinkIds),
            $this->slice($workspace, $filters->previous(), $accessibleLinkIds),
        );
    }

    /** @return list<array{bucket: string, visits: int, scans: int, visitors: int, blocked: int}> */
    public function timeseries(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null): array
    {
        return $this->timeSeries->build($this->slice($workspace, $filters, $accessibleLinkIds));
    }

    /** @return list<array{label: string, count: int, share: float}> */
    public function breakdown(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds, string $column): array
    {
        return $this->breakdowns->dimension($this->slice($workspace, $filters, $accessibleLinkIds), $column);
    }

    /** @return list<array{outcome: string, count: int, share: float}> */
    public function outcomes(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null): array
    {
        return $this->breakdowns->outcomes($this->slice($workspace, $filters, $accessibleLinkIds));
    }

    /** @return list<array<string, mixed>> */
    public function topLinks(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null, int $limit = 10): array
    {
        return $this->rankings->topLinks($this->slice($workspace, $filters, $accessibleLinkIds), $limit);
    }

    /** @return list<array<string, mixed>> */
    public function topQrCodes(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null, int $limit = 10): array
    {
        return $this->rankings->topQrCodes($this->slice($workspace, $filters, $accessibleLinkIds), $limit);
    }

    /** @return list<array<string, mixed>> */
    public function routingPerformance(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null): array
    {
        return $this->rankings->routingPerformance($this->slice($workspace, $filters, $accessibleLinkIds));
    }

    /** @return list<int>|null */
    public function accessibleLinkIds(Workspace $workspace, User $user): ?array
    {
        if ($this->access->canEditWorkspace($user, $workspace)) {
            return null;
        }

        $accessibleFolderIds = $this->views->make($workspace, $user)->accessibleFolderIds();

        return $workspace->shortLinks()
            ->where(fn ($query) => $query->whereNull('folder_id')->orWhereIn('folder_id', $accessibleFolderIds))
            ->pluck('id')
            ->all();
    }

    /** @return Generator<int, list<string|null>, void, void> */
    public function exportRows(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds = null): Generator
    {
        return $this->exports->rows($this->slice($workspace, $filters, $accessibleLinkIds));
    }

    /** @param list<int>|null $accessibleLinkIds */
    private function slice(Workspace $workspace, AnalyticsFilters $filters, ?array $accessibleLinkIds): AnalyticsEventSlice
    {
        return new AnalyticsEventSlice($workspace, $filters, $accessibleLinkIds);
    }
}
