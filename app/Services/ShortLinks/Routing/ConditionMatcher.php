<?php

namespace App\Services\ShortLinks\Routing;

use App\Models\RoutingRule;
use App\Services\ResolutionContext;

class ConditionMatcher
{
    public function __construct(private readonly TemporalConditions $temporal) {}

    public function matches(RoutingRule $rule, ResolutionContext $context): bool
    {
        $conditions = collect($rule->conditions ?? [])->filter(fn ($condition) => is_array($condition));

        if ($conditions->isEmpty()) {
            return true;
        }

        $results = $conditions->map(fn (array $condition) => $this->conditionMatches($condition, $context));

        return $rule->match_mode === RoutingRule::MATCH_ANY
            ? $results->contains(true)
            : $results->every(fn (bool $result) => $result);
    }

    public function conditionMatches(array $condition, ResolutionContext $context): bool
    {
        $type = (string) ($condition['type'] ?? '');
        $operator = (string) ($condition['operator'] ?? 'is');
        $expected = $condition['value'] ?? null;

        return match ($type) {
            'date_time' => $this->temporal->dateTimeMatches($condition, $context->occurredAt),
            'time_of_day' => $this->temporal->timeOfDayMatches($condition, $context->occurredAt),
            'day_of_week' => $this->scalarMatches($this->temporal->dayOfWeek($condition, $context->occurredAt), $operator, $expected),
            'operating_system' => $this->scalarMatches($context->value('os'), $operator, $expected),
            default => $this->scalarMatches($context->value($type), $operator, $expected),
        };
    }

    private function scalarMatches(mixed $actual, string $operator, mixed $expected): bool
    {
        $isEmpty = $actual === null || $actual === '';

        if ($operator === 'is_empty' || $operator === 'is_not_empty') {
            return $isEmpty === ($operator === 'is_empty');
        }

        if ($isEmpty) {
            return false;
        }

        $actual = mb_strtolower((string) $actual);
        $expectedValues = collect(is_array($expected) ? $expected : [$expected])
            ->map(fn ($value) => mb_strtolower(trim((string) $value)))
            ->filter(fn (string $value) => $value !== '')
            ->values();

        if ($expectedValues->isEmpty()) {
            return false;
        }

        return match ($operator) {
            'is' => $expectedValues->contains($actual),
            'is_not' => ! $expectedValues->contains($actual),
            'contains' => $expectedValues->contains(fn (string $value) => str_contains($actual, $value)),
            'does_not_contain' => $expectedValues->every(fn (string $value) => ! str_contains($actual, $value)),
            'starts_with' => $expectedValues->contains(fn (string $value) => str_starts_with($actual, $value)),
            'ends_with' => $expectedValues->contains(fn (string $value) => str_ends_with($actual, $value)),
            default => false,
        };
    }
}
