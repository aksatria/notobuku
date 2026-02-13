<?php

namespace Tests\Feature;

use App\Models\Biblio;
use App\Services\ExportService;
use App\Services\MetadataMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarcExportValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_returns_error_when_required_fields_missing(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-REQ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Missing Year',
            'place_of_publication' => 'Jakarta',
            'language' => 'id',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_export_requires_856_for_online_material(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-ONLINE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Online Resource',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'id',
            'material_type' => 'ebook',
            'media_type' => 'online',
            'ai_status' => 'draft',
        ]);

        $service = new ExportService(new MetadataMappingService());
        $response = $service->exportMarcXmlCore($institutionId);

        $this->assertSame(422, $response->getStatusCode());
    }
}
