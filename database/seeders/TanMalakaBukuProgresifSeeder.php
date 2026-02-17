<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Biblio;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Subject;
use App\Models\Tag;
use App\Services\MetadataMappingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TanMalakaBukuProgresifSeeder extends Seeder
{
    private const START_URL = 'https://bukuprogresif.com/?s=tan+malaka&post_type=product&type_aws=true';

    public function run(): void
    {
        $institutionId = (int) (DB::table('institutions')->value('id') ?? 1);
        $branchId = $this->resolveNganjukBranchId($institutionId);
        $metadata = app(MetadataMappingService::class);
        $processedBiblioIds = [];

        $productUrls = $this->collectProductUrlsFromAllPages(self::START_URL);
        if (empty($productUrls)) {
            $this->command?->warn('Tidak ada URL produk Tan Malaka yang ditemukan.');
            return;
        }

        foreach ($productUrls as $url) {
            $html = $this->fetchHtml($url);
            if ($html === '') {
                $this->command?->warn('Skip (gagal fetch): ' . $url);
                continue;
            }

            $titleRaw = $this->extractTitle($html);
            if ($titleRaw === '') {
                $this->command?->warn('Skip (judul kosong): ' . $url);
                continue;
            }

            [$title, $subtitle] = $this->splitTitleSubtitle($titleRaw);
            $author = $this->sanitizeAuthor($this->extractField($html, ['Penulis', 'Penulis/Penyusun', 'Author']));
            $publisher = $this->extractField($html, ['Penerbit', 'Publisher']);
            $pages = $this->extractField($html, ['Jumlah Halaman', 'Halaman']);
            $size = $this->extractField($html, ['Ukuran']);
            $dimensions = $this->extractField($html, ['Dimensi']);
            $paper = $this->extractField($html, ['Kertas']);
            $binding = $this->extractField($html, ['Jenis Sampul', 'Sampul']);
            $sku = $this->extractField($html, ['SKU']);
            $price = $this->extractPrice($html);
            $description = $this->extractDescription($html);
            $categories = $this->extractCategories($html);
            $tags = $this->extractTags($html);
            $coverUrl = $this->extractCoverUrl($html);
            $coverPath = $coverUrl ? $this->downloadCover($coverUrl, $title) : null;

            $normalizedTitle = $this->normalizeTitle($title, $subtitle);
            $responsibility = $author !== '' ? Str::limit('oleh ' . $author, 240, '') : null;
            $extent = $this->normalizeExtent($pages);
            $physicalDesc = $this->buildPhysicalDesc($extent, $size, $dimensions);
            $ddc = $this->inferDdc($categories, $tags);
            $callNumber = $this->makeCallNumber($ddc, $author !== '' ? $author : $title);

            $biblio = Biblio::query()->firstOrNew([
                'institution_id' => $institutionId,
                'normalized_title' => $normalizedTitle,
                'responsibility_statement' => $responsibility,
            ]);

            $biblio->fill([
                'title' => $title,
                'subtitle' => $subtitle,
                'normalized_title' => $normalizedTitle,
                'responsibility_statement' => $responsibility,
                'publisher' => $publisher !== '' ? $publisher : 'Buku Progresif',
                'place_of_publication' => null,
                'publish_year' => null,
                'isbn' => null,
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'edition' => null,
                'physical_desc' => $physicalDesc,
                'extent' => $extent,
                'dimensions' => $dimensions !== '' ? $dimensions : null,
                'illustrations' => null,
                'ddc' => $ddc,
                'call_number' => $callNumber,
                'notes' => $this->buildNotes($price, $paper, $binding, $sku, $url),
                'general_note' => $this->truncateText($description, 1200),
                'ai_status' => 'draft',
            ]);

            if ($coverPath !== null) {
                $biblio->cover_path = $coverPath;
            }

            $biblio->save();
            $processedBiblioIds[] = (int) $biblio->id;

            $this->syncAuthors($biblio, $author);
            $this->syncSubjects($biblio, $categories, $tags);
            $this->syncTags($biblio, $tags);
            $this->ensureNganjukItem($biblio, $institutionId, $branchId);

            $metadata->syncMetadataForBiblio(
                $biblio,
                [
                    'id' => [
                        'title' => $titleRaw,
                        'creator' => $author !== '' ? [$author] : [],
                        'subject' => array_values(array_unique(array_merge(['Tan Malaka'], $categories, $tags))),
                        'description' => $this->truncateText($description, 300),
                        'publisher' => $publisher !== '' ? $publisher : 'Buku Progresif',
                        'date' => null,
                        'language' => 'id',
                        'identifier' => [$url],
                        'type' => 'buku',
                        'format' => 'teks',
                    ],
                ],
                $this->buildIdentifiers($url, $coverUrl, $sku)
            );
        }

        $this->pruneBranchItems($institutionId, $branchId, $processedBiblioIds);
    }

    private function collectProductUrlsFromAllPages(string $startUrl): array
    {
        $queue = [$startUrl];
        $visited = [];
        $products = [];

        while (!empty($queue) && count($visited) < 30) {
            $pageUrl = array_shift($queue);
            if (isset($visited[$pageUrl])) {
                continue;
            }
            $visited[$pageUrl] = true;

            $html = $this->fetchHtml($pageUrl);
            if ($html === '') {
                continue;
            }

            preg_match_all('/href=["\'](https:\/\/bukuprogresif\.com\/product\/[^"\']+)["\']/i', $html, $mProduct);
            foreach (($mProduct[1] ?? []) as $u) {
                $clean = strtok($u, '?');
                if (!str_ends_with($clean, '/')) {
                    $clean .= '/';
                }
                $products[$clean] = true;
            }

            preg_match_all('/href=["\'](https:\/\/bukuprogresif\.com\/[^"\']+)["\']/i', $html, $mLinks);
            foreach (($mLinks[1] ?? []) as $link) {
                $isSearchPage = str_contains($link, 'post_type=product') && str_contains($link, 'type_aws=true');
                if (!$isSearchPage) {
                    continue;
                }
                $cleanLink = html_entity_decode($link, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $lower = strtolower($cleanLink);
                $isTanMalakaQuery = str_contains($lower, 's=tan+malaka')
                    || str_contains($lower, 's=tan%20malaka');
                if (!$isTanMalakaQuery) {
                    continue;
                }
                if (!isset($visited[$cleanLink])) {
                    $queue[] = $cleanLink;
                }
            }
        }

        $list = array_keys($products);
        sort($list);
        return $list;
    }

    private function resolveNganjukBranchId(int $institutionId): ?int
    {
        $byCode = Branch::query()
            ->where('institution_id', $institutionId)
            ->where('code', 'NGJ')
            ->value('id');
        if ($byCode) {
            return (int) $byCode;
        }

        $byName = Branch::query()
            ->where('institution_id', $institutionId)
            ->where('name', 'like', '%Nganjuk%')
            ->value('id');
        if ($byName) {
            return (int) $byName;
        }

        $branch = Branch::query()->create([
            'institution_id' => $institutionId,
            'code' => 'NGJ',
            'name' => 'Perpustakaan Kabupaten Nganjuk',
            'is_active' => true,
        ]);

        return (int) $branch->id;
    }

    private function syncAuthors(Biblio $biblio, string $authorText): void
    {
        $authorText = trim($authorText);
        if ($authorText === '') {
            return;
        }

        $parts = collect(preg_split('/\s*(?:,|;|\/|\bdan\b|\band\b)\s*/iu', $authorText))
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return;
        }

        $sync = [];
        foreach ($parts as $idx => $name) {
            $author = Author::query()->firstOrCreate(
                ['normalized_name' => $this->normalizeLoose($name)],
                ['name' => $name, 'normalized_name' => $this->normalizeLoose($name)]
            );
            $sync[$author->id] = ['role' => 'aut', 'sort_order' => $idx + 1];
        }

        $biblio->authors()->sync($sync);
    }

    private function syncSubjects(Biblio $biblio, array $categories, array $tags): void
    {
        $subjects = array_values(array_unique(array_merge(['Tan Malaka'], $categories, $tags)));
        if (empty($subjects)) {
            return;
        }

        $sync = [];
        foreach ($subjects as $idx => $term) {
            $subject = Subject::query()->firstOrCreate(
                ['normalized_term' => $this->normalizeLoose($term)],
                [
                    'name' => $term,
                    'term' => $term,
                    'normalized_term' => $this->normalizeLoose($term),
                    'scheme' => 'local',
                ]
            );
            $sync[$subject->id] = ['type' => 'topic', 'sort_order' => $idx + 1];
        }

        $biblio->subjects()->sync($sync);
    }

    private function syncTags(Biblio $biblio, array $sourceTags): void
    {
        $tags = array_values(array_unique(array_merge(['bukuprogresif', 'tan malaka'], $sourceTags)));

        $sync = [];
        foreach ($tags as $idx => $name) {
            $tag = Tag::query()->firstOrCreate(
                ['normalized_name' => $this->normalizeLoose($name)],
                ['name' => $name, 'normalized_name' => $this->normalizeLoose($name)]
            );
            $sync[$tag->id] = ['sort_order' => $idx + 1];
        }

        if (!empty($sync)) {
            $biblio->tags()->sync($sync);
        }
    }

    private function ensureNganjukItem(Biblio $biblio, int $institutionId, ?int $branchId): void
    {
        if ($branchId === null) {
            return;
        }

        $exists = Item::query()
            ->where('institution_id', $institutionId)
            ->where('biblio_id', $biblio->id)
            ->where('branch_id', $branchId)
            ->exists();

        if ($exists) {
            return;
        }

        Item::query()->create([
            'institution_id' => $institutionId,
            'branch_id' => $branchId,
            'biblio_id' => $biblio->id,
            'barcode' => $this->generateUniqueCode('NB'),
            'accession_number' => $this->generateUniqueCode('ACC'),
            'status' => 'available',
            'acquisition_source' => 'beli',
            'circulation_status' => 'circulating',
        ]);
    }

    private function pruneBranchItems(int $institutionId, ?int $branchId, array $keepBiblioIds): void
    {
        if ($branchId === null) {
            return;
        }

        $keepBiblioIds = array_values(array_unique(array_map('intval', $keepBiblioIds)));
        $query = Item::query()
            ->where('institution_id', $institutionId)
            ->where('branch_id', $branchId);

        if (!empty($keepBiblioIds)) {
            $query->whereNotIn('biblio_id', $keepBiblioIds);
        }

        $query->delete();
    }

    private function fetchHtml(string $url): string
    {
        try {
            $response = Http::timeout(25)
                ->retry(2, 500)
                ->withHeaders(['User-Agent' => 'NotoBuku Seeder/1.0'])
                ->get($url);

            return $response->ok() ? (string) $response->body() : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('/<h1[^>]*class=["\'][^"\']*product_title[^"\']*["\'][^>]*>(.*?)<\/h1>/si', $html, $m)) {
            return $this->cleanText($m[1]);
        }
        return '';
    }

    private function splitTitleSubtitle(string $title): array
    {
        $title = trim($title);
        if ($title === '') {
            return ['', null];
        }

        if (preg_match('/^(.+?)\s*:\s*(.+)$/u', $title, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [$title, null];
    }

    private function extractField(string $html, array $labels): string
    {
        foreach ($labels as $label) {
            if (preg_match('/<(?:p|li)[^>]*>\s*' . preg_quote($label, '/') . '\s*[-:]\s*(.*?)<\/(?:p|li)>/iu', $html, $m)) {
                return $this->cleanText($m[1]);
            }
        }

        $text = $this->htmlToText($html);
        foreach ($labels as $label) {
            if (preg_match('/\b' . preg_quote($label, '/') . '\s*[-:]\s*([^\n\r]+)/iu', $text, $m)) {
                return trim((string) $m[1]);
            }
        }
        return '';
    }

    private function extractPrice(string $html): ?string
    {
        if (preg_match('/<p[^>]*class=["\'][^"\']*price[^"\']*["\'][^>]*>(.*?)<\/p>/si', $html, $m)) {
            $price = $this->cleanText($m[1]);
            return $price !== '' ? $price : null;
        }

        $text = $this->htmlToText($html);
        if (preg_match('/(Rp\.?\s?[0-9\.\,]+)/iu', $text, $m)) {
            return trim((string) $m[1]);
        }

        return null;
    }

    private function extractDescription(string $html): string
    {
        if (preg_match('/<meta[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
            $desc = trim((string) $m[1]);
            if ($desc !== '') {
                return html_entity_decode($desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        if (preg_match('/<div[^>]*id=["\']tab-description["\'][^>]*>(.*?)<\/div>\s*<\/div>/si', $html, $m)) {
            return $this->cleanText($m[1]);
        }

        return '';
    }

    private function extractCategories(string $html): array
    {
        $out = [];
        if (preg_match('/<span[^>]*class=["\']posted_in["\'][^>]*>(.*?)<\/span>/si', $html, $m)) {
            preg_match_all('/>([^<]+)</', $m[1], $links);
            foreach (($links[1] ?? []) as $text) {
                $v = trim(html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($v !== '') {
                    $out[] = $v;
                }
            }
        }
        return array_values(array_unique($out));
    }

    private function extractTags(string $html): array
    {
        $out = [];
        if (preg_match('/<span[^>]*class=["\']tagged_as["\'][^>]*>(.*?)<\/span>/si', $html, $m)) {
            preg_match_all('/>([^<]+)</', $m[1], $links);
            foreach (($links[1] ?? []) as $text) {
                $v = trim(html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($v !== '') {
                    $out[] = $v;
                }
            }
        }
        return array_values(array_unique($out));
    }

    private function extractCoverUrl(string $html): ?string
    {
        if (preg_match('/<meta[^>]*property=["\']og:image["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m)) {
            return trim((string) $m[1]);
        }
        if (preg_match('/<img[^>]+class=["\'][^"\']*wp-post-image[^"\']*["\'][^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            return trim((string) $m[1]);
        }
        return null;
    }

    private function buildPhysicalDesc(?string $extent, string $size, string $dimensions): ?string
    {
        $parts = [];
        if (!empty($extent)) {
            $parts[] = $extent;
        }

        $dim = trim($dimensions) !== '' ? trim($dimensions) : trim($size);
        if ($dim !== '') {
            $parts[] = $dim;
        }

        return !empty($parts) ? implode('; ', $parts) : null;
    }

    private function normalizeExtent(string $pages): ?string
    {
        $pages = trim($pages);
        if ($pages === '') {
            return null;
        }

        if (preg_match('/(\d{1,5})/', $pages, $m)) {
            return $m[1] . ' hlm';
        }

        return $pages;
    }

    private function buildNotes(?string $price, string $paper, string $binding, string $sku, string $source): ?string
    {
        $parts = [];
        if ($price) {
            $parts[] = 'Harga: ' . $price;
        }
        if (trim($paper) !== '') {
            $parts[] = 'Kertas: ' . trim($paper);
        }
        if (trim($binding) !== '') {
            $parts[] = 'Sampul: ' . trim($binding);
        }
        if (trim($sku) !== '' && strtoupper(trim($sku)) !== 'N/A') {
            $parts[] = 'SKU: ' . trim($sku);
        }
        $parts[] = 'Sumber: ' . $source;

        return !empty($parts) ? implode(' | ', $parts) : null;
    }

    private function inferDdc(array $categories, array $tags): ?string
    {
        $text = strtolower(implode(' ', array_merge($categories, $tags)));
        if (str_contains($text, 'politik')) return '320';
        if (str_contains($text, 'sejarah')) return '900';
        if (str_contains($text, 'filsafat')) return '100';
        return '100';
    }

    private function makeCallNumber(?string $ddc, string $authorOrTitle): ?string
    {
        if ($ddc === null || trim($ddc) === '') {
            return null;
        }

        $token = trim((string) collect(preg_split('/\s+/', trim($authorOrTitle)))->last());
        $letters = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $token), 0, 3));
        if ($letters === '') {
            $letters = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $authorOrTitle), 0, 3));
        }
        if ($letters === '') {
            $letters = 'XXX';
        }

        return $ddc . ' ' . $letters;
    }

    private function downloadCover(string $url, string $title): ?string
    {
        try {
            $resp = Http::timeout(20)
                ->retry(2, 400)
                ->withHeaders(['User-Agent' => 'NotoBuku Seeder/1.0'])
                ->get($url);

            if (!$resp->ok()) {
                return null;
            }

            $type = strtolower((string) $resp->header('Content-Type', 'image/jpeg'));
            $ext = 'jpg';
            if (str_contains($type, 'png')) {
                $ext = 'png';
            } elseif (str_contains($type, 'webp')) {
                $ext = 'webp';
            }

            $file = 'covers/bukuprogresif-tan-malaka-' . Str::slug($title) . '-' . Str::lower(Str::random(6)) . '.' . $ext;
            Storage::disk('public')->put($file, $resp->body());

            return $file;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildIdentifiers(string $productUrl, ?string $coverUrl, string $sku): array
    {
        $ids = [
            ['scheme' => 'uri', 'value' => $productUrl, 'uri' => $productUrl],
        ];

        if ($coverUrl !== null && trim($coverUrl) !== '') {
            $ids[] = ['scheme' => 'cover_url', 'value' => $coverUrl, 'uri' => $coverUrl];
        }

        if (trim($sku) !== '' && strtoupper(trim($sku)) !== 'N/A') {
            $ids[] = ['scheme' => 'sku', 'value' => trim($sku), 'uri' => null];
        }

        return $ids;
    }

    private function normalizeLoose(string $text): string
    {
        return (string) Str::of($text)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]/u', ' ')
            ->squish();
    }

    private function normalizeTitle(string $title, ?string $subtitle): string
    {
        $base = trim($title);
        $subtitle = trim((string) $subtitle);
        if ($subtitle !== '') {
            $base .= ' ' . $subtitle;
        }
        return $this->normalizeLoose($base);
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html);
        $html = preg_replace('/<\s*\/\s*p\s*>/i', "\n", $html);
        $html = preg_replace('/<\s*\/\s*li\s*>/i', "\n", $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', (string) $text);
        $text = preg_replace('/\s*\n\s*/', "\n", (string) $text);
        return trim((string) $text);
    }

    private function cleanText(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', (string) $text);
        return trim((string) $text);
    }

    private function truncateText(string $text, int $limit): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $limit - 3)) . '...';
    }

    private function sanitizeAuthor(string $author): string
    {
        $author = trim($author);
        if ($author === '') {
            return '';
        }

        if (preg_match('/[{}<>]|@media|font-family|\.|#|{/', $author)) {
            return '';
        }

        if (mb_strlen($author) > 120) {
            return '';
        }

        return $author;
    }

    private function generateUniqueCode(string $prefix): string
    {
        $date = now()->format('Ymd');
        for ($i = 0; $i < 20; $i++) {
            $code = $prefix . '-' . $date . '-' . Str::upper(Str::random(6));
            $exists = Item::query()->where('barcode', $code)->orWhere('accession_number', $code)->exists();
            if (!$exists) {
                return $code;
            }
        }
        return $prefix . '-' . $date . '-' . Str::upper(Str::random(10));
    }
}
