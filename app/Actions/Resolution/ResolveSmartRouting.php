<?php

namespace App\Actions\Resolution;

use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use App\Models\ShortLink;
use App\Services\ResolutionContext;
use App\Services\Routing\SmartRoutingSchema;
use App\Services\RoutingDecision;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ResolveSmartRouting
{
    public function __construct(private readonly SmartRoutingSchema $schema) {}

    public function resolve(ShortLink $shortLink, ResolutionContext $context): RoutingDecision
    {
        $shortLink->loadMissing(['routingRules.variants']);

        foreach ($shortLink->routingRules as $rule) {
            if (! $rule->is_enabled || ! $this->matches($rule, $context)) {
                continue;
            }

            if ($rule->type === RoutingRule::TYPE_SPLIT_TEST) {
                $variant = $this->variantFor($rule, $context);

                if ($variant) {
                    return new RoutingDecision($variant->destination_url, $rule, $variant);
                }

                continue;
            }

            if (filled($rule->destination_url)) {
                return new RoutingDecision($rule->destination_url, $rule);
            }
        }

        return new RoutingDecision($shortLink->destination_url);
    }

    private function matches(RoutingRule $rule, ResolutionContext $context): bool
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

    /** @param array<string, mixed> $condition */
    private function conditionMatches(array $condition, ResolutionContext $context): bool
    {
        $type = (string) ($condition['type'] ?? '');
        $operator = (string) ($condition['operator'] ?? 'is');

        return match ($type) {
            'date_time' => $this->dateTimeMatches($condition, $context),
            'day_of_week' => $this->dayOfWeekMatches($condition, $context),
            'time_of_day' => $this->timeOfDayMatches($condition, $context),
            default => $this->scalarMatches($context->value($this->schema->contextKey($type)), $operator, $condition['value'] ?? null),
        };
    }

    private function scalarMatches(mixed $actual, string $operator, mixed $expected): bool
    {
        $isEmpty = $actual === null || $actual === '';

        if ($operator === 'is_empty') {
            return $isEmpty;
        }

        if ($operator === 'is_not_empty') {
            return ! $isEmpty;
        }

        if ($isEmpty) {
            return false;
        }

        $actual = mb_strtolower((string) $actual);
        $expectedValues = is_array($expected) ? $expected : [$expected];
        $expectedValues = collect($expectedValues)
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

    /** @param array<string, mixed> $condition */
    private function dateTimeMatches(array $condition, ResolutionContext $context): bool
    {
        $operator = (string) ($condition['operator'] ?? 'is');
        $now = $context->occurredAt;
        $timezone = $this->timezone($condition);

        return match ($operator) {
            'before' => ($value = $this->dateValue($condition['value'] ?? null, $timezone)) && $now->lessThan($value),
            'after' => ($value = $this->dateValue($condition['value'] ?? null, $timezone)) && $now->greaterThan($value),
            'between' => $this->betweenDates($now, $condition['value'] ?? null, $timezone),
            default => false,
        };
    }

    private function dateValue(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    private function betweenDates(CarbonImmutable $now, mixed $value, string $timezone): bool
    {
        [$from, $to] = $this->rangeValues($value);
        $from = $this->dateValue($from, $timezone);
        $to = $this->dateValue($to, $timezone);

        return $from && $to && $now->betweenIncluded($from, $to);
    }

    /** @param array<string, mixed> $condition */
    private function dayOfWeekMatches(array $condition, ResolutionContext $context): bool
    {
        $day = mb_strtolower($context->occurredAt->setTimezone($this->timezone($condition))->format('l'));

        return $this->scalarMatches($day, (string) ($condition['operator'] ?? 'is'), $condition['value'] ?? null);
    }

    /** @param array<string, mixed> $condition */
    private function timeOfDayMatches(array $condition, ResolutionContext $context): bool
    {
        $operator = (string) ($condition['operator'] ?? 'between');
        $minutes = $this->minutes($context->occurredAt->setTimezone($this->timezone($condition))->format('H:i'));

        if ($minutes === null) {
            return false;
        }

        if ($operator === 'before' || $operator === 'after') {
            $value = $this->minutes(is_string($condition['value'] ?? null) ? $condition['value'] : null);

            return $value !== null && ($operator === 'before' ? $minutes < $value : $minutes > $value);
        }

        if ($operator !== 'between') {
            return false;
        }

        [$from, $to] = $this->rangeValues($condition['value'] ?? null);
        $from = $this->minutes($from);
        $to = $this->minutes($to);

        if ($from === null || $to === null) {
            return false;
        }

        return $from <= $to
            ? $minutes >= $from && $minutes <= $to
            : $minutes >= $from || $minutes <= $to;
    }

    /** @param array<string, mixed> $condition */
    private function timezone(array $condition): string
    {
        $timezone = (string) ($condition['timezone'] ?? 'UTC');

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
    }

    /** @return array{mixed, mixed} */
    private function rangeValues(mixed $value): array
    {
        if (is_array($value)) {
            return [$value['from'] ?? $value[0] ?? null, $value['to'] ?? $value[1] ?? null];
        }

        return [null, null];
    }

    private function minutes(?string $value): ?int
    {
        if (! is_string($value) || ! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    private function variantFor(RoutingRule $rule, ResolutionContext $context): ?RoutingVariant
    {
        /** @var Collection<int, RoutingVariant> $variants */
        $variants = $rule->variants
            ->filter(fn (RoutingVariant $variant) => $variant->is_enabled && $variant->weight > 0)
            ->values();

        if ($variants->isEmpty()) {
            return null;
        }

        $total = $variants->sum('weight');
        $slot = hexdec(substr($context->visitorHash, 0, 8)) % $total;
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
