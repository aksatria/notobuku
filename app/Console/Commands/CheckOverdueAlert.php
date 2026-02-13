<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckOverdueAlert extends Command
{
    protected $signature = 'notobuku:check-overdue-alert';
    protected $description = 'Check overdue items and trigger alerts if exceed threshold';

    public function handle(): int
    {
        $threshold = (int) config('notobuku.alerts.overdue_items_threshold', 20);
        $now = Carbon::now()->toDateTimeString();

        // Contoh: hitung per institution + branch (biar actionable)
        $rows = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->whereNull('loan_items.returned_at')
            ->whereNotNull('loan_items.due_at')
            ->where('loan_items.due_at', '<', $now)
            ->selectRaw('loans.institution_id, loans.branch_id, COUNT(*) as overdue_items')
            ->groupBy('loans.institution_id', 'loans.branch_id')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->get();

        if ($rows->isEmpty()) {
            $this->info("No overdue alerts. Threshold={$threshold}");
            return self::SUCCESS;
        }

        foreach ($rows as $r) {
            // ✅ Aksi alert (pilih salah satu):
            // 1) simpan ke table notifications kamu (kalau ada)
            // 2) kirim email
            // 3) kirim ke in-app notification table
            // Karena aku belum lihat skema notifikasi kamu, aku buat default: log + (opsional) insert.

            $msg = "ALERT: overdue_items={$r->overdue_items} (inst={$r->institution_id}, branch={$r->branch_id}) threshold={$threshold}";
            $this->warn($msg);

            // Kalau kamu punya tabel notifications sendiri:
            // DB::table('notifications')->insert([...]);
        }

        return self::SUCCESS;
    }
}
