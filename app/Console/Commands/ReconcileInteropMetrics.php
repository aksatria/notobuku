<?php

namespace App\Console\Commands;

use App\Support\InteropMetrics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReconcileInteropMetrics extends Command
{
    protected $signature = 'notobuku:interop-reconcile {--retention-days=}';

    protected $description = 'Reconcile interop metrics snapshot and prune old DB metric rows.';

    public function handle(): int
    {
        $retentionDays = (int) ($this->option('retention-days') ?? 0);
        if ($retentionDays <= 0) {
            $retentionDays = (int) config('notobuku.interop.db_retention_days', 120);
        }
        $retentionDays = max(7, min(3650, $retentionDays));

        $snapshot = InteropMetrics::snapshot();
        $health = (array) ($snapshot['health'] ?? []);
        $this->info('Interop snapshot refreshed. Health=' . (string) ($health['label'] ?? 'unknown'));

        $deletedPoints = 0;
        $deletedDaily = 0;

        if (Schema::hasTable('interop_metric_daily')) {
            DB::table('interop_metric_daily')->updateOrInsert(
                ['day' => now()->toDateString()],
                ['day' => now()->toDateString(), 'updated_at' => now(), 'created_at' => now()]
            );
        }

        if (Schema::hasTable('interop_metric_points')) {
            $deletedPoints = DB::table('interop_metric_points')
                ->where('minute_at', '<', now()->subDays($retentionDays))
                ->delete();
        }
        if (Schema::hasTable('interop_metric_daily')) {
            $deletedDaily = DB::table('interop_metric_daily')
                ->where('day', '<', now()->subDays($retentionDays)->toDateString())
                ->delete();
        }

        $this->info("Pruned interop_metric_points={$deletedPoints}, interop_metric_daily={$deletedDaily}, retention_days={$retentionDays}");
        return self::SUCCESS;
    }
}
