<?php

namespace Tests\Unit;

use App\Models\Author;
use App\Models\Biblio;
use App\Models\Subject;
use App\Services\AiCatalogingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiCatalogingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_cataloging_generates_fields(): void
    {
        $institutionId = DB::table('institutions')->insertGetId([
            'name' => 'Test Institution',
            'code' => 'TEST-AI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $biblio = Biblio::create([
            'institution_id' => $institutionId,
            'title' => 'Artificial Intelligence Basics',
            'subtitle' => 'An Intro to Machine Learning',
            'normalized_title' => 'artificial intelligence basics an intro to machine learning',
            'publisher' => 'Test Press',
            'publish_year' => 2024,
            'language' => 'en',
            'isbn' => '9786020000099',
            'material_type' => 'buku',
            'media_type' => 'teks',
            'ai_status' => 'draft',
            'notes' => 'This book introduces machine learning and artificial intelligence with practical examples.',
        ]);

        $author = Author::create([
            'name' => 'Test Author',
            'normalized_name' => 'test author',
        ]);
        $biblio->authors()->sync([$author->id => ['role' => 'pengarang', 'sort_order' => 1]]);

        $subject = Subject::create([
            'name' => 'Artificial intelligence',
            'term' => 'Artificial intelligence',
            'normalized_term' => 'artificial intelligence',
            'scheme' => 'local',
        ]);
        $biblio->subjects()->sync([$subject->id => ['type' => 'topic', 'sort_order' => 1]]);

        $service = new AiCatalogingService();
        $result = $service->runForBiblio($biblio, true);

        $biblio->refresh();

        $this->assertSame('completed', $result['status']);
        $this->assertSame('approved', $biblio->ai_status);
        $this->assertNotEmpty($biblio->ai_summary);
        $this->assertNotEmpty($biblio->ai_suggested_subjects_json);
        $this->assertNotEmpty($biblio->ai_suggested_tags_json);
        $this->assertNotEmpty($biblio->ai_suggested_ddc);
    }
}
