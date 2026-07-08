<?php

namespace App\Services\Analytics\Report;

use App\Services\Analytics\Outcome;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TimeSeriesSection
{
    /** @return list<array{bucket: string, visits: int, scans: int, visitors: int, blocked: int}> */
    public function build(AnalyticsEventSlice $slice): array
    {
        $filters = $slice->filters();
        $unit = $filters->bucketUnit();
        $expression = $this->bucketExpression($unit);

        $successful = $slice->query()
            ->successful()
            ->selectRaw("{$expression} as bucket")
            ->selectRaw("sum(case when metric = 'visit' then 1 else 0 end) as visits")
            ->selectRaw("sum(case when metric = 'scan' then 1 else 0 end) as scans")
            ->selectRaw('count(distinct visitor_hash) as visitors')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $blocked = $slice->query()
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
