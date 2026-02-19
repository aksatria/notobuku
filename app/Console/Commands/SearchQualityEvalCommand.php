<?php

namespace App\Console\Commands;

use App\Models\Biblio;
use App\Services\Search\BiblioSearchService;
use App\Services\Search\SearchQualityEvaluator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SearchQualityEvalCommand extends Command
{
    protected $signature = 'notobuku:search-quality-eval
        {--dataset=resources/search/query_eval_dataset.json : File JSON dataset evaluasi}
        {--institution= : Scope institution}
        {--k=10 : Nilai default top-k}
        {--strict : Exit code non-zero jika skor di bawah threshold}
        {--output= : Simpan hasil JSON ke storage path}';

    protected $description = 'Evaluasi kualitas query katalog menggunakan MRR dan NDCG@K dari dataset tetap.';

    public function handle(BiblioSearchService $search, SearchQualityEvaluator $evaluator): int
    {
        $datasetPath = trim((string) $this->option('dataset'));
        if ($datasetPath === '') {
            $datasetPath = 'resources/search/query_eval_dataset.json';
        }

        if (!is_file(base_path($datasetPath))) {
            $this->error("Dataset tidak ditemukan: {$datasetPath}");
            return self::FAILURE;
        }

        $json = file_get_contents(base_path($datasetPath));
        $decoded = json_decode((string) $json, true);
        if (!is_array($decoded)) {
            $this->error('Format dataset tidak valid (harus array JSON).');
            return self::FAILURE;
        }

        $institutionId = (int) $this->option('institution');
        if ($institutionId <= 0) {
            $institutionId = (int) config('notobuku.opac.public_institution_id', 1);
        }
        if ($institutionId <= 0) {
            $institutionId = 1;
        }

        $defaultK = max(1, (int) $this->option('k'));
        $start = microtime(true);

        $report = $evaluator->evaluate($decoded, function (string $query, int $k) use ($search, $institutionId) {
            $result = $search->search([
                'q' => $query,
                'sort' => 'relevant',
                'page' => 1,
                'per_page' => $k,
            ], $institutionId);

            if (is_array($result) && !empty($result['ids']) && is_array($result['ids'])) {
                return array_map('strval', array_slice($result['ids'], 0, $k));
            }

            // Fallback deterministic saat search engine tidak aktif.
            return Biblio::query()
                ->where('institution_id', $institutionId)
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', '%' . $query . '%')
                        ->orWhere('subtitle', 'like', '%' . $query . '%');
                })
                ->orderBy('id')
                ->limit($k)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();
        }, $defaultK);

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $thresholdMrr = (float) config('search.quality_eval.thresholds.mrr', 0.5);
        $thresholdNdcg = (float) config('search.quality_eval.thresholds.ndcg_at_k', 0.6);
        $minQueriesUsed = max(1, (int) config('search.quality_eval.minimums.queries_used', 3));
        $minPerQueryMrr = max(0.0, (float) config('search.quality_eval.minimums.per_query_mrr', 0.15));
        $minPerQueryNdcg = max(0.0, (float) config('search.quality_eval.minimums.per_query_ndcg_at_k', 0.25));

        $rowViolations = [];
        foreach ((array) ($report['rows'] ?? []) as $row) {
            $query = (string) ($row['query'] ?? '');
            $rr = (float) ($row['reciprocal_rank'] ?? 0.0);
            $ndcg = (float) ($row['ndcg_at_k'] ?? 0.0);
            if ($rr < $minPerQueryMrr || $ndcg < $minPerQueryNdcg) {
                $rowViolations[] = [
                    'query' => $query,
                    'reciprocal_rank' => $rr,
                    'ndcg_at_k' => $ndcg,
                ];
            }
        }

        $pass = ((float) ($report['mrr'] ?? 0.0) >= $thresholdMrr)
            && ((float) ($report['ndcg_at_k'] ?? 0.0) >= $thresholdNdcg)
            && ((int) ($report['queries_used'] ?? 0) >= $minQueriesUsed)
            && empty($rowViolations);

        $full = [
            'generated_at' => now()->toDateTimeString(),
            'institution_id' => $institutionId,
            'dataset' => $datasetPath,
            'default_k' => $defaultK,
            'duration_ms' => $durationMs,
            'thresholds' => [
                'mrr' => $thresholdMrr,
                'ndcg_at_k' => $thresholdNdcg,
            ],
            'minimums' => [
                'queries_used' => $minQueriesUsed,
                'per_query_mrr' => $minPerQueryMrr,
                'per_query_ndcg_at_k' => $minPerQueryNdcg,
            ],
            'pass' => $pass,
            'metrics' => $report,
            'violations' => [
                'query_count_below_minimum' => ((int) ($report['queries_used'] ?? 0) < $minQueriesUsed),
                'per_query' => $rowViolations,
            ],
        ];

        $output = trim((string) $this->option('output'));
        if ($output === '') {
            $output = 'reports/search-quality/search-quality-' . now()->format('Ymd-His') . '.json';
        }
        Storage::put($output, json_encode($full, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Search quality report: {$output}");
        $this->line('queries_used=' . (int) ($report['queries_used'] ?? 0)
            . ' mrr=' . (float) ($report['mrr'] ?? 0.0)
            . ' ndcg@k=' . (float) ($report['ndcg_at_k'] ?? 0.0)
            . ' pass=' . ($pass ? 'yes' : 'no')
            . ' duration=' . $durationMs . 'ms');

        if (!$pass) {
            if ((int) ($report['queries_used'] ?? 0) < $minQueriesUsed) {
                $this->warn("Minimum queries_used belum terpenuhi. expected>={$minQueriesUsed}, got=" . (int) ($report['queries_used'] ?? 0));
            }
            if (!empty($rowViolations)) {
                $this->warn('Ada query di bawah floor kualitas per-query.');
            }
        }

        if ((bool) $this->option('strict') && !$pass) {
            $this->error('Search quality gate failed (strict mode).');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
