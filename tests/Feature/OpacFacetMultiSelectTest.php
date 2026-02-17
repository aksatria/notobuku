<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OpacFacetMultiSelectTest extends TestCase
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

    public function test_opac_supports_multi_select_author_facet_from_url(): void
    {
        $institutionId = (int) config('notobuku.opac.public_institution_id', 1);

        DB::table('institutions')->insert([
            'id' => $institutionId,
            'name' => 'Public',
            'code' => 'PUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('authors')->insert([
            ['id' => 101, 'name' => 'Author One', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 102, 'name' => 'Author Two', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 103, 'name' => 'Author Three', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('biblio')->insert([
            ['id' => 1001, 'institution_id' => $institutionId, 'title' => 'Buku A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 1002, 'institution_id' => $institutionId, 'title' => 'Buku B', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 1003, 'institution_id' => $institutionId, 'title' => 'Buku C', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('biblio_author')->insert([
            ['biblio_id' => 1001, 'author_id' => 101, 'role' => 'pengarang', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['biblio_id' => 1002, 'author_id' => 102, 'role' => 'pengarang', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['biblio_id' => 1003, 'author_id' => 103, 'role' => 'pengarang', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get('/opac?author[]=101&author[]=102');

        $response->assertOk();
        $response->assertSee('Buku A');
        $response->assertSee('Buku B');
        $response->assertSee('Total koleksi tersedia: 2 judul');
    }

    public function test_opac_supports_branch_filter_from_url(): void
    {
        $institutionId = (int) config('notobuku.opac.public_institution_id', 1);

        DB::table('institutions')->insert([
            'id' => $institutionId,
            'name' => 'Public',
            'code' => 'PUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('branches')->insert([
            ['id' => 201, 'institution_id' => $institutionId, 'name' => 'Cabang A', 'code' => 'A', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 202, 'institution_id' => $institutionId, 'name' => 'Cabang B', 'code' => 'B', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('biblio')->insert([
            ['id' => 2001, 'institution_id' => $institutionId, 'title' => 'Buku Cabang A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2002, 'institution_id' => $institutionId, 'title' => 'Buku Cabang B', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('items')->insert([
            ['institution_id' => $institutionId, 'branch_id' => 201, 'biblio_id' => 2001, 'barcode' => 'BR-A-1', 'accession_number' => 'BR-A-ACC-1', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()],
            ['institution_id' => $institutionId, 'branch_id' => 202, 'biblio_id' => 2002, 'barcode' => 'BR-B-1', 'accession_number' => 'BR-B-ACC-1', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get('/opac?branch[]=201');

        $response->assertOk();
        $response->assertSee('Buku Cabang A');
        $response->assertSee('Total koleksi tersedia: 1 judul');
    }

    public function test_opac_supports_year_range_filter_from_url(): void
    {
        $institutionId = (int) config('notobuku.opac.public_institution_id', 1);

        DB::table('institutions')->insert([
            'id' => $institutionId,
            'name' => 'Public',
            'code' => 'PUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('biblio')->insert([
            ['id' => 3001, 'institution_id' => $institutionId, 'title' => 'Buku 2019', 'publish_year' => 2019, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3002, 'institution_id' => $institutionId, 'title' => 'Buku 2021', 'publish_year' => 2021, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3003, 'institution_id' => $institutionId, 'title' => 'Buku 2025', 'publish_year' => 2025, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get('/opac?year_from=2020&year_to=2024');

        $response->assertOk();
        $response->assertSee('Buku 2021');
        $response->assertSee('Total koleksi tersedia: 1 judul');
    }

    public function test_opac_facets_endpoint_returns_facet_html_json(): void
    {
        $institutionId = (int) config('notobuku.opac.public_institution_id', 1);

        DB::table('institutions')->insert([
            'id' => $institutionId,
            'name' => 'Public',
            'code' => 'PUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('biblio')->insert([
            ['id' => 4001, 'institution_id' => $institutionId, 'title' => 'Buku Facet', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get('/opac/facets');

        $response->assertOk();
        $response->assertJsonStructure(['facet_html']);
        $this->assertStringContainsString('nb-k-facetCompact', (string) $response->json('facet_html'));
    }
}
