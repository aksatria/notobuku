<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InteropReconcileCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_command_runs_and_writes_metric_rows(): void
    {
        $code = Artisan::call('notobuku:interop-reconcile', [
            '--retention-days' => 30,
        ]);

        $this->assertSame(0, $code);
        $this->assertGreaterThan(0, (int) DB::table('interop_metric_points')->count());
        $this->assertGreaterThan(0, (int) DB::table('interop_metric_daily')->count());
    }
}

