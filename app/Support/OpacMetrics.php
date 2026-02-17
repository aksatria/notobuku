<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OpacMetrics
{
    private const SAMPLE_SIZE = 500;
    private const TTL_DAYS = 35;
    private const HISTORY_MAX_EVENTS = 12000;
    private const HISTORY_24H_MINUTE_POINTS = 1440;
    private const SLO_ALERT_COOLDOWN_DEFAULT = 15;
    private const SEARCH_ALERT_COOLDOWN_DEFAULT = 15;

    public static function recordRequest(string $endpoint, int $statusCode, float $milliseconds, string $traceId = ''): void
    {
        self::incrementRequests();
        if ($statusCode >= 500) {
            self::incrementErrors();
        }
        self::incrementEndpointCounter($endpoint, 'requests');
        if ($statusCode >= 500) {
            self::incrementEndpointCounter($endpoint, 'errors');
        }

        $ms = max(0, (int) round($milliseconds));
        $samples = Cache::get(self::latencyKey(), []);
        if (!is_array($samples)) {
            $samples = [];
        }
        $samples[] = $ms;
        if (count($samples) > self::SAMPLE_SIZE) {
            $samples = array_slice($samples, -self::SAMPLE_SIZE);
        }
        Cache::put(self::latencyKey(), $samples, now()->addDays(self::TTL_DAYS));
        self::appendHistoryEvent($ms, $statusCode, $endpoint, $traceId);
    }

    public static function incrementRequests(int $count = 1): void
    {
        Cache::increment(self::counterKey('requests'), max(1, $count));
        Cache::put(self::counterKey('requests'), (int) Cache::get(self::counterKey('requests'), 0), now()->addDays(self::TTL_DAYS));
    }

    public static function incrementErrors(int $count = 1): void
    {
        Cache::increment(self::counterKey('errors'), max(1, $count));
        Cache::put(self::counterKey('errors'), (int) Cache::get(self::counterKey('errors'), 0), now()->addDays(self::TTL_DAYS));
    }

    public static function snapshot(): array
    {
        $samples = Cache::get(self::latencyKey(), []);
        if (!is_array($samples)) {
            $samples = [];
        }
        $vals = array_values(array_map('intval', $samples));
        sort($vals);
        $count = count($vals);

        $requests = (int) Cache::get(self::counterKey('requests'), 0);
        $errors = (int) Cache::get(self::counterKey('errors'), 0);
        $errorRate = $requests > 0 ? round(($errors / $requests) * 100, 2) : 0.0;

        $history24h = self::historyLast24h();
        $slo = self::sloStatus($history24h);
        $searchAnalytics = self::searchAnalytics();
        $endpointStats = self::endpointStats();
        self::dispatchSloAlertIfNeeded($slo);
        self::dispatchSearchAnalyticsAlertIfNeeded($searchAnalytics);

        return [
            'requests' => $requests,
            'errors' => $errors,
            'error_rate_pct' => $errorRate,
            'latency' => [
                'count' => $count,
                'p50_ms' => $count > 0 ? self::percentile($vals, 50) : 0,
                'p95_ms' => $count > 0 ? self::percentile($vals, 95) : 0,
                'max_ms' => $count > 0 ? (int) end($vals) : 0,
            ],
            'endpoints' => $endpointStats,
            'slo' => $slo,
            'search_analytics' => $searchAnalytics,
            'history' => [
                'last_24h' => $history24h,
            ],
        ];
    }

    private static function searchAnalytics(): array
    {
        $cacheKey = 'opac:metrics:search_analytics';

        return Cache::remember($cacheKey, now()->addSeconds(60), function () {
            if (!Schema::hasTable('search_queries')) {
                return self::emptySearchAnalytics();
            }

            $base = DB::table('search_queries');
            $totalDistinct = (int) (clone $base)->count();
            $totalSearches = (int) (clone $base)->sum('search_count');
            $zeroDistinct = (int) (clone $base)->where('last_hits', '<=', 0)->count();
            $zeroSearches = (int) (clone $base)->where('last_hits', '<=', 0)->sum('search_count');

            $topKeywords = (clone $base)
                ->where('search_count', '>', 0)
                ->orderByDesc('search_count')
                ->orderByDesc('last_searched_at')
                ->limit(10)
                ->get(['query', 'search_count', 'last_hits', 'last_searched_at'])
                ->map(fn ($row) => [
                    'query' => (string) ($row->query ?? ''),
                    'search_count' => (int) ($row->search_count ?? 0),
                    'last_hits' => (int) ($row->last_hits ?? 0),
                    'last_searched_at' => $row->last_searched_at ? (string) $row->last_searched_at : null,
                ])
                ->values()
                ->all();

            $topZero = (clone $base)
                ->where('last_hits', '<=', 0)
                ->where('search_count', '>', 0)
                ->orderByDesc('search_count')
                ->orderByDesc('last_searched_at')
                ->limit(10)
                ->get(['query', 'search_count', 'last_searched_at'])
                ->map(fn ($row) => [
                    'query' => (string) ($row->query ?? ''),
                    'search_count' => (int) ($row->search_count ?? 0),
                    'last_searched_at' => $row->last_searched_at ? (string) $row->last_searched_at : null,
                ])
                ->values()
                ->all();

            $successSearches = max(0, $totalSearches - $zeroSearches);
            $successRate = $totalSearches > 0 ? round(($successSearches / $totalSearches) * 100, 2) : 0.0;
            $windowHours = max(1, min(168, (int) config('notobuku.opac.observability.search_window_hours', 24)));
            $windowStart = now()->subHours($windowHours);

            $windowSearches = 0;
            $windowZero = 0;
            if (Schema::hasTable('search_query_events')) {
                $events = DB::table('search_query_events')
                    ->where('searched_at', '>=', $windowStart)
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw('SUM(CASE WHEN is_zero_result = 1 THEN 1 ELSE 0 END) as zero_total')
                    ->first();
                $windowSearches = (int) ($events->total ?? 0);
                $windowZero = (int) ($events->zero_total ?? 0);
            } else {
                // Fallback lama: agregasi dari search_queries (approximate).
                $windowSearches = $totalSearches;
                $windowZero = $zeroSearches;
            }

            $windowClicks = 0;
            $windowBorrows = 0;
            if (Schema::hasTable('biblio_events')) {
                $events = DB::table('biblio_events')
                    ->where('created_at', '>=', $windowStart)
                    ->selectRaw("SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as click_total")
                    ->selectRaw("SUM(CASE WHEN event_type = 'borrow' THEN 1 ELSE 0 END) as borrow_total")
                    ->first();
                $windowClicks = (int) ($events->click_total ?? 0);
                $windowBorrows = (int) ($events->borrow_total ?? 0);
            }

            $zeroRate = $windowSearches > 0 ? round(($windowZero / $windowSearches) * 100, 2) : 0.0;
            $ctr = $windowSearches > 0 ? round(min(100.0, ($windowClicks / $windowSearches) * 100), 2) : 0.0;
            $noClick = $windowSearches > 0 ? round(max(0.0, 100.0 - $ctr), 2) : 0.0;
            $borrowConv = $windowSearches > 0 ? round(min(100.0, ($windowBorrows / $windowSearches) * 100), 2) : 0.0;
            $alertMeta = self::evaluateSearchAlertState($windowSearches, $zeroRate, $noClick);

            return [
                'total_distinct_queries' => $totalDistinct,
                'total_searches' => $totalSearches,
                'zero_result_distinct_queries' => $zeroDistinct,
                'zero_result_searches' => $zeroSearches,
                'successful_searches' => $successSearches,
                'success_rate_pct' => $successRate,
                'window_hours' => $windowHours,
                'window_searches' => $windowSearches,
                'window_clicks' => $windowClicks,
                'window_borrows' => $windowBorrows,
                'zero_result_rate_pct' => $zeroRate,
                'click_through_rate_pct' => $ctr,
                'no_click_rate_pct' => $noClick,
                'conversion_to_borrow_rate_pct' => $borrowConv,
                'derived_from_events' => Schema::hasTable('search_query_events') && Schema::hasTable('biblio_events'),
                'no_click_rate_estimated' => true,
                'alert_state' => (string) ($alertMeta['state'] ?? 'ok'),
                'alert_enabled' => (bool) ($alertMeta['enabled'] ?? true),
                'alert_min_window_searches' => (int) ($alertMeta['min_window_searches'] ?? 20),
                'alert_thresholds' => (array) ($alertMeta['thresholds'] ?? []),
                'alert_last_triggered_at' => (string) Cache::get(self::searchAlertKey(), ''),
                'top_keywords' => $topKeywords,
                'top_zero_result_queries' => $topZero,
            ];
        });
    }

    private static function emptySearchAnalytics(): array
    {
        return [
            'total_distinct_queries' => 0,
            'total_searches' => 0,
            'zero_result_distinct_queries' => 0,
            'zero_result_searches' => 0,
            'successful_searches' => 0,
            'success_rate_pct' => 0.0,
            'window_hours' => max(1, min(168, (int) config('notobuku.opac.observability.search_window_hours', 24))),
            'window_searches' => 0,
            'window_clicks' => 0,
            'window_borrows' => 0,
            'zero_result_rate_pct' => 0.0,
            'click_through_rate_pct' => 0.0,
            'no_click_rate_pct' => 0.0,
            'conversion_to_borrow_rate_pct' => 0.0,
            'derived_from_events' => false,
            'no_click_rate_estimated' => true,
            'alert_state' => 'ok',
            'alert_enabled' => (bool) config('notobuku.opac.observability.alerts.enabled', true),
            'alert_min_window_searches' => max(1, (int) config('notobuku.opac.observability.alerts.min_window_searches', 20)),
            'alert_thresholds' => [
                'zero_result_warn_pct' => (float) config('notobuku.opac.observability.alerts.zero_result_warn_pct', 35.0),
                'zero_result_critical_pct' => (float) config('notobuku.opac.observability.alerts.zero_result_critical_pct', 55.0),
                'no_click_warn_pct' => (float) config('notobuku.opac.observability.alerts.no_click_warn_pct', 65.0),
                'no_click_critical_pct' => (float) config('notobuku.opac.observability.alerts.no_click_critical_pct', 80.0),
            ],
            'alert_last_triggered_at' => (string) Cache::get(self::searchAlertKey(), ''),
            'top_keywords' => [],
            'top_zero_result_queries' => [],
        ];
    }

    private static function appendHistoryEvent(int $ms, int $statusCode, string $endpoint = '', string $traceId = ''): void
    {
        $events = Cache::get(self::historyKey(), []);
        if (!is_array($events)) {
            $events = [];
        }
        $nowTs = now()->timestamp;
        $minTs = $nowTs - (24 * 60 * 60);
        $events[] = [
            'ts' => $nowTs,
            'ms' => max(0, $ms),
            'status' => max(0, $statusCode),
            'endpoint' => trim((string) $endpoint),
            'trace' => trim((string) $traceId),
        ];
        $events = array_values(array_filter($events, function ($row) use ($minTs) {
            return is_array($row) && (int) ($row['ts'] ?? 0) >= $minTs;
        }));
        if (count($events) > self::HISTORY_MAX_EVENTS) {
            $events = array_slice($events, -self::HISTORY_MAX_EVENTS);
        }
        Cache::put(self::historyKey(), $events, now()->addDays(self::TTL_DAYS));
    }

    private static function historyLast24h(): array
    {
        $events = Cache::get(self::historyKey(), []);
        if (!is_array($events)) {
            $events = [];
        }

        $bucket = [];
        foreach ($events as $row) {
            if (!is_array($row)) {
                continue;
            }
            $ts = (int) ($row['ts'] ?? 0);
            $ms = (int) ($row['ms'] ?? 0);
            $status = (int) ($row['status'] ?? 0);
            if ($ts <= 0) {
                continue;
            }
            $minute = date('Y-m-d H:i:00', $ts);
            $bucket[$minute] = $bucket[$minute] ?? [];
            $bucket[$minute][] = [
                'ms' => $ms,
                'status' => $status,
            ];
        }

        $rows = [];
        for ($i = self::HISTORY_24H_MINUTE_POINTS - 1; $i >= 0; $i--) {
            $minute = now()->copy()->subMinutes($i)->format('Y-m-d H:i:00');
            $items = $bucket[$minute] ?? [];
            $vals = array_values(array_map(fn ($it) => (int) ($it['ms'] ?? 0), $items));
            sort($vals);
            $total = count($items);
            $bad = 0;
            $latencyBudgetMs = (int) config('notobuku.opac.slo.latency_budget_ms', 1500);
            foreach ($items as $it) {
                $status = (int) ($it['status'] ?? 0);
                $ms = (int) ($it['ms'] ?? 0);
                if ($status >= 500 || $ms > $latencyBudgetMs) {
                    $bad++;
                }
            }
            $rows[] = [
                'minute' => $minute,
                'p95_ms' => !empty($vals) ? self::percentile($vals, 95) : 0,
                'total' => $total,
                'bad' => $bad,
            ];
        }

        return $rows;
    }

    private static function sloStatus(array $history24h): array
    {
        $targetPct = (float) config('notobuku.opac.slo.availability_target_pct', 99.5);
        $targetPct = max(90.0, min(99.99, $targetPct));
        $errorBudget = max(0.0001, 1 - ($targetPct / 100));

        $w5m = self::windowStats($history24h, 5);
        $w60m = self::windowStats($history24h, 60);

        $badRate5m = $w5m['total'] > 0 ? ($w5m['bad'] / $w5m['total']) : 0.0;
        $badRate60m = $w60m['total'] > 0 ? ($w60m['bad'] / $w60m['total']) : 0.0;
        $burn5m = $errorBudget > 0 ? round($badRate5m / $errorBudget, 2) : 0.0;
        $burn60m = $errorBudget > 0 ? round($badRate60m / $errorBudget, 2) : 0.0;

        $warnBurn = (float) config('notobuku.opac.slo.burn_rate_warning', 2.0);
        $critBurn = (float) config('notobuku.opac.slo.burn_rate_critical', 5.0);

        $state = 'ok';
        if ($burn5m >= $warnBurn || $burn60m >= $warnBurn) {
            $state = 'warning';
        }
        if ($burn5m >= $critBurn || $burn60m >= $critBurn) {
            $state = 'critical';
        }

        return [
            'target_pct' => $targetPct,
            'error_budget_pct' => round($errorBudget * 100, 4),
            'bad_rate_5m_pct' => round($badRate5m * 100, 4),
            'bad_rate_60m_pct' => round($badRate60m * 100, 4),
            'burn_rate_5m' => $burn5m,
            'burn_rate_60m' => $burn60m,
            'warning_threshold' => $warnBurn,
            'critical_threshold' => $critBurn,
            'state' => $state,
        ];
    }

    private static function windowStats(array $history24h, int $minutes): array
    {
        $minutes = max(1, min(1440, $minutes));
        $rows = array_slice($history24h, -$minutes);
        $total = 0;
        $bad = 0;
        foreach ($rows as $row) {
            $total += (int) ($row['total'] ?? 0);
            $bad += (int) ($row['bad'] ?? 0);
        }
        return ['total' => $total, 'bad' => $bad];
    }

    private static function incrementEndpointCounter(string $endpoint, string $metric, int $delta = 1): void
    {
        $endpoint = trim($endpoint) !== '' ? trim($endpoint) : 'unknown';
        $metric = trim($metric) !== '' ? trim($metric) : 'requests';
        $key = self::endpointKey($endpoint, $metric);
        Cache::increment($key, max(1, $delta));
        Cache::put($key, (int) Cache::get($key, 0), now()->addDays(self::TTL_DAYS));

        $listKey = self::endpointListKey();
        $list = Cache::get($listKey, []);
        if (!is_array($list)) {
            $list = [];
        }
        if (!in_array($endpoint, $list, true)) {
            $list[] = $endpoint;
            if (count($list) > 32) {
                $list = array_slice($list, -32);
            }
            Cache::put($listKey, $list, now()->addDays(self::TTL_DAYS));
        }
    }

    private static function endpointStats(): array
    {
        $rows = [];
        $list = Cache::get(self::endpointListKey(), []);
        if (!is_array($list)) {
            $list = [];
        }
        foreach ($list as $endpoint) {
            $req = (int) Cache::get(self::endpointKey($endpoint, 'requests'), 0);
            $err = (int) Cache::get(self::endpointKey($endpoint, 'errors'), 0);
            if ($req <= 0 && $err <= 0) {
                continue;
            }
            $rows[] = [
                'endpoint' => (string) $endpoint,
                'requests' => $req,
                'errors' => $err,
                'error_rate_pct' => $req > 0 ? round(($err / $req) * 100, 2) : 0.0,
            ];
        }
        usort($rows, fn ($a, $b) => ($b['requests'] <=> $a['requests']));
        return array_slice($rows, 0, 10);
    }

    private static function dispatchSloAlertIfNeeded(array $slo): void
    {
        $state = (string) ($slo['state'] ?? 'ok');
        if ($state !== 'critical') {
            return;
        }
        $cooldown = max(1, (int) config('notobuku.opac.slo.alert_cooldown_minutes', self::SLO_ALERT_COOLDOWN_DEFAULT));
        $last = (string) Cache::get(self::sloAlertKey(), '');
        if ($last !== '') {
            $prevTs = strtotime($last);
            if ($prevTs !== false && (time() - $prevTs) < ($cooldown * 60)) {
                return;
            }
        }

        $payload = [
            'at' => now()->toIso8601String(),
            'state' => $state,
            'burn_rate_5m' => (float) ($slo['burn_rate_5m'] ?? 0),
            'burn_rate_60m' => (float) ($slo['burn_rate_60m'] ?? 0),
            'target_pct' => (float) ($slo['target_pct'] ?? 99.5),
        ];
        Log::warning('OPAC SLO burn-rate critical.', $payload);

        $webhook = trim((string) config('notobuku.opac.slo.alert_webhook_url', ''));
        if ($webhook !== '') {
            try {
                Http::timeout(4)->post($webhook, $payload);
            } catch (\Throwable $e) {
                Log::warning('OPAC SLO webhook dispatch failed: ' . $e->getMessage());
            }
        }

        Cache::put(self::sloAlertKey(), $payload['at'], now()->addDays(self::TTL_DAYS));
    }

    private static function dispatchSearchAnalyticsAlertIfNeeded(array $searchAnalytics): void
    {
        $windowSearches = (int) ($searchAnalytics['window_searches'] ?? 0);
        $zeroRate = (float) ($searchAnalytics['zero_result_rate_pct'] ?? 0.0);
        $noClickRate = (float) ($searchAnalytics['no_click_rate_pct'] ?? 0.0);
        $meta = self::evaluateSearchAlertState($windowSearches, $zeroRate, $noClickRate);

        if (!((bool) ($meta['enabled'] ?? true))) {
            return;
        }
        if (!((bool) ($meta['eligible'] ?? false))) {
            return;
        }
        $state = (string) ($meta['state'] ?? 'ok');
        if ($state === 'ok') {
            return;
        }

        $cooldown = max(1, (int) config('notobuku.opac.observability.alerts.cooldown_minutes', self::SEARCH_ALERT_COOLDOWN_DEFAULT));
        $last = (string) Cache::get(self::searchAlertKey(), '');
        if ($last !== '') {
            $prevTs = strtotime($last);
            if ($prevTs !== false && (time() - $prevTs) < ($cooldown * 60)) {
                return;
            }
        }

        $payload = [
            'event' => 'opac_search_quality_alert',
            'at' => now()->toIso8601String(),
            'state' => $state,
            'window_hours' => (int) ($searchAnalytics['window_hours'] ?? 24),
            'window_searches' => $windowSearches,
            'zero_result_rate_pct' => $zeroRate,
            'no_click_rate_pct' => $noClickRate,
            'click_through_rate_pct' => (float) ($searchAnalytics['click_through_rate_pct'] ?? 0.0),
            'conversion_to_borrow_rate_pct' => (float) ($searchAnalytics['conversion_to_borrow_rate_pct'] ?? 0.0),
            'thresholds' => (array) ($meta['thresholds'] ?? []),
        ];

        Log::warning('OPAC search quality degradation detected.', $payload);

        $webhook = trim((string) config('notobuku.opac.observability.alerts.webhook_url', ''));
        if ($webhook === '') {
            $webhook = trim((string) config('notobuku.opac.slo.alert_webhook_url', ''));
        }
        if ($webhook !== '') {
            try {
                Http::timeout(4)->post($webhook, $payload);
            } catch (\Throwable $e) {
                Log::warning('OPAC search quality webhook dispatch failed: ' . $e->getMessage());
            }
        }

        Cache::put(self::searchAlertKey(), $payload['at'], now()->addDays(self::TTL_DAYS));
    }

    private static function evaluateSearchAlertState(int $windowSearches, float $zeroRate, float $noClickRate): array
    {
        $enabled = (bool) config('notobuku.opac.observability.alerts.enabled', true);
        $minSamples = max(1, (int) config('notobuku.opac.observability.alerts.min_window_searches', 20));
        $warnZero = (float) config('notobuku.opac.observability.alerts.zero_result_warn_pct', 35.0);
        $critZero = (float) config('notobuku.opac.observability.alerts.zero_result_critical_pct', 55.0);
        $warnNoClick = (float) config('notobuku.opac.observability.alerts.no_click_warn_pct', 65.0);
        $critNoClick = (float) config('notobuku.opac.observability.alerts.no_click_critical_pct', 80.0);

        $state = 'ok';
        if ($enabled && $windowSearches >= $minSamples) {
            if ($zeroRate >= $warnZero || $noClickRate >= $warnNoClick) {
                $state = 'warning';
            }
            if ($zeroRate >= $critZero || $noClickRate >= $critNoClick) {
                $state = 'critical';
            }
        }

        return [
            'enabled' => $enabled,
            'eligible' => $windowSearches >= $minSamples,
            'min_window_searches' => $minSamples,
            'state' => $state,
            'thresholds' => [
                'zero_result_warn_pct' => $warnZero,
                'zero_result_critical_pct' => $critZero,
                'no_click_warn_pct' => $warnNoClick,
                'no_click_critical_pct' => $critNoClick,
            ],
        ];
    }

    private static function percentile(array $sortedVals, int $percentile): int
    {
        if (empty($sortedVals)) {
            return 0;
        }
        $percentile = max(0, min(100, $percentile));
        $index = (int) ceil(($percentile / 100) * count($sortedVals)) - 1;
        $index = max(0, min(count($sortedVals) - 1, $index));
        return (int) $sortedVals[$index];
    }

    private static function latencyKey(): string
    {
        return 'opac:metrics:latency_samples';
    }

    private static function counterKey(string $name): string
    {
        return 'opac:metrics:counter:' . $name;
    }

    private static function historyKey(): string
    {
        return 'opac:metrics:history:events';
    }

    private static function endpointKey(string $endpoint, string $metric): string
    {
        return 'opac:metrics:endpoint:' . md5($endpoint) . ':' . $metric;
    }

    private static function endpointListKey(): string
    {
        return 'opac:metrics:endpoint:list';
    }

    private static function sloAlertKey(): string
    {
        return 'opac:metrics:slo:last_critical_alert_at';
    }

    private static function searchAlertKey(): string
    {
        return 'opac:metrics:search_quality:last_alert_at';
    }
}
