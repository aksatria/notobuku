<?php

namespace App\Console\Commands;

use App\Models\Biblio;
use App\Services\Search\BiblioSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ReindexBiblioSearch extends Command
{
    protected $signature = 'notobuku:search-reindex {--chunk=500 : Jumlah data per batch}';
    protected $description = 'Rebuild index pencarian katalog (Meilisearch).';

    public function handle(BiblioSearchService $search): int
    {
        if (!$search->enabled()) {
            $this->warn('Search belum aktif. Set SEARCH_ENABLED=true dan konfigurasi Meilisearch.');
            return self::SUCCESS;
        }

        $search->ensureSettings();
        $chunk = max(50, (int) $this->option('chunk'));

        $total = Biblio::query()->count();
        $this->info("Reindex katalog: {$total} biblio (chunk={$chunk})");

        $query = Biblio::query()
            ->with(['authors', 'subjects', 'identifiers', 'metadata'])
            ->withCount(['items', 'availableItems as available_items_count'])
            ->orderBy('id');

        if (Schema::hasTable('biblio_metrics')) {
            $query->with('metric');
        }

        $query->chunk($chunk, function ($biblios) use ($search) {
                $search->indexDocuments($biblios);
                $this->line(' - synced: ' . $biblios->count());
            });

        $this->info('Selesai.');
        return self::SUCCESS;
    }
}
