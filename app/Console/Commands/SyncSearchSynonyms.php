<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncSearchSynonyms extends Command
{
    protected $signature = 'notobuku:sync-search-synonyms 
        {--institution= : Batasi institution_id}
        {--limit=300 : Jumlah query teratas}
        {--min=2 : Minimal search_count}
        {--lev=2 : Maksimal jarak Levenshtein}
        {--prefix=3 : Minimal prefix sama (panjang)}
        {--aggressive=1 : Mode agresif (1=on,0=off)}';
    protected $description = 'Bangun sinonim otomatis dari log pencarian (akronim, bentuk singkat, mirip)';

    public function handle(): int
    {
        if (!Schema::hasTable('search_queries') || !Schema::hasTable('search_synonyms')) {
            $this->error('Tabel search_queries / search_synonyms belum ada.');
            return self::FAILURE;
        }

        $institutionId = $this->option('institution') ? (int) $this->option('institution') : null;
        $limit = max(50, (int) $this->option('limit'));
        $minCount = max(1, (int) $this->option('min'));
        $maxLev = max(1, (int) $this->option('lev'));
        $minPrefix = max(2, (int) $this->option('prefix'));
        $aggressive = (int) $this->option('aggressive') === 1;
        $stopWords = array_map('strtolower', (array) config('search.stop_words', []));

        $query = DB::table('search_queries')
            ->when($institutionId, fn($q) => $q->where('institution_id', $institutionId))
            ->where('search_count', '>=', $minCount)
            ->orderByDesc('search_count')
            ->orderByDesc('last_searched_at')
            ->limit($limit)
            ->get(['institution_id', 'query', 'normalized_query', 'search_count']);

        if ($query->isEmpty()) {
            $this->info('Tidak ada query yang cukup untuk sinkronisasi.');
            return self::SUCCESS;
        }

        $byNorm = $query->keyBy('normalized_query');
        $added = 0;

        foreach ($query as $row) {
            $phrase = trim((string) $row->query);
            $words = preg_split('/\s+/', Str::of($phrase)->lower()->replaceMatches('/[^a-z0-9\s]/', ' ')->squish());
            $words = array_values(array_filter($words, function ($w) use ($stopWords) {
                return $w !== '' && !in_array($w, $stopWords, true);
            }));

            if (count($words) < 2) {
                continue;
            }

            $acronym = implode('', array_map(fn($w) => mb_substr($w, 0, 1), $words));
            $acronym = strtolower($acronym);
            if (strlen($acronym) < 3 || strlen($acronym) > 6) {
                continue;
            }

            if ($byNorm->has($acronym)) {
                $added += $this->upsertSynonym((int) $row->institution_id, null, $acronym, [$phrase]);
            }

            if ($aggressive) {
                $added += $this->createShortFormSynonyms((int) $row->institution_id, $phrase, $byNorm);
            }
        }

        if ($aggressive) {
            $added += $this->createLevenshteinPairs($query, $maxLev, $minPrefix);
        }

        $this->info("Synonyms auto-synced: {$added}");
        return self::SUCCESS;
    }

    private function upsertSynonym(int $institutionId, ?int $branchId, string $term, array $synonyms): int
    {
        $term = trim($term);
        if ($term === '' || empty($synonyms)) {
            return 0;
        }

        $max = (int) config('search.synonym_max', 10);
        $synonyms = array_values(array_unique(array_filter(array_map('trim', $synonyms))));
        if (empty($synonyms)) {
            return 0;
        }

        $now = now();
        $existing = DB::table('search_synonyms')
            ->where('institution_id', $institutionId)
            ->where('branch_id', $branchId)
            ->where('term', $term)
            ->first();

        if ($existing) {
            $current = (array) json_decode((string) $existing->synonyms, true);
            $merged = array_values(array_unique(array_merge($current, $synonyms)));
            if ($max > 0 && count($merged) > $max) {
                $merged = array_slice($merged, 0, $max);
            }
            DB::table('search_synonyms')
                ->where('id', $existing->id)
                ->update([
                    'synonyms' => json_encode($merged),
                    'updated_at' => $now,
                ]);
            return 0;
        }

        DB::table('search_synonyms')->insert([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'term' => $term,
            'synonyms' => json_encode($max > 0 ? array_slice($synonyms, 0, $max) : $synonyms),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return 1;
    }

    private function createShortFormSynonyms(int $institutionId, string $phrase, $byNorm): int
    {
        $added = 0;
        $plain = Str::of($phrase)->lower()->replaceMatches('/[^a-z0-9\s]/', ' ')->squish()->toString();
        $words = array_values(array_filter(explode(' ', $plain)));
        if (count($words) < 2) {
            return 0;
        }

        $short = implode('', array_map(fn($w) => mb_substr($w, 0, 3), $words));
        $short = strtolower($short);
        if (strlen($short) >= 4 && strlen($short) <= 12 && $byNorm->has($short)) {
            $added += $this->upsertSynonym($institutionId, null, $short, [$phrase]);
        }

        $joined = implode('', $words);
        if (strlen($joined) >= 5 && strlen($joined) <= 20 && $byNorm->has($joined)) {
            $added += $this->upsertSynonym($institutionId, null, $joined, [$phrase]);
        }

        return $added;
    }

    private function createLevenshteinPairs($rows, int $maxLev, int $minPrefix): int
    {
        $added = 0;
        $grouped = $rows->groupBy('institution_id');
        foreach ($grouped as $institutionId => $items) {
            $items = $items->values();
            $count = $items->count();
            for ($i = 0; $i < $count; $i++) {
                $a = $items[$i];
                $aNorm = (string) $a->normalized_query;
                if ($aNorm === '' || mb_strlen($aNorm) < 4) continue;
                for ($j = $i + 1; $j < $count; $j++) {
                    $b = $items[$j];
                    $bNorm = (string) $b->normalized_query;
                    if ($bNorm === '' || abs(mb_strlen($aNorm) - mb_strlen($bNorm)) > 3) continue;

                    if (mb_substr($aNorm, 0, $minPrefix) !== mb_substr($bNorm, 0, $minPrefix)) {
                        continue;
                    }

                    $dist = levenshtein($aNorm, $bNorm);
                    if ($dist > $maxLev) continue;

                    $added += $this->upsertSynonym((int) $institutionId, null, $aNorm, [$b->query]);
                    $added += $this->upsertSynonym((int) $institutionId, null, $bNorm, [$a->query]);
                }
            }
        }
        return $added;
    }
}
