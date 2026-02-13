<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberImportSnapshotCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_command_writes_monthly_csv_to_storage(): void
    {
        Storage::fake('local');

        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Snapshot',
            'code' => 'INS-SNAP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-SNAP',
            'name' => 'Cabang Snapshot',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::create([
            'name' => 'Snapshot Admin',
            'email' => 'snapshot-admin@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);

        $targetMonth = now()->subMonthNoOverflow()->format('Y-m');
        $createdAt = now()->subMonthNoOverflow()->startOfMonth()->addDays(1);
        DB::table('audit_logs')->insert([
            'user_id' => $admin->id,
            'action' => 'member_import',
            'format' => 'csv',
            'status' => 'success',
            'meta' => json_encode([
                'institution_id' => $institutionId,
                'batch_key' => 'batch-snap-1',
                'inserted' => 6,
                'updated' => 1,
                'skipped' => 0,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $this->artisan('notobuku:member-import-snapshot', ['--month' => $targetMonth])->assertExitCode(0);

        $file = 'reports/member-import-snapshots/member-import-history-' . $targetMonth . '.csv';
        Storage::disk('local')->assertExists($file);

        $content = Storage::disk('local')->get($file);
        $this->assertStringContainsString('batch-snap-1', $content);
        $this->assertStringContainsString('member_import', $content);
    }
}
