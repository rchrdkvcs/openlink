<?php

namespace App\Services\DestinationHealth;

use App\Enums\DestinationHealthStatus;

final readonly class DestinationProbeResult
{
    public function __construct(
        public DestinationHealthStatus $status,
        public ?int $httpStatus = null,
        public ?string $detail = null,
    ) {}
}
