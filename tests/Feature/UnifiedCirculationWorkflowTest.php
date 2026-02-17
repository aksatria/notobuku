<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UnifiedCirculationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private function seedInstitution(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Unified',
            'code' => 'INST-UNI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-UNI',
            'name' => 'Cabang Unified',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeAdmin(int $institutionId, int $branchId): User
    {
        return User::create([
            'name' => 'Admin Unified',
            'email' => 'admin-unified@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    private function makeMemberUser(int $institutionId, int $branchId): User
    {
        return User::create([
            'name' => 'Member Unified User',
            'email' => 'member-unified@test.local',
            'password' => Hash::make('password'),
            'role' => 'member',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    public function test_unified_screen_can_be_opened(): void
    {
        [$institutionId, $branchId] = $this->seedInstitution();
        $admin = $this->makeAdmin($institutionId, $branchId);

        $this->actingAs($admin)
            ->get(route('transaksi.index'))
            ->assertOk()
            ->assertSee('Sirkulasi Terpadu')
            ->assertSee('Auto-commit');
    }

    public function test_unified_checkout_commit_returns_json_success(): void
    {
        [$institutionId, $branchId] = $this->seedInstitution();
        $admin = $this->makeAdmin($institutionId, $branchId);

        $memberId = DB::table('members')->insertGetId([
            'institution_id' => $institutionId,
            'member_code' => 'MBR-UNI-01',
            'full_name' => 'Member Unified',
            'member_type' => 'member',
            'status' => 'active',
            'joined_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblioId = DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'Judul Unified',
            'material_type' => 'book',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('items')->insert([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'biblio_id' => $biblioId,
            'barcode' => 'BC-UNI-0001',
            'accession_number' => 'ACC-UNI-0001',
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->actingAs($admin)->postJson(route('transaksi.unified.commit'), [
            'action' => 'checkout',
            'payload' => [
                'member_id' => $memberId,
                'barcodes' => ['BC-UNI-0001'],
            ],
            'client_event_id' => 'evt-uni-1',
        ]);

        $res->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(1, DB::table('loans')->count());
    }

    public function test_unified_sync_is_idempotent_for_same_client_event_id(): void
    {
        [$institutionId, $branchId] = $this->seedInstitution();
        $admin = $this->makeAdmin($institutionId, $branchId);

        $memberId = DB::table('members')->insertGetId([
            'institution_id' => $institutionId,
            'member_code' => 'MBR-UNI-02',
            'full_name' => 'Member Unified 2',
            'member_type' => 'member',
            'status' => 'active',
            'joined_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblioId = DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'Judul Unified 2',
            'material_type' => 'book',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('items')->insert([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'biblio_id' => $biblioId,
            'barcode' => 'BC-UNI-0002',
            'accession_number' => 'ACC-UNI-0002',
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'events' => [[
                'action' => 'checkout',
                'payload' => [
                    'member_id' => $memberId,
                    'barcodes' => ['BC-UNI-0002'],
                ],
                'client_event_id' => 'evt-uni-sync-1',
            ]],
        ];

        $first = $this->actingAs($admin)->postJson(route('transaksi.unified.sync'), $payload);
        $first->assertOk()->assertJsonPath('summary.success', 1);

        $second = $this->actingAs($admin)->postJson(route('transaksi.unified.sync'), $payload);
        $second->assertOk()->assertJsonPath('summary.success', 1);

        $this->assertSame(1, DB::table('loans')->count());
    }

    public function test_member_cannot_access_unified_circulation_endpoints(): void
    {
        [$institutionId, $branchId] = $this->seedInstitution();
        $memberUser = $this->makeMemberUser($institutionId, $branchId);

        $this->actingAs($memberUser)
            ->get(route('transaksi.index'))
            ->assertStatus(302);

        $this->actingAs($memberUser)
            ->postJson(route('transaksi.unified.commit'), [
                'action' => 'return',
                'payload' => ['loan_item_ids' => [1]],
            ])
            ->assertStatus(302);
    }
}
