<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\InteropMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InteropHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_oai_route_applies_rate_limit_per_ip_with_burst_control(): void
    {
        config([
            'notobuku.interop.rate_limit.oai.per_minute' => 2,
            'notobuku.interop.rate_limit.oai.per_second' => 100,
        ]);

        $server = ['REMOTE_ADDR' => '198.51.100.10'];
        $this->withServerVariables($server)->get(route('oai.pmh', ['verb' => 'Identify']))->assertOk();
        $this->withServerVariables($server)->get(route('oai.pmh', ['verb' => 'Identify']))->assertOk();
        $this->withServerVariables($server)->get(route('oai.pmh', ['verb' => 'Identify']))->assertStatus(429);

        $admin = User::factory()->create(['role' => 'admin']);
        $metrics = $this->actingAs($admin)->get(route('interop.metrics'));
        $metrics->assertOk();
        $this->assertGreaterThanOrEqual(1, (int) data_get($metrics->json(), 'metrics.counters.oai_rate_limited', 0));
    }

    public function test_sru_route_applies_rate_limit_per_ip_with_burst_control(): void
    {
        config([
            'notobuku.interop.rate_limit.sru.per_minute' => 2,
            'notobuku.interop.rate_limit.sru.per_second' => 100,
        ]);

        $server = ['REMOTE_ADDR' => '198.51.100.11'];
        $this->withServerVariables($server)->get(route('sru.endpoint', ['operation' => 'explain']))->assertOk();
        $this->withServerVariables($server)->get(route('sru.endpoint', ['operation' => 'explain']))->assertOk();
        $this->withServerVariables($server)->get(route('sru.endpoint', ['operation' => 'explain']))->assertStatus(429);

        $admin = User::factory()->create(['role' => 'admin']);
        $metrics = $this->actingAs($admin)->get(route('interop.metrics'));
        $metrics->assertOk();
        $this->assertGreaterThanOrEqual(1, (int) data_get($metrics->json(), 'metrics.counters.sru_rate_limited', 0));
    }

    public function test_interop_metrics_collect_invalid_token_eviction_and_latency(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Interop Inst',
            'code' => 'INS-INT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('biblio')->insert([
            'institution_id' => $institutionId,
            'title' => 'Interop Seed',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        for ($i = 0; $i < 6; $i++) {
            $this->get(route('oai.pmh', [
                'verb' => 'ListIdentifiers',
                'metadataPrefix' => 'oai_dc',
                'from' => now()->subDays($i + 2)->toDateString(),
            ]))->assertOk();
        }

        $this->get(route('oai.pmh', [
            'verb' => 'ListIdentifiers',
            'resumptionToken' => 'invalid-token',
        ]))->assertOk();

        $this->get(route('sru.endpoint', [
            'operation' => 'explain',
        ]))->assertOk();

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $resp = $this->actingAs($admin)->get(route('interop.metrics'));
        $resp->assertOk();
        $resp->assertJsonPath('ok', true);
        $this->assertGreaterThanOrEqual(1, (int) data_get($resp->json(), 'metrics.counters.oai_invalid_token', 0));
        $this->assertGreaterThanOrEqual(1, (int) data_get($resp->json(), 'metrics.counters.oai_snapshot_evictions', 0));
        $this->assertGreaterThanOrEqual(1, (int) data_get($resp->json(), 'metrics.latency.oai.count', 0));
        $this->assertGreaterThanOrEqual(1, (int) data_get($resp->json(), 'metrics.latency.sru.count', 0));
        $this->assertArrayHasKey('p95_ms', (array) data_get($resp->json(), 'metrics.latency.oai', []));
        $this->assertArrayHasKey('p95_ms', (array) data_get($resp->json(), 'metrics.latency.sru', []));
        $resp->assertJsonStructure([
            'metrics' => [
                'health' => [
                    'label',
                    'class',
                    'p95_ms',
                    'invalid_token_total',
                    'rate_limited_total',
                ],
                'history' => [
                    'last_24h',
                    'daily_35d',
                ],
                'alerts' => [
                    'critical_streak' => [
                        'active',
                        'streak_minutes',
                        'threshold_minutes',
                        'last_triggered_at',
                    ],
                ],
            ],
        ]);
    }

    public function test_interop_daily_csv_export_is_available_for_admin(): void
    {
        Cache::flush();
        InteropMetrics::recordLatency('oai', 120);
        InteropMetrics::recordLatency('sru', 240);
        InteropMetrics::incrementInvalidToken('oai', 2);
        InteropMetrics::incrementRateLimited('sru', 3);
        InteropMetrics::snapshot();

        $admin = User::factory()->create(['role' => 'admin']);
        $resp = $this->actingAs($admin)->get(route('interop.metrics.export.csv', ['days' => 7]));
        $resp->assertOk();
        $resp->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = (string) $resp->streamedContent();
        $this->assertStringContainsString('day,oai_p95_ms,sru_p95_ms,p95_ms', $csv);
        $this->assertStringContainsString('invalid_token_total', $csv);
        $this->assertStringContainsString('rate_limited_total', $csv);
    }

    public function test_staff_cannot_export_interop_daily_csv(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $resp = $this->actingAs($staff)->get(route('interop.metrics.export.csv', ['days' => 7]));
        $resp->assertRedirect(route('app'));
    }
}
