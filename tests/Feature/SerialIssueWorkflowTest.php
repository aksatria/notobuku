<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SerialIssueWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitutionAndBranch(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Serial',
            'code' => 'INST-SER-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-SER',
            'name' => 'Cabang Serial',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeAdmin(int $institutionId, int $branchId): User
    {
        return User::create([
            'name' => 'Admin Serial',
            'email' => 'admin-serial@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    private function makeSerialBiblio(int $institutionId): int
    {
        return DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'Jurnal Uji Serial',
            'material_type' => 'serial',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_claim_receive_missing_workflow_updates_status_and_fields(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);
        $biblioId = $this->makeSerialBiblio($institutionId);

        $issueId = DB::table('serial_issues')->insertGetId([
            'institution_id' => $institutionId,
            'biblio_id' => $biblioId,
            'branch_id' => $branchId,
            'issue_code' => '2026-V1-N1',
            'status' => 'expected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('serial_issues.claim', $issueId), [
            'claim_reference' => 'CLM-001',
            'claim_notes' => 'Klaim ke vendor',
        ])->assertRedirect(route('serial_issues.index'));

        $this->assertDatabaseHas('serial_issues', [
            'id' => $issueId,
            'status' => 'claimed',
            'claim_reference' => 'CLM-001',
            'claim_notes' => 'Klaim ke vendor',
            'claimed_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('serial_issues.receive', $issueId))
            ->assertRedirect(route('serial_issues.index'));

        $this->assertDatabaseHas('serial_issues', [
            'id' => $issueId,
            'status' => 'received',
            'received_by' => $admin->id,
            'claim_reference' => null,
            'claim_notes' => null,
            'claimed_by' => null,
        ]);

        $this->actingAs($admin)->post(route('serial_issues.missing', $issueId))
            ->assertRedirect(route('serial_issues.index'));

        $this->assertDatabaseHas('serial_issues', [
            'id' => $issueId,
            'status' => 'missing',
        ]);
    }

    public function test_index_is_safe_when_serial_issues_table_missing(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);

        Schema::dropIfExists('serial_issues');

        $resp = $this->actingAs($admin)->get(route('serial_issues.index'));
        $resp->assertOk();
        $resp->assertSee('Tabel <code>serial_issues</code> belum tersedia', false);
    }

    public function test_serial_issue_export_csv_and_xlsx_are_available(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);
        $biblioId = $this->makeSerialBiblio($institutionId);

        DB::table('serial_issues')->insert([
            'institution_id' => $institutionId,
            'biblio_id' => $biblioId,
            'branch_id' => $branchId,
            'issue_code' => '2026-V1-N2',
            'status' => 'expected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $csv = $this->actingAs($admin)->get(route('serial_issues.export.csv', [
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
            'branch_id' => $branchId,
        ]));
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', strtolower((string) $csv->headers->get('content-type', '')));

        $xlsx = $this->actingAs($admin)->get(route('serial_issues.export.xlsx', [
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
            'branch_id' => $branchId,
        ]));
        $xlsx->assertOk();
        $xlsx->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
