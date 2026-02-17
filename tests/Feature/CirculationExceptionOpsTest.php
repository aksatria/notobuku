<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CirculationExceptionOpsTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitutionAndBranch(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Exception Ops',
            'code' => 'INST-EX-OPS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-EX-OPS',
            'name' => 'Cabang Exception Ops',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeUser(string $role, int $institutionId, int $branchId, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Exception',
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
            $date . ',branch_mismatch_active_loan,critical,1,1,11,L-EX-11,101,1001,BC-EX-11,2001,MBR-EX-11,loan.branch_id=1 item.branch_id=2,0,' . now()->toDateTimeString(),
        ]);
        Storage::disk('local')->put('reports/circulation-exceptions/circulation-exceptions-' . $date . '.csv', $csv);
    }

    private function fingerprintForSample(string $date): string
    {
        return sha1(implode('|', [
            $date,
            'overdue_extreme',
            '10',
            '100',
            '1000',
            'BC-EX-10',
            '2000',
            'Overdue aktif melebihi threshold',
        ]));
    }

    private function fingerprintForSampleTwo(string $date): string
    {
        return sha1(implode('|', [
            $date,
            'branch_mismatch_active_loan',
            '11',
            '101',
            '1001',
            'BC-EX-11',
            '2001',
            'loan.branch_id=1 item.branch_id=2',
        ]));
    }

    public function test_admin_can_view_exception_panel_and_ack_then_resolve_item(): void
    {
        Storage::fake('local');
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeUser('admin', $institutionId, $branchId, 'admin-exops@test.local');

        $date = now()->toDateString();
        $this->writeSnapshot($date);

        $index = $this->actingAs($admin)->get(route('transaksi.exceptions.index', ['date' => $date]));
        $index->assertOk();
        $index->assertSee('Monitoring Operasional Sirkulasi');
        $index->assertSee('overdue_extreme');

        $fingerprint = $this->fingerprintForSample($date);

        $this->actingAs($admin)->post(route('transaksi.exceptions.ack'), [
            'snapshot_date' => $date,
            'fingerprint' => $fingerprint,
            'exception_type' => 'overdue_extreme',
            'severity' => 'warning',
            'loan_id' => 10,
            'loan_item_id' => 100,
            'item_id' => 1000,
            'barcode' => 'BC-EX-10',
            'member_id' => 2000,
            'detail' => 'Overdue aktif melebihi threshold',
            'ack_note' => 'Diterima petugas shift pagi.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('circulation_exception_acknowledgements', [
            'institution_id' => $institutionId,
            'snapshot_date' => $date,
            'fingerprint' => $fingerprint,
            'status' => 'ack',
        ]);

        $this->actingAs($admin)->post(route('transaksi.exceptions.resolve'), [
            'snapshot_date' => $date,
            'fingerprint' => $fingerprint,
            'exception_type' => 'overdue_extreme',
            'severity' => 'warning',
            'loan_id' => 10,
            'loan_item_id' => 100,
            'item_id' => 1000,
            'barcode' => 'BC-EX-10',
            'member_id' => 2000,
            'detail' => 'Overdue aktif melebihi threshold',
            'ack_note' => 'Kasus selesai setelah follow-up.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('circulation_exception_acknowledgements', [
            'institution_id' => $institutionId,
            'snapshot_date' => $date,
            'fingerprint' => $fingerprint,
            'status' => 'resolved',
        ]);
    }

    public function test_member_cannot_access_exception_ops_endpoints(): void
    {
        Storage::fake('local');
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $member = $this->makeUser('member', $institutionId, $branchId, 'member-exops@test.local');

        $date = now()->toDateString();
        $this->writeSnapshot($date);
        $fingerprint = $this->fingerprintForSample($date);

        $this->actingAs($member)->get(route('transaksi.exceptions.index'))
            ->assertRedirect(route('app'));
        $this->actingAs($member)->post(route('transaksi.exceptions.ack'), [
            'snapshot_date' => $date,
            'fingerprint' => $fingerprint,
        ])->assertRedirect(route('app'));
    }

    public function test_admin_can_bulk_acknowledge_selected_items(): void
    {
        Storage::fake('local');
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeUser('admin', $institutionId, $branchId, 'admin-exops-bulk@test.local');

        $date = now()->toDateString();
        $this->writeSnapshot($date);
        $fp1 = $this->fingerprintForSample($date);
        $fp2 = $this->fingerprintForSampleTwo($date);

        $this->actingAs($admin)->post(route('transaksi.exceptions.bulk'), [
            'snapshot_date' => $date,
            'bulk_action' => 'ack',
            'ack_note' => 'Bulk ack oleh supervisor',
            'fingerprints' => [$fp1, $fp2],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('circulation_exception_acknowledgements', [
            'institution_id' => $institutionId,
            'snapshot_date' => $date,
            'fingerprint' => $fp1,
            'status' => 'ack',
        ]);
        $this->assertDatabaseHas('circulation_exception_acknowledgements', [
            'institution_id' => $institutionId,
            'snapshot_date' => $date,
            'fingerprint' => $fp2,
            'status' => 'ack',
        ]);
    }

    public function test_staff_can_export_exception_csv_with_active_filters(): void
    {
        Storage::fake('local');
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $staff = $this->makeUser('staff', $institutionId, $branchId, 'staff-exops-export@test.local');

        $date = now()->toDateString();
        $this->writeSnapshot($date);

        $resp = $this->actingAs($staff)->get(route('transaksi.exceptions.export.csv', [
            'date' => $date,
            'type' => 'overdue_extreme',
            'severity' => 'warning',
            'status' => '',
            'q' => 'Overdue aktif',
        ]));

        $resp->assertOk();
        $this->assertStringContainsString('text/csv', strtolower((string) $resp->headers->get('content-type', '')));
        $csv = $resp->streamedContent();
        $this->assertStringContainsString('overdue_extreme', $csv);
        $this->assertStringContainsString('BC-EX-10', $csv);
    }

    public function test_member_cannot_export_exception_csv(): void
    {
        Storage::fake('local');
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $member = $this->makeUser('member', $institutionId, $branchId, 'member-exops-export@test.local');

        $date = now()->toDateString();
        $this->writeSnapshot($date);

        $this->actingAs($member)
            ->get(route('transaksi.exceptions.export.csv', ['date' => $date]))
            ->assertRedirect(route('app'));
    }

    public function test_member_cannot_export_xlsx_or_assign_owner(): void
    {
        Storage::fake('local');
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $member = $this->makeUser('member', $institutionId, $branchId, 'member-exops-owner@test.local');

        $date = now()->toDateString();
        $this->writeSnapshot($date);
        $fingerprint = $this->fingerprintForSample($date);

        $this->actingAs($member)
            ->get(route('transaksi.exceptions.export.xlsx', ['date' => $date]))
            ->assertRedirect(route('app'));

        $this->actingAs($member)->post(route('transaksi.exceptions.assign_owner'), [
            'snapshot_date' => $date,
            'fingerprint' => $fingerprint,
            'owner_user_id' => 1,
            'exception_type' => 'overdue_extreme',
            'severity' => 'warning',
        ])->assertRedirect(route('app'));

        $this->actingAs($member)->post(route('transaksi.exceptions.bulk_assign_owner'), [
            'snapshot_date' => $date,
            'owner_user_id' => 1,
            'fingerprints' => [$fingerprint],
        ])->assertRedirect(route('app'));
    }

    public function test_staff_can_export_exception_xlsx(): void
    {
        Storage::fake('local');
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $staff = $this->makeUser('staff', $institutionId, $branchId, 'staff-exops-export-xlsx@test.local');

        $date = now()->toDateString();
        $this->writeSnapshot($date);

        $resp = $this->actingAs($staff)->get(route('transaksi.exceptions.export.xlsx', [
            'date' => $date,
            'type' => '',
            'severity' => '',
            'status' => '',
            'q' => '',
        ]));

        $resp->assertOk();
        $resp->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_admin_can_assign_owner_pic_for_exception(): void
    {
        Storage::fake('local');
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeUser('admin', $institutionId, $branchId, 'admin-exops-owner@test.local');
        $staffOwner = $this->makeUser('staff', $institutionId, $branchId, 'staff-owner@test.local');

        $date = now()->toDateString();
        $this->writeSnapshot($date);
        $fingerprint = $this->fingerprintForSample($date);

        $this->actingAs($admin)->post(route('transaksi.exceptions.assign_owner'), [
            'snapshot_date' => $date,
            'fingerprint' => $fingerprint,
            'owner_user_id' => $staffOwner->id,
            'exception_type' => 'overdue_extreme',
            'severity' => 'warning',
            'loan_id' => 10,
            'loan_item_id' => 100,
            'item_id' => 1000,
            'barcode' => 'BC-EX-10',
            'member_id' => 2000,
            'detail' => 'Overdue aktif melebihi threshold',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('circulation_exception_acknowledgements', [
            'institution_id' => $institutionId,
            'snapshot_date' => $date,
            'fingerprint' => $fingerprint,
            'owner_user_id' => $staffOwner->id,
        ]);
    }

    public function test_admin_can_bulk_assign_owner_pic(): void
    {
        Storage::fake('local');
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeUser('admin', $institutionId, $branchId, 'admin-exops-bulk-owner@test.local');
        $staffOwner = $this->makeUser('staff', $institutionId, $branchId, 'staff-bulk-owner@test.local');

        $date = now()->toDateString();
        $this->writeSnapshot($date);
        $fp1 = $this->fingerprintForSample($date);
        $fp2 = $this->fingerprintForSampleTwo($date);

        $this->actingAs($admin)->post(route('transaksi.exceptions.bulk_assign_owner'), [
            'snapshot_date' => $date,
            'owner_user_id' => $staffOwner->id,
            'fingerprints' => [$fp1, $fp2],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('circulation_exception_acknowledgements', [
            'institution_id' => $institutionId,
            'snapshot_date' => $date,
            'fingerprint' => $fp1,
            'owner_user_id' => $staffOwner->id,
        ]);
        $this->assertDatabaseHas('circulation_exception_acknowledgements', [
            'institution_id' => $institutionId,
            'snapshot_date' => $date,
            'fingerprint' => $fp2,
            'owner_user_id' => $staffOwner->id,
        ]);
    }
}
