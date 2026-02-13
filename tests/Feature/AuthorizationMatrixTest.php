<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitutionAndBranch(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Auth',
            'code' => 'INST-AUTH-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-AUTH',
            'name' => 'Cabang Auth',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeUser(string $role, int $institutionId, int $branchId, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    private function ensureMembersEmailColumn(): void
    {
        if (!Schema::hasColumn('members', 'email')) {
            Schema::table('members', function (Blueprint $table) {
                $table->string('email')->nullable()->after('phone');
                $table->index('email');
            });
        }
    }

    private function csvUpload(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('members.csv', $content);
    }

    private function duplicateEmailCsv(): string
    {
        return implode("\n", [
            'member_code,full_name,member_type,status,phone,email,joined_at,address',
            'M-901,Alpha,member,active,08100,dup-auth@test.local,2026-02-01,Addr',
        ]);
    }

    private function currentPreviewToken(): string
    {
        $preview = session('member_import_preview');
        return (string) ($preview['confirm_token'] ?? '');
    }

    public function test_staff_can_override_duplicate_email_in_member_import_confirm(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $this->ensureMembersEmailColumn();

        $staff = $this->makeUser('staff', $institutionId, $branchId, 'staff-auth@test.local');
        Member::create([
            'institution_id' => $institutionId,
            'member_code' => 'EX-AUTH-STAFF',
            'full_name' => 'Existing Staff',
            'status' => 'active',
            'email' => 'dup-auth@test.local',
            'joined_at' => now()->toDateString(),
        ]);

        $this->actingAs($staff)->post(route('anggota.import.preview'), [
            'csv_file' => $this->csvUpload($this->duplicateEmailCsv()),
        ])->assertRedirect(route('anggota.index'));

        $this->actingAs($staff)->post(route('anggota.import.confirm'), [
            'confirm_token' => $this->currentPreviewToken(),
            'force_email_duplicate' => '1',
        ])->assertRedirect(route('anggota.index'));

        $this->assertDatabaseHas('members', [
            'institution_id' => $institutionId,
            'member_code' => 'M-901',
            'full_name' => 'Alpha',
        ]);
    }

    public function test_admin_can_override_duplicate_email_in_member_import_confirm(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $this->ensureMembersEmailColumn();

        $admin = $this->makeUser('admin', $institutionId, $branchId, 'admin-auth@test.local');
        Member::create([
            'institution_id' => $institutionId,
            'member_code' => 'EX-AUTH-ADMIN',
            'full_name' => 'Existing Admin',
            'status' => 'active',
            'email' => 'dup-auth@test.local',
            'joined_at' => now()->toDateString(),
        ]);

        $this->actingAs($admin)->post(route('anggota.import.preview'), [
            'csv_file' => $this->csvUpload($this->duplicateEmailCsv()),
        ])->assertRedirect(route('anggota.index'));

        $this->actingAs($admin)->post(route('anggota.import.confirm'), [
            'confirm_token' => $this->currentPreviewToken(),
            'force_email_duplicate' => '1',
        ])->assertRedirect(route('anggota.index'));

        $this->assertDatabaseHas('members', [
            'institution_id' => $institutionId,
            'member_code' => 'M-901',
            'full_name' => 'Alpha',
        ]);
    }

    public function test_member_cannot_access_import_report_and_serial_claim_endpoints(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $this->ensureMembersEmailColumn();
        $member = $this->makeUser('member', $institutionId, $branchId, 'member-auth@test.local');

        $csv = implode("\n", [
            'member_code,full_name,member_type,status,phone,email,joined_at,address',
            'M-999,Blocked User,member,active,08100,blocked@test.local,2026-02-01,Addr',
        ]);

        $this->actingAs($member)->post(route('anggota.import.preview'), [
            'csv_file' => $this->csvUpload($csv),
        ])->assertRedirect(route('app'));

        $this->actingAs($member)->post(route('anggota.import.confirm'), [
            'force_email_duplicate' => '1',
        ])->assertRedirect(route('app'));

        $this->actingAs($member)->get(route('anggota.import.history'))
            ->assertRedirect(route('app'));
        $this->actingAs($member)->get(route('anggota.import.history.xlsx'))
            ->assertRedirect(route('app'));

        $this->actingAs($member)->get(route('laporan.export_xlsx', [
            'type' => 'sirkulasi',
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
            'branch_id' => 0,
        ]))->assertRedirect(route('app'));

        $this->actingAs($member)->post(route('serial_issues.claim', 1), [
            'claim_reference' => 'CLM-BLOCK',
            'claim_notes' => 'Blocked',
        ])->assertRedirect(route('app'));
    }
}
