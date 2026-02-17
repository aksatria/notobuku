<?php

namespace App\Services\Search;

class SearchQualityEvaluator
{
    /**
     * @param array<int, array<string, mixed>> $dataset
     * @param callable(string, int): array<int, int|string> $searcher
     * @return array<string, mixed>
     */
    public function evaluate(array $dataset, callable $searcher, int $defaultK = 10): array
    {
        $rows = [];
        $mrrSum = 0.0;
        $ndcgSum = 0.0;
        $used = 0;

        foreach ($dataset as $row) {
            $query = trim((string) ($row['query'] ?? ''));
            $relevanceRaw = (array) ($row['relevance'] ?? []);
            $k = max(1, (int) ($row['k'] ?? $defaultK));

            if ($query === '' || empty($relevanceRaw)) {
                continue;
            }

            $relevance = $this->normalizeRelevanceMap($relevanceRaw);
            if (empty($relevance)) {
                continue;
            }

            $results = array_values(array_map('strval', (array) $searcher($query, $k)));
            $rr = $this->reciprocalRank($results, $relevance);
            $ndcg = $this->ndcgAtK($results, $relevance, $k);

            $mrrSum += $rr;
            $ndcgSum += $ndcg;
            $used++;

            $rows[] = [
                'query' => $query,
                'k' => $k,
                'reciprocal_rank' => round($rr, 6),
                'ndcg_at_k' => round($ndcg, 6),
                'expected_docs' => count($relevance),
                'returned_docs' => count($results),
            ];
        }

        return [
            'queries_total' => count($dataset),
            'queries_used' => $used,
            'mrr' => $used > 0 ? round($mrrSum / $used, 6) : 0.0,
            'ndcg_at_k' => $used > 0 ? round($ndcgSum / $used, 6) : 0.0,
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, int> $relevance
     * @param array<int, string> $results
     */
    public function reciprocalRank(array $results, array $relevance): float
    {
        foreach ($results as $idx => $docId) {
            if (($relevance[$docId] ?? 0) > 0) {
                return 1.0 / ($idx + 1);
            }
        }

        return 0.0;
    }

    /**
     * @param array<int, string> $results
     * @param array<string, int> $relevance
     */
    public function ndcgAtK(array $results, array $relevance, int $k = 10): float
    {
        $k = max(1, $k);
        $actual = $this->dcg(array_slice($results, 0, $k), $relevance);

        $idealGrades = array_values($relevance);
        rsort($idealGrades, SORT_NUMERIC);
        $ideal = $this->dcgFromGrades(array_slice($idealGrades, 0, $k));

        if ($ideal <= 0.0) {
            return 0.0;
        }

        return $actual / $ideal;
    }

    /**
     * @param array<string, int> $raw
     * @return array<string, int>
     */
    private function normalizeRelevanceMap(array $raw): array
    {
        $map = [];
        foreach ($raw as $docId => $grade) {
            $id = trim((string) $docId);
            $g = max(0, (int) $grade);
            if ($id === '' || $g <= 0) {
                continue;
            }
            $map[$id] = $g;
        }

        return $map;
    }

    /**
     * @param array<int, string> $docs
     * @param array<string, int> $relevance
     */
    private function dcg(array $docs, array $relevance): float
    {
        $dcg = 0.0;
        foreach ($docs as $idx => $docId) {
            $grade = (int) ($relevance[$docId] ?? 0);
            if ($grade <= 0) {
                continue;
            }
            $dcg += $this->gainAt($grade, $idx + 1);
        }
        return $dcg;
    }

    /**
     * @param array<int, int> $grades
     */
    private function dcgFromGrades(array $grades): float
    {
        $dcg = 0.0;
        foreach ($grades as $idx => $grade) {
            $g = max(0, (int) $grade);
            if ($g <= 0) {
                continue;
            }
            $dcg += $this->gainAt($g, $idx + 1);
        }
        return $dcg;
    }

    private function gainAt(int $grade, int $rank): float
    {
        return ((2 ** $grade) - 1) / log($rank + 1, 2);
    }
}
