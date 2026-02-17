<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CirculationMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CirculationObservabilityTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitutionAndBranch(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Obs',
            'code' => 'INST-OBS-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-OBS',
            'name' => 'Cabang Obs',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeUser(string $role, int $institutionId, int $branchId, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Obs',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    public function test_staff_can_access_circulation_metrics_endpoint_and_get_snapshot(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $staff = $this->makeUser('staff', $institutionId, $branchId, 'staff-obs@test.local');

        CirculationMetrics::recordEndpoint('transaksi.pinjam.store', 120, 200);
        CirculationMetrics::recordBusinessOutcome('checkout', true);
        CirculationMetrics::recordBusinessOutcome('checkout', false);
        CirculationMetrics::incrementFailureReason('checkout', 'Validasi cabang gagal');

        $resp = $this->actingAs($staff)->get(route('transaksi.metrics'));
        $resp->assertOk();
        $resp->assertJsonPath('ok', true);
        $resp->assertJsonStructure([
            'ok',
            'metrics' => [
                'totals' => ['requests', 'latency_p95_ms', 'business_failure_rate_pct'],
                'health' => ['label', 'class', 'p95_ms'],
                'top_failure_reasons',
            ],
        ]);
    }

    public function test_transaksi_dashboard_shows_observability_panel(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeUser('admin', $institutionId, $branchId, 'admin-obs@test.local');

        $resp = $this->actingAs($admin)->get(route('transaksi.dashboard'));
        $resp->assertOk();
        $resp->assertSee('Observability Alert', false);
        $resp->assertSee('Top failure', false);
    }

    public function test_member_cannot_access_circulation_metrics_endpoint(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $member = $this->makeUser('member', $institutionId, $branchId, 'member-obs@test.local');

        $this->actingAs($member)
            ->get(route('transaksi.metrics'))
            ->assertRedirect(route('app'));
    }
}

