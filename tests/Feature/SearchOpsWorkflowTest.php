<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * @group searchops-isolated
 */
class SearchOpsWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private function seedContext(): array
    {
        $institutionId = (int) DB::table('institutions')->insertGetId([
            'name' => 'Inst Ops',
            'code' => 'INST-OPS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $branchId = (int) DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'code' => 'BR-OPS',
            'name' => 'Cabang Ops',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::create([
            'name' => 'Admin Ops',
            'email' => 'admin-ops@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);

        return [$institutionId, $branchId, $admin];
    }

    public function test_admin_can_resolve_zero_result_and_create_synonym(): void
    {
        [$institutionId, $branchId, $admin] = $this->seedContext();

        $queryId = (int) DB::table('search_queries')->insertGetId([
            'institution_id' => $institutionId,
            'user_id' => $admin->id,
            'query' => 'hary poter',
            'normalized_query' => 'hary poter',
            'search_count' => 6,
            'last_hits' => 0,
            'zero_result_status' => 'open',
            'last_searched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->actingAs($admin)->post(route('admin.search_synonyms.zero_result.resolve', $queryId), [
            'status' => 'resolved',
            'term' => 'hary poter',
            'synonyms' => 'harry potter',
            'note' => 'Ditangani pustakawan',
            'branch_id' => $branchId,
        ]);
        $resp->assertRedirect();

        $this->assertDatabaseHas('search_synonyms', [
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'term' => 'hary poter',
        ]);
        $this->assertDatabaseHas('search_queries', [
            'id' => $queryId,
            'institution_id' => $institutionId,
            'zero_result_status' => 'resolved',
        ]);
    }

    public function test_admin_can_store_stop_words(): void
    {
        [$institutionId, $branchId, $admin] = $this->seedContext();

        $resp = $this->actingAs($admin)->post(route('admin.search_stopwords.store'), [
            'words' => 'dan, atau, yang',
            'branch_id' => $branchId,
        ]);
        $resp->assertRedirect();

        $this->assertDatabaseHas('search_stop_words', [
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'word' => 'dan',
        ]);
    }

    public function test_admin_can_store_pending_synonym_then_approve(): void
    {
        [$institutionId, $branchId, $admin] = $this->seedContext();

        $resp = $this->actingAs($admin)->post(route('admin.search_synonyms.store'), [
            'term' => 'informatka',
            'synonyms' => 'informatika',
            'branch_id' => $branchId,
            'status' => 'pending',
        ]);
        $resp->assertRedirect();

        $synId = (int) DB::table('search_synonyms')
            ->where('institution_id', $institutionId)
            ->where('branch_id', $branchId)
            ->where('term', 'informatka')
            ->value('id');

        $this->assertDatabaseHas('search_synonyms', [
            'id' => $synId,
            'status' => 'pending',
        ]);

        $approve = $this->actingAs($admin)->post(route('admin.search_synonyms.approve', $synId));
        $approve->assertRedirect();

        $this->assertDatabaseHas('search_synonyms', [
            'id' => $synId,
            'status' => 'approved',
        ]);
    }

    public function test_admin_can_approve_auto_suggestion_from_zero_result_queue(): void
    {
        [$institutionId, $branchId, $admin] = $this->seedContext();

        $queryId = (int) DB::table('search_queries')->insertGetId([
            'institution_id' => $institutionId,
            'user_id' => $admin->id,
            'query' => 'hary poter',
            'normalized_query' => 'hary poter',
            'search_count' => 4,
            'last_hits' => 0,
            'zero_result_status' => 'open',
            'auto_suggestion_query' => 'harry potter',
            'auto_suggestion_score' => 87.50,
            'auto_suggestion_status' => 'open',
            'last_searched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->actingAs($admin)->post(route('admin.search_synonyms.zero_result.resolve', $queryId), [
            'status' => 'resolved',
            'use_auto_suggestion' => 1,
        ]);
        $resp->assertRedirect();

        $this->assertDatabaseHas('search_synonyms', [
            'institution_id' => $institutionId,
            'term' => 'hary poter',
            'status' => 'approved',
            'source' => 'zero_result',
        ]);
        $this->assertDatabaseHas('search_queries', [
            'id' => $queryId,
            'auto_suggestion_status' => 'approved',
            'zero_result_status' => 'resolved',
        ]);
    }

    public function test_admin_can_open_search_analytics_page(): void
    {
        [, , $admin] = $this->seedContext();
        $resp = $this->actingAs($admin)->get(route('admin.search_analytics'));
        $resp->assertOk();
        $resp->assertSee('Search Analytics');
        $resp->assertSee('Search Alert');
    }
}
