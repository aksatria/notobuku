<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReservationActiveInstitutionContextTest extends TestCase
{
    use RefreshDatabase;

    private function createInstitution(string $name, string $code): int
    {
        return (int) DB::table('institutions')->insertGetId([
            'name' => $name,
            'code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeUser(string $role, int $institutionId, string $email): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'institution_id' => $institutionId,
        ]);
    }

    private function setActiveInstitution(User $user, int $institutionId): void
    {
        $hasActiveInstitutionId = Schema::hasColumn('users', 'active_institution_id');
        $hasActiveInstId = Schema::hasColumn('users', 'active_inst_id');

        $update = [];
        if ($hasActiveInstitutionId) {
            $update['active_institution_id'] = $institutionId;
        }
        if ($hasActiveInstId) {
            $update['active_inst_id'] = $institutionId;
        }
        if (!$hasActiveInstitutionId && !$hasActiveInstId) {
            // Fallback environment lama: pakai institution_id tetap mem-validasi resolver institusi.
            $update['institution_id'] = $institutionId;
        }

        DB::table('users')->where('id', $user->id)->update($update);
        $user->refresh();
    }

    public function test_reservation_policy_store_uses_active_institution_context(): void
    {
        $instA = $this->createInstitution('Inst A', 'INST-ACT-01');
        $instB = $this->createInstitution('Inst B', 'INST-ACT-02');
        $admin = $this->makeUser('admin', $instA, 'admin-active-inst@test.local');
        $this->setActiveInstitution($admin, $instB);

        $response = $this->actingAs($admin)->post(route('reservasi.rules.store'), [
            'label' => 'Rule Active Inst',
            'max_active_reservations' => 3,
            'max_queue_per_biblio' => 20,
            'hold_hours' => 24,
            'is_enabled' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('reservation_policy_rules', [
            'institution_id' => $instB,
            'label' => 'Rule Active Inst',
        ]);
    }

    public function test_member_reservation_status_uses_active_institution_context(): void
    {
        $instA = $this->createInstitution('Inst X', 'INST-MEM-01');
        $instB = $this->createInstitution('Inst Y', 'INST-MEM-02');
        $memberUser = $this->makeUser('member', $instA, 'member-active-inst@test.local');
        $this->setActiveInstitution($memberUser, $instB);

        $memberId = (int) DB::table('members')->insertGetId([
            'institution_id' => $instB,
            'user_id' => $memberUser->id,
            'member_code' => 'MBR-ACT-01',
            'full_name' => 'Member Active',
            'status' => 'active',
            'joined_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblioA = (int) DB::table('biblio')->insertGetId([
            'institution_id' => $instA,
            'title' => 'Title A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $biblioB = (int) DB::table('biblio')->insertGetId([
            'institution_id' => $instB,
            'title' => 'Title B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('reservations')->insert([
            [
                'institution_id' => $instA,
                'member_id' => $memberId,
                'biblio_id' => $biblioA,
                'status' => 'queued',
                'queue_no' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'institution_id' => $instB,
                'member_id' => $memberId,
                'biblio_id' => $biblioB,
                'status' => 'ready',
                'queue_no' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($memberUser)->getJson(route('member.reservasi.status'));
        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('counts.ready', 1);
        $response->assertJsonPath('counts.queued', 0);
    }
}
