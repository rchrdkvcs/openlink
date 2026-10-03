<?php

namespace Tests\Unit\ShortLinks;

use App\Models\RoutingRule;
use App\Services\ResolutionContext;
use App\Services\ShortLinks\Routing\ConditionMatcher;
use App\Services\ShortLinks\Routing\TemporalConditions;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConditionMatcherTest extends TestCase
{
    public static function scalarCases(): array
    {
        return [
            'is matches case-insensitively' => [['type' => 'country', 'operator' => 'is', 'value' => 'fr'], true],
            'is accepts a list' => [['type' => 'country', 'operator' => 'is', 'value' => ['DE', 'FR']], true],
            'is not' => [['type' => 'country', 'operator' => 'is_not', 'value' => 'FR'], false],
            'contains' => [['type' => 'browser', 'operator' => 'contains', 'value' => 'chro'], true],
            'does not contain' => [['type' => 'browser', 'operator' => 'does_not_contain', 'value' => 'fire'], true],
            'starts with' => [['type' => 'utm_campaign', 'operator' => 'starts_with', 'value' => 'spring'], true],
            'ends with' => [['type' => 'utm_campaign', 'operator' => 'ends_with', 'value' => 'sale'], true],
            'is empty on a missing dimension' => [['type' => 'utm_term', 'operator' => 'is_empty'], true],
            'is not empty' => [['type' => 'country', 'operator' => 'is_not_empty'], true],
            'blank expectation never matches' => [['type' => 'country', 'operator' => 'is', 'value' => '  '], false],
            'operating system reads the os dimension' => [['type' => 'operating_system', 'operator' => 'is', 'value' => 'ios'], true],
            'day of week uses the condition timezone' => [['type' => 'day_of_week', 'operator' => 'is', 'value' => 'wednesday', 'timezone' => 'Pacific/Kiritimati'], true],
        ];
    }

    #[DataProvider('scalarCases')]
    public function test_conditions_match_the_resolution_context(array $condition, bool $expected): void
    {
        $this->assertSame($expected, $this->matcher()->conditionMatches($condition, $this->context()));
    }

    public static function temporalCases(): array
    {
        return [
            'inside a window' => [['type' => 'time_of_day', 'operator' => 'between', 'value' => ['from' => '09:00', 'to' => '18:00']], true],
            'window wrapping midnight' => [['type' => 'time_of_day', 'operator' => 'between', 'value' => ['from' => '22:00', 'to' => '06:00']], false],
            'window in another timezone' => [['type' => 'time_of_day', 'operator' => 'between', 'value' => ['from' => '22:00', 'to' => '06:00'], 'timezone' => 'Asia/Tokyo'], true],
            'before a time' => [['type' => 'time_of_day', 'operator' => 'before', 'value' => '10:00'], false],
            'after a time' => [['type' => 'time_of_day', 'operator' => 'after', 'value' => '10:00'], true],
            'malformed time' => [['type' => 'time_of_day', 'operator' => 'after', 'value' => '25:00'], false],
            'after a date' => [['type' => 'date_time', 'operator' => 'after', 'value' => '2026-03-01T00:00'], true],
            'before a date' => [['type' => 'date_time', 'operator' => 'before', 'value' => '2026-03-01T00:00'], false],
            'between dates' => [['type' => 'date_time', 'operator' => 'between', 'value' => ['2026-03-03', '2026-03-04']], true],
            'unparseable date' => [['type' => 'date_time', 'operator' => 'after', 'value' => 'not a date'], false],
        ];
    }

    #[DataProvider('temporalCases')]
    public function test_temporal_conditions_respect_windows_and_timezones(array $condition, bool $expected): void
    {
        $this->assertSame($expected, $this->matcher()->conditionMatches($condition, $this->context()));
    }

    public function test_rules_combine_conditions_with_their_match_mode(): void
    {
        $conditions = [
            ['type' => 'country', 'operator' => 'is', 'value' => 'FR'],
            ['type' => 'device_type', 'operator' => 'is', 'value' => 'desktop'],
        ];

        $this->assertFalse($this->matcher()->matches($this->rule($conditions, RoutingRule::MATCH_ALL), $this->context()));
        $this->assertTrue($this->matcher()->matches($this->rule($conditions, RoutingRule::MATCH_ANY), $this->context()));
        $this->assertTrue($this->matcher()->matches($this->rule([], RoutingRule::MATCH_ALL), $this->context()));
    }

    private function matcher(): ConditionMatcher
    {
        return new ConditionMatcher(new TemporalConditions);
    }

    private function rule(array $conditions, string $matchMode): RoutingRule
    {
        return new RoutingRule(['conditions' => $conditions, 'match_mode' => $matchMode]);
    }

    private function context(): ResolutionContext
    {
        return new ResolutionContext(
            ['country' => 'FR', 'device_type' => 'mobile', 'browser' => 'Chrome', 'os' => 'iOS', 'utm_campaign' => 'spring-sale'],
            CarbonImmutable::parse('2026-03-03 14:30:00', 'UTC'),
            str_repeat('0', 64),
        );
    }
}
