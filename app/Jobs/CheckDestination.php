<?php

namespace App\Jobs;

use App\Actions\DestinationHealth\DestinationChecks;
use App\Models\DestinationCheck;
use App\Services\DestinationHealth\DestinationProbe;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckDestination implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $destinationCheckId) {}

    public function uniqueId(): string
    {
        return (string) $this->destinationCheckId;
    }

    public function handle(DestinationProbe $probe, DestinationChecks $checks): void
    {
        $check = DestinationCheck::query()->find($this->destinationCheckId);

        if ($check === null) {
            return;
        }

        $checks->record($check, $probe->check($check->url));
    }
}
