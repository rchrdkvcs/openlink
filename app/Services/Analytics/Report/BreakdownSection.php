<?php

namespace App\Services\Analytics\Report;

class BreakdownSection
{
    private const LIMIT = 12;

    /** @return list<array{label: string, count: int, share: float}> */
    public function dimension(AnalyticsEventSlice $slice, string $column): array
    {
        $rows = $slice->query()
            ->successful()
            ->whereNotNull($column)
            ->selectRaw("{$column} as label, count(*) as count, count(distinct visitor_hash) as visitors")
            ->groupBy($column)
            ->orderByDesc('count')
            ->limit(self::LIMIT)
            ->get();

        $total = (int) $slice->query()
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

    /** @return list<array{outcome: string, count: int, share: float}> */
    public function outcomes(AnalyticsEventSlice $slice): array
    {
        $rows = $slice->query()
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
}
