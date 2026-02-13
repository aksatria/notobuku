<?php

namespace Tests\Unit;

use App\Models\Author;
use App\Models\AuthorityAuthor;
use App\Models\AuthorityPublisher;
use App\Models\Biblio;
use App\Services\MarcValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarcValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_relator_emits_warning(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-REL',
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
        ]);
        AuthorityPublisher::create([
            'preferred_name' => 'QA Press',
            'normalized_name' => 'qa press',
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Relator Check',
            'normalized_title' => 'relator check',
            'publisher' => 'QA Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
        ]);
        $biblio->authors()->sync([
            $author->id => ['role' => 'mysteryrole', 'sort_order' => 1],
        ]);

        $service = new MarcValidationService();
        $issues = $service->validateForExport($biblio);

        $this->assertTrue(collect($issues)->contains(fn($m) => str_contains((string) $m, 'WARN: Relator tidak dikenal')));
    }

    public function test_policy_can_escalate_to_error(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-REL-ERR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Models\MarcPolicySet::create([
            'institution_id' => $institutionId,
            'name' => 'RDA Core',
            'version' => 1,
            'status' => 'published',
            'payload_json' => [
                'rules' => [
                    'audio_missing_narrator' => 'error',
                ],
            ],
        ]);

        $author = Author::create([
            'name' => 'Jane Doe',
            'normalized_name' => 'jane doe',
        ]);
        AuthorityAuthor::create([
            'preferred_name' => 'Jane Doe',
            'normalized_name' => 'jane doe',
        ]);
        AuthorityPublisher::create([
            'preferred_name' => 'QA Press',
            'normalized_name' => 'qa press',
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Audio Policy',
            'normalized_title' => 'audio policy',
            'publisher' => 'QA Press',
            'place_of_publication' => 'Jakarta',
            'publish_year' => 2024,
            'language' => 'en',
            'material_type' => 'audio',
            'media_type' => 'cd audio',
            'ai_status' => 'draft',
        ]);
        $biblio->authors()->sync([
            $author->id => ['role' => 'pengarang', 'sort_order' => 1],
        ]);

        $service = new MarcValidationService();
        $issues = $service->validateForExport($biblio);

        $this->assertTrue(collect($issues)->contains(fn($m) => $m === 'Audio: sebaiknya ada relator narrator (narator/narrator).'));
        $this->assertFalse(collect($issues)->contains(fn($m) => str_contains((string) $m, 'WARN: Audio:')));
    }
}
