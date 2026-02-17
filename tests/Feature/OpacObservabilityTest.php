<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OpacObservabilityTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitutionAndBranch(int $institutionId): int
    {
        DB::table('institutions')->insert([
            'id' => $institutionId,
            'name' => 'OPAC Observability',
            'code' => 'INS-OPAC-OBS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-OBS',
            'name' => 'Cabang OBS',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAdmin(int $institutionId, int $branchId): User
    {
        return User::create([
            'name' => 'Admin OBS',
            'email' => 'admin-opac-obs@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    public function test_opac_routes_emit_trace_headers_and_metrics_exposes_slo_payload(): void
    {
        $publicInstitutionId = (int) config('notobuku.opac.public_institution_id', 1);
        $branchId = $this->seedInstitutionAndBranch($publicInstitutionId);

        DB::table('biblio')->insert([
            'institution_id' => $publicInstitutionId,
            'title' => 'Traceable OPAC Record',
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);
        DB::table('search_queries')->insert([
            'institution_id' => $publicInstitutionId,
            'query' => 'harry poter',
            'normalized_query' => 'harry poter',
            'last_hits' => 0,
            'search_count' => 3,
            'last_searched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->get('/opac');
        $resp->assertOk();
        $resp->assertHeader('X-Trace-Id');
        $resp->assertHeader('traceparent');

        $admin = $this->makeAdmin($publicInstitutionId, $branchId);
        $metrics = $this->actingAs($admin)->get(route('opac.metrics'));

        $metrics->assertOk();
        $metrics->assertJsonPath('ok', true);
        $metrics->assertJsonStructure([
            'trace_id',
            'metrics' => [
                'requests',
                'errors',
                'latency' => ['p50_ms', 'p95_ms'],
                'slo' => ['state', 'burn_rate_5m', 'burn_rate_60m'],
                'search_analytics' => ['total_searches', 'success_rate_pct', 'top_keywords', 'top_zero_result_queries'],
                'endpoints',
            ],
        ]);
    }

    public function test_robots_contains_crawl_budget_rules_for_large_scale_opac(): void
    {
        $resp = $this->get('/robots.txt');
        $resp->assertOk();
        $resp->assertSee('Disallow: /*?*page=', false);
        $resp->assertSee('Disallow: /*?*sort=', false);
        $resp->assertSee('Disallow: /opac/metrics', false);
    }
}
