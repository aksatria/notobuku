<?php

namespace Tests\Unit;

use App\Services\Search\SearchQualityEvaluator;
use Tests\TestCase;

class SearchQualityEvaluatorTest extends TestCase
{
    public function test_reciprocal_rank_returns_inverse_rank_of_first_relevant_doc(): void
    {
        $svc = app(SearchQualityEvaluator::class);

        $rr = $svc->reciprocalRank(['90', '10', '50'], ['10' => 3, '50' => 1]);

        $this->assertSame(0.5, $rr);
    }

    public function test_ndcg_at_k_returns_one_for_perfect_ranking(): void
    {
        $svc = app(SearchQualityEvaluator::class);

        $ndcg = $svc->ndcgAtK(['10', '20', '30'], ['10' => 3, '20' => 2, '30' => 1], 3);

        $this->assertEqualsWithDelta(1.0, $ndcg, 0.000001);
    }

    public function test_evaluate_aggregates_mrr_and_ndcg(): void
    {
        $svc = app(SearchQualityEvaluator::class);
        $dataset = [
            [
                'query' => 'q1',
                'k' => 3,
                'relevance' => ['1' => 3, '2' => 2],
            ],
            [
                'query' => 'q2',
                'k' => 3,
                'relevance' => ['9' => 3],
            ],
        ];

        $searcher = function (string $query, int $k): array {
            if ($query === 'q1') {
                return ['1', '2', '3'];
            }
            return ['7', '8', '9'];
        };

        $report = $svc->evaluate($dataset, $searcher, 3);

        $this->assertSame(2, $report['queries_used']);
        $this->assertEqualsWithDelta(0.666667, (float) $report['mrr'], 0.000001);
        $this->assertEqualsWithDelta(0.75, (float) $report['ndcg_at_k'], 0.000001);
        $this->assertCount(2, $report['rows']);
    }
}
