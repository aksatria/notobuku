<?php

namespace Tests\Unit;

use App\Models\AuthorityAuthor;
use App\Models\AuthorityPublisher;
use App\Models\AuthoritySubject;
use App\Models\Author;
use App\Models\Biblio;
use App\Models\Subject;
use App\Services\ExportService;
use App\Services\MetadataMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthorityControlExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_includes_authority_control_numbers(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-AUTH',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $authorityAuthor = AuthorityAuthor::create([
            'preferred_name' => 'Jane Doe',
            'normalized_name' => 'jane doe',
        ]);
        $authoritySubject = AuthoritySubject::create([
            'preferred_term' => 'Software Testing',
            'normalized_term' => 'software testing',
            'scheme' => 'local',
        ]);
        $authorityPublisher = AuthorityPublisher::create([
            'preferred_name' => 'QA Press',
            'normalized_name' => 'qa press',
        ]);

        $author = Author::create([
            'name' => 'Jane Doe',
            'normalized_name' => 'jane doe',
        ]);
        $subject = Subject::create([
            'name' => 'Software Testing',
            'term' => 'Software Testing',
            'normalized_term' => 'software testing',
            'scheme' => 'local',
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Authority Record',
            'normalized_title' => 'authority record',
            'publisher' => 'QA Press',
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

        $authorControl = $xpath->query('//marc:datafield[@tag="100"]/marc:subfield[@code="0"]')->item(0);
        $this->assertNotNull($authorControl);
        $this->assertSame('NBK:author:' . $authorityAuthor->id, (string) $authorControl->textContent);

        $subjectControl = $xpath->query('//marc:datafield[@tag="650"]/marc:subfield[@code="0"]')->item(0);
        $this->assertNotNull($subjectControl);
        $this->assertSame('NBK:subject:' . $authoritySubject->id, (string) $subjectControl->textContent);

        $publisherControl = $xpath->query('//marc:datafield[@tag="710"]/marc:subfield[@code="0"]')->item(0);
        $this->assertNotNull($publisherControl);
        $this->assertSame('NBK:publisher:' . $authorityPublisher->id, (string) $publisherControl->textContent);
    }
}
