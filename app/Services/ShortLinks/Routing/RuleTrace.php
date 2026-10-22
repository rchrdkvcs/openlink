<?php

namespace App\Services\ShortLinks\Routing;

use App\Models\RoutingRule;
use App\Models\RoutingVariant;

class RuleTrace
{
    public const DISABLED = 'disabled';

    public const NOT_MATCHED = 'not_matched';

    public const MATCHED = 'matched';

    public const SKIPPED = 'skipped';

    public const NOT_REACHED = 'not_reached';

    public function __construct(
        public readonly RoutingRule $rule,
        public readonly string $status,
        public readonly array $conditions = [],
        public readonly ?int $slot = null,
        public readonly ?RoutingVariant $variant = null,
    ) {}

    public function matched(): bool
    {
        return $this->status === self::MATCHED;
    }

    public function destinationUrl(): ?string
    {
        return $this->variant?->destination_url ?? $this->rule->destination_url;
    }
}
