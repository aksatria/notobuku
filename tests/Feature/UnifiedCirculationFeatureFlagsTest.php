<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UnifiedCirculationFeatureFlagsTest extends TestCase
{
    use DatabaseTransactions;

    private function seedInstitution(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Unified Flags',
            'code' => 'INST-UF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-UF',
            'name' => 'Cabang Unified Flags',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeStaff(int $institutionId, int $branchId): User
    {
        return User::create([
            'name' => 'Staff Unified Flags',
            'email' => 'staff-unified-flags@test.local',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    public function test_redirects_to_legacy_pinjam_form_when_unified_disabled(): void
    {
        [$institutionId, $branchId] = $this->seedInstitution();
        $staff = $this->makeStaff($institutionId, $branchId);

        config(['notobuku.circulation.unified.enabled' => false]);

        $this->actingAs($staff)
            ->get(route('transaksi.index'))
            ->assertRedirect(route('transaksi.pinjam.form'));
    }

    public function test_unified_commit_returns_404_when_unified_disabled(): void
    {
        [$institutionId, $branchId] = $this->seedInstitution();
        $staff = $this->makeStaff($institutionId, $branchId);

        config(['notobuku.circulation.unified.enabled' => false]);

        $this->actingAs($staff)
            ->postJson(route('transaksi.unified.commit'), [
                'action' => 'return',
                'payload' => ['loan_item_ids' => [1]],
            ])
            ->assertStatus(404)
            ->assertJsonPath('ok', false);
    }

    public function test_unified_sync_returns_404_when_offline_queue_disabled(): void
    {
        [$institutionId, $branchId] = $this->seedInstitution();
        $staff = $this->makeStaff($institutionId, $branchId);

        config([
            'notobuku.circulation.unified.enabled' => true,
            'notobuku.circulation.unified.offline_queue_enabled' => false,
        ]);

        $this->actingAs($staff)
            ->postJson(route('transaksi.unified.sync'), [
                'events' => [[
                    'action' => 'return',
                    'payload' => ['loan_item_ids' => [1]],
                    'client_event_id' => 'evt-sync-disabled',
                ]],
            ])
            ->assertStatus(404)
            ->assertJsonPath('ok', false);
    }

    public function test_unified_page_renders_flag_attributes_for_frontend_guardrails(): void
    {
        [$institutionId, $branchId] = $this->seedInstitution();
        $staff = $this->makeStaff($institutionId, $branchId);

        config([
            'notobuku.circulation.unified.enabled' => true,
            'notobuku.circulation.unified.offline_queue_enabled' => false,
            'notobuku.circulation.unified.shortcuts_enabled' => false,
        ]);

        $this->actingAs($staff)
            ->get(route('transaksi.index'))
            ->assertOk()
            ->assertSee('data-flag-offline-queue-enabled="0"', false)
            ->assertSee('data-flag-shortcuts-enabled="0"', false);
    }
}
