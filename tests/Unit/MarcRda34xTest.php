<?php

namespace Tests\Unit;

use App\Models\Biblio;
use App\Models\BiblioIdentifier;
use App\Services\ExportService;
use App\Services\MetadataMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarcRda34xTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_video_emits_347_video_file(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-34X-V',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Online Video',
            'normalized_title' => 'online video',
            'publisher' => 'Media Press',
            'place_of_publication' => 'Tokyo',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'video',
            'media_type' => 'online',
            'ai_status' => 'draft',
        ]);
        BiblioIdentifier::create([
            'biblio_id' => $biblio->id,
            'scheme' => 'uri',
            'value' => 'https://example.org/video',
            'normalized_value' => 'https://example.org/video',
            'uri' => 'https://example.org/video',
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $field347 = $xpath->query('//marc:datafield[@tag="347"]/marc:subfield[@code="a" and text()="video file"]')->item(0);
        $this->assertNotNull($field347);
    }

    public function test_online_text_emits_347_text_file(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-34X-T',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Online Text',
            'normalized_title' => 'online text',
            'publisher' => 'Text Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'ebook',
            'media_type' => 'online',
            'ai_status' => 'draft',
        ]);
        BiblioIdentifier::create([
            'biblio_id' => $biblio->id,
            'scheme' => 'uri',
            'value' => 'https://example.org/text',
            'normalized_value' => 'https://example.org/text',
            'uri' => 'https://example.org/text',
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $field347 = $xpath->query('//marc:datafield[@tag="347"]/marc:subfield[@code="a" and text()="text file"]')->item(0);
        $this->assertNotNull($field347);
    }
}
