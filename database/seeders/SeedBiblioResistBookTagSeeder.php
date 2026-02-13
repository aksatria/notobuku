<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Biblio;
use App\Models\Item;
use App\Models\Subject;
use App\Models\Tag;
use App\Services\MetadataMappingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeedBiblioResistBookTagSeeder extends Seeder
{
    public function run(): void
    {
        $institutionId = (int) (DB::table('institutions')->value('id') ?? 1);
        $branchId = DB::table('branches')->where('institution_id', $institutionId)->value('id');
        $metadata = app(MetadataMappingService::class);

        $urls = [
            'https://bukuprogresif.com/product/agama-itu-bukan-candu/',
            'https://bukuprogresif.com/product/akumulasi-primitif-pondasi-kapitalisme/',
            'https://bukuprogresif.com/product/analisa-ekologi-kritis/',
            'https://bukuprogresif.com/product/anarki-kapitalisme/',
            'https://bukuprogresif.com/product/anti-filsafat-metode-pemikiran-marx/',
            'https://bukuprogresif.com/product/arab-israel-untuk-pemula/',
            'https://bukuprogresif.com/product/asal-usul-kekayaan-sejarah-teori-nilai-dalam-ilmu-ekonomi-dari-aristoteles-sampai-amartya-sen/',
            'https://bukuprogresif.com/product/astaghfirullah-islam-jangan-dijual/',
            'https://bukuprogresif.com/product/atas-dan-bawah-topeng-dan-keheningan-komunike-komunike-zapatista-melawan-neoliberalisme/',
            'https://bukuprogresif.com/product/bandit-genealogi-dan-struktur-sosial/',
            'https://bukuprogresif.com/product/bayang-tak-berwajah-dokumen-perlawanan-tentara-pembebasan-nasional-zapatista/',
            'https://bukuprogresif.com/product/bertuhan-tanpa-agama/',
            'https://bukuprogresif.com/product/bolshevisme-jalan-menuju-revolusi-jilid-i/',
            'https://bukuprogresif.com/product/bolshevisme-jalan-menuju-revolusi-jilid-ii/',
            'https://bukuprogresif.com/product/bolshevisme-jalan-menuju-revolusi-jilid-iii/',
            'https://bukuprogresif.com/product/brumaire-xviii-louis-bonaparte/',
            'https://bukuprogresif.com/product/che-guevara-sang-revolusioner/',
            'https://bukuprogresif.com/product/che-untuk-pemula/',
            'https://bukuprogresif.com/product/chomsky-untuk-pemula/',
            'https://bukuprogresif.com/product/belanja-sampai-mati/',
        ];

        foreach ($urls as $url) {
            $html = $this->fetchHtml($url);
            if ($html === '') {
                $this->command?->warn('Gagal fetch: ' . $url);
                continue;
            }

            $title = $this->extractTitle($html);
            $author = $this->extractField($html, 'Penulis');
            $publisher = $this->extractField($html, 'Penerbit');
            $pages = $this->extractField($html, 'Jumlah Halaman');
            if ($pages === '') {
                $pages = $this->extractField($html, 'Halaman');
            }
            $size = $this->extractField($html, 'Ukuran');
            $dimensions = $this->extractField($html, 'Dimensi');
            $paper = $this->extractField($html, 'Kertas');
            $coverUrl = $this->extractCoverUrl($html);
            $description = $this->extractDescription($html);
            $categories = $this->extractCategories($html);

            if ($title === '') {
                $this->command?->warn('Skip (judul kosong): ' . $url);
                continue;
            }

            $responsibility = $author !== '' ? 'oleh ' . $author : null;
            $normalizedTitle = $this->normalizeTitle($title, null);

            $physical = $this->buildPhysicalDesc($pages, $size, $dimensions);
            $notesHtml = $this->toNotesHtml($description);
            $ddc = $this->inferDdcFromCategories($categories);
            $callNumber = $ddc !== '' ? $this->makeCallNumber($ddc, $author ?: $title) : null;

            $coverPath = $coverUrl ? $this->downloadCover($coverUrl, $title) : null;

            $biblio = Biblio::query()->updateOrCreate(
                [
                    'institution_id' => $institutionId,
                    'normalized_title' => $normalizedTitle,
                    'responsibility_statement' => $responsibility,
                ],
                [
                    'title' => $title,
                    'subtitle' => null,
                    'normalized_title' => $normalizedTitle,
                    'responsibility_statement' => $responsibility,
                    'publisher' => $publisher !== '' ? $publisher : 'Resist Book',
                    'place_of_publication' => null,
                    'publish_year' => null,
                    'isbn' => null,
                    'language' => 'ind',
                    'edition' => null,
                    'physical_desc' => $physical,
                    'extent' => $pages !== '' ? $pages . ' hlm' : null,
                    'dimensions' => $dimensions !== '' ? $dimensions : null,
                    'material_type' => 'buku',
                    'media_type' => 'teks',
                    'ddc' => $ddc !== '' ? $ddc : null,
                    'call_number' => $callNumber,
                    'cover_path' => $coverPath,
                    'notes' => $notesHtml,
                    'ai_status' => 'draft',
                ]
            );

            if ($author !== '') {
                $authorModel = Author::query()->firstOrCreate(
                    ['normalized_name' => $this->normalizeLoose($author)],
                    ['name' => $author, 'normalized_name' => $this->normalizeLoose($author)]
                );
                $biblio->authors()->syncWithoutDetaching([
                    $authorModel->id => ['role' => 'aut', 'sort_order' => 1],
                ]);
            }

            if (!empty($categories)) {
                foreach (array_values($categories) as $i => $term) {
                    $subjectModel = Subject::query()->firstOrCreate(
                        ['normalized_term' => $this->normalizeLoose($term)],
                        ['name' => $term, 'term' => $term, 'normalized_term' => $this->normalizeLoose($term), 'scheme' => 'local']
                    );
                    $biblio->subjects()->syncWithoutDetaching([
                        $subjectModel->id => ['type' => 'topic', 'sort_order' => $i + 1],
                    ]);
                }
            }

            $tagModel = Tag::query()->firstOrCreate(
                ['normalized_name' => $this->normalizeLoose('resist book')],
                ['name' => 'resist book', 'normalized_name' => $this->normalizeLoose('resist book')]
            );
            $biblio->tags()->syncWithoutDetaching([$tagModel->id => ['sort_order' => 1]]);

            if (!$biblio->items()->exists()) {
                Item::create([
                    'institution_id' => $institutionId,
                    'branch_id' => $branchId ?: null,
                    'biblio_id' => $biblio->id,
                    'barcode' => $this->generateUniqueCode('NB'),
                    'accession_number' => $this->generateUniqueCode('ACC'),
                    'status' => 'available',
                ]);
            }

            $dcI18n = [
                'id' => [
                    'title' => $title,
                    'creator' => $author !== '' ? [$author] : [],
                    'subject' => $categories,
                    'description' => $this->truncateText($description, 200),
                    'publisher' => $publisher !== '' ? $publisher : 'Resist Book',
                    'date' => null,
                    'language' => 'id',
                    'type' => 'buku',
                    'format' => 'teks',
                ],
            ];

            $identifiers = [
                ['scheme' => 'uri', 'value' => $url, 'uri' => $url],
            ];

            $metadata->syncMetadataForBiblio($biblio, $dcI18n, $identifiers);
        }
    }

    private function fetchHtml(string $url): string
    {
        try {
            $resp = Http::retry(2, 500)->get($url);
            return $resp->ok() ? (string) $resp->body() : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('/<h1[^>]*class=\"[^\"]*product_title[^\"]*\"[^>]*>(.*?)<\\/h1>/si', $html, $m)) {
            return $this->cleanText($m[1]);
        }
        return '';
    }

    private function extractField(string $html, string $label): string
    {
        $text = $this->htmlToText($html);
        if (preg_match('/' . preg_quote($label, '/') . '\\s+([^\\n]+)/i', $text, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private function extractCoverUrl(string $html): ?string
    {
        if (preg_match('/<img[^>]+class=\"[^\"]*wp-post-image[^\"]*\"[^>]+src=\"([^\"]+)\"/i', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/<meta[^>]+property=\"og:image\"[^>]+content=\"([^\"]+)\"/i', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/\"image\"\\s*:\\s*\"(https?:\\\\\\/\\\\\\/[^\\\"]+)\"/i', $html, $m)) {
            return str_replace('\\/', '/', $m[1]);
        }
        return null;
    }

    private function extractDescription(string $html): string
    {
        if (preg_match('/<div[^>]+id=\"tab-description\"[^>]*>(.*?)<\\/div>/si', $html, $m)) {
            return $this->cleanText($m[1]);
        }
        if (preg_match('/<div[^>]+class=\"woocommerce-Tabs-panel--description\"[^>]*>(.*?)<\\/div>/si', $html, $m)) {
            return $this->cleanText($m[1]);
        }
        return '';
    }

    private function extractCategories(string $html): array
    {
        $categories = [];
        if (preg_match('/Kategori:\\s*(.*?)\\s*Share:/si', $html, $m)) {
            $raw = $this->cleanText($m[1]);
            $parts = preg_split('/,|\\n/', $raw);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '') $categories[] = $p;
            }
        }
        return array_values(array_unique($categories));
    }

    private function buildPhysicalDesc(string $pages, string $size, string $dimensions): ?string
    {
        $pages = trim($pages);
        $size = trim($size);
        $dimensions = trim($dimensions);

        $parts = [];
        if ($pages !== '') {
            $parts[] = $pages . ' hlm';
        }
        $dim = $size !== '' ? $size : $dimensions;
        if ($dim !== '') {
            $parts[] = $dim;
        }
        return empty($parts) ? null : implode('; ', $parts);
    }

    private function toNotesHtml(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') return null;
        $snippet = $this->truncateText($text, 450);
        return '<p>' . e($snippet) . '</p>';
    }

    private function truncateText(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\\s+/', ' ', $text));
        if (mb_strlen($text) <= $limit) return $text;
        return rtrim(mb_substr($text, 0, $limit - 3)) . '...';
    }

    private function normalizeLoose(string $text): string
    {
        return (string) Str::of($text)
            ->lower()
            ->replaceMatches('/[^a-z0-9\\s]/', ' ')
            ->squish();
    }

    private function normalizeTitle(string $title, ?string $subtitle): string
    {
        $base = trim($title);
        $sub = trim((string) $subtitle);
        if ($sub !== '') $base .= ' ' . $sub;
        return $this->normalizeLoose($base);
    }

    private function makeCallNumber(string $ddc, string $authorOrTitle): string
    {
        $parts = preg_split('/\\s+/', trim($authorOrTitle));
        $last = $parts ? end($parts) : $authorOrTitle;
        $cutter = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $last), 0, 3));
        if ($cutter === '') {
            $cutter = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $authorOrTitle), 0, 3));
        }
        if ($cutter === '') {
            $cutter = 'XXX';
        }
        return trim($ddc . ' ' . $cutter);
    }

    private function inferDdcFromCategories(array $categories): string
    {
        $text = strtolower(implode(' ', $categories));
        if (str_contains($text, 'agama')) return '200';
        if (str_contains($text, 'filsafat')) return '100';
        if (str_contains($text, 'gender')) return '300';
        if (str_contains($text, 'gerakan sosial')) return '300';
        if (str_contains($text, 'sosial') || str_contains($text, 'politik')) return '300';
        if (str_contains($text, 'sejarah')) return '900';
        if (str_contains($text, 'sastra')) return '800';
        if (str_contains($text, 'pendidikan')) return '370';
        if (str_contains($text, 'seni')) return '700';
        return '';
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<\\s*br\\s*\\/?\\s*>/i', "\n", $html);
        $html = preg_replace('/<\\s*\\/\\s*p\\s*>/i', "\n", $html);
        $html = preg_replace('/<\\s*\\/\\s*li\\s*>/i', "\n", $html);
        $text = strip_tags($html);
        $text = preg_replace('/[ \\t]+/', ' ', $text);
        $text = preg_replace('/\\s*\\n\\s*/', "\n", $text);
        return trim($text);
    }

    private function cleanText(string $html): string
    {
        $text = strip_tags($html);
        $text = preg_replace('/\\s+/', ' ', $text);
        return trim($text);
    }

    private function downloadCover(string $url, string $title): ?string
    {
        try {
            $resp = Http::retry(2, 400)->get($url);
            if (!$resp->ok()) return null;
            $file = 'covers/resist-' . Str::slug($title) . '-' . Str::random(6) . '.jpg';
            Storage::disk('public')->put($file, $resp->body());
            return $file;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function generateUniqueCode(string $prefix): string
    {
        $date = now()->format('Ymd');
        for ($tries = 0; $tries < 20; $tries++) {
            $code = $prefix . '-' . $date . '-' . Str::upper(Str::random(6));
            $exists = Item::query()->where('barcode', $code)->orWhere('accession_number', $code)->exists();
            if (!$exists) return $code;
        }
        return $prefix . '-' . $date . '-' . Str::upper(Str::random(10));
    }
}
