<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IlsCoreE2EFlowTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitutionAndBranch(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst E2E',
            'code' => 'INST-E2E-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-E2E',
            'name' => 'Cabang E2E',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeAdmin(int $institutionId, int $branchId): User
    {
        return User::create([
            'name' => 'Admin E2E',
            'email' => 'admin-e2e@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    public function test_e2e_core_modules_catalog_circulation_report_serial(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);

        $memberId = DB::table('members')->insertGetId([
            'institution_id' => $institutionId,
            'member_code' => 'MBR-E2E-01',
            'full_name' => 'Member E2E',
            'status' => 'active',
            'joined_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblioId = DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'Judul E2E Integrasi',
            'material_type' => 'serial',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemId = DB::table('items')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'biblio_id' => $biblioId,
            'barcode' => 'BC-E2E-0001',
            'accession_number' => 'ACC-E2E-0001',
            'status' => 'borrowed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $loanId = DB::table('loans')->insertGetId([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'member_id' => $memberId,
            'loan_code' => 'L-E2E-0001',
            'status' => 'open',
            'loaned_at' => now()->subDays(2),
            'due_at' => now()->addDays(5),
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $loanItemId = DB::table('loan_items')->insertGetId([
            'loan_id' => $loanId,
            'item_id' => $itemId,
            'status' => 'borrowed',
            'borrowed_at' => now()->subDays(2),
            'due_at' => now()->addDays(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('fines')->insert([
            'institution_id' => $institutionId,
            'member_id' => $memberId,
            'loan_item_id' => $loanItemId,
            'status' => 'unpaid',
            'days_late' => 1,
            'rate' => 1000,
            'amount' => 1000,
            'assessed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1) Katalog
        $catalogShow = $this->actingAs($admin)->get(route('katalog.show', $biblioId));
        $catalogShow->assertOk();
        $catalogShow->assertSee('Judul E2E Integrasi');

        // 2) Sirkulasi
        $riwayat = $this->actingAs($admin)->get(route('transaksi.riwayat', ['tab' => 'transaksi']));
        $riwayat->assertOk();
        $riwayat->assertSee('L-E2E-0001');

        // 3) Laporan
        $report = $this->actingAs($admin)->get(route('laporan.index', [
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
            'branch_id' => $branchId,
        ]));
        $report->assertOk();
        $report->assertSee('Laporan Operasional');
        $report->assertSee('Ringkasan Serial');
        $report->assertSee('Ringkasan Anggota');

        $reportCsv = $this->actingAs($admin)->get(route('laporan.export', [
            'type' => 'serial',
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
            'branch_id' => $branchId,
        ]));
        $reportCsv->assertOk();

        // 4) Serial workflow
        $this->actingAs($admin)->post(route('serial_issues.store'), [
            'biblio_id' => $biblioId,
            'branch_id' => $branchId,
            'issue_code' => 'SER-E2E-2026-01',
            'volume' => 'Vol 1',
            'issue_no' => 'No 1',
            'published_on' => now()->toDateString(),
            'expected_on' => now()->toDateString(),
            'notes' => 'Issue E2E',
        ])->assertRedirect(route('serial_issues.index'));

        $issueId = (int) DB::table('serial_issues')
            ->where('institution_id', $institutionId)
            ->where('issue_code', 'SER-E2E-2026-01')
            ->value('id');

        $this->assertGreaterThan(0, $issueId);

        $this->actingAs($admin)->post(route('serial_issues.claim', $issueId), [
            'claim_reference' => 'CLM-E2E-01',
            'claim_notes' => 'Claim flow E2E',
        ])->assertRedirect(route('serial_issues.index'));

        $this->assertDatabaseHas('serial_issues', [
            'id' => $issueId,
            'status' => 'claimed',
            'claim_reference' => 'CLM-E2E-01',
        ]);

        $this->actingAs($admin)->post(route('serial_issues.receive', $issueId))
            ->assertRedirect(route('serial_issues.index'));

        $this->assertDatabaseHas('serial_issues', [
            'id' => $issueId,
            'status' => 'received',
        ]);

        $this->actingAs($admin)->get(route('serial_issues.export.csv', [
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
            'branch_id' => $branchId,
        ]))->assertOk();
    }
}

