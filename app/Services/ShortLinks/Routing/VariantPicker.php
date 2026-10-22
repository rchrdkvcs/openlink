<?php

namespace App\Services\ShortLinks\Routing;

use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use Illuminate\Support\Collection;

class VariantPicker
{
    public function pick(RoutingRule $rule, string $visitorHash): ?RoutingVariant
    {
        $variants = $this->eligible($rule);
        $slot = $this->slot($rule, $visitorHash);

        if ($slot === null) {
            return null;
        }

        $cursor = 0;

        foreach ($variants as $variant) {
            $cursor += $variant->weight;

            if ($slot < $cursor) {
                return $variant;
            }
        }

        return $variants->last();
    }

    public function eligible(RoutingRule $rule): Collection
    {
        return $rule->variants
            ->filter(fn (RoutingVariant $variant) => $this->isEligible($variant))
            ->values();
    }

    public function isEligible(RoutingVariant $variant): bool
    {
        return $variant->is_enabled && $variant->weight > 0;
    }

    public function slot(RoutingRule $rule, string $visitorHash): ?int
    {
        $total = $this->eligible($rule)->sum('weight');

        return $total > 0 ? hexdec(substr($visitorHash, 0, 8)) % $total : null;
    }
}
