<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SruEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitution(string $code): int
    {
        return DB::table('institutions')->insertGetId([
            'name' => 'Inst ' . $code,
            'code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedBiblio(int $institutionId): void
    {
        $biblioId = DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'SRU Catalog Title',
            'subtitle' => 'Interoperability',
            'publisher' => 'SRU Press',
            'isbn' => '9786020000002',
            'language' => 'id',
            'publish_year' => 2026,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'Another Library Record',
            'subtitle' => null,
            'publisher' => 'Archive House',
            'isbn' => '9786020000999',
            'language' => 'id',
            'publish_year' => 2024,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('biblio_metadata')->insert([
            'biblio_id' => $biblioId,
            'dublin_core_json' => json_encode([
                'title' => ['SRU Catalog Title'],
                'creator' => ['SRU Tester'],
                'subject' => ['Interoperability'],
            ], JSON_UNESCAPED_UNICODE),
            'marc_core_json' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_explain_operation_returns_xml_response(): void
    {
        $this->seedInstitution('INS-SRU-EX');

        $resp = $this->get(route('sru.endpoint', ['operation' => 'explain']));
        $resp->assertOk();
        $resp->assertHeader('content-type', 'application/xml; charset=UTF-8');
        $this->assertStringContainsString('searchRetrieveResponse', $resp->getContent());
        $this->assertStringContainsString('explain', $resp->getContent());
        $this->assertStringContainsString('info:srw/schema/1/oai_marc-v1.0', $resp->getContent());
    }

    public function test_search_retrieve_returns_matching_records(): void
    {
        $institutionId = $this->seedInstitution('INS-SRU-SR');
        $this->seedBiblio($institutionId);

        $resp = $this->get(route('sru.endpoint', [
            'operation' => 'searchRetrieve',
            'query' => 'title any "SRU Catalog"',
            'startRecord' => 1,
            'maximumRecords' => 5,
        ]));
        $resp->assertOk();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($resp->getContent()));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('srw', 'http://www.loc.gov/zing/srw/');
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        $this->assertGreaterThan(0, $xpath->query('//srw:numberOfRecords')->length);
        $this->assertGreaterThan(0, $xpath->query('//srw:record')->length);
        $this->assertGreaterThan(0, $xpath->query('//dc:title')->length);
        $this->assertGreaterThan(0, $xpath->query('//srw:echoedSearchRetrieveRequest/srw:query')->length);
    }

    public function test_search_retrieve_returns_diagnostic_when_query_missing(): void
    {
        $this->seedInstitution('INS-SRU-DIAG');

        $resp = $this->get(route('sru.endpoint', [
            'operation' => 'searchRetrieve',
        ]));
        $resp->assertOk();
        $this->assertStringContainsString('<srw:diagnostics>', $resp->getContent());
        $this->assertStringContainsString('Missing query parameter', $resp->getContent());
    }

    public function test_search_retrieve_supports_dc_index_and_boolean_clause(): void
    {
        $institutionId = $this->seedInstitution('INS-SRU-BOOL');
        $this->seedBiblio($institutionId);

        $resp = $this->get(route('sru.endpoint', [
            'operation' => 'searchRetrieve',
            'query' => 'dc.title exact "SRU Catalog Title" and publisher any "SRU"',
            'startRecord' => 1,
            'maximumRecords' => 10,
        ]));
        $resp->assertOk();
        $this->assertStringNotContainsString('<srw:diagnostics>', $resp->getContent());
        $this->assertStringContainsString('SRU Catalog Title', $resp->getContent());
    }

    public function test_search_retrieve_supports_oai_marc_record_schema(): void
    {
        $institutionId = $this->seedInstitution('INS-SRU-MARC');
        $this->seedBiblio($institutionId);

        $resp = $this->get(route('sru.endpoint', [
            'operation' => 'searchRetrieve',
            'query' => 'title any "SRU Catalog"',
            'recordSchema' => 'oai_marc',
        ]));
        $resp->assertOk();
        $this->assertStringContainsString('info:srw/schema/1/oai_marc-v1.0', $resp->getContent());
        $this->assertStringContainsString('<oai_marc:record', $resp->getContent());
    }

    public function test_search_retrieve_returns_diagnostic_for_unsupported_record_schema(): void
    {
        $institutionId = $this->seedInstitution('INS-SRU-BADSCHEMA');
        $this->seedBiblio($institutionId);

        $resp = $this->get(route('sru.endpoint', [
            'operation' => 'searchRetrieve',
            'query' => 'title any "SRU Catalog"',
            'recordSchema' => 'mods',
        ]));
        $resp->assertOk();
        $this->assertStringContainsString('info:srw/diagnostic/1/66', $resp->getContent());
    }

    public function test_search_retrieve_returns_diagnostic_for_unsupported_relation_and_index(): void
    {
        $institutionId = $this->seedInstitution('INS-SRU-DREL');
        $this->seedBiblio($institutionId);

        $badRelation = $this->get(route('sru.endpoint', [
            'operation' => 'searchRetrieve',
            'query' => 'title adj "SRU"',
        ]));
        $badRelation->assertOk();
        $this->assertStringContainsString('info:srw/diagnostic/1/19', $badRelation->getContent());

        $badIndex = $this->get(route('sru.endpoint', [
            'operation' => 'searchRetrieve',
            'query' => 'foo.bar any "SRU"',
        ]));
        $badIndex->assertOk();
        $this->assertStringContainsString('info:srw/diagnostic/1/16', $badIndex->getContent());
    }

    public function test_scan_operation_returns_terms(): void
    {
        $institutionId = $this->seedInstitution('INS-SRU-SCAN');
        $this->seedBiblio($institutionId);

        $resp = $this->get(route('sru.endpoint', [
            'operation' => 'scan',
            'scanClause' => 'title any "SRU"',
            'maximumTerms' => 5,
        ]));
        $resp->assertOk();
        $this->assertStringContainsString('<srw:scanResponse', $resp->getContent());
        $this->assertStringContainsString('<srw:terms>', $resp->getContent());
        $this->assertStringContainsString('SRU Catalog Title', $resp->getContent());
    }

    public function test_scan_operation_requires_scan_clause(): void
    {
        $this->seedInstitution('INS-SRU-SCAN-ERR');
        $resp = $this->get(route('sru.endpoint', [
            'operation' => 'scan',
        ]));
        $resp->assertOk();
        $this->assertStringContainsString('Missing scanClause parameter', $resp->getContent());
        $this->assertStringContainsString('info:srw/diagnostic/1/7', $resp->getContent());
    }

    public function test_search_retrieve_sort_keys_orders_records_by_date_desc(): void
    {
        $institutionId = $this->seedInstitution('INS-SRU-SORT');
        $this->seedBiblio($institutionId);

        $resp = $this->get(route('sru.endpoint', [
            'operation' => 'searchRetrieve',
            'query' => 'identifier any "9786020000"',
            'sortKeys' => 'date,,0',
            'maximumRecords' => 10,
        ]));
        $resp->assertOk();

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($resp->getContent()));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        $titles = $xpath->query('//dc:title');
        $this->assertGreaterThan(0, $titles->length);
        $first = $titles->item(0);
        $this->assertNotNull($first);
        $this->assertStringContainsString('SRU Catalog Title', (string) $first->textContent);
    }
}
