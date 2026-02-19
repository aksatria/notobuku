<?php

namespace Tests\Unit;

use App\Services\Search\BiblioRankingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BiblioRankingServiceTest extends TestCase
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

    public function test_rerank_prioritizes_available_items_over_borrowed_when_scores_are_equal(): void
    {
        DB::table('institutions')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Inst',
                'code' => 'INST',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('biblio')->insert([
            ['id' => 11, 'institution_id' => 1, 'title' => 'Avail First', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'institution_id' => 1, 'title' => 'Borrowed Later', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('biblio_metrics')->insert([
            ['institution_id' => 1, 'biblio_id' => 11, 'click_count' => 0, 'borrow_count' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['institution_id' => 1, 'biblio_id' => 12, 'click_count' => 0, 'borrow_count' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('items')->insert([
            ['institution_id' => 1, 'biblio_id' => 11, 'barcode' => 'ITM-11', 'accession_number' => 'ACC-11', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()],
            ['institution_id' => 1, 'biblio_id' => 12, 'barcode' => 'ITM-12', 'accession_number' => 'ACC-12', 'status' => 'borrowed', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = app(BiblioRankingService::class);
        $ranked = $service->rerankIds([12, 11], 1, null, 'institution', null, null);

        $this->assertSame([11, 12], $ranked);
    }

    public function test_rerank_uses_selected_branch_context_when_branch_filter_exists(): void
    {
        DB::table('institutions')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Inst',
                'code' => 'INST',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('branches')->updateOrInsert(
            ['id' => 9],
            ['institution_id' => 1, 'name' => 'Pusat', 'code' => 'PST', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('branches')->updateOrInsert(
            ['id' => 10],
            ['institution_id' => 1, 'name' => 'Selatan', 'code' => 'SLT', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        DB::table('biblio')->insert([
            ['id' => 21, 'institution_id' => 1, 'title' => 'Ada di Cabang 9', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 22, 'institution_id' => 1, 'title' => 'Ada di Cabang 10', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('biblio_metrics')->insert([
            ['institution_id' => 1, 'biblio_id' => 21, 'click_count' => 0, 'borrow_count' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['institution_id' => 1, 'biblio_id' => 22, 'click_count' => 0, 'borrow_count' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('items')->insert([
            ['institution_id' => 1, 'branch_id' => 9, 'biblio_id' => 21, 'barcode' => 'BR9-21', 'accession_number' => 'ACC-BR9-21', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()],
            ['institution_id' => 1, 'branch_id' => 10, 'biblio_id' => 22, 'barcode' => 'BR10-22', 'accession_number' => 'ACC-BR10-22', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = app(BiblioRankingService::class);
        $ranked = $service->rerankIds([22, 21], 1, null, 'institution', null, null, [9]);

        $this->assertSame([21, 22], $ranked);
    }

    public function test_rerank_uses_custom_tuning_weights_from_admin_settings(): void
    {
        DB::table('institutions')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Inst',
                'code' => 'INST',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('biblio')->insert([
            ['id' => 31, 'institution_id' => 1, 'title' => 'abc', 'normalized_title' => 'abc', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 32, 'institution_id' => 1, 'title' => 'judul lain', 'normalized_title' => 'judul lain', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('authors')->insert([
            ['id' => 301, 'name' => 'abc', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('biblio_author')->insert([
            ['biblio_id' => 32, 'author_id' => 301, 'role' => 'pengarang', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('biblio_metrics')->insert([
            ['institution_id' => 1, 'biblio_id' => 31, 'click_count' => 0, 'borrow_count' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['institution_id' => 1, 'biblio_id' => 32, 'click_count' => 0, 'borrow_count' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('items')->insert([
            ['institution_id' => 1, 'biblio_id' => 31, 'barcode' => 'ITM-31', 'accession_number' => 'ACC-31', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()],
            ['institution_id' => 1, 'biblio_id' => 32, 'barcode' => 'ITM-32', 'accession_number' => 'ACC-32', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('search_tuning_settings')->insert([
            'institution_id' => 1,
            'title_exact_weight' => 20,
            'author_exact_weight' => 120,
            'subject_exact_weight' => 25,
            'publisher_exact_weight' => 15,
            'isbn_exact_weight' => 100,
            'short_query_max_len' => 4,
            'short_query_multiplier' => 1.0,
            'available_weight' => 10,
            'borrowed_penalty' => 3,
            'reserved_penalty' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(BiblioRankingService::class);
        $ranked = $service->rerankIds([31, 32], 1, null, 'institution', 'abc', null, []);

        $this->assertSame([32, 31], $ranked);
    }
}
