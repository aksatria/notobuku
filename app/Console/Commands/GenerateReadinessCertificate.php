<?php

namespace App\Console\Commands;

use App\Support\InteropMetrics;
use App\Support\OpacMetrics;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class GenerateReadinessCertificate extends Command
{
    protected $signature = 'notobuku:readiness-certificate
        {--date= : Tanggal sertifikat (YYYY-MM-DD)}
        {--institution= : Scope institution_id}
        {--window-days=30 : Jendela evaluasi KPI}
        {--output= : Override output markdown}
        {--strict-ready : FAIL otomatis bila evidence trafik observasi belum mencukupi}';

    protected $description = 'Generate sertifikat readiness internal berbasis KPI dan evidensi command/log.';

    public function handle(): int
    {
        $date = trim((string) $this->option('date'));
        $checkDate = $date !== '' ? Carbon::parse($date)->toDateString() : now()->toDateString();
        $windowDays = max(1, (int) $this->option('window-days'));
        $strictReady = (bool) $this->option('strict-ready');
        $institutionId = $this->option('institution');
        $institutionId = $institutionId !== null && $institutionId !== '' ? (int) $institutionId : (int) config('notobuku.opac.public_institution_id', 1);
        if ($institutionId <= 0) {
            $institutionId = 1;
        }

        $opac = OpacMetrics::snapshot();
        $interop = InteropMetrics::snapshot();
        $opacSlo = (array) data_get($opac, 'slo', []);
        $interopHealth = (array) data_get($interop, 'health', []);

        $opacP95 = (int) data_get($opac, 'latency.p95_ms', 0);
        $opacErrRate = (float) data_get($opac, 'error_rate_pct', 0);
        $opacSloState = (string) ($opacSlo['state'] ?? 'ok');
        $opacLatencyBudget = (int) config('notobuku.opac.slo.latency_budget_ms', 800);
        $opacErrBudget = 0.5;

        $interopLabel = (string) ($interopHealth['label'] ?? 'N/A');
        $interopP95 = (int) ($interopHealth['p95_ms'] ?? 0);
        $interopWarnP95 = (int) config('notobuku.interop.health_thresholds.warning.p95_ms', 1000);

        $zeroOpen = 0;
        if (Schema::hasTable('search_queries') && Schema::hasColumn('search_queries', 'zero_result_status')) {
            $zeroOpen = (int) DB::table('search_queries')
                ->where('institution_id', $institutionId)
                ->where('zero_result_status', 'open')
                ->count();
        }

        $qualityGateEnabled = (bool) config('notobuku.catalog.quality_gate.enabled', true);
        $opsEmail = trim((string) config('notobuku.catalog.ops_email_to', ''));
        $zeroGovEnabled = (bool) config('notobuku.catalog.zero_result_governance.enabled', true);

        $uat = $this->latestUat($institutionId);
        $hasRecentUatPass = $this->hasRecentUatPass($institutionId, 7);

        $scaleProof = $this->latestScaleProof();
        $scaleP95 = (int) data_get($scaleProof, 'data.metrics.p95_ms', 0);
        $scaleErr = (float) data_get($scaleProof, 'data.metrics.error_rate_pct', 0);
        $scaleSamples = (int) data_get($scaleProof, 'data.samples', 0);
        $observedOpacSearches = $this->countOpacSearches($institutionId, $windowDays);
        $observedInteropPoints = $this->countInteropPoints($windowDays);
        $minOpacSearches = max(1, (int) config('notobuku.readiness.minimum_traffic.opac_searches', 200));
        $minInteropPoints = max(1, (int) config('notobuku.readiness.minimum_traffic.interop_points', 240));
        $minScaleSamples = max(1, (int) config('notobuku.readiness.minimum_traffic.scale_samples', 60));

        $insufficientReasons = [];
        if ($observedOpacSearches < $minOpacSearches) {
            $insufficientReasons[] = "OPAC searches {$observedOpacSearches}/{$minOpacSearches}";
        }
        if ($observedInteropPoints < $minInteropPoints) {
            $insufficientReasons[] = "Interop points {$observedInteropPoints}/{$minInteropPoints}";
        }
        if ($scaleSamples < $minScaleSamples) {
            $insufficientReasons[] = "Scale samples {$scaleSamples}/{$minScaleSamples}";
        }
        $insufficientTraffic = !empty($insufficientReasons);
        $trafficStatus = $insufficientTraffic
            ? ($strictReady ? 'FAIL' : 'WARN')
            : 'PASS';
        $trafficValue = $insufficientTraffic
            ? implode('; ', $insufficientReasons)
            : 'cukup';

        $checks = [
            [
                'name' => 'Catalog Quality Gate aktif',
                'status' => $qualityGateEnabled ? 'PASS' : 'FAIL',
                'value' => $qualityGateEnabled ? 'enabled' : 'disabled',
                'target' => 'enabled',
                'evidence' => 'config:notobuku.catalog.quality_gate.enabled',
            ],
            [
                'name' => 'Zero-result queue open',
                'status' => $zeroOpen === 0 ? 'PASS' : 'FAIL',
                'value' => (string) $zeroOpen,
                'target' => '0',
                'evidence' => "DB:search_queries zero_result_status=open institution={$institutionId}",
            ],
            [
                'name' => 'Zero-result governance scheduler',
                'status' => $zeroGovEnabled ? 'PASS' : 'FAIL',
                'value' => $zeroGovEnabled ? 'enabled' : 'disabled',
                'target' => 'enabled',
                'evidence' => 'config:notobuku.catalog.zero_result_governance.enabled',
            ],
            [
                'name' => 'OPAC SLO state',
                'status' => in_array($opacSloState, ['ok', 'healthy'], true) ? 'PASS' : 'FAIL',
                'value' => $opacSloState,
                'target' => 'ok',
                'evidence' => 'snapshot:OpacMetrics::snapshot().slo.state',
            ],
            [
                'name' => 'OPAC p95 latency',
                'status' => $opacP95 > 0 && $opacP95 <= $opacLatencyBudget ? 'PASS' : ($opacP95 === 0 ? 'WARN' : 'FAIL'),
                'value' => $opacP95 . ' ms',
                'target' => '<= ' . $opacLatencyBudget . ' ms',
                'evidence' => 'snapshot:OpacMetrics::snapshot().latency.p95_ms',
            ],
            [
                'name' => 'OPAC error rate',
                'status' => $opacErrRate <= $opacErrBudget ? 'PASS' : 'FAIL',
                'value' => number_format($opacErrRate, 2) . '%',
                'target' => '<= ' . number_format($opacErrBudget, 2) . '%',
                'evidence' => 'snapshot:OpacMetrics::snapshot().error_rate_pct',
            ],
            [
                'name' => 'Interop health',
                'status' => strtolower($interopLabel) === 'sehat' ? 'PASS' : 'FAIL',
                'value' => $interopLabel,
                'target' => 'Sehat',
                'evidence' => 'snapshot:InteropMetrics::snapshot().health.label',
            ],
            [
                'name' => 'Interop p95',
                'status' => $interopP95 > 0 && $interopP95 <= $interopWarnP95 ? 'PASS' : ($interopP95 === 0 ? 'WARN' : 'FAIL'),
                'value' => $interopP95 . ' ms',
                'target' => '<= ' . $interopWarnP95 . ' ms',
                'evidence' => 'snapshot:InteropMetrics::snapshot().health.p95_ms',
            ],
            [
                'name' => 'UAT sign-off 7 hari',
                'status' => $hasRecentUatPass ? 'PASS' : 'FAIL',
                'value' => $uat['summary'],
                'target' => 'Ada pass <= 7 hari',
                'evidence' => 'DB:uat_signoffs',
            ],
            [
                'name' => 'Catalog scale proof p95',
                'status' => $scaleSamples > 0 && $scaleP95 <= $opacLatencyBudget ? 'PASS' : ($scaleSamples > 0 ? 'FAIL' : 'WARN'),
                'value' => $scaleSamples > 0 ? ($scaleP95 . ' ms') : 'n/a',
                'target' => '<= ' . $opacLatencyBudget . ' ms',
                'evidence' => $scaleProof['path'] !== '' ? ('file:' . $scaleProof['path']) : 'file:n/a',
            ],
            [
                'name' => 'Catalog scale proof error rate',
                'status' => $scaleSamples > 0 && $scaleErr <= $opacErrBudget ? 'PASS' : ($scaleSamples > 0 ? 'FAIL' : 'WARN'),
                'value' => $scaleSamples > 0 ? (number_format($scaleErr, 2) . '%') : 'n/a',
                'target' => '<= ' . number_format($opacErrBudget, 2) . '%',
                'evidence' => $scaleProof['path'] !== '' ? ('file:' . $scaleProof['path']) : 'file:n/a',
            ],
            [
                'name' => 'Ops email terpasang',
                'status' => $opsEmail !== '' ? 'PASS' : 'WARN',
                'value' => $opsEmail !== '' ? $opsEmail : '(kosong)',
                'target' => 'terisi',
                'evidence' => 'config:notobuku.catalog.ops_email_to',
            ],
            [
                'name' => 'Minimum traffic evidence',
                'status' => $trafficStatus,
                'value' => $trafficValue,
                'target' => "OPAC>={$minOpacSearches}, Interop>={$minInteropPoints}, Samples>={$minScaleSamples}",
                'evidence' => "window={$windowDays}d strict=" . ($strictReady ? '1' : '0'),
            ],
        ];

        $final = $this->overallStatus($checks);
        $score = $this->readinessScore($checks);
        if ($strictReady && $insufficientTraffic) {
            $final = 'NOT_READY';
        }

        $output = trim((string) $this->option('output'));
        if ($output === '') {
            $output = 'reports/readiness/readiness-' . str_replace('-', '', $checkDate) . '.md';
        }

        $jsonOutput = preg_replace('/\.md$/', '.json', $output) ?: ($output . '.json');
        $markdown = $this->buildMarkdown($checkDate, $institutionId, $windowDays, $checks, $final, $score, $uat, $scaleProof);
        Storage::put($output, $markdown);
        Storage::put($jsonOutput, json_encode([
            'generated_at' => now()->toDateTimeString(),
            'check_date' => $checkDate,
            'institution_id' => $institutionId,
            'window_days' => $windowDays,
            'strict_ready' => $strictReady,
            'final_status' => $final,
            'readiness_score' => $score,
            'insufficient_traffic' => $insufficientTraffic,
            'insufficient_reasons' => $insufficientReasons,
            'checks' => $checks,
            'uat' => $uat,
            'scale_proof' => $scaleProof,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Readiness certificate generated: {$output}");
        $this->info("JSON evidence: {$jsonOutput}");
        $this->info("Final: {$final} | score={$score}/100");
        if ($strictReady && $insufficientTraffic) {
            $this->error('Strict mode: evidence trafik belum cukup -> FAIL.');
            return self::FAILURE;
        }
        return self::SUCCESS;
    }

    /**
     * @return array{summary:string,last:array<string,mixed>}
     */
    private function latestUat(int $institutionId): array
    {
        if (!Schema::hasTable('uat_signoffs')) {
            return [
                'summary' => 'Tabel uat_signoffs tidak tersedia',
                'last' => [],
            ];
        }

        $row = DB::table('uat_signoffs')
            ->where(function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId)
                    ->orWhereNull('institution_id');
            })
            ->orderByDesc('check_date')
            ->orderByDesc('signed_at')
            ->first(['check_date', 'status', 'operator_name', 'signed_at', 'notes']);

        if (!$row) {
            return [
                'summary' => 'Belum ada sign-off',
                'last' => [],
            ];
        }

        return [
            'summary' => sprintf(
                '%s | %s | %s',
                (string) ($row->check_date ?? '-'),
                strtoupper((string) ($row->status ?? 'pending')),
                (string) ($row->operator_name ?? '-')
            ),
            'last' => (array) $row,
        ];
    }

    private function hasRecentUatPass(int $institutionId, int $days): bool
    {
        if (!Schema::hasTable('uat_signoffs')) {
            return false;
        }
        $since = now()->subDays(max(1, $days))->toDateString();
        return DB::table('uat_signoffs')
            ->where(function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId)
                    ->orWhereNull('institution_id');
            })
            ->where('status', 'pass')
            ->whereDate('check_date', '>=', $since)
            ->exists();
    }

    /**
     * @return array{path:string,data:array<string,mixed>}
     */
    private function latestScaleProof(): array
    {
        $disk = Storage::disk();
        $dir = 'reports/catalog-scale';
        if (!$disk->exists($dir)) {
            return ['path' => '', 'data' => []];
        }

        $files = collect($disk->files($dir))
            ->filter(fn ($f) => str_ends_with((string) $f, '.json'))
            ->sort()
            ->values();
        if ($files->isEmpty()) {
            return ['path' => '', 'data' => []];
        }
        $latest = (string) $files->last();
        $raw = (string) $disk->get($latest);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = [];
        }

        return ['path' => $latest, 'data' => $data];
    }

    private function countOpacSearches(int $institutionId, int $windowDays): int
    {
        if (!Schema::hasTable('search_queries')) {
            return 0;
        }
        $since = now()->subDays(max(1, $windowDays));
        return (int) DB::table('search_queries')
            ->where('institution_id', $institutionId)
            ->where('last_searched_at', '>=', $since)
            ->sum('search_count');
    }

    private function countInteropPoints(int $windowDays): int
    {
        if (!Schema::hasTable('interop_metric_points')) {
            return 0;
        }
        $since = now()->subDays(max(1, $windowDays));
        return (int) DB::table('interop_metric_points')
            ->where('minute_at', '>=', $since)
            ->count();
    }

    /**
     * @param array<int, array<string, string>> $checks
     */
    private function overallStatus(array $checks): string
    {
        $hasFail = collect($checks)->contains(fn ($c) => ($c['status'] ?? '') === 'FAIL');
        if ($hasFail) {
            return 'NOT_READY';
        }

        $hasWarn = collect($checks)->contains(fn ($c) => ($c['status'] ?? '') === 'WARN');
        if ($hasWarn) {
            return 'READY_WITH_NOTES';
        }

        return 'READY';
    }

    /**
     * @param array<int, array<string, string>> $checks
     */
    private function readinessScore(array $checks): int
    {
        if (empty($checks)) {
            return 0;
        }

        $sum = 0;
        foreach ($checks as $check) {
            $status = (string) ($check['status'] ?? 'WARN');
            $sum += match ($status) {
                'PASS' => 100,
                'WARN' => 70,
                default => 0,
            };
        }

        return (int) round($sum / count($checks));
    }

    /**
     * @param array<int, array<string, string>> $checks
     * @param array<string, mixed> $uat
     * @param array<string, mixed> $scaleProof
     */
    private function buildMarkdown(
        string $checkDate,
        int $institutionId,
        int $windowDays,
        array $checks,
        string $final,
        int $score,
        array $uat,
        array $scaleProof
    ): string {
        $lines = [];
        $lines[] = '# Sertifikat Readiness Internal - NOTOBUKU';
        $lines[] = '';
        $lines[] = '- Tanggal evaluasi: ' . $checkDate;
        $lines[] = '- Dibuat: ' . now()->toDateTimeString();
        $lines[] = '- Institution ID: ' . $institutionId;
        $lines[] = '- Window KPI: ' . $windowDays . ' hari';
        $lines[] = '- Status akhir: **' . $final . '**';
        $lines[] = '- Readiness score: **' . $score . '/100**';
        $lines[] = '';
        $lines[] = '## Tabel KPI';
        $lines[] = '| KPI | Status | Nilai | Target | Evidensi |';
        $lines[] = '|---|---|---|---|---|';
        foreach ($checks as $check) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                (string) ($check['name'] ?? '-'),
                (string) ($check['status'] ?? '-'),
                str_replace('|', '/', (string) ($check['value'] ?? '-')),
                str_replace('|', '/', (string) ($check['target'] ?? '-')),
                str_replace('|', '/', (string) ($check['evidence'] ?? '-'))
            );
        }
        $lines[] = '';
        $lines[] = '## Evidensi Utama';
        $lines[] = '- UAT terbaru: ' . (string) ($uat['summary'] ?? 'n/a');
        $scalePath = (string) ($scaleProof['path'] ?? '');
        $lines[] = '- Catalog scale proof: ' . ($scalePath !== '' ? $scalePath : 'n/a');
        $lines[] = '- Command referensi:';
        $lines[] = '  - `php artisan notobuku:search-zero-triage --limit=500 --min-search-count=2 --age-hours=24 --force-close-open=1`';
        $lines[] = '  - `php artisan notobuku:catalog-scale-proof --samples=60`';
        $lines[] = '  - `php artisan notobuku:opac-slo-alert`';
        $lines[] = '  - `php artisan notobuku:interop-reconcile`';
        $lines[] = '  - `php artisan notobuku:uat-generate` + `php artisan notobuku:uat-signoff --status=pass --operator="Nama"`';
        $lines[] = '';
        $lines[] = '## Catatan';
        $lines[] = '- Sertifikat ini bersifat internal untuk audit kesiapan operasional.';
        $lines[] = '- Jika ada status `FAIL`, remediation harus ditutup sebelum go-live.';
        $lines[] = '- Jika ada status `WARN`, lanjutkan observasi hingga stabil sesuai target.';
        $lines[] = '';

        return implode("\n", $lines) . "\n";
    }
}
