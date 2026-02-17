<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CirculationCompactUiModeTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitutionAndBranch(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Compact UI',
            'code' => 'INST-COMPACT-UI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-COMPACT',
            'name' => 'Cabang Compact',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeUser(string $role, int $institutionId, int $branchId, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Compact',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    private function writeSnapshot(string $date): void
    {
        $csv = implode("\n", [
            'snapshot_date,exception_type,severity,institution_id,branch_id,loan_id,loan_code,loan_item_id,item_id,barcode,member_id,member_code,detail,days_late,detected_at',
            $date . ',overdue_extreme,warning,1,1,10,L-EX-10,100,1000,BC-EX-10,2000,MBR-EX-10,Overdue aktif melebihi threshold,35,' . now()->toDateTimeString(),
        ]);
        Storage::disk('local')->put('reports/circulation-exceptions/circulation-exceptions-' . $date . '.csv', $csv);
    }

    public function test_exception_ops_always_uses_compact_mode_and_hides_full_toggle(): void
    {
        Storage::fake('local');
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $staff = $this->makeUser('staff', $institutionId, $branchId, 'staff-compact-ex@test.local');
        $date = now()->toDateString();
        $this->writeSnapshot($date);

        $resp = $this->actingAs($staff)->get(route('transaksi.exceptions.index', [
            'date' => $date,
            'mode' => 'full',
        ]));

        $resp->assertOk();
        $resp->assertSee('Monitoring Operasional Sirkulasi');
        $resp->assertSee('Mode ringkas aktif');
        $resp->assertSee('Terapkan');
        $resp->assertSee('Bersihkan');
        $resp->assertDontSee('Mode tampilan');
        $resp->assertDontSee('Lengkap');
    }

    public function test_denda_always_uses_compact_mode_and_hides_full_toggle(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $staff = $this->makeUser('staff', $institutionId, $branchId, 'staff-compact-fine@test.local');

        $resp = $this->actingAs($staff)->get(route('transaksi.denda.index', [
            'mode' => 'full',
        ]));

        $resp->assertOk();
        $resp->assertSee('Monitoring Operasional Denda');
        $resp->assertSee('Mode ringkas aktif');
        $resp->assertSee('Terapkan');
        $resp->assertSee('Bersihkan');
        $resp->assertDontSee('Mode tampilan');
        $resp->assertDontSee('Lengkap');
    }
}
