<?php

namespace Tests\Unit;

use App\Models\Biblio;
use App\Services\ExportService;
use App\Services\MetadataMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarcSerialFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_serial_type_code_prefers_material_specific_override(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Serial Override',
            'code' => 'TEST-SERIAL-OVR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Daily News',
            'normalized_title' => 'daily news',
            'publisher' => 'News Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'id',
            'material_type' => 'newspaper',
            'media_type' => 'teks',
            'frequency' => 'Harian',
            'ai_status' => 'draft',
        ]);

        Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Database Journal',
            'normalized_title' => 'database journal',
            'publisher' => 'Data Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'id',
            'material_type' => 'database',
            'media_type' => 'online',
            'frequency' => 'Bulanan',
            'ai_status' => 'draft',
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $newsRecord = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Daily News']")->item(0);
        $this->assertNotNull($newsRecord);
        $news008 = $xpath->query('.//marc:controlfield[@tag="008"]', $newsRecord)->item(0);
        $this->assertNotNull($news008);
        $this->assertSame('n', ((string) $news008->textContent)[21]);
        $this->assertSame(' ', ((string) $news008->textContent)[23]);

        $dbRecord = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Database Journal']")->item(0);
        $this->assertNotNull($dbRecord);
        $db008 = $xpath->query('.//marc:controlfield[@tag="008"]', $dbRecord)->item(0);
        $this->assertNotNull($db008);
        $this->assertSame('d', ((string) $db008->textContent)[21]);
        $this->assertSame('o', ((string) $db008->textContent)[23]);
    }

    public function test_serial_leader_008_and_frequency_fields(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-SERIAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Jurnal Teknologi',
            'normalized_title' => 'jurnal teknologi',
            'publisher' => 'Tech Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'id',
            'material_type' => 'jurnal',
            'media_type' => 'teks',
            'frequency' => 'Bulanan',
            'former_frequency' => 'Mingguan',
            'serial_beginning' => 'Vol. 1, No. 1 (2020)',
            'serial_ending' => 'Vol. 5, No. 4 (2024)',
            'serial_first_issue' => '1',
            'serial_last_issue' => '20',
            'serial_source_note' => 'Description based on: Vol. 1, No. 1 (2020).',
            'serial_preceding_title' => 'Jurnal Teknologi Lama',
            'serial_preceding_issn' => '1234-5678',
            'serial_succeeding_title' => 'Jurnal Teknologi Baru',
            'serial_succeeding_issn' => '8765-4321',
            'ai_status' => 'draft',
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $leader = $xpath->query('//marc:leader')->item(0);
        $this->assertNotNull($leader);
        $leaderVal = (string) $leader->textContent;
        $this->assertSame('s', $leaderVal[6]);
        $this->assertSame('s', $leaderVal[7]);

        $control008 = $xpath->query('//marc:controlfield[@tag="008"]')->item(0);
        $this->assertNotNull($control008);
        $value = (string) $control008->textContent;
        $this->assertSame(40, strlen($value));
        $this->assertSame('m', $value[18]);
        $this->assertSame('r', $value[19]);
        $this->assertSame('p', $value[21]);

        $field310 = $xpath->query('//marc:datafield[@tag="310"]/marc:subfield[@code="a" and text()="Bulanan"]')->item(0);
        $this->assertNotNull($field310);
        $field321 = $xpath->query('//marc:datafield[@tag="321"]/marc:subfield[@code="a" and text()="Mingguan"]')->item(0);
        $this->assertNotNull($field321);
        $field362 = $xpath->query('//marc:datafield[@tag="362"]/marc:subfield[@code="a" and contains(text(),"Vol. 1, No. 1 (2020)")]')->item(0);
        $this->assertNotNull($field362);
        $field363 = $xpath->query('//marc:datafield[@tag="363"]/marc:subfield[@code="a" and text()="1"]/..')->item(0);
        $this->assertNotNull($field363);
        $field588 = $xpath->query('//marc:datafield[@tag="588"]/marc:subfield[@code="a" and contains(text(),"Description based on")]')->item(0);
        $this->assertNotNull($field588);
        $field780 = $xpath->query('//marc:datafield[@tag="780"]/marc:subfield[@code="t" and text()="Jurnal Teknologi Lama"]/..')->item(0);
        $this->assertNotNull($field780);
        $field785 = $xpath->query('//marc:datafield[@tag="785"]/marc:subfield[@code="t" and text()="Jurnal Teknologi Baru"]/..')->item(0);
        $this->assertNotNull($field785);
    }
}
