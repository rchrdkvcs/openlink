<?php

namespace App\Actions\Analytics;

use App\Models\Workspace;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Analytics\SummaryPeriod;
use App\Services\InstanceSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

class BuildWorkspaceSummary
{
    public function __construct(
        private readonly BuildAnalyticsReport $reporter,
        private readonly InstanceSettings $settings,
    ) {}

    public function build(Workspace $workspace, SummaryPeriod $period): array
    {
        $filters = new AnalyticsFilters(range: 'custom', from: $period->start, to: $period->end);
        $cutoff = CarbonImmutable::now()->subDays((int) $this->settings->get('analytics_retention_days', 365));
        $comparable = $filters->previous()->from->greaterThanOrEqualTo($cutoff);
        $summary = $this->reporter->summary($workspace, $filters);

        return [
            'workspace' => $workspace->name,
            'workspace_text' => self::markdownText($workspace->name),
            'period' => sprintf('%s – %s', $period->start->format('M j'), $period->lastDay()->format('M j, Y')),
            'timezone' => $period->timezone,
            'metrics' => [
                ['label' => 'Visits', 'value' => $summary['visits'], 'change' => $comparable ? $summary['visits_change'] : null],
                ['label' => 'QR scans', 'value' => $summary['scans'], 'change' => $comparable ? $summary['scans_change'] : null],
                ['label' => 'Visitors', 'value' => $summary['visitors'], 'change' => $comparable ? $summary['visitors_change'] : null],
            ],
            'is_empty' => $summary['visits'] + $summary['scans'] === 0,
            'is_comparable' => $comparable,
            'is_complete' => $filters->from->greaterThanOrEqualTo($cutoff),
            'top_links' => array_map(fn (array $link) => [
                'label' => self::markdownText($link['short_url'] ?? $link['slug']),
                'total' => $link['total'],
            ], $this->reporter->topLinks($workspace, $filters, 5)),
            'report_url' => URL::temporarySignedRoute('analytics.open', now()->addDays(90), [
                'workspace' => $workspace->id,
                'range' => 'custom',
                'from' => $period->start->toDateString(),
                'to' => $period->lastDay()->toDateString(),
            ]),
        ];
    }

    public static function markdownText(string $value): string
    {
        return preg_replace('/([\\\\`*_{}\[\]()#+\-.!|<>~])/', '\\\\$1', $value);
    }
}
