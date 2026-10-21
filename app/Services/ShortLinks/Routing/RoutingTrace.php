<?php

namespace App\Services\ShortLinks\Routing;

use App\Services\RoutingDecision;

class RoutingTrace
{
    public function __construct(
        public readonly array $rules,
        public readonly RoutingDecision $decision,
    ) {}
}
