<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BiblioRankingService
{
    public function rerankIds(array $ids, int $institutionId, ?int $userId = null, string $mode = 'institution', ?string $query = null, ?int $branchId = null): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($institutionId <= 0 || empty($ids)) {
            return $ids;
        }

        if (!Schema::hasTable('biblio_metrics')) {
            return $ids;
        }

        $halfLife = (int) config('search.ranking.half_life_days', 30);
        if ($halfLife <= 0) $halfLife = 30;

        $baseScores = $this->loadInstitutionScores($ids, $institutionId, $halfLife);
        $userScores = [];
        $branchScores = [];
        $exactBoosts = [];

        if ($mode === 'personal' && $userId && Schema::hasTable('biblio_user_metrics')) {
            $userScores = $this->loadUserScores($ids, $institutionId, $userId, $halfLife);
        }
        if ($branchId && Schema::hasTable('items')) {
            $branchScores = $this->loadBranchScores($ids, $branchId);
        }
        if ($query) {
            $exactBoosts = $this->loadExactMatchBoosts($ids, $institutionId, $query);
        }

        $originalPos = array_flip($ids);

        usort($ids, function ($a, $b) use ($baseScores, $userScores, $branchScores, $exactBoosts, $originalPos) {
            $scoreA = ($baseScores[$a] ?? 0) + ($userScores[$a] ?? 0) + ($branchScores[$a] ?? 0) + ($exactBoosts[$a] ?? 0);
            $scoreB = ($baseScores[$b] ?? 0) + ($userScores[$b] ?? 0) + ($branchScores[$b] ?? 0) + ($exactBoosts[$b] ?? 0);

            if ($scoreA === $scoreB) {
                return ($originalPos[$a] ?? 0) <=> ($originalPos[$b] ?? 0);
            }

            return $scoreB <=> $scoreA;
        });

        return $ids;
    }

    private function loadInstitutionScores(array $ids, int $institutionId, int $halfLifeDays): array
    {
        $rows = DB::table('biblio_metrics')
            ->select(['biblio_id', 'click_count', 'borrow_count', 'last_clicked_at', 'last_borrowed_at'])
            ->where('institution_id', $institutionId)
            ->whereIn('biblio_id', $ids)
            ->get();

        $scores = [];
        foreach ($rows as $row) {
            $clickDecay = $this->decayMultiplier($row->last_clicked_at ?? null, $halfLifeDays);
            $borrowDecay = $this->decayMultiplier($row->last_borrowed_at ?? null, $halfLifeDays);
            $scores[(int) $row->biblio_id] = ((int) $row->borrow_count * 5 * $borrowDecay) + ((int) $row->click_count * 1 * $clickDecay);
        }

        return $scores;
    }

    private function loadUserScores(array $ids, int $institutionId, int $userId, int $halfLifeDays): array
    {
        $rows = DB::table('biblio_user_metrics')
            ->select(['biblio_id', 'click_count', 'borrow_count', 'last_clicked_at', 'last_borrowed_at'])
            ->where('institution_id', $institutionId)
            ->where('user_id', $userId)
            ->whereIn('biblio_id', $ids)
            ->get();

        $scores = [];
        foreach ($rows as $row) {
            $clickDecay = $this->decayMultiplier($row->last_clicked_at ?? null, $halfLifeDays);
            $borrowDecay = $this->decayMultiplier($row->last_borrowed_at ?? null, $halfLifeDays);
            $scores[(int) $row->biblio_id] = ((int) $row->borrow_count * 8 * $borrowDecay) + ((int) $row->click_count * 2 * $clickDecay);
        }

        return $scores;
    }

    private function loadBranchScores(array $ids, int $branchId): array
    {
        $rows = DB::table('items')
            ->select([
                'biblio_id',
                DB::raw('COUNT(*) as total_count'),
                DB::raw("SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_count"),
            ])
            ->whereIn('biblio_id', $ids)
            ->where('branch_id', $branchId)
            ->groupBy('biblio_id')
            ->get();

        $scores = [];
        foreach ($rows as $row) {
            $total = (int) ($row->total_count ?? 0);
            $available = (int) ($row->available_count ?? 0);
            $scores[(int) $row->biblio_id] = ($available * 3) + ($total * 1);
        }
        return $scores;
    }

    private function loadExactMatchBoosts(array $ids, int $institutionId, string $query): array
    {
        $boosts = [];
        $normalized = $this->normalizeQuery($query);
        if ($normalized === '') {
            return $boosts;
        }

        $shortMax = (int) config('search.short_query_boost.max_len', 4);
        $shortMultiplier = (float) config('search.short_query_boost.multiplier', 1.6);
        $compact = str_replace(' ', '', $normalized);
        $isShort = $shortMax > 0 && $compact !== '' && mb_strlen($compact) <= $shortMax;
        $boostFactor = $isShort ? $shortMultiplier : 1.0;

        $titleIds = DB::table('biblio')
            ->where('institution_id', $institutionId)
            ->whereIn('id', $ids)
            ->where(function ($q) use ($normalized) {
                $q->whereRaw('LOWER(title) = ?', [$normalized])
                  ->orWhereRaw('LOWER(normalized_title) = ?', [$normalized]);
            })
            ->pluck('id')
            ->all();
        foreach ($titleIds as $id) {
            $boosts[(int) $id] = ($boosts[(int) $id] ?? 0) + (int) round(80 * $boostFactor);
        }

        $authorIds = DB::table('authors')
            ->join('biblio_author', 'authors.id', '=', 'biblio_author.author_id')
            ->whereIn('biblio_author.biblio_id', $ids)
            ->whereRaw('LOWER(authors.name) = ?', [$normalized])
            ->pluck('biblio_author.biblio_id')
            ->all();
        foreach ($authorIds as $id) {
            $boosts[(int) $id] = ($boosts[(int) $id] ?? 0) + (int) round(40 * $boostFactor);
        }

        $subjectIds = DB::table('subjects')
            ->join('biblio_subject', 'subjects.id', '=', 'biblio_subject.subject_id')
            ->whereIn('biblio_subject.biblio_id', $ids)
            ->where(function ($q) use ($normalized) {
                $q->whereRaw('LOWER(subjects.term) = ?', [$normalized])
                  ->orWhereRaw('LOWER(subjects.name) = ?', [$normalized]);
            })
            ->pluck('biblio_subject.biblio_id')
            ->all();
        foreach ($subjectIds as $id) {
            $boosts[(int) $id] = ($boosts[(int) $id] ?? 0) + (int) round(25 * $boostFactor);
        }

        $publisherIds = DB::table('biblio')
            ->where('institution_id', $institutionId)
            ->whereIn('id', $ids)
            ->whereRaw('LOWER(publisher) = ?', [$normalized])
            ->pluck('id')
            ->all();
        foreach ($publisherIds as $id) {
            $boosts[(int) $id] = ($boosts[(int) $id] ?? 0) + (int) round(15 * $boostFactor);
        }

        if ($this->isLikelyIsbn($normalized)) {
            $isbnIds = DB::table('biblio')
                ->where('institution_id', $institutionId)
                ->whereIn('id', $ids)
                ->whereRaw('REPLACE(REPLACE(REPLACE(LOWER(isbn), \"-\", \"\"), \" \", \"\"), \"_\", \"\") = ?', [$this->normalizeIsbn($normalized)])
                ->pluck('id')
                ->all();
            foreach ($isbnIds as $id) {
                $boosts[(int) $id] = ($boosts[(int) $id] ?? 0) + 100;
            }
        }

        return $boosts;
    }

    private function normalizeQuery(string $value): string
    {
        $v = mb_strtolower(trim($value));
        $v = preg_replace('/[^a-z0-9\s]/i', ' ', $v);
        $v = preg_replace('/\s+/', ' ', $v);
        return trim($v);
    }

    private function isLikelyIsbn(string $value): bool
    {
        $v = preg_replace('/[^0-9xX]/', '', $value);
        return in_array(strlen($v), [10, 13], true);
    }

    private function normalizeIsbn(string $value): string
    {
        return preg_replace('/[^0-9xX]/', '', $value);
    }

    private function decayMultiplier($lastAt, int $halfLifeDays): float
    {
        if (!$lastAt) {
            return 1.0;
        }

        $ts = is_string($lastAt) ? strtotime($lastAt) : (is_object($lastAt) && method_exists($lastAt, 'getTimestamp') ? $lastAt->getTimestamp() : null);
        if (!$ts) {
            return 1.0;
        }

        $ageDays = max(0, (time() - $ts) / 86400);
        if ($halfLifeDays <= 0) {
            return 1.0;
        }

        return pow(0.5, $ageDays / $halfLifeDays);
    }
}
