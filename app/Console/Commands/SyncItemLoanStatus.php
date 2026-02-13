<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncItemLoanStatus extends Command
{
    /**
     * Default aman: hanya audit (dry-run). Untuk benar-benar update, pakai --fix
     *
     * Opsi:
     *  --fix            : benar-benar update items.status
     *  --institution=ID : batasi satu institusi
     *  --branch=ID      : batasi satu cabang (loans.branch_id)
     *  --limit=N        : batasi jumlah row yang diproses (berguna untuk uji)
     *  --json           : output laporan sebagai JSON
     */
    protected $signature = 'notobuku:sync-item-loan-status
        {--fix : Apply changes (default is dry-run)}
        {--institution= : Filter by institution_id}
        {--branch= : Filter by branch_id}
        {--limit= : Limit rows processed}
        {--json : Output as JSON}';

    protected $description = 'Audit & perbaiki mismatch status item vs loan aktif (loan_items returned_at NULL).';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $institutionId = $this->option('institution');
        $branchId = $this->option('branch');
        $limit = $this->option('limit') ? (int)$this->option('limit') : null;
        $asJson = (bool) $this->option('json');

        // Fokus perbaikan yang aman:
        // Jika ada loan_item aktif (returned_at NULL) => item harus borrowed.
        $q = DB::table('loan_items as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id')
            ->join('items as i', 'i.id', '=', 'li.item_id')
            // perhatikan: title ada di biblio (bukan biblios) di project kamu
            ->leftJoin('biblio as b', 'b.id', '=', 'i.biblio_id')
            ->whereNull('li.returned_at')
            ->where('i.status', '!=', 'borrowed')
            ->select([
                'i.id as item_id',
                'i.barcode',
                'i.status as current_item_status',
                'l.id as loan_id',
                'l.loan_code',
                'l.branch_id',
                'l.institution_id',
                DB::raw('COALESCE(b.title, "-") as title'),
                'li.id as loan_item_id',
                'li.due_at',
                'li.borrowed_at',
            ])
            ->orderBy('l.institution_id')
            ->orderBy('l.branch_id')
            ->orderByDesc('li.id');

        if ($institutionId !== null && $institutionId !== '') {
            $q->where('l.institution_id', (int)$institutionId);
        }
        if ($branchId !== null && $branchId !== '') {
            $q->where('l.branch_id', (int)$branchId);
        }
        if ($limit !== null && $limit > 0) {
            $q->limit($limit);
        }

        $rows = $q->get();

        $summary = [
            'mode' => $fix ? 'FIX' : 'DRY_RUN',
            'filters' => [
                'institution_id' => $institutionId ? (int)$institutionId : null,
                'branch_id' => $branchId ? (int)$branchId : null,
                'limit' => $limit,
            ],
            'total_mismatch_found' => $rows->count(),
            'total_updated' => 0,
        ];

        if ($rows->isEmpty()) {
            $this->info('✅ Tidak ada mismatch ditemukan.');
            if ($asJson) {
                $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            return self::SUCCESS;
        }

        // Output tabel audit
        if (!$asJson) {
            $this->warn(($fix ? '🔧 MODE FIX' : '🧪 MODE DRY-RUN') . " — ditemukan {$rows->count()} mismatch.");
            $this->table(
                ['item_id', 'barcode', 'status_sekarang', 'loan_code', 'branch', 'inst', 'loan_item_id', 'due_at', 'title'],
                $rows->map(function ($r) {
                    return [
                        $r->item_id,
                        $r->barcode,
                        $r->current_item_status,
                        $r->loan_code,
                        $r->branch_id,
                        $r->institution_id,
                        $r->loan_item_id,
                        $r->due_at,
                        mb_strimwidth((string)$r->title, 0, 40, '…'),
                    ];
                })->all()
            );
        }

        if (!$fix) {
            $this->info('ℹ️ Ini hanya audit (dry-run). Jalankan dengan --fix untuk memperbaiki.');
            if ($asJson) {
                $this->line(json_encode($summary + ['rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            return self::SUCCESS;
        }

        // FIX: update items.status -> borrowed untuk semua mismatch yang ditemukan
        // Update satu per satu supaya aman dan mudah di-log (bisa diganti bulk update jika mau).
        $updated = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $r) {
                $aff = DB::table('items')
                    ->where('id', (int)$r->item_id)
                    ->where('status', '!=', 'borrowed') // guard lagi
                    ->update([
                        'status' => 'borrowed',
                        'updated_at' => now(),
                    ]);

                if ($aff > 0) $updated += $aff;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('❌ Gagal sync: ' . $e->getMessage());
            return self::FAILURE;
        }

        $summary['total_updated'] = $updated;

        $this->info("✅ Selesai. Updated: {$updated} item menjadi status 'borrowed'.");

        if ($asJson) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
