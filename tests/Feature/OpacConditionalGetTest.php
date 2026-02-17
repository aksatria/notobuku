<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OpacConditionalGetTest extends TestCase
{
    use RefreshDatabase;

    public function test_opac_index_sets_etag_and_last_modified_and_returns_304_when_not_modified(): void
    {
        $publicInstitutionId = (int) config('notobuku.opac.public_institution_id', 1);
        DB::table('institutions')->insert([
            'id' => $publicInstitutionId,
            'name' => 'Public',
            'code' => 'PUB',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('biblio')->insert([
            'institution_id' => $publicInstitutionId,
            'title' => 'Conditional Cache',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subMinute(),
        ]);

        $first = $this->get('/opac');
        $first->assertOk();
        $etag = $first->headers->get('ETag');
        $lastModified = $first->headers->get('Last-Modified');
        $this->assertNotEmpty($etag);
        $this->assertNotEmpty($lastModified);

        $second = $this->withHeaders([
            'If-None-Match' => $etag,
        ])->get('/opac');

        $second->assertStatus(304);
    }
}

