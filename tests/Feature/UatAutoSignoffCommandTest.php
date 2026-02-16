<?php

namespace Tests\Feature;

use App\Support\OpacMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UatAutoSignoffCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_signoff_marks_pass_when_strict_readiness_evidence_is_sufficient(): void
    {
        Storage::fake('local');

        config()->set('notobuku.opac.public_institution_id', 1);
        config()->set('notobuku.readiness.minimum_traffic.opac_searches', 1);
        config()->set('notobuku.readiness.minimum_traffic.interop_points', 1);
        config()->set('notobuku.readiness.minimum_traffic.scale_samples', 1);
        config()->set('notobuku.uat.auto_signoff.operator', 'SYSTEM AUTO');
        config()->set('notobuku.catalog.quality_gate.enabled', true);
        config()->set('notobuku.catalog.zero_result_governance.enabled', true);

        OpacMetrics::recordRequest('opac.search', 200, 120);
        OpacMetrics::recordRequest('opac.search', 200, 140);

        DB::table('search_queries')->insert([
            'institution_id' => 1,
            'user_id' => null,
            'query' => 'filsafat',
            'normalized_query' => 'filsafat',
            'search_count' => 1,
            'last_hits' => 10,
            'last_searched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('interop_metric_points')->insert([
            'minute_at' => now()->startOfMinute(),
            'health_label' => 'Sehat',
            'health_class' => 'good',
            'p95_ms' => 150,
            'invalid_token_total' => 0,
            'rate_limited_total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put('reports/catalog-scale/proof-test.json', json_encode([
            'samples' => 1,
            'metrics' => [
                'p95_ms' => 120,
                'error_rate_pct' => 0.0,
            ],
        ], JSON_PRETTY_PRINT));

        $this->artisan('notobuku:uat-auto-signoff', ['--strict-ready' => true])
            ->assertExitCode(0);

        $row = DB::table('uat_signoffs')
            ->where('institution_id', 1)
            ->whereDate('check_date', now()->toDateString())
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('pass', (string) $row->status);
        $this->assertSame('SYSTEM AUTO', (string) $row->operator_name);
        $this->assertNotNull($row->signed_at);
    }
}

