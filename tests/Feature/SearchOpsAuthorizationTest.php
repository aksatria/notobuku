<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('searchops-isolated')]
class SearchOpsAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private function seedInstitutionAndBranch(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Search Ops',
            'code' => 'INST-SOPS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-SOPS',
            'name' => 'Cabang Search Ops',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return [$institutionId, $branchId];
    }

    private function makeUser(string $role, int $institutionId, int $branchId, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' SearchOps',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    public function test_member_cannot_access_search_ops_admin_endpoints(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $member = $this->makeUser('member', $institutionId, $branchId, 'member-sops@test.local');

        $this->actingAs($member)->get(route('admin.search_synonyms'))->assertRedirect(route('app'));
        $this->actingAs($member)->get(route('admin.search_tuning'))->assertRedirect(route('app'));
        $this->actingAs($member)->get(route('admin.search_stopwords'))->assertRedirect(route('app'));
        $this->actingAs($member)->get(route('admin.search_analytics'))->assertRedirect(route('app'));
    }

    public function test_admin_can_access_search_ops_admin_endpoints(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $admin = $this->makeUser('admin', $institutionId, $branchId, 'admin-sops@test.local');

        $this->actingAs($admin)->get(route('admin.search_synonyms'))->assertOk();
        $this->actingAs($admin)->get(route('admin.search_tuning'))->assertOk();
        $this->actingAs($admin)->get(route('admin.search_stopwords'))->assertOk();
        $this->actingAs($admin)->get(route('admin.search_analytics'))->assertOk();
    }
}
