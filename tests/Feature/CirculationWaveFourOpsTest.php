<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Support\CirculationMetrics;

class CirculationWaveFourOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_circulation_health_alert_command_sends_webhook_and_writes_audit_log(): void
    {
        Cache::forget('circulation:metrics:alerts:last_triggered_at');
        Http::fake([
            'https://ops.example.test/*' => Http::response(['ok' => true], 200),
        ]);

        config([
            'notobuku.circulation.alerts.webhook_url' => 'https://ops.example.test/circulation-alert',
            'notobuku.circulation.alerts.email_to' => '',
            'notobuku.circulation.health_thresholds.warning.p95_ms' => 1,
            'notobuku.circulation.health_thresholds.critical.p95_ms' => 999999,
        ]);

        CirculationMetrics::recordEndpoint('transaksi.pinjam.store', 120, 200);

        $this->artisan('notobuku:circulation-health-alert')->assertExitCode(0);

        Http::assertSent(function ($request) {
            return str_contains((string) $request->url(), 'ops.example.test/circulation-alert')
                && data_get($request->data(), 'event') === 'circulation_health_alert';
        });

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'circulation_health_alert',
            'status' => 'sent',
            'format' => 'system',
        ]);
    }

    public function test_circulation_exception_snapshot_command_generates_daily_csv(): void
    {
        Storage::fake('local');

        config([
            'notobuku.circulation.exceptions.overdue_extreme_days' => 1,
        ]);

        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst C-EX',
            'code' => 'INST-C-EX-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchA = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-A',
            'name' => 'Branch A',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $branchB = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-B',
            'name' => 'Branch B',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberId = DB::table('members')->insertGetId([
            'institution_id' => $institutionId,
            'member_code' => 'MBR-EX-01',
            'full_name' => 'Member Exception',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblioId = DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'Judul Exception',
            'material_type' => 'book',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemA = DB::table('items')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchA,
            'biblio_id' => $biblioId,
            'barcode' => 'BC-EX-A',
            'accession_number' => 'ACC-EX-A',
            'status' => 'borrowed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemB = DB::table('items')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchB,
            'biblio_id' => $biblioId,
            'barcode' => 'BC-EX-B',
            'accession_number' => 'ACC-EX-B',
            'status' => 'borrowed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $loan1 = DB::table('loans')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchA,
            'member_id' => $memberId,
            'loan_code' => 'L-EX-001',
            'status' => 'overdue',
            'loaned_at' => now()->subDays(3),
            'due_at' => now()->subDays(2),
            'created_at' => now()->subDays(3),
            'updated_at' => now(),
        ]);

        DB::table('loan_items')->insert([
            'loan_id' => $loan1,
            'item_id' => $itemA,
            'status' => 'borrowed',
            'borrowed_at' => now()->subDays(3),
            'due_at' => now()->subDays(2),
            'returned_at' => null,
            'created_at' => now()->subDays(3),
            'updated_at' => now(),
        ]);

        $loan2 = DB::table('loans')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchA,
            'member_id' => $memberId,
            'loan_code' => 'L-EX-002',
            'status' => 'open',
            'loaned_at' => now()->subDay(),
            'due_at' => now()->addDays(3),
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        DB::table('loan_items')->insert([
            'loan_id' => $loan2,
            'item_id' => $itemB,
            'status' => 'borrowed',
            'borrowed_at' => now()->subDay(),
            'due_at' => now()->addDays(3),
            'returned_at' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        DB::table('audits')->insert([
            'institution_id' => $institutionId,
            'actor_user_id' => null,
            'actor_role' => 'admin',
            'action' => 'fine.void',
            'module' => 'denda',
            'auditable_type' => 'Fine',
            'auditable_id' => 123,
            'metadata' => json_encode(['loan_item_id' => 999], JSON_UNESCAPED_UNICODE),
            'ip' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $date = now()->toDateString();
        $this->artisan('notobuku:circulation-exception-snapshot', ['--date' => $date])->assertExitCode(0);

        $file = 'reports/circulation-exceptions/circulation-exceptions-' . $date . '.csv';
        Storage::disk('local')->assertExists($file);

        $csv = Storage::disk('local')->get($file);
        $this->assertStringContainsString('overdue_extreme', $csv);
        $this->assertStringContainsString('fine_void_activity', $csv);
        $this->assertStringContainsString('branch_mismatch_active_loan', $csv);
    }
}

