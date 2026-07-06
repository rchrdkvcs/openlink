<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Services\InstanceSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('openlink:prune-analytics')]
#[Description('Prune analytics events older than the configured retention window')]
class PruneAnalyticsEvents extends Command
{
    public function handle(InstanceSettings $settings): int
    {
        $days = (int) $settings->get('analytics_retention_days', 365);
        $deleted = AnalyticsEvent::query()
            ->where('occurred_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} analytics events older than {$days} days.");

        return self::SUCCESS;
    }
}
