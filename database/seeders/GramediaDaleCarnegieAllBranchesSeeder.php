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

class GramediaDaleCarnegieAllBranchesSeeder extends Seeder
{
    private const KEYWORD = 'dale carnegie';
    private const BASE_API = 'https://api-service.gramedia.com';

    public function run(): void
    {
        $institutionId = (int) (DB::table('institutions')->value('id') ?? 1);
        $branches = Branch::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->get(['id']);

        if ($branches->isEmpty()) {
            $this->command?->warn('Tidak ada cabang aktif. Seeder dihentikan.');
            return;
        }

        $metadata = app(MetadataMappingService::class);
        $products = $this->fetchAllProducts(self::KEYWORD);

        if (empty($products)) {
            $this->command?->warn('Tidak ada produk dari pencarian Gramedia: ' . self::KEYWORD);
            return;
        }

        foreach ($products as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $detail = $this->fetchProductDetail($slug);
            $item = is_array($detail) ? array_merge($row, $detail) : $row;

            $titleRaw = trim((string) ($item['title'] ?? ''));
            if ($titleRaw === '') {
                continue;
            }

            [$title, $subtitle] = $this->splitTitleSubtitle($titleRaw);
            $author = trim((string) ($item['author'] ?? ''));
            $isbn = $this->normalizeIsbn((string) ($item['isbn'] ?? ''));
            $description = trim((string) ($item['description'] ?? ''));
            $coverUrl = $this->extractCoverUrl($item['image'] ?? null);
            $coverPath = $coverUrl !== '' ? $this->downloadCover($coverUrl, $title) : null;
            $ddc = $this->inferDdc($titleRaw, $description);
            $callNumber = $this->makeCallNumber($ddc, $author !== '' ? $author : $title);
            $normalizedTitle = $this->normalizeTitle($title, $subtitle);
            $responsibility = $author !== '' ? Str::limit('oleh ' . $author, 240, '') : null;

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
                'publisher' => 'Gramedia',
                'place_of_publication' => 'Jakarta',
                'publish_year' => null,
                'isbn' => $isbn,
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'edition' => null,
                'physical_desc' => null,
                'extent' => null,
                'dimensions' => null,
                'ddc' => $ddc,
                'call_number' => $callNumber,
                'notes' => $this->buildNotes($item, $slug),
                'general_note' => $this->truncateText($description, 2000),
                'ai_status' => 'draft',
            ]);

            if ($coverPath !== null) {
                $biblio->cover_path = $coverPath;
            }

            $biblio->save();

            $this->syncAuthors($biblio, $author);
            $this->syncSubjects($biblio, $titleRaw, $description, 'Dale Carnegie');
            $this->syncTags($biblio);
            $this->ensureItemsInAllBranches($biblio, $institutionId, $branches->pluck('id')->all());

            $metadata->syncMetadataForBiblio(
                $biblio,
                [
                    'id' => [
                        'title' => $titleRaw,
                        'creator' => $author !== '' ? [$author] : [],
                        'subject' => ['Dale Carnegie', 'Pengembangan Diri'],
                        'description' => $this->truncateText($description, 350),
                        'publisher' => 'Gramedia',
                        'date' => null,
                        'language' => 'id',
                        'identifier' => [$this->productUrl($slug)],
                        'type' => 'buku',
                        'format' => 'teks',
                    ],
                ],
                $this->buildIdentifiers($item, $slug, $coverUrl)
            );
        }
    }

    private function fetchAllProducts(string $keyword): array
    {
        $all = [];
        $page = 1;
        $totalPage = 1;

        do {
            $url = self::BASE_API . '/api/v2/public/search-result-product';
            $response = Http::timeout(35)
                ->retry(2, 500)
                ->withHeaders($this->apiHeaders())
                ->get($url, [
                    'keyword' => $keyword,
                    'page' => $page,
                    'size' => 20,
                ]);

            if (!$response->ok()) {
                break;
            }

            $json = $response->json();
            if (!is_array($json) || ($json['code'] ?? '') !== 'success') {
                break;
            }

            foreach (($json['data'] ?? []) as $row) {
                if (is_array($row)) {
                    $all[] = $row;
                }
            }

            $meta = $json['meta'] ?? [];
            $totalPage = max(1, (int) ($meta['total_page'] ?? 1));
            $page++;
        } while ($page <= $totalPage);

        $unique = [];
        foreach ($all as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug !== '') {
                $unique[$slug] = $row;
            }
        }

        return array_values($unique);
    }

    private function fetchProductDetail(string $slug): ?array
    {
        $url = self::BASE_API . '/api/v2/public/product-detail-meta/' . rawurlencode($slug);
        $response = Http::timeout(35)
            ->retry(2, 500)
            ->withHeaders($this->apiHeaders())
            ->get($url);

        if (!$response->ok()) {
            return null;
        }

        $json = $response->json();
        if (!is_array($json) || ($json['code'] ?? '') !== 'success' || !is_array($json['data'] ?? null)) {
            return null;
        }

        return $json['data'];
    }

    private function apiHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.gramedia.v3+json, */*;',
            'X-Grmd-Time-Zone' => 'Asia/Jakarta',
            'X-Grmd-Device-Type' => 'desktop',
            'User-Agent' => 'NotoBuku Seeder/1.0',
        ];
    }

    private function ensureItemsInAllBranches(Biblio $biblio, int $institutionId, array $branchIds): void
    {
        foreach ($branchIds as $branchId) {
            $exists = Item::query()
                ->where('institution_id', $institutionId)
                ->where('biblio_id', $biblio->id)
                ->where('branch_id', (int) $branchId)
                ->exists();

            if ($exists) {
                continue;
            }

            Item::query()->create([
                'institution_id' => $institutionId,
                'branch_id' => (int) $branchId,
                'biblio_id' => $biblio->id,
                'barcode' => $this->generateUniqueCode('NB'),
                'accession_number' => $this->generateUniqueCode('ACC'),
                'status' => 'available',
                'acquisition_source' => 'beli',
                'circulation_status' => 'circulating',
            ]);
        }
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

    private function syncSubjects(Biblio $biblio, string $title, string $description, string $keyword): void
    {
        $subjects = [$keyword, 'Filsafat'];
        $haystack = strtolower($title . ' ' . $description);
        if (str_contains($haystack, 'plato')) $subjects[] = 'Plato';
        if (str_contains($haystack, 'aristoteles')) $subjects[] = 'Aristoteles';
        if (str_contains($haystack, 'yunani')) $subjects[] = 'Filsafat Yunani';

        $subjects = array_values(array_unique($subjects));
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

    private function syncTags(Biblio $biblio): void
    {
        $tags = ['gramedia', 'dale carnegie'];
        $sync = [];
        foreach ($tags as $idx => $name) {
            $tag = Tag::query()->firstOrCreate(
                ['normalized_name' => $this->normalizeLoose($name)],
                ['name' => $name, 'normalized_name' => $this->normalizeLoose($name)]
            );
            $sync[$tag->id] = ['sort_order' => $idx + 1];
        }
        $biblio->tags()->sync($sync);
    }

    private function buildNotes(array $item, string $slug): ?string
    {
        $parts = [];
        if (!empty($item['sku'])) {
            $parts[] = 'SKU: ' . $item['sku'];
        }
        if (!empty($item['format'])) {
            $parts[] = 'Format: ' . $item['format'];
        }
        if (isset($item['final_price']) && is_numeric($item['final_price'])) {
            $parts[] = 'Harga: Rp' . number_format((int) $item['final_price'], 0, ',', '.');
        }
        if (!empty($item['store_name'])) {
            $parts[] = 'Store source: ' . $item['store_name'];
        }
        $parts[] = 'Sumber: ' . $this->productUrl($slug);

        return implode(' | ', $parts);
    }

    private function buildIdentifiers(array $item, string $slug, string $coverUrl): array
    {
        $ids = [
            ['scheme' => 'uri', 'value' => $this->productUrl($slug), 'uri' => $this->productUrl($slug)],
        ];

        if (!empty($item['sku'])) {
            $ids[] = ['scheme' => 'sku', 'value' => (string) $item['sku'], 'uri' => null];
        }
        if (!empty($item['product_meta_id'])) {
            $ids[] = ['scheme' => 'gramedia_product_meta_id', 'value' => (string) $item['product_meta_id'], 'uri' => null];
        }
        if (!empty($item['isbn'])) {
            $isbn = $this->normalizeIsbn((string) $item['isbn']);
            if ($isbn !== null) {
                $ids[] = ['scheme' => 'isbn', 'value' => $isbn, 'uri' => null];
            }
        }
        if ($coverUrl !== '') {
            $ids[] = ['scheme' => 'cover_url', 'value' => $coverUrl, 'uri' => $coverUrl];
        }

        return $ids;
    }

    private function productUrl(string $slug): string
    {
        return 'https://www.gramedia.com/products/' . $slug;
    }

    private function extractCoverUrl($image): string
    {
        if (is_string($image)) {
            return trim($image);
        }

        if (is_array($image)) {
            if (isset($image[0]) && is_array($image[0])) {
                $nested = $this->extractCoverUrl($image[0]);
                if ($nested !== '') {
                    return $nested;
                }
            }
            foreach (['image', 'url', 'large', 'medium', 'small', 'thumbnail', 'src'] as $k) {
                $v = trim((string) ($image[$k] ?? ''));
                if ($v !== '') {
                    return $v;
                }
            }
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

    private function normalizeIsbn(string $isbn): ?string
    {
        $isbn = trim($isbn);
        if ($isbn === '') return null;
        $isbn = preg_replace('/[^0-9Xx-]/', '', $isbn);
        return $isbn !== '' ? $isbn : null;
    }

    private function inferDdc(string $title, string $description): string
    {
        $text = strtolower($title . ' ' . $description);
        if (str_contains($text, 'biografi')) return '920';
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

    private function truncateText(string $text, int $limit): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') return null;
        if (mb_strlen($text) <= $limit) return $text;
        return rtrim(mb_substr($text, 0, $limit - 3)) . '...';
    }

    private function downloadCover(string $url, string $title): ?string
    {
        try {
            $resp = Http::timeout(25)
                ->retry(2, 500)
                ->withHeaders(['User-Agent' => 'NotoBuku Seeder/1.0'])
                ->get($url);
            if (!$resp->ok()) {
                return null;
            }

            $type = strtolower((string) $resp->header('Content-Type', 'image/jpeg'));
            $ext = 'jpg';
            if (str_contains($type, 'png')) $ext = 'png';
            if (str_contains($type, 'webp')) $ext = 'webp';

            $file = 'covers/gramedia-dale-carnegie-' . Str::slug($title) . '-' . Str::lower(Str::random(6)) . '.' . $ext;
            Storage::disk('public')->put($file, $resp->body());
            return $file;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function generateUniqueCode(string $prefix): string
    {
        $date = now()->format('Ymd');
        for ($i = 0; $i < 20; $i++) {
            $code = $prefix . '-' . $date . '-' . Str::upper(Str::random(6));
            $exists = Item::query()->where('barcode', $code)->orWhere('accession_number', $code)->exists();
            if (!$exists) return $code;
        }
        return $prefix . '-' . $date . '-' . Str::upper(Str::random(10));
    }
}
