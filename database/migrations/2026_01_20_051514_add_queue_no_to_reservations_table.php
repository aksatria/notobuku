<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reservations')) {
            return;
        }

        // 1) Tambahkan queue_no bila belum ada
        if (!Schema::hasColumn('reservations', 'queue_no')) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->unsignedInteger('queue_no')->nullable()->index();
            });
        }

        // 2) Backfill queue_no untuk data existing (hanya queued/ready)
        $hasBranch = Schema::hasColumn('reservations', 'branch_id');

        $groups = DB::table('reservations')
            ->select(['institution_id', 'biblio_id'])
            ->when($hasBranch, fn($q) => $q->addSelect('branch_id'))
            ->groupBy('institution_id', 'biblio_id')
            ->when($hasBranch, fn($q) => $q->groupBy('branch_id'))
            ->get();

        foreach ($groups as $g) {
            $q = DB::table('reservations')
                ->where('institution_id', (int)$g->institution_id)
                ->where('biblio_id', (int)$g->biblio_id)
                ->whereIn('status', ['queued', 'ready']);

            if ($hasBranch) {
                if ($g->branch_id === null) $q->whereNull('branch_id');
                else $q->where('branch_id', (int)$g->branch_id);
            }

            $rows = $q->orderBy('created_at')->orderBy('id')->get(['id', 'queue_no']);

            $no = 1;
            foreach ($rows as $r) {
                // kalau sudah ada queue_no, lanjutkan angka setelahnya
                if (!is_null($r->queue_no)) {
                    $no = max($no, ((int)$r->queue_no) + 1);
                    continue;
                }

                DB::table('reservations')->where('id', (int)$r->id)->update([
                    'queue_no' => $no,
                    'updated_at' => now(),
                ]);

                $no++;
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('reservations')) {
            return;
        }

        if (Schema::hasColumn('reservations', 'queue_no')) {
            Schema::table('reservations', function (Blueprint $table) {
                // dropIndex aman kalau ada
                try { $table->dropIndex(['queue_no']); } catch (\Throwable $e) {}
                $table->dropColumn('queue_no');
            });
        }
    }
};
