<?php

namespace App\Support;

use App\Services\CirculationPolicyEngine;
use Illuminate\Support\Carbon;

class CirculationSlaClock
{
    public static function elapsedHoursFrom(?string $startAt, ?string $fallbackDate = null, ?Carbon $now = null): int
    {
        $now = $now ?: now();
        $start = self::resolveStart($startAt, $fallbackDate);
        if (!$start) {
            return 0;
        }
        if ($start->greaterThanOrEqualTo($now)) {
            return 0;
        }

        $excludeWeekend = (bool) config('notobuku.circulation.sla.exclude_weekends', true);
        if (!$excludeWeekend) {
            return max(0, $start->diffInHours($now));
        }

        return self::businessHoursBetween($start, $now);
    }

    public static function elapsedLateDays(
        ?string $dueAt,
        ?string $returnedAt = null,
        ?Carbon $now = null,
        ?int $institutionId = null,
        ?int $branchId = null,
        int $graceDays = 0
    ): int
    {
        if ($institutionId !== null && class_exists(CirculationPolicyEngine::class)) {
            try {
                $engine = app(CirculationPolicyEngine::class);
                return $engine->elapsedLateDays(
                    max(1, (int) $institutionId),
                    $branchId,
                    $dueAt,
                    $returnedAt,
                    max(0, $graceDays)
                );
            } catch (\Throwable $e) {
                // fallback to legacy hour-based weekend exclusion calculation
            }
        }

        $now = $now ?: now();
        $start = self::resolveStart($dueAt, null);
        if (!$start) {
            return 0;
        }

        $end = self::resolveStart($returnedAt, null) ?: $now;
        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        $excludeWeekend = (bool) config('notobuku.circulation.sla.exclude_weekends', true);
        if (!$excludeWeekend) {
            return max(0, $start->diffInDays($end));
        }

        $hours = self::businessHoursBetween($start, $end);
        return max(0, intdiv($hours, 24));
    }

    private static function resolveStart(?string $startAt, ?string $fallbackDate): ?Carbon
    {
        try {
            if (is_string($startAt) && trim($startAt) !== '') {
                return Carbon::parse($startAt);
            }
            if (is_string($fallbackDate) && trim($fallbackDate) !== '') {
                return Carbon::parse(trim($fallbackDate) . ' 00:00:00');
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    private static function businessHoursBetween(Carbon $start, Carbon $end): int
    {
        $cursor = $start->copy();
        $hours = 0;

        while ($cursor->lt($end)) {
            if (!$cursor->isWeekend()) {
                $hours++;
            }
            $cursor->addHour();
        }

        return max(0, $hours);
    }
}
