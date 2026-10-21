<?php

namespace App\Actions\Analytics;

use App\Jobs\SendWorkspaceSummary;
use App\Models\AnalyticsSummaryDelivery;
use App\Models\AnalyticsSummarySchedule;
use App\Services\Analytics\SummaryPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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

        foreach ($schedule->currentRecipients()->pluck('workspace_members.user_id') as $userId) {
            if ($this->claim($schedule, $userId, $period, $now) && $this->dispatch($schedule, $userId, $period, $now)) {
                $queued++;
            }
        }

        return $queued;
    }

    private function claim(AnalyticsSummarySchedule $schedule, int $userId, SummaryPeriod $period, CarbonImmutable $now): bool
    {
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

        if ($inserted === 1) {
            return true;
        }

        return $this->delivery($schedule, $userId, $period)
            ->where('status', AnalyticsSummaryDelivery::STATUS_FAILED)
            ->where('failure', self::QUEUE_FAILURE)
            ->update(['status' => AnalyticsSummaryDelivery::STATUS_PENDING, 'failure' => null, 'updated_at' => $now]) === 1;
    }

    private function dispatch(AnalyticsSummarySchedule $schedule, int $userId, SummaryPeriod $period, CarbonImmutable $now): bool
    {
        $delivery = $this->delivery($schedule, $userId, $period)->sole();

        try {
            SendWorkspaceSummary::dispatch($delivery->id);

            return true;
        } catch (Throwable $exception) {
            report($exception);
            $delivery->update([
                'status' => AnalyticsSummaryDelivery::STATUS_FAILED,
                'failed_at' => $now,
                'failure' => self::QUEUE_FAILURE,
            ]);

            return false;
        }
    }

    private function delivery(AnalyticsSummarySchedule $schedule, int $userId, SummaryPeriod $period): Builder
    {
        return AnalyticsSummaryDelivery::query()
            ->where('analytics_summary_schedule_id', $schedule->id)
            ->where('user_id', $userId)
            ->whereDate('period_date', $period->date());
    }
}
