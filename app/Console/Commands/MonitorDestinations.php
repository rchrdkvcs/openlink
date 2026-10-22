<?php

namespace App\Console\Commands;

use App\Actions\DestinationHealth\DestinationChecks;
use App\Models\DestinationCheck;
use App\Services\InstanceSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('openlink:monitor-destinations {--limit=100}')]
#[Description('Queue Destination Checks for destinations that are due when Destination Monitoring is enabled')]
class MonitorDestinations extends Command
{
    public function handle(DestinationChecks $checks, InstanceSettings $settings): int
    {
        if (! $settings->get('destination_monitoring')) {
            $this->info('Destination monitoring is disabled.');

            return self::SUCCESS;
        }

        $checks->prune();
        $checks->discover();
        $due = $checks->due(max(1, (int) $this->option('limit')));
        $due->each(fn (DestinationCheck $check) => $checks->queue($check));

        $this->info("Queued {$due->count()} destination checks.");

        return self::SUCCESS;
    }
}
