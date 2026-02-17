<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CirculationEscalationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['notobuku.circulation.sla.exclude_weekends' => false]);
    }

    public function test_escalation_command_sends_webhook_for_critical_unresolved_items(): void
    {
        Cache::forget('circulation:exception:escalation:last:critical');
        Cache::forget('circulation:exception:escalation:last:warning');

        Http::fake([
            'https://ops.example.test/*' => Http::response(['ok' => true], 200),
        ]);

        config([
            'notobuku.circulation.escalation.warning_hours' => 1,
            'notobuku.circulation.escalation.critical_hours' => 2,
            'notobuku.circulation.escalation.cooldown_minutes' => 1,
            'notobuku.circulation.escalation.warning_email_to' => '',
            'notobuku.circulation.escalation.critical_email_to' => '',
            'notobuku.circulation.escalation.webhook_url' => 'https://ops.example.test/circ-escalation',
        ]);

        DB::table('circulation_exception_acknowledgements')->insert([
            'institution_id' => 1,
            'snapshot_date' => now()->subDays(2)->toDateString(),
            'fingerprint' => sha1('critical-item-1'),
            'exception_type' => 'overdue_extreme',
            'severity' => 'critical',
            'loan_id' => 10,
            'loan_item_id' => 100,
            'item_id' => 1000,
            'barcode' => 'BC-CRIT-1',
            'member_id' => 2000,
            'status' => 'open',
            'ack_note' => null,
            'ack_by' => null,
            'ack_at' => null,
            'resolved_by' => null,
            'resolved_at' => null,
            'metadata' => json_encode(['detail' => 'example'], JSON_UNESCAPED_UNICODE),
            'created_at' => now()->subHours(4),
            'updated_at' => now()->subHours(4),
        ]);

        $this->artisan('notobuku:circulation-exception-escalation')->assertExitCode(0);

        Http::assertSent(function ($request) {
            return str_contains((string) $request->url(), 'ops.example.test/circ-escalation')
                && data_get($request->data(), 'event') === 'circulation_exception_escalation'
                && data_get($request->data(), 'level') === 'critical';
        });

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'circulation_exception_escalation',
            'status' => 'sent',
            'format' => 'system',
        ]);
    }
}
