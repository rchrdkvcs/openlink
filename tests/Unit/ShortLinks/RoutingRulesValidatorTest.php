<?php

namespace Tests\Unit\ShortLinks;

use App\Models\Domain;
use App\Models\RoutingRule;
use App\Services\ShortLinks\Routing\RoutingRulesValidator;
use App\Services\ShortLinks\ShortUrlAddress;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoutingRulesValidatorTest extends TestCase
{
    public static function invalidRules(): array
    {
        return [
            'conditional rule without destination' => [
                [['type' => RoutingRule::TYPE_CONDITIONAL]],
                'routing_rules.0.destination_url',
            ],
            'conditional rule looping to its Short URL' => [
                [['destination_url' => 'https://go.example.test/promo/']],
                'routing_rules.0.destination_url',
            ],
            'split test with one active variant' => [
                [['type' => RoutingRule::TYPE_SPLIT_TEST, 'variants' => [
                    ['destination_url' => 'https://example.com/a'],
                    ['destination_url' => 'https://example.com/b', 'is_enabled' => false],
                ]]],
                'routing_rules.0.variants',
            ],
            'variant looping to its Short URL' => [
                [['destination_url' => 'https://example.com'], ['type' => RoutingRule::TYPE_SPLIT_TEST, 'variants' => [
                    ['destination_url' => 'https://example.com/a'],
                    ['destination_url' => 'https://go.example.test/promo'],
                ]]],
                'routing_rules.1.variants.1.destination_url',
            ],
        ];
    }

    #[DataProvider('invalidRules')]
    public function test_invalid_rules_are_rejected_with_the_offending_field(array $rules, string $field): void
    {
        try {
            (new RoutingRulesValidator)->validate($this->address(), $rules);
            $this->fail('The routing rules should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame([$field], array_keys($exception->errors()));
        }
    }

    public function test_disabled_rules_and_other_short_urls_are_accepted(): void
    {
        (new RoutingRulesValidator)->validate($this->address(), [
            ['is_enabled' => false],
            ['destination_url' => 'https://go.example.test/other'],
            ['type' => RoutingRule::TYPE_SPLIT_TEST, 'variants' => [
                ['destination_url' => 'https://example.com/a', 'weight' => 1],
                ['destination_url' => 'https://example.com/b', 'weight' => 3],
            ]],
        ]);

        $this->addToAssertionCount(1);
    }

    private function address(): ShortUrlAddress
    {
        return new ShortUrlAddress(new Domain(['hostname' => 'go.example.test']), 'promo');
    }
}
