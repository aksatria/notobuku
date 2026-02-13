<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class InteropMetrics
{
    private const LATENCY_SAMPLE_SIZE = 500;
    private const DAILY_LATENCY_SAMPLE_SIZE = 5000;
    private const HISTORY_24H_MINUTE_POINTS = 1440;
    private const TTL_MINUTES = 60 * 24 * 35;
    private const DAILY_RETENTION_DAYS = 400;

    public static function incrementInvalidToken(string $endpoint = 'oai', int $count = 1): void
    {
        $count = max(1, $count);
        self::increment('interop:metrics:counter:' . $endpoint . ':invalid_token', $count);
        self::incrementDailyCounter($endpoint, 'invalid_token', $count);
    }

    public static function incrementSnapshotEvictions(int $count = 1): void
    {
        $count = max(1, $count);
        self::increment('interop:metrics:counter:oai:snapshot_evictions', $count);
        self::incrementDailyCounter('oai', 'snapshot_evictions', $count);
    }

    public static function incrementRateLimited(string $endpoint = 'oai', int $count = 1): void
    {
        $count = max(1, $count);
        self::increment('interop:metrics:counter:' . $endpoint . ':rate_limited', $count);
        self::incrementDailyCounter($endpoint, 'rate_limited', $count);
    }

    public static function recordLatency(string $endpoint, float $milliseconds): void
    {
        $endpoint = trim($endpoint) !== '' ? trim($endpoint) : 'unknown';
        $ms = max(0, (int) round($milliseconds));
        $key = self::latencyKey($endpoint);
        $samples = Cache::get($key, []);
        if (!is_array($samples)) {
            $samples = [];
        }
        $samples[] = $ms;
        if (count($samples) > self::LATENCY_SAMPLE_SIZE) {
            $samples = array_slice($samples, -self::LATENCY_SAMPLE_SIZE);
        }
        Cache::put($key, $samples, now()->addMinutes(self::TTL_MINUTES));
        self::appendDailyLatencySample($endpoint, $ms);
    }

    public static function snapshot(): array
    {
        $snapshot = [
            'generated_at' => now()->toIso8601String(),
            'counters' => [
                'oai_invalid_token' => (int) Cache::get('interop:metrics:counter:oai:invalid_token', 0),
                'sru_invalid_token' => (int) Cache::get('interop:metrics:counter:sru:invalid_token', 0),
                'oai_snapshot_evictions' => (int) Cache::get('interop:metrics:counter:oai:snapshot_evictions', 0),
                'oai_rate_limited' => (int) Cache::get('interop:metrics:counter:oai:rate_limited', 0),
                'sru_rate_limited' => (int) Cache::get('interop:metrics:counter:sru:rate_limited', 0),
            ],
            'latency' => [
                'oai' => self::latencyStats('oai'),
                'sru' => self::latencyStats('sru'),
            ],
        ];

        $snapshot['health'] = self::healthStatus($snapshot);
        $snapshot['history'] = [
            'last_24h' => self::historyLast24h(),
            'daily_35d' => self::dailySummary(35),
        ];
        $snapshot['alerts'] = self::evaluateAlerts($snapshot);
        self::recordHealthPoint($snapshot);

        return $snapshot;
    }

    public static function dailySummary(int $days = 30): array
    {
        $days = max(1, min(180, $days));
        if (Schema::hasTable('interop_metric_daily')) {
            return self::dailySummaryFromDatabase($days);
        }
        $rows = [];
        $today = Carbon::today();
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i)->toDateString();
            $oaiInvalid = (int) Cache::get(self::dailyCounterKey($day, 'oai', 'invalid_token'), 0);
            $sruInvalid = (int) Cache::get(self::dailyCounterKey($day, 'sru', 'invalid_token'), 0);
            $oaiLimited = (int) Cache::get(self::dailyCounterKey($day, 'oai', 'rate_limited'), 0);
            $sruLimited = (int) Cache::get(self::dailyCounterKey($day, 'sru', 'rate_limited'), 0);
            $oaiP95 = self::dailyLatencyP95($day, 'oai');
            $sruP95 = self::dailyLatencyP95($day, 'sru');

            $rows[] = [
                'day' => $day,
                'oai_p95_ms' => $oaiP95,
                'sru_p95_ms' => $sruP95,
                'p95_ms' => max($oaiP95, $sruP95),
                'oai_invalid_token' => $oaiInvalid,
                'sru_invalid_token' => $sruInvalid,
                'invalid_token_total' => $oaiInvalid + $sruInvalid,
                'oai_rate_limited' => $oaiLimited,
                'sru_rate_limited' => $sruLimited,
                'rate_limited_total' => $oaiLimited + $sruLimited,
            ];
        }

        return $rows;
    }

    private static function dailySummaryFromDatabase(int $days): array
    {
        $rows = [];
        $today = Carbon::today();
        $from = $today->copy()->subDays($days - 1)->toDateString();
        $map = DB::table('interop_metric_daily')
            ->where('day', '>=', $from)
            ->orderBy('day')
            ->get()
            ->keyBy(function ($r) {
                return (string) ($r->day ?? '');
            });

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i)->toDateString();
            $r = $map[$day] ?? null;
            $oaiP95 = (int) ($r->oai_p95_ms ?? 0);
            $sruP95 = (int) ($r->sru_p95_ms ?? 0);
            $oaiInvalid = (int) ($r->oai_invalid_token ?? 0);
            $sruInvalid = (int) ($r->sru_invalid_token ?? 0);
            $oaiLimited = (int) ($r->oai_rate_limited ?? 0);
            $sruLimited = (int) ($r->sru_rate_limited ?? 0);

            $rows[] = [
                'day' => $day,
                'oai_p95_ms' => $oaiP95,
                'sru_p95_ms' => $sruP95,
                'p95_ms' => max($oaiP95, $sruP95),
                'oai_invalid_token' => $oaiInvalid,
                'sru_invalid_token' => $sruInvalid,
                'invalid_token_total' => $oaiInvalid + $sruInvalid,
                'oai_rate_limited' => $oaiLimited,
                'sru_rate_limited' => $sruLimited,
                'rate_limited_total' => $oaiLimited + $sruLimited,
            ];
        }

        return $rows;
    }

    public static function healthStatus(array $snapshot): array
    {
        $p95 = max(
            (int) data_get($snapshot, 'latency.oai.p95_ms', 0),
            (int) data_get($snapshot, 'latency.sru.p95_ms', 0)
        );
        $invalid = (int) data_get($snapshot, 'counters.oai_invalid_token', 0)
            + (int) data_get($snapshot, 'counters.sru_invalid_token', 0);
        $limited = (int) data_get($snapshot, 'counters.oai_rate_limited', 0)
            + (int) data_get($snapshot, 'counters.sru_rate_limited', 0);

        $warnP95 = (int) config('notobuku.interop.health_thresholds.warning.p95_ms', 1500);
        $warnInvalid = (int) config('notobuku.interop.health_thresholds.warning.invalid_token', 20);
        $warnLimited = (int) config('notobuku.interop.health_thresholds.warning.rate_limited', 20);

        $critP95 = (int) config('notobuku.interop.health_thresholds.critical.p95_ms', 3000);
        $critInvalid = (int) config('notobuku.interop.health_thresholds.critical.invalid_token', 50);
        $critLimited = (int) config('notobuku.interop.health_thresholds.critical.rate_limited', 50);

        $label = 'Sehat';
        $class = 'good';
        if ($p95 > $warnP95 || $limited > $warnLimited || $invalid > $warnInvalid) {
            $label = 'Waspada';
            $class = 'warning';
        }
        if ($p95 > $critP95 || $limited > $critLimited || $invalid > $critInvalid) {
            $label = 'Kritis';
            $class = 'critical';
        }

        return [
            'label' => $label,
            'class' => $class,
            'p95_ms' => $p95,
            'invalid_token_total' => $invalid,
            'rate_limited_total' => $limited,
        ];
    }

    private static function evaluateAlerts(array $snapshot): array
    {
        $criticalStreakThreshold = (int) config('notobuku.interop.alerts.critical_streak_minutes', 5);
        $cooldownMinutes = (int) config('notobuku.interop.alerts.cooldown_minutes', 15);
        $history = self::historyLast24h();
        $streak = self::criticalStreakMinutes($history);
        $isActive = $streak >= max(1, $criticalStreakThreshold);
        $lastTriggeredAt = (string) Cache::get(self::alertLastTriggeredKey(), '');

        if ($isActive && self::shouldTriggerAlert($lastTriggeredAt, $cooldownMinutes)) {
            $payload = [
                'at' => now()->toIso8601String(),
                'streak_minutes' => $streak,
                'threshold_minutes' => $criticalStreakThreshold,
                'health' => data_get($snapshot, 'health'),
            ];
            Log::warning('Interop health critical streak detected.', $payload);
            Cache::put(self::alertLastTriggeredKey(), $payload['at'], now()->addDays(self::DAILY_RETENTION_DAYS));
            self::persistDailyAlertTimestamp((string) now()->toDateString(), $payload['at']);
            self::dispatchCriticalAlert($payload);
            $lastTriggeredAt = $payload['at'];
        }

        return [
            'critical_streak' => [
                'active' => $isActive,
                'streak_minutes' => $streak,
                'threshold_minutes' => max(1, $criticalStreakThreshold),
                'last_triggered_at' => $lastTriggeredAt,
            ],
        ];
    }

    private static function latencyStats(string $endpoint): array
    {
        $samples = Cache::get(self::latencyKey($endpoint), []);
        if (!is_array($samples) || count($samples) === 0) {
            return [
                'count' => 0,
                'p50_ms' => 0,
                'p95_ms' => 0,
                'max_ms' => 0,
            ];
        }

        $vals = array_values(array_map('intval', $samples));
        sort($vals);
        $count = count($vals);

        return [
            'count' => $count,
            'p50_ms' => self::percentile($vals, 50),
            'p95_ms' => self::percentile($vals, 95),
            'max_ms' => (int) end($vals),
        ];
    }

    private static function recordHealthPoint(array $snapshot): void
    {
        $health = (array) data_get($snapshot, 'health', []);
        $point = [
            'minute' => now()->format('Y-m-d H:i'),
            'at' => now()->toIso8601String(),
            'label' => (string) ($health['label'] ?? 'Sehat'),
            'class' => (string) ($health['class'] ?? 'good'),
            'p95_ms' => (int) ($health['p95_ms'] ?? 0),
            'invalid_token_total' => (int) ($health['invalid_token_total'] ?? 0),
            'rate_limited_total' => (int) ($health['rate_limited_total'] ?? 0),
        ];

        $key = self::history24hKey();
        $rows = Cache::get($key, []);
        if (!is_array($rows)) {
            $rows = [];
        }

        $last = end($rows);
        if (is_array($last) && (($last['minute'] ?? '') === $point['minute'])) {
            array_pop($rows);
        }
        $rows[] = $point;
        if (count($rows) > self::HISTORY_24H_MINUTE_POINTS) {
            $rows = array_slice($rows, -self::HISTORY_24H_MINUTE_POINTS);
        }
        Cache::put($key, array_values($rows), now()->addDays(self::DAILY_RETENTION_DAYS));
        self::persistHealthPoint($point);
    }

    private static function historyLast24h(): array
    {
        if (Schema::hasTable('interop_metric_points')) {
            $from = now()->subDay()->startOfMinute();
            return DB::table('interop_metric_points')
                ->where('minute_at', '>=', $from)
                ->orderBy('minute_at')
                ->get([
                    'minute_at',
                    'health_label',
                    'health_class',
                    'p95_ms',
                    'invalid_token_total',
                    'rate_limited_total',
                ])
                ->map(function ($r) {
                    return [
                        'minute' => Carbon::parse((string) $r->minute_at)->format('Y-m-d H:i'),
                        'at' => Carbon::parse((string) $r->minute_at)->toIso8601String(),
                        'label' => (string) ($r->health_label ?? 'Sehat'),
                        'class' => (string) ($r->health_class ?? 'good'),
                        'p95_ms' => (int) ($r->p95_ms ?? 0),
                        'invalid_token_total' => (int) ($r->invalid_token_total ?? 0),
                        'rate_limited_total' => (int) ($r->rate_limited_total ?? 0),
                    ];
                })
                ->all();
        }

        $rows = Cache::get(self::history24hKey(), []);
        if (!is_array($rows)) {
            return [];
        }
        return array_values($rows);
    }

    private static function criticalStreakMinutes(array $history): int
    {
        if (count($history) === 0) {
            return 0;
        }
        $streak = 0;
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $row = (array) ($history[$i] ?? []);
            if ((string) ($row['label'] ?? '') !== 'Kritis') {
                break;
            }
            $streak++;
        }
        return $streak;
    }

    private static function shouldTriggerAlert(string $lastTriggeredAt, int $cooldownMinutes): bool
    {
        $cooldownMinutes = max(1, $cooldownMinutes);
        if (trim($lastTriggeredAt) === '') {
            return true;
        }
        try {
            $last = Carbon::parse($lastTriggeredAt);
            return $last->diffInMinutes(now()) >= $cooldownMinutes;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private static function incrementDailyCounter(string $endpoint, string $metric, int $count): void
    {
        $day = now()->toDateString();
        $key = self::dailyCounterKey($day, $endpoint, $metric);
        if (!Cache::has($key)) {
            Cache::put($key, 0, now()->addDays(self::DAILY_RETENTION_DAYS));
        }
        Cache::increment($key, $count);
        Cache::put($key, (int) Cache::get($key, 0), now()->addDays(self::DAILY_RETENTION_DAYS));
        self::persistDailyCounter($day, $endpoint, $metric, $count);
    }

    private static function appendDailyLatencySample(string $endpoint, int $ms): void
    {
        $day = now()->toDateString();
        $key = self::dailyLatencySamplesKey($day, $endpoint);
        $samples = Cache::get($key, []);
        if (!is_array($samples)) {
            $samples = [];
        }
        $samples[] = $ms;
        if (count($samples) > self::DAILY_LATENCY_SAMPLE_SIZE) {
            $samples = array_slice($samples, -self::DAILY_LATENCY_SAMPLE_SIZE);
        }
        Cache::put($key, $samples, now()->addDays(self::DAILY_RETENTION_DAYS));
        self::persistDailyLatencyP95($day, $endpoint, self::dailyLatencyP95($day, $endpoint));
    }

    private static function dailyLatencyP95(string $day, string $endpoint): int
    {
        $samples = Cache::get(self::dailyLatencySamplesKey($day, $endpoint), []);
        if (!is_array($samples) || count($samples) === 0) {
            return 0;
        }
        $vals = array_values(array_map('intval', $samples));
        sort($vals);
        return self::percentile($vals, 95);
    }

    private static function percentile(array $sortedVals, int $percentile): int
    {
        if (count($sortedVals) === 0) {
            return 0;
        }
        $percentile = max(0, min(100, $percentile));
        $index = (int) ceil(($percentile / 100) * count($sortedVals)) - 1;
        $index = max(0, min(count($sortedVals) - 1, $index));
        return (int) $sortedVals[$index];
    }

    private static function latencyKey(string $endpoint): string
    {
        return 'interop:metrics:latency:' . $endpoint;
    }

    private static function history24hKey(): string
    {
        return 'interop:metrics:history:24h';
    }

    private static function dailyCounterKey(string $day, string $endpoint, string $metric): string
    {
        return "interop:metrics:daily:{$day}:{$endpoint}:{$metric}";
    }

    private static function dailyLatencySamplesKey(string $day, string $endpoint): string
    {
        return "interop:metrics:daily:{$day}:{$endpoint}:latency_samples";
    }

    private static function alertLastTriggeredKey(): string
    {
        return 'interop:metrics:alert:critical_streak:last_triggered_at';
    }

    private static function increment(string $key, int $count): void
    {
        if (!Cache::has($key)) {
            Cache::put($key, 0, now()->addMinutes(self::TTL_MINUTES));
        }
        Cache::increment($key, $count);
        Cache::put($key, (int) Cache::get($key, 0), now()->addMinutes(self::TTL_MINUTES));
    }

    private static function persistHealthPoint(array $point): void
    {
        if (!Schema::hasTable('interop_metric_points')) {
            return;
        }

        $minuteAt = (string) ($point['minute'] ?? '');
        if ($minuteAt === '') {
            return;
        }

        DB::table('interop_metric_points')->updateOrInsert(
            ['minute_at' => $minuteAt . ':00'],
            [
                'health_label' => (string) ($point['label'] ?? 'Sehat'),
                'health_class' => (string) ($point['class'] ?? 'good'),
                'p95_ms' => (int) ($point['p95_ms'] ?? 0),
                'invalid_token_total' => (int) ($point['invalid_token_total'] ?? 0),
                'rate_limited_total' => (int) ($point['rate_limited_total'] ?? 0),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private static function persistDailyCounter(string $day, string $endpoint, string $metric, int $count): void
    {
        if (!Schema::hasTable('interop_metric_daily')) {
            return;
        }

        $column = match ($endpoint . ':' . $metric) {
            'oai:invalid_token' => 'oai_invalid_token',
            'sru:invalid_token' => 'sru_invalid_token',
            'oai:rate_limited' => 'oai_rate_limited',
            'sru:rate_limited' => 'sru_rate_limited',
            'oai:snapshot_evictions' => 'oai_snapshot_evictions',
            default => null,
        };
        if ($column === null) {
            return;
        }

        DB::table('interop_metric_daily')->updateOrInsert(
            ['day' => $day],
            ['day' => $day, 'updated_at' => now(), 'created_at' => now()]
        );
        DB::table('interop_metric_daily')->where('day', $day)->increment($column, max(1, $count));
    }

    private static function persistDailyLatencyP95(string $day, string $endpoint, int $p95): void
    {
        if (!Schema::hasTable('interop_metric_daily')) {
            return;
        }
        $column = $endpoint === 'oai' ? 'oai_p95_ms' : ($endpoint === 'sru' ? 'sru_p95_ms' : null);
        if ($column === null) {
            return;
        }

        DB::table('interop_metric_daily')->updateOrInsert(
            ['day' => $day],
            [
                'day' => $day,
                $column => max(0, $p95),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        DB::table('interop_metric_daily')
            ->where('day', $day)
            ->update([$column => max(0, $p95), 'updated_at' => now()]);
    }

    private static function persistDailyAlertTimestamp(string $day, string $at): void
    {
        if (!Schema::hasTable('interop_metric_daily')) {
            return;
        }
        DB::table('interop_metric_daily')->updateOrInsert(
            ['day' => $day],
            [
                'day' => $day,
                'last_critical_alert_at' => $at,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private static function dispatchCriticalAlert(array $payload): void
    {
        $emailTo = trim((string) config('notobuku.interop.alerts.email_to', ''));
        $webhookUrl = trim((string) config('notobuku.interop.alerts.webhook_url', ''));
        $message = 'Interop alert: status Kritis berturut-turut '
            . (int) ($payload['streak_minutes'] ?? 0)
            . ' menit (threshold '
            . (int) ($payload['threshold_minutes'] ?? 0)
            . ').';

        if ($emailTo !== '') {
            try {
                Mail::raw($message, function ($mail) use ($emailTo) {
                    $mail->to($emailTo)->subject('[NOTOBUKU] Interop Critical Alert');
                });
            } catch (\Throwable $e) {
                Log::warning('Failed sending interop email alert: ' . $e->getMessage());
            }
        }

        if ($webhookUrl !== '') {
            try {
                Http::timeout(5)->post($webhookUrl, [
                    'event' => 'interop_critical_streak',
                    'payload' => $payload,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed sending interop webhook alert: ' . $e->getMessage());
            }
        }
    }
}
