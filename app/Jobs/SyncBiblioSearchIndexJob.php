<?php

namespace App\Jobs;

use App\Models\Biblio;
use App\Services\Search\BiblioSearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;

class SyncBiblioSearchIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private array $biblioIds)
    {
    }

    public function handle(BiblioSearchService $search): void
    {
        if (!$search->enabled()) {
            return;
        }

        $ids = array_values(array_unique(array_map('intval', $this->biblioIds)));
        if (empty($ids)) {
            return;
        }

        $query = Biblio::query()
            ->whereIn('id', $ids)
            ->with(['authors', 'subjects', 'identifiers', 'metadata'])
            ->withCount([
                'items',
                'availableItems as available_items_count'
            ])
            ;

        if (Schema::hasTable('biblio_metrics')) {
            $query->with('metric');
        }

        $biblios = $query->get();

        $search->indexDocuments($biblios);
    }
}
