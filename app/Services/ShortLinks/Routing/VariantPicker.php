<?php

namespace App\Services\ShortLinks\Routing;

use App\Models\RoutingRule;
use App\Models\RoutingVariant;

class VariantPicker
{
    public function pick(RoutingRule $rule, string $visitorHash): ?RoutingVariant
    {
        $variants = $rule->variants
            ->filter(fn (RoutingVariant $variant) => $variant->is_enabled && $variant->weight > 0)
            ->values();

        if ($variants->isEmpty()) {
            return null;
        }

        $slot = hexdec(substr($visitorHash, 0, 8)) % $variants->sum('weight');
        $cursor = 0;

        foreach ($variants as $variant) {
            $cursor += $variant->weight;

            if ($slot < $cursor) {
                return $variant;
            }
        }

        return $variants->last();
    }
}
