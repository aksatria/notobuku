<?php

namespace Tests\Unit;

use App\Services\MarcPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarcPolicyValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_payload_rejects_unknown_rule_and_severity(): void
    {
        $service = new MarcPolicyService();
        $errors = $service->validatePayload([
            'rules' => [
                'unknown_rule' => 'warn',
                'relator_uncontrolled' => 'maybe',
            ],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertTrue(collect($errors)->contains(fn($m) => str_contains((string) $m, 'Rule key tidak dikenal')));
        $this->assertTrue(collect($errors)->contains(fn($m) => str_contains((string) $m, 'Severity rule')));
    }
}
