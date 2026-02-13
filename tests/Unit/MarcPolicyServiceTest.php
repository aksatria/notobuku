<?php

namespace Tests\Unit;

use App\Models\MarcPolicySet;
use App\Services\MarcPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarcPolicyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_policy_prefers_institution_specific(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-POL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        MarcPolicySet::create([
            'institution_id' => null,
            'name' => 'RDA Core',
            'version' => 1,
            'status' => 'published',
            'payload_json' => ['rules' => ['relator_uncontrolled' => 'warn']],
        ]);

        MarcPolicySet::create([
            'institution_id' => $institutionId,
            'name' => 'RDA Core',
            'version' => 2,
            'status' => 'published',
            'payload_json' => ['rules' => ['relator_uncontrolled' => 'error']],
        ]);

        $service = new MarcPolicyService();
        $policy = $service->getActivePolicy($institutionId);

        $this->assertSame('error', $policy['rules']['relator_uncontrolled'] ?? null);
    }

    public function test_policy_schema_version_is_normalized(): void
    {
        $service = new MarcPolicyService();
        $payload = $service->normalizePayload([
            'rules' => [
                'relator_uncontrolled' => 'warn',
            ],
        ]);

        $this->assertSame((int) config('marc.policy_schema_version', 1), $payload['schema_version'] ?? null);
    }

    public function test_policy_migration_renames_relator_unknown(): void
    {
        $service = new MarcPolicyService();
        $payload = $service->normalizePayload([
            'schema_version' => 1,
            'rules' => [
                'relator_unknown' => 'error',
            ],
        ]);

        $this->assertSame('error', $payload['rules']['relator_uncontrolled'] ?? null);
        $this->assertArrayNotHasKey('relator_unknown', $payload['rules'] ?? []);
    }

    public function test_multi_step_migration_keeps_rules_intact(): void
    {
        $service = new MarcPolicyService();
        $payload = $service->migratePayload([
            'schema_version' => 1,
            'rules' => [
                'relator_unknown' => 'warn',
                'audio_missing_narrator' => 'error',
            ],
        ], 1, 2);

        $this->assertSame(2, $payload['schema_version'] ?? null);
        $this->assertSame('warn', $payload['rules']['relator_uncontrolled'] ?? null);
        $this->assertSame('error', $payload['rules']['audio_missing_narrator'] ?? null);
    }
}
