<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SearchTuningAdminTest extends TestCase
{
    use DatabaseTransactions;

    protected static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$migrated) {
            Artisan::call('migrate', ['--force' => true, '--env' => 'testing']);
            self::$migrated = true;
        }
    }

    private function seedInstitutionAndBranch(int $institutionId): int
    {
        DB::table('institutions')->insert([
            'id' => $institutionId,
            'name' => 'Inst Tuning',
            'code' => 'INST-TUNE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-TUNE',
            'name' => 'Cabang Tuning',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeUser(string $role, int $institutionId, int $branchId): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Tuning',
            'email' => $role . '-tuning@test.local',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    public function test_admin_can_update_search_tuning_settings(): void
    {
        $institutionId = 1;
        $branchId = $this->seedInstitutionAndBranch($institutionId);
        $admin = $this->makeUser('admin', $institutionId, $branchId);

        $payload = [
            'title_exact_weight' => 90,
            'author_exact_weight' => 55,
            'subject_exact_weight' => 30,
            'publisher_exact_weight' => 20,
            'isbn_exact_weight' => 120,
            'short_query_max_len' => 5,
            'short_query_multiplier' => 1.8,
            'available_weight' => 11.5,
            'borrowed_penalty' => 2.5,
            'reserved_penalty' => 1.5,
        ];

        $resp = $this->actingAs($admin)->post(route('admin.search_tuning.update'), $payload);
        $resp->assertRedirect();

        $this->assertDatabaseHas('search_tuning_settings', [
            'institution_id' => $institutionId,
            'title_exact_weight' => 90,
            'author_exact_weight' => 55,
            'isbn_exact_weight' => 120,
        ]);
    }

    public function test_staff_cannot_access_search_tuning_route(): void
    {
        $institutionId = 1;
        $branchId = $this->seedInstitutionAndBranch($institutionId);
        $staff = $this->makeUser('staff', $institutionId, $branchId);

        $resp = $this->actingAs($staff)->get(route('admin.search_tuning'));
        $resp->assertRedirect(route('app'));
    }
}
