<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SearchStopWordService
{
    public function listForInstitution(int $institutionId, ?int $branchId = null): array
    {
        $base = array_values(array_unique(array_filter(array_map(
            fn ($w) => mb_strtolower(trim((string) $w)),
            (array) config('search.stop_words', [])
        ))));

        if ($institutionId <= 0 || !Schema::hasTable('search_stop_words')) {
            return $base;
        }

        $cacheKey = 'search:stop_words:' . $institutionId . ':' . (int) ($branchId ?? 0);
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($institutionId, $branchId, $base) {
            $rows = DB::table('search_stop_words')
                ->where('institution_id', $institutionId)
                ->when($branchId, fn ($q) => $q->where(function ($qq) use ($branchId) {
                    $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
                }), fn ($q) => $q->whereNull('branch_id'))
                ->orderBy('word')
                ->pluck('word')
                ->all();
            $dynamic = array_values(array_unique(array_filter(array_map(
                fn ($w) => mb_strtolower(trim((string) $w)),
                $rows
            ))));

            return array_values(array_unique(array_merge($base, $dynamic)));
        });
    }

    public function clearCache(int $institutionId): void
    {
        for ($i = 0; $i <= 500; $i++) {
            Cache::forget('search:stop_words:' . $institutionId . ':' . $i);
        }
    }
}

