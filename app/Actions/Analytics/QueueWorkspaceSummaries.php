<?php

namespace App\Actions\Analytics;

use App\Jobs\SendWorkspaceSummary;
use App\Models\AnalyticsSummaryDelivery;
use App\Models\AnalyticsSummarySchedule;
use App\Services\Analytics\SummaryPeriod;
use Carbon\CarbonImmutable;
use Throwable;

class QueueWorkspaceSummaries
{
    public const QUEUE_FAILURE = 'The queue did not accept the summary. Check the queue connection.';

    public function handle(AnalyticsSummarySchedule $schedule, CarbonImmutable $now): int
    {
        if (! $schedule->is_enabled) {
            return 0;
        }

        $period = SummaryPeriod::latest($schedule->timezone, $now);

        if (! $period->isDue($now)) {
            return 0;
        }

        $queued = 0;

        $schedule->recipients()
            ->where('workspace_members.workspace_id', $schedule->workspace_id)
            ->pluck('workspace_members.user_id')
            ->each(function (int $userId) use ($schedule, $period, $now, &$queued) {
                $inserted = AnalyticsSummaryDelivery::query()->insertOrIgnore([
                    'analytics_summary_schedule_id' => $schedule->id,
                    'user_id' => $userId,
                    'period_date' => $period->date(),
                    'period_start' => $period->start->utc(),
                    'period_end' => $period->end->utc(),
                    'status' => AnalyticsSummaryDelivery::STATUS_PENDING,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($inserted === 0) {
                    return;
                }

                $delivery = AnalyticsSummaryDelivery::query()
                    ->where('analytics_summary_schedule_id', $schedule->id)
                    ->where('user_id', $userId)
                    ->whereDate('period_date', $period->date())
                    ->sole();

                try {
                    SendWorkspaceSummary::dispatch($delivery->id);
                    $queued++;
                } catch (Throwable $exception) {
                    report($exception);
                    $delivery->update([
                        'status' => AnalyticsSummaryDelivery::STATUS_FAILED,
                        'failed_at' => $now,
                        'failure' => self::QUEUE_FAILURE,
                    ]);
                }
            });

        return $queued;
    }
}
