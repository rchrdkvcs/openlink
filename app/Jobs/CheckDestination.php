<?php

namespace App\Jobs;

use App\Actions\DestinationHealth\DestinationChecks;
use App\Models\DestinationCheck;
use App\Services\DestinationHealth\DestinationProbe;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class CheckDestination implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const HOST_RATE_LIMITER = 'destination-check-hosts';

    public const CONCURRENCY = 4;

    public int $maxExceptions = 1;

    public int $timeout = 90;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $destinationCheckId, public readonly string $host = '') {}

    public static function for(DestinationCheck $check): self
    {
        return new self($check->id, $check->host());
    }

    public function uniqueId(): string
    {
        return (string) $this->destinationCheckId;
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function middleware(): array
    {
        return [
            new RateLimited(self::HOST_RATE_LIMITER),
            (new WithoutOverlapping('destination-host:'.$this->host))->releaseAfter(15)->expireAfter(120),
            (new WithoutOverlapping('destination-slot:'.($this->destinationCheckId % self::CONCURRENCY)))->releaseAfter(5)->expireAfter(120),
        ];
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
