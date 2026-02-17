<?php

namespace Tests\Unit;

use App\Support\CirculationSlaClock;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CirculationSlaClockTest extends TestCase
{
    public function test_elapsed_late_days_excludes_weekend_when_enabled(): void
    {
        config(['notobuku.circulation.sla.exclude_weekends' => true]);

        $days = CirculationSlaClock::elapsedLateDays(
            '2026-02-13 00:00:00', // Friday
            '2026-02-16 00:00:00'  // Monday
        );

        $this->assertSame(1, $days);
    }

    public function test_elapsed_late_days_counts_calendar_days_when_disabled(): void
    {
        config(['notobuku.circulation.sla.exclude_weekends' => false]);

        $days = CirculationSlaClock::elapsedLateDays(
            '2026-02-13 00:00:00', // Friday
            '2026-02-16 00:00:00'  // Monday
        );

        $this->assertSame(3, $days);
    }

    public function test_elapsed_hours_excludes_weekend_when_enabled(): void
    {
        config(['notobuku.circulation.sla.exclude_weekends' => true]);
        Carbon::setTestNow(Carbon::parse('2026-02-16 00:00:00')); // Monday

        $hours = CirculationSlaClock::elapsedHoursFrom('2026-02-13 00:00:00');

        Carbon::setTestNow();
        $this->assertSame(24, $hours);
    }
}

