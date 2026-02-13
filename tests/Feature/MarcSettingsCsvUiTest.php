<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarcSettingsCsvUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_columns_and_limit_persist_from_query_params(): void
    {
        $institutionId = \Illuminate\Support\Facades\DB::table('institutions')->insertGetId([
            'name' => 'Test Institution CSV UI',
            'code' => 'TEST-CSV-UI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'admin',
            'institution_id' => $institutionId,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('admin.marc.settings', [
            'columns' => ['id', 'action'],
            'limit' => 123,
        ]));

        $response->assertOk();

        $content = $response->getContent();
        $this->assertMatchesRegularExpression('/<option value="id"[^>]*selected/i', $content);
        $this->assertMatchesRegularExpression('/<option value="action"[^>]*selected/i', $content);
        $this->assertDoesNotMatchRegularExpression('/<option value="status"[^>]*selected/i', $content);
        $this->assertMatchesRegularExpression('/name="limit"[^>]*value="123"/i', $content);
        $this->assertStringContainsString('data-columns-select-all', $content);
        $this->assertStringContainsString('data-columns-clear-all', $content);
    }
}
