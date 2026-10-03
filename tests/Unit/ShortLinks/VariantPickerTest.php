<?php

namespace Tests\Unit\ShortLinks;

use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use App\Services\ShortLinks\Routing\VariantPicker;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class VariantPickerTest extends TestCase
{
    public function test_visitor_hash_selects_a_slot_within_the_cumulative_weights(): void
    {
        $rule = $this->rule([['A', 30, true], ['B', 70, true]]);
        $picker = new VariantPicker;

        $this->assertSame('A', $picker->pick($rule, $this->hashForSlot(0))->name);
        $this->assertSame('A', $picker->pick($rule, $this->hashForSlot(29))->name);
        $this->assertSame('B', $picker->pick($rule, $this->hashForSlot(30))->name);
        $this->assertSame('B', $picker->pick($rule, $this->hashForSlot(99))->name);
        $this->assertSame('A', $picker->pick($rule, $this->hashForSlot(100))->name);
    }

    public function test_disabled_and_zero_weight_variants_are_skipped(): void
    {
        $rule = $this->rule([['A', 50, false], ['B', 0, true], ['C', 10, true]]);

        $this->assertSame('C', (new VariantPicker)->pick($rule, $this->hashForSlot(0))->name);
    }

    public function test_no_variant_is_chosen_without_active_variants(): void
    {
        $rule = $this->rule([['A', 50, false]]);

        $this->assertNull((new VariantPicker)->pick($rule, $this->hashForSlot(0)));
    }

    public function test_the_same_visitor_keeps_the_same_variant(): void
    {
        $rule = $this->rule([['A', 1, true], ['B', 1, true], ['C', 1, true]]);
        $hash = hash('sha256', 'visitor-of-the-day');
        $picker = new VariantPicker;

        $this->assertSame($picker->pick($rule, $hash)->name, $picker->pick($rule, $hash)->name);
    }

    private function rule(array $variants): RoutingRule
    {
        $rule = new RoutingRule(['type' => RoutingRule::TYPE_SPLIT_TEST]);

        return $rule->setRelation('variants', new Collection(array_map(
            fn (array $variant) => new RoutingVariant(['name' => $variant[0], 'weight' => $variant[1], 'is_enabled' => $variant[2]]),
            $variants,
        )));
    }

    private function hashForSlot(int $slot): string
    {
        return str_pad(dechex($slot), 8, '0', STR_PAD_LEFT).str_repeat('f', 56);
    }
}
