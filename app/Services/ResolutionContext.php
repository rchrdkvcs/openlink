<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class ResolutionContext
{
    public function __construct(
        private readonly array $dimensions,
        public readonly CarbonImmutable $occurredAt,
        public readonly string $visitorHash,
    ) {}

    public function value(string $key): mixed
    {
        return $this->dimensions[$key] ?? null;
    }

    public function analyticsDimensions(): array
    {
        return $this->dimensions;
    }
}
