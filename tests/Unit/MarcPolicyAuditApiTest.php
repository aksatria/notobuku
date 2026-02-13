<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\MarcPolicyApiController;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MarcPolicyAuditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_api_paginates_and_includes_user_name(): void
    {
        $institutionId = \Illuminate\Support\Facades\DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-AUD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'admin',
            'institution_id' => $institutionId,
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'marc_policy_publish',
            'format' => 'marc_policy',
            'status' => 'published',
            'meta' => [
                'policy_id' => 1,
                'name' => 'RDA Core',
                'version' => 1,
                'institution_id' => $institutionId,
            ],
        ]);

        $this->actingAs($user);
        $controller = new MarcPolicyApiController();
        $request = Request::create('/admin/marc/policy/audits', 'GET', [
            'per_page' => 10,
            'page' => 1,
        ]);

        $response = $controller->audits($request);
        $payload = $response->getData(true);

        $this->assertTrue($payload['ok']);
        $this->assertSame(1, $payload['meta']['total'] ?? 0);
        $this->assertSame($user->name, $payload['data'][0]['user_name'] ?? null);
        $this->assertSame($user->email, $payload['data'][0]['user_email'] ?? null);
        $this->assertSame($user->role, $payload['data'][0]['user_role'] ?? null);
    }

    public function test_audit_csv_respects_column_selection(): void
    {
        $institutionId = \Illuminate\Support\Facades\DB::table('institutions')->insertGetId([
            'name' => 'Test Institution 2',
            'code' => 'TEST-AUD-CSV',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'admin',
            'institution_id' => $institutionId,
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'marc_policy_draft',
            'format' => 'marc_policy',
            'status' => 'draft',
            'meta' => [
                'policy_id' => 2,
                'name' => 'RDA Core',
                'version' => 1,
                'institution_id' => $institutionId,
            ],
        ]);

        $this->actingAs($user);
        $controller = new MarcPolicyApiController();
        $request = Request::create('/admin/marc/policy/audits.csv', 'GET', [
            'columns' => 'id,action,user_name',
        ]);

        $response = $controller->auditsCsv($request);
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
    }
}
