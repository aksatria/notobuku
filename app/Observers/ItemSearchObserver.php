<?php

namespace App\Observers;

use App\Jobs\SyncBiblioSearchIndexJob;
use App\Models\Item;
use App\Services\Search\BiblioSearchService;

class ItemSearchObserver
{
    public function saved(Item $item): void
    {
        if (!app(BiblioSearchService::class)->enabled()) {
            return;
        }

        if ($item->wasChanged(['status', 'biblio_id'])) {
            $biblioId = (int) ($item->biblio_id ?? 0);
            if ($biblioId > 0) {
                $mode = (string) config('search.auto_index_mode', 'sync');
                if ($mode === 'sync') {
                    SyncBiblioSearchIndexJob::dispatchSync([$biblioId]);
                    return;
                }
                SyncBiblioSearchIndexJob::dispatch([$biblioId])->afterCommit();
            }
        }
    }

    public function deleted(Item $item): void
    {
        if (!app(BiblioSearchService::class)->enabled()) {
            return;
        }

        $biblioId = (int) ($item->biblio_id ?? 0);
        if ($biblioId > 0) {
            $mode = (string) config('search.auto_index_mode', 'sync');
            if ($mode === 'sync') {
                SyncBiblioSearchIndexJob::dispatchSync([$biblioId]);
                return;
            }
            SyncBiblioSearchIndexJob::dispatch([$biblioId])->afterCommit();
        }
    }
}
