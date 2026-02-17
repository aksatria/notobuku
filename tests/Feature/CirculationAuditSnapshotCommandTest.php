<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CirculationAuditSnapshotCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_command_writes_monthly_circulation_csv_to_storage(): void
    {
        Storage::fake('local');

        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Cir Snapshot',
            'code' => 'INS-CIR-SNAP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-CIR-SNAP',
            'name' => 'Cabang Cir Snapshot',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::create([
            'name' => 'Cir Snapshot Admin',
            'email' => 'cir-snapshot-admin@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);

        $memberId = DB::table('members')->insertGetId([
            'institution_id' => $institutionId,
            'member_code' => 'MBR-CIR-SNAP',
            'full_name' => 'Member Cir Snapshot',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblioId = DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'Buku Snapshot',
            'material_type' => 'book',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemId = DB::table('items')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'biblio_id' => $biblioId,
            'barcode' => 'BC-CIR-SNAP-01',
            'accession_number' => 'ACC-CIR-SNAP-01',
            'status' => 'borrowed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $targetMonth = now()->subMonthNoOverflow()->format('Y-m');
        $borrowedAt = now()->subMonthNoOverflow()->startOfMonth()->addDays(2)->setTime(10, 0);

        $loanId = DB::table('loans')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'member_id' => $memberId,
            'loan_code' => 'L-CIR-SNAP-01',
            'status' => 'closed',
            'loaned_at' => $borrowedAt,
            'due_at' => $borrowedAt->copy()->addDays(7),
            'closed_at' => $borrowedAt->copy()->addDays(6),
            'created_by' => $admin->id,
            'created_at' => $borrowedAt,
            'updated_at' => $borrowedAt,
        ]);

        DB::table('loan_items')->insert([
            'loan_id' => $loanId,
            'item_id' => $itemId,
            'status' => 'returned',
            'borrowed_at' => $borrowedAt,
            'due_at' => $borrowedAt->copy()->addDays(7),
            'returned_at' => $borrowedAt->copy()->addDays(6),
            'created_at' => $borrowedAt,
            'updated_at' => $borrowedAt,
        ]);

        $this->artisan('notobuku:circulation-audit-snapshot', ['--month' => $targetMonth])->assertExitCode(0);

        $file = 'reports/circulation-audit-snapshots/circulation-audit-' . $targetMonth . '.csv';
        Storage::disk('local')->assertExists($file);

        $content = Storage::disk('local')->get($file);
        $this->assertStringContainsString('L-CIR-SNAP-01', $content);
        $this->assertStringContainsString('BC-CIR-SNAP-01', $content);
    }
}
