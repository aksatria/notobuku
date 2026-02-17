<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpacQueryGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_opac_rejects_wildcard_abuse_pattern(): void
    {
        $resp = $this->get('/opac?q=%%%%%%');

        $resp->assertStatus(422);
        $resp->assertSee('wildcard_pattern');
    }

    public function test_opac_rejects_suspicious_boolean_expression(): void
    {
        $resp = $this->get('/opac?q=judul%20OR%201=1');

        $resp->assertStatus(422);
        $resp->assertSee('suspicious_boolean_expression');
    }

    public function test_opac_applies_adaptive_rate_limit_for_bot_ua(): void
    {
        config([
            'notobuku.opac.rate_limit.search.per_minute' => 2,
            'notobuku.opac.rate_limit.search.per_second' => 100,
            'notobuku.opac.rate_limit.search_adaptive.high_risk_multiplier' => 0.5,
        ]);

        $server = [
            'REMOTE_ADDR' => '203.0.113.30',
            'HTTP_USER_AGENT' => 'curl/8.5.0',
        ];

        $this->withServerVariables($server)->get('/opac?q=buku')->assertOk();
        $this->withServerVariables($server)->get('/opac?q=buku')->assertStatus(429);
    }
}
