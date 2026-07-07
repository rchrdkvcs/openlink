<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class ResolutionContext
{
    /**
     * @param  array<string, mixed>  $dimensions
     */
    public function __construct(
        private readonly array $dimensions,
        public readonly CarbonImmutable $occurredAt,
        public readonly string $visitorHash,
    ) {}

    public function value(string $key): mixed
    {
        return $this->dimensions[$key] ?? null;
    }

    /** @return array<string, mixed> */
    public function analyticsDimensions(): array
    {
        return $this->dimensions;
    }
}
