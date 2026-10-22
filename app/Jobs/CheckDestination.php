<?php

namespace App\Jobs;

use App\Actions\DestinationHealth\DestinationChecks;
use App\Models\DestinationCheck;
use App\Services\DestinationHealth\DestinationProbe;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class CheckDestination implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public const HOST_RATE_LIMITER = 'destination-check-hosts';

    public const CONCURRENCY = 4;

    public int $maxExceptions = 1;

    public int $timeout = 90;

    public int $uniqueFor = 1920;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $destinationCheckId,
        public readonly string $host = '',
        public readonly ?string $generation = null,
    ) {}

    public static function for(DestinationCheck $check): self
    {
        return new self($check->id, $check->host(), $check->generation);
    }

    public function uniqueId(): string
    {
        return $this->destinationCheckId.(isset($this->generation) ? ':'.$this->generation : '');
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
        $check = DestinationCheck::query()->where('generation', $this->generation ?? null)->find($this->destinationCheckId);

        if ($check === null) {
            return;
        }

        $started = DestinationCheck::query()
            ->whereKey($check->id)
            ->where('generation', $this->generation ?? null)
            ->update(['execution_state' => 'running', 'started_at' => now()]);

        if (! $started) {
            return;
        }

        $check->refresh();

        if ($check->generation !== ($this->generation ?? null)) {
            return;
        }
        $checks->record($check, $probe->check($check->url));
    }

    public function failed(?Throwable $exception): void
    {
        DestinationCheck::query()
            ->whereKey($this->destinationCheckId)
            ->where('generation', $this->generation ?? null)
            ->whereIn('execution_state', ['queued', 'running'])
            ->update(['execution_state' => 'failed']);
    }
}
