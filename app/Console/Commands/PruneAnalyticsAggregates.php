<?php

namespace App\Console\Commands;

use App\Models\AnalyticsDailyAggregate;
use App\Services\InstanceSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('openlink:prune-analytics')]
#[Description('Prune daily analytics aggregates older than the configured retention window')]
class PruneAnalyticsAggregates extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(InstanceSettings $settings): int
    {
        $days = (int) $settings->get('analytics_retention_days', 365);
        $deleted = AnalyticsDailyAggregate::query()
            ->where('date', '<', now()->subDays($days)->toDateString())
            ->delete();

        $this->info("Pruned {$deleted} analytics aggregate rows older than {$days} days.");

        return self::SUCCESS;
    }
}
