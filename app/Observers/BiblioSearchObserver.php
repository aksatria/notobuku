<?php

namespace App\Observers;

use App\Jobs\SyncBiblioSearchIndexJob;
use App\Models\Biblio;
use App\Services\Search\BiblioSearchService;

class BiblioSearchObserver
{
    public function saved(Biblio $biblio): void
    {
        if (!app(BiblioSearchService::class)->enabled()) {
            return;
        }

        $mode = (string) config('search.auto_index_mode', 'sync');
        if ($mode === 'sync') {
            SyncBiblioSearchIndexJob::dispatchSync([$biblio->id]);
            return;
        }

        SyncBiblioSearchIndexJob::dispatch([$biblio->id])->afterCommit();
    }

    public function deleted(Biblio $biblio): void
    {
        $search = app(BiblioSearchService::class);
        if (!$search->enabled()) {
            return;
        }
        $search->deleteDocuments([(int) $biblio->id]);
    }
}
