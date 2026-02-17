<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OpacSeoEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function seedInstitution(string $code = 'INS-OPAC-SEO', ?int $id = null): int
    {
        $payload = [
            'name' => 'Institution ' . $code,
            'code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($id !== null) {
            $payload['id'] = $id;
            DB::table('institutions')->insert($payload);
            return $id;
        }

        return DB::table('institutions')->insertGetId($payload);
    }

    private function seedBiblio(int $institutionId, string $title = 'OPAC SEO Test'): int
    {
        return DB::table('biblio')->insertGetId([
            'institution_id' => $institutionId,
            'title' => $title,
            'media_type' => 'video',
            'cover_path' => 'covers/test-cover.jpg',
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);
    }

    public function test_sitemap_endpoint_returns_index_and_chunk_contains_public_opac_urls(): void
    {
        $publicInstitutionId = (int) config('notobuku.opac.public_institution_id', 1);
        $institutionId = $this->seedInstitution('INS-SEO-SITEMAP', $publicInstitutionId);
        $biblioId = $this->seedBiblio($institutionId, 'Sitemap Entry');

        $resp = $this->get('/sitemap.xml');
        $resp->assertOk();
        $resp->assertHeader('content-type', 'application/xml; charset=UTF-8');
        $resp->assertSee('/sitemap-opac-root.xml', false);
        $resp->assertSee('/sitemap-opac-1.xml', false);

        $chunk = $this->get('/sitemap-opac-1.xml');
        $chunk->assertOk();
        $chunk->assertSee('/opac/' . $biblioId, false);
        $chunk->assertSee('xmlns:image=', false);
        $chunk->assertSee('xmlns:video=', false);
        $chunk->assertSee('<image:loc>', false);
        $chunk->assertSee('<video:video>', false);
    }

    public function test_robots_endpoint_points_to_sitemap(): void
    {
        $this->seedInstitution('INS-SEO-ROBOTS');

        $resp = $this->get('/robots.txt');
        $resp->assertOk();
        $resp->assertHeader('content-type', 'text/plain; charset=UTF-8');
        $resp->assertSee('Sitemap: ' . url('/sitemap.xml'), false);
        $resp->assertSee('Disallow: /admin', false);
    }

    public function test_public_opac_embeds_prefetch_urls_for_popular_queries(): void
    {
        $publicInstitutionId = (int) config('notobuku.opac.public_institution_id', 1);
        $institutionId = $this->seedInstitution('INS-SEO-PREFETCH', $publicInstitutionId);
        $this->seedBiblio($institutionId, 'Sejarah Nusantara');

        DB::table('search_queries')->insert([
            'institution_id' => $institutionId,
            'normalized_query' => 'sejarah islam',
            'query' => 'sejarah islam',
            'last_hits' => 4,
            'search_count' => 6,
            'last_searched_at' => now()->subMinute(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subMinute(),
        ]);

        $resp = $this->get('/opac');
        $resp->assertOk();
        $resp->assertSee('requestIdleCallback', false);
        $resp->assertSee('/opac?q=sejarah%20islam', false);
    }
}
