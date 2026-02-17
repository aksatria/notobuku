<?php

namespace App\Services\Search;

use App\Models\Author;
use App\Models\Biblio;
use App\Models\Subject;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BiblioSearchService
{
    public function __construct(private MeilisearchClient $client)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('search.enabled', false)
            && (string) config('search.driver') === 'meilisearch'
            && $this->client->isConfigured();
    }

    public function search(array $params, int $institutionId): ?array
    {
        if (!$this->enabled()) {
            return null;
        }

        $q = trim((string) ($params['q'] ?? ''));
        $title = trim((string) ($params['title'] ?? ''));
        $authorName = trim((string) ($params['author_name'] ?? ''));
        $subjectTerm = trim((string) ($params['subject_term'] ?? ''));
        $isbn = trim((string) ($params['isbn'] ?? ''));
        $callNumber = trim((string) ($params['call_number'] ?? ''));
        $language = trim((string) ($params['language'] ?? ''));
        $materialType = trim((string) ($params['material_type'] ?? ''));
        $mediaType = trim((string) ($params['media_type'] ?? ''));
        $languageList = $this->normalizeFilterArray($params['language_list'] ?? []);
        $materialTypeList = $this->normalizeFilterArray($params['material_type_list'] ?? []);
        $mediaTypeList = $this->normalizeFilterArray($params['media_type_list'] ?? []);
        $ddc = trim((string) ($params['ddc'] ?? ''));
        $year = trim((string) ($params['year'] ?? ''));
        $yearFrom = max(0, (int) ($params['year_from'] ?? 0));
        $yearTo = max(0, (int) ($params['year_to'] ?? 0));
        $onlyAvailable = (bool) ($params['onlyAvailable'] ?? false);
        $author = trim((string) ($params['author'] ?? ''));
        $subject = trim((string) ($params['subject'] ?? ''));
        $publisher = trim((string) ($params['publisher'] ?? ''));
        $branchList = $this->normalizeFilterArray($params['branch_list'] ?? [], true);
        $authorList = $this->normalizeFilterArray($params['author_list'] ?? [], true);
        $subjectList = $this->normalizeFilterArray($params['subject_list'] ?? [], true);
        $publisherList = $this->normalizeFilterArray($params['publisher_list'] ?? []);
        $sort = trim((string) ($params['sort'] ?? 'relevant'));
        $page = max(1, (int) ($params['page'] ?? 1));

        $branchId = isset($params['branch_id']) ? (int) $params['branch_id'] : null;
        $queryParts = array_filter([$q, $title, $authorName, $subjectTerm, $publisher, $isbn, $callNumber]);
        $q = trim(implode(' ', $queryParts));

        // Deteksi ISBN / call number untuk precision-first
        $forceExactSort = false;
        if ($q !== '' && $isbn === '' && $this->isLikelyIsbn($q)) {
            $isbn = $q;
            $q = '';
            $forceExactSort = true;
        }
        if ($q !== '' && $callNumber === '' && $this->isLikelyCallNumber($q)) {
            $callNumber = $q;
            $q = '';
            $forceExactSort = true;
        }

        if ($q !== '') {
            $q = $this->expandQueryWithSynonyms($q, $institutionId, $branchId);
        }

        $filters = [];
        $filters[] = "institution_id = {$institutionId}";

        if ($ddc !== '') {
            $ddcBase = $this->extractDdcBase($ddc);
            if ($ddcBase !== '') {
                $filters[] = "(ddc = \"" . $this->escapeFilter($ddc) . "\" OR ddc_base = \"" . $this->escapeFilter($ddcBase) . "\")";
            } else {
                $filters[] = "ddc = \"" . $this->escapeFilter($ddc) . "\"";
            }
        }

        if ($yearFrom > 0 && $yearTo > 0) {
            $from = min($yearFrom, $yearTo);
            $to = max($yearFrom, $yearTo);
            $filters[] = "publish_year >= {$from}";
            $filters[] = "publish_year <= {$to}";
        } elseif ($yearFrom > 0) {
            $filters[] = "publish_year >= {$yearFrom}";
        } elseif ($yearTo > 0) {
            $filters[] = "publish_year <= {$yearTo}";
        } elseif ($year !== '') {
            $yearNum = (int) $year;
            if ($yearNum > 0) {
                $filters[] = "publish_year = {$yearNum}";
            }
        }

        if (!empty($publisherList)) {
            $filters[] = '(' . implode(' OR ', array_map(fn ($v) => "publisher = \"" . $this->escapeFilter($v) . "\"", $publisherList)) . ')';
        } elseif ($publisher !== '') {
            $filters[] = "publisher = \"" . $this->escapeFilter($publisher) . "\"";
        }

        if (!empty($authorList)) {
            $filters[] = '(' . implode(' OR ', array_map(fn ($v) => "author_ids = " . (int) $v, $authorList)) . ')';
        } elseif ($author !== '') {
            $filters[] = "author_ids = " . (int) $author;
        }

        if (!empty($subjectList)) {
            $filters[] = '(' . implode(' OR ', array_map(fn ($v) => "subject_ids = " . (int) $v, $subjectList)) . ')';
        } elseif ($subject !== '') {
            $filters[] = "subject_ids = " . (int) $subject;
        }

        if ($isbn !== '') {
            $filters[] = "isbn = \"" . $this->escapeFilter($isbn) . "\"";
        }

        if ($callNumber !== '') {
            $filters[] = "call_number = \"" . $this->escapeFilter($callNumber) . "\"";
        }

        if (!empty($languageList)) {
            $filters[] = '(' . implode(' OR ', array_map(fn ($v) => "language = \"" . $this->escapeFilter($v) . "\"", $languageList)) . ')';
        } elseif ($language !== '') {
            $filters[] = "language = \"" . $this->escapeFilter($language) . "\"";
        }

        if (!empty($materialTypeList)) {
            $filters[] = '(' . implode(' OR ', array_map(fn ($v) => "material_type = \"" . $this->escapeFilter($v) . "\"", $materialTypeList)) . ')';
        } elseif ($materialType !== '') {
            $filters[] = "material_type = \"" . $this->escapeFilter($materialType) . "\"";
        }

        if (!empty($mediaTypeList)) {
            $filters[] = '(' . implode(' OR ', array_map(fn ($v) => "media_type = \"" . $this->escapeFilter($v) . "\"", $mediaTypeList)) . ')';
        } elseif ($mediaType !== '') {
            $filters[] = "media_type = \"" . $this->escapeFilter($mediaType) . "\"";
        }

        if (!empty($branchList)) {
            $filters[] = '(' . implode(' OR ', array_map(fn ($v) => "branch_ids = " . (int) $v, $branchList)) . ')';
        } elseif ($branchId) {
            $filters[] = "branch_ids = {$branchId}";
        }

        if ($onlyAvailable) {
            $filters[] = "available = true";
        }

        $sortParam = [];
        if ($forceExactSort) {
            $sortParam = ['popularity_score:desc', 'title:asc'];
        } else {
            $branchOverride = $this->resolveBranchSortOverride($sort, $branchId, $q);
            if (!empty($branchOverride)) {
                $sortParam = $branchOverride;
            } elseif ($sort === 'latest') {
                $sortParam = ['publish_year:desc', 'popularity_score:desc'];
            } elseif ($sort === 'popular') {
                $sortParam = ['popularity_score:desc', 'items_count:desc'];
            } elseif ($sort === 'available') {
                $sortParam = ['available_items_count:desc', 'popularity_score:desc', 'title:asc'];
            } elseif ($q === '') {
                $sortParam = ['available_items_count:desc', 'popularity_score:desc', 'title:asc'];
            } else {
                $sortParam = ['available_items_count:desc', 'popularity_score:desc', 'title:asc'];
            }
        }

        $perPage = (int) config('search.per_page', 12);
        $offset = ($page - 1) * $perPage;

        $payload = [
            'limit' => $perPage,
            'offset' => $offset,
            'filter' => $filters,
            'facets' => ['author_ids', 'subject_ids', 'publisher', 'publish_year', 'language', 'material_type', 'media_type', 'available', 'branch_ids'],
            'sort' => $sortParam,
            'attributesToRetrieve' => ['id'],
        ];

        if ($q !== '') {
            $tokens = preg_split('/\s+/', trim($q));
            $tokens = array_values(array_filter(array_map('trim', $tokens)));
            $minLen = !empty($tokens) ? min(array_map('mb_strlen', $tokens)) : 999;
            [$oneTypo, $twoTypos] = $this->resolveTypoTolerance($branchId, $minLen);
            $payload['typoTolerance'] = [
                'enabled' => true,
                'minWordSizeForTypos' => [
                    'oneTypo' => $oneTypo,
                    'twoTypos' => $twoTypos,
                ],
            ];
        }

        $data = $this->client->search($q, $payload);
        if ($branchId && ($data['estimatedTotalHits'] ?? 0) === 0 && $q !== '') {
            // fallback tanpa filter cabang jika tidak ada hasil
            $fallbackPayload = $payload;
            $fallbackPayload['filter'] = array_values(array_filter($filters, function ($f) {
                return !str_starts_with($f, 'branch_ids = ');
            }));
            $data = $this->client->search($q, $fallbackPayload);
        }
        if (!$data) {
            return null;
        }

        $ids = collect($data['hits'] ?? [])->pluck('id')->filter()->values()->all();
        $total = (int) ($data['estimatedTotalHits'] ?? $data['nbHits'] ?? 0);
        $facets = (array) ($data['facetDistribution'] ?? []);

        return [
            'ids' => $ids,
            'total' => $total,
            'facets' => $facets,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function hydrateResults(array $ids, int $total, int $page, int $perPage): LengthAwarePaginator
    {
        if (empty($ids)) {
            return new LengthAwarePaginator([], $total, $perPage, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
        }

        $biblios = Biblio::query()
            ->whereIn('id', $ids)
            ->with(['authors:id,name'])
            ->withCount([
                'items',
                'availableItems as available_items_count'
            ])
            ->orderByRaw('FIELD(id, ' . implode(',', array_map('intval', $ids)) . ')')
            ->get();

        return new LengthAwarePaginator($biblios, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    public function mapAuthorFacets(array $facetDistribution): Collection
    {
        $rows = $facetDistribution['author_ids'] ?? [];
        if (empty($rows)) {
            return collect();
        }

        $ids = array_map('intval', array_keys($rows));
        $names = Author::query()
            ->whereIn('id', $ids)
            ->pluck('name', 'id');

        return collect($rows)
            ->map(function ($total, $id) use ($names) {
                $id = (int) $id;
                return (object) [
                    'id' => $id,
                    'name' => (string) ($names[$id] ?? ''),
                    'total' => (int) $total,
                ];
            })
            ->filter(fn($row) => $row->name !== '')
            ->sortByDesc('total')
            ->values();
    }

    public function mapSubjectFacets(array $facetDistribution): Collection
    {
        $rows = $facetDistribution['subject_ids'] ?? [];
        if (empty($rows)) {
            return collect();
        }

        $ids = array_map('intval', array_keys($rows));
        $subjects = Subject::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($rows)
            ->map(function ($total, $id) use ($subjects) {
                $id = (int) $id;
                $subj = $subjects[$id] ?? null;
                return (object) [
                    'id' => $id,
                    'term' => $subj?->term,
                    'name' => $subj?->name,
                    'total' => (int) $total,
                ];
            })
            ->filter(fn($row) => !empty($row->term) || !empty($row->name))
            ->sortByDesc('total')
            ->values();
    }

    public function mapPublisherFacets(array $facetDistribution): Collection
    {
        $rows = $facetDistribution['publisher'] ?? [];
        if (empty($rows)) {
            return collect();
        }

        return collect($rows)
            ->map(function ($total, $name) {
                return (object) [
                    'publisher' => (string) $name,
                    'total' => (int) $total,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    public function indexDocuments(Collection $biblios): void
    {
        if (!$this->enabled()) {
            return;
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('biblio_metrics')) {
            $biblios->loadMissing('metric');
        }
        $biblios->loadMissing('items:id,biblio_id,branch_id');

        $documents = $biblios->map(function (Biblio $biblio) {
            return $this->toDocument($biblio);
        })->values()->all();

        $this->client->addDocuments($documents);
    }

    public function deleteDocuments(array $ids): void
    {
        if (!$this->enabled()) {
            return;
        }
        $this->client->deleteDocuments($ids);
    }

    public function ensureSettings(): void
    {
        if (!$this->enabled()) {
            return;
        }

        $settings = [
            // Boost relevance: judul > subjek > pengarang > penerbit
            'searchableAttributes' => [
                'title',
                'normalized_title',
                'subtitle',
                'call_number',
                'isbn',
                'subjects',
                'authors',
                'publisher',
                'issn',
                'series_title',
                'ddc',
                'identifiers',
                'notes',
                'general_note',
                'bibliography_note',
                'ai_summary',
                'place_of_publication',
                'responsibility_statement',
            ],
            'filterableAttributes' => [
                'institution_id',
                'branch_ids',
                'author_ids',
                'subject_ids',
                'publisher',
                'publish_year',
                'language',
                'material_type',
                'media_type',
                'available',
                'is_reference',
                'ddc',
                'ddc_base',
                'call_number',
                'isbn',
                'issn',
            ],
            'sortableAttributes' => [
                'title',
                'publish_year',
                'items_count',
                'available_items_count',
                'popularity_score',
                'created_at',
            ],
            // Boost judul > subjek > pengarang > penerbit via attribute ranking order
            // (searchableAttributes order) + prioritizing attribute/exactness earlier.
            'rankingRules' => [
                'words',
                'typo',
                'attribute',
                'proximity',
                'exactness',
                'sort',
            ],
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => [
                    'oneTypo' => (int) config('search.typo_tolerance.one_typo', 3),
                    'twoTypos' => (int) config('search.typo_tolerance.two_typos', 5),
                ],
            ],
            'synonyms' => (array) config('search.synonyms', []),
            'stopWords' => $this->loadStopWords((int) config('notobuku.opac.public_institution_id', 1), null),
        ];

        $this->client->updateSettings($settings);
    }

    private function toDocument(Biblio $biblio): array
    {
        $biblio->loadMissing(['authors', 'subjects', 'identifiers', 'metadata']);

        $authorNames = $biblio->authors?->pluck('name')->filter()->values()->all() ?? [];
        $authorIds = $biblio->authors?->pluck('id')->filter()->values()->all() ?? [];
        $subjectNames = $biblio->subjects?->map(function ($s) {
            return $s->term ?? $s->name ?? null;
        })->filter()->values()->all() ?? [];
        $subjectIds = $biblio->subjects?->pluck('id')->filter()->values()->all() ?? [];
        $branchIds = $biblio->relationLoaded('items')
            ? $biblio->items->pluck('branch_id')->filter()->unique()->values()->all()
            : [];
        $identifiers = $biblio->identifiers?->map(function ($id) {
            return trim((string) ($id->scheme . ':' . $id->value));
        })->filter()->values()->all() ?? [];

        $dc = (array) ($biblio->metadata?->dublin_core_json ?? []);
        $dcCreators = (array) ($dc['creator'] ?? []);
        $dcSubjects = (array) ($dc['subject'] ?? []);

        $itemsCount = (int) ($biblio->items_count ?? 0);
        $availableCount = (int) ($biblio->available_items_count ?? 0);
        if ($itemsCount === 0 && $biblio->exists) {
            $itemsCount = $biblio->items()->count();
        }
        if ($availableCount === 0 && $biblio->exists) {
            $availableCount = $biblio->availableItems()->count();
        }

        $metric = $biblio->relationLoaded('metric') ? $biblio->metric : null;
        $clickCount = (int) ($metric->click_count ?? 0);
        $borrowCount = (int) ($metric->borrow_count ?? 0);
        $halfLife = (int) config('search.ranking.half_life_days', 30);
        if ($halfLife <= 0) $halfLife = 30;
        $clickDecay = $this->decayMultiplier($metric?->last_clicked_at, $halfLife);
        $borrowDecay = $this->decayMultiplier($metric?->last_borrowed_at, $halfLife);
        $popularityScore = (int) round(($borrowCount * 5 * $borrowDecay) + ($clickCount * 1 * $clickDecay));

        return [
            'id' => (int) $biblio->id,
            'institution_id' => (int) $biblio->institution_id,
            'branch_ids' => array_values(array_unique(array_map('intval', $branchIds))),
            'title' => (string) $biblio->title,
            'subtitle' => (string) ($biblio->subtitle ?? ''),
            'normalized_title' => (string) ($biblio->normalized_title ?? ''),
            'responsibility_statement' => (string) ($biblio->responsibility_statement ?? ''),
            'authors' => array_values(array_unique(array_filter(array_merge($authorNames, $dcCreators)))),
            'author_ids' => array_values(array_unique(array_map('intval', $authorIds))),
            'subjects' => array_values(array_unique(array_filter(array_merge($subjectNames, $dcSubjects)))),
            'subject_ids' => array_values(array_unique(array_map('intval', $subjectIds))),
            'publisher' => (string) ($biblio->publisher ?? ''),
            'place_of_publication' => (string) ($biblio->place_of_publication ?? ''),
            'publish_year' => $biblio->publish_year ? (int) $biblio->publish_year : null,
            'language' => (string) ($biblio->language ?? ''),
            'ddc' => (string) ($biblio->ddc ?? ''),
            'ddc_base' => $this->extractDdcBase((string) ($biblio->ddc ?? '')),
            'call_number' => (string) ($biblio->call_number ?? ''),
            'isbn' => (string) ($biblio->isbn ?? ''),
            'issn' => (string) ($biblio->issn ?? ''),
            'series_title' => (string) ($biblio->series_title ?? ''),
            'material_type' => (string) ($biblio->material_type ?? ''),
            'media_type' => (string) ($biblio->media_type ?? ''),
            'audience' => (string) ($biblio->audience ?? ''),
            'is_reference' => (bool) ($biblio->is_reference ?? false),
            'notes' => (string) ($biblio->notes ?? ''),
            'general_note' => (string) ($biblio->general_note ?? ''),
            'bibliography_note' => (string) ($biblio->bibliography_note ?? ''),
            'ai_summary' => (string) ($biblio->ai_summary ?? ''),
            'identifiers' => $identifiers,
            'items_count' => $itemsCount,
            'available_items_count' => $availableCount,
            'click_count' => $clickCount,
            'borrow_count' => $borrowCount,
            'popularity_score' => $popularityScore,
            'available' => $availableCount > 0,
            'created_at' => $biblio->created_at?->timestamp,
        ];
    }

    private function extractDdcBase(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }
        if (preg_match('/^(\d{3})(?:\.(\d+))?/', $v, $m)) {
            return $m[1] . (isset($m[2]) ? '.' . $m[2] : '');
        }
        return '';
    }

    private function escapeFilter(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\"'], $value);
    }

    private function isLikelyIsbn(string $value): bool
    {
        $v = preg_replace('/[^0-9Xx]/', '', $value);
        return in_array(strlen($v), [10, 13], true);
    }

    private function isLikelyCallNumber(string $value): bool
    {
        $v = trim($value);
        if (strlen($v) < 3 || strlen($v) > 20) {
            return false;
        }
        // Pola umum: 000.000 XXX / 000 XXX / 005.133 GER
        return (bool) preg_match('/^\d{3}(\.\d{1,4})?([\/\s-]*[A-Z]{1,4})?$/i', $v);
    }

    private function expandQueryWithSynonyms(string $q, int $institutionId, ?int $branchId = null): string
    {
        $base = trim($q);
        if ($base === '') {
            return $base;
        }

        $stopWords = $this->loadStopWords($institutionId, $branchId);
        $stopMap = array_fill_keys(array_map('mb_strtolower', $stopWords), true);
        $tokens = preg_split('/\s+/', $base);
        $synonyms = $this->loadSynonyms($institutionId, $branchId);
        if (empty($synonyms)) {
            return $base;
        }

        $expanded = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '') continue;
            if (isset($stopMap[mb_strtolower($token)])) continue;
            $expanded[] = $token;
            $lower = mb_strtolower($token);
            if (isset($synonyms[$lower])) {
                foreach ($synonyms[$lower] as $syn) {
                    $expanded[] = $syn;
                }
            }
        }

        return trim(implode(' ', array_values(array_unique($expanded))));
    }

    private function loadSynonyms(int $institutionId, ?int $branchId = null): array
    {
        $syn = (array) config('search.synonyms', []);

        if (\Illuminate\Support\Facades\Schema::hasTable('search_synonyms')) {
            $q = \Illuminate\Support\Facades\DB::table('search_synonyms')
                ->where('institution_id', $institutionId)
                ->when($branchId, fn($q) => $q->where(function ($qq) use ($branchId) {
                    $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
                }), fn($q) => $q->whereNull('branch_id'));
            if (\Illuminate\Support\Facades\Schema::hasColumn('search_synonyms', 'status')) {
                $q->where('status', 'approved');
            }
            $rows = $q->get(['term', 'synonyms']);

            foreach ($rows as $row) {
                $term = trim((string) $row->term);
                $list = (array) json_decode((string) $row->synonyms, true);
                if ($term === '' || empty($list)) continue;
                $term = mb_strtolower($term);
                $syn[$term] = array_values(array_unique(array_filter(array_map('trim', $list))));
            }
        }

        // Normalize keys to lowercase
        $normalized = [];
        foreach ($syn as $key => $list) {
            $key = mb_strtolower(trim((string) $key));
            if ($key === '') continue;
            $normalized[$key] = array_values(array_unique(array_filter(array_map('trim', (array) $list))));
        }

        return $normalized;
    }

    private function resolveTypoTolerance(?int $branchId, int $minLen): array
    {
        $base = (array) config('search.typo_tolerance', []);
        $shortThreshold = (int) ($base['short_word'] ?? 4);
        $oneTypo = (int) ($base['one_typo'] ?? 3);
        $twoTypos = (int) ($base['two_typos'] ?? 5);
        $shortOne = (int) ($base['short_one_typo'] ?? 2);
        $shortTwo = (int) ($base['short_two_typos'] ?? 4);

        $overrides = $this->loadBranchTypoOverrides($branchId);
        if (!empty($overrides)) {
            if (isset($overrides['short_word'])) {
                $shortThreshold = (int) $overrides['short_word'];
            }
            if (isset($overrides['one_typo'])) {
                $oneTypo = (int) $overrides['one_typo'];
            }
            if (isset($overrides['two_typos'])) {
                $twoTypos = (int) $overrides['two_typos'];
            }
            if (isset($overrides['short_one_typo'])) {
                $shortOne = (int) $overrides['short_one_typo'];
            }
            if (isset($overrides['short_two_typos'])) {
                $shortTwo = (int) $overrides['short_two_typos'];
            }
        }

        if ($minLen <= $shortThreshold) {
            $oneTypo = $shortOne;
            $twoTypos = $shortTwo;
        }

        return [$oneTypo, $twoTypos];
    }

    private function loadBranchTypoOverrides(?int $branchId): array
    {
        if (!$branchId) {
            return [];
        }

        $branches = (array) config('search.branch_typo_tolerance.branches', []);
        $overrides = $branches[$branchId] ?? [];
        return is_array($overrides) ? $overrides : [];
    }

    private function resolveBranchSortOverride(string $sort, ?int $branchId, string $q): array
    {
        if (!$branchId) {
            return [];
        }

        $branches = (array) config('search.branch_sort_overrides.branches', []);
        $rules = $branches[$branchId] ?? [];
        if (!is_array($rules) || empty($rules)) {
            return [];
        }

        $key = $sort !== '' ? $sort : 'relevant';
        if ($q === '' && isset($rules['empty'])) {
            $key = 'empty';
        }

        $override = $rules[$key] ?? [];
        return is_array($override) ? $override : [];
    }

    private function normalizeFilterArray($value, bool $numeric = false): array
    {
        if (!is_array($value)) {
            if ($value === null || $value === '') {
                return [];
            }
            $value = [$value];
        }

        $clean = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            if ($numeric) {
                $n = (int) $item;
                if ($n <= 0) {
                    continue;
                }
                $clean[] = $n;
            } else {
                $clean[] = $item;
            }
        }

        return array_values(array_unique($clean));
    }

    private function decayMultiplier($lastAt, int $halfLifeDays): float
    {
        if (!$lastAt) {
            return 1.0;
        }

        $ts = is_string($lastAt) ? strtotime($lastAt) : (is_object($lastAt) && method_exists($lastAt, 'getTimestamp') ? $lastAt->getTimestamp() : null);
        if (!$ts) {
            return 1.0;
        }

        $ageDays = max(0, (time() - $ts) / 86400);
        if ($halfLifeDays <= 0) {
            return 1.0;
        }

        return pow(0.5, $ageDays / $halfLifeDays);
    }

    private function loadStopWords(int $institutionId, ?int $branchId = null): array
    {
        try {
            /** @var SearchStopWordService $svc */
            $svc = app(SearchStopWordService::class);
            return $svc->listForInstitution($institutionId, $branchId);
        } catch (\Throwable) {
            return array_values(array_unique(array_filter((array) config('search.stop_words', []))));
        }
    }
}
