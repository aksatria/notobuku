<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class CirculationMetrics
{
    private const LATENCY_SAMPLE_SIZE = 500;
    private const TTL_DAYS = 30;

    public static function recordEndpoint(string $endpoint, float $milliseconds, int $statusCode): void
    {
        $endpoint = self::normalizeEndpoint($endpoint);
        $ms = max(0, (int) round($milliseconds));

        self::increment("circulation:metrics:endpoint:{$endpoint}:requests");
        self::appendLatencySample($endpoint, $ms);

        if ($statusCode >= 500) {
            self::increment("circulation:metrics:endpoint:{$endpoint}:server_errors");
        } elseif ($statusCode >= 400) {
            self::increment("circulation:metrics:endpoint:{$endpoint}:client_errors");
        }

        self::trackEndpoint($endpoint);
    }

    public static function recordBusinessOutcome(string $action, bool $ok): void
    {
        $action = trim(strtolower($action));
        if ($action === '') {
            $action = 'unknown';
        }

        self::increment("circulation:metrics:business:{$action}:" . ($ok ? 'success' : 'failure'));
    }

    public static function incrementFailureReason(string $action, string $reason, int $count = 1): void
    {
        $action = trim(strtolower($action));
        if ($action === '') {
            $action = 'unknown';
        }
        $count = max(1, $count);

        $bucket = self::classifyReason($reason);
        self::increment("circulation:metrics:reason:{$action}:{$bucket}", $count);
        self::increment("circulation:metrics:reason:all:{$bucket}", $count);

        self::trackReasonBucket($bucket);
    }

    public static function snapshot(int $topReasons = 5): array
    {
        $endpoints = self::trackedEndpoints();
        $endpointStats = [];
        $globalSamples = [];
        $totalReq = 0;
        $totalEndpointErrors = 0;

        foreach ($endpoints as $endpoint) {
            $req = (int) Cache::get("circulation:metrics:endpoint:{$endpoint}:requests", 0);
            $cErr = (int) Cache::get("circulation:metrics:endpoint:{$endpoint}:client_errors", 0);
            $sErr = (int) Cache::get("circulation:metrics:endpoint:{$endpoint}:server_errors", 0);
            $samples = Cache::get(self::latencyKey($endpoint), []);
            if (!is_array($samples)) {
                $samples = [];
            }
            $samples = array_values(array_map('intval', $samples));
            if (!empty($samples)) {
                $globalSamples = array_merge($globalSamples, $samples);
            }

            $p95 = self::latencyP95($samples);
            $endpointStats[] = [
                'endpoint' => $endpoint,
                'requests' => $req,
                'client_errors' => $cErr,
                'server_errors' => $sErr,
                'error_rate_pct' => $req > 0 ? round((($cErr + $sErr) / $req) * 100, 2) : 0.0,
                'p95_ms' => $p95,
            ];

            $totalReq += $req;
            $totalEndpointErrors += ($cErr + $sErr);
        }

        usort($endpointStats, fn($a, $b) => (int) ($b['requests'] ?? 0) <=> (int) ($a['requests'] ?? 0));

        $business = [
            'checkout' => self::businessSummary('checkout'),
            'return' => self::businessSummary('return'),
            'extend' => self::businessSummary('extend'),
        ];

        $businessTotalSuccess = array_sum(array_map(fn($r) => (int) ($r['success'] ?? 0), $business));
        $businessTotalFailure = array_sum(array_map(fn($r) => (int) ($r['failure'] ?? 0), $business));
        $businessTotal = $businessTotalSuccess + $businessTotalFailure;
        $businessFailureRate = $businessTotal > 0
            ? round(($businessTotalFailure / $businessTotal) * 100, 2)
            : 0.0;

        $top = self::topFailureReasons($topReasons);
        $globalP95 = self::latencyP95($globalSamples);

        $snapshot = [
            'generated_at' => now()->toIso8601String(),
            'totals' => [
                'requests' => $totalReq,
                'endpoint_errors' => $totalEndpointErrors,
                'endpoint_error_rate_pct' => $totalReq > 0 ? round(($totalEndpointErrors / $totalReq) * 100, 2) : 0.0,
                'business_success' => $businessTotalSuccess,
                'business_failure' => $businessTotalFailure,
                'business_failure_rate_pct' => $businessFailureRate,
                'latency_p95_ms' => $globalP95,
            ],
            'business' => $business,
            'top_failure_reasons' => $top,
            'by_endpoint' => array_slice($endpointStats, 0, 12),
        ];

        $snapshot['health'] = self::healthStatus($snapshot);

        return $snapshot;
    }

    private static function businessSummary(string $action): array
    {
        $success = (int) Cache::get("circulation:metrics:business:{$action}:success", 0);
        $failure = (int) Cache::get("circulation:metrics:business:{$action}:failure", 0);
        $total = $success + $failure;

        return [
            'success' => $success,
            'failure' => $failure,
            'failure_rate_pct' => $total > 0 ? round(($failure / $total) * 100, 2) : 0.0,
        ];
    }

    private static function healthStatus(array $snapshot): array
    {
        $p95 = (int) data_get($snapshot, 'totals.latency_p95_ms', 0);
        $failureRate = (float) data_get($snapshot, 'totals.business_failure_rate_pct', 0.0);

        $warnP95 = (int) config('notobuku.circulation.health_thresholds.warning.p95_ms', 1500);
        $warnFailureRate = (float) config('notobuku.circulation.health_thresholds.warning.failure_rate_pct', 7.0);

        $critP95 = (int) config('notobuku.circulation.health_thresholds.critical.p95_ms', 3000);
        $critFailureRate = (float) config('notobuku.circulation.health_thresholds.critical.failure_rate_pct', 15.0);

        $label = 'Sehat';
        $class = 'good';

        if ($p95 > $warnP95 || $failureRate > $warnFailureRate) {
            $label = 'Waspada';
            $class = 'warning';
        }
        if ($p95 > $critP95 || $failureRate > $critFailureRate) {
            $label = 'Kritis';
            $class = 'critical';
        }

        return [
            'label' => $label,
            'class' => $class,
            'p95_ms' => $p95,
            'business_failure_rate_pct' => $failureRate,
        ];
    }

    private static function appendLatencySample(string $endpoint, int $ms): void
    {
        $key = self::latencyKey($endpoint);
        $samples = Cache::get($key, []);
        if (!is_array($samples)) {
            $samples = [];
        }
        $samples[] = $ms;
        if (count($samples) > self::LATENCY_SAMPLE_SIZE) {
            $samples = array_slice($samples, -self::LATENCY_SAMPLE_SIZE);
        }
        Cache::put($key, $samples, now()->addDays(self::TTL_DAYS));
    }

    private static function topFailureReasons(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $buckets = self::trackedReasonBuckets();
        $rows = [];
        foreach ($buckets as $bucket) {
            $count = (int) Cache::get("circulation:metrics:reason:all:{$bucket}", 0);
            if ($count <= 0) {
                continue;
            }
            $rows[] = [
                'reason' => $bucket,
                'count' => $count,
            ];
        }
        usort($rows, fn($a, $b) => (int) ($b['count'] ?? 0) <=> (int) ($a['count'] ?? 0));
        return array_slice($rows, 0, $limit);
    }

    private static function classifyReason(string $message): string
    {
        $m = strtolower(trim($message));
        if ($m === '') {
            return 'unknown';
        }
        if (str_contains($m, 'duplikat') || str_contains($m, 'duplicate')) {
            return 'duplicate_submit';
        }
        if (str_contains($m, 'cabang') || str_contains($m, 'branch')) {
            return 'branch_policy';
        }
        if (str_contains($m, 'batas pinjam') || str_contains($m, 'maksimal')) {
            return 'policy_limit';
        }
        if (str_contains($m, 'tidak ditemukan') || str_contains($m, 'not found')) {
            return 'not_found';
        }
        if (str_contains($m, 'tidak tersedia') || str_contains($m, 'ditolak')) {
            return 'availability';
        }
        if (str_contains($m, 'perpanjangan') || str_contains($m, 'renew')) {
            return 'renew_policy';
        }
        if (str_contains($m, 'valid') || str_contains($m, 'format')) {
            return 'validation';
        }
        return 'unknown';
    }

    private static function increment(string $key, int $count = 1): void
    {
        $count = max(1, $count);
        if (!Cache::has($key)) {
            Cache::put($key, 0, now()->addDays(self::TTL_DAYS));
        }
        Cache::increment($key, $count);
        Cache::put($key, (int) Cache::get($key, 0), now()->addDays(self::TTL_DAYS));
    }

    private static function latencyP95(array $samples): int
    {
        if (count($samples) === 0) {
            return 0;
        }
        $vals = array_values(array_map('intval', $samples));
        sort($vals);
        $idx = (int) ceil(count($vals) * 0.95) - 1;
        $idx = max(0, min(count($vals) - 1, $idx));
        return (int) ($vals[$idx] ?? 0);
    }

    private static function normalizeEndpoint(string $endpoint): string
    {
        $endpoint = trim(strtolower($endpoint));
        if ($endpoint === '') {
            $endpoint = 'unknown';
        }
        return preg_replace('/[^a-z0-9._-]/', '_', $endpoint) ?: 'unknown';
    }

    private static function trackEndpoint(string $endpoint): void
    {
        $key = 'circulation:metrics:endpoints';
        $list = Cache::get($key, []);
        if (!is_array($list)) {
            $list = [];
        }
        if (!in_array($endpoint, $list, true)) {
            $list[] = $endpoint;
            $list = array_slice(array_values(array_unique($list)), -100);
            Cache::put($key, $list, now()->addDays(self::TTL_DAYS));
        }
    }

    private static function trackedEndpoints(): array
    {
        $list = Cache::get('circulation:metrics:endpoints', []);
        return is_array($list) ? array_values($list) : [];
    }

    private static function trackReasonBucket(string $bucket): void
    {
        $key = 'circulation:metrics:reason:buckets';
        $list = Cache::get($key, []);
        if (!is_array($list)) {
            $list = [];
        }
        if (!in_array($bucket, $list, true)) {
            $list[] = $bucket;
            $list = array_slice(array_values(array_unique($list)), -40);
            Cache::put($key, $list, now()->addDays(self::TTL_DAYS));
        }
    }

    private static function trackedReasonBuckets(): array
    {
        $list = Cache::get('circulation:metrics:reason:buckets', []);
        return is_array($list) ? array_values($list) : [];
    }

    private static function latencyKey(string $endpoint): string
    {
        return "circulation:metrics:endpoint:{$endpoint}:latency_samples";
    }
}

