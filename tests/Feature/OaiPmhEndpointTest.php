<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OaiPmhEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitution(string $code = 'INS-OAI'): int
    {
        return DB::table('institutions')->insertGetId([
            'name' => 'Institution ' . $code,
            'code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedBiblioWithMetadata(int $institutionId): int
    {
        $biblioId = DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => 'OAI Testing Title',
            'subtitle' => 'Cataloging',
            'isbn' => '9786020000001',
            'publisher' => 'NOTOBUKU Press',
            'publish_year' => 2025,
            'language' => 'id',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subMinutes(10),
        ]);

        DB::table('biblio_metadata')->insert([
            'biblio_id' => $biblioId,
            'dublin_core_json' => json_encode([
                'title' => ['OAI Testing Title'],
                'creator' => ['QA Librarian'],
                'subject' => ['Testing'],
                'publisher' => ['NOTOBUKU Press'],
                'date' => ['2025'],
                'type' => ['Text'],
                'language' => ['id'],
                'identifier' => ['local:123'],
                'description' => ['Testing OAI endpoint'],
            ], JSON_UNESCAPED_UNICODE),
            'marc_core_json' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        return $biblioId;
    }

    public function test_identify_returns_valid_oai_pmh_envelope(): void
    {
        $this->seedInstitution('INS-OAI-ID');

        $resp = $this->get(route('oai.pmh', ['verb' => 'Identify']));
        $resp->assertOk();
        $resp->assertHeader('content-type', 'application/xml; charset=UTF-8');

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($resp->getContent()));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');

        $this->assertSame(1, $xpath->query('/oai:OAI-PMH/oai:Identify')->length);
        $this->assertGreaterThan(0, $xpath->query('//oai:protocolVersion[text()="2.0"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//oai:granularity[text()="YYYY-MM-DDThh:mm:ssZ"]')->length);
        $this->assertGreaterThan(0, $xpath->query('//oai:deletedRecord[text()="transient"]')->length);
    }

    public function test_list_metadata_formats_includes_oai_dc_and_oai_marc(): void
    {
        $this->seedInstitution('INS-OAI-MF');
        $resp = $this->get(route('oai.pmh', ['verb' => 'ListMetadataFormats']));
        $resp->assertOk();
        $this->assertStringContainsString('<metadataPrefix>oai_dc</metadataPrefix>', $resp->getContent());
        $this->assertStringContainsString('<metadataPrefix>oai_marc</metadataPrefix>', $resp->getContent());
    }

    public function test_listrecords_and_getrecord_return_oai_dc_metadata(): void
    {
        $institutionId = $this->seedInstitution('INS-OAI-LR');
        $biblioId = $this->seedBiblioWithMetadata($institutionId);

        $listResp = $this->get(route('oai.pmh', [
            'verb' => 'ListRecords',
            'metadataPrefix' => 'oai_dc',
        ]));
        $listResp->assertOk();
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($listResp->getContent()));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
        $xpath->registerNamespace('oai_dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        $identifier = 'oai:notobuku:biblio:' . $biblioId;
        $this->assertGreaterThan(0, $xpath->query("//oai:record/oai:header/oai:identifier[text()='{$identifier}']")->length);
        $this->assertGreaterThan(0, $xpath->query('//oai_dc:dc/dc:title')->length);
        $this->assertGreaterThan(0, $xpath->query('//oai_dc:dc/dc:creator')->length);

        $getResp = $this->get(route('oai.pmh', [
            'verb' => 'GetRecord',
            'metadataPrefix' => 'oai_dc',
            'identifier' => $identifier,
        ]));
        $getResp->assertOk();
        $doc2 = new \DOMDocument();
        $this->assertTrue($doc2->loadXML($getResp->getContent()));
        $xpath2 = new \DOMXPath($doc2);
        $xpath2->registerNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
        $this->assertGreaterThan(0, $xpath2->query("//oai:GetRecord/oai:record/oai:header/oai:identifier[text()='{$identifier}']")->length);
    }

    public function test_oai_errors_for_bad_format_and_unknown_identifier(): void
    {
        $this->seedInstitution('INS-OAI-ERR');

        $badFormat = $this->get(route('oai.pmh', [
            'verb' => 'ListRecords',
            'metadataPrefix' => 'marc21',
        ]));
        $badFormat->assertOk();
        $this->assertStringContainsString('code="cannotDisseminateFormat"', $badFormat->getContent());

        $unknown = $this->get(route('oai.pmh', [
            'verb' => 'GetRecord',
            'metadataPrefix' => 'oai_dc',
            'identifier' => 'oai:notobuku:biblio:999999',
        ]));
        $unknown->assertOk();
        $this->assertStringContainsString('code="idDoesNotExist"', $unknown->getContent());
    }

    public function test_listsets_and_listidentifiers_support_institution_set_filter(): void
    {
        $instA = $this->seedInstitution('INS-OAI-SA');
        $instB = $this->seedInstitution('INS-OAI-SB');
        $idA = $this->seedBiblioWithMetadata($instA);
        $idB = $this->seedBiblioWithMetadata($instB);

        $sets = $this->get(route('oai.pmh', ['verb' => 'ListSets']));
        $sets->assertOk();
        $this->assertStringContainsString('<ListSets>', $sets->getContent());
        $this->assertStringContainsString('<setSpec>institution:' . $instA . '</setSpec>', $sets->getContent());
        $this->assertStringContainsString('<setSpec>institution:' . $instB . '</setSpec>', $sets->getContent());

        $all = $this->get(route('oai.pmh', [
            'verb' => 'ListIdentifiers',
            'metadataPrefix' => 'oai_dc',
        ]));
        $all->assertOk();
        $this->assertStringContainsString('oai:notobuku:biblio:' . $idA, $all->getContent());
        $this->assertStringContainsString('oai:notobuku:biblio:' . $idB, $all->getContent());

        $filtered = $this->get(route('oai.pmh', [
            'verb' => 'ListIdentifiers',
            'metadataPrefix' => 'oai_dc',
            'set' => 'institution:' . $instA,
        ]));
        $filtered->assertOk();
        $this->assertStringContainsString('oai:notobuku:biblio:' . $idA, $filtered->getContent());
        $this->assertStringNotContainsString('oai:notobuku:biblio:' . $idB, $filtered->getContent());
    }

    public function test_list_identifiers_returns_bad_argument_for_invalid_set_spec(): void
    {
        $this->seedInstitution('INS-OAI-BADSET');
        $resp = $this->get(route('oai.pmh', [
            'verb' => 'ListIdentifiers',
            'metadataPrefix' => 'oai_dc',
            'set' => 'branch:1',
        ]));
        $resp->assertOk();
        $this->assertStringContainsString('code="badArgument"', $resp->getContent());
    }

    public function test_list_records_rejects_mixed_day_and_datetime_granularity(): void
    {
        $inst = $this->seedInstitution('INS-OAI-GRAN');
        $this->seedBiblioWithMetadata($inst);

        $resp = $this->get(route('oai.pmh', [
            'verb' => 'ListRecords',
            'metadataPrefix' => 'oai_dc',
            'from' => now()->subDays(2)->toDateString(),
            'until' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ]));
        $resp->assertOk();
        $this->assertStringContainsString('code="badArgument"', $resp->getContent());
        $this->assertStringContainsString('Granularity from/until harus konsisten', $resp->getContent());
    }

    public function test_get_record_supports_oai_marc_metadata_prefix(): void
    {
        $inst = $this->seedInstitution('INS-OAI-MARC');
        $biblioId = $this->seedBiblioWithMetadata($inst);
        $identifier = 'oai:notobuku:biblio:' . $biblioId;

        $resp = $this->get(route('oai.pmh', [
            'verb' => 'GetRecord',
            'metadataPrefix' => 'oai_marc',
            'identifier' => $identifier,
        ]));
        $resp->assertOk();
        $this->assertStringContainsString('<oai_marc:record', $resp->getContent());
        $this->assertStringContainsString('id="245"', $resp->getContent());
    }

    public function test_list_records_supports_oai_marc_metadata_prefix(): void
    {
        $inst = $this->seedInstitution('INS-OAI-MARC-LIST');
        $biblioId = $this->seedBiblioWithMetadata($inst);

        $resp = $this->get(route('oai.pmh', [
            'verb' => 'ListRecords',
            'metadataPrefix' => 'oai_marc',
        ]));
        $resp->assertOk();
        $this->assertStringContainsString('oai:notobuku:biblio:' . $biblioId, $resp->getContent());
        $this->assertStringContainsString('<oai_marc:record', $resp->getContent());
    }

    public function test_get_record_returns_tombstone_header_for_deleted_biblio(): void
    {
        $inst = $this->seedInstitution('INS-OAI-DEL');
        $biblioId = $this->seedBiblioWithMetadata($inst);

        DB::table('biblio_metadata')->where('biblio_id', $biblioId)->delete();
        DB::table('biblio')->where('id', $biblioId)->delete();
        DB::table('audit_logs')->insert([
            'user_id' => null,
            'action' => 'delete',
            'format' => 'biblio',
            'status' => 'success',
            'meta' => json_encode([
                'biblio_id' => $biblioId,
                'institution_id' => $inst,
                'title' => 'OAI Testing Title',
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $identifier = 'oai:notobuku:biblio:' . $biblioId;
        $resp = $this->get(route('oai.pmh', [
            'verb' => 'GetRecord',
            'metadataPrefix' => 'oai_dc',
            'identifier' => $identifier,
        ]));
        $resp->assertOk();
        $this->assertStringContainsString('status="deleted"', $resp->getContent());
        $this->assertStringNotContainsString('<metadata>', $resp->getContent());
    }

    public function test_list_identifiers_and_list_records_include_tombstones(): void
    {
        $inst = $this->seedInstitution('INS-OAI-LDEL');
        $biblioId = $this->seedBiblioWithMetadata($inst);
        DB::table('biblio_metadata')->where('biblio_id', $biblioId)->delete();
        DB::table('biblio')->where('id', $biblioId)->delete();
        DB::table('audit_logs')->insert([
            'user_id' => null,
            'action' => 'delete',
            'format' => 'biblio',
            'status' => 'success',
            'meta' => json_encode([
                'biblio_id' => $biblioId,
                'institution_id' => $inst,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $ids = $this->get(route('oai.pmh', [
            'verb' => 'ListIdentifiers',
            'metadataPrefix' => 'oai_dc',
            'set' => 'institution:' . $inst,
        ]));
        $ids->assertOk();
        $this->assertStringContainsString('status="deleted"', $ids->getContent());
        $this->assertStringContainsString('oai:notobuku:biblio:' . $biblioId, $ids->getContent());

        $records = $this->get(route('oai.pmh', [
            'verb' => 'ListRecords',
            'metadataPrefix' => 'oai_dc',
            'set' => 'institution:' . $inst,
        ]));
        $records->assertOk();
        $this->assertStringContainsString('status="deleted"', $records->getContent());
    }

    public function test_resumption_token_uses_snapshot_cursor_consistently(): void
    {
        Cache::flush();
        $inst = $this->seedInstitution('INS-OAI-CURSOR');

        for ($i = 1; $i <= 105; $i++) {
            DB::table('biblio')->insert([
                'institution_id' => $inst,
                'title' => 'Cursor Title ' . $i,
                'created_at' => now()->subMinutes(200 - $i),
                'updated_at' => now()->subMinutes(200 - $i),
            ]);
        }

        $first = $this->get(route('oai.pmh', [
            'verb' => 'ListIdentifiers',
            'metadataPrefix' => 'oai_dc',
            'set' => 'institution:' . $inst,
        ]));
        $first->assertOk();
        $content = (string) $first->getContent();
        $this->assertStringContainsString('<resumptionToken', $content);

        $matched = preg_match('/<resumptionToken[^>]*>([^<]+)<\/resumptionToken>/', $content, $m);
        $this->assertSame(1, $matched);
        $token = (string) ($m[1] ?? '');
        $this->assertNotSame('', $token);

        $newId = DB::table('biblio')->insertGetId([
            'institution_id' => $inst,
            'title' => 'Cursor New After Token',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $second = $this->get(route('oai.pmh', [
            'verb' => 'ListIdentifiers',
            'resumptionToken' => $token,
        ]));
        $second->assertOk();
        $secondContent = (string) $second->getContent();
        $this->assertStringContainsString('oai:notobuku:biblio:', $secondContent);
        $this->assertStringNotContainsString('oai:notobuku:biblio:' . $newId, $secondContent);
    }

    public function test_resumption_token_is_rejected_when_tampered(): void
    {
        Cache::flush();
        $inst = $this->seedInstitution('INS-OAI-TAMPER');
        for ($i = 1; $i <= 105; $i++) {
            DB::table('biblio')->insert([
                'institution_id' => $inst,
                'title' => 'Tamper Title ' . $i,
                'created_at' => now()->subMinutes(200 - $i),
                'updated_at' => now()->subMinutes(200 - $i),
            ]);
        }

        $first = $this->get(route('oai.pmh', [
            'verb' => 'ListIdentifiers',
            'metadataPrefix' => 'oai_dc',
            'set' => 'institution:' . $inst,
        ]));
        $first->assertOk();
        preg_match('/<resumptionToken[^>]*>([^<]+)<\/resumptionToken>/', (string) $first->getContent(), $m);
        $token = (string) ($m[1] ?? '');
        $this->assertNotSame('', $token);

        $tampered = substr($token, 0, -1) . (substr($token, -1) === 'A' ? 'B' : 'A');
        $resp = $this->get(route('oai.pmh', [
            'verb' => 'ListIdentifiers',
            'resumptionToken' => $tampered,
        ]));
        $resp->assertOk();
        $this->assertStringContainsString('code="badResumptionToken"', (string) $resp->getContent());
    }

    public function test_resumption_token_is_bound_to_client_fingerprint(): void
    {
        Cache::flush();
        $inst = $this->seedInstitution('INS-OAI-CLIENT');
        for ($i = 1; $i <= 105; $i++) {
            DB::table('biblio')->insert([
                'institution_id' => $inst,
                'title' => 'Client Title ' . $i,
                'created_at' => now()->subMinutes(200 - $i),
                'updated_at' => now()->subMinutes(200 - $i),
            ]);
        }

        $first = $this
            ->withHeaders(['User-Agent' => 'Client-A'])
            ->get(route('oai.pmh', [
                'verb' => 'ListIdentifiers',
                'metadataPrefix' => 'oai_dc',
                'set' => 'institution:' . $inst,
            ]));
        $first->assertOk();
        preg_match('/<resumptionToken[^>]*>([^<]+)<\/resumptionToken>/', (string) $first->getContent(), $m);
        $token = (string) ($m[1] ?? '');
        $this->assertNotSame('', $token);

        $second = $this
            ->withHeaders(['User-Agent' => 'Client-B'])
            ->get(route('oai.pmh', [
                'verb' => 'ListIdentifiers',
                'resumptionToken' => $token,
            ]));
        $second->assertOk();
        $this->assertStringContainsString('code="badResumptionToken"', (string) $second->getContent());
    }

    public function test_old_snapshot_token_is_evicted_when_client_exceeds_limit(): void
    {
        Cache::flush();
        $inst = $this->seedInstitution('INS-OAI-EVICT');
        for ($i = 1; $i <= 105; $i++) {
            DB::table('biblio')->insert([
                'institution_id' => $inst,
                'title' => 'Evict Title ' . $i,
                'created_at' => now()->subMinutes(200 - $i),
                'updated_at' => now()->subMinutes(200 - $i),
            ]);
        }

        $oldToken = '';
        for ($i = 1; $i <= 6; $i++) {
            $resp = $this
                ->withHeaders(['User-Agent' => 'Evict-Client'])
                ->get(route('oai.pmh', [
                    'verb' => 'ListIdentifiers',
                    'metadataPrefix' => 'oai_dc',
                    'set' => 'institution:' . $inst,
                    'from' => now()->subDays($i + 1)->toDateString(),
                ]));
            $resp->assertOk();
            preg_match('/<resumptionToken[^>]*>([^<]+)<\/resumptionToken>/', (string) $resp->getContent(), $m);
            $token = (string) ($m[1] ?? '');
            $this->assertNotSame('', $token);
            if ($i === 1) {
                $oldToken = $token;
            }
        }

        $this->assertNotSame('', $oldToken);
        $oldResp = $this
            ->withHeaders(['User-Agent' => 'Evict-Client'])
            ->get(route('oai.pmh', [
                'verb' => 'ListIdentifiers',
                'resumptionToken' => $oldToken,
            ]));
        $oldResp->assertOk();
        $this->assertStringContainsString('code="badResumptionToken"', (string) $oldResp->getContent());
    }
}
