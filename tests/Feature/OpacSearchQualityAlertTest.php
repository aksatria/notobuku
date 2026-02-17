<?php

namespace Tests\Feature;

use App\Support\OpacMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpacSearchQualityAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_search_quality_alert_triggers_webhook_and_respects_cooldown(): void
    {
        config([
            'notobuku.opac.observability.search_window_hours' => 24,
            'notobuku.opac.observability.alerts.enabled' => true,
            'notobuku.opac.observability.alerts.min_window_searches' => 1,
            'notobuku.opac.observability.alerts.zero_result_warn_pct' => 10,
            'notobuku.opac.observability.alerts.zero_result_critical_pct' => 20,
            'notobuku.opac.observability.alerts.no_click_warn_pct' => 10,
            'notobuku.opac.observability.alerts.no_click_critical_pct' => 20,
            'notobuku.opac.observability.alerts.cooldown_minutes' => 30,
            'notobuku.opac.observability.alerts.webhook_url' => 'https://hooks.example.test/opac-search-alert',
        ]);

        Http::fake();

        DB::table('search_query_events')->insert([
            [
                'institution_id' => 1,
                'query' => 'q1',
                'normalized_query' => 'q1',
                'hits' => 0,
                'is_zero_result' => 1,
                'searched_at' => now()->subMinutes(2),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'institution_id' => 1,
                'query' => 'q2',
                'normalized_query' => 'q2',
                'hits' => 0,
                'is_zero_result' => 1,
                'searched_at' => now()->subMinutes(1),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        OpacMetrics::snapshot();
        OpacMetrics::snapshot();

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $data = $request->data();
            return $request->url() === 'https://hooks.example.test/opac-search-alert'
                && ($data['event'] ?? '') === 'opac_search_quality_alert'
                && in_array((string) ($data['state'] ?? ''), ['warning', 'critical'], true);
        });
    }
}
