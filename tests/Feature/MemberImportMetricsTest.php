<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberImportMetricsTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitutionAndBranch(string $code): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst ' . $code,
            'code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-' . $code,
            'name' => 'Cabang ' . $code,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeAdmin(int $institutionId, int $branchId, string $email): User
    {
        return User::create([
            'name' => 'Admin Metrics',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    public function test_metrics_only_count_last_30_days_and_same_institution(): void
    {
        [$institutionIdA, $branchIdA] = $this->seedInstitutionAndBranch('INS-MET-A');
        [$institutionIdB, $branchIdB] = $this->seedInstitutionAndBranch('INS-MET-B');

        $adminA = $this->makeAdmin($institutionIdA, $branchIdA, 'metrics-a@test.local');
        $adminB = $this->makeAdmin($institutionIdB, $branchIdB, 'metrics-b@test.local');

        AuditLog::create([
            'user_id' => $adminA->id,
            'action' => 'member_import',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionIdA,
                'inserted' => 3,
                'updated' => 2,
                'skipped' => 1,
                'force_email_duplicate' => true,
            ],
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        AuditLog::create([
            'user_id' => $adminA->id,
            'action' => 'member_import',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionIdA,
                'inserted' => 1,
                'updated' => 0,
                'skipped' => 0,
                'force_email_duplicate' => false,
            ],
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        AuditLog::create([
            'user_id' => $adminA->id,
            'action' => 'member_import_undo',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionIdA,
            ],
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        // Harus diabaikan (lebih dari 30 hari)
        AuditLog::create([
            'user_id' => $adminA->id,
            'action' => 'member_import',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionIdA,
                'inserted' => 99,
                'updated' => 99,
                'skipped' => 99,
                'force_email_duplicate' => true,
            ],
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        // Harus diabaikan (institusi lain)
        AuditLog::create([
            'user_id' => $adminB->id,
            'action' => 'member_import',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionIdB,
                'inserted' => 50,
                'updated' => 50,
                'skipped' => 50,
                'force_email_duplicate' => true,
            ],
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        $resp = $this->actingAs($adminA)->get(route('anggota.index'));
        $resp->assertOk();

        // Agregat institusi A dalam 30 hari:
        // import runs=2, undo runs=1, inserted=4, updated=2, skipped=1, override=1
        $resp->assertSee('Import Metrics (30 hari)');
        $resp->assertSee('Run Import');
        $resp->assertSee('Run Undo');
        $resp->assertSee('Inserted');
        $resp->assertSee('Updated');
        $resp->assertSee('Skipped');
        $resp->assertSee('Override Email Dup');
        $resp->assertSee('4');
        $resp->assertSee('2');
        $resp->assertSee('1');
    }

    public function test_recent_activity_table_shows_action_and_user(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch('INS-MET-C');
        $admin = $this->makeAdmin($institutionId, $branchId, 'metrics-c@test.local');

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'member_import',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionId,
                'inserted' => 2,
                'updated' => 1,
                'skipped' => 0,
                'force_email_duplicate' => true,
            ],
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'member_import_undo',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionId,
            ],
            'created_at' => now()->subMinutes(4),
            'updated_at' => now()->subMinutes(4),
        ]);

        $resp = $this->actingAs($admin)->get(route('anggota.index'));
        $resp->assertOk();
        $resp->assertSee('MEMBER_IMPORT');
        $resp->assertSee('MEMBER_IMPORT_UNDO');
        $resp->assertSee('Admin Metrics');
        $resp->assertSee('YES');
    }

    public function test_metrics_json_endpoint_returns_expected_shape_and_recent_limit(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch('INS-MET-J');
        $admin = $this->makeAdmin($institutionId, $branchId, 'metrics-json@test.local');

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'member_import',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionId,
                'inserted' => 5,
                'updated' => 3,
                'skipped' => 1,
                'force_email_duplicate' => true,
            ],
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'member_import_undo',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionId,
            ],
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);

        $resp = $this->actingAs($admin)->get(route('anggota.import.metrics', ['recent_limit' => 1]));
        $resp->assertOk();
        $resp->assertJsonPath('ok', true);
        $resp->assertJsonPath('institution_id', $institutionId);
        $resp->assertJsonStructure([
            'ok',
            'institution_id',
            'generated_at',
            'metrics' => [
                'last_7d_import_runs',
                'last_7d_undo_runs',
                'last_7d_inserted',
                'last_7d_updated',
                'last_7d_skipped',
                'last_30d_import_runs',
                'last_30d_undo_runs',
                'last_30d_inserted',
                'last_30d_updated',
                'last_30d_skipped',
                'last_30d_override_email_dup',
                'daily_7d',
                'daily_30d',
                'recent',
            ],
        ]);
        $resp->assertJsonCount(1, 'metrics.recent');
        $resp->assertJsonCount(7, 'metrics.daily_7d');
        $resp->assertJsonCount(30, 'metrics.daily_30d');
        $resp->assertJsonPath('metrics.last_7d_import_runs', 1);
        $resp->assertJsonPath('metrics.last_7d_undo_runs', 1);
        $resp->assertJsonPath('metrics.last_7d_inserted', 5);
        $resp->assertJsonPath('metrics.last_30d_import_runs', 1);
        $resp->assertJsonPath('metrics.last_30d_undo_runs', 1);
        $resp->assertJsonPath('metrics.last_30d_inserted', 5);
        $resp->assertJsonPath('metrics.last_30d_updated', 3);
        $resp->assertJsonPath('metrics.last_30d_skipped', 1);
        $resp->assertJsonPath('metrics.last_30d_override_email_dup', 1);
    }

    public function test_import_history_csv_endpoint_returns_csv_and_scopes_institution(): void
    {
        [$institutionIdA, $branchIdA] = $this->seedInstitutionAndBranch('INS-MET-HA');
        [$institutionIdB, $branchIdB] = $this->seedInstitutionAndBranch('INS-MET-HB');
        $adminA = $this->makeAdmin($institutionIdA, $branchIdA, 'metrics-history-a@test.local');
        $adminB = $this->makeAdmin($institutionIdB, $branchIdB, 'metrics-history-b@test.local');

        AuditLog::create([
            'user_id' => $adminA->id,
            'action' => 'member_import',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionIdA,
                'batch_key' => 'batch-A-1',
                'inserted' => 7,
                'updated' => 1,
                'skipped' => 0,
                'force_email_duplicate' => true,
            ],
            'created_at' => now()->subMinutes(6),
            'updated_at' => now()->subMinutes(6),
        ]);

        AuditLog::create([
            'user_id' => $adminA->id,
            'action' => 'member_import_undo',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionIdA,
                'batch_key' => 'batch-A-1',
                'undone_from_audit_id' => 99,
            ],
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        // Di luar rentang filter from/to.
        AuditLog::create([
            'user_id' => $adminA->id,
            'action' => 'member_import_undo',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionIdA,
                'batch_key' => 'batch-A-old',
                'inserted' => 1,
                'updated' => 1,
                'skipped' => 0,
            ],
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        // Institusi lain tidak boleh ikut diexport.
        AuditLog::create([
            'user_id' => $adminB->id,
            'action' => 'member_import',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionIdB,
                'batch_key' => 'batch-B-1',
                'inserted' => 100,
                'updated' => 50,
                'skipped' => 0,
                'force_email_duplicate' => true,
            ],
            'created_at' => now()->subMinutes(4),
            'updated_at' => now()->subMinutes(4),
        ]);

        $resp = $this->actingAs($adminA)->get(route('anggota.import.history', [
            'from' => now()->subDays(2)->toDateString(),
            'to' => now()->toDateString(),
            'action' => 'member_import',
            'limit' => 10,
        ]));
        $resp->assertOk();
        $resp->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $resp->streamedContent();
        $this->assertStringContainsString('institution_id,' . $institutionIdA, $csv);
        $this->assertStringContainsString('batch-A-1', $csv);
        $this->assertStringNotContainsString('batch-A-old', $csv);
        $this->assertStringNotContainsString('batch-B-1', $csv);
        $this->assertStringContainsString('member_import', $csv);
        $this->assertStringNotContainsString('member_import_undo', $csv);

        $xlsx = $this->actingAs($adminA)->get(route('anggota.import.history.xlsx', [
            'from' => now()->subDays(2)->toDateString(),
            'to' => now()->toDateString(),
            'action' => 'member_import',
            'limit' => 10,
        ]));
        $xlsx->assertOk();
        $xlsx->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_member_cannot_access_metrics_json_endpoint(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch('INS-MET-M');
        $member = User::create([
            'name' => 'Member Metrics',
            'email' => 'member-metrics@test.local',
            'password' => Hash::make('password'),
            'role' => 'member',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);

        $resp = $this->actingAs($member)->get(route('anggota.import.metrics'));
        $resp->assertRedirect(route('app'));

        $resp = $this->actingAs($member)->get(route('anggota.import.history'));
        $resp->assertRedirect(route('app'));

        $resp = $this->actingAs($member)->get(route('anggota.import.history.xlsx'));
        $resp->assertRedirect(route('app'));

        $resp = $this->actingAs($member)->get(route('anggota.import.metrics.chart'));
        $resp->assertRedirect(route('app'));

        $resp = $this->actingAs($member)->get(route('anggota.metrics.kpi'));
        $resp->assertRedirect(route('app'));
    }

    public function test_metrics_chart_json_endpoint_returns_series_for_selected_window(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch('INS-MET-CJ');
        $admin = $this->makeAdmin($institutionId, $branchId, 'metrics-chart@test.local');

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'member_import',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionId,
                'inserted' => 9,
                'updated' => 2,
                'skipped' => 0,
            ],
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'member_import_undo',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionId,
            ],
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        $resp = $this->actingAs($admin)->get(route('anggota.import.metrics.chart', ['window' => 7]));
        $resp->assertOk();
        $resp->assertJsonPath('ok', true);
        $resp->assertJsonPath('institution_id', $institutionId);
        $resp->assertJsonPath('window', 7);
        $resp->assertJsonCount(7, 'labels');
        $resp->assertJsonCount(7, 'series.import_runs');
        $resp->assertJsonCount(7, 'series.undo_runs');
        $resp->assertJsonCount(7, 'series.inserted');

        $json = $resp->json();
        $insertedValues = collect($json['series']['inserted'] ?? [])->pluck('value')->map(fn($v) => (int) $v)->all();
        $undoValues = collect($json['series']['undo_runs'] ?? [])->pluck('value')->map(fn($v) => (int) $v)->all();
        $this->assertContains(9, $insertedValues);
        $this->assertContains(1, $undoValues);
    }

    public function test_kpi_metrics_json_endpoint_returns_summary_and_sparklines(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch('INS-MET-KPI');
        $admin = $this->makeAdmin($institutionId, $branchId, 'metrics-kpi@test.local');

        DB::table('members')->insert([
            'institution_id' => $institutionId,
            'member_code' => 'MBR-KPI-01',
            'full_name' => 'Member KPI 1',
            'status' => 'active',
            'joined_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->actingAs($admin)->get(route('anggota.metrics.kpi'));
        $resp->assertOk();
        $resp->assertJsonPath('ok', true);
        $resp->assertJsonPath('institution_id', $institutionId);
        $resp->assertJsonStructure([
            'ok',
            'institution_id',
            'generated_at',
            'summary' => [
                'total',
                'active',
                'overdue',
                'unpaid',
                'sparklines' => [
                    'labels',
                    'total',
                    'active',
                    'overdue',
                    'unpaid',
                ],
            ],
        ]);
        $resp->assertJsonCount(7, 'summary.sparklines.labels');
        $resp->assertJsonCount(7, 'summary.sparklines.total');
        $resp->assertJsonCount(7, 'summary.sparklines.active');
        $resp->assertJsonCount(7, 'summary.sparklines.overdue');
        $resp->assertJsonCount(7, 'summary.sparklines.unpaid');
    }
}
