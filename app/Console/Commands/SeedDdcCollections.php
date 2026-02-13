<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\Biblio;
use App\Models\Item;
use App\Models\Subject;
use App\Services\MetadataMappingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeedDdcCollections extends Command
{
    protected $signature = 'notobuku:seed-ddc {--per=20 : Jumlah koleksi per divisi DDC} {--limit=20 : Alias per} {--no-covers : Jangan download cover} {--dry-run : Tidak simpan ke DB}';

    protected $description = 'Seed koleksi nyata dari Open Library + Google Books untuk DDC 000-900.';

    public function handle(): int
    {
        $per = (int) ($this->option('per') ?? $this->option('limit') ?? 20);
        $per = $per > 0 ? $per : 20;
        $noCovers = (bool) $this->option('no-covers');
        $dryRun = (bool) $this->option('dry-run');

        $institutionId = (int) (DB::table('institutions')->value('id') ?? 1);
        if ($institutionId <= 0) {
            $this->error('Institution tidak ditemukan.');
            return 1;
        }

        $branchId = DB::table('branches')->where('institution_id', $institutionId)->value('id');
        $branchId = $branchId ? (int) $branchId : null;

        $classes = $this->ddcSubjects();
        $metadataService = app(MetadataMappingService::class);
        $allowedLanguages = ['id', 'en', 'ms'];

        $totalCreated = 0;
        $coverDownloads = 0;

        foreach ($classes as $classCode => $subjects) {
            $this->info("DDC {$classCode}: target {$per} koleksi");
            $collected = [];
            $seen = [];

            $subjects = $this->expandSubjects($classCode, $subjects);
            $maxPages = 5;
            $limitPerPage = 100;

            foreach ($subjects as $subject) {
                if (count($collected) >= $per) break;

                $pageOffset = random_int(1, 20);
                for ($page = 1; $page <= $maxPages; $page++) {
                    if (count($collected) >= $per) break;

                    $docs = $this->fetchOpenLibraryBySubject($subject, $limitPerPage, $page + $pageOffset);
                    foreach ($docs as $doc) {
                        if (count($collected) >= $per) break;

                        $title = trim((string) ($doc['title'] ?? ''));
                        $author = $doc['author_name'][0] ?? ($doc['first_author'] ?? null);
                        if ($title === '') continue;

                        $key = Str::lower($title . '|' . ($author ?? ''));
                        if (isset($seen[$key])) continue;
                        $seen[$key] = true;

                        $collected[] = $doc;
                    }
                }
            }

            if (count($collected) < $per) {
                $fallbackQueries = $this->fallbackQueries($classCode);
                foreach ($fallbackQueries as $query) {
                    if (count($collected) >= $per) break;

                    $pageOffset = random_int(1, 20);
                    for ($page = 1; $page <= $maxPages; $page++) {
                        if (count($collected) >= $per) break;

                        $docs = $this->fetchOpenLibraryByQuery($query, $limitPerPage, $page + $pageOffset);
                        foreach ($docs as $doc) {
                            if (count($collected) >= $per) break;

                            $title = trim((string) ($doc['title'] ?? ''));
                            $author = $doc['author_name'][0] ?? ($doc['first_author'] ?? null);
                            if ($title === '') continue;

                            $key = Str::lower($title . '|' . ($author ?? ''));
                            if (isset($seen[$key])) continue;
                            $seen[$key] = true;

                            $collected[] = $doc;
                        }
                    }
                }
            }

            if (count($collected) < $per) {
                $gbQueries = $this->fallbackQueries($classCode);
                foreach ($gbQueries as $query) {
                    if (count($collected) >= $per) break;

                    for ($start = 0; $start <= 80; $start += 40) {
                        if (count($collected) >= $per) break;

                        $docs = $this->fetchGoogleBooksByQuery($query, $start, 40);
                        foreach ($docs as $doc) {
                            if (count($collected) >= $per) break;

                            $title = trim((string) ($doc['title'] ?? ''));
                            $author = $doc['author_name'][0] ?? ($doc['first_author'] ?? null);
                            if ($title === '') continue;

                            $key = Str::lower($title . '|' . ($author ?? ''));
                            if (isset($seen[$key])) continue;
                            $seen[$key] = true;

                            $collected[] = $doc;
                        }
                    }
                }
            }

            if (empty($collected)) {
                $this->warn("DDC {$classCode}: tidak ada hasil.");
                continue;
            }

            foreach ($collected as $doc) {
                $title = trim((string) ($doc['title'] ?? ''));
                $author = $doc['author_name'][0] ?? ($doc['first_author'] ?? null);
                if ($title === '') continue;

                $isbnList = array_values(array_filter((array) ($doc['isbn'] ?? [])));
                $isbn = $this->pickIsbn($isbnList);

                $normalizedTitle = $this->normalizeTitle($title, null);
                $existsQuery = Biblio::query()->where('institution_id', $institutionId);
                if ($isbn) {
                    $existsQuery->where('isbn', $isbn);
                } else {
                    $existsQuery->where('normalized_title', $normalizedTitle);
                    if ($author) {
                        $existsQuery->where('responsibility_statement', "oleh {$author}");
                    }
                }
                $exists = $existsQuery->exists();
                if ($exists) continue;

                $gb = $doc['_gb'] ?? $this->fetchGoogleBooks($isbn, $title, $author);

                $publisher = $gb['publisher'] ?? null;
                $publishYear = $gb['year'] ?? ($doc['first_publish_year'] ?? null);
                $language = $this->normalizeLanguage($gb['language'] ?? ($doc['language'][0] ?? null));
                if ($language && !$this->isAllowedLanguage($language, $allowedLanguages)) {
                    continue;
                }
                $pageCount = $gb['pageCount'] ?? null;
                $description = $gb['description'] ?? null;
                $subjects = $this->mergeSubjects($doc['subject'] ?? [], $gb['categories'] ?? []);

                $coverPath = null;
                if (!$noCovers) {
                    $coverUrl = $this->pickCoverUrl($doc, $gb);
                    if ($coverUrl) {
                        $coverPath = $this->downloadCover($coverUrl, $title, $author, $classCode);
                        if ($coverPath) $coverDownloads++;
                    }
                }

                $ddc = $classCode;
                $callNumber = $this->makeCallNumber($ddc, $author ?? $title);

                $biblioData = [
                    'institution_id' => $institutionId,
                    'title' => $title,
                    'subtitle' => $gb['subtitle'] ?? null,
                    'normalized_title' => $normalizedTitle,
                    'responsibility_statement' => $author ? "oleh {$author}" : null,
                    'publisher' => $publisher,
                    'place_of_publication' => $gb['place'] ?? null,
                    'publish_year' => $publishYear,
                    'isbn' => $isbn,
                    'issn' => null,
                    'language' => $language ?? 'id',
                    'edition' => $gb['edition'] ?? null,
                    'physical_desc' => $pageCount ? "{$pageCount} hlm" : null,
                    'extent' => $pageCount ? "{$pageCount} hlm" : null,
                    'dimensions' => null,
                    'illustrations' => null,
                    'series_title' => $gb['series'] ?? null,
                    'cover_path' => $coverPath,
                    'ddc' => $ddc,
                    'call_number' => $callNumber,
                    'notes' => $description,
                    'bibliography_note' => null,
                    'general_note' => null,
                    'material_type' => 'buku',
                    'media_type' => 'teks',
                    'audience' => null,
                    'is_reference' => false,
                    'ai_status' => 'draft',
                ];

                if ($dryRun) {
                    $this->line("DRY: {$title}");
                    continue;
                }

                $biblio = Biblio::create($biblioData);

                // authors
                if ($author) {
                    $authorModel = Author::query()->firstOrCreate(
                        ['normalized_name' => $this->normalizeLoose($author)],
                        ['name' => $author, 'normalized_name' => $this->normalizeLoose($author)]
                    );
                    $biblio->authors()->syncWithoutDetaching([
                        $authorModel->id => ['role' => 'pengarang', 'sort_order' => 1],
                    ]);
                }

                // subjects
                $subjectTerms = array_slice($subjects, 0, 6);
                foreach ($subjectTerms as $i => $term) {
                    $subjectModel = Subject::query()->firstOrCreate(
                        ['normalized_term' => $this->normalizeLoose($term)],
                        [
                            'name' => $term,
                            'term' => $term,
                            'normalized_term' => $this->normalizeLoose($term),
                            'scheme' => 'local',
                        ]
                    );
                    $biblio->subjects()->syncWithoutDetaching([
                        $subjectModel->id => ['type' => 'topic', 'sort_order' => $i + 1],
                    ]);
                }

                // identifiers + dc_i18n
                $identifiers = [];
                if ($isbn) {
                    $identifiers[] = ['scheme' => 'isbn', 'value' => $isbn, 'uri' => null];
                }
                if (!empty($doc['key'])) {
                    $identifiers[] = ['scheme' => 'openlibrary', 'value' => $doc['key'], 'uri' => "https://openlibrary.org{$doc['key']}" ];
                }

                $dcI18n = [
                    'en' => [
                        'title' => $title,
                        'creator' => array_values(array_filter([$author])),
                        'subject' => $subjectTerms,
                        'description' => $description,
                        'publisher' => $publisher,
                        'date' => $publishYear ? (string) $publishYear : null,
                        'language' => $language,
                        'identifier' => $isbn ? [$isbn] : [],
                        'type' => 'buku',
                        'format' => 'teks',
                    ]
                ];

                $metadataService->syncMetadataForBiblio($biblio, $dcI18n, $identifiers);

                // items (1 copy)
                $barcode = $this->generateUniqueCode('NB');
                $acc = $this->generateUniqueCode('ACC');
                Item::create([
                    'institution_id' => $institutionId,
                    'branch_id' => $branchId,
                    'shelf_id' => null,
                    'biblio_id' => $biblio->id,
                    'barcode' => $barcode,
                    'accession_number' => $acc,
                    'status' => 'available',
                    'notes' => null,
                ]);

                $totalCreated++;
                $this->line("+ {$title}");
            }
        }

        $this->info("Selesai. Dibuat: {$totalCreated}. Cover: {$coverDownloads}");
        return 0;
    }

    private function ddcSubjects(): array
    {
        return [
            '000' => [
                'computer science', 'information science', 'library science', 'encyclopedias', 'data processing',
                'informatics', 'knowledge management', 'archives', 'bibliography', 'digital libraries',
                'information retrieval', 'systems analysis',
            ],
            '100' => [
                'philosophy', 'psychology', 'ethics', 'logic', 'metaphysics',
                'epistemology', 'philosophy of mind', 'moral philosophy', 'psychology research',
                'cognitive psychology', 'behavioral science',
            ],
            '200' => [
                'religion', 'theology', 'bible', 'islam', 'christianity',
                'quran', 'hadith', 'comparative religion', 'hinduism', 'buddhism',
                'religious studies', 'spirituality',
            ],
            '300' => [
                'social sciences', 'economics', 'sociology', 'political science', 'education', 'law',
                'anthropology', 'public administration', 'communication', 'management', 'finance',
                'development studies', 'social policy',
            ],
            '400' => [
                'language', 'linguistics', 'english language', 'indonesian language', 'translation',
                'lexicography', 'grammar', 'language learning', 'bilingual', 'phonetics',
                'semantics', 'writing',
            ],
            '500' => [
                'science', 'mathematics', 'physics', 'chemistry', 'biology', 'astronomy', 'geology',
                'statistics', 'environmental science', 'ecology', 'botany', 'zoology',
                'earth science', 'oceanography',
            ],
            '600' => [
                'technology', 'medicine', 'engineering', 'agriculture', 'business', 'computer engineering',
                'information technology', 'public health', 'pharmacy', 'civil engineering', 'mechanical engineering',
                'electrical engineering', 'entrepreneurship',
            ],
            '700' => [
                'arts', 'music', 'architecture', 'painting', 'photography', 'design',
                'graphic design', 'film', 'theatre', 'dance', 'sculpture',
                'visual arts', 'art history',
            ],
            '800' => [
                'literature', 'fiction', 'poetry', 'drama', 'literary criticism',
                'short stories', 'novel', 'world literature', 'indonesian literature', 'english literature',
                'literary theory', 'creative writing',
            ],
            '900' => [
                'history', 'geography', 'biography', 'asia', 'indonesia', 'world war',
                'europe history', 'american history', 'southeast asia', 'maps',
                'travel', 'historiography',
            ],
        ];
    }

    private function expandSubjects(string $classCode, array $subjects): array
    {
        $extra = [
            '000' => ['sistem informasi', 'perpustakaan', 'ensiklopedia', 'data mining'],
            '100' => ['filsafat', 'psikologi', 'etika', 'logika'],
            '200' => ['agama', 'teologi', 'islam', 'kristen', 'buddha', 'hindu'],
            '300' => ['ilmu sosial', 'ekonomi', 'sosiologi', 'politik', 'pendidikan', 'hukum'],
            '400' => ['bahasa', 'linguistik', 'bahasa indonesia', 'bahasa inggris', 'terjemahan'],
            '500' => ['sains', 'matematika', 'fisika', 'kimia', 'biologi', 'astronomi', 'geologi'],
            '600' => ['teknologi', 'kedokteran', 'teknik', 'pertanian', 'bisnis', 'kesehatan'],
            '700' => ['seni', 'musik', 'arsitektur', 'lukisan', 'fotografi', 'desain'],
            '800' => ['sastra', 'puisi', 'drama', 'novel', 'kritik sastra'],
            '900' => ['sejarah', 'geografi', 'biografi', 'indonesia', 'asia', 'dunia'],
        ];

        return array_values(array_unique(array_merge($subjects, $extra[$classCode] ?? [])));
    }

    private function fallbackQueries(string $classCode): array
    {
        return match ($classCode) {
            '000' => ['information systems', 'library management', 'encyclopedia'],
            '100' => ['philosophy ethics', 'psychology textbook'],
            '200' => ['religious studies', 'islamic studies', 'christian theology'],
            '300' => ['social science', 'economics textbook', 'education policy'],
            '400' => ['language learning', 'linguistics textbook'],
            '500' => ['science textbook', 'biology textbook', 'physics textbook', 'chemistry textbook'],
            '600' => ['technology textbook', 'engineering handbook', 'medical textbook', 'agriculture'],
            '700' => ['art history', 'music history', 'architecture design'],
            '800' => ['literature classics', 'novel', 'poetry collection'],
            '900' => ['world history', 'indonesia history', 'geography atlas', 'biography'],
            default => [],
        };
    }

    private function fetchOpenLibraryBySubject(string $subject, int $limit, int $page = 1): array
    {
        $q = 'subject:"' . $subject . '"';
        $resp = Http::retry(3, 500)
            ->get('https://openlibrary.org/search.json', [
                'q' => $q,
                'limit' => $limit,
                'page' => $page,
                'fields' => 'title,author_name,first_publish_year,isbn,subject,cover_i,language,key,edition_count',
            ]);

        if (!$resp->ok()) {
            return [];
        }

        $data = $resp->json();
        return $data['docs'] ?? [];
    }

    private function fetchOpenLibraryByQuery(string $query, int $limit, int $page = 1): array
    {
        $resp = Http::retry(3, 500)
            ->get('https://openlibrary.org/search.json', [
                'q' => $query,
                'limit' => $limit,
                'page' => $page,
                'fields' => 'title,author_name,first_publish_year,isbn,subject,cover_i,language,key,edition_count',
            ]);

        if (!$resp->ok()) {
            return [];
        }

        $data = $resp->json();
        return $data['docs'] ?? [];
    }

    private function fetchGoogleBooks(?string $isbn, string $title, ?string $author): array
    {
        $query = $isbn
            ? 'isbn:' . $isbn
            : 'intitle:"' . $title . '"' . ($author ? ' inauthor:"' . $author . '"' : '');

        $resp = Http::retry(3, 500)->get('https://www.googleapis.com/books/v1/volumes', [
            'q' => $query,
            'maxResults' => 1,
            'printType' => 'books',
            'projection' => 'full',
        ]);

        if (!$resp->ok()) return [];
        $items = $resp->json('items') ?? [];
        if (empty($items)) return [];

        $info = $items[0]['volumeInfo'] ?? [];
        $published = $info['publishedDate'] ?? null;
        $year = null;
        if (is_string($published) && preg_match('/^(\d{4})/', $published, $m)) {
            $year = (int) $m[1];
        }

        $isbn13 = null;
        $industry = $info['industryIdentifiers'] ?? [];
        foreach ($industry as $id) {
            if (($id['type'] ?? '') === 'ISBN_13') {
                $isbn13 = $id['identifier'] ?? null;
                break;
            }
        }
        if (!$isbn13 && !empty($industry)) {
            $isbn13 = $industry[0]['identifier'] ?? null;
        }

        return [
            'subtitle' => $info['subtitle'] ?? null,
            'publisher' => $info['publisher'] ?? null,
            'year' => $year,
            'language' => $info['language'] ?? null,
            'pageCount' => $info['pageCount'] ?? null,
            'description' => $info['description'] ?? null,
            'categories' => $info['categories'] ?? [],
            'imageLinks' => $info['imageLinks'] ?? [],
            'isbn' => $isbn13,
        ];
    }

    private function fetchGoogleBooksByQuery(string $query, int $startIndex, int $maxResults): array
    {
        $resp = Http::retry(3, 500)->get('https://www.googleapis.com/books/v1/volumes', [
            'q' => $query,
            'startIndex' => $startIndex,
            'maxResults' => $maxResults,
            'printType' => 'books',
            'projection' => 'full',
        ]);

        if (!$resp->ok()) return [];
        $items = $resp->json('items') ?? [];
        if (empty($items)) return [];

        $docs = [];
        foreach ($items as $item) {
            $info = $item['volumeInfo'] ?? [];
            $title = $info['title'] ?? null;
            $authors = $info['authors'] ?? [];
            if (!$title) continue;

            $published = $info['publishedDate'] ?? null;
            $year = null;
            if (is_string($published) && preg_match('/^(\d{4})/', $published, $m)) {
                $year = (int) $m[1];
            }

            $isbn13 = null;
            $industry = $info['industryIdentifiers'] ?? [];
            foreach ($industry as $id) {
                if (($id['type'] ?? '') === 'ISBN_13') {
                    $isbn13 = $id['identifier'] ?? null;
                    break;
                }
            }
            if (!$isbn13 && !empty($industry)) {
                $isbn13 = $industry[0]['identifier'] ?? null;
            }

            $docs[] = [
                'title' => $title,
                'author_name' => $authors,
                'first_publish_year' => $year,
                'isbn' => $isbn13 ? [$isbn13] : [],
                'subject' => $info['categories'] ?? [],
                'cover_i' => null,
                'language' => isset($info['language']) ? [$info['language']] : [],
                'key' => isset($item['id']) ? ('/books/' . $item['id']) : null,
                '_gb' => [
                    'subtitle' => $info['subtitle'] ?? null,
                    'publisher' => $info['publisher'] ?? null,
                    'year' => $year,
                    'language' => $info['language'] ?? null,
                    'pageCount' => $info['pageCount'] ?? null,
                    'description' => $info['description'] ?? null,
                    'categories' => $info['categories'] ?? [],
                    'imageLinks' => $info['imageLinks'] ?? [],
                    'isbn' => $isbn13,
                ],
            ];
        }

        return $docs;
    }

    private function pickIsbn(array $isbnList): ?string
    {
        $isbnList = array_values(array_filter($isbnList));
        foreach ($isbnList as $id) {
            $clean = preg_replace('/[^0-9Xx]/', '', (string) $id);
            if (strlen($clean) === 13) return $clean;
        }
        foreach ($isbnList as $id) {
            $clean = preg_replace('/[^0-9Xx]/', '', (string) $id);
            if (strlen($clean) === 10) return $clean;
        }
        return null;
    }

    private function normalizeLanguage(?string $lang): ?string
    {
        if (!$lang) return null;
        $lang = strtolower(trim($lang));
        $map = [
            'eng' => 'en',
            'ind' => 'id',
            'spa' => 'es',
            'fre' => 'fr',
            'fra' => 'fr',
            'ger' => 'de',
            'deu' => 'de',
            'jpn' => 'ja',
            'zho' => 'zh',
            'chi' => 'zh',
            'ara' => 'ar',
        ];
        return $map[$lang] ?? (strlen($lang) <= 3 ? $lang : substr($lang, 0, 3));
    }

    private function mergeSubjects(array $a, array $b): array
    {
        $out = [];
        foreach (array_merge($a, $b) as $s) {
            $s = trim((string) $s);
            if ($s === '') continue;
            $key = Str::lower($s);
            if (!isset($out[$key])) $out[$key] = $s;
        }
        return array_values($out);
    }

    private function pickCoverUrl(array $doc, array $gb): ?string
    {
        if (!empty($doc['cover_i'])) {
            return 'https://covers.openlibrary.org/b/id/' . $doc['cover_i'] . '-L.jpg?default=false';
        }
        $img = $gb['imageLinks']['thumbnail'] ?? ($gb['imageLinks']['smallThumbnail'] ?? null);
        return $img ?: null;
    }

    private function downloadCover(string $url, string $title, ?string $author, string $ddc): ?string
    {
        try {
            $resp = Http::retry(3, 500)->get($url);
            if (!$resp->ok()) return null;
            $ext = 'jpg';
            $name = Str::slug($title . '-' . ($author ?? ''));
            $name = $name !== '' ? $name : Str::random(10);
            $file = 'covers/' . $ddc . '-' . $name . '-' . Str::random(6) . '.' . $ext;
            Storage::disk('public')->put($file, $resp->body());
            return $file;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeLoose(string $text): string
    {
        return Str::of($text)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->toString();
    }

    private function normalizeTitle(string $title, ?string $subtitle = null): string
    {
        $t = trim($title);
        $s = trim((string) $subtitle);
        $base = $t;
        if ($s !== '') $base .= ' ' . $s;
        return $this->normalizeLoose($base);
    }

    private function makeCallNumber(string $ddc, string $authorOrTitle): string
    {
        $parts = preg_split('/\s+/', trim($authorOrTitle));
        $last = $parts ? end($parts) : $authorOrTitle;
        $cutter = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $last), 0, 3));
        if ($cutter === '') $cutter = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $authorOrTitle), 0, 3));
        if ($cutter === '') $cutter = 'XXX';
        return trim($ddc . ' ' . $cutter);
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

    private function isAllowedLanguage(string $language, array $allowed): bool
    {
        $language = strtolower($language);
        if (in_array($language, $allowed, true)) return true;
        if (str_contains($language, '-')) {
            $base = explode('-', $language)[0];
            return in_array($base, $allowed, true);
        }
        return false;
    }
}
