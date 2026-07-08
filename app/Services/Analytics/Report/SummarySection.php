<?php

namespace App\Services\Analytics\Report;

class SummarySection
{
    /** @return array<string, mixed> */
    public function build(AnalyticsEventSlice $current, AnalyticsEventSlice $previous): array
    {
        $currentTotals = $this->totals($current);
        $previousTotals = $this->totals($previous);

        $resolved = $currentTotals['visits'] + $currentTotals['scans'];
        $attempts = $resolved + $currentTotals['blocked'];

        return [
            ...$currentTotals,
            'success_rate' => $attempts > 0 ? round($resolved / $attempts * 100, 1) : null,
            'visits_change' => $this->change($currentTotals['visits'], $previousTotals['visits']),
            'scans_change' => $this->change($currentTotals['scans'], $previousTotals['scans']),
            'visitors_change' => $this->change($currentTotals['visitors'], $previousTotals['visitors']),
            'blocked_change' => $this->change($currentTotals['blocked'], $previousTotals['blocked']),
        ];
    }

    /** @return array{visits: int, scans: int, visitors: int, blocked: int, bots: int, active_links: int} */
    private function totals(AnalyticsEventSlice $slice): array
    {
        $row = $slice->query()
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
}
