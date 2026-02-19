<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BiblioRankingService
{
    public function __construct(private readonly SearchTuningService $tuning)
    {
    }

    public function rerankIds(
        array $ids,
        int $institutionId,
        ?int $userId = null,
        string $mode = 'institution',
        ?string $query = null,
        ?int $branchId = null,
        array $branchIds = []
    ): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($institutionId <= 0 || empty($ids)) {
            return $ids;
        }

        if (!Schema::hasTable('biblio_metrics')) {
            return $ids;
        }
        $settings = $this->tuning->forInstitution($institutionId);
        $signalConfig = (array) config('search.ranking.signals', []);

        $halfLife = (int) config('search.ranking.half_life_days', 30);
        if ($halfLife <= 0) $halfLife = 30;

        $baseScores = $this->loadInstitutionScores($ids, $institutionId, $halfLife, $signalConfig);
        $userScores = [];
        $branchScores = [];
        $availabilityScores = [];
        $exactBoosts = [];

        if ($mode === 'personal' && $userId && Schema::hasTable('biblio_user_metrics')) {
            $userScores = $this->loadUserScores($ids, $institutionId, $userId, $halfLife, $signalConfig);
        }
        $branchIds = array_values(array_unique(array_filter(array_map('intval', $branchIds), fn ($v) => $v > 0)));

        if ((($branchId && $branchId > 0) || !empty($branchIds)) && Schema::hasTable('items')) {
            $branchScores = $this->loadBranchScores($ids, $branchId, $branchIds, $signalConfig);
        }
        if (Schema::hasTable('items')) {
            $availabilityScores = $this->loadAvailabilityScores($ids, $branchId, $branchIds, $settings);
        }
        if ($query) {
            $exactBoosts = $this->loadExactMatchBoosts($ids, $institutionId, $query, $settings);
        }

        $originalPos = array_flip($ids);

        usort($ids, function ($a, $b) use ($baseScores, $userScores, $branchScores, $availabilityScores, $exactBoosts, $originalPos) {
            $scoreA = ($baseScores[$a] ?? 0) + ($userScores[$a] ?? 0) + ($branchScores[$a] ?? 0) + ($availabilityScores[$a] ?? 0) + ($exactBoosts[$a] ?? 0);
            $scoreB = ($baseScores[$b] ?? 0) + ($userScores[$b] ?? 0) + ($branchScores[$b] ?? 0) + ($availabilityScores[$b] ?? 0) + ($exactBoosts[$b] ?? 0);

            if ($scoreA === $scoreB) {
                return ($originalPos[$a] ?? 0) <=> ($originalPos[$b] ?? 0);
            }

            return $scoreB <=> $scoreA;
        });

        return $ids;
    }

    private function loadAvailabilityScores(array $ids, ?int $branchId = null, array $branchIds = [], array $settings = []): array
    {
        $weightAvailable = (float) ($settings['available_weight'] ?? config('search.ranking.availability.available_weight', 10));
        $weightBorrowed = (float) ($settings['borrowed_penalty'] ?? config('search.ranking.availability.borrowed_penalty', 3));
        $weightReserved = (float) ($settings['reserved_penalty'] ?? config('search.ranking.availability.reserved_penalty', 2));

        $rows = DB::table('items')
            ->select([
                'biblio_id',
                DB::raw("SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_count"),
                DB::raw("SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as borrowed_count"),
                DB::raw("SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved_count"),
            ])
            ->whereIn('biblio_id', $ids)
            ->when(!empty($branchIds), fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->when(empty($branchIds) && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('biblio_id')
            ->get();

        $scores = [];
        foreach ($rows as $row) {
            $available = (int) ($row->available_count ?? 0);
            $borrowed = (int) ($row->borrowed_count ?? 0);
            $reserved = (int) ($row->reserved_count ?? 0);
            $scores[(int) $row->biblio_id] = ($available * $weightAvailable)
                - ($borrowed * $weightBorrowed)
                - ($reserved * $weightReserved);
        }

        return $scores;
    }

    private function loadInstitutionScores(array $ids, int $institutionId, int $halfLifeDays, array $signalConfig = []): array
    {
        $borrowWeight = (float) ($signalConfig['institution_borrow_weight'] ?? 5);
        $clickWeight = (float) ($signalConfig['institution_click_weight'] ?? 1);
        $recentCfg = (array) ($signalConfig['recent_activity_boost'] ?? []);
        $recentEnabled = (bool) ($recentCfg['enabled'] ?? true);
        $recentWindowDays = max(1, (int) ($recentCfg['window_days'] ?? 14));
        $recentWeight = (float) ($recentCfg['weight'] ?? 2.5);

        $rows = DB::table('biblio_metrics')
            ->select(['biblio_id', 'click_count', 'borrow_count', 'last_clicked_at', 'last_borrowed_at'])
            ->where('institution_id', $institutionId)
            ->whereIn('biblio_id', $ids)
            ->get();

        $scores = [];
        foreach ($rows as $row) {
            $clickDecay = $this->decayMultiplier($row->last_clicked_at ?? null, $halfLifeDays);
            $borrowDecay = $this->decayMultiplier($row->last_borrowed_at ?? null, $halfLifeDays);
            $score = ((int) $row->borrow_count * $borrowWeight * $borrowDecay)
                + ((int) $row->click_count * $clickWeight * $clickDecay);

            if ($recentEnabled) {
                $score += $this->recentActivityBoost($row->last_clicked_at ?? null, $recentWindowDays, $recentWeight);
                $score += $this->recentActivityBoost($row->last_borrowed_at ?? null, $recentWindowDays, $recentWeight);
            }

            $scores[(int) $row->biblio_id] = $score;
        }

        return $scores;
    }

    private function loadUserScores(array $ids, int $institutionId, int $userId, int $halfLifeDays, array $signalConfig = []): array
    {
        $borrowWeight = (float) ($signalConfig['user_borrow_weight'] ?? 8);
        $clickWeight = (float) ($signalConfig['user_click_weight'] ?? 2);

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
            $scores[(int) $row->biblio_id] = ((int) $row->borrow_count * $borrowWeight * $borrowDecay)
                + ((int) $row->click_count * $clickWeight * $clickDecay);
        }

        return $scores;
    }

    private function loadBranchScores(array $ids, ?int $branchId = null, array $branchIds = [], array $signalConfig = []): array
    {
        $availableWeight = (float) ($signalConfig['branch_available_weight'] ?? 3);
        $totalWeight = (float) ($signalConfig['branch_total_weight'] ?? 1);

        $rows = DB::table('items')
            ->select([
                'biblio_id',
                DB::raw('COUNT(*) as total_count'),
                DB::raw("SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_count"),
            ])
            ->whereIn('biblio_id', $ids)
            ->when(!empty($branchIds), fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->when(empty($branchIds) && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('biblio_id')
            ->get();

        $scores = [];
        foreach ($rows as $row) {
            $total = (int) ($row->total_count ?? 0);
            $available = (int) ($row->available_count ?? 0);
            $scores[(int) $row->biblio_id] = ($available * $availableWeight) + ($total * $totalWeight);
        }
        return $scores;
    }

    private function loadExactMatchBoosts(array $ids, int $institutionId, string $query, array $settings = []): array
    {
        $boosts = [];
        $normalized = $this->normalizeQuery($query);
        if ($normalized === '') {
            return $boosts;
        }

        $shortMax = (int) ($settings['short_query_max_len'] ?? config('search.short_query_boost.max_len', 4));
        $shortMultiplier = (float) ($settings['short_query_multiplier'] ?? config('search.short_query_boost.multiplier', 1.6));
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
            $boosts[(int) $id] = ($boosts[(int) $id] ?? 0) + (int) round(((int) ($settings['title_exact_weight'] ?? 80)) * $boostFactor);
        }

        $authorIds = DB::table('authors')
            ->join('biblio_author', 'authors.id', '=', 'biblio_author.author_id')
            ->whereIn('biblio_author.biblio_id', $ids)
            ->whereRaw('LOWER(authors.name) = ?', [$normalized])
            ->pluck('biblio_author.biblio_id')
            ->all();
        foreach ($authorIds as $id) {
            $boosts[(int) $id] = ($boosts[(int) $id] ?? 0) + (int) round(((int) ($settings['author_exact_weight'] ?? 40)) * $boostFactor);
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
            $boosts[(int) $id] = ($boosts[(int) $id] ?? 0) + (int) round(((int) ($settings['subject_exact_weight'] ?? 25)) * $boostFactor);
        }

        $publisherIds = DB::table('biblio')
            ->where('institution_id', $institutionId)
            ->whereIn('id', $ids)
            ->whereRaw('LOWER(publisher) = ?', [$normalized])
            ->pluck('id')
            ->all();
        foreach ($publisherIds as $id) {
            $boosts[(int) $id] = ($boosts[(int) $id] ?? 0) + (int) round(((int) ($settings['publisher_exact_weight'] ?? 15)) * $boostFactor);
        }

        if ($this->isLikelyIsbn($normalized)) {
            $isbnIds = DB::table('biblio')
                ->where('institution_id', $institutionId)
                ->whereIn('id', $ids)
                ->whereRaw('REPLACE(REPLACE(REPLACE(LOWER(isbn), \"-\", \"\"), \" \", \"\"), \"_\", \"\") = ?', [$this->normalizeIsbn($normalized)])
                ->pluck('id')
                ->all();
            foreach ($isbnIds as $id) {
                $boosts[(int) $id] = ($boosts[(int) $id] ?? 0) + (int) ($settings['isbn_exact_weight'] ?? 100);
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

    private function recentActivityBoost($lastAt, int $windowDays, float $weight): float
    {
        if (!$lastAt || $windowDays <= 0 || $weight <= 0) {
            return 0.0;
        }

        $ts = is_string($lastAt) ? strtotime($lastAt) : (is_object($lastAt) && method_exists($lastAt, 'getTimestamp') ? $lastAt->getTimestamp() : null);
        if (!$ts) {
            return 0.0;
        }

        $ageDays = max(0, (time() - $ts) / 86400);
        if ($ageDays > $windowDays) {
            return 0.0;
        }

        return $weight * (1 - ($ageDays / $windowDays));
    }
}
