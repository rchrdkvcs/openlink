<?php

namespace App\Services;

use App\Models\RoutingRule;
use App\Models\RoutingVariant;

class RoutingDecision
{
    public function __construct(
        public readonly string $destinationUrl,
        public readonly ?RoutingRule $rule = null,
        public readonly ?RoutingVariant $variant = null,
    ) {}
}
