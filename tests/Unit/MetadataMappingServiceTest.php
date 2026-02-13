<?php

namespace Tests\Unit;

use App\Models\Author;
use App\Models\Biblio;
use App\Models\Subject;
use App\Services\MetadataMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetadataMappingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_toDublinCore_has_min_fields(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Judul Buku',
            'subtitle' => 'Subjudul',
            'normalized_title' => 'judul buku subjudul',
            'publisher' => 'Penerbit Test',
            'publish_year' => 2022,
            'language' => 'id',
            'isbn' => '9786020000001',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $author = Author::create([
            'name' => 'Penulis A',
            'normalized_name' => 'penulis a',
        ]);
        $biblio->authors()->sync([$author->id => ['role' => 'pengarang', 'sort_order' => 1]]);

        $subject = Subject::create([
            'name' => 'Sains',
            'term' => 'Sains',
            'normalized_term' => 'sains',
            'scheme' => 'local',
        ]);
        $biblio->subjects()->sync([$subject->id => ['type' => 'topic', 'sort_order' => 1]]);

        $service = new MetadataMappingService();
        $dc = $service->toDublinCore($biblio);

        $this->assertArrayHasKey('title', $dc);
        $this->assertArrayHasKey('creator', $dc);
        $this->assertArrayHasKey('subject', $dc);
        $this->assertArrayHasKey('description', $dc);
        $this->assertArrayHasKey('publisher', $dc);
        $this->assertArrayHasKey('date', $dc);
        $this->assertArrayHasKey('language', $dc);
        $this->assertArrayHasKey('identifier', $dc);
        $this->assertArrayHasKey('type', $dc);
        $this->assertArrayHasKey('format', $dc);
    }

    public function test_sync_global_identifiers(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-ID',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Judul Buku',
            'normalized_title' => 'judul buku',
            'publisher' => 'Penerbit Test',
            'publish_year' => 2022,
            'language' => 'id',
            'isbn' => '9786020000002',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $service = new MetadataMappingService();
        $service->syncMetadataForBiblio($biblio, null, [
            [
                'scheme' => 'doi',
                'value' => '10.1234/example',
                'uri' => 'https://doi.org/10.1234/example',
            ],
        ]);

        $this->assertDatabaseHas('biblio_identifiers', [
            'biblio_id' => $biblio->id,
            'scheme' => 'doi',
        ]);

        $meta = $biblio->fresh()->metadata;
        $this->assertNotNull($meta);
    }

    public function test_compute_personal_name_indicator_rules(): void
    {
        $service = new MetadataMappingService();
        $method = new \ReflectionMethod(MetadataMappingService::class, 'computePersonalNameIndicator');
        $method->setAccessible(true);

        $cases = [
            ['Rahman, E.', '1'],
            ['Rahman, 1980-', '1'],
            ['Rahman, Ph.D.', '0'],
            ['Rahman, Dr.', '0'],
            ['Rahman, Jr.', '0'],
            ['A. Rahman', '0'],
            ['Rahman, E., Ph.D.', '1'],
            ['Rahman, E., Jr.', '1'],
            ['Rahman,', '0'],
        ];

        foreach ($cases as [$name, $expected]) {
            $this->assertSame($expected, $method->invoke($service, $name), $name);
        }
    }
}
