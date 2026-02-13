<?php

namespace Tests\Unit;

use App\Models\Author;
use App\Models\Biblio;
use App\Models\Subject;
use App\Services\ExportService;
use App\Services\MetadataMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_marcxml_valid_xml(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-02',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Judul Buku',
            'subtitle' => 'Subjudul',
            'normalized_title' => 'judul buku subjudul',
            'place_of_publication' => 'Jakarta',
            'publisher' => 'Penerbit Test',
            'publish_year' => 2020,
            'language' => 'id',
            'isbn' => '9786020000002',
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

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');
        $fields = $xpath->query('//marc:datafield[@tag="245"]');

        $this->assertGreaterThan(0, $fields->length);

        $leader = $xpath->query('//marc:record/marc:leader');
        $this->assertGreaterThan(0, $leader->length);

        $control008 = $xpath->query('//marc:controlfield[@tag="008"]')->item(0);
        $this->assertNotNull($control008);
        $this->assertGreaterThanOrEqual(35, strlen((string) $control008->textContent));
    }
}
