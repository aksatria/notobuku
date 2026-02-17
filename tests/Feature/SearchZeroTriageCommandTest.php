<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchZeroTriageCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_closes_open_zero_result_queue(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Triage',
            'code' => 'INST-TRIAGE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('search_queries')->insert([
            [
                'institution_id' => $institutionId,
                'query' => 'cakn',
                'normalized_query' => 'cakn',
                'search_count' => 10,
                'last_hits' => 0,
                'zero_result_status' => 'open',
                'auto_suggestion_query' => 'cak',
                'auto_suggestion_score' => 0.9,
                'auto_suggestion_status' => 'open',
                'last_searched_at' => now()->subDays(2),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            [
                'institution_id' => $institutionId,
                'query' => 'emha',
                'normalized_query' => 'emha',
                'search_count' => 4,
                'last_hits' => 1,
                'zero_result_status' => 'open',
                'auto_suggestion_query' => null,
                'auto_suggestion_score' => null,
                'auto_suggestion_status' => 'none',
                'last_searched_at' => now()->subHours(3),
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(3),
            ],
        ]);

        $this->artisan('notobuku:search-zero-triage', [
            '--institution' => $institutionId,
            '--limit' => 50,
            '--min-search-count' => 2,
            '--age-hours' => 24,
            '--force-close-open' => 1,
        ])->assertSuccessful();

        $open = DB::table('search_queries')
            ->where('institution_id', $institutionId)
            ->where('zero_result_status', 'open')
            ->count();

        $this->assertSame(0, (int) $open);
        $this->assertDatabaseHas('search_queries', [
            'institution_id' => $institutionId,
            'normalized_query' => 'emha',
            'zero_result_status' => 'resolved_auto',
        ]);
        $this->assertDatabaseHas('search_queries', [
            'institution_id' => $institutionId,
            'normalized_query' => 'cakn',
            'zero_result_status' => 'ignored',
        ]);
    }
}

