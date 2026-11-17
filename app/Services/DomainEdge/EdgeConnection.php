<?php

namespace App\Services\DomainEdge;

final readonly class EdgeConnection
{
    private function __construct(
        public bool $connected,
        public ?string $error,
    ) {}

    public static function connected(): self
    {
        return new self(true, null);
    }

    public static function waiting(?string $error = null): self
    {
        return new self(false, $error);
    }
}
