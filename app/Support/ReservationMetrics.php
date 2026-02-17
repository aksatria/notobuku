<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReservationMetrics
{
    public static function snapshot(?int $institutionId = null, ?int $windowDays = null): array
    {
        $windowDays = $windowDays ?: (int) config('notobuku.reservations.kpi.window_days', 30);
        $windowDays = max(1, min(365, $windowDays));
        $from = now()->subDays($windowDays);

        if (!Schema::hasTable('reservations')) {
            return [
                'ok' => false,
                'window_days' => $windowDays,
                'generated_at' => now()->toIso8601String(),
                'message' => 'reservations table not found',
            ];
        }

        $base = DB::table('reservations')->where('created_at', '>=', $from);
        if ($institutionId !== null && $institutionId > 0 && Schema::hasColumn('reservations', 'institution_id')) {
            $base->where('institution_id', $institutionId);
        }

        $total = (int) (clone $base)->count();
        $fulfilled = (int) (clone $base)->where('status', 'fulfilled')->count();
        $expired = (int) (clone $base)->where('status', 'expired')->count();
        $cancelled = (int) (clone $base)->where('status', 'cancelled')->count();
        $backlogQueued = (int) (clone $base)->where('status', 'queued')->count();

        $closed = max(1, $fulfilled + $expired + $cancelled);
        $readyTotal = (int) (clone $base)->whereIn('status', ['ready', 'fulfilled', 'expired'])->count();

        $fulfillmentRate = round(($fulfilled / $closed) * 100, 2);
        $expiryRate = $readyTotal > 0 ? round(($expired / $readyTotal) * 100, 2) : 0.0;
        $noShowRate = $expiryRate;

        $avgWaitMinutes = 0.0;
        if (Schema::hasColumn('reservations', 'ready_at')) {
            $waitQ = DB::table('reservations')
                ->whereNotNull('ready_at')
                ->where('created_at', '>=', $from)
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, ready_at)) as avg_wait_minutes');
            if ($institutionId !== null && $institutionId > 0 && Schema::hasColumn('reservations', 'institution_id')) {
                $waitQ->where('institution_id', $institutionId);
            }
            $avgWaitMinutes = (float) ($waitQ->value('avg_wait_minutes') ?? 0.0);
            if ($avgWaitMinutes < 0) {
                $avgWaitMinutes = 0;
            }
        }

        $series = self::dailySeries($institutionId, $from);

        $warnFulfillment = (float) config('notobuku.reservations.kpi.alert_fulfillment_min_pct', 70);
        $warnBacklog = (int) config('notobuku.reservations.kpi.alert_backlog_max', 100);
        $warnExpiry = (float) config('notobuku.reservations.kpi.alert_expiry_max_pct', 20);

        $health = 'Sehat';
        $class = 'good';
        if ($fulfillmentRate < $warnFulfillment || $backlogQueued > $warnBacklog || $expiryRate > $warnExpiry) {
            $health = 'Waspada';
            $class = 'warning';
        }
        if ($fulfillmentRate < ($warnFulfillment * 0.7) || $backlogQueued > ($warnBacklog * 1.5) || $expiryRate > ($warnExpiry * 1.5)) {
            $health = 'Kritis';
            $class = 'critical';
        }

        return [
            'ok' => true,
            'generated_at' => now()->toIso8601String(),
            'window_days' => $windowDays,
            'totals' => [
                'total' => $total,
                'fulfilled' => $fulfilled,
                'expired' => $expired,
                'cancelled' => $cancelled,
                'backlog_queued' => $backlogQueued,
            ],
            'kpi' => [
                'fulfillment_rate_pct' => $fulfillmentRate,
                'no_show_rate_pct' => $noShowRate,
                'avg_waiting_time_minutes' => round($avgWaitMinutes, 2),
                'expiry_rate_pct' => $expiryRate,
            ],
            'health' => [
                'label' => $health,
                'class' => $class,
            ],
            'series_30d' => $series,
        ];
    }

    private static function dailySeries(?int $institutionId, $from): array
    {
        $q = DB::table('reservations')
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw("SUM(CASE WHEN status='fulfilled' THEN 1 ELSE 0 END) as fulfilled")
            ->selectRaw("SUM(CASE WHEN status='expired' THEN 1 ELSE 0 END) as expired")
            ->selectRaw("SUM(CASE WHEN status='queued' THEN 1 ELSE 0 END) as queued")
            ->groupBy('day')
            ->orderBy('day');

        if ($institutionId !== null && $institutionId > 0 && Schema::hasColumn('reservations', 'institution_id')) {
            $q->where('institution_id', $institutionId);
        }

        return $q->get()->map(function ($row) {
            return [
                'day' => (string) ($row->day ?? ''),
                'fulfilled' => (int) ($row->fulfilled ?? 0),
                'expired' => (int) ($row->expired ?? 0),
                'queued' => (int) ($row->queued ?? 0),
            ];
        })->values()->all();
    }
}
