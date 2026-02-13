<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SeedSearchQueries extends Command
{
    protected $signature = 'notobuku:seed-search-queries {--institution= : Batasi institution_id} {--limit=50 : Jumlah seed per sumber}';
    protected $description = 'Seed search_queries dari judul/penulis populer (views/borrow) & peminjaman';

    public function handle(): int
    {
        if (!Schema::hasTable('search_queries')) {
            $this->error('Tabel search_queries belum ada. Jalankan migrate dulu.');
            return self::FAILURE;
        }

        $institutionId = $this->option('institution') ? (int) $this->option('institution') : null;
        $limit = max(10, (int) $this->option('limit'));

        $seeded = 0;
        $seeded += $this->seedFromMetrics('click', $institutionId, $limit);
        $seeded += $this->seedFromMetrics('borrow', $institutionId, $limit);
        $seeded += $this->seedFromLoans($institutionId, $limit);

        $this->info("Seeded {$seeded} queries.");
        return self::SUCCESS;
    }

    private function seedFromMetrics(string $type, ?int $institutionId, int $limit): int
    {
        if (!Schema::hasTable('biblio_metrics')) {
            return 0;
        }

        $metricCol = $type === 'borrow' ? 'borrow_count' : 'click_count';

        $query = DB::table('biblio_metrics as bm')
            ->join('biblio as b', 'b.id', '=', 'bm.biblio_id')
            ->whereColumn('bm.institution_id', 'b.institution_id')
            ->orderByDesc("bm.{$metricCol}")
            ->limit($limit)
            ->get(['b.id', 'b.title', 'bm.institution_id', "bm.{$metricCol} as score"]);

        if ($institutionId) {
            $query = $query->filter(fn ($row) => (int) $row->institution_id === $institutionId);
        }

        $rows = $query->values();
        if ($rows->isEmpty()) {
            return 0;
        }

        $authors = $this->loadAuthors($rows->pluck('id')->all());
        $seeded = 0;

        foreach ($rows as $row) {
            $seeded += $this->upsertQuery((int) $row->institution_id, $row->title, (int) $row->score);
            $authorNames = $authors[$row->id] ?? [];
            foreach ($authorNames as $name) {
                $seeded += $this->upsertQuery((int) $row->institution_id, $name, max(1, (int) $row->score / 2));
            }
        }

        return $seeded;
    }

    private function seedFromLoans(?int $institutionId, int $limit): int
    {
        if (!Schema::hasTable('loan_items')) {
            return 0;
        }

        $base = DB::table('loan_items as li')
            ->join('items as i', 'i.id', '=', 'li.item_id')
            ->join('biblio as b', 'b.id', '=', 'i.biblio_id')
            ->select('b.id', 'b.title', 'b.institution_id', DB::raw('COUNT(li.id) as borrow_count'))
            ->groupBy('b.id', 'b.title', 'b.institution_id')
            ->orderByDesc('borrow_count')
            ->limit($limit);

        if ($institutionId) {
            $base->where('b.institution_id', $institutionId);
        }

        $rows = $base->get();
        if ($rows->isEmpty()) {
            return 0;
        }

        $authors = $this->loadAuthors($rows->pluck('id')->all());
        $seeded = 0;

        foreach ($rows as $row) {
            $seeded += $this->upsertQuery((int) $row->institution_id, $row->title, (int) $row->borrow_count);
            $authorNames = $authors[$row->id] ?? [];
            foreach ($authorNames as $name) {
                $seeded += $this->upsertQuery((int) $row->institution_id, $name, max(1, (int) $row->borrow_count / 2));
            }
        }

        return $seeded;
    }

    private function loadAuthors(array $biblioIds): array
    {
        if (empty($biblioIds)) {
            return [];
        }

        $rows = DB::table('biblio_author as ba')
            ->join('authors as a', 'a.id', '=', 'ba.author_id')
            ->whereIn('ba.biblio_id', $biblioIds)
            ->orderBy('ba.sort_order')
            ->get(['ba.biblio_id', 'a.name']);

        return $rows->groupBy('biblio_id')
            ->map(fn ($group) => $group->pluck('name')->filter()->values()->all())
            ->all();
    }

    private function upsertQuery(int $institutionId, ?string $query, int $score): int
    {
        $query = trim((string) $query);
        if ($query === '') {
            return 0;
        }

        $normalized = Str::of($query)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->toString();

        if ($normalized === '' || mb_strlen($normalized) < 3) {
            return 0;
        }

        $now = now();
        $affected = DB::table('search_queries')
            ->where('institution_id', $institutionId)
            ->where('normalized_query', $normalized)
            ->update([
                'query' => $query,
                'last_hits' => $score,
                'last_searched_at' => $now,
                'search_count' => DB::raw('search_count + ' . max(1, $score)),
                'updated_at' => $now,
            ]);

        if ($affected === 0) {
            DB::table('search_queries')->insert([
                'institution_id' => $institutionId,
                'normalized_query' => $normalized,
                'query' => $query,
                'user_id' => null,
                'last_hits' => $score,
                'last_searched_at' => $now,
                'search_count' => max(1, $score),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return 1;
    }
}
