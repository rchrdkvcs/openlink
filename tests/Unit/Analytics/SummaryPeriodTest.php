<?php

namespace Tests\Unit\Analytics;

use App\Services\Analytics\SummaryPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SummaryPeriodTest extends TestCase
{
    public function test_latest_period_is_previous_local_week(): void
    {
        $period = SummaryPeriod::latest('Europe/Paris', CarbonImmutable::parse('2026-10-21 10:00', 'UTC'));

        $this->assertSame('2026-10-12', $period->date());
        $this->assertSame('2026-10-11T22:00:00+00:00', $period->start->utc()->toIso8601String());
        $this->assertSame('2026-10-18T22:00:00+00:00', $period->end->utc()->toIso8601String());
        $this->assertSame('2026-10-19T06:00:00+00:00', $period->sendAt()->utc()->toIso8601String());
    }

    public function test_week_crossing_dst_end_is_169_hours(): void
    {
        $period = SummaryPeriod::latest('Europe/Paris', CarbonImmutable::parse('2026-10-26 09:00', 'UTC'));

        $this->assertSame('2026-10-19', $period->date());
        $this->assertSame(169.0, $period->start->diffInHours($period->end));
        $this->assertSame('2026-10-26T07:00:00+00:00', $period->sendAt()->utc()->toIso8601String());
    }

    public function test_week_crossing_dst_start_is_167_hours(): void
    {
        $period = SummaryPeriod::latest('America/New_York', CarbonImmutable::parse('2026-03-09 14:00', 'UTC'));

        $this->assertSame('2026-03-02', $period->date());
        $this->assertSame(167.0, $period->start->diffInHours($period->end));
        $this->assertSame('2026-03-09T12:00:00+00:00', $period->sendAt()->utc()->toIso8601String());
    }

    public function test_period_is_due_for_24_hours_after_monday_8am_local(): void
    {
        $tz = 'Asia/Tokyo';

        $this->assertFalse(SummaryPeriod::latest($tz, $at = CarbonImmutable::parse('2026-10-18 22:59', 'UTC'))->isDue($at));
        $this->assertTrue(SummaryPeriod::latest($tz, $at = CarbonImmutable::parse('2026-10-18 23:00', 'UTC'))->isDue($at));
        $this->assertTrue(SummaryPeriod::latest($tz, $at = CarbonImmutable::parse('2026-10-19 22:59', 'UTC'))->isDue($at));
        $this->assertFalse(SummaryPeriod::latest($tz, $at = CarbonImmutable::parse('2026-10-19 23:00', 'UTC'))->isDue($at));
    }

    public function test_next_send_time(): void
    {
        $this->assertSame(
            '2026-10-26T08:00:00+00:00',
            SummaryPeriod::next('UTC', CarbonImmutable::parse('2026-10-21 10:00', 'UTC'))->sendAt()->toIso8601String(),
        );
        $this->assertSame(
            '2026-10-19T08:00:00+00:00',
            SummaryPeriod::next('UTC', CarbonImmutable::parse('2026-10-19 07:00', 'UTC'))->sendAt()->toIso8601String(),
        );
    }
}
