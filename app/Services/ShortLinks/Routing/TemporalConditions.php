<?php

namespace App\Services\ShortLinks\Routing;

use Carbon\CarbonImmutable;
use Throwable;

class TemporalConditions
{
    public function dateTimeMatches(array $condition, CarbonImmutable $now): bool
    {
        $timezone = $this->timezone($condition);
        $value = $condition['value'] ?? null;

        return match ((string) ($condition['operator'] ?? 'is')) {
            'before' => ($moment = $this->moment($value, $timezone)) !== null && $now->lessThan($moment),
            'after' => ($moment = $this->moment($value, $timezone)) !== null && $now->greaterThan($moment),
            'between' => $this->betweenMoments($now, $value, $timezone),
            default => false,
        };
    }

    public function timeOfDayMatches(array $condition, CarbonImmutable $now): bool
    {
        $operator = (string) ($condition['operator'] ?? 'between');
        $minutes = $this->minutes($now->setTimezone($this->timezone($condition))->format('H:i'));
        $value = $condition['value'] ?? null;

        if ($operator === 'before' || $operator === 'after') {
            $limit = $this->minutes(is_string($value) ? $value : null);

            return $limit !== null && ($operator === 'before' ? $minutes < $limit : $minutes > $limit);
        }

        if ($operator !== 'between') {
            return false;
        }

        [$from, $to] = array_map(fn ($bound) => $this->minutes($bound), $this->range($value));

        if ($from === null || $to === null) {
            return false;
        }

        return $from <= $to
            ? $minutes >= $from && $minutes <= $to
            : $minutes >= $from || $minutes <= $to;
    }

    public function dayOfWeek(array $condition, CarbonImmutable $now): string
    {
        return mb_strtolower($now->setTimezone($this->timezone($condition))->format('l'));
    }

    private function betweenMoments(CarbonImmutable $now, mixed $value, string $timezone): bool
    {
        [$from, $to] = array_map(fn ($bound) => $this->moment($bound, $timezone), $this->range($value));

        return $from && $to && $now->betweenIncluded($from, $to);
    }

    private function moment(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, $timezone);
        } catch (Throwable) {
            return null;
        }
    }

    private function timezone(array $condition): string
    {
        $timezone = (string) ($condition['timezone'] ?? 'UTC');

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
    }

    private function range(mixed $value): array
    {
        if (! is_array($value)) {
            return [null, null];
        }

        return [$value['from'] ?? $value[0] ?? null, $value['to'] ?? $value[1] ?? null];
    }

    private function minutes(mixed $value): ?int
    {
        if (! is_string($value) || ! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }
}
