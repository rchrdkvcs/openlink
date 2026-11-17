<?php

namespace App\Services\DomainEdge;

use App\Enums\EdgeStatus;

final readonly class EdgeConnection
{
    private function __construct(
        public EdgeStatus $status,
        public ?string $hostnameId,
        public ?string $waitingReason,
    ) {}

    public static function connected(?string $hostnameId = null): self
    {
        return new self(EdgeStatus::Connected, $hostnameId, null);
    }

    public static function issuing(string $hostnameId, ?string $waitingReason = null): self
    {
        return new self(EdgeStatus::Issuing, $hostnameId, $waitingReason);
    }

    public static function waiting(?string $waitingReason = null, ?string $hostnameId = null): self
    {
        return new self(EdgeStatus::Waiting, $hostnameId, $waitingReason);
    }

    public function isConnected(): bool
    {
        return $this->status === EdgeStatus::Connected;
    }
}
