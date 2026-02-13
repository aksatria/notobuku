<?php

namespace App\Services;

use App\Jobs\SyncBiblioSearchIndexJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BiblioInteractionService
{
    public function recordClick(int $biblioId, int $institutionId, ?int $userId = null, ?int $branchId = null): void
    {
        if ($biblioId <= 0 || $institutionId <= 0) {
            return;
        }

        $this->insertEvent($biblioId, $institutionId, $userId, $branchId, 'click');
        $this->incrementMetric($biblioId, $institutionId, $userId, 'click');
    }

    public function recordBorrow(array $biblioIds, int $institutionId, ?int $userId = null, ?int $branchId = null): void
    {
        $ids = array_values(array_unique(array_map('intval', $biblioIds)));
        $ids = array_values(array_filter($ids, fn ($id) => $id > 0));

        if ($institutionId <= 0 || empty($ids)) {
            return;
        }

        foreach ($ids as $biblioId) {
            $this->insertEvent($biblioId, $institutionId, $userId, $branchId, 'borrow');
            $this->incrementMetric($biblioId, $institutionId, $userId, 'borrow');
        }
    }

    private function insertEvent(int $biblioId, int $institutionId, ?int $userId, ?int $branchId, string $type): void
    {
        if (!Schema::hasTable('biblio_events')) {
            return;
        }

        DB::table('biblio_events')->insert([
            'institution_id' => $institutionId,
            'biblio_id' => $biblioId,
            'user_id' => $userId,
            'branch_id' => $branchId,
            'event_type' => $type,
            'created_at' => now(),
        ]);
    }

    private function incrementMetric(int $biblioId, int $institutionId, ?int $userId, string $type): void
    {
        $now = now();
        $click = $type === 'click';
        $borrow = $type === 'borrow';

        if (Schema::hasTable('biblio_metrics')) {
            $updated = DB::table('biblio_metrics')
                ->where('institution_id', $institutionId)
                ->where('biblio_id', $biblioId)
                ->update([
                    'click_count' => $click ? DB::raw('click_count + 1') : DB::raw('click_count'),
                    'borrow_count' => $borrow ? DB::raw('borrow_count + 1') : DB::raw('borrow_count'),
                    'last_clicked_at' => $click ? $now : DB::raw('last_clicked_at'),
                    'last_borrowed_at' => $borrow ? $now : DB::raw('last_borrowed_at'),
                    'updated_at' => $now,
                ]);

            if ($updated === 0) {
                DB::table('biblio_metrics')->insert([
                    'institution_id' => $institutionId,
                    'biblio_id' => $biblioId,
                    'click_count' => $click ? 1 : 0,
                    'borrow_count' => $borrow ? 1 : 0,
                    'last_clicked_at' => $click ? $now : null,
                    'last_borrowed_at' => $borrow ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if ($userId && Schema::hasTable('biblio_user_metrics')) {
            $updated = DB::table('biblio_user_metrics')
                ->where('institution_id', $institutionId)
                ->where('user_id', $userId)
                ->where('biblio_id', $biblioId)
                ->update([
                    'click_count' => $click ? DB::raw('click_count + 1') : DB::raw('click_count'),
                    'borrow_count' => $borrow ? DB::raw('borrow_count + 1') : DB::raw('borrow_count'),
                    'last_clicked_at' => $click ? $now : DB::raw('last_clicked_at'),
                    'last_borrowed_at' => $borrow ? $now : DB::raw('last_borrowed_at'),
                    'updated_at' => $now,
                ]);

            if ($updated === 0) {
                DB::table('biblio_user_metrics')->insert([
                    'institution_id' => $institutionId,
                    'user_id' => $userId,
                    'biblio_id' => $biblioId,
                    'click_count' => $click ? 1 : 0,
                    'borrow_count' => $borrow ? 1 : 0,
                    'last_clicked_at' => $click ? $now : null,
                    'last_borrowed_at' => $borrow ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('biblio_metrics')) {
            SyncBiblioSearchIndexJob::dispatch([$biblioId])->afterCommit();
        }
    }
}
