<?php

namespace App\Console\Commands;

use App\Models\DiagnosticObservation;
use App\Services\Diagnostics\Heartbeats;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('openlink:heartbeat')]
#[Description('Record that the scheduler is running')]
class RecordSchedulerHeartbeat extends Command
{
    public function handle(Heartbeats $heartbeats): int
    {
        $heartbeats->record(DiagnosticObservation::SCHEDULER);

        return self::SUCCESS;
    }
}
