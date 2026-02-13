<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\DashboardService;

class CheckDashboardAlerts extends Command
{
    protected $signature = 'notobuku:check-dashboard-alerts';
    protected $description = 'Cek alert dashboard (overdue) dan buat notifikasi';

    public function handle(DashboardService $service): int
    {
        $now = Carbon::now();

        // Threshold bisa kamu ubah cepat:
        $ratioThreshold = 10;   // % overdue ratio
        $itemsThreshold = 20;   // item overdue minimal

        // Kalau belum ada tabel institutions, ganti sumbernya.
        $institutions = DB::table('institutions')
            ->select('id')
            ->get();

        foreach ($institutions as $inst) {
            $data = $service->build($inst->id, null);

            $ratio = (float) (
                $data['health']['overdue_ratio'] ?? 0
            );

            $overdueItems = (int) (
                $data['health']['overdue_items'] ?? 0
            );

            $shouldAlert = (
                $ratio >= $ratioThreshold
                || $overdueItems >= $itemsThreshold
            );

            if (!$shouldAlert) {
                continue;
            }

            $title = '⚠️ Alert Dashboard: Overdue Tinggi';

            $body = implode("\n", [
                "Tanggal: " . $now->isoFormat('D MMM YYYY HH:mm'),
                "Overdue ratio: {$ratio}%",
                "Overdue items: {$overdueItems}",
                "Saran: lakukan follow-up member / tarik laporan overdue.",
            ]);

            DB::table('notifications')->insert([
                'institution_id' => $inst->id,
                'title' => $title,
                'body' => $body,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->info(
                "Alert dibuat untuk institution {$inst->id}"
            );
        }

        return Command::SUCCESS;
    }
}
