<?php

namespace App\Jobs;

use App\Services\DomainEdge\CloudflareDomainEdge;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class ReleaseEdgeHostname implements ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    public int $timeout = 30;

    public function __construct(public readonly string $hostnameId)
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600, 10800, 21600, 43200];
    }

    public function handle(CloudflareDomainEdge $edge): void
    {
        $edge->release($this->hostnameId);
    }

    public function failed(Throwable $exception): void
    {
        report(new RuntimeException(
            "Cloudflare custom hostname {$this->hostnameId} could not be released. Delete it under SSL/TLS → Custom Hostnames.",
            previous: $exception,
        ));
    }
}
