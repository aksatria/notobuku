<?php

namespace App\Console\Commands;

use App\Models\Biblio;
use App\Services\Search\BiblioSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CatalogScaleProofCommand extends Command
{
    protected $signature = 'notobuku:catalog-scale-proof
        {--institution= : Scope institution}
        {--samples=60 : Jumlah query sampel}
        {--per-page=12 : Per page search}
        {--seed-from=biblio,query : Sumber sampel}
        {--output= : Override output file}';

    protected $description = 'Generate bukti performa katalog (p50/p95/p99 + error rate) untuk audit reliability.';

    public function handle(BiblioSearchService $search): int
    {
        $institutionId = $this->option('institution');
        $institutionId = $institutionId !== null && $institutionId !== '' ? (int) $institutionId : (int) config('notobuku.opac.public_institution_id', 1);
        if ($institutionId <= 0) {
            $institutionId = 1;
        }

        $samples = max(10, (int) $this->option('samples'));
        $perPage = max(1, min(100, (int) $this->option('per-page')));
        $seedFrom = (string) $this->option('seed-from');
        $sources = array_values(array_filter(array_map('trim', explode(',', $seedFrom))));
        if (empty($sources)) {
            $sources = ['biblio', 'query'];
        }

        $queries = $this->buildSampleQueries($institutionId, $samples, $sources);
        if (empty($queries)) {
            $this->warn('Tidak ada sampel query untuk diuji.');
            return self::SUCCESS;
        }

        $latencies = [];
        $errors = 0;
        $rows = [];
        foreach ($queries as $q) {
            $start = microtime(true);
            $ok = true;
            $hits = 0;
            try {
                $result = $search->search([
                    'q' => $q,
                    'sort' => 'relevant',
                    'page' => 1,
                    'per_page' => $perPage,
                ], $institutionId);
                if (is_array($result)) {
                    $hits = (int) ($result['total'] ?? 0);
                } else {
                    $hits = (int) Biblio::query()
                        ->where('institution_id', $institutionId)
                        ->where('title', 'like', '%' . $q . '%')
                        ->count();
                }
            } catch (\Throwable $e) {
                $ok = false;
                $errors++;
            }
            $ms = (int) round((microtime(true) - $start) * 1000);
            $latencies[] = $ms;
            $rows[] = [
                'query' => $q,
                'latency_ms' => $ms,
                'hits' => $hits,
                'ok' => $ok ? 1 : 0,
            ];
        }

        sort($latencies);
        $count = count($latencies);
        $p50 = $this->percentile($latencies, 50);
        $p95 = $this->percentile($latencies, 95);
        $p99 = $this->percentile($latencies, 99);
        $max = $latencies[$count - 1] ?? 0;
        $avg = $count > 0 ? (int) round(array_sum($latencies) / $count) : 0;
        $errorRate = $count > 0 ? round(($errors / $count) * 100, 2) : 0.0;

        $report = [
            'generated_at' => now()->toDateTimeString(),
            'institution_id' => $institutionId,
            'samples' => $count,
            'metrics' => [
                'avg_ms' => $avg,
                'p50_ms' => $p50,
                'p95_ms' => $p95,
                'p99_ms' => $p99,
                'max_ms' => $max,
                'error_count' => $errors,
                'error_rate_pct' => $errorRate,
            ],
            'rows' => $rows,
        ];

        $output = trim((string) $this->option('output'));
        if ($output === '') {
            $output = 'reports/catalog-scale/catalog-scale-' . now()->format('Ymd-His') . '.json';
        }
        Storage::put($output, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Catalog scale proof tersimpan: {$output}");
        $this->info("samples={$count} avg={$avg}ms p50={$p50}ms p95={$p95}ms p99={$p99}ms max={$max}ms error_rate={$errorRate}%");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function buildSampleQueries(int $institutionId, int $samples, array $sources): array
    {
        $queries = [];

        if (in_array('query', $sources, true) && Schema::hasTable('search_queries')) {
            $queryRows = DB::table('search_queries')
                ->where('institution_id', $institutionId)
                ->orderByDesc('search_count')
                ->orderByDesc('last_searched_at')
                ->limit($samples)
                ->pluck('query')
                ->map(fn ($q) => trim((string) $q))
                ->filter(fn ($q) => $q !== '' && mb_strlen($q) >= 2)
                ->values()
                ->all();
            $queries = array_merge($queries, $queryRows);
        }

        if (in_array('biblio', $sources, true)) {
            $titleRows = Biblio::query()
                ->where('institution_id', $institutionId)
                ->orderByDesc('updated_at')
                ->limit($samples)
                ->pluck('title')
                ->map(function ($title) {
                    $t = trim((string) $title);
                    if ($t === '') {
                        return '';
                    }
                    $parts = preg_split('/\s+/', $t);
                    $parts = array_values(array_filter((array) $parts));
                    return implode(' ', array_slice($parts, 0, min(3, count($parts))));
                })
                ->filter(fn ($q) => $q !== '' && mb_strlen($q) >= 2)
                ->values()
                ->all();
            $queries = array_merge($queries, $titleRows);
        }

        $queries = array_values(array_unique($queries));
        if (count($queries) > $samples) {
            $queries = array_slice($queries, 0, $samples);
        }

        return $queries;
    }

    /**
     * @param array<int, int> $sorted
     */
    private function percentile(array $sorted, int $p): int
    {
        if (empty($sorted)) {
            return 0;
        }
        $idx = (int) ceil(($p / 100) * count($sorted)) - 1;
        $idx = max(0, min($idx, count($sorted) - 1));
        return (int) $sorted[$idx];
    }
}

