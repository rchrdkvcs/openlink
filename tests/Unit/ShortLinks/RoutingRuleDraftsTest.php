<?php

namespace Tests\Unit\ShortLinks;

use App\Models\RoutingRule;
use App\Services\ShortLinks\Routing\RoutingRuleDrafts;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoutingRuleDraftsTest extends TestCase
{
    public static function switches(): array
    {
        return [
            'false string' => ['false', false],
            'zero string' => ['0', false],
            'off' => ['off', false],
            'false' => [false, false],
            'true string' => ['true', true],
            'one' => [1, true],
            'missing' => [null, true],
        ];
    }

    #[DataProvider('switches')]
    public function test_rule_and_variant_switches_are_normalised_to_booleans(mixed $raw, bool $expected): void
    {
        $data = $raw === null ? [] : ['is_enabled' => $raw];
        $rule = (new RoutingRuleDrafts)->unsaved([[
            ...$data,
            'type' => RoutingRule::TYPE_SPLIT_TEST,
            'variants' => [['destination_url' => 'https://example.com/a', ...$data]],
        ]])->sole();

        $this->assertSame($expected, $rule->is_enabled);
        $this->assertSame($expected, $rule->variants->sole()->is_enabled);
    }
}
