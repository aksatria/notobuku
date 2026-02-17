<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SearchTuningService
{
    public function forInstitution(int $institutionId): array
    {
        if ($institutionId <= 0 || !Schema::hasTable('search_tuning_settings')) {
            return $this->defaults();
        }

        $cacheKey = $this->cacheKey($institutionId);
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($institutionId) {
            $row = DB::table('search_tuning_settings')
                ->where('institution_id', $institutionId)
                ->first();
            if (!$row) {
                return $this->defaults();
            }

            return $this->normalize(array_merge($this->defaults(), [
                'title_exact_weight' => (int) ($row->title_exact_weight ?? 80),
                'author_exact_weight' => (int) ($row->author_exact_weight ?? 40),
                'subject_exact_weight' => (int) ($row->subject_exact_weight ?? 25),
                'publisher_exact_weight' => (int) ($row->publisher_exact_weight ?? 15),
                'isbn_exact_weight' => (int) ($row->isbn_exact_weight ?? 100),
                'short_query_max_len' => (int) ($row->short_query_max_len ?? 4),
                'short_query_multiplier' => (float) ($row->short_query_multiplier ?? 1.6),
                'available_weight' => (float) ($row->available_weight ?? 10),
                'borrowed_penalty' => (float) ($row->borrowed_penalty ?? 3),
                'reserved_penalty' => (float) ($row->reserved_penalty ?? 2),
            ]));
        });
    }

    public function upsertForInstitution(int $institutionId, array $input, ?int $updatedBy = null): array
    {
        $settings = $this->normalize(array_merge($this->defaults(), $input));
        $now = now();

        if ($institutionId <= 0 || !Schema::hasTable('search_tuning_settings')) {
            return $settings;
        }

        $payload = array_merge($settings, [
            'institution_id' => $institutionId,
            'updated_by' => $updatedBy,
            'updated_at' => $now,
        ]);

        $exists = DB::table('search_tuning_settings')->where('institution_id', $institutionId)->exists();
        if ($exists) {
            DB::table('search_tuning_settings')
                ->where('institution_id', $institutionId)
                ->update($payload);
        } else {
            $payload['created_at'] = $now;
            DB::table('search_tuning_settings')->insert($payload);
        }

        Cache::forget($this->cacheKey($institutionId));
        return $settings;
    }

    public function resetForInstitution(int $institutionId, ?int $updatedBy = null): array
    {
        $defaults = $this->defaults();
        return $this->upsertForInstitution($institutionId, $defaults, $updatedBy);
    }

    public function defaults(): array
    {
        return $this->normalize([
            'title_exact_weight' => 80,
            'author_exact_weight' => 40,
            'subject_exact_weight' => 25,
            'publisher_exact_weight' => 15,
            'isbn_exact_weight' => 100,
            'short_query_max_len' => (int) config('search.short_query_boost.max_len', 4),
            'short_query_multiplier' => (float) config('search.short_query_boost.multiplier', 1.6),
            'available_weight' => (float) config('search.ranking.availability.available_weight', 10),
            'borrowed_penalty' => (float) config('search.ranking.availability.borrowed_penalty', 3),
            'reserved_penalty' => (float) config('search.ranking.availability.reserved_penalty', 2),
        ]);
    }

    private function normalize(array $data): array
    {
        return [
            'title_exact_weight' => max(0, min(500, (int) ($data['title_exact_weight'] ?? 80))),
            'author_exact_weight' => max(0, min(500, (int) ($data['author_exact_weight'] ?? 40))),
            'subject_exact_weight' => max(0, min(500, (int) ($data['subject_exact_weight'] ?? 25))),
            'publisher_exact_weight' => max(0, min(500, (int) ($data['publisher_exact_weight'] ?? 15))),
            'isbn_exact_weight' => max(0, min(1000, (int) ($data['isbn_exact_weight'] ?? 100))),
            'short_query_max_len' => max(1, min(12, (int) ($data['short_query_max_len'] ?? 4))),
            'short_query_multiplier' => max(1.0, min(5.0, (float) ($data['short_query_multiplier'] ?? 1.6))),
            'available_weight' => max(0.0, min(100.0, (float) ($data['available_weight'] ?? 10))),
            'borrowed_penalty' => max(0.0, min(100.0, (float) ($data['borrowed_penalty'] ?? 3))),
            'reserved_penalty' => max(0.0, min(100.0, (float) ($data['reserved_penalty'] ?? 2))),
        ];
    }

    private function cacheKey(int $institutionId): string
    {
        return 'search:tuning:institution:' . $institutionId;
    }
}

