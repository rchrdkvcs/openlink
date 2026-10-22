<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SummaryPeriod
{
    public const SEND_HOUR = 8;

    public const DUE_HOURS = 24;

    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly string $timezone,
    ) {}

    public static function latest(string $timezone, CarbonImmutable $now): self
    {
        $end = $now->setTimezone($timezone)->startOfWeek(CarbonInterface::MONDAY);

        return new self($end->subWeek(), $end, $timezone);
    }

    public static function next(string $timezone, CarbonImmutable $now): self
    {
        $latest = self::latest($timezone, $now);

        return $latest->sendAt()->greaterThan($now)
            ? $latest
            : new self($latest->end, $latest->end->addWeek(), $timezone);
    }

    public function date(): string
    {
        return $this->start->toDateString();
    }

    public function sendAt(): CarbonImmutable
    {
        return $this->end->setTime(self::SEND_HOUR, 0);
    }

    public function isDue(CarbonImmutable $now): bool
    {
        return $now->greaterThanOrEqualTo($this->sendAt())
            && $now->lessThan($this->sendAt()->addHours(self::DUE_HOURS));
    }

    public function lastDay(): CarbonImmutable
    {
        return $this->end->subDay();
    }
}
