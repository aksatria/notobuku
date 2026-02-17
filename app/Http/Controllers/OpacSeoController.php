<?php

namespace App\Http\Controllers;

use App\Models\Biblio;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OpacSeoController extends Controller
{
    public function sitemap(): Response
    {
        $institutionId = (int) config('notobuku.opac.public_institution_id', 1);
        $maxUrls = max(100, (int) config('notobuku.opac.sitemap.max_urls', 5000));
        $ttl = max(60, (int) config('notobuku.opac.sitemap.cache_seconds', 900));
        $total = (int) Biblio::query()->where('institution_id', $institutionId)->count();
        $chunks = max(1, (int) ceil($total / $maxUrls));

        $xml = Cache::remember("opac:sitemap:index:{$institutionId}:{$chunks}", now()->addSeconds($ttl), function () use ($chunks) {
            $entries = [];
            $entries[] = [
                'loc' => url('/sitemap-opac-root.xml'),
                'lastmod' => now()->toAtomString(),
            ];

            for ($i = 1; $i <= $chunks; $i++) {
                $entries[] = [
                    'loc' => url('/sitemap-opac-' . $i . '.xml'),
                    'lastmod' => now()->toAtomString(),
                ];
            }

            $out = ['<?xml version="1.0" encoding="UTF-8"?>'];
            $out[] = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach ($entries as $entry) {
                $out[] = '<sitemap>';
                $out[] = '<loc>' . e($entry['loc']) . '</loc>';
                $out[] = '<lastmod>' . e($entry['lastmod']) . '</lastmod>';
                $out[] = '</sitemap>';
            }
            $out[] = '</sitemapindex>';
            return implode('', $out);
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function sitemapRoot(): Response
    {
        $institutionId = (int) config('notobuku.opac.public_institution_id', 1);
        $ttl = max(60, (int) config('notobuku.opac.sitemap.cache_seconds', 900));

        $xml = Cache::remember("opac:sitemap:root:{$institutionId}", now()->addSeconds($ttl), function () {
            $urls = [];
            $urls[] = [
                'loc' => url('/opac'),
                'lastmod' => now()->toAtomString(),
                'priority' => '1.0',
                'changefreq' => 'hourly',
            ];

            $out = ['<?xml version="1.0" encoding="UTF-8"?>'];
            $out[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach ($urls as $u) {
                $out[] = '<url>';
                $out[] = '<loc>' . e($u['loc']) . '</loc>';
                $out[] = '<lastmod>' . e($u['lastmod']) . '</lastmod>';
                $out[] = '<changefreq>' . e($u['changefreq']) . '</changefreq>';
                $out[] = '<priority>' . e($u['priority']) . '</priority>';
                $out[] = '</url>';
            }
            $out[] = '</urlset>';

            return implode('', $out);
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function sitemapChunk(int $page): Response
    {
        $institutionId = (int) config('notobuku.opac.public_institution_id', 1);
        $maxUrls = max(100, (int) config('notobuku.opac.sitemap.max_urls', 5000));
        $ttl = max(60, (int) config('notobuku.opac.sitemap.cache_seconds', 900));
        $page = max(1, $page);
        $baseUrl = rtrim(config('app.url') ?: url('/'), '/');

        $xml = Cache::remember("opac:sitemap:chunk:{$institutionId}:{$page}", now()->addSeconds($ttl), function () use ($institutionId, $maxUrls, $page, $baseUrl) {
            $rows = Biblio::query()
                ->where('institution_id', $institutionId)
                ->orderByDesc('updated_at')
                ->forPage($page, $maxUrls)
                ->get(['id', 'updated_at', 'title', 'cover_path', 'media_type', 'notes']);

            $out = ['<?xml version="1.0" encoding="UTF-8"?>'];
            $out[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';
            foreach ($rows as $row) {
                $loc = "{$baseUrl}/opac/{$row->id}";
                $title = trim((string) ($row->title ?? 'Koleksi OPAC'));
                $coverUrl = !empty($row->cover_path) ? asset('storage/' . ltrim((string) $row->cover_path, '/')) : null;
                $isVideo = Str::contains(Str::lower((string) ($row->media_type ?? '')), 'video');
                $desc = trim(strip_tags((string) ($row->notes ?? '')));
                if ($desc === '') {
                    $desc = $title;
                }
                $desc = Str::limit($desc, 180, '');

                $out[] = '<url>';
                $out[] = '<loc>' . e($loc) . '</loc>';
                $out[] = '<lastmod>' . e(optional($row->updated_at)->toAtomString() ?: now()->toAtomString()) . '</lastmod>';
                $out[] = '<changefreq>daily</changefreq>';
                $out[] = '<priority>0.8</priority>';
                if ($coverUrl) {
                    $out[] = '<image:image>';
                    $out[] = '<image:loc>' . e($coverUrl) . '</image:loc>';
                    $out[] = '<image:title>' . e($title) . '</image:title>';
                    $out[] = '</image:image>';
                }
                if ($isVideo && $coverUrl) {
                    $out[] = '<video:video>';
                    $out[] = '<video:thumbnail_loc>' . e($coverUrl) . '</video:thumbnail_loc>';
                    $out[] = '<video:title>' . e(Str::limit($title, 100, '')) . '</video:title>';
                    $out[] = '<video:description>' . e(Str::limit($desc, 2048, '')) . '</video:description>';
                    $out[] = '<video:player_loc>' . e($loc) . '</video:player_loc>';
                    $out[] = '</video:video>';
                }
                $out[] = '</url>';
            }
            $out[] = '</urlset>';
            return implode('', $out);
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function robots(): Response
    {
        $sitemap = url('/sitemap.xml');
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Allow: /opac',
            'Allow: /opac/',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /admin',
            'Disallow: /staff',
            'Disallow: /member',
            'Disallow: /opac/suggest',
            'Disallow: /opac/metrics',
            'Disallow: /opac/preferences/',
            'Disallow: /*?*page=',
            'Disallow: /*?*sort=',
            'Disallow: /*?*view=',
            'Disallow: /*?*scope=',
            'Disallow: /*?*branch=',
            'Disallow: /*?*availability=',
            'Sitemap: ' . $sitemap,
            '',
            'User-agent: Googlebot',
            'Allow: /',
            '',
            'User-agent: bingbot',
            'Crawl-delay: 1',
            '',
            'User-agent: Yandex',
            'Crawl-delay: 2',
        ];

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
