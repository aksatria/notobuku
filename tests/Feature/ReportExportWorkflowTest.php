<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportExportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitutionAndBranch(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Report',
            'code' => 'INST-RPT-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-RPT',
            'name' => 'Cabang Report',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeAdmin(int $institutionId, int $branchId): User
    {
        return User::create([
            'name' => 'Admin Report',
            'email' => 'admin-report@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    public function test_reports_xlsx_export_endpoint_returns_xlsx(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);

        $resp = $this->actingAs($admin)->get(route('laporan.export_xlsx', [
            'type' => 'sirkulasi',
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
            'branch_id' => 0,
        ]));

        $resp->assertOk();
        $resp->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_reports_csv_export_endpoint_returns_csv(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);

        $resp = $this->actingAs($admin)->get(route('laporan.export', [
            'type' => 'sirkulasi',
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
            'branch_id' => 0,
        ]));

        $resp->assertOk();
        $contentType = (string) $resp->headers->get('content-type', '');
        $this->assertStringContainsString('text/csv', strtolower($contentType));
    }

    public function test_reports_csv_export_supports_member_and_serial_types(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);

        DB::table('members')->insert([
            'institution_id' => $institutionId,
            'member_code' => 'MBR-RPT-01',
            'full_name' => 'Member Report',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblioId = DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'Serial Report',
            'material_type' => 'serial',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('serial_issues')->insert([
            'institution_id' => $institutionId,
            'biblio_id' => $biblioId,
            'branch_id' => $branchId,
            'issue_code' => 'SER-RPT-01',
            'status' => 'expected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $memberCsv = $this->actingAs($admin)->get(route('laporan.export', [
            'type' => 'anggota',
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
            'branch_id' => 0,
        ]));
        $memberCsv->assertOk();
        $this->assertStringContainsString('text/csv', strtolower((string) $memberCsv->headers->get('content-type', '')));

        $serialXlsx = $this->actingAs($admin)->get(route('laporan.export_xlsx', [
            'type' => 'serial',
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
            'branch_id' => 0,
        ]));
        $serialXlsx->assertOk();
        $serialXlsx->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
