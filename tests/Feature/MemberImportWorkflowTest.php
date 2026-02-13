<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MemberImportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitutionAndBranch(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Test',
            'code' => 'INST-TST-IM',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-IM',
            'name' => 'Cabang Test',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeAdmin(int $institutionId, int $branchId): User
    {
        return User::create([
            'name' => 'Admin Import',
            'email' => 'admin-import@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
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

    private function currentPreviewToken(): string
    {
        $preview = session('member_import_preview');
        return (string) ($preview['confirm_token'] ?? '');
    }

    public function test_preview_detects_duplicate_email_and_phone(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);
        $this->ensureMembersEmailColumn();

        Member::create([
            'institution_id' => $institutionId,
            'member_code' => 'EX-001',
            'full_name' => 'Existing',
            'status' => 'active',
            'phone' => '08111',
            'email' => 'existing@test.local',
            'joined_at' => now()->toDateString(),
        ]);

        $csv = implode("\n", [
            'member_code,full_name,member_type,status,phone,email,joined_at,address',
            'M-001,Alpha,member,active,08111,existing@test.local,2026-02-01,Addr 1',
            'M-002,Beta,member,active,08222,beta@test.local,2026-02-01,Addr 2',
            'M-003,Gamma,member,active,08222,gamma@test.local,2026-02-01,Addr 3',
        ]);

        $resp = $this->actingAs($admin)->post(route('anggota.import.preview'), [
            'csv_file' => $this->csvUpload($csv),
        ]);

        $resp->assertRedirect(route('anggota.index'));
        $resp->assertSessionHas('member_import_preview');

        $preview = session('member_import_preview');
        $this->assertIsArray($preview);
        $this->assertSame(1, (int) ($preview['summary']['duplicate_email_rows'] ?? 0));
        $this->assertSame(3, (int) ($preview['summary']['duplicate_phone_rows'] ?? 0));
    }

    public function test_confirm_is_hard_blocked_on_duplicate_email_without_override(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);
        $this->ensureMembersEmailColumn();

        Member::create([
            'institution_id' => $institutionId,
            'member_code' => 'EX-002',
            'full_name' => 'Existing',
            'status' => 'active',
            'phone' => '08000',
            'email' => 'dup@test.local',
            'joined_at' => now()->toDateString(),
        ]);

        $csv = implode("\n", [
            'member_code,full_name,member_type,status,phone,email,joined_at,address',
            'M-010,Alpha,member,active,08100,dup@test.local,2026-02-01,Addr',
        ]);

        $this->actingAs($admin)->post(route('anggota.import.preview'), [
            'csv_file' => $this->csvUpload($csv),
        ])->assertRedirect(route('anggota.index'));

        $resp = $this->actingAs($admin)->post(route('anggota.import.confirm'), [
            'confirm_token' => $this->currentPreviewToken(),
        ]);
        $resp->assertRedirect(route('anggota.index'));
        $resp->assertSessionHasErrors('csv_file');

        $this->assertDatabaseMissing('members', [
            'institution_id' => $institutionId,
            'member_code' => 'M-010',
        ]);
    }

    public function test_admin_can_override_duplicate_email_and_confirm_import(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);
        $this->ensureMembersEmailColumn();

        Member::create([
            'institution_id' => $institutionId,
            'member_code' => 'EX-003',
            'full_name' => 'Existing',
            'status' => 'active',
            'phone' => '08000',
            'email' => 'override@test.local',
            'joined_at' => now()->toDateString(),
        ]);

        $csv = implode("\n", [
            'member_code,full_name,member_type,status,phone,email,joined_at,address',
            'M-020,Alpha,member,active,08100,override@test.local,2026-02-01,Addr',
        ]);

        $this->actingAs($admin)->post(route('anggota.import.preview'), [
            'csv_file' => $this->csvUpload($csv),
        ])->assertRedirect(route('anggota.index'));

        $resp = $this->actingAs($admin)->post(route('anggota.import.confirm'), [
            'confirm_token' => $this->currentPreviewToken(),
            'force_email_duplicate' => '1',
        ]);
        $resp->assertRedirect(route('anggota.index'));
        $resp->assertSessionHasNoErrors();

        $this->assertDatabaseHas('members', [
            'institution_id' => $institutionId,
            'member_code' => 'M-020',
            'full_name' => 'Alpha',
        ]);
    }

    public function test_error_csv_and_summary_csv_can_be_downloaded_after_preview(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);
        $this->ensureMembersEmailColumn();

        $csv = implode("\n", [
            'member_code,full_name,member_type,status,phone,email,joined_at,address',
            'M-030,Alpha,member,active,08100,alpha@test.local,2026-02-01,Addr',
            ',MissingCode,member,active,08101,missing@test.local,2026-02-01,Addr',
            'M-031,BadDate,member,active,08102,bad@test.local,not-a-date,Addr',
        ]);

        $this->actingAs($admin)->post(route('anggota.import.preview'), [
            'csv_file' => $this->csvUpload($csv),
        ])->assertRedirect(route('anggota.index'));

        $errResp = $this->actingAs($admin)->get(route('anggota.import.errors'));
        $errResp->assertOk();
        $errResp->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $errContent = $errResp->streamedContent();
        $this->assertStringContainsString('member_code', $errContent);
        $this->assertStringContainsString('reason', $errContent);

        $sumResp = $this->actingAs($admin)->get(route('anggota.import.summary'));
        $sumResp->assertOk();
        $sumResp->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $sumContent = $sumResp->streamedContent();
        $this->assertStringContainsString('db_action', $sumContent);
        $this->assertStringContainsString('is_error', $sumContent);
    }

    public function test_undo_import_batch_reverts_insert_and_update(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);
        $this->ensureMembersEmailColumn();

        Member::create([
            'institution_id' => $institutionId,
            'member_code' => 'EX-UNDO',
            'full_name' => 'Before Name',
            'status' => 'active',
            'phone' => '08199',
            'email' => 'before@test.local',
            'joined_at' => now()->toDateString(),
        ]);

        $csv = implode("\n", [
            'member_code,full_name,member_type,status,phone,email,joined_at,address',
            'EX-UNDO,After Name,member,inactive,08199,after@test.local,2026-02-01,Addr',
            'M-UNDO-NEW,New Member,member,active,08200,new@test.local,2026-02-01,Addr',
        ]);

        $this->actingAs($admin)->post(route('anggota.import.preview'), [
            'csv_file' => $this->csvUpload($csv),
        ])->assertRedirect(route('anggota.index'));

        $this->actingAs($admin)->post(route('anggota.import.confirm'), [
            'confirm_token' => $this->currentPreviewToken(),
        ])
            ->assertRedirect(route('anggota.index'));

        $this->assertDatabaseHas('members', [
            'institution_id' => $institutionId,
            'member_code' => 'EX-UNDO',
            'full_name' => 'After Name',
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('members', [
            'institution_id' => $institutionId,
            'member_code' => 'M-UNDO-NEW',
            'full_name' => 'New Member',
        ]);

        $this->actingAs($admin)->post(route('anggota.import.undo'))
            ->assertRedirect(route('anggota.index'));

        $this->assertDatabaseHas('members', [
            'institution_id' => $institutionId,
            'member_code' => 'EX-UNDO',
            'full_name' => 'Before Name',
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('members', [
            'institution_id' => $institutionId,
            'member_code' => 'M-UNDO-NEW',
        ]);
    }

    public function test_confirm_token_is_idempotent_and_cannot_be_reused(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);
        $this->ensureMembersEmailColumn();

        $csv = implode("\n", [
            'member_code,full_name,member_type,status,phone,email,joined_at,address',
            'M-IDEMP-1,Alpha,member,active,08100,idemp1@test.local,2026-02-01,Addr',
        ]);

        $this->actingAs($admin)->post(route('anggota.import.preview'), [
            'csv_file' => $this->csvUpload($csv),
        ])->assertRedirect(route('anggota.index'));

        $token = $this->currentPreviewToken();
        $this->assertNotSame('', $token);

        $this->actingAs($admin)->post(route('anggota.import.confirm'), [
            'confirm_token' => $token,
        ])->assertRedirect(route('anggota.index'));

        $second = $this->actingAs($admin)->post(route('anggota.import.confirm'), [
            'confirm_token' => $token,
        ]);
        $second->assertRedirect(route('anggota.index'));
        $second->assertSessionHasErrors('csv_file');
    }

    public function test_undo_is_rejected_for_import_batch_older_than_24_hours(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeAdmin($institutionId, $branchId);
        $this->ensureMembersEmailColumn();

        $csv = implode("\n", [
            'member_code,full_name,member_type,status,phone,email,joined_at,address',
            'M-OLD-1,Alpha,member,active,08100,old1@test.local,2026-02-01,Addr',
        ]);

        $this->actingAs($admin)->post(route('anggota.import.preview'), [
            'csv_file' => $this->csvUpload($csv),
        ])->assertRedirect(route('anggota.index'));

        $this->actingAs($admin)->post(route('anggota.import.confirm'), [
            'confirm_token' => $this->currentPreviewToken(),
        ])->assertRedirect(route('anggota.index'));

        DB::table('audit_logs')
            ->where('action', 'member_import')
            ->update([
                'created_at' => Carbon::now()->subHours(25),
                'updated_at' => Carbon::now()->subHours(25),
            ]);

        $resp = $this->actingAs($admin)->post(route('anggota.import.undo'));
        $resp->assertRedirect(route('anggota.index'));
        $resp->assertSessionHas('error');
    }

}
