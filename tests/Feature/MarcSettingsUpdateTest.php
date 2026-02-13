<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MarcSettingsUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-MARC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::create([
            'institution_id' => $institutionId,
            'name' => 'Admin User',
            'username' => 'adminuser',
            'email' => 'admin@example.test',
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    public function test_update_rejects_media_profile_with_short_pattern_007_for_min_007(): void
    {
        $user = $this->createAdminUser();

        $payload = [
            'place_codes_city' => json_encode(['jakarta' => 'io'], JSON_UNESCAPED_SLASHES),
            'place_codes_country' => json_encode(['indonesia' => 'io'], JSON_UNESCAPED_SLASHES),
            'media_profiles' => json_encode([
                [
                    'name' => 'video',
                    'keywords' => ['video'],
                    'type_006' => 'g',
                    'type_007' => 'vd',
                    'pattern_006' => 'g                 ',
                    'pattern_007' => 'vd',
                    'min_007' => 7,
                ],
            ], JSON_UNESCAPED_SLASHES),
        ];

        $response = $this->actingAs($user)
            ->post(route('admin.marc.settings.update'), $payload);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['media_profiles']);
    }
}
