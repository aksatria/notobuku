<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcquisitionsAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createInstitutionId(): int
    {
        return DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-ACQ-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeUser(string $role, int $institutionId): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => "{$role}@test.local",
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'institution_id' => $institutionId,
        ]);
    }

    public function test_member_forbidden_for_acquisitions(): void
    {
        $institutionId = $this->createInstitutionId();
        $member = $this->makeUser('member', $institutionId);

        $response = $this->actingAs($member)->get('/acquisitions/requests');
        $response->assertStatus(403);
    }

    public function test_staff_can_access_acquisitions(): void
    {
        $institutionId = $this->createInstitutionId();
        $staff = $this->makeUser('staff', $institutionId);

        $response = $this->actingAs($staff)->get('/acquisitions/requests');
        $response->assertStatus(200);
    }
}
