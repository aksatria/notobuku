<?php

namespace Tests\Unit;

use App\Models\Author;
use App\Models\AuthorityAuthor;
use App\Models\AuthoritySubject;
use App\Models\AuthorityPublisher;
use App\Models\Biblio;
use App\Models\BiblioIdentifier;
use App\Models\Subject;
use App\Services\ExportService;
use App\Services\ImportService;
use App\Services\MetadataMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarcXmlFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_marcxml_includes_extended_fields(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-MARC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'The Art of Testing',
            'subtitle' => 'Field Coverage',
            'responsibility_statement' => 'Jane Doe',
            'normalized_title' => 'the art of testing field coverage',
            'publisher' => 'QA Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'id',
            'isbn' => '9786020000123',
            'issn' => '1234-5678',
            'edition' => 'Edisi 2',
            'series_title' => 'Seri Pengujian',
            'physical_desc' => 'x + 200 hlm',
            'illustrations' => 'il.',
            'dimensions' => '24 cm',
            'ddc' => '005.1',
            'call_number' => '005.1 ART',
            'notes' => 'Catatan umum',
            'bibliography_note' => 'Bibliografi: hlm. 180-190',
            'general_note' => 'Catatan tambahan',
            'ai_summary' => 'Ringkasan singkat',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $biblioUk = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'History of London',
            'normalized_title' => 'history of london',
            'publisher' => 'UK Press',
            'place_of_publication' => 'London',
            'publish_year' => 2021,
            'language' => 'en',
            'isbn' => '9786020000456',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $biblioEbook = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Digital Cataloging',
            'normalized_title' => 'digital cataloging',
            'publisher' => 'Online Press',
            'place_of_publication' => 'Singapore',
            'publish_year' => 2023,
            'language' => 'en',
            'isbn' => '9786020000789',
            'material_type' => 'ebook',
            'media_type' => 'online',
            'ai_status' => 'draft',
        ]);

        $biblioNewYork = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'New York Stories',
            'normalized_title' => 'new york stories',
            'publisher' => 'NY Press',
            'place_of_publication' => 'New York City',
            'publish_year' => 2020,
            'language' => 'en',
            'isbn' => '9786020000666',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $biblioVideo = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Learning with Video',
            'normalized_title' => 'learning with video',
            'publisher' => 'Media Press',
            'place_of_publication' => 'Tokyo',
            'publish_year' => 2020,
            'language' => 'en',
            'isbn' => '9786020000999',
            'material_type' => 'video',
            'media_type' => 'dvd',
            'ai_status' => 'draft',
        ]);

        $biblioAudio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Audio Learning',
            'normalized_title' => 'audio learning',
            'publisher' => 'Audio Press',
            'place_of_publication' => 'Osaka',
            'publish_year' => 2019,
            'language' => 'en',
            'isbn' => '9786020000888',
            'material_type' => 'audio',
            'media_type' => 'cd audio',
            'ai_status' => 'draft',
        ]);

        $biblioMeeting = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Proceedings of the Conference',
            'normalized_title' => 'proceedings of the conference',
            'publisher' => 'Event Press',
            'place_of_publication' => 'Bandung',
            'publish_year' => 2022,
            'language' => 'en',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);
        $biblioDupPublisher = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Corporate Report',
            'normalized_title' => 'corporate report',
            'publisher' => 'Universitas Contoh',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2022,
            'language' => 'id',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);
        $biblioDupPublisherNormalized = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Corporate Report 2',
            'normalized_title' => 'corporate report 2',
            'publisher' => 'PT. ABC',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2022,
            'language' => 'id',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $author1 = Author::create([
            'name' => 'Jane Doe',
            'normalized_name' => 'jane doe',
        ]);
        $author2 = Author::create([
            'name' => 'John Roe',
            'normalized_name' => 'john roe',
        ]);
        $author3 = Author::create([
            'name' => 'Alex Doe',
            'normalized_name' => 'alex doe',
        ]);
        $author4 = Author::create([
            'name' => 'Universitas Contoh',
            'normalized_name' => 'universitas contoh',
        ]);
        $author5 = Author::create([
            'name' => 'Rani Composer',
            'normalized_name' => 'rani composer',
        ]);
        $author6 = Author::create([
            'name' => 'Dedi Producer',
            'normalized_name' => 'dedi producer',
        ]);
        $author7 = Author::create([
            'name' => 'Konferensi Pendidikan 2025',
            'normalized_name' => 'konferensi pendidikan 2025',
        ]);
        $author8 = Author::create([
            'name' => 'National Science Conference',
            'normalized_name' => 'national science conference',
        ]);
        $biblio->authors()->sync([
            $author1->id => ['role' => 'pengarang', 'sort_order' => 1],
            $author2->id => ['role' => 'editor', 'sort_order' => 2],
            $author3->id => ['role' => 'xyz', 'sort_order' => 3],
            $author4->id => ['role' => 'organisasi', 'sort_order' => 4],
            $author5->id => ['role' => 'komposer', 'sort_order' => 5],
            $author6->id => ['role' => 'produser', 'sort_order' => 6],
            $author7->id => ['role' => 'conference', 'sort_order' => 7],
        ]);
        $biblioMeeting->authors()->sync([
            $author8->id => ['role' => 'conference', 'sort_order' => 1],
        ]);
        $biblioDupPublisher->authors()->sync([
            $author4->id => ['role' => 'organisasi', 'sort_order' => 1],
        ]);
        $author9 = Author::create([
            'name' => 'ABC',
            'normalized_name' => 'abc',
        ]);
        $biblioDupPublisherNormalized->authors()->sync([
            $author9->id => ['role' => 'organisasi', 'sort_order' => 1],
        ]);

        $subject = Subject::create([
            'name' => 'Software Testing',
            'term' => 'Software Testing',
            'normalized_term' => 'software testing',
            'scheme' => 'local',
        ]);
        $biblio->subjects()->sync([$subject->id => ['type' => 'topic', 'sort_order' => 1]]);

        BiblioIdentifier::create([
            'biblio_id' => $biblio->id,
            'scheme' => 'doi',
            'value' => '10.1234/abc',
            'normalized_value' => '10.1234/abc',
            'uri' => 'https://doi.org/10.1234/abc',
        ]);
        BiblioIdentifier::create([
            'biblio_id' => $biblio->id,
            'scheme' => 'uri',
            'value' => 'https://example.org/resource',
            'normalized_value' => 'https://example.org/resource',
            'uri' => 'https://example.org/resource',
        ]);
        BiblioIdentifier::create([
            'biblio_id' => $biblioEbook->id,
            'scheme' => 'uri',
            'value' => 'https://example.org/ebook',
            'normalized_value' => 'https://example.org/ebook',
            'uri' => 'https://example.org/ebook',
        ]);

        \App\Models\MarcSetting::create([
            'key' => 'place_codes_city',
            'value_json' => [
                'New York' => 'xxu',
                'York' => 'yy ',
            ],
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="022"]/marc:subfield[@code="a"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="250"]/marc:subfield[@code="a"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="300"]/marc:subfield[@code="a"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="336"]/marc:subfield[@code="a"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="337"]/marc:subfield[@code="a"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="338"]/marc:subfield[@code="a"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="490"]/marc:subfield[@code="a"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="504"]/marc:subfield[@code="a"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="520"]/marc:subfield[@code="a"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="082"]/marc:subfield[@code="a"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="090"]/marc:subfield[@code="a"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="700"]/marc:subfield[@code="a"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="024"]/marc:subfield[@code="a"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="856"]/marc:subfield[@code="u"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="040"]/marc:subfield[@code="a"]')->length);

        $field245 = $xpath->query('//marc:datafield[@tag="245"]')->item(0);
        $this->assertNotNull($field245);
        $this->assertSame('4', $field245->attributes?->getNamedItem('ind2')?->nodeValue);

        $field650 = $xpath->query('//marc:datafield[@tag="650"]')->item(0);
        $this->assertNotNull($field650);
        $this->assertSame('7', $field650->attributes?->getNamedItem('ind2')?->nodeValue);
        $this->assertGreaterThan(0, $xpath->query('//marc:datafield[@tag="650"]/marc:subfield[@code="2"]')->length);

        $field100e = $xpath->query('//marc:datafield[@tag="100"]/marc:subfield[@code="e"]')->item(0);
        $this->assertNotNull($field100e);
        $this->assertSame('author', (string) $field100e->textContent);
        $field1004 = $xpath->query('//marc:datafield[@tag="100"]/marc:subfield[@code="4"]')->item(0);
        $this->assertNotNull($field1004);
        $this->assertSame('aut', (string) $field1004->textContent);

        $field700e = $xpath->query('//marc:datafield[@tag="700"]/marc:subfield[@code="e"]')->item(0);
        $this->assertNotNull($field700e);
        $this->assertSame('editor', (string) $field700e->textContent);
        $field7004 = $xpath->query('//marc:datafield[@tag="700"]/marc:subfield[@code="4"]')->item(0);
        $this->assertNotNull($field7004);
        $this->assertSame('edt', (string) $field7004->textContent);

        $field7004Unknown = $xpath->query('//marc:datafield[@tag="700"]/marc:subfield[@code="4" and text()="xyz"]')->item(0);
        $this->assertNotNull($field7004Unknown);

        $field710 = $xpath->query('//marc:datafield[@tag="710"]/marc:subfield[@code="a" and text()="Universitas Contoh"]')->item(0);
        $this->assertNotNull($field710);

        $field7004Composer = $xpath->query('//marc:datafield[@tag="700"]/marc:subfield[@code="4" and text()="cmp"]')->item(0);
        $this->assertNotNull($field7004Composer);

        $field7004Producer = $xpath->query('//marc:datafield[@tag="700"]/marc:subfield[@code="4" and text()="pro"]')->item(0);
        $this->assertNotNull($field7004Producer);

        $control008 = $xpath->query('//marc:controlfield[@tag="008"]')->item(0);
        $this->assertNotNull($control008);
        $this->assertSame(40, strlen((string) $control008->textContent));
        $this->assertSame('io ', substr((string) $control008->textContent, 15, 3));

        $control006 = $xpath->query('//marc:controlfield[@tag="006"]')->item(0);
        $this->assertNotNull($control006);
        $this->assertSame(18, strlen((string) $control006->textContent));
        $this->assertSame('a', substr((string) $control006->textContent, 0, 1));

        $control007 = $xpath->query('//marc:controlfield[@tag="007"]')->item(0);
        $this->assertNotNull($control007);
        $this->assertSame('ta', (string) $control007->textContent);

        $recordUk = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='History of London']")->item(0);
        $this->assertNotNull($recordUk);
        $this->assertNotNull($xpath->query('.//marc:datafield[@tag="110"]/marc:subfield[@code="a" and text()="UK Press"]', $recordUk)->item(0));
        $control008Uk = $xpath->query('.//marc:controlfield[@tag="008"]', $recordUk)->item(0);
        $this->assertNotNull($control008Uk);
        $this->assertSame('xxk', substr((string) $control008Uk->textContent, 15, 3));

        $recordNy = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='New York Stories']")->item(0);
        $this->assertNotNull($recordNy);
        $control008Ny = $xpath->query('.//marc:controlfield[@tag="008"]', $recordNy)->item(0);
        $this->assertNotNull($control008Ny);
        $this->assertSame('xxu', substr((string) $control008Ny->textContent, 15, 3));

        $recordEbook = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Digital Cataloging']")->item(0);
        $this->assertNotNull($recordEbook);
        $control006Ebook = $xpath->query('.//marc:controlfield[@tag="006"]', $recordEbook)->item(0);
        $this->assertNotNull($control006Ebook);
        $this->assertSame('m', substr((string) $control006Ebook->textContent, 0, 1));
        $control007Ebook = $xpath->query('.//marc:controlfield[@tag="007"]', $recordEbook)->item(0);
        $this->assertNotNull($control007Ebook);
        $this->assertSame('cr', substr((string) $control007Ebook->textContent, 0, 2));

        $recordVideo = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Learning with Video']")->item(0);
        $this->assertNotNull($recordVideo);
        $control006Video = $xpath->query('.//marc:controlfield[@tag="006"]', $recordVideo)->item(0);
        $this->assertNotNull($control006Video);
        $this->assertSame('g', substr((string) $control006Video->textContent, 0, 1));
        $control007Video = $xpath->query('.//marc:controlfield[@tag="007"]', $recordVideo)->item(0);
        $this->assertNotNull($control007Video);
        $this->assertSame('vd', substr((string) $control007Video->textContent, 0, 2));

        $recordAudio = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Audio Learning']")->item(0);
        $this->assertNotNull($recordAudio);
        $control006Audio = $xpath->query('.//marc:controlfield[@tag="006"]', $recordAudio)->item(0);
        $this->assertNotNull($control006Audio);
        $this->assertSame('i', substr((string) $control006Audio->textContent, 0, 1));
        $control007Audio = $xpath->query('.//marc:controlfield[@tag="007"]', $recordAudio)->item(0);
        $this->assertNotNull($control007Audio);
        $this->assertSame('sd', substr((string) $control007Audio->textContent, 0, 2));

        $recordMeeting = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Proceedings of the Conference']")->item(0);
        $this->assertNotNull($recordMeeting);
        $this->assertNotNull($xpath->query('.//marc:datafield[@tag="111"]/marc:subfield[@code="a" and text()="National Science Conference"]', $recordMeeting)->item(0));

        $field711 = $xpath->query('//marc:datafield[@tag="711"]/marc:subfield[@code="a" and text()="Konferensi Pendidikan 2025"]')->item(0);
        $this->assertNotNull($field711);

        $recordDupPublisher = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Corporate Report']")->item(0);
        $this->assertNotNull($recordDupPublisher);
        $this->assertNotNull($xpath->query('.//marc:datafield[@tag="110"]/marc:subfield[@code="a" and text()="Universitas Contoh"]', $recordDupPublisher)->item(0));
        $this->assertSame(0, $xpath->query('.//marc:datafield[@tag="710"]/marc:subfield[@code="a" and text()="Universitas Contoh"]', $recordDupPublisher)->length);

        $recordDupPublisherNormalized = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Corporate Report 2']")->item(0);
        $this->assertNotNull($recordDupPublisherNormalized);
        $this->assertNotNull($xpath->query('.//marc:datafield[@tag="110"]/marc:subfield[@code="a" and text()="ABC"]', $recordDupPublisherNormalized)->item(0));
        $this->assertSame(0, $xpath->query('.//marc:datafield[@tag="710"]/marc:subfield[@code="a" and text()="PT. ABC"]', $recordDupPublisherNormalized)->length);
    }

    public function test_import_marcxml_maps_extended_fields(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-IM',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<collection xmlns="http://www.loc.gov/MARC21/slim">
  <record>
    <leader>00000nam a2200000 a 4500</leader>
    <controlfield tag="001">123</controlfield>
    <controlfield tag="003">TEST-IM</controlfield>
    <controlfield tag="005">20260209101010</controlfield>
    <controlfield tag="008">250209s2024    xxu                 ind  </controlfield>
    <datafield tag="022" ind1=" " ind2=" ">
      <subfield code="a">1234-5678</subfield>
    </datafield>
    <datafield tag="100" ind1="1" ind2=" ">
      <subfield code="a">Jane Doe</subfield>
      <subfield code="e">author</subfield>
    </datafield>
    <datafield tag="700" ind1="1" ind2=" ">
      <subfield code="a">John Roe</subfield>
      <subfield code="4">edt</subfield>
    </datafield>
    <datafield tag="700" ind1="1" ind2=" ">
      <subfield code="a">Alex Doe</subfield>
      <subfield code="4">xyz</subfield>
    </datafield>
    <datafield tag="110" ind1="2" ind2=" ">
      <subfield code="a">Universitas Contoh</subfield>
      <subfield code="e">corporate</subfield>
    </datafield>
    <datafield tag="111" ind1="2" ind2=" ">
      <subfield code="a">Konferensi Pendidikan 2025</subfield>
      <subfield code="e">meeting</subfield>
    </datafield>
    <datafield tag="700" ind1="1" ind2=" ">
      <subfield code="a">Rani Composer</subfield>
      <subfield code="4">cmp</subfield>
    </datafield>
    <datafield tag="700" ind1="1" ind2=" ">
      <subfield code="a">Dedi Producer</subfield>
      <subfield code="4">pro</subfield>
    </datafield>
    <datafield tag="711" ind1="2" ind2=" ">
      <subfield code="a">National Science Conference</subfield>
      <subfield code="4">mtg</subfield>
    </datafield>
    <datafield tag="245" ind1="1" ind2="4">
      <subfield code="a">The Art of Testing</subfield>
      <subfield code="b">Field Coverage</subfield>
      <subfield code="c">Jane Doe</subfield>
    </datafield>
    <datafield tag="250" ind1=" " ind2=" ">
      <subfield code="a">Edisi 2</subfield>
    </datafield>
    <datafield tag="264" ind1=" " ind2="1">
      <subfield code="a">Jakarta</subfield>
      <subfield code="b">QA Press</subfield>
      <subfield code="c">2024</subfield>
    </datafield>
    <datafield tag="300" ind1=" " ind2=" ">
      <subfield code="a">x + 200 hlm</subfield>
      <subfield code="b">il.</subfield>
      <subfield code="c">24 cm</subfield>
    </datafield>
    <datafield tag="490" ind1="1" ind2=" ">
      <subfield code="a">Seri Pengujian</subfield>
    </datafield>
    <datafield tag="650" ind1=" " ind2="7">
      <subfield code="a">Software Testing</subfield>
      <subfield code="2">local</subfield>
    </datafield>
    <datafield tag="504" ind1=" " ind2=" ">
      <subfield code="a">Bibliografi: hlm. 180-190</subfield>
    </datafield>
    <datafield tag="520" ind1=" " ind2=" ">
      <subfield code="a">Ringkasan singkat</subfield>
    </datafield>
    <datafield tag="082" ind1="0" ind2="4">
      <subfield code="a">005.1</subfield>
    </datafield>
    <datafield tag="090" ind1=" " ind2=" ">
      <subfield code="a">005.1 ART</subfield>
    </datafield>
    <datafield tag="024" ind1="7" ind2=" ">
      <subfield code="a">10.1234/abc</subfield>
      <subfield code="2">doi</subfield>
    </datafield>
    <datafield tag="856" ind1="4" ind2="0">
      <subfield code="u">https://example.org/resource</subfield>
    </datafield>
  </record>
</collection>
XML;

        $path = tempnam(sys_get_temp_dir(), 'marcxml_');
        file_put_contents($path, $xml);

        $service = new ImportService(new MetadataMappingService());
        $report = $service->importByFormatFromPath('marcxml', $path, $institutionId, null);

        $this->assertSame(1, $report['created']);
        $this->assertSame(0, $report['updated']);
        $this->assertSame(0, $report['skipped']);
        $this->assertEmpty($report['errors']);

        $biblio = Biblio::query()->where('institution_id', $institutionId)->first();
        $this->assertNotNull($biblio);
        $this->assertSame('The Art of Testing', $biblio->title);
        $this->assertSame('Field Coverage', $biblio->subtitle);
        $this->assertSame('Jane Doe', $biblio->responsibility_statement);
        $this->assertSame('QA Press', $biblio->publisher);
        $this->assertSame('Jakarta', $biblio->place_of_publication);
        $this->assertSame(2024, $biblio->publish_year);
        $this->assertSame('1234-5678', $biblio->issn);
        $this->assertSame('Edisi 2', $biblio->edition);
        $this->assertSame('Seri Pengujian', $biblio->series_title);
        $this->assertSame('x + 200 hlm', $biblio->physical_desc);
        $this->assertSame('il.', $biblio->illustrations);
        $this->assertSame('24 cm', $biblio->dimensions);
        $this->assertSame('005.1', $biblio->ddc);
        $this->assertSame('005.1 ART', $biblio->call_number);
        $this->assertSame('Bibliografi: hlm. 180-190', $biblio->bibliography_note);
        $this->assertSame('Ringkasan singkat', $biblio->notes);

        $authors = $biblio->authors()->withPivot('role')->get();
        $roleMap = $authors->mapWithKeys(fn($a) => [$a->name => $a->pivot->role])->all();
        $this->assertSame('pengarang', $roleMap['Jane Doe'] ?? null);
        $this->assertSame('editor', $roleMap['John Roe'] ?? null);
        $this->assertSame('xyz', $roleMap['Alex Doe'] ?? null);
        $this->assertSame('organisasi', $roleMap['Universitas Contoh'] ?? null);
        $this->assertSame('meeting', $roleMap['Konferensi Pendidikan 2025'] ?? null);
        $this->assertSame('komposer', $roleMap['Rani Composer'] ?? null);
        $this->assertSame('produser', $roleMap['Dedi Producer'] ?? null);
        $this->assertSame('meeting', $roleMap['National Science Conference'] ?? null);

        $this->assertDatabaseHas('biblio_identifiers', [
            'biblio_id' => $biblio->id,
            'scheme' => 'doi',
        ]);
        $this->assertDatabaseHas('biblio_identifiers', [
            'biblio_id' => $biblio->id,
            'scheme' => 'uri',
        ]);
    }

    public function test_preview_marcxml_includes_meeting_fields(): void
    {
        $service = new ExportService(new MetadataMappingService());

        $xmlMainMeeting = $service->buildMarcPreview([
            'title' => 'Meeting Only',
            'meeting_names' => 'Seminar Nasional 2025',
            'meeting_ind1' => '2',
        ], 'TEST');

        $docMain = new \DOMDocument();
        $this->assertTrue($docMain->loadXML($xmlMainMeeting));
        $xpathMain = new \DOMXPath($docMain);
        $xpathMain->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');
        $field111 = $xpathMain->query('//marc:datafield[@tag="111"]/marc:subfield[@code="a" and text()="Seminar Nasional 2025"]/..')->item(0);
        $this->assertNotNull($field111);
        $this->assertSame('2', $field111->attributes?->getNamedItem('ind1')?->nodeValue);

        $xmlSecondaryMeeting = $service->buildMarcPreview([
            'title' => 'Meeting + Author',
            'author' => 'Jane Doe',
            'author_role' => 'pengarang',
            'meeting_names' => 'Konferensi Pendidikan 2025',
            'meeting_ind1' => '1',
        ], 'TEST');

        $docSecondary = new \DOMDocument();
        $this->assertTrue($docSecondary->loadXML($xmlSecondaryMeeting));
        $xpathSecondary = new \DOMXPath($docSecondary);
        $xpathSecondary->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');
        $field711 = $xpathSecondary->query('//marc:datafield[@tag="711"]/marc:subfield[@code="a" and text()="Konferensi Pendidikan 2025"]/..')->item(0);
        $this->assertNotNull($field711);
        $this->assertSame('1', $field711->attributes?->getNamedItem('ind1')?->nodeValue);

        $xmlForcedMain = $service->buildMarcPreview([
            'title' => 'Meeting Forced Main',
            'author' => 'Jane Doe',
            'author_role' => 'pengarang',
            'meeting_names' => 'Rapat Tahunan 2025',
            'meeting_ind1' => '0',
            'force_meeting_main' => true,
        ], 'TEST');

        $docForced = new \DOMDocument();
        $this->assertTrue($docForced->loadXML($xmlForcedMain));
        $xpathForced = new \DOMXPath($docForced);
        $xpathForced->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');
        $field111Forced = $xpathForced->query('//marc:datafield[@tag="111"]/marc:subfield[@code="a" and text()="Rapat Tahunan 2025"]/..')->item(0);
        $this->assertNotNull($field111Forced);
        $this->assertSame('0', $field111Forced->attributes?->getNamedItem('ind1')?->nodeValue);
    }

    public function test_preview_marcxml_includes_validation_comment_when_missing_required_fields(): void
    {
        $service = new ExportService(new MetadataMappingService());

        $xml = $service->buildMarcPreview([
            'title' => '',
            'place_of_publication' => '',
            'publish_year' => null,
            'language' => '',
        ], 'TEST');

        $this->assertStringContainsString('VALIDATION ERRORS', $xml);
    }

    public function test_export_uses_profile_specific_008_pattern(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-008',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\MarcSetting::create([
            'key' => 'media_profiles',
            'value_json' => [
                [
                    'name' => 'visual',
                    'keywords' => ['video'],
                    'pattern_006' => 'g                 ',
                    'pattern_007' => 'vd cvaizq',
                    'pattern_008_visual' => '{entered}{status}{date1}{date2}{place}z           {lang}  ',
                ],
            ],
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Visual Record',
            'normalized_title' => 'visual record',
            'publisher' => 'Media Press',
            'place_of_publication' => 'Tokyo',
            'publish_year' => 2020,
            'language' => 'en',
            'isbn' => '9786020000100',
            'material_type' => 'video',
            'media_type' => 'dvd',
            'ai_status' => 'draft',
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');
        $record = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Visual Record']")->item(0);
        $this->assertNotNull($record);
        $control008 = $xpath->query('.//marc:controlfield[@tag="008"]', $record)->item(0);
        $this->assertNotNull($control008);
        $value = (string) $control008->textContent;
        $this->assertSame(40, strlen($value));
        $this->assertSame('z', $value[18]);
    }

    public function test_export_uses_music_profile_even_when_media_contains_audio(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Music Audio',
            'code' => 'TEST-MUSIC-AUDIO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $patternAudio = '{entered}{status}{date1}{date2}{place}A' . str_repeat(' ', 16) . '{lang}  ';
        $patternMusic = '{entered}{status}{date1}{date2}{place}M' . str_repeat(' ', 16) . '{lang}  ';

        \App\Models\MarcSetting::create([
            'key' => 'media_profiles',
            'value_json' => [
                [
                    'name' => 'audio_music',
                    'keywords' => ['music', 'album'],
                    'type_006' => 'j',
                    'type_007' => 'sd',
                    'pattern_006' => 'j                 ',
                    'pattern_007' => 'sd fmnngnn',
                    'min_007' => 7,
                    'pattern_008_music' => $patternMusic,
                ],
                [
                    'name' => 'audio',
                    'keywords' => ['audio'],
                    'type_006' => 'i',
                    'type_007' => 'sd',
                    'pattern_006' => 'i                 ',
                    'pattern_007' => 'sd fmnngnn',
                    'min_007' => 7,
                    'pattern_008_audio' => $patternAudio,
                ],
            ],
        ]);

        $music = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Music Record',
            'normalized_title' => 'music record',
            'publisher' => 'Music Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'id',
            'material_type' => 'music',
            'media_type' => 'cd audio',
            'ai_status' => 'draft',
        ]);

        $audio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Audio Record',
            'normalized_title' => 'audio record',
            'publisher' => 'Audio Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'id',
            'material_type' => 'audio',
            'media_type' => 'cd audio',
            'ai_status' => 'draft',
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $musicRecord = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Music Record']")->item(0);
        $this->assertNotNull($musicRecord);
        $music008 = $xpath->query('.//marc:controlfield[@tag="008"]', $musicRecord)->item(0);
        $this->assertNotNull($music008);
        $this->assertSame('M', ((string) $music008->textContent)[18]);

        $audioRecord = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Audio Record']")->item(0);
        $this->assertNotNull($audioRecord);
        $audio008 = $xpath->query('.//marc:controlfield[@tag="008"]', $audioRecord)->item(0);
        $this->assertNotNull($audio008);
        $this->assertSame('A', ((string) $audio008->textContent)[18]);
    }

    public function test_subject_rules_rda3xx_and_non_filing_indicator(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-POLICY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'The Art of Cataloging',
            'normalized_title' => 'the art of cataloging',
            'publisher' => 'Policy Press',
            'place_of_publication' => 'London',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'video',
            'media_type' => 'online',
            'ai_status' => 'draft',
        ]);

        $author = Author::create([
            'name' => 'Jane Doe',
            'normalized_name' => 'jane doe',
        ]);
        $biblio->authors()->sync([
            $author->id => ['role' => 'pengarang', 'sort_order' => 1],
        ]);

        $subjectLocal = Subject::create([
            'name' => 'Local Topic',
            'term' => 'Local Topic',
            'normalized_term' => 'local topic',
            'scheme' => 'local',
        ]);
        $subjectLcsh = Subject::create([
            'name' => 'Library science',
            'term' => 'Library science',
            'normalized_term' => 'library science',
            'scheme' => 'lcsh',
        ]);
        $subjectGeo = Subject::create([
            'name' => 'Indonesia',
            'term' => 'Indonesia',
            'normalized_term' => 'indonesia',
            'scheme' => 'local',
        ]);

        $biblio->subjects()->sync([
            $subjectLocal->id => ['type' => 'topic', 'sort_order' => 1],
            $subjectLcsh->id => ['type' => 'topic', 'sort_order' => 2],
            $subjectGeo->id => ['type' => 'geographic', 'sort_order' => 3],
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

        $field245 = $xpath->query('//marc:datafield[@tag="245"]')->item(0);
        $this->assertNotNull($field245);
        $this->assertSame('4', $field245->attributes?->getNamedItem('ind2')?->nodeValue);

        $local650 = $xpath->query('//marc:datafield[@tag="650"]/marc:subfield[@code="a" and text()="Local Topic"]/..')->item(0);
        $this->assertNotNull($local650);
        $this->assertSame('7', $local650->attributes?->getNamedItem('ind2')?->nodeValue);
        $this->assertNotNull($xpath->query('.//marc:subfield[@code="2" and text()="local"]', $local650)->item(0));

        $lcsh650 = $xpath->query('//marc:datafield[@tag="650"]/marc:subfield[@code="a" and text()="Library science"]/..')->item(0);
        $this->assertNotNull($lcsh650);
        $this->assertSame('0', $lcsh650->attributes?->getNamedItem('ind2')?->nodeValue);
        $this->assertSame(0, $xpath->query('.//marc:subfield[@code="2"]', $lcsh650)->length);

        $geo651 = $xpath->query('//marc:datafield[@tag="651"]/marc:subfield[@code="a" and text()="Indonesia"]/..')->item(0);
        $this->assertNotNull($geo651);

        $field336 = $xpath->query('//marc:datafield[@tag="336"]/marc:subfield[@code="a" and text()="two-dimensional moving image"]/..')->item(0);
        $this->assertNotNull($field336);
        $field337 = $xpath->query('//marc:datafield[@tag="337"]/marc:subfield[@code="a" and text()="computer"]/..')->item(0);
        $this->assertNotNull($field337);
        $field338 = $xpath->query('//marc:datafield[@tag="338"]/marc:subfield[@code="a" and text()="online resource"]/..')->item(0);
        $this->assertNotNull($field338);
    }

    public function test_export_includes_holdings_fields_from_items(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Holdings',
            'code' => 'TEST-HOLD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Main Branch',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Holding Title',
            'normalized_title' => 'holding title',
            'publisher' => 'Hold Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'id',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'call_number' => '001.23',
            'ai_status' => 'draft',
        ]);

        \App\Models\Item::create([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'biblio_id' => $biblio->id,
            'barcode' => 'BC-001',
            'accession_number' => 'ACC-001',
            'inventory_number' => 'INV-001',
            'location_note' => 'Rak A1',
            'status' => 'available',
            'condition' => 'good',
            'acquisition_source' => 'Donation',
            'source' => 'Vendor A',
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $record = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Holding Title']")->item(0);
        $this->assertNotNull($record);
        $field852 = $xpath->query('.//marc:datafield[@tag="852"]', $record)->item(0);
        $this->assertNotNull($field852);
        $this->assertNotNull($xpath->query('.//marc:datafield[@tag="852"]/marc:subfield[@code="b" and text()="Main Branch"]', $record)->item(0));
        $this->assertNotNull($xpath->query('.//marc:datafield[@tag="852"]/marc:subfield[@code="p" and text()="BC-001"]', $record)->item(0));

        $field876 = $xpath->query('.//marc:datafield[@tag="876"]', $record)->item(0);
        $this->assertNotNull($field876);
        $this->assertNotNull($xpath->query('.//marc:datafield[@tag="876"]/marc:subfield[@code="p" and text()="BC-001"]', $record)->item(0));
        $this->assertNotNull($xpath->query('.//marc:datafield[@tag="877"]/marc:subfield[@code="a" and text()="Donation"]', $record)->item(0));
        $this->assertNotNull($xpath->query('.//marc:datafield[@tag="878"]/marc:subfield[@code="a" and text()="Vendor A"]', $record)->item(0));
    }

    public function test_export_includes_holdings_summary_fields(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Holdings Summary',
            'code' => 'TEST-HOLD-SUM',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Holdings Summary Title',
            'normalized_title' => 'holdings summary title',
            'publisher' => 'Holdings Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'id',
            'material_type' => 'serial',
            'media_type' => 'teks',
            'holdings_summary' => 'Vol. 1 (2020)-Vol. 5 (2024)',
            'holdings_supplement' => 'Suplemen Tahunan 2023',
            'holdings_index' => 'Indeks Vol. 1-5',
            'ai_status' => 'draft',
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $record = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Holdings Summary Title']")->item(0);
        $this->assertNotNull($record);
        $this->assertNotNull($xpath->query('.//marc:datafield[@tag="866"]/marc:subfield[@code="a" and contains(text(),"Vol. 1")]', $record)->item(0));
        $this->assertNotNull($xpath->query('.//marc:datafield[@tag="867"]/marc:subfield[@code="a" and contains(text(),"Suplemen")]', $record)->item(0));
        $this->assertNotNull($xpath->query('.//marc:datafield[@tag="868"]/marc:subfield[@code="a" and contains(text(),"Indeks")]', $record)->item(0));
    }

    public function test_export_cleans_title_leading_punctuation(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Clean Title',
            'code' => 'TEST-TITLE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => '\"The Clean Title\"',
            'normalized_title' => 'the clean title',
            'publisher' => 'Clean Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $field245 = $xpath->query('//marc:datafield[@tag="245"]/marc:subfield[@code="a"]')->item(0);
        $this->assertNotNull($field245);
        $this->assertSame('The Clean Title', (string) $field245->textContent);
    }

    public function test_export_includes_authority_uri_in_subfield_1(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Auth',
            'code' => 'TEST-AUTH-URI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $author = Author::create([
            'name' => 'Jane Doe',
            'normalized_name' => 'jane doe',
        ]);

        AuthorityAuthor::create([
            'preferred_name' => 'Jane Doe',
            'normalized_name' => 'jane doe',
            'external_ids' => [
                'lcnaf' => 'n12345678',
                'viaf' => '987654',
                'isni' => '0000000123456789',
            ],
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Authority Title',
            'normalized_title' => 'authority title',
            'publisher' => 'Auth Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $biblio->authors()->sync([
            $author->id => ['role' => 'pengarang', 'sort_order' => 1],
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $field100 = $xpath->query('//marc:datafield[@tag="100"]/marc:subfield[@code="a" and text()="Jane Doe"]/..')->item(0);
        $this->assertNotNull($field100);
        $this->assertNotNull($xpath->query('.//marc:subfield[@code="1" and text()="http://id.loc.gov/authorities/names/n12345678"]', $field100)->item(0));
    }

    public function test_export_includes_subject_authority_uri_in_subfield_1(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Subject Auth',
            'code' => 'TEST-SUB-AUTH',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subject = Subject::create([
            'name' => 'Library science',
            'term' => 'Library science',
            'normalized_term' => 'library science',
            'scheme' => 'lcsh',
        ]);

        AuthoritySubject::create([
            'preferred_term' => 'Library science',
            'normalized_term' => 'library science',
            'scheme' => 'lcsh',
            'external_ids' => [
                'viaf' => '24681012',
            ],
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Subject Authority',
            'normalized_title' => 'subject authority',
            'publisher' => 'Sub Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $biblio->subjects()->sync([
            $subject->id => ['type' => 'topic', 'sort_order' => 1],
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $field650 = $xpath->query('//marc:datafield[@tag="650"]/marc:subfield[@code="a" and text()="Library science"]/..')->item(0);
        $this->assertNotNull($field650);
        $this->assertNotNull($xpath->query('.//marc:subfield[@code="1" and text()="https://viaf.org/viaf/24681012"]', $field650)->item(0));
    }

    public function test_export_corporate_authority_uri_uses_viaf_priority_fallback(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Corporate Auth',
            'code' => 'TEST-CORP-AUTH',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $author = Author::create([
            'name' => 'Example Corporation',
            'normalized_name' => 'example corporation',
        ]);

        AuthorityAuthor::create([
            'preferred_name' => 'Example Corporation',
            'normalized_name' => 'example corporation',
            'external_ids' => [
                'viaf' => '112233',
                'isni' => '0000000112233445',
            ],
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Corporate Authority',
            'normalized_title' => 'corporate authority',
            'publisher' => 'Corp Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $biblio->authors()->sync([
            $author->id => ['role' => 'corporate', 'sort_order' => 1],
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $field110 = $xpath->query('//marc:datafield[@tag="110"]/marc:subfield[@code="a" and text()="Example Corporation"]/..')->item(0);
        $this->assertNotNull($field110);
        $this->assertNotNull($xpath->query('.//marc:subfield[@code="1" and text()="https://viaf.org/viaf/112233"]', $field110)->item(0));
    }

    public function test_export_added_corporate_authority_uri_in_710(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Added Corporate',
            'code' => 'TEST-710-AUTH',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mainAuthor = Author::create([
            'name' => 'Jane Doe',
            'normalized_name' => 'jane doe',
        ]);
        AuthorityAuthor::create([
            'preferred_name' => 'Jane Doe',
            'normalized_name' => 'jane doe',
        ]);

        $corpAuthor = Author::create([
            'name' => 'Example Corporation',
            'normalized_name' => 'example corporation',
        ]);
        AuthorityAuthor::create([
            'preferred_name' => 'Example Corporation',
            'normalized_name' => 'example corporation',
            'external_ids' => [
                'viaf' => '998877',
            ],
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Added Corporate Authority',
            'normalized_title' => 'added corporate authority',
            'publisher' => 'Corp Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $biblio->authors()->sync([
            $mainAuthor->id => ['role' => 'pengarang', 'sort_order' => 1],
            $corpAuthor->id => ['role' => 'corporate', 'sort_order' => 2],
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $field710 = $xpath->query('//marc:datafield[@tag="710"]/marc:subfield[@code="a" and text()="Example Corporation"]/..')->item(0);
        $this->assertNotNull($field710);
        $this->assertNotNull($xpath->query('.//marc:subfield[@code="1" and text()="https://viaf.org/viaf/998877"]', $field710)->item(0));
    }

    public function test_export_authority_uri_falls_back_to_isni_when_viaf_missing(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution ISNI',
            'code' => 'TEST-ISNI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $author = Author::create([
            'name' => 'ISNI Author',
            'normalized_name' => 'isni author',
        ]);

        AuthorityAuthor::create([
            'preferred_name' => 'ISNI Author',
            'normalized_name' => 'isni author',
            'external_ids' => [
                'isni' => '0000000123456789',
            ],
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'ISNI Fallback',
            'normalized_title' => 'isni fallback',
            'publisher' => 'ISNI Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $biblio->authors()->sync([
            $author->id => ['role' => 'pengarang', 'sort_order' => 1],
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $field100 = $xpath->query('//marc:datafield[@tag="100"]/marc:subfield[@code="a" and text()="ISNI Author"]/..')->item(0);
        $this->assertNotNull($field100);
        $this->assertNotNull($xpath->query('.//marc:subfield[@code="1" and text()="https://isni.org/isni/0000000123456789"]', $field100)->item(0));
    }

    public function test_export_authority_uri_falls_back_to_wikidata_when_viaf_and_isni_missing(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Wikidata',
            'code' => 'TEST-WD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $author = Author::create([
            'name' => 'Wikidata Author',
            'normalized_name' => 'wikidata author',
        ]);

        AuthorityAuthor::create([
            'preferred_name' => 'Wikidata Author',
            'normalized_name' => 'wikidata author',
            'external_ids' => [
                'wikidata' => 'Q123456',
            ],
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Wikidata Fallback',
            'normalized_title' => 'wikidata fallback',
            'publisher' => 'WD Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $biblio->authors()->sync([
            $author->id => ['role' => 'pengarang', 'sort_order' => 1],
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $field100 = $xpath->query('//marc:datafield[@tag="100"]/marc:subfield[@code="a" and text()="Wikidata Author"]/..')->item(0);
        $this->assertNotNull($field100);
        $this->assertNotNull($xpath->query('.//marc:subfield[@code="1" and text()="https://www.wikidata.org/entity/Q123456"]', $field100)->item(0));
    }

    public function test_export_authority_uri_falls_back_to_custom_uri_when_all_missing(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Custom URI',
            'code' => 'TEST-URI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $author = Author::create([
            'name' => 'Custom URI Author',
            'normalized_name' => 'custom uri author',
        ]);

        AuthorityAuthor::create([
            'preferred_name' => 'Custom URI Author',
            'normalized_name' => 'custom uri author',
            'external_ids' => [
                'uri' => 'https://authority.example.org/author/abc123',
            ],
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Custom URI Fallback',
            'normalized_title' => 'custom uri fallback',
            'publisher' => 'URI Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $biblio->authors()->sync([
            $author->id => ['role' => 'pengarang', 'sort_order' => 1],
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $field100 = $xpath->query('//marc:datafield[@tag="100"]/marc:subfield[@code="a" and text()="Custom URI Author"]/..')->item(0);
        $this->assertNotNull($field100);
        $this->assertNotNull($xpath->query('.//marc:subfield[@code="1" and text()="https://authority.example.org/author/abc123"]', $field100)->item(0));
    }

    public function test_export_subject_authority_uri_falls_back_to_custom_uri(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Subject URI',
            'code' => 'TEST-SUB-URI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subject = Subject::create([
            'name' => 'Custom Subject',
            'term' => 'Custom Subject',
            'normalized_term' => 'custom subject',
            'scheme' => 'local',
        ]);

        AuthoritySubject::create([
            'preferred_term' => 'Custom Subject',
            'normalized_term' => 'custom subject',
            'scheme' => 'local',
            'external_ids' => [
                'uri' => 'https://authority.example.org/subject/xyz999',
            ],
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Subject URI Fallback',
            'normalized_title' => 'subject uri fallback',
            'publisher' => 'URI Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $biblio->subjects()->sync([
            $subject->id => ['type' => 'topic', 'sort_order' => 1],
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $field650 = $xpath->query('//marc:datafield[@tag="650"]/marc:subfield[@code="a" and text()="Custom Subject"]/..')->item(0);
        $this->assertNotNull($field650);
        $this->assertNotNull($xpath->query('.//marc:subfield[@code="1" and text()="https://authority.example.org/subject/xyz999"]', $field650)->item(0));
    }

    public function test_export_corporate_authority_uri_falls_back_to_custom_uri(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Corporate URI',
            'code' => 'TEST-CORP-URI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mainAuthor = Author::create([
            'name' => 'Jane Doe',
            'normalized_name' => 'jane doe',
        ]);
        AuthorityAuthor::create([
            'preferred_name' => 'Jane Doe',
            'normalized_name' => 'jane doe',
        ]);

        $corpAuthor = Author::create([
            'name' => 'Corp URI Org',
            'normalized_name' => 'corp uri org',
        ]);
        AuthorityAuthor::create([
            'preferred_name' => 'Corp URI Org',
            'normalized_name' => 'corp uri org',
            'external_ids' => [
                'uri' => 'https://authority.example.org/corp/def456',
            ],
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Corporate URI Fallback',
            'normalized_title' => 'corporate uri fallback',
            'publisher' => 'URI Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $biblio->authors()->sync([
            $mainAuthor->id => ['role' => 'pengarang', 'sort_order' => 1],
            $corpAuthor->id => ['role' => 'corporate', 'sort_order' => 2],
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $field710 = $xpath->query('//marc:datafield[@tag="710"]/marc:subfield[@code="a" and text()="Corp URI Org"]/..')->item(0);
        $this->assertNotNull($field710);
        $this->assertNotNull($xpath->query('.//marc:subfield[@code="1" and text()="https://authority.example.org/corp/def456"]', $field710)->item(0));
    }

    public function test_export_meeting_authority_uri_in_111_and_711(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution Meeting Auth',
            'code' => 'TEST-MEET-AUTH',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $meetingMain = Author::create([
            'name' => 'Annual Research Conference',
            'normalized_name' => 'annual research conference',
        ]);
        AuthorityAuthor::create([
            'preferred_name' => 'Annual Research Conference',
            'normalized_name' => 'annual research conference',
            'external_ids' => [
                'viaf' => '445566',
            ],
        ]);

        $meetingAdded = Author::create([
            'name' => 'Library Symposium 2024',
            'normalized_name' => 'library symposium 2024',
        ]);
        AuthorityAuthor::create([
            'preferred_name' => 'Library Symposium 2024',
            'normalized_name' => 'library symposium 2024',
            'external_ids' => [
                'viaf' => '778899',
            ],
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Meeting Authority',
            'normalized_title' => 'meeting authority',
            'publisher' => 'Meeting Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $biblio->authors()->sync([
            $meetingMain->id => ['role' => 'meeting', 'sort_order' => 1],
            $meetingAdded->id => ['role' => 'meeting', 'sort_order' => 2],
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $field111 = $xpath->query('//marc:datafield[@tag="111"]/marc:subfield[@code="a" and text()="Annual Research Conference"]/..')->item(0);
        $this->assertNotNull($field111);
        $this->assertNotNull($xpath->query('.//marc:subfield[@code="1" and text()="https://viaf.org/viaf/445566"]', $field111)->item(0));

        $field711 = $xpath->query('//marc:datafield[@tag="711"]/marc:subfield[@code="a" and text()="Library Symposium 2024"]/..')->item(0);
        $this->assertNotNull($field711);
        $this->assertNotNull($xpath->query('.//marc:subfield[@code="1" and text()="https://viaf.org/viaf/778899"]', $field711)->item(0));
    }

    public function test_non_filing_indicator_handles_leading_quotes_and_brackets(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-QUOTE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Biblio::create([
            'institution_id' => $institutionId,
            'title' => '“The Art of Cataloging”',
            'normalized_title' => 'the art of cataloging',
            'publisher' => 'Quote Press',
            'place_of_publication' => 'London',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $field245 = $xpath->query('//marc:datafield[@tag="245"]')->item(0);
        $this->assertNotNull($field245);
        $this->assertSame('4', $field245->attributes?->getNamedItem('ind2')?->nodeValue);
    }

    public function test_dedup_856_and_audiobook_soundtrack_3xx(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-856',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $audiobook = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Audiobook Learning',
            'normalized_title' => 'audiobook learning',
            'publisher' => 'Audio Lab',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'audiobook',
            'media_type' => 'online',
            'ai_status' => 'draft',
        ]);

        $author = Author::create([
            'name' => 'Narrator A',
            'normalized_name' => 'narrator a',
        ]);
        $audiobook->authors()->sync([
            $author->id => ['role' => 'pengarang', 'sort_order' => 1],
        ]);

        BiblioIdentifier::create([
            'biblio_id' => $audiobook->id,
            'scheme' => 'uri',
            'value' => 'https://example.org/audio',
            'normalized_value' => 'https://example.org/audio',
            'uri' => 'https://example.org/audio',
        ]);
        BiblioIdentifier::create([
            'biblio_id' => $audiobook->id,
            'scheme' => 'url',
            'value' => 'https://example.org/audio',
            'normalized_value' => 'https://example.org/audio',
            'uri' => 'https://example.org/audio',
        ]);

        $soundtrack = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Soundtrack Collection',
            'normalized_title' => 'soundtrack collection',
            'publisher' => 'Music Lab',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'soundtrack',
            'media_type' => 'online',
            'ai_status' => 'draft',
        ]);
        $soundtrack->authors()->sync([
            $author->id => ['role' => 'pengarang', 'sort_order' => 1],
        ]);
        BiblioIdentifier::create([
            'biblio_id' => $soundtrack->id,
            'scheme' => 'uri',
            'value' => 'https://example.org/soundtrack',
            'normalized_value' => 'https://example.org/soundtrack',
            'uri' => 'https://example.org/soundtrack',
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);
        $xml = $response->getContent();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($xml));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('marc', 'http://www.loc.gov/MARC21/slim');

        $audioRecord = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Audiobook Learning']")->item(0);
        $this->assertNotNull($audioRecord);
        $audio856 = $xpath->query('.//marc:datafield[@tag="856"]', $audioRecord);
        $this->assertSame(1, $audio856->length);
        $audio336 = $xpath->query('.//marc:datafield[@tag="336"]/marc:subfield[@code="a" and text()="spoken word"]', $audioRecord);
        $this->assertSame(1, $audio336->length);

        $soundtrackRecord = $xpath->query("//marc:record[marc:datafield[@tag='245']/marc:subfield[@code='a']='Soundtrack Collection']")->item(0);
        $this->assertNotNull($soundtrackRecord);
        $soundtrack336 = $xpath->query('.//marc:datafield[@tag="336"]/marc:subfield[@code="a" and text()="performed music"]', $soundtrackRecord);
        $this->assertSame(1, $soundtrack336->length);
    }
}
