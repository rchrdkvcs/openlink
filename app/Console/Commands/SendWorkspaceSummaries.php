<?php

namespace App\Console\Commands;

use App\Actions\Analytics\QueueWorkspaceSummaries;
use App\Models\AnalyticsSummarySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('openlink:send-workspace-summaries')]
#[Description('Queue weekly Workspace Summaries that are due')]
class SendWorkspaceSummaries extends Command
{
    public function handle(QueueWorkspaceSummaries $summaries): int
    {
        $now = CarbonImmutable::now();
        $queued = 0;

        AnalyticsSummarySchedule::query()
            ->where('is_enabled', true)
            ->lazyById()
            ->each(function (AnalyticsSummarySchedule $schedule) use ($summaries, $now, &$queued) {
                $queued += $summaries->handle($schedule, $now);
            });

        $this->info("Queued {$queued} workspace summaries.");

        return self::SUCCESS;
    }
}
