<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CopyCatalogingWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private static bool $schemaReady = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$schemaReady) {
            return;
        }

        $database = strtolower((string) config('database.connections.mysql.database', ''));
        if ($database === '' || (!str_contains($database, 'test') && !str_contains($database, 'testing'))) {
            $this->markTestSkipped('CopyCatalogingWorkflowTest hanya boleh berjalan pada database testing.');
        }

        // Stabilkan schema testing tanpa db:wipe agar tidak kena race drop table di MySQL.
        Artisan::call('migrate', ['--force' => true, '--env' => 'testing']);
        self::$schemaReady = true;
    }

    private function seedInstitutionAndBranch(): array
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst Copy',
            'code' => 'INST-COPY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'institution_id' => $institutionId,
            'name' => 'Cabang Copy',
            'code' => 'BR-COPY',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$institutionId, $branchId];
    }

    private function makeStaff(int $institutionId, int $branchId): User
    {
        $suffix = uniqid('copy', true);
        return User::query()->create([
            'name' => 'Staff Copy',
            'email' => "staff-{$suffix}@test.local",
            'password' => Hash::make('password'),
            'role' => 'staff',
            'status' => 'active',
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
        ]);
    }

    public function test_copy_cataloging_sru_search_and_import_record(): void
    {
        [$institutionId, $branchId] = $this->seedInstitutionAndBranch();
        $staff = $this->makeStaff($institutionId, $branchId);

        DB::table('copy_catalog_sources')->insert([
            'institution_id' => $institutionId,
            'name' => 'SRU Test',
            'protocol' => 'sru',
            'endpoint' => 'https://example.test/sru',
            'is_active' => 1,
            'priority' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sourceId = (int) DB::table('copy_catalog_sources')->value('id');

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<searchRetrieveResponse xmlns="http://www.loc.gov/zing/srw/">
  <records>
    <record>
      <recordData>
        <dc xmlns:dc="http://purl.org/dc/elements/1.1/">
          <dc:title>Belajar Interoperabilitas</dc:title>
          <dc:creator>Tester</dc:creator>
          <dc:publisher>NB Press</dc:publisher>
          <dc:date>2024</dc:date>
          <dc:identifier>ISBN 9786020000018</dc:identifier>
        </dc>
      </recordData>
    </record>
  </records>
</searchRetrieveResponse>
XML;

        Http::fake([
            'example.test/sru*' => Http::response($xml, 200, ['Content-Type' => 'application/xml']),
        ]);

        $resp = $this->actingAs($staff)->get(route('copy_cataloging.index', [
            'source_id' => $sourceId,
            'q' => 'interoperabilitas',
            'limit' => 5,
        ]));
        $resp->assertOk();
        $resp->assertSee('Belajar Interoperabilitas');

        $payload = base64_encode(json_encode([
            'external_id' => '9786020000018',
            'title' => 'Belajar Interoperabilitas',
            'author' => 'Tester',
            'publisher' => 'NB Press',
            'publish_year' => '2024',
            'isbn' => '9786020000018',
            'source_protocol' => 'sru',
        ]));

        $importResp = $this->actingAs($staff)->post(route('copy_cataloging.import'), [
            'source_id' => $sourceId,
            'record_payload' => $payload,
        ]);

        $importResp->assertRedirect();

        $this->assertDatabaseHas('biblio', [
            'institution_id' => $institutionId,
            'title' => 'Belajar Interoperabilitas',
            'isbn' => '9786020000018',
        ]);
        $this->assertDatabaseHas('copy_catalog_imports', [
            'institution_id' => $institutionId,
            'source_id' => $sourceId,
            'status' => 'imported',
            'title' => 'Belajar Interoperabilitas',
        ]);
    }
}
