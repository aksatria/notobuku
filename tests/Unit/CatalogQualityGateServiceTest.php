<?php

namespace Tests\Unit;

use App\Models\Biblio;
use App\Services\CatalogQualityGateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogQualityGateServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_quality_gate_rejects_missing_core_fields(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst QG',
            'code' => 'INST-QG',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $svc = app(CatalogQualityGateService::class);
        $result = $svc->evaluate([
            'title' => '',
            'authors_text' => '',
            'subjects_text' => '',
            'isbn' => '',
            'ddc' => '',
            'call_number' => '',
        ], $institutionId);

        $this->assertFalse((bool) $result['ok']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_quality_gate_warns_duplicate_isbn(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Inst QG 2',
            'code' => 'INST-QG2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Buku Lama',
            'normalized_title' => 'buku lama',
            'isbn' => '9786021111111',
            'ai_status' => 'draft',
        ]);

        $svc = app(CatalogQualityGateService::class);
        $result = $svc->evaluate([
            'title' => 'Buku Baru',
            'authors_text' => 'Anonim',
            'subjects_text' => 'Filsafat',
            'isbn' => '9786021111111',
            'ddc' => '100',
        ], $institutionId);

        $this->assertTrue((bool) $result['ok']);
        $this->assertNotEmpty($result['warnings']);
    }
}
