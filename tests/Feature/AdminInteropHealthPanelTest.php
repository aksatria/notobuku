<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\InteropMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminInteropHealthPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_interop_health_panel(): void
    {
        Cache::flush();
        InteropMetrics::recordLatency('oai', 2100);
        InteropMetrics::recordLatency('sru', 900);
        InteropMetrics::incrementInvalidToken('oai', 2);
        InteropMetrics::incrementRateLimited('sru', 1);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $resp = $this->actingAs($admin)->get(route('admin.dashboard'));
        $resp->assertOk();
        $resp->assertSee('Interop Health (OAI + SRU)', false);
        $resp->assertSee('p95', false);
        $resp->assertSee('invalid', false);
        $resp->assertSee('limited', false);
    }
}

