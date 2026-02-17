<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportKatalogRequest;
use App\Http\Requests\StoreBiblioRequest;
use App\Http\Requests\UpdateBiblioRequest;
use App\Models\Author;
use App\Models\Biblio;
use App\Models\BiblioAttachment;
use App\Models\Item;
use App\Models\Branch;
use App\Models\Shelf;
use App\Models\Subject;
use App\Models\Tag;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ExportService;
use App\Services\ImportService;
use App\Services\MarcValidationService;
use App\Services\MetadataMappingService;
use App\Services\Search\BiblioSearchService;
use App\Services\Search\BiblioRankingService;
use App\Services\BiblioInteractionService;
use App\Services\BiblioAutofixService;
use App\Services\PustakawanDigital\ExternalApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

class KatalogController extends Controller
{
    private function currentInstitutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    private function currentBranchId(): ?int
    {
        $active = (int) session('active_branch_id', 0);
        if ($active > 0) {
            $institutionId = $this->currentInstitutionId();
            $isValidActive = Branch::query()
                ->where('id', $active)
                ->where('institution_id', $institutionId)
                ->where('is_active', 1)
                ->exists();
            if ($isValidActive) {
                return $active;
            }
        }
        $userBranch = (int) (auth()->user()->branch_id ?? 0);
        if ($userBranch > 0) {
            $institutionId = $this->currentInstitutionId();
            $isValidUserBranch = Branch::query()
                ->where('id', $userBranch)
                ->where('institution_id', $institutionId)
                ->where('is_active', 1)
                ->exists();
            if ($isValidUserBranch) {
                return $userBranch;
            }
        }
        return null;
    }

    private function canManageCatalog(): bool
    {
        $role = auth()->user()->role ?? 'member';
        return in_array($role, ['super_admin', 'admin', 'staff'], true);
    }

    private function canViewAttachment(?string $visibility): bool
    {
        $visibility = $visibility ?: 'staff';

        if ($visibility === 'public') {
            return true;
        }

        if (!auth()->check()) {
            return false;
        }

        if ($this->canManageCatalog()) {
            return true;
        }

        return $visibility === 'member';
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

    private function parseIdentifiersInput($identifiers): array
    {
        if (!is_array($identifiers)) {
            return [];
        }

        $clean = [];
        foreach ($identifiers as $row) {
            if (!is_array($row)) {
                continue;
            }
            $scheme = trim((string) ($row['scheme'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            $uri = trim((string) ($row['uri'] ?? ''));
            if ($scheme === '' || $value === '') {
                continue;
            }
            $clean[] = [
                'scheme' => $scheme,
                'value' => $value,
                'uri' => $uri !== '' ? $uri : null,
            ];
        }

        return $clean;
    }

    private function normalizeDcI18nInput($dcI18n): array
    {
        if (!is_array($dcI18n)) {
            return [];
        }

        $normalizeList = function ($value): array {
            if (is_array($value)) {
                return array_values(array_filter(array_map('trim', array_map('strval', $value))));
            }
            $value = trim((string) $value);
            if ($value === '') {
                return [];
            }
            $parts = preg_split('/[;,\n]+/', $value);
            return array_values(array_filter(array_map('trim', $parts)));
        };

        $clean = [];
        foreach ($dcI18n as $locale => $payload) {
            $locale = trim((string) $locale);
            if ($locale === '' || !is_array($payload)) {
                continue;
            }

            $row = $payload;
            if (array_key_exists('creator', $row)) {
                $row['creator'] = $normalizeList($row['creator']);
            }
            if (array_key_exists('subject', $row)) {
                $row['subject'] = $normalizeList($row['subject']);
            }

            $clean[$locale] = $row;
        }

        return $clean;
    }

    private function tokenizeQuery(string $query, ?int $institutionId = null): array
    {
        $tokens = preg_split('/\s+/', $this->normalizeLoose($query));
        $stopWords = array_values(array_unique(array_filter((array) config('search.stop_words', []))));
        if ($institutionId !== null) {
            try {
                /** @var \App\Services\Search\SearchStopWordService $svc */
                $svc = app(\App\Services\Search\SearchStopWordService::class);
                $stopWords = $svc->listForInstitution($institutionId, null);
            } catch (\Throwable) {
                // fallback ke config saja
            }
        }
        $stopMap = array_fill_keys(array_map(fn ($w) => mb_strtolower((string) $w), $stopWords), true);
        $tokens = array_values(array_filter((array) $tokens, function ($t) use ($stopMap) {
            $k = mb_strtolower(trim((string) $t));
            return $k !== '' && !isset($stopMap[$k]);
        }));
        $tokens = $this->expandTokens($tokens, $institutionId);
        return collect($tokens)
            ->filter(fn($t) => $t !== '' && mb_strlen($t) >= 3)
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function expandTokens(array $tokens, ?int $institutionId = null): array
    {
        $map = (array) config('search.synonyms', []);
        $typoMap = (array) config('search.typos', []);
        if ($institutionId !== null) {
            $typoMap = array_merge($typoMap, $this->getInstitutionTypoMap($institutionId));
        }
        $dictionary = $this->buildSearchDictionary($map, $institutionId);

        $expanded = [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') continue;
            $expanded[] = $token;
            if (isset($typoMap[$token])) {
                $expanded[] = $typoMap[$token];
            }
            $compressed = preg_replace('/(.)\1{2,}/', '$1$1', $token);
            if ($compressed && $compressed !== $token) {
                $expanded[] = $compressed;
            }
            if (isset($map[$token])) {
                foreach ((array) $map[$token] as $syn) {
                    $expanded[] = $syn;
                }
            } else {
                $guess = $this->fuzzyGuessToken($token, $dictionary);
                if ($guess && $guess !== $token) {
                    $expanded[] = $guess;
                    if (isset($map[$guess])) {
                        foreach ((array) $map[$guess] as $syn) {
                            $expanded[] = $syn;
                        }
                    }
                }
            }
        }

        return $expanded;
    }

    private function buildSearchDictionary(array $synonyms, ?int $institutionId = null): array
    {
        $terms = [];
        foreach ($synonyms as $key => $values) {
            $key = trim((string) $key);
            if ($key !== '') {
                $terms[] = $key;
            }
            foreach ((array) $values as $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $terms[] = $value;
                }
            }
        }

        $flat = [];
        foreach ($terms as $term) {
            foreach (preg_split('/\s+/', $term) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $flat[] = $part;
                }
            }
        }

        if ($institutionId !== null) {
            $flat = array_merge($flat, $this->getSearchDictionaryTerms($institutionId));
        }

        return array_values(array_unique($flat));
    }

    private function getInstitutionTypoMap(int $institutionId): array
    {
        $cacheKey = 'nbk:search:typo:' . $institutionId;

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($institutionId) {
            if (!Schema::hasTable('search_queries')) {
                return [];
            }

            $rows = DB::table('search_queries')
                ->where('institution_id', $institutionId)
                ->orderByDesc('search_count')
                ->orderByDesc('last_searched_at')
                ->limit(400)
                ->get(['query', 'normalized_query', 'last_hits', 'search_count']);

            $knownTokens = [];
            $missTokens = [];

            foreach ($rows as $row) {
                $normalized = $this->normalizeLoose((string) ($row->normalized_query ?: $row->query));
                if ($normalized === '') {
                    continue;
                }

                $parts = array_values(array_filter(
                    preg_split('/\s+/', $normalized),
                    fn ($p) => $p !== '' && mb_strlen($p) >= 4
                ));

                if ((int) $row->last_hits > 0 && (int) $row->search_count >= 2) {
                    foreach ($parts as $part) {
                        $knownTokens[$part] = true;
                    }
                }

                if ((int) $row->last_hits === 0 && (int) $row->search_count >= 2) {
                    foreach ($parts as $part) {
                        $missTokens[$part] = true;
                    }
                }
            }

            $known = array_keys($knownTokens);
            $miss = array_keys($missTokens);
            if (empty($known) || empty($miss)) {
                return [];
            }

            $typoMap = [];
            foreach ($miss as $token) {
                $best = null;
                $bestDist = 99;
                foreach ($known as $candidate) {
                    if ($candidate === $token) {
                        continue;
                    }
                    if (mb_substr($candidate, 0, 1) !== mb_substr($token, 0, 1)) {
                        continue;
                    }
                    $lenDiff = abs(mb_strlen($candidate) - mb_strlen($token));
                    if ($lenDiff > 2) {
                        continue;
                    }
                    $dist = levenshtein($token, $candidate);
                    if ($dist < $bestDist) {
                        $bestDist = $dist;
                        $best = $candidate;
                    }
                }

                $limit = mb_strlen($token) <= 6 ? 1 : 2;
                if ($best !== null && $bestDist <= $limit) {
                    $typoMap[$token] = $best;
                }
            }

            return $typoMap;
        });
    }

    private function getOpacPrefetchUrls(int $institutionId): array
    {
        if (!Schema::hasTable('search_queries')) {
            return [];
        }
        if (!(bool) config('notobuku.opac.prefetch.enabled', true)) {
            return [];
        }

        $limit = max(0, (int) config('notobuku.opac.prefetch.top_queries', 6));
        if ($limit === 0) {
            return [];
        }

        $rows = DB::table('search_queries')
            ->where('institution_id', $institutionId)
            ->where('last_hits', '>', 0)
            ->where('search_count', '>', 1)
            ->orderByDesc('search_count')
            ->orderByDesc('last_searched_at')
            ->limit($limit)
            ->pluck('query');

        return $rows
            ->map(fn ($q) => trim((string) $q))
            ->filter(fn ($q) => $q !== '' && mb_strlen($q) >= 3)
            ->unique()
            ->take($limit)
            ->map(fn ($q) => route('opac.index', ['q' => $q]))
            ->values()
            ->all();
    }

    private function fuzzyGuessToken(string $token, array $dictionary): ?string
    {
        $token = trim($token);
        if ($token === '' || mb_strlen($token) < 4) {
            return null;
        }

        $best = null;
        $bestDist = 99;
        foreach ($dictionary as $candidate) {
            $lenDiff = abs(mb_strlen($candidate) - mb_strlen($token));
            if ($lenDiff > 2) {
                continue;
            }
            $dist = levenshtein($token, $candidate);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $candidate;
            }
        }

        if ($best === null) {
            return null;
        }

        $limit = mb_strlen($token) <= 6 ? 1 : 2;
        return $bestDist <= $limit ? $best : null;
    }

    private function getSearchDictionaryTerms(int $institutionId): array
    {
        $cacheKey = 'nbk:search:dict:' . $institutionId;

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($institutionId) {
            $terms = [];

            if (Schema::hasTable('search_queries')) {
                $rows = DB::table('search_queries')
                    ->where('institution_id', $institutionId)
                    ->orderByDesc('search_count')
                    ->orderByDesc('last_searched_at')
                    ->limit(200)
                    ->get(['query', 'normalized_query']);

                foreach ($rows as $row) {
                    $raw = (string) ($row->query ?? '');
                    $norm = (string) ($row->normalized_query ?? '');
                    foreach (preg_split('/\s+/', trim($raw . ' ' . $norm)) as $part) {
                        $part = trim($part);
                        if ($part !== '' && mb_strlen($part) >= 3) {
                            $terms[] = $part;
                        }
                    }
                }
            }

            return array_values(array_unique($terms));
        });
    }

    private function logSearchQuery(string $query, int $institutionId, ?int $userId, int $hits): void
    {
        $normalized = $this->normalizeLoose($query);
        if ($normalized === '' || mb_strlen($normalized) < 3) {
            return;
        }

        if (!Schema::hasTable('search_queries')) {
            return;
        }

        $hasAutoSuggestionCols = Schema::hasColumn('search_queries', 'auto_suggestion_query')
            && Schema::hasColumn('search_queries', 'auto_suggestion_score')
            && Schema::hasColumn('search_queries', 'auto_suggestion_status');
        $hasZeroWorkflowCols = Schema::hasColumn('search_queries', 'zero_result_status')
            && Schema::hasColumn('search_queries', 'zero_resolved_at')
            && Schema::hasColumn('search_queries', 'zero_resolved_by')
            && Schema::hasColumn('search_queries', 'zero_resolution_note')
            && Schema::hasColumn('search_queries', 'zero_resolution_link');

        $now = now();
        $autoSuggestion = null;
        $autoScore = null;
        if ($hits <= 0) {
            $suggest = $this->suggestQueryWithScore($query, $institutionId);
            if ($suggest !== null) {
                $autoSuggestion = (string) ($suggest['text'] ?? '');
                $autoScore = (float) ($suggest['score'] ?? 0);
            }
        }

        $updatePayload = [
            'query' => $query,
            'user_id' => $userId,
            'last_hits' => max(0, $hits),
            'last_searched_at' => $now,
            'search_count' => DB::raw('search_count + 1'),
            'updated_at' => $now,
        ];
        if ($hasZeroWorkflowCols) {
            $updatePayload = array_merge($updatePayload, [
                'zero_result_status' => $hits > 0 ? 'resolved_auto' : 'open',
                'zero_resolved_at' => $hits > 0 ? $now : null,
                'zero_resolved_by' => $hits > 0 ? $userId : null,
                'zero_resolution_note' => $hits > 0 ? 'Resolved otomatis: query sekarang punya hasil.' : null,
                'zero_resolution_link' => null,
            ]);
        }
        if ($hasAutoSuggestionCols) {
            $updatePayload = array_merge($updatePayload, [
                'auto_suggestion_query' => $hits > 0 ? null : $autoSuggestion,
                'auto_suggestion_score' => $hits > 0 ? null : $autoScore,
                'auto_suggestion_status' => $hits > 0 ? 'resolved_auto' : ($autoSuggestion ? 'open' : 'none'),
            ]);
        }

        $affected = DB::table('search_queries')
            ->where('institution_id', $institutionId)
            ->where('normalized_query', $normalized)
            ->update($updatePayload);

        $searchQueryId = null;
        if ($affected > 0) {
            $searchQueryId = (int) DB::table('search_queries')
                ->where('institution_id', $institutionId)
                ->where('normalized_query', $normalized)
                ->value('id');
        }

        if ($affected === 0) {
            $insertPayload = [
                'institution_id' => $institutionId,
                'normalized_query' => $normalized,
                'query' => $query,
                'user_id' => $userId,
                'last_hits' => max(0, $hits),
                'last_searched_at' => $now,
                'search_count' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($hasZeroWorkflowCols) {
                $insertPayload = array_merge($insertPayload, [
                    'zero_result_status' => $hits > 0 ? 'resolved_auto' : 'open',
                    'zero_resolved_at' => $hits > 0 ? $now : null,
                    'zero_resolved_by' => $hits > 0 ? $userId : null,
                    'zero_resolution_note' => null,
                    'zero_resolution_link' => null,
                ]);
            }
            if ($hasAutoSuggestionCols) {
                $insertPayload = array_merge($insertPayload, [
                    'auto_suggestion_query' => $hits > 0 ? null : $autoSuggestion,
                    'auto_suggestion_score' => $hits > 0 ? null : $autoScore,
                    'auto_suggestion_status' => $hits > 0 ? 'resolved_auto' : ($autoSuggestion ? 'open' : 'none'),
                ]);
            }

            $searchQueryId = (int) DB::table('search_queries')->insertGetId($insertPayload);
        }

        if (Schema::hasTable('search_query_events')) {
            DB::table('search_query_events')->insert([
                'institution_id' => $institutionId,
                'user_id' => $userId,
                'search_query_id' => $searchQueryId ?: null,
                'query' => $query,
                'normalized_query' => $normalized,
                'hits' => max(0, $hits),
                'is_zero_result' => $hits <= 0 ? 1 : 0,
                'suggestion' => $autoSuggestion,
                'suggestion_score' => $autoScore,
                'searched_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function suggestQuery(string $query, int $institutionId): ?string
    {
        $best = $this->suggestQueryWithScore($query, $institutionId);
        return $best['text'] ?? null;
    }

    private function suggestQueryWithScore(string $query, int $institutionId): ?array
    {
        $normalized = $this->normalizeLoose($query);
        if ($normalized === '') return null;

        $candidates = collect()
            ->merge(
                Biblio::query()
                    ->where('institution_id', $institutionId)
                    ->select('title')
                    ->limit(200)
                    ->pluck('title')
            )
            ->merge(
                Author::query()
                    ->select('name')
                    ->limit(200)
                    ->pluck('name')
            )
            ->merge(
                Subject::query()
                    ->select(DB::raw('COALESCE(term, name) as term'))
                    ->limit(200)
                    ->pluck('term')
            )
            ->merge(
                Schema::hasTable('search_queries')
                    ? DB::table('search_queries')
                        ->where('institution_id', $institutionId)
                        ->where('last_hits', '>', 0)
                        ->orderByDesc('search_count')
                        ->orderByDesc('last_searched_at')
                        ->limit(200)
                        ->pluck('query')
                    : collect()
            )
            ->filter()
            ->unique()
            ->values();

        $best = null;
        $bestScore = 0;

        foreach ($candidates as $candidate) {
            $candidateNorm = $this->normalizeLoose($candidate);
            if ($candidateNorm === '' || $candidateNorm === $normalized) continue;
            similar_text($normalized, $candidateNorm, $percent);
            if ($percent > $bestScore) {
                $bestScore = $percent;
                $best = $candidate;
            }
        }

        if ($bestScore >= 55 && $best) {
            return [
                'text' => (string) $best,
                'score' => (float) round($bestScore, 2),
            ];
        }

        return null;
    }

    private function applyCatalogFilters($query, array $filters)
    {
        $q = $filters['q'] ?? '';
        $title = $filters['title'] ?? '';
        $authorName = $filters['author_name'] ?? '';
        $subjectTerm = $filters['subject_term'] ?? '';
        $isbn = $filters['isbn'] ?? '';
        $callNumber = $filters['call_number'] ?? '';
        $language = $filters['language'] ?? '';
        $materialType = $filters['material_type'] ?? '';
        $mediaType = $filters['media_type'] ?? '';
        $languageList = $this->normalizeQueryArray($filters['language_list'] ?? []);
        $materialTypeList = $this->normalizeQueryArray($filters['material_type_list'] ?? []);
        $mediaTypeList = $this->normalizeQueryArray($filters['media_type_list'] ?? []);
        $ddc = $filters['ddc'] ?? '';
        $year = $filters['year'] ?? '';
        $yearFrom = (int) ($filters['year_from'] ?? 0);
        $yearTo = (int) ($filters['year_to'] ?? 0);
        $onlyAvailable = $filters['onlyAvailable'] ?? false;
        $author = $filters['author'] ?? '';
        $subject = $filters['subject'] ?? '';
        $publisher = $filters['publisher'] ?? '';
        $authorList = $this->normalizeQueryArray($filters['author_list'] ?? [], true);
        $subjectList = $this->normalizeQueryArray($filters['subject_list'] ?? [], true);
        $publisherList = $this->normalizeQueryArray($filters['publisher_list'] ?? []);
        $branchList = $this->normalizeQueryArray($filters['branch_list'] ?? [], true);

        if ($title !== '') {
            $query->where(function ($qq) use ($title) {
                $qq->where('title', 'like', "%{$title}%")
                    ->orWhere('subtitle', 'like', "%{$title}%")
                    ->orWhere('normalized_title', 'like', "%{$this->normalizeLoose($title)}%");
            });
        }

        if ($isbn !== '') {
            $query->where('isbn', 'like', "%{$isbn}%");
        }

        if ($callNumber !== '') {
            $query->where('call_number', 'like', "%{$callNumber}%");
        }

        if (!empty($languageList)) {
            $query->whereIn('language', $languageList);
        } elseif ($language !== '') {
            $query->where('language', $language);
        }

        if (!empty($materialTypeList)) {
            $query->whereIn('material_type', $materialTypeList);
        } elseif ($materialType !== '') {
            $query->where('material_type', $materialType);
        }

        if (!empty($mediaTypeList)) {
            $query->whereIn('media_type', $mediaTypeList);
        } elseif ($mediaType !== '') {
            $query->where('media_type', $mediaType);
        }

        if ($authorName !== '') {
            $query->whereHas('authors', function ($a) use ($authorName) {
                $a->where('name', 'like', "%{$authorName}%");
            });
        }

        if ($subjectTerm !== '') {
            $query->whereHas('subjects', function ($s) use ($subjectTerm) {
                $s->where(function ($ss) use ($subjectTerm) {
                    $ss->where('term', 'like', "%{$subjectTerm}%")
                       ->orWhere('name', 'like', "%{$subjectTerm}%");
                });
            });
        }

        if ($q !== '') {
            $normalizedQ = $this->normalizeLoose($q);
            $tokens = $this->tokenizeQuery($q, $this->currentInstitutionId());

            $query->where(function ($qq) use ($q, $normalizedQ, $tokens) {
                $qq->where('title', 'like', "%{$q}%")
                    ->orWhere('subtitle', 'like', "%{$q}%")
                    ->orWhere('isbn', 'like', "%{$q}%")
                    ->orWhere('publisher', 'like', "%{$q}%")
                    ->orWhere('ddc', 'like', "%{$q}%")
                    ->orWhere('call_number', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%")
                    ->orWhere('normalized_title', 'like', "%{$normalizedQ}%")
                    ->orWhereHas('authors', function ($a) use ($q) {
                        $a->where('name', 'like', "%{$q}%");
                    })
                    ->orWhereHas('subjects', function ($s) use ($q) {
                        $s->where(function ($ss) use ($q) {
                            $ss->where('term', 'like', "%{$q}%")
                               ->orWhere('name', 'like', "%{$q}%");
                        });
                    });

                foreach ($tokens as $token) {
                    $qq->orWhere('title', 'like', "%{$token}%")
                        ->orWhere('subtitle', 'like', "%{$token}%")
                        ->orWhere('publisher', 'like', "%{$token}%")
                        ->orWhere('normalized_title', 'like', "%{$token}%")
                        ->orWhereHas('authors', function ($a) use ($token) {
                            $a->where('name', 'like', "%{$token}%");
                        })
                        ->orWhereHas('subjects', function ($s) use ($token) {
                            $s->where(function ($ss) use ($token) {
                                $ss->where('term', 'like', "%{$token}%")
                                   ->orWhere('name', 'like', "%{$token}%");
                            });
                        });
                }
            });
        }

        if ($ddc !== '') {
            $query->where('ddc', 'like', "%{$ddc}%");
        }

        if ($yearFrom > 0 && $yearTo > 0) {
            $from = min($yearFrom, $yearTo);
            $to = max($yearFrom, $yearTo);
            $query->whereBetween('publish_year', [$from, $to]);
        } elseif ($yearFrom > 0) {
            $query->where('publish_year', '>=', $yearFrom);
        } elseif ($yearTo > 0) {
            $query->where('publish_year', '<=', $yearTo);
        } elseif ($year !== '') {
            $query->where('publish_year', (int) $year);
        }

        if (!empty($publisherList)) {
            $query->whereIn('publisher', $publisherList);
        } elseif ($publisher !== '') {
            $query->where('publisher', 'like', "%{$publisher}%");
        }

        if (!empty($authorList)) {
            $query->whereHas('authors', function ($a) use ($authorList) {
                $a->whereIn('authors.id', $authorList);
            });
        } elseif ($author !== '') {
            $query->whereHas('authors', function ($a) use ($author) {
                $a->where('authors.id', $author);
            });
        }

        if (!empty($subjectList)) {
            $query->whereHas('subjects', function ($s) use ($subjectList) {
                $s->whereIn('subjects.id', $subjectList);
            });
        } elseif ($subject !== '') {
            $query->whereHas('subjects', function ($s) use ($subject) {
                $s->where('subjects.id', $subject);
            });
        }

        if ($onlyAvailable) {
            $query->whereHas('items', function ($itemQuery) {
                $itemQuery->where('status', 'available');
            });
        }

        if (!empty($branchList)) {
            $query->whereHas('items', function ($itemQuery) use ($branchList) {
                $itemQuery->whereIn('branch_id', $branchList);
            });
        }

        return $query;
    }

    private function normalizeQueryArray($value, bool $numeric = false): array
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
                $int = (int) $item;
                if ($int <= 0) {
                    continue;
                }
                $clean[] = $int;
            } else {
                $clean[] = $item;
            }
        }

        return array_values(array_unique($clean));
    }

    private function normalizedFacetCacheSignature(
        int $institutionId,
        array $filters,
        array $fieldFilters,
        string $qfOp,
        bool $qfExact,
        ?int $activeBranchId
    ): array {
        $normalizeList = function ($value, bool $numeric = false): array {
            $list = $this->normalizeQueryArray($value, $numeric);
            sort($list);
            return $list;
        };

        $fieldFiltersNorm = collect($fieldFilters)
            ->map(function (array $row) {
                return [
                    'field' => trim((string) ($row['field'] ?? '')),
                    'value' => trim((string) ($row['value'] ?? '')),
                ];
            })
            ->filter(fn ($row) => $row['field'] !== '' && $row['value'] !== '')
            ->values()
            ->all();

        return [
            'institution_id' => $institutionId,
            'active_branch_id' => (int) ($activeBranchId ?? 0),
            'q' => trim((string) ($filters['q'] ?? '')),
            'title' => trim((string) ($filters['title'] ?? '')),
            'author_name' => trim((string) ($filters['author_name'] ?? '')),
            'subject_term' => trim((string) ($filters['subject_term'] ?? '')),
            'isbn' => trim((string) ($filters['isbn'] ?? '')),
            'call_number' => trim((string) ($filters['call_number'] ?? '')),
            'ddc' => trim((string) ($filters['ddc'] ?? '')),
            'year' => trim((string) ($filters['year'] ?? '')),
            'year_from' => (int) ($filters['year_from'] ?? 0),
            'year_to' => (int) ($filters['year_to'] ?? 0),
            'available' => (bool) ($filters['onlyAvailable'] ?? false),
            'author_list' => $normalizeList($filters['author_list'] ?? [], true),
            'subject_list' => $normalizeList($filters['subject_list'] ?? [], true),
            'publisher_list' => $normalizeList($filters['publisher_list'] ?? []),
            'language_list' => $normalizeList($filters['language_list'] ?? []),
            'material_type_list' => $normalizeList($filters['material_type_list'] ?? []),
            'media_type_list' => $normalizeList($filters['media_type_list'] ?? []),
            'branch_list' => $normalizeList($filters['branch_list'] ?? [], true),
            'qf_op' => strtoupper(trim($qfOp)),
            'qf_exact' => (bool) $qfExact,
            'field_filters' => $fieldFiltersNorm,
        ];
    }

    private function applyFieldedFilters($query, array $fieldFilters, string $operator, bool $exact)
    {
        if (empty($fieldFilters)) {
            return $query;
        }

        $operator = strtoupper($operator) === 'OR' ? 'OR' : 'AND';

        $query->where(function ($qq) use ($fieldFilters, $operator, $exact) {
            foreach ($fieldFilters as $i => $filter) {
                $field = $filter['field'] ?? '';
                $value = trim((string) ($filter['value'] ?? ''));
                if ($field === '' || $value === '') {
                    continue;
                }

                $method = ($operator === 'OR' && $i > 0) ? 'orWhere' : 'where';

                if ($field === 'author') {
                    $qq->{$method}(function ($sub) use ($value, $exact) {
                        $sub->whereHas('authors', function ($a) use ($value, $exact) {
                            if ($exact) {
                                $a->where('name', $value);
                            } else {
                                $a->where('name', 'like', "%{$value}%");
                            }
                        });
                    });
                    continue;
                }

                if ($field === 'subject') {
                    $qq->{$method}(function ($sub) use ($value, $exact) {
                        $sub->whereHas('subjects', function ($s) use ($value, $exact) {
                            $s->where(function ($ss) use ($value, $exact) {
                                if ($exact) {
                                    $ss->where('term', $value)->orWhere('name', $value);
                                } else {
                                    $ss->where('term', 'like', "%{$value}%")
                                       ->orWhere('name', 'like', "%{$value}%");
                                }
                            });
                        });
                    });
                    continue;
                }

                if ($field === 'publisher') {
                    $qq->{$method}('publisher', $exact ? '=' : 'like', $exact ? $value : "%{$value}%");
                    continue;
                }

                if ($field === 'isbn') {
                    $qq->{$method}('isbn', $exact ? '=' : 'like', $exact ? $value : "%{$value}%");
                    continue;
                }

                if ($field === 'call_number') {
                    $qq->{$method}('call_number', $exact ? '=' : 'like', $exact ? $value : "%{$value}%");
                    continue;
                }

                if ($field === 'ddc') {
                    $qq->{$method}('ddc', $exact ? '=' : 'like', $exact ? $value : "%{$value}%");
                    continue;
                }

                if ($field === 'title') {
                    $qq->{$method}(function ($sub) use ($value, $exact) {
                        if ($exact) {
                            $sub->where('title', $value)
                                ->orWhere('subtitle', $value)
                                ->orWhere('normalized_title', $this->normalizeLoose($value));
                        } else {
                            $sub->where('title', 'like', "%{$value}%")
                                ->orWhere('subtitle', 'like', "%{$value}%")
                                ->orWhere('normalized_title', 'like', "%{$this->normalizeLoose($value)}%");
                        }
                    });
                    continue;
                }

                if ($field === 'notes') {
                    $qq->{$method}('notes', $exact ? '=' : 'like', $exact ? $value : "%{$value}%");
                    continue;
                }
            }
        });

        return $query;
    }

    private function buildRelevanceScore(string $q, string $normalizedQ): array
    {
        $tokens = $this->tokenizeQuery($q, $this->currentInstitutionId());
        $scoreSql = "(
            CASE
                WHEN isbn = ? THEN 220
                WHEN call_number = ? THEN 200
                WHEN title = ? THEN 170
                WHEN normalized_title = ? THEN 165
                WHEN title LIKE ? THEN 135
                WHEN normalized_title LIKE ? THEN 130
                WHEN title LIKE ? THEN 110
                WHEN normalized_title LIKE ? THEN 105
                WHEN subtitle LIKE ? THEN 80
                WHEN EXISTS (
                    SELECT 1 FROM biblio_subject bs
                    JOIN subjects s ON s.id = bs.subject_id
                    WHERE bs.biblio_id = biblio.id
                    AND (s.term = ? OR s.name = ?)
                ) THEN 115
                WHEN EXISTS (
                    SELECT 1 FROM biblio_subject bs
                    JOIN subjects s ON s.id = bs.subject_id
                    WHERE bs.biblio_id = biblio.id
                    AND (s.term LIKE ? OR s.name LIKE ?)
                ) THEN 90
                WHEN EXISTS (
                    SELECT 1 FROM biblio_author ba
                    JOIN authors a ON a.id = ba.author_id
                    WHERE ba.biblio_id = biblio.id
                    AND a.name = ?
                ) THEN 90
                WHEN EXISTS (
                    SELECT 1 FROM biblio_author ba
                    JOIN authors a ON a.id = ba.author_id
                    WHERE ba.biblio_id = biblio.id
                    AND a.name LIKE ?
                ) THEN 70
                WHEN publisher LIKE ? THEN 50
                WHEN isbn LIKE ? THEN 120
                WHEN call_number LIKE ? THEN 100
                WHEN ddc LIKE ? THEN 60
                ELSE 0
            END
        )";

        $bindings = [
            $q,                // isbn =
            $q,                // call_number =
            $q,
            $normalizedQ,
            "{$q}%",
            "{$normalizedQ}%",
            "%{$q}%",
            "%{$normalizedQ}%",
            "%{$q}%",
            $q,
            $q,
            "%{$q}%",
            "%{$q}%",
            $q,
            "%{$q}%",
            "%{$q}%",
            "%{$q}%",
            "%{$q}%",
            "%{$q}%",
        ];

        foreach ($tokens as $token) {
            $scoreSql .= " + (CASE
                WHEN EXISTS (
                    SELECT 1 FROM biblio_subject bs
                    JOIN subjects s ON s.id = bs.subject_id
                    WHERE bs.biblio_id = biblio.id
                    AND (s.term LIKE ? OR s.name LIKE ?)
                ) THEN 14
                WHEN EXISTS (
                    SELECT 1 FROM biblio_author ba
                    JOIN authors a ON a.id = ba.author_id
                    WHERE ba.biblio_id = biblio.id
                    AND a.name LIKE ?
                ) THEN 11
                WHEN title LIKE ? THEN 20
                WHEN title LIKE ? THEN 14
                WHEN subtitle LIKE ? THEN 12
                WHEN normalized_title LIKE ? THEN 18
                WHEN normalized_title LIKE ? THEN 12
                WHEN publisher LIKE ? THEN 6
                ELSE 0
            END)";
            $bindings[] = "%{$token}%"; // subject term/name
            $bindings[] = "%{$token}%"; // subject term/name
            $bindings[] = "%{$token}%"; // author
            $bindings[] = "%{$token}%"; // title contains
            $bindings[] = "{$token}%";  // title starts
            $bindings[] = "%{$token}%"; // subtitle
            $bindings[] = "%{$token}%"; // normalized title contains
            $bindings[] = "{$token}%";  // normalized title starts
            $bindings[] = "%{$token}%"; // publisher
        }

        return [$scoreSql, $bindings];
    }

    private function buildIndexPayload(Request $request, bool $isPublic = false)
    {
        $q = trim((string) $request->query('q', ''));
        $title = trim((string) $request->query('title', ''));
        $authorName = trim((string) $request->query('author_name', ''));
        $subjectTerm = trim((string) $request->query('subject_term', ''));
        $isbn = trim((string) $request->query('isbn', ''));
        $callNumber = trim((string) $request->query('call_number', ''));
        $languageRaw = $request->query('language', '');
        $materialTypeRaw = $request->query('material_type', '');
        $mediaTypeRaw = $request->query('media_type', '');
        $language = is_array($languageRaw) ? '' : trim((string) $languageRaw);
        $materialType = is_array($materialTypeRaw) ? '' : trim((string) $materialTypeRaw);
        $mediaType = is_array($mediaTypeRaw) ? '' : trim((string) $mediaTypeRaw);
        $languageList = $this->normalizeQueryArray($request->query('language', []));
        $materialTypeList = $this->normalizeQueryArray($request->query('material_type', []));
        $mediaTypeList = $this->normalizeQueryArray($request->query('media_type', []));
        if (empty($languageList) && $language !== '') {
            $languageList = [$language];
        }
        if (empty($materialTypeList) && $materialType !== '') {
            $materialTypeList = [$materialType];
        }
        if (empty($mediaTypeList) && $mediaType !== '') {
            $mediaTypeList = [$mediaType];
        }
        $ddc = trim((string) $request->query('ddc', ''));
        $year = trim((string) $request->query('year', ''));
        $yearFrom = max(0, (int) $request->query('year_from', 0));
        $yearTo = max(0, (int) $request->query('year_to', 0));
        $onlyAvailable = (string) $request->query('available', '') === '1';
        $authorRaw = $request->query('author', '');
        $subjectRaw = $request->query('subject', '');
        $publisherRaw = $request->query('publisher', '');
        $author = is_array($authorRaw) ? '' : trim((string) $authorRaw);
        $subject = is_array($subjectRaw) ? '' : trim((string) $subjectRaw);
        $publisher = is_array($publisherRaw) ? '' : trim((string) $publisherRaw);
        $authorList = $this->normalizeQueryArray($request->query('author', []), true);
        $subjectList = $this->normalizeQueryArray($request->query('subject', []), true);
        $publisherList = $this->normalizeQueryArray($request->query('publisher', []));
        if (empty($authorList) && $author !== '') {
            $authorList = $this->normalizeQueryArray([$author], true);
        }
        if (empty($subjectList) && $subject !== '') {
            $subjectList = $this->normalizeQueryArray([$subject], true);
        }
        if (empty($publisherList) && $publisher !== '') {
            $publisherList = [$publisher];
        }
        $branchRaw = $request->query('branch', '');
        $branch = is_array($branchRaw) ? '' : trim((string) $branchRaw);
        $branchList = $this->normalizeQueryArray($request->query('branch', []), true);
        if (empty($branchList) && $branch !== '') {
            $branchList = $this->normalizeQueryArray([$branch], true);
        }
        $sort = trim((string) $request->query('sort', 'relevant'));
        $aiSearch = !$isPublic && (string) $request->query('ai', '') === '1'; // NEW: Flag AI search
        $forceShelves = false;
        $qfFields = (array) $request->query('qf_field', []);
        $qfValues = (array) $request->query('qf_value', []);
        $qfOp = strtoupper(trim((string) $request->query('qf_op', 'AND')));
        $qfExact = (string) $request->query('qf_exact', '') === '1';
        $rankMode = trim((string) $request->query('rank', 'institution'));
        $rankMode = ($rankMode === 'personal' && auth()->check() && !$isPublic) ? 'personal' : 'institution';

        if ($isPublic) {
            $sessionKey = 'opac_shelves';
            $shelvesParam = $request->query->has('shelves') ? (string) $request->query('shelves') : null;
            if ($shelvesParam !== null) {
                $forceShelves = $shelvesParam === '1';
                session([$sessionKey => $forceShelves]);
            } elseif (session()->has($sessionKey)) {
                $forceShelves = (bool) session($sessionKey);
            }
        } else {
            $forceShelves = (string) $request->query('shelves', '') === '1';
        }

        $institutionId = $this->currentInstitutionId();
        $activeBranchId = $this->currentBranchId();

        if ($q === '') {
            $parts = array_filter([$title, $authorName, $subjectTerm, $isbn, $callNumber, $publisher]);
            if (!empty($parts)) {
                $q = implode(' ', $parts);
            }
        }

        $filters = [
            'q' => $q,
            'title' => $title,
            'author_name' => $authorName,
            'subject_term' => $subjectTerm,
            'isbn' => $isbn,
            'call_number' => $callNumber,
            'language' => $language,
            'material_type' => $materialType,
            'media_type' => $mediaType,
            'language_list' => $languageList,
            'material_type_list' => $materialTypeList,
            'media_type_list' => $mediaTypeList,
            'ddc' => $ddc,
            'year' => $year,
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'onlyAvailable' => $onlyAvailable,
            'author' => $author,
            'subject' => $subject,
            'publisher' => $publisher,
            'author_list' => $authorList,
            'subject_list' => $subjectList,
            'publisher_list' => $publisherList,
            'branch_list' => $branchList,
            'qf_field' => $qfFields,
            'qf_value' => $qfValues,
            'qf_op' => $qfOp,
            'qf_exact' => $qfExact,
            'rank' => $rankMode,
        ];

        $fieldFilters = [];
        foreach ($qfFields as $i => $field) {
            $field = trim((string) $field);
            $value = isset($qfValues[$i]) ? trim((string) $qfValues[$i]) : '';
            if ($field === '' || $value === '') {
                continue;
            }
            $fieldFilters[] = ['field' => $field, 'value' => $value];
        }

        $page = (int) $request->query('page', 1);
        $searchService = app(BiblioSearchService::class);
        $searchResult = null;
        $languageFacets = collect();
        $materialTypeFacets = collect();
        $mediaTypeFacets = collect();
        $yearFacets = collect();
        $branchFacets = collect();
        $availabilityFacets = ['available' => 0, 'unavailable' => 0];
        $shouldUseMeili = empty($fieldFilters);
        if ($q === '') {
            $shouldUseMeili = false; // Browse default wajib stabil walau index search belum sinkron.
        }
        if ($sort === 'latest' && $activeBranchId && Schema::hasColumn('items', 'branch_id')) {
            $shouldUseMeili = false; // per cabang: pakai DB agar urut per item cabang
        }

        if ($shouldUseMeili) {
            $searchPayload = [
                'q' => $q,
                'title' => $title,
                'author_name' => $authorName,
                'subject_term' => $subjectTerm,
                'isbn' => $isbn,
                'call_number' => $callNumber,
                'language' => $language,
                'material_type' => $materialType,
                'media_type' => $mediaType,
                'language_list' => $languageList,
                'material_type_list' => $materialTypeList,
                'media_type_list' => $mediaTypeList,
                'ddc' => $ddc,
                'year' => $year,
                'year_from' => $yearFrom,
                'year_to' => $yearTo,
                'onlyAvailable' => $onlyAvailable,
                'author' => $author,
                'subject' => $subject,
                'publisher' => $publisher,
                'author_list' => $authorList,
                'subject_list' => $subjectList,
                'publisher_list' => $publisherList,
                'sort' => $sort,
                'page' => $page,
                'branch_id' => empty($branchList) ? $activeBranchId : null,
                'branch_list' => $branchList,
            ];

            if ($isPublic) {
                $cacheKey = 'nbk:search:ms:' . $institutionId . ':' . md5(json_encode($searchPayload));
                $searchResult = Cache::remember($cacheKey, now()->addSeconds(45), function () use ($searchService, $searchPayload, $institutionId) {
                    return $searchService->search($searchPayload, $institutionId);
                });
            } else {
                $searchResult = $searchService->search($searchPayload, $institutionId);
            }
        }

        $isUnfilteredBrowse =
            $q === ''
            && $title === ''
            && $authorName === ''
            && $subjectTerm === ''
            && $isbn === ''
            && $callNumber === ''
            && $language === ''
            && $materialType === ''
            && $mediaType === ''
            && $ddc === ''
            && $year === ''
            && empty($authorList)
            && empty($subjectList)
            && empty($publisherList)
            && $yearFrom <= 0
            && $yearTo <= 0
            && empty($languageList)
            && empty($materialTypeList)
            && empty($mediaTypeList)
            && empty($branchList)
            && !$onlyAvailable
            && empty($fieldFilters);

        // Safety net: jika index search kosong/out-of-sync, browse katalog default tetap tampil via DB.
        if ($searchResult && $isUnfilteredBrowse && (int) ($searchResult['total'] ?? 0) === 0) {
            $searchResult = null;
        }

        if ($searchResult) {
            $ranking = app(BiblioRankingService::class);
            $searchResult['ids'] = $ranking->rerankIds(
                $searchResult['ids'] ?? [],
                $institutionId,
                auth()->id(),
                $rankMode,
                $q,
                $activeBranchId,
                $branchList
            );

            $biblios = $searchService->hydrateResults(
                $searchResult['ids'],
                $searchResult['total'],
                $searchResult['page'],
                $searchResult['per_page']
            );
            $biblios->appends($request->query());

            $authorFacets = $searchService->mapAuthorFacets($searchResult['facets'] ?? []);
            $subjectFacets = $searchService->mapSubjectFacets($searchResult['facets'] ?? []);
            $publisherFacets = $searchService->mapPublisherFacets($searchResult['facets'] ?? []);
            $rawFacets = (array) ($searchResult['facets'] ?? []);
            $languageFacets = collect((array) ($rawFacets['language'] ?? []))
                ->map(fn ($total, $label) => (object) ['label' => (string) $label, 'total' => (int) $total])
                ->sortByDesc('total')
                ->values();
            $materialTypeFacets = collect((array) ($rawFacets['material_type'] ?? []))
                ->map(fn ($total, $label) => (object) ['label' => (string) $label, 'total' => (int) $total])
                ->sortByDesc('total')
                ->values();
            $mediaTypeFacets = collect((array) ($rawFacets['media_type'] ?? []))
                ->map(fn ($total, $label) => (object) ['label' => (string) $label, 'total' => (int) $total])
                ->sortByDesc('total')
                ->values();
            $yearFacets = collect((array) ($rawFacets['publish_year'] ?? []))
                ->map(fn ($total, $label) => (object) ['label' => (string) $label, 'total' => (int) $total])
                ->sortByDesc('total')
                ->values();
            $availableMap = (array) ($rawFacets['available'] ?? []);
            $availabilityFacets = [
                'available' => (int) ($availableMap['true'] ?? $availableMap[true] ?? 0),
                'unavailable' => (int) ($availableMap['false'] ?? $availableMap[false] ?? 0),
            ];
            $branchRows = (array) ($rawFacets['branch_ids'] ?? []);
            if (!empty($branchRows)) {
                $branchNames = Branch::query()->whereIn('id', array_map('intval', array_keys($branchRows)))->pluck('name', 'id');
                $branchFacets = collect($branchRows)
                    ->map(function ($total, $branchId) use ($branchNames) {
                        $id = (int) $branchId;
                        return (object) [
                            'id' => $id,
                            'name' => (string) ($branchNames[$id] ?? ('Cabang #' . $id)),
                            'total' => (int) $total,
                        ];
                    })
                    ->sortByDesc('total')
                    ->values();
            }
        } else {
            $baseQuery = Biblio::query()
                ->where('biblio.institution_id', $institutionId);

            $this->applyCatalogFilters($baseQuery, $filters);
            $this->applyFieldedFilters($baseQuery, $fieldFilters, $qfOp, $qfExact);

            $bibliosQuery = (clone $baseQuery)
                ->with(['authors:id,name', 'items.branch:id,name', 'items.shelf:id,name'])
                ->withCount([
                    'items',
                    'availableItems as available_items_count'
                ]);

            if ($q !== '' && $sort === 'relevant') {
                $normalizedQ = $this->normalizeLoose($q);
                [$scoreSql, $scoreBindings] = $this->buildRelevanceScore($q, $normalizedQ);
                $bibliosQuery
                    ->addSelect(DB::raw("{$scoreSql} as relevance_score"))
                    ->addBinding($scoreBindings, 'select')
                    ->orderByDesc('relevance_score')
                    ->orderByDesc('available_items_count');

                if (Schema::hasTable('biblio_metrics')) {
                    $bibliosQuery->leftJoin('biblio_metrics as bm', function ($join) use ($institutionId) {
                        $join->on('bm.biblio_id', '=', 'biblio.id')
                            ->where('bm.institution_id', '=', $institutionId);
                    })->addSelect(DB::raw('COALESCE(bm.borrow_count, 0) as borrow_count'))
                      ->addSelect(DB::raw('COALESCE(bm.click_count, 0) as click_count'))
                      ->addSelect(DB::raw('(COALESCE(bm.borrow_count, 0) * 5 + COALESCE(bm.click_count, 0)) as popularity_score'))
                      ->orderByDesc('popularity_score');
                }
            } elseif ($sort === 'latest') {
                if ($activeBranchId && Schema::hasColumn('items', 'branch_id')) {
                    $latestItems = DB::table('items')
                        ->select('biblio_id', DB::raw('MAX(created_at) as latest_item_at'))
                        ->where('branch_id', $activeBranchId)
                        ->groupBy('biblio_id');

                    $bibliosQuery
                        ->joinSub($latestItems, 'li', function ($join) {
                            $join->on('li.biblio_id', '=', 'biblio.id');
                        })
                        ->addSelect(DB::raw('li.latest_item_at'))
                        ->orderByDesc('li.latest_item_at')
                        ->orderByDesc('publish_year');
                } else {
                    $bibliosQuery->orderByDesc('publish_year');
                }
            } elseif ($sort === 'popular') {
                if (Schema::hasTable('biblio_metrics')) {
                    $bibliosQuery->leftJoin('biblio_metrics as bm', function ($join) use ($institutionId) {
                        $join->on('bm.biblio_id', '=', 'biblio.id')
                            ->where('bm.institution_id', '=', $institutionId);
                    })
                    ->addSelect('biblio.*')
                    ->addSelect(DB::raw('COALESCE(bm.borrow_count, 0) as borrow_count'))
                    ->addSelect(DB::raw('COALESCE(bm.click_count, 0) as click_count'))
                    ->addSelect(DB::raw('(COALESCE(bm.borrow_count, 0) * 5 + COALESCE(bm.click_count, 0)) as popularity_score'));
                    $bibliosQuery->orderByDesc('popularity_score');
                } else {
                    $bibliosQuery->orderByDesc('items_count');
                }
            } elseif ($sort === 'available') {
                $bibliosQuery->orderByDesc('available_items_count');
            }

            $cacheKey = 'nbk:search:' . $institutionId . ':' . md5(json_encode([
                'q' => $q,
                'ddc' => $ddc,
                'year' => $year,
                'year_from' => $yearFrom,
                'year_to' => $yearTo,
                'language_list' => $languageList,
                'material_type_list' => $materialTypeList,
                'media_type_list' => $mediaTypeList,
                'onlyAvailable' => $onlyAvailable,
                'author_list' => $authorList,
                'subject_list' => $subjectList,
                'publisher_list' => $publisherList,
                'branch_list' => $branchList,
                'sort' => $sort,
                'page' => $page,
                'qf_field' => $qfFields,
                'qf_value' => $qfValues,
                'qf_op' => $qfOp,
                'qf_exact' => $qfExact,
                'rank' => $rankMode,
            ]));

            $useCache = $isPublic;
            if ($useCache) {
                $biblios = Cache::remember($cacheKey, now()->addMinutes(2), function () use ($bibliosQuery) {
                return $bibliosQuery
                    ->orderByDesc('available_items_count')
                    ->orderBy('title')
                    ->paginate(12)
                    ->withQueryString();
            });
        } else {
            $biblios = $bibliosQuery
                ->orderByDesc('available_items_count')
                ->orderBy('title')
                ->paginate(12)
                ->withQueryString();
        }

            $facetCacheKey = 'nbk:facets:' . $institutionId . ':' . md5(json_encode($filters));
            $facets = Cache::remember($facetCacheKey, now()->addMinutes(5), function () use ($institutionId, $filters) {
                $facetBase = Biblio::query()->where('biblio.institution_id', $institutionId);
                $this->applyCatalogFilters($facetBase, $filters);
                $fieldFilters = [];
                $qfFields = $filters['qf_field'] ?? [];
                $qfValues = $filters['qf_value'] ?? [];
                $qfOp = (string) ($filters['qf_op'] ?? 'AND');
                $qfExact = (bool) ($filters['qf_exact'] ?? false);
                foreach ($qfFields as $i => $field) {
                    $field = trim((string) $field);
                    $value = isset($qfValues[$i]) ? trim((string) $qfValues[$i]) : '';
                    if ($field === '' || $value === '') {
                        continue;
                    }
                    $fieldFilters[] = ['field' => $field, 'value' => $value];
                }
                $this->applyFieldedFilters($facetBase, $fieldFilters, $qfOp, $qfExact);

                $facetSub = $facetBase->select('biblio.id');

                $authorFacets = Author::query()
                    ->select('authors.id', 'authors.name', DB::raw('COUNT(*) as total'))
                    ->join('biblio_author', 'authors.id', '=', 'biblio_author.author_id')
                    ->joinSub($facetSub, 'fb', function ($join) {
                        $join->on('fb.id', '=', 'biblio_author.biblio_id');
                    })
                    ->groupBy('authors.id', 'authors.name')
                    ->orderByDesc('total')
                    ->limit(12)
                    ->get();

                $subjectFacets = Subject::query()
                    ->select('subjects.id', 'subjects.term', 'subjects.name', DB::raw('COUNT(*) as total'))
                    ->join('biblio_subject', 'subjects.id', '=', 'biblio_subject.subject_id')
                    ->joinSub($facetSub, 'fb', function ($join) {
                        $join->on('fb.id', '=', 'biblio_subject.biblio_id');
                    })
                    ->groupBy('subjects.id', 'subjects.term', 'subjects.name')
                    ->orderByDesc('total')
                    ->limit(12)
                    ->get();

                $publisherFacets = (clone $facetBase)
                    ->select('publisher', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('publisher')
                    ->where('publisher', '<>', '')
                    ->groupBy('publisher')
                    ->orderByDesc('total')
                    ->limit(12)
                    ->get();

                $languageFacets = (clone $facetBase)
                    ->select('language as label', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('language')
                    ->where('language', '<>', '')
                    ->groupBy('language')
                    ->orderByDesc('total')
                    ->limit(12)
                    ->get();

                $materialTypeFacets = (clone $facetBase)
                    ->select('material_type as label', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('material_type')
                    ->where('material_type', '<>', '')
                    ->groupBy('material_type')
                    ->orderByDesc('total')
                    ->limit(12)
                    ->get();

                $mediaTypeFacets = (clone $facetBase)
                    ->select('media_type as label', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('media_type')
                    ->where('media_type', '<>', '')
                    ->groupBy('media_type')
                    ->orderByDesc('total')
                    ->limit(12)
                    ->get();

                $yearFacets = (clone $facetBase)
                    ->select('publish_year as label', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('publish_year')
                    ->groupBy('publish_year')
                    ->orderByDesc('total')
                    ->limit(12)
                    ->get();

                $availableCount = (clone $facetBase)
                    ->whereHas('items', function ($q) {
                        $q->where('status', 'available');
                    })
                    ->count();

                $unavailableCount = (clone $facetBase)
                    ->whereDoesntHave('items', function ($q) {
                        $q->where('status', 'available');
                    })
                    ->count();

                $branchFacets = collect();
                if (Schema::hasColumn('items', 'branch_id')) {
                    $branchFacets = DB::table('items as it')
                        ->joinSub($facetSub, 'fb', function ($join) {
                            $join->on('fb.id', '=', 'it.biblio_id');
                        })
                        ->join('branches as br', 'br.id', '=', 'it.branch_id')
                        ->select('br.id', 'br.name', DB::raw('COUNT(DISTINCT it.biblio_id) as total'))
                        ->whereNotNull('it.branch_id')
                        ->groupBy('br.id', 'br.name')
                        ->orderByDesc('total')
                        ->limit(12)
                        ->get();
                }

                return [
                    'authors' => $authorFacets,
                    'subjects' => $subjectFacets,
                    'publishers' => $publisherFacets,
                    'languages' => $languageFacets,
                    'material_types' => $materialTypeFacets,
                    'media_types' => $mediaTypeFacets,
                    'years' => $yearFacets,
                    'branches' => $branchFacets,
                    'availability' => [
                        'available' => (int) $availableCount,
                        'unavailable' => (int) $unavailableCount,
                    ],
                ];
            });

            $authorFacets = $facets['authors'] ?? collect();
            $subjectFacets = $facets['subjects'] ?? collect();
            $publisherFacets = $facets['publishers'] ?? collect();
            $languageFacets = $facets['languages'] ?? collect();
            $materialTypeFacets = $facets['material_types'] ?? collect();
            $mediaTypeFacets = $facets['media_types'] ?? collect();
            $yearFacets = $facets['years'] ?? collect();
            $branchFacets = $facets['branches'] ?? collect();
            $availabilityFacets = $facets['availability'] ?? ['available' => 0, 'unavailable' => 0];

            if (Schema::hasTable('biblio_metrics') && $biblios->count() > 1) {
                $ranking = app(BiblioRankingService::class);
                $rankedIds = $ranking->rerankIds(
                    $biblios->getCollection()->pluck('id')->all(),
                    $institutionId,
                    auth()->id(),
                    $rankMode,
                    null,
                    $activeBranchId,
                    $branchList
                );
                $rankMap = array_flip($rankedIds);
                $biblios->setCollection(
                    $biblios->getCollection()->sortBy(function ($biblio) use ($rankMap) {
                        return $rankMap[$biblio->id] ?? PHP_INT_MAX;
                    })->values()
                );
            }
        }

        $facetSignature = $this->normalizedFacetCacheSignature(
            $institutionId,
            $filters,
            $fieldFilters,
            $qfOp,
            $qfExact,
            $activeBranchId
        );
        $facetCacheKey = 'nbk:facets:v2:' . md5(json_encode($facetSignature));
        $facetsV2 = Cache::remember($facetCacheKey, now()->addMinutes(5), function () use ($institutionId, $filters, $fieldFilters, $qfOp, $qfExact) {
            $withoutKeys = function (array $base, array $keys): array {
                foreach ($keys as $key) {
                    unset($base[$key]);
                }
                return $base;
            };
            $baseCache = [];
            $subCache = [];
            $normalizeScope = function (array $scopeFilters): array {
                foreach (['author_list', 'subject_list', 'publisher_list', 'language_list', 'material_type_list', 'media_type_list', 'branch_list'] as $k) {
                    if (isset($scopeFilters[$k]) && is_array($scopeFilters[$k])) {
                        $v = $scopeFilters[$k];
                        sort($v);
                        $scopeFilters[$k] = $v;
                    }
                }
                return $scopeFilters;
            };
            $makeBase = function (array $scopeFilters) use (&$baseCache, $normalizeScope, $institutionId, $fieldFilters, $qfOp, $qfExact) {
                $scopeFilters = $normalizeScope($scopeFilters);
                $key = md5(json_encode($scopeFilters));
                if (isset($baseCache[$key])) {
                    return clone $baseCache[$key];
                }
                $q = Biblio::query()->where('biblio.institution_id', $institutionId);
                $this->applyCatalogFilters($q, $scopeFilters);
                $this->applyFieldedFilters($q, $fieldFilters, $qfOp, $qfExact);
                $baseCache[$key] = clone $q;
                return clone $q;
            };
            $makeSub = function (array $scopeFilters) use (&$subCache, $normalizeScope, $makeBase) {
                $scopeFilters = $normalizeScope($scopeFilters);
                $key = md5(json_encode($scopeFilters));
                if (isset($subCache[$key])) {
                    return clone $subCache[$key];
                }
                $sub = $makeBase($scopeFilters)->select('biblio.id');
                $subCache[$key] = clone $sub;
                return clone $sub;
            };

            $authorScope = $withoutKeys($filters, ['author', 'author_list']);
            $authorSub = $makeSub($authorScope);
            $authorFacets = Author::query()
                ->select('authors.id', 'authors.name', DB::raw('COUNT(*) as total'))
                ->join('biblio_author', 'authors.id', '=', 'biblio_author.author_id')
                ->joinSub($authorSub, 'fb', function ($join) {
                    $join->on('fb.id', '=', 'biblio_author.biblio_id');
                })
                ->groupBy('authors.id', 'authors.name')
                ->orderByDesc('total')
                ->limit(12)
                ->get();

            $subjectScope = $withoutKeys($filters, ['subject', 'subject_list']);
            $subjectSub = $makeSub($subjectScope);
            $subjectFacets = Subject::query()
                ->select('subjects.id', 'subjects.term', 'subjects.name', DB::raw('COUNT(*) as total'))
                ->join('biblio_subject', 'subjects.id', '=', 'biblio_subject.subject_id')
                ->joinSub($subjectSub, 'fb', function ($join) {
                    $join->on('fb.id', '=', 'biblio_subject.biblio_id');
                })
                ->groupBy('subjects.id', 'subjects.term', 'subjects.name')
                ->orderByDesc('total')
                ->limit(12)
                ->get();

            $publisherBase = $makeBase($withoutKeys($filters, ['publisher', 'publisher_list']));
            $publisherFacets = (clone $publisherBase)
                ->select('publisher', DB::raw('COUNT(*) as total'))
                ->whereNotNull('publisher')
                ->where('publisher', '<>', '')
                ->groupBy('publisher')
                ->orderByDesc('total')
                ->limit(12)
                ->get();

            $languageBase = $makeBase($withoutKeys($filters, ['language', 'language_list']));
            $languageFacets = (clone $languageBase)
                ->select('language as label', DB::raw('COUNT(*) as total'))
                ->whereNotNull('language')
                ->where('language', '<>', '')
                ->groupBy('language')
                ->orderByDesc('total')
                ->limit(12)
                ->get();

            $materialBase = $makeBase($withoutKeys($filters, ['material_type', 'material_type_list']));
            $materialFacets = (clone $materialBase)
                ->select('material_type as label', DB::raw('COUNT(*) as total'))
                ->whereNotNull('material_type')
                ->where('material_type', '<>', '')
                ->groupBy('material_type')
                ->orderByDesc('total')
                ->limit(12)
                ->get();

            $mediaBase = $makeBase($withoutKeys($filters, ['media_type', 'media_type_list']));
            $mediaFacets = (clone $mediaBase)
                ->select('media_type as label', DB::raw('COUNT(*) as total'))
                ->whereNotNull('media_type')
                ->where('media_type', '<>', '')
                ->groupBy('media_type')
                ->orderByDesc('total')
                ->limit(12)
                ->get();

            $yearBase = $makeBase($withoutKeys($filters, ['year', 'year_from', 'year_to']));
            $yearFacets = (clone $yearBase)
                ->select('publish_year as label', DB::raw('COUNT(*) as total'))
                ->whereNotNull('publish_year')
                ->groupBy('publish_year')
                ->orderByDesc('total')
                ->limit(12)
                ->get();

            $branchScope = $withoutKeys($filters, ['branch_list']);
            $branchSub = $makeSub($branchScope);
            $branchFacets = collect();
            if (Schema::hasColumn('items', 'branch_id')) {
                $branchFacets = DB::table('items as it')
                    ->joinSub($branchSub, 'fb', function ($join) {
                        $join->on('fb.id', '=', 'it.biblio_id');
                    })
                    ->join('branches as br', 'br.id', '=', 'it.branch_id')
                    ->select('br.id', 'br.name', DB::raw('COUNT(DISTINCT it.biblio_id) as total'))
                    ->whereNotNull('it.branch_id')
                    ->groupBy('br.id', 'br.name')
                    ->orderByDesc('total')
                    ->limit(12)
                    ->get();
            }

            $availabilityBase = $makeBase($withoutKeys($filters, ['onlyAvailable']));
            $availableCount = (clone $availabilityBase)
                ->whereHas('items', function ($q) {
                    $q->where('status', 'available');
                })
                ->count();
            $unavailableCount = (clone $availabilityBase)
                ->whereDoesntHave('items', function ($q) {
                    $q->where('status', 'available');
                })
                ->count();

            return [
                'authors' => $authorFacets,
                'subjects' => $subjectFacets,
                'publishers' => $publisherFacets,
                'languages' => $languageFacets,
                'material_types' => $materialFacets,
                'media_types' => $mediaFacets,
                'years' => $yearFacets,
                'branches' => $branchFacets,
                'availability' => [
                    'available' => (int) $availableCount,
                    'unavailable' => (int) $unavailableCount,
                ],
            ];
        });
        $authorFacets = $facetsV2['authors'] ?? collect();
        $subjectFacets = $facetsV2['subjects'] ?? collect();
        $publisherFacets = $facetsV2['publishers'] ?? collect();
        $languageFacets = $facetsV2['languages'] ?? collect();
        $materialTypeFacets = $facetsV2['material_types'] ?? collect();
        $mediaTypeFacets = $facetsV2['media_types'] ?? collect();
        $yearFacets = $facetsV2['years'] ?? collect();
        $branchFacets = $facetsV2['branches'] ?? collect();
        $availabilityFacets = $facetsV2['availability'] ?? ['available' => 0, 'unavailable' => 0];

        if ($q !== '') {
            $this->logSearchQuery($q, $institutionId, auth()->id(), (int) $biblios->total());
        }

        $didYouMean = null;
        if ($q !== '' && $biblios->total() <= 3) {
            $didYouMean = $this->suggestQuery($q, $institutionId);
            if ($didYouMean !== null && $this->normalizeLoose($didYouMean) === $this->normalizeLoose($q)) {
                $didYouMean = null;
            }
        }

        $showDiscovery = $q === ''
            && $ddc === ''
            && $year === ''
            && $yearFrom <= 0
            && $yearTo <= 0
            && empty($publisherList)
            && empty($authorList)
            && empty($subjectList)
            && empty($languageList)
            && empty($materialTypeList)
            && empty($mediaTypeList)
            && empty($branchList)
            && !$onlyAvailable;

        $trendingBooks = collect();
        $newArrivals = collect();

        if ($showDiscovery || $forceShelves || $isPublic) {
            $trendingBooks = Cache::remember(
                'nbk:shelf:popular:' . $institutionId . ':' . ($activeBranchId ?: 'all'),
                now()->addMinutes(5),
                function () use ($institutionId, $activeBranchId) {
                    $query = Biblio::query()
                        ->where('biblio.institution_id', $institutionId);

                    if ($activeBranchId && Schema::hasColumn('items', 'branch_id')) {
                        $query->whereHas('items', function ($q) use ($activeBranchId) {
                            $q->where('items.branch_id', $activeBranchId);
                        });
                    }

                    return $query
                        ->leftJoin('biblio_metrics as bm', function ($join) use ($institutionId) {
                            $join->on('bm.biblio_id', '=', 'biblio.id')
                                ->where('bm.institution_id', '=', $institutionId);
                        })
                        ->select('biblio.*')
                        ->addSelect(DB::raw('COALESCE(bm.click_count, 0) as click_count'))
                        ->addSelect(DB::raw('COALESCE(bm.borrow_count, 0) as borrow_count'))
                        ->addSelect(DB::raw('(COALESCE(bm.borrow_count, 0) * 5 + COALESCE(bm.click_count, 0)) as popularity_score'))
                        ->with(['authors:id,name'])
                        ->withCount([
                            'items',
                            'availableItems as available_items_count'
                        ])
                        ->orderByDesc('popularity_score')
                        ->orderBy('title')
                        ->limit(6)
                        ->get();
                }
            );

            $newArrivals = Cache::remember(
                'nbk:shelf:new:' . $institutionId . ':' . ($activeBranchId ?: 'all'),
                now()->addMinutes(5),
                function () use ($institutionId, $activeBranchId) {
                    $base = Biblio::query()
                        ->where('biblio.institution_id', $institutionId);

                    if ($activeBranchId && Schema::hasColumn('items', 'branch_id')) {
                        $latestItems = DB::table('items')
                            ->select('biblio_id', DB::raw('MAX(created_at) as latest_item_at'))
                            ->where('branch_id', $activeBranchId)
                            ->groupBy('biblio_id');

                        return $base
                            ->joinSub($latestItems, 'li', function ($join) {
                                $join->on('li.biblio_id', '=', 'biblio.id');
                            })
                            ->select('biblio.*', 'li.latest_item_at')
                            ->with(['authors:id,name'])
                            ->withCount([
                                'items',
                                'availableItems as available_items_count'
                            ])
                            ->orderByDesc('li.latest_item_at')
                            ->orderByDesc('biblio.created_at')
                            ->limit(6)
                            ->get();
                    }

                    return $base
                        ->with(['authors:id,name'])
                        ->withCount([
                            'items',
                            'availableItems as available_items_count'
                        ])
                        ->orderByDesc('created_at')
                        ->orderByDesc('publish_year')
                        ->limit(6)
                        ->get();
                }
            );
        }

        $activeBranchLabel = null;
        if ($activeBranchId) {
            try {
                $activeBranchLabel = DB::table('branches')->where('id', $activeBranchId)->value('name');
            } catch (\Throwable $e) {
                $activeBranchLabel = null;
            }
        }

        $languageOptions = Cache::remember("nbk:filter:lang:{$institutionId}", now()->addMinutes(10), function () use ($institutionId) {
            return Biblio::query()
                ->where('institution_id', $institutionId)
                ->whereNotNull('language')
                ->where('language', '<>', '')
                ->select('language')
                ->distinct()
                ->orderBy('language')
                ->pluck('language');
        });

        $materialTypeOptions = Cache::remember("nbk:filter:material:{$institutionId}", now()->addMinutes(10), function () use ($institutionId) {
            return Biblio::query()
                ->where('institution_id', $institutionId)
                ->whereNotNull('material_type')
                ->where('material_type', '<>', '')
                ->select('material_type')
                ->distinct()
                ->orderBy('material_type')
                ->pluck('material_type');
        });

        $mediaTypeOptions = Cache::remember("nbk:filter:media:{$institutionId}", now()->addMinutes(10), function () use ($institutionId) {
            return Biblio::query()
                ->where('institution_id', $institutionId)
                ->whereNotNull('media_type')
                ->where('media_type', '<>', '')
                ->select('media_type')
                ->distinct()
                ->orderBy('media_type')
                ->pluck('media_type');
        });

        $branchOptions = Cache::remember("nbk:filter:branch:{$institutionId}", now()->addMinutes(10), function () use ($institutionId) {
            return Branch::query()
                ->where('institution_id', $institutionId)
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'is_active']);
        });

        $shelfOptions = Cache::remember("nbk:filter:shelf:{$institutionId}", now()->addMinutes(10), function () use ($institutionId) {
            return DB::table('shelves as s')
                ->leftJoin('branches as b', 'b.id', '=', 's.branch_id')
                ->where('s.institution_id', $institutionId)
                ->orderByRaw("COALESCE(b.name, '') asc")
                ->orderBy('s.name')
                ->get([
                    's.id',
                    's.name',
                    's.code',
                    's.branch_id',
                    's.is_active',
                    'b.name as branch_name',
                ]);
        });

        $tagOptions = Cache::remember("nbk:filter:tag:{$institutionId}", now()->addMinutes(10), function () {
            return Tag::query()
                ->orderBy('name')
                ->limit(300)
                ->pluck('name');
        });

        $itemStatusOptions = Cache::remember("nbk:filter:item_status:{$institutionId}", now()->addMinutes(10), function () use ($institutionId) {
            return Item::query()
                ->where('institution_id', $institutionId)
                ->whereNotNull('status')
                ->where('status', '<>', '')
                ->select('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status');
        });

        $indexRouteName = $isPublic ? 'opac.index' : 'katalog.index';
        $showRouteName = $isPublic ? 'opac.show' : 'katalog.show';
        $opacPrefetchUrls = $isPublic ? $this->getOpacPrefetchUrls($institutionId) : [];

        // NEW: Set AI search context if coming from AI
        if ($aiSearch && $q) {
            session()->flash('ai_search_context', [
                'query' => $q,
                'timestamp' => now()->format('H:i'),
                'source' => 'ai_assistant'
            ]);
        }

        $payload = [
            'q' => $q,
            'title' => $title,
            'author_name' => $authorName,
            'subject_term' => $subjectTerm,
            'isbn' => $isbn,
            'call_number' => $callNumber,
            'language' => $language,
            'material_type' => $materialType,
            'media_type' => $mediaType,
            'languageList' => $languageList,
            'materialTypeList' => $materialTypeList,
            'mediaTypeList' => $mediaTypeList,
            'ddc' => $ddc,
            'year' => $year,
            'yearFrom' => $yearFrom,
            'yearTo' => $yearTo,
            'onlyAvailable' => $onlyAvailable,
            'author' => $author,
            'subject' => $subject,
            'publisher' => $publisher,
            'authorList' => $authorList,
            'subjectList' => $subjectList,
            'publisherList' => $publisherList,
            'branchList' => $branchList,
            'sort' => $sort,
            'qfFields' => $qfFields,
            'qfValues' => $qfValues,
            'qfOp' => $qfOp,
            'qfExact' => $qfExact,
            'rankMode' => $rankMode,
            'aiSearch' => $aiSearch, // NEW: Pass AI flag to view
            'forceShelves' => $forceShelves,
            'didYouMean' => $didYouMean,
            'showDiscovery' => $showDiscovery,
            'trendingBooks' => $trendingBooks,
            'newArrivals' => $newArrivals,
            'activeBranchId' => $activeBranchId,
            'activeBranchLabel' => $activeBranchLabel,
            'indexRouteName' => $indexRouteName,
            'showRouteName' => $showRouteName,
            'isPublic' => $isPublic,
            'opacPrefetchUrls' => $opacPrefetchUrls,
            'biblios' => $biblios,
            'authorFacets' => $authorFacets,
            'subjectFacets' => $subjectFacets,
            'publisherFacets' => $publisherFacets,
            'languageFacets' => $languageFacets,
            'materialTypeFacets' => $materialTypeFacets,
            'mediaTypeFacets' => $mediaTypeFacets,
            'yearFacets' => $yearFacets,
            'branchFacets' => $branchFacets,
            'availabilityFacets' => $availabilityFacets,
            'languageOptions' => $languageOptions,
            'materialTypeOptions' => $materialTypeOptions,
            'mediaTypeOptions' => $mediaTypeOptions,
            'branchOptions' => $branchOptions,
            'shelfOptions' => $shelfOptions,
            'tagOptions' => $tagOptions,
            'itemStatusOptions' => $itemStatusOptions,
            'canManage' => (!$isPublic && auth()->check()) ? $this->canManageCatalog() : false,
        ];

        if ($request->ajax() || (string) $request->query('ajax') === '1') {
            if ((string) $request->query('facets_only') === '1') {
                return response()->json([
                    'facet_html' => view('katalog.partials.facets', $payload)->render(),
                ]);
            }

            if ((string) $request->query('grid_only') === '1') {
                return response()->json([
                    'html' => view('katalog.partials.grid', $payload)->render(),
                    'next_page_url' => $biblios->nextPageUrl(),
                ]);
            }

            return response()->json([
                'html' => view('katalog.partials.list', $payload)->render(),
                'next_page_url' => $biblios->nextPageUrl(),
            ]);
        }

        return view('katalog.index', $payload);
    }

    public function index(Request $request)
    {
        return $this->buildIndexPayload($request, false);
    }

    public function facets(Request $request)
    {
        $request->query->set('ajax', '1');
        $request->query->set('facets_only', '1');
        return $this->buildIndexPayload($request, false);
    }

    public function indexPublic(Request $request)
    {
        return $this->buildIndexPayload($request, true);
    }

    public function facetsPublic(Request $request)
    {
        $request->query->set('ajax', '1');
        $request->query->set('facets_only', '1');
        return $this->buildIndexPayload($request, true);
    }

    public function suggest(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $type = in_array($type, ['title', 'author', 'subject', 'publisher', 'isbn', 'ddc', 'call_number'], true) ? $type : '';
        if (mb_strlen($q) < 2) {
            return response()->json(['items' => [], 'preview' => []]);
        }

        $institutionId = $this->currentInstitutionId();
        $isPublic = $request->routeIs('opac.*');

        $cacheKey = 'suggest:' . ($isPublic ? 'pub' : 'inst:' . $institutionId) . ':t=' . ($type ?: 'all') . ':' . md5($q);

        $cached = Cache::get($cacheKey);
        $cacheHit = $cached !== null;

        $payload = $cached ?? Cache::remember($cacheKey, 60, function () use ($q, $type, $institutionId, $isPublic) {
            $titleMatches = collect();
            $authorMatches = collect();
            $subjectMatches = collect();
            $publisherMatches = collect();
            $isbnMatches = collect();
            $ddcMatches = collect();
            $callMatches = collect();

            if ($type === '' || $type === 'title') {
                $titleMatches = Biblio::query()
                    ->when(!$isPublic, fn($qq) => $qq->where('institution_id', $institutionId))
                    ->where(function ($qq) use ($q) {
                        $qq->where('title', 'like', "%{$q}%")
                            ->orWhere('subtitle', 'like', "%{$q}%")
                            ->orWhere('normalized_title', 'like', "%" . $this->normalizeLoose($q) . "%");
                    })
                    ->with(['authors:id,name'])
                    ->orderBy('title')
                    ->limit(6)
                    ->get();
            }

            if ($type === '' || $type === 'author') {
                $authorMatches = Author::query()
                    ->where('name', 'like', "%{$q}%")
                    ->orderBy('name')
                    ->limit(6)
                    ->get();
            }

            if ($type === '' || $type === 'subject') {
                $subjectMatches = Subject::query()
                    ->where(function ($qq) use ($q) {
                        $qq->where('term', 'like', "%{$q}%")
                           ->orWhere('name', 'like', "%{$q}%");
                    })
                    ->orderBy('term')
                    ->limit(6)
                    ->get();
            }

            if ($type === '' || $type === 'publisher') {
                $publisherMatches = Biblio::query()
                    ->when(!$isPublic, fn($qq) => $qq->where('institution_id', $institutionId))
                    ->whereNotNull('publisher')
                    ->where('publisher', 'like', "%{$q}%")
                    ->select('publisher')
                    ->distinct()
                    ->orderBy('publisher')
                    ->limit(6)
                    ->get();
            }

            if ($type === '' || $type === 'isbn') {
                $isbnMatches = Biblio::query()
                    ->when(!$isPublic, fn($qq) => $qq->where('institution_id', $institutionId))
                    ->where('isbn', 'like', "%{$q}%")
                    ->orderBy('isbn')
                    ->limit(4)
                    ->get();
            }

            if ($type === '' || $type === 'ddc') {
                $ddcMatches = Biblio::query()
                    ->when(!$isPublic, fn($qq) => $qq->where('institution_id', $institutionId))
                    ->whereNotNull('ddc')
                    ->where('ddc', 'like', "%{$q}%")
                    ->select('ddc')
                    ->distinct()
                    ->orderBy('ddc')
                    ->limit(6)
                    ->get();
            }

            if ($type === '' || $type === 'call_number') {
                $callMatches = Biblio::query()
                    ->when(!$isPublic, fn($qq) => $qq->where('institution_id', $institutionId))
                    ->whereNotNull('call_number')
                    ->where('call_number', 'like', "%{$q}%")
                    ->select('call_number')
                    ->distinct()
                    ->orderBy('call_number')
                    ->limit(6)
                    ->get();
            }

            $items = [];

            foreach ($titleMatches as $b) {
                $authors = $b->authors?->pluck('name')->take(2)->implode(', ') ?? '';
                $items[] = [
                    'type' => 'title',
                    'label' => trim($b->title . ($authors ? " — {$authors}" : '')),
                    'value' => $b->title,
                    'url' => route($isPublic ? 'opac.show' : 'katalog.show', $b->id),
                ];
            }

            foreach ($authorMatches as $a) {
                $items[] = [
                    'type' => 'author',
                    'label' => $a->name,
                    'value' => $a->name,
                    'url' => route($isPublic ? 'opac.index' : 'katalog.index', ['q' => $a->name]),
                ];
            }

            foreach ($subjectMatches as $s) {
                $label = $s->term ?? $s->name ?? '';
                if ($label === '') continue;
                $items[] = [
                    'type' => 'subject',
                    'label' => $label,
                    'value' => $label,
                    'url' => route($isPublic ? 'opac.index' : 'katalog.index', ['q' => $label]),
                ];
            }

            foreach ($publisherMatches as $p) {
                $label = trim((string) $p->publisher);
                if ($label === '') continue;
                $items[] = [
                    'type' => 'publisher',
                    'label' => $label,
                    'value' => $label,
                    'url' => route($isPublic ? 'opac.index' : 'katalog.index', ['q' => $label]),
                ];
            }

            foreach ($isbnMatches as $b) {
                $items[] = [
                    'type' => 'isbn',
                    'label' => $b->isbn,
                    'value' => $b->isbn,
                    'url' => route($isPublic ? 'opac.show' : 'katalog.show', $b->id),
                ];
            }

            foreach ($ddcMatches as $d) {
                $label = trim((string) $d->ddc);
                if ($label === '') continue;
                $items[] = [
                    'type' => 'ddc',
                    'label' => $label,
                    'value' => $label,
                    'url' => route($isPublic ? 'opac.index' : 'katalog.index', ['q' => $label]),
                ];
            }

            foreach ($callMatches as $c) {
                $label = trim((string) $c->call_number);
                if ($label === '') continue;
                $items[] = [
                    'type' => 'call_number',
                    'label' => $label,
                    'value' => $label,
                    'url' => route($isPublic ? 'opac.index' : 'katalog.index', ['q' => $label]),
                ];
            }

            $previewItems = [];
            if ($type === '' || $type === 'title') {
                foreach ($titleMatches->take(3) as $b) {
                    $cover = !empty($b->cover_path) ? asset('storage/' . ltrim((string) $b->cover_path, '/')) : null;
                    $previewItems[] = [
                        'title' => $b->display_title ?? $b->title,
                        'authors' => $b->authors?->pluck('name')->take(2)->implode(', ') ?? '',
                        'year' => $b->publish_year,
                        'cover' => $cover,
                        'url' => route($isPublic ? 'opac.show' : 'katalog.show', $b->id),
                    ];
                }
            }

            return [
                'items' => array_slice($items, 0, 10),
                'preview' => $previewItems,
            ];
        });

        return response()->json([
            'items' => $payload['items'] ?? [],
            'preview' => $payload['preview'] ?? [],
            'cache_hit' => $cacheHit,
        ]);
    }

    public function setShelvesPreference(Request $request)
    {
        $enabled = (string) $request->input('enabled', '0') === '1';
        session(['opac_shelves' => $enabled]);

        return response()->json([
            'success' => true,
            'enabled' => $enabled,
        ]);
    }

    public function export(Request $request, ExportService $exportService)
    {
        $this->authorize('export', Biblio::class);

        $format = strtolower(trim((string) $request->query('format', 'csv')));
        $institutionId = $this->currentInstitutionId();

        return match ($format) {
            'csv' => $exportService->exportCsvBiblios($institutionId),
            'dcxml' => $exportService->exportDublinCoreXml($institutionId),
            'marcxml' => $exportService->exportMarcXmlCore($institutionId),
            'jsonld' => $exportService->exportJsonLd($institutionId),
            default => response()->json(['message' => 'Format tidak dikenali.'], 422),
        };
    }

    public function import(ImportKatalogRequest $request, ImportService $importService)
    {
        $this->authorize('import', Biblio::class);

        $format = strtolower(trim((string) $request->input('format')));
        $file = $request->file('file');
        $institutionId = $this->currentInstitutionId();
        $userId = auth()->user()?->id;

        if (!$file) {
            return response()->json(['message' => 'File tidak ditemukan.'], 422);
        }

        if ($importService->shouldQueue($file, $format)) {
            $jobId = $importService->queueImport($file, $format, $institutionId, $userId);

            return response()->json([
                'queued' => true,
                'job_id' => $jobId,
                'report' => [
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => [],
                ],
            ], 202);
        }

        $report = $importService->importByFormat($format, $file, $institutionId, $userId);

        if (($report['status'] ?? '') === 'invalid_format') {
            return response()->json($report, 422);
        }

        $ids = $report['biblio_ids'] ?? [];
        $total = (int) ($report['created'] ?? 0) + (int) ($report['updated'] ?? 0);
        if (!empty($ids) && $importService->shouldQueueAi($total)) {
            \App\Jobs\AiCatalogingJob::dispatch($ids, $institutionId);
        }

        return response()->json($report);
    }

    public function create()
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        return view('katalog.create', [
            'canManage' => true,
        ]);
    }

    public function store(StoreBiblioRequest $request, MetadataMappingService $metadataService, \App\Services\AiCatalogingService $aiCatalogingService)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $data = $request->validated();

        $institutionId = $this->currentInstitutionId();
        $gate = ['ok' => true, 'errors' => [], 'warnings' => []];
        if ((bool) config('notobuku.catalog.quality_gate.enabled', true)) {
            /** @var \App\Services\CatalogQualityGateService $qualityGate */
            $qualityGate = app(\App\Services\CatalogQualityGateService::class);
            $gate = $qualityGate->evaluate($data, $institutionId, null);
            if (!$gate['ok']) {
                return back()
                    ->withInput()
                    ->withErrors(['quality_gate' => implode(' ', (array) ($gate['errors'] ?? []))]);
            }
        }

        $title = trim($data['title']);
        $subtitle = isset($data['subtitle']) ? trim((string) $data['subtitle']) : null;
        $subtitle = ($subtitle !== '' ? $subtitle : null);

        // Note:… Simpan cover dulu (opsional) agar bisa langsung masuk create array
        $coverPath = null;
        $coverError = null;
        try {
            $file = $request->file('cover');
            \Log::info('Cover upload (store) debug', [
                'has_file' => $request->hasFile('cover'),
                'file_keys' => array_keys($request->allFiles() ?? []),
                'name' => $file?->getClientOriginalName(),
                'size' => $file?->getSize(),
                'error' => $file?->getError(),
                'mime' => $file?->getClientMimeType(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Cover upload (store) debug failed: ' . $e->getMessage());
        }
        if ($request->hasFile('cover')) {
            try {
                $coverPath = $request->file('cover')->store('covers', 'public');
            } catch (\Throwable $e) {
                $coverPath = null; // jangan gagalkan proses utama
                $coverError = $e->getMessage();
            }
        }

        $biblio = Biblio::create([
            'institution_id' => $institutionId,

            'title' => $title,
            'subtitle' => $subtitle,

            'normalized_title' => $this->normalizeTitle($title, $subtitle),

            'responsibility_statement' => isset($data['responsibility_statement'])
                ? (trim((string) $data['responsibility_statement']) ?: null)
                : null,

            'publisher' => isset($data['publisher']) ? trim((string) $data['publisher']) ?: null : null,
            'place_of_publication' => isset($data['place_of_publication']) ? trim((string) $data['place_of_publication']) ?: null : null,

            'publish_year' => $data['publish_year'] ?? null,
            'isbn' => isset($data['isbn']) ? trim((string) $data['isbn']) ?: null : null,
            'issn' => isset($data['issn']) ? trim((string) $data['issn']) ?: null : null,

            'language' => isset($data['language']) ? trim((string) $data['language']) ?: 'id' : 'id',
            'edition' => isset($data['edition']) ? trim((string) $data['edition']) ?: null : null,

            'physical_desc' => isset($data['physical_desc']) ? trim((string) $data['physical_desc']) ?: null : null,

            'extent' => isset($data['extent']) ? trim((string) $data['extent']) ?: null : null,
            'dimensions' => isset($data['dimensions']) ? trim((string) $data['dimensions']) ?: null : null,
            'illustrations' => isset($data['illustrations']) ? trim((string) $data['illustrations']) ?: null : null,
            'series_title' => isset($data['series_title']) ? trim((string) $data['series_title']) ?: null : null,

            'cover_path' => $coverPath, // Note:…

            'ddc' => isset($data['ddc']) ? trim((string) $data['ddc']) ?: null : null,
            'call_number' => isset($data['call_number']) ? trim((string) $data['call_number']) ?: null : null,

            'notes' => isset($data['notes']) ? trim((string) $data['notes']) ?: null : null,
            'bibliography_note' => isset($data['bibliography_note']) ? trim((string) $data['bibliography_note']) ?: null : null,
            'general_note' => isset($data['general_note']) ? trim((string) $data['general_note']) ?: null : null,

            'frequency' => isset($data['frequency']) ? trim((string) $data['frequency']) ?: null : null,
            'former_frequency' => isset($data['former_frequency']) ? trim((string) $data['former_frequency']) ?: null : null,
            'serial_beginning' => isset($data['serial_beginning']) ? trim((string) $data['serial_beginning']) ?: null : null,
            'serial_ending' => isset($data['serial_ending']) ? trim((string) $data['serial_ending']) ?: null : null,
            'serial_first_issue' => isset($data['serial_first_issue']) ? trim((string) $data['serial_first_issue']) ?: null : null,
            'serial_last_issue' => isset($data['serial_last_issue']) ? trim((string) $data['serial_last_issue']) ?: null : null,
            'serial_source_note' => isset($data['serial_source_note']) ? trim((string) $data['serial_source_note']) ?: null : null,
            'serial_preceding_title' => isset($data['serial_preceding_title']) ? trim((string) $data['serial_preceding_title']) ?: null : null,
            'serial_preceding_issn' => isset($data['serial_preceding_issn']) ? trim((string) $data['serial_preceding_issn']) ?: null : null,
            'serial_succeeding_title' => isset($data['serial_succeeding_title']) ? trim((string) $data['serial_succeeding_title']) ?: null : null,
            'serial_succeeding_issn' => isset($data['serial_succeeding_issn']) ? trim((string) $data['serial_succeeding_issn']) ?: null : null,
            'holdings_summary' => isset($data['holdings_summary']) ? trim((string) $data['holdings_summary']) ?: null : null,
            'holdings_supplement' => isset($data['holdings_supplement']) ? trim((string) $data['holdings_supplement']) ?: null : null,
            'holdings_index' => isset($data['holdings_index']) ? trim((string) $data['holdings_index']) ?: null : null,

            'material_type' => isset($data['material_type']) ? (trim((string) $data['material_type']) ?: 'buku') : 'buku',
            'media_type' => isset($data['media_type']) ? (trim((string) $data['media_type']) ?: 'teks') : 'teks',
            'audience' => isset($data['audience']) ? trim((string) $data['audience']) ?: null : null,
            'is_reference' => isset($data['is_reference'])
                ? (in_array((string) $data['is_reference'], ['1', 'true', 'on', 'yes'], true))
                : false,

            'ai_status' => 'draft',
        ]);

        // Authors (relator-aware)
        $useRoles = (string)($data['authors_role_mode'] ?? '0') === '1';
        $authorsRoles = $request->input('authors_roles_json');

        if ($useRoles && is_array($authorsRoles) && !empty($authorsRoles)) {
            $syncAuthors = [];
            $rows = collect($authorsRoles)
                ->filter(fn($row) => is_array($row))
                ->map(function ($row) {
                    return [
                        'name' => trim((string)($row['name'] ?? '')),
                        'role' => trim((string)($row['role'] ?? 'aut')),
                    ];
                })
                ->filter(fn($row) => $row['name'] !== '')
                ->values();

            foreach ($rows as $i => $row) {
                $name = $row['name'];
                $role = $row['role'] !== '' ? $row['role'] : 'aut';
                if ($role === 'pengarang') $role = 'aut';

                $normalized = $this->normalizeLoose($name);
                $author = Author::query()->firstOrCreate(
                    ['normalized_name' => $normalized],
                    ['name' => $name, 'normalized_name' => $normalized]
                );

                $syncAuthors[$author->id] = ['role' => $role, 'sort_order' => $i + 1];
            }

            if (!empty($syncAuthors)) {
                $biblio->authors()->sync($syncAuthors);
            }
        } else {
            $authors = collect(explode(',', (string) $data['authors_text']))
                ->map(fn($x) => trim($x))
                ->filter()
                ->values();

            foreach ($authors as $i => $name) {
                $normalized = $this->normalizeLoose($name);

                $author = Author::query()->firstOrCreate(
                    ['normalized_name' => $normalized],
                    ['name' => $name, 'normalized_name' => $normalized]
                );

                $biblio->authors()->syncWithoutDetaching([
                    $author->id => ['role' => 'pengarang', 'sort_order' => $i + 1],
                ]);
            }
        }

        // Subjects
        $subjectsText = trim((string) ($data['subjects_text'] ?? ''));
        if ($subjectsText !== '') {
            $subjects = collect(preg_split('/[,;\n]/', $subjectsText))
                ->map(fn($x) => trim($x))
                ->filter()
                ->values();

            foreach ($subjects as $i => $term) {
                $normalized = $this->normalizeLoose($term);

                $subject = Subject::query()->firstOrCreate(
                    ['normalized_term' => $normalized],
                    [
                        'name' => $term,
                        'term' => $term,
                        'normalized_term' => $normalized,
                        'scheme' => 'local',
                    ]
                );

                $biblio->subjects()->syncWithoutDetaching([
                    $subject->id => ['type' => 'topic', 'sort_order' => $i + 1],
                ]);
            }
        }

        // Tags
        $tagsText = trim((string) ($data['tags_text'] ?? ''));
        if ($tagsText !== '') {
            $tags = collect(preg_split('/[,;\n]/', $tagsText))
                ->map(fn($x) => trim($x))
                ->filter()
                ->values();

            foreach ($tags as $i => $name) {
                $normalized = $this->normalizeLoose($name);

                $tag = Tag::query()->firstOrCreate(
                    ['normalized_name' => $normalized],
                    ['name' => $name, 'normalized_name' => $normalized]
                );

                $biblio->tags()->syncWithoutDetaching([
                    $tag->id => ['sort_order' => $i + 1],
                ]);
            }
        }

        $dcI18n = $this->normalizeDcI18nInput($request->input('dc_i18n'));
        $identifiers = $this->parseIdentifiersInput($request->input('identifiers'));
        $metadataService->syncMetadataForBiblio($biblio, $dcI18n, $identifiers);
        $aiCatalogingService->runForBiblio($biblio);

        // Create Items (optional)
        $copiesCount = (int) ($data['copies_count'] ?? 0);
        if ($copiesCount > 0) {
            for ($i = 0; $i < $copiesCount; $i++) {
                $barcode = $this->generateUniqueCode('NB', 'barcode');
                $acc = $this->generateUniqueCode('ACC', 'accession_number');

                Item::create([
                    'institution_id' => $institutionId,
                    'branch_id' => null,
                    'shelf_id' => null,
                    'biblio_id' => $biblio->id,
                    'barcode' => $barcode,
                    'accession_number' => $acc,
                    'inventory_code' => null,
                    'status' => 'available',
                    'acquired_at' => null,
                    'price' => null,
                    'source' => null,
                    'notes' => null,
                ]);
            }
        }

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'create',
                'format' => 'biblio',
                'status' => 'success',
                'meta' => [
                    'biblio_id' => (int) $biblio->id,
                    'title' => (string) $biblio->title,
                    'publisher' => (string) ($biblio->publisher ?? ''),
                    'isbn' => (string) ($biblio->isbn ?? ''),
                    'copies_created' => $copiesCount,
                    'ip' => (string) request()->ip(),
                    'user_agent' => (string) request()->userAgent(),
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore audit failures
        }

        $redirect = redirect()
            ->route('katalog.show', $biblio->id)
            ->with('success', 'Bibliografi berhasil ditambahkan. Eksemplar: ' . $copiesCount);
        if (!empty($gate['warnings'])) {
            $redirect->with('warning', implode(' | ', (array) $gate['warnings']));
        }

        if ($coverError) {
            \Log::warning('Upload cover gagal (store): ' . $coverError);
            $redirect->with('warning', 'Cover gagal diupload. Coba file lain atau cek izin folder storage.');
        }

        return $redirect;
    }

    public function edit($id)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $institutionId = $this->currentInstitutionId();

        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->with(['authors', 'subjects', 'tags'])
            ->withCount([
                'items',
                'availableItems as available_items_count'
            ])
            ->findOrFail($id);

        $authorsText = $biblio->authors?->pluck('name')->filter()->implode(', ') ?? '';
        $subjectsText = $biblio->subjects?->pluck('term')->filter()->implode('; ') ?? '';
        $tagsText = $biblio->tags?->pluck('name')->filter()->implode(', ') ?? '';

        $marcErrors = collect();
        $marcWarnings = collect();
        try {
            $issues = (new MarcValidationService())->validateForExport($biblio);
            $marcErrors = collect($issues)
                ->filter(fn($msg) => !str_starts_with((string) $msg, 'WARN:'))
                ->values();
            $marcWarnings = collect($issues)
                ->filter(fn($msg) => str_starts_with((string) $msg, 'WARN:'))
                ->map(fn($msg) => trim(substr((string) $msg, 5)))
                ->values();
        } catch (\Throwable $e) {
            \Log::warning('MARC validation preview failed: ' . $e->getMessage());
        }

        $auditRows = AuditLog::query()
            ->where(function ($q) use ($id) {
                $q->where('format', 'biblio')
                    ->where('meta->biblio_id', $id);
            })
            ->orWhere(function ($q) use ($id) {
                $q->where('format', 'biblio_attachment')
                    ->where('meta->biblio_id', $id);
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $auditUsers = User::query()
            ->whereIn('id', $auditRows->pluck('user_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        return view('katalog.edit', [
            'biblio' => $biblio,
            'authorsText' => $authorsText,
            'subjectsText' => $subjectsText,
            'tagsText' => $tagsText,
            'attachments' => $biblio->attachments?->sortByDesc('created_at')->values() ?? collect(),
            'canManage' => true,
            'marcErrors' => $marcErrors,
            'marcWarnings' => $marcWarnings,
            'auditRows' => $auditRows,
            'auditUsers' => $auditUsers,
        ]);
    }

    public function audit(Request $request, $id)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $institutionId = $this->currentInstitutionId();
        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $query = AuditLog::query()
            ->where(function ($q) use ($id) {
                $q->where('format', 'biblio')
                    ->where('meta->biblio_id', $id);
            })
            ->orWhere(function ($q) use ($id) {
                $q->where('format', 'biblio_attachment')
                    ->where('meta->biblio_id', $id);
            })
            ->orderByDesc('created_at');

        $action = trim((string) $request->query('action', ''));
        if ($action !== '') {
            $query->where('action', $action);
        }
        $status = trim((string) $request->query('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }
        $start = trim((string) $request->query('start_date', ''));
        if ($start !== '') {
            $query->whereDate('created_at', '>=', $start);
        }
        $end = trim((string) $request->query('end_date', ''));
        if ($end !== '') {
            $query->whereDate('created_at', '<=', $end);
        }

        $audits = $query->paginate(50);
        $auditUsers = User::query()
            ->whereIn('id', collect($audits->items())->pluck('user_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        return view('katalog.audit', [
            'biblio' => $biblio,
            'audits' => $audits,
            'auditUsers' => $auditUsers,
            'auditFilters' => [
                'action' => $action,
                'status' => $status,
                'start_date' => $start,
                'end_date' => $end,
            ],
        ]);
    }

    public function auditCsv(Request $request, $id)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $institutionId = $this->currentInstitutionId();
        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $query = AuditLog::query()
            ->where(function ($q) use ($id) {
                $q->where('format', 'biblio')
                    ->where('meta->biblio_id', $id);
            })
            ->orWhere(function ($q) use ($id) {
                $q->where('format', 'biblio_attachment')
                    ->where('meta->biblio_id', $id);
            })
            ->orderByDesc('created_at')
            ;

        $action = trim((string) $request->query('action', ''));
        if ($action !== '') {
            $query->where('action', $action);
        }
        $status = trim((string) $request->query('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }
        $start = trim((string) $request->query('start_date', ''));
        if ($start !== '') {
            $query->whereDate('created_at', '>=', $start);
        }
        $end = trim((string) $request->query('end_date', ''));
        if ($end !== '') {
            $query->whereDate('created_at', '<=', $end);
        }

        $rows = $query->get();

        $auditUsers = User::query()
            ->whereIn('id', $rows->pluck('user_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        $fileName = 'audit_katalog_' . $biblio->id . '.csv';

        return response()->streamDownload(function () use ($rows, $auditUsers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['created_at', 'action', 'format', 'status', 'user_id', 'user_name', 'meta']);
            foreach ($rows as $row) {
                $userName = $auditUsers[$row->user_id]->name ?? '';
                fputcsv($out, [
                    $row->created_at?->format('Y-m-d H:i:s'),
                    $row->action,
                    $row->format,
                    $row->status,
                    $row->user_id,
                    $userName,
                    json_encode($row->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function addAttachment(Request $request, $id)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $institutionId = $this->currentInstitutionId();

        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'visibility' => ['required', 'in:public,member,staff'],
            'attachment' => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,jpg,jpeg,png,webp,gif,mp3,wav,ogg,mp4,webm,zip,doc,docx,xls,xlsx,ppt,pptx,txt,epub',
            ],
        ]);

        $file = $request->file('attachment');
        $original = $file?->getClientOriginalName() ?? 'attachment';
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = pathinfo($original, PATHINFO_FILENAME) ?: $original;
        }

        $path = $file->store('attachments', 'public');

        BiblioAttachment::create([
            'biblio_id' => $biblio->id,
            'title' => $title,
            'file_path' => $path,
            'file_name' => $original,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'visibility' => $data['visibility'],
            'created_by' => auth()->id(),
        ]);

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'attachment_add',
                'format' => 'biblio_attachment',
                'status' => 'success',
                'meta' => [
                    'biblio_id' => (int) $biblio->id,
                    'title' => (string) $title,
                    'file_name' => (string) $original,
                    'file_path' => (string) $path,
                    'mime_type' => (string) ($file?->getClientMimeType() ?? ''),
                    'file_size' => (int) ($file?->getSize() ?? 0),
                    'visibility' => (string) ($data['visibility'] ?? 'staff'),
                    'ip' => (string) request()->ip(),
                    'user_agent' => (string) request()->userAgent(),
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore audit failures
        }

        return back()->with('success', 'Lampiran berhasil ditambahkan.');
    }

    public function deleteAttachment($id, $attachmentId)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $institutionId = $this->currentInstitutionId();

        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $attachment = BiblioAttachment::query()
            ->where('biblio_id', $biblio->id)
            ->findOrFail($attachmentId);

        $auditMeta = [
            'biblio_id' => (int) $biblio->id,
            'attachment_id' => (int) $attachment->id,
            'file_name' => (string) ($attachment->file_name ?? ''),
            'file_path' => (string) ($attachment->file_path ?? ''),
            'mime_type' => (string) ($attachment->mime_type ?? ''),
            'file_size' => (int) ($attachment->file_size ?? 0),
            'visibility' => (string) ($attachment->visibility ?? 'staff'),
            'ip' => (string) request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ];

        try {
            if (!empty($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $attachment->delete();

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'attachment_delete',
                'format' => 'biblio_attachment',
                'status' => 'success',
                'meta' => $auditMeta,
            ]);
        } catch (\Throwable $e) {
            // ignore audit failures
        }

        return back()->with('success', 'Lampiran berhasil dihapus.');
    }

    public function downloadAttachment(Request $request, $id, $attachmentId)
    {
        $isPublic = $request->routeIs('opac.*');

        $biblioQuery = Biblio::query();
        if (!$isPublic) {
            $institutionId = $this->currentInstitutionId();
            $biblioQuery->where('institution_id', $institutionId);
        }

        $biblio = $biblioQuery->findOrFail($id);

        $attachment = BiblioAttachment::query()
            ->where('biblio_id', $biblio->id)
            ->findOrFail($attachmentId);

        if (!$this->canViewAttachment($attachment->visibility)) {
            abort(403);
        }

        $path = $attachment->file_path;
        if (!$path || !Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $downloadName = $attachment->file_name ?: basename($path);

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'download',
                'format' => 'biblio_attachment',
                'status' => 'success',
                'meta' => [
                    'biblio_id' => (int) $biblio->id,
                    'attachment_id' => (int) $attachment->id,
                    'visibility' => (string) ($attachment->visibility ?? 'staff'),
                    'file_name' => $downloadName,
                    'file_path' => (string) $path,
                    'mime_type' => (string) ($attachment->mime_type ?? ''),
                    'ip' => (string) $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore audit failures
        }

        if ((string) $request->query('inline') === '1') {
            $fullPath = Storage::disk('public')->path($path);
            $mime = $attachment->mime_type ?: 'application/pdf';
            return response()->file($fullPath, [
                'Content-Type' => $mime,
            ]);
        }

        return Storage::disk('public')->download($path, $downloadName);
    }

    public function update(UpdateBiblioRequest $request, $id, MetadataMappingService $metadataService, \App\Services\AiCatalogingService $aiCatalogingService)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $institutionId = $this->currentInstitutionId();

        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->with(['authors', 'subjects', 'tags'])
            ->findOrFail($id);

        $data = $request->validated();
        $gate = ['ok' => true, 'errors' => [], 'warnings' => []];
        if ((bool) config('notobuku.catalog.quality_gate.enabled', true)) {
            /** @var \App\Services\CatalogQualityGateService $qualityGate */
            $qualityGate = app(\App\Services\CatalogQualityGateService::class);
            $gate = $qualityGate->evaluate($data, $institutionId, (int) $biblio->id);
            if (!$gate['ok']) {
                return back()
                    ->withInput()
                    ->withErrors(['quality_gate' => implode(' ', (array) ($gate['errors'] ?? []))]);
            }
        }

        $title = trim($data['title']);
        $subtitle = isset($data['subtitle']) ? trim((string) $data['subtitle']) : null;
        $subtitle = ($subtitle !== '' ? $subtitle : null);

        $biblio->title = $title;
        $biblio->subtitle = $subtitle;
        $biblio->normalized_title = $this->normalizeTitle($title, $subtitle);

        $biblio->responsibility_statement = isset($data['responsibility_statement'])
            ? (trim((string) $data['responsibility_statement']) ?: null)
            : null;

        $biblio->publisher = isset($data['publisher']) ? (trim((string) $data['publisher']) ?: null) : null;
        $biblio->place_of_publication = isset($data['place_of_publication']) ? (trim((string) $data['place_of_publication']) ?: null) : null;
        $biblio->publish_year = $data['publish_year'] ?? null;

        $biblio->isbn = isset($data['isbn']) ? (trim((string) $data['isbn']) ?: null) : null;
        $biblio->issn = isset($data['issn']) ? (trim((string) $data['issn']) ?: null) : null;

        $biblio->language = isset($data['language'])
            ? (trim((string) $data['language']) ?: 'id')
            : ($biblio->language ?: 'id');

        $biblio->material_type = isset($data['material_type'])
            ? (trim((string) $data['material_type']) ?: null)
            : ($biblio->material_type ?: null);
        $biblio->media_type = isset($data['media_type'])
            ? (trim((string) $data['media_type']) ?: null)
            : ($biblio->media_type ?: null);

        $biblio->edition = isset($data['edition']) ? (trim((string) $data['edition']) ?: null) : null;
        $biblio->physical_desc = isset($data['physical_desc']) ? (trim((string) $data['physical_desc']) ?: null) : null;

        $biblio->ddc = isset($data['ddc']) ? (trim((string) $data['ddc']) ?: null) : null;
        $biblio->call_number = isset($data['call_number']) ? (trim((string) $data['call_number']) ?: null) : null;

        $biblio->notes = isset($data['notes']) ? (trim((string) $data['notes']) ?: null) : null;

        $biblio->frequency = isset($data['frequency']) ? (trim((string) $data['frequency']) ?: null) : null;
        $biblio->former_frequency = isset($data['former_frequency']) ? (trim((string) $data['former_frequency']) ?: null) : null;
        $biblio->serial_beginning = isset($data['serial_beginning']) ? (trim((string) $data['serial_beginning']) ?: null) : null;
        $biblio->serial_ending = isset($data['serial_ending']) ? (trim((string) $data['serial_ending']) ?: null) : null;
        $biblio->serial_first_issue = isset($data['serial_first_issue']) ? (trim((string) $data['serial_first_issue']) ?: null) : null;
        $biblio->serial_last_issue = isset($data['serial_last_issue']) ? (trim((string) $data['serial_last_issue']) ?: null) : null;
        $biblio->serial_source_note = isset($data['serial_source_note']) ? (trim((string) $data['serial_source_note']) ?: null) : null;
        $biblio->serial_preceding_title = isset($data['serial_preceding_title']) ? (trim((string) $data['serial_preceding_title']) ?: null) : null;
        $biblio->serial_preceding_issn = isset($data['serial_preceding_issn']) ? (trim((string) $data['serial_preceding_issn']) ?: null) : null;
        $biblio->serial_succeeding_title = isset($data['serial_succeeding_title']) ? (trim((string) $data['serial_succeeding_title']) ?: null) : null;
        $biblio->serial_succeeding_issn = isset($data['serial_succeeding_issn']) ? (trim((string) $data['serial_succeeding_issn']) ?: null) : null;
        $biblio->holdings_summary = isset($data['holdings_summary']) ? (trim((string) $data['holdings_summary']) ?: null) : null;
        $biblio->holdings_supplement = isset($data['holdings_supplement']) ? (trim((string) $data['holdings_supplement']) ?: null) : null;
        $biblio->holdings_index = isset($data['holdings_index']) ? (trim((string) $data['holdings_index']) ?: null) : null;

        $biblio->save();

        /**
         * Note:… COVER LOGIC (AMAN):
         * 1) Kalau user klik "hapus cover" (remove_cover=1) dan tidak upload cover baru => hapus file + null
         * 2) Kalau user upload cover baru => hapus cover lama + simpan cover baru + remove_cover diabaikan
         */
        $removeCover = (string)($request->input('remove_cover', '0')) === '1';

        $coverError = null;
        try {
            $file = $request->file('cover');
            \Log::info('Cover upload (update) debug', [
                'has_file' => $request->hasFile('cover'),
                'file_keys' => array_keys($request->allFiles() ?? []),
                'name' => $file?->getClientOriginalName(),
                'size' => $file?->getSize(),
                'error' => $file?->getError(),
                'mime' => $file?->getClientMimeType(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Cover upload (update) debug failed: ' . $e->getMessage());
        }
        if ($request->hasFile('cover')) {
            try {
                if (!empty($biblio->cover_path)) {
                    Storage::disk('public')->delete($biblio->cover_path);
                }
                $path = $request->file('cover')->store('covers', 'public');
                $biblio->cover_path = $path;
                $biblio->save();
            } catch (\Throwable $e) {
                $coverError = $e->getMessage();
            }
        } elseif ($removeCover) {
            try {
                if (!empty($biblio->cover_path)) {
                    Storage::disk('public')->delete($biblio->cover_path);
                }
            } catch (\Throwable $e) {
                // ignore
            }
            $biblio->cover_path = null;
            $biblio->save();
        }

        // SYNC Authors (relator-aware)
        $useRoles = (string)($data['authors_role_mode'] ?? '0') === '1';
        $authorsRoles = $request->input('authors_roles_json');
        $syncAuthors = [];

        if ($useRoles && is_array($authorsRoles) && !empty($authorsRoles)) {
            $rows = collect($authorsRoles)
                ->filter(fn($row) => is_array($row))
                ->map(function ($row) {
                    return [
                        'name' => trim((string)($row['name'] ?? '')),
                        'role' => trim((string)($row['role'] ?? 'aut')),
                    ];
                })
                ->filter(fn($row) => $row['name'] !== '')
                ->values();

            foreach ($rows as $i => $row) {
                $name = $row['name'];
                $role = $row['role'] !== '' ? $row['role'] : 'aut';
                if ($role === 'pengarang') $role = 'aut';

                $normalized = $this->normalizeLoose($name);
                $author = Author::query()->firstOrCreate(
                    ['normalized_name' => $normalized],
                    ['name' => $name, 'normalized_name' => $normalized]
                );

                $syncAuthors[$author->id] = ['role' => $role, 'sort_order' => $i + 1];
            }
        } else {
            $authors = collect(explode(',', (string) $data['authors_text']))
                ->map(fn($x) => trim($x))
                ->filter()
                ->values();

            foreach ($authors as $i => $name) {
                $normalized = $this->normalizeLoose($name);

                $author = Author::query()->firstOrCreate(
                    ['normalized_name' => $normalized],
                    ['name' => $name, 'normalized_name' => $normalized]
                );

                $syncAuthors[$author->id] = ['role' => 'pengarang', 'sort_order' => $i + 1];
            }
        }
        $biblio->authors()->sync($syncAuthors);

        // SYNC Subjects
        $subjectsText = trim((string) ($data['subjects_text'] ?? ''));
        $syncSubjects = [];
        if ($subjectsText !== '') {
            $subjects = collect(preg_split('/[,;\n]/', $subjectsText))
                ->map(fn($x) => trim($x))
                ->filter()
                ->values();

            foreach ($subjects as $i => $term) {
                $normalized = $this->normalizeLoose($term);

                $subject = Subject::query()->firstOrCreate(
                    ['normalized_term' => $normalized],
                    [
                        'name' => $term,
                        'term' => $term,
                        'normalized_term' => $normalized,
                        'scheme' => 'local',
                    ]
                );

                $syncSubjects[$subject->id] = ['type' => 'topic', 'sort_order' => $i + 1];
            }
        }
        $biblio->subjects()->sync($syncSubjects);

        // SYNC Tags
        $tagsText = trim((string) ($data['tags_text'] ?? ''));
        $syncTags = [];
        if ($tagsText !== '') {
            $tags = collect(preg_split('/[,;\n]/', $tagsText))
                ->map(fn($x) => trim($x))
                ->filter()
                ->values();

            foreach ($tags as $i => $name) {
                $normalized = $this->normalizeLoose($name);

                $tag = Tag::query()->firstOrCreate(
                    ['normalized_name' => $normalized],
                    ['name' => $name, 'normalized_name' => $normalized]
                );

                $syncTags[$tag->id] = ['sort_order' => $i + 1];
            }
        }
        $biblio->tags()->sync($syncTags);

        $dcI18n = $this->normalizeDcI18nInput($request->input('dc_i18n'));
        $identifiers = $this->parseIdentifiersInput($request->input('identifiers'));
        $metadataService->syncMetadataForBiblio($biblio, $dcI18n, $identifiers);
        $aiCatalogingService->runForBiblio($biblio);

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'update',
                'format' => 'biblio',
                'status' => 'success',
                'meta' => [
                    'biblio_id' => (int) $biblio->id,
                    'title' => (string) $biblio->title,
                    'publisher' => (string) ($biblio->publisher ?? ''),
                    'isbn' => (string) ($biblio->isbn ?? ''),
                    'ip' => (string) request()->ip(),
                    'user_agent' => (string) request()->userAgent(),
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore audit failures
        }

        $redirect = redirect()
            ->route('katalog.show', $biblio->id)
            ->with('success', 'Bibliografi berhasil diperbarui.');
        if (!empty($gate['warnings'])) {
            $redirect->with('warning', implode(' | ', (array) $gate['warnings']));
        }

        if ($coverError) {
            \Log::warning('Upload cover gagal (update): ' . $coverError);
            $redirect->with('warning', 'Cover gagal diupload. Coba file lain atau cek izin folder storage.');
        }

        return $redirect;
    }

    public function autofix($id, MetadataMappingService $metadataService, BiblioAutofixService $autofixService)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $institutionId = $this->currentInstitutionId();
        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->with(['authors', 'subjects', 'tags'])
            ->findOrFail($id);

        $changed = $autofixService->autofix($biblio);
        $metadataService->syncMetadataForBiblio($biblio);

        return redirect()
            ->route('katalog.edit', ['id' => $biblio->id])
            ->with('status', $changed ? 'Auto-fix diterapkan.' : 'Auto-fix: tidak ada perubahan.');
    }

    public function isbnLookup(Request $request, ExternalApiService $externalApiService)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $isbn = trim((string) $request->query('isbn', ''));
        $isbn = preg_replace('/[^0-9Xx]/', '', $isbn);
        if ($isbn === '' || !in_array(strlen($isbn), [10, 13], true)) {
            return response()->json(['ok' => false, 'message' => 'ISBN tidak valid.'], 422);
        }

        $book = $externalApiService->getBookByIsbn($isbn);
        if (!$book) {
            return response()->json(['ok' => false, 'message' => 'Data ISBN tidak ditemukan.'], 404);
        }

        $authors = $book['authors'] ?? [];
        if (is_array($authors)) {
            $authorText = implode(', ', array_slice(array_filter(array_map(function ($a) {
                if (is_array($a)) return $a['name'] ?? '';
                return (string) $a;
            }, $authors)), 0, 3));
        } else {
            $authorText = (string) $authors;
        }

        $subjects = $book['category'] ?? '';
        $subjectsText = '';
        if (is_array($subjects)) {
            $subjectsText = implode('; ', array_slice(array_filter(array_map('strval', $subjects)), 0, 5));
        } elseif (is_string($subjects)) {
            $subjectsText = $subjects;
        }

        $payload = [
            'title' => (string) ($book['title'] ?? ''),
            'subtitle' => (string) ($book['subtitle'] ?? ''),
            'authors_text' => $authorText,
            'publisher' => (string) ($book['publisher'] ?? ''),
            'publish_year' => (string) ($book['year'] ?? ''),
            'isbn' => (string) ($book['isbn'] ?? $isbn),
            'language' => (string) ($book['language'] ?? 'id'),
            'physical_desc' => !empty($book['page_count']) ? ((int) $book['page_count'] . ' hlm') : '',
            'subjects_text' => $subjectsText,
            'notes' => (string) ($book['description'] ?? ''),
            'cover_url' => (string) ($book['cover_url'] ?? ''),
            'source' => (string) ($book['source'] ?? ''),
        ];

        return response()->json(['ok' => true, 'data' => $payload]);
    }

    public function validateMetadata(Request $request, MarcValidationService $validationService)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $data = $request->all();

        $biblio = new Biblio();
        $biblio->institution_id = $this->currentInstitutionId();
        $biblio->title = trim((string) ($data['title'] ?? ''));
        $biblio->subtitle = trim((string) ($data['subtitle'] ?? ''));
        $biblio->publisher = trim((string) ($data['publisher'] ?? ''));
        $biblio->place_of_publication = trim((string) ($data['place_of_publication'] ?? ''));
        $biblio->publish_year = trim((string) ($data['publish_year'] ?? ''));
        $biblio->language = trim((string) ($data['language'] ?? ''));
        $biblio->ddc = trim((string) ($data['ddc'] ?? ''));
        $biblio->call_number = trim((string) ($data['call_number'] ?? ''));
        $biblio->isbn = trim((string) ($data['isbn'] ?? ''));
        $biblio->issn = trim((string) ($data['issn'] ?? ''));
        $biblio->physical_desc = trim((string) ($data['physical_desc'] ?? ''));
        $biblio->extent = trim((string) ($data['extent'] ?? ''));
        $biblio->material_type = trim((string) ($data['material_type'] ?? ''));
        $biblio->media_type = trim((string) ($data['media_type'] ?? ''));

        $authorsText = trim((string) ($data['authors_text'] ?? ''));
        $subjectsText = trim((string) ($data['subjects_text'] ?? ''));
        $authors = collect();
        if ($authorsText !== '') {
            foreach (preg_split('/[;,]+/', $authorsText) as $name) {
                $name = trim((string) $name);
                if ($name === '') continue;
                $a = new Author(['name' => $name]);
                $a->pivot = (object) ['role' => null];
                $authors->push($a);
            }
        }
        $subjects = collect();
        if ($subjectsText !== '') {
            foreach (preg_split('/[;,]+/', $subjectsText) as $term) {
                $term = trim((string) $term);
                if ($term === '') continue;
                $s = new Subject(['term' => $term]);
                $subjects->push($s);
            }
        }
        $biblio->setRelation('authors', $authors);
        $biblio->setRelation('subjects', $subjects);
        $biblio->setRelation('identifiers', collect());

        $messages = $validationService->validateForExport($biblio);
        $errors = [];
        $warnings = [];
        foreach ($messages as $msg) {
            if (str_starts_with($msg, 'WARN:')) {
                $warnings[] = trim(substr($msg, 5));
            } else {
                $errors[] = $msg;
            }
        }

        return response()->json([
            'ok' => true,
            'errors' => $errors,
            'warnings' => $warnings,
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $data = $request->validate([
            'ids' => ['required'],
            'material_type' => ['nullable', 'string', 'max:32'],
            'media_type' => ['nullable', 'string', 'max:32'],
            'language' => ['nullable', 'string', 'max:10'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'ddc' => ['nullable', 'string', 'max:32'],
            'tags_text' => ['nullable', 'string', 'max:255'],
            'item_status' => ['nullable', 'string', 'max:32'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'shelf_id' => ['nullable', 'integer', 'min:1'],
            'location_note' => ['nullable', 'string', 'max:255'],
        ]);

        $ids = $data['ids'];
        if (is_string($ids)) {
            $ids = array_map('intval', array_filter(explode(',', $ids)));
        } elseif (is_array($ids)) {
            $ids = array_map('intval', $ids);
        } else {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));

        if (empty($ids)) {
            return back()->with('error', 'Tidak ada koleksi yang dipilih.');
        }

        $updates = [];
        foreach (['material_type', 'media_type', 'language', 'publisher', 'ddc'] as $field) {
            $val = isset($data[$field]) ? trim((string) $data[$field]) : '';
            if ($val !== '') {
                $updates[$field] = $field === 'language' ? strtolower($val) : $val;
            }
        }

        $hasItemUpdate = false;
        $itemUpdate = [];
        $itemStatus = trim((string) ($data['item_status'] ?? ''));
        if ($itemStatus !== '') {
            $itemUpdate['status'] = $itemStatus;
            $hasItemUpdate = true;
        }
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : 0;
        if ($branchId > 0) {
            $itemUpdate['branch_id'] = $branchId;
            $hasItemUpdate = true;
        }
        $shelfId = isset($data['shelf_id']) ? (int) $data['shelf_id'] : 0;
        if ($shelfId > 0) {
            $itemUpdate['shelf_id'] = $shelfId;
            $hasItemUpdate = true;
        }
        $locNote = trim((string) ($data['location_note'] ?? ''));
        if ($locNote !== '') {
            $itemUpdate['location_note'] = $locNote;
            $hasItemUpdate = true;
        }

        $tagsText = trim((string) ($data['tags_text'] ?? ''));
        $hasTagUpdate = $tagsText !== '';

        if (empty($updates) && !$hasItemUpdate && !$hasTagUpdate) {
            return back()->with('error', 'Pilih minimal satu field untuk diperbarui.');
        }

        $institutionId = $this->currentInstitutionId();
        $batchKey = (string) Str::uuid();
        $before = [
            'biblio' => [],
            'items' => [],
            'tags' => [],
        ];

        $needsBiblioSnapshot = !empty($updates) || $hasTagUpdate;
        if ($needsBiblioSnapshot) {
            $biblioFields = ['id'];
            foreach (array_keys($updates) as $field) {
                if ($field !== 'updated_at') {
                    $biblioFields[] = $field;
                }
            }
            $biblioQuery = Biblio::query()
                ->where('institution_id', $institutionId)
                ->whereIn('id', $ids);
            if ($hasTagUpdate) {
                $biblioQuery->with('tags:id');
            }
            $biblioRows = $biblioQuery->get(array_unique($biblioFields));
            $before['biblio'] = $biblioRows->map(function ($row) use ($biblioFields) {
                $data = ['id' => (int) $row->id];
                foreach ($biblioFields as $field) {
                    if ($field === 'id') continue;
                    $data[$field] = $row->{$field};
                }
                return $data;
            })->values()->all();
            if ($hasTagUpdate) {
                $before['tags'] = $biblioRows->mapWithKeys(function ($row) {
                    return [(int) $row->id => $row->tags->pluck('id')->values()->all()];
                })->all();
            }
        }

        if ($hasItemUpdate) {
            $itemRows = Item::query()
                ->where('institution_id', $institutionId)
                ->whereIn('biblio_id', $ids)
                ->get(['id', 'biblio_id', 'status', 'branch_id', 'shelf_id', 'location_note']);
            $before['items'] = $itemRows->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'biblio_id' => (int) $row->biblio_id,
                    'status' => $row->status,
                    'branch_id' => $row->branch_id,
                    'shelf_id' => $row->shelf_id,
                    'location_note' => $row->location_note,
                ];
            })->values()->all();
        }

        $updates['updated_at'] = now();
        $count = 0;
        if (!empty($updates)) {
            $count = Biblio::query()
                ->where('institution_id', $institutionId)
                ->whereIn('id', $ids)
                ->update($updates);
        }

        if ($hasItemUpdate) {
            $itemUpdate['updated_at'] = now();
            Item::query()
                ->where('institution_id', $institutionId)
                ->whereIn('biblio_id', $ids)
                ->update($itemUpdate);
        }

        if ($hasTagUpdate) {
            $tagNames = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,]+/', $tagsText)))));
            if (!empty($tagNames)) {
                $tags = [];
                foreach ($tagNames as $name) {
                    $norm = strtolower(preg_replace('/\s+/', ' ', $name));
                    $tag = Tag::firstOrCreate(
                        ['normalized_name' => $norm],
                        ['name' => $name, 'normalized_name' => $norm]
                    );
                    $tags[$tag->id] = ['sort_order' => 0];
                }
                Biblio::query()
                    ->where('institution_id', $institutionId)
                    ->whereIn('id', $ids)
                    ->get()
                    ->each(function ($b) use ($tags) {
                        $b->tags()->sync($tags);
                    });
            }
        }

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'bulk_update',
                'format' => 'biblio',
                'status' => 'success',
                'meta' => [
                    'count' => (int) count($ids),
                    'ids' => $ids,
                    'updates' => $updates,
                    'item_updates' => $itemUpdate,
                    'tags' => $tagsText,
                    'batch_key' => $batchKey,
                    'institution_id' => $institutionId,
                    'before' => $before,
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        return back()->with('success', 'Batch update berhasil untuk ' . (int) max($count, 0) . ' koleksi.');
    }

    public function bulkPreview(Request $request)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $data = $request->validate([
            'ids' => ['required'],
            'material_type' => ['nullable', 'string', 'max:32'],
            'media_type' => ['nullable', 'string', 'max:32'],
            'language' => ['nullable', 'string', 'max:10'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'ddc' => ['nullable', 'string', 'max:32'],
            'tags_text' => ['nullable', 'string', 'max:255'],
            'item_status' => ['nullable', 'string', 'max:32'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'shelf_id' => ['nullable', 'integer', 'min:1'],
            'location_note' => ['nullable', 'string', 'max:255'],
        ]);

        $ids = $data['ids'];
        if (is_string($ids)) {
            $ids = array_map('intval', array_filter(explode(',', $ids)));
        } elseif (is_array($ids)) {
            $ids = array_map('intval', $ids);
        } else {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));

        if (empty($ids)) {
            return response()->json(['message' => 'Tidak ada koleksi yang dipilih.'], 422);
        }

        $updates = [];
        foreach (['material_type', 'media_type', 'language', 'publisher', 'ddc'] as $field) {
            $val = isset($data[$field]) ? trim((string) $data[$field]) : '';
            if ($val !== '') {
                $updates[$field] = $field === 'language' ? strtolower($val) : $val;
            }
        }

        $hasItemUpdate = false;
        $itemUpdate = [];
        $itemStatus = trim((string) ($data['item_status'] ?? ''));
        if ($itemStatus !== '') {
            $itemUpdate['status'] = $itemStatus;
            $hasItemUpdate = true;
        }
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : 0;
        if ($branchId > 0) {
            $itemUpdate['branch_id'] = $branchId;
            $hasItemUpdate = true;
        }
        $shelfId = isset($data['shelf_id']) ? (int) $data['shelf_id'] : 0;
        if ($shelfId > 0) {
            $itemUpdate['shelf_id'] = $shelfId;
            $hasItemUpdate = true;
        }
        $locNote = trim((string) ($data['location_note'] ?? ''));
        if ($locNote !== '') {
            $itemUpdate['location_note'] = $locNote;
            $hasItemUpdate = true;
        }

        $tagsText = trim((string) ($data['tags_text'] ?? ''));
        $hasTagUpdate = $tagsText !== '';

        if (empty($updates) && !$hasItemUpdate && !$hasTagUpdate) {
            return response()->json(['message' => 'Pilih minimal satu field untuk diperbarui.'], 422);
        }

        $fields = [];
        if (array_key_exists('material_type', $updates)) $fields[] = 'Jenis Konten';
        if (array_key_exists('media_type', $updates)) $fields[] = 'Media';
        if (array_key_exists('language', $updates)) $fields[] = 'Bahasa';
        if (array_key_exists('publisher', $updates)) $fields[] = 'Penerbit';
        if (array_key_exists('ddc', $updates)) $fields[] = 'DDC';
        if ($hasTagUpdate) $fields[] = 'Tag';
        if ($hasItemUpdate) {
            if (array_key_exists('status', $itemUpdate)) $fields[] = 'Status Eksemplar';
            if (array_key_exists('branch_id', $itemUpdate)) $fields[] = 'Cabang';
            if (array_key_exists('shelf_id', $itemUpdate)) $fields[] = 'Rak';
            if (array_key_exists('location_note', $itemUpdate)) $fields[] = 'Catatan lokasi';
        }

        $institutionId = $this->currentInstitutionId();
        $query = Biblio::query()
            ->where('institution_id', $institutionId)
            ->whereIn('id', $ids);
        $count = (int) $query->count();
        $items = $query
            ->with('authors:id,name')
            ->orderBy('title')
            ->limit(10)
            ->get()
            ->map(function ($b) {
                return [
                    'id' => (int) $b->id,
                    'title' => (string) ($b->display_title ?? $b->title ?? ''),
                    'authors' => $b->authors?->pluck('name')->filter()->take(2)->implode(', ') ?? '',
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'count' => $count,
            'fields' => $fields,
            'items' => $items,
        ]);
    }

    public function bulkUndo(Request $request)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $institutionId = $this->currentInstitutionId();
        $last = AuditLog::query()
            ->where('user_id', auth()->id())
            ->where('action', 'bulk_update')
            ->orderByDesc('id')
            ->first();

        if (!$last) {
            return back()->with('error', 'Tidak ada batch update untuk dibatalkan.');
        }

        $meta = is_array($last->meta) ? $last->meta : [];
        if (!empty($meta['institution_id']) && (int) $meta['institution_id'] !== $institutionId) {
            return back()->with('error', 'Batch terakhir bukan untuk institusi ini.');
        }
        if (!empty($meta['undone_at'])) {
            return back()->with('error', 'Batch terakhir sudah dibatalkan.');
        }

        $before = $meta['before'] ?? [];
        if (empty($before)) {
            return back()->with('error', 'Data undo tidak tersedia untuk batch ini.');
        }

        DB::transaction(function () use ($institutionId, $before, $last, $meta) {
            $now = now();
            $biblioRows = $before['biblio'] ?? [];
            foreach ($biblioRows as $row) {
                if (empty($row['id'])) continue;
                $update = $row;
                unset($update['id']);
                if (!empty($update)) {
                    $update['updated_at'] = $now;
                    Biblio::query()
                        ->where('institution_id', $institutionId)
                        ->where('id', (int) $row['id'])
                        ->update($update);
                }
            }

            $itemRows = $before['items'] ?? [];
            foreach ($itemRows as $row) {
                if (empty($row['id'])) continue;
                $update = [
                    'status' => $row['status'] ?? null,
                    'branch_id' => $row['branch_id'] ?? null,
                    'shelf_id' => $row['shelf_id'] ?? null,
                    'location_note' => $row['location_note'] ?? null,
                    'updated_at' => $now,
                ];
                Item::query()
                    ->where('institution_id', $institutionId)
                    ->where('id', (int) $row['id'])
                    ->update($update);
            }

            $tagMap = $before['tags'] ?? [];
            foreach ($tagMap as $biblioId => $tagIds) {
                $biblio = Biblio::query()
                    ->where('institution_id', $institutionId)
                    ->find((int) $biblioId);
                if ($biblio) {
                    $biblio->tags()->sync($tagIds ?: []);
                }
            }

            $meta['undone_at'] = $now->toDateTimeString();
            $last->meta = $meta;
            $last->save();

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'bulk_undo',
                'format' => 'biblio',
                'status' => 'success',
                'meta' => [
                    'batch_key' => $meta['batch_key'] ?? null,
                    'institution_id' => $institutionId,
                ],
            ]);
        });

        return back()->with('success', 'Undo batch terakhir berhasil.');
    }

    public function destroy($id)
    {
        abort_unless(auth()->check() && $this->canManageCatalog(), 403);

        $institutionId = $this->currentInstitutionId();

        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $auditMeta = [
            'biblio_id' => (int) $biblio->id,
            'institution_id' => $institutionId,
            'title' => (string) ($biblio->title ?? ''),
            'publisher' => (string) ($biblio->publisher ?? ''),
            'isbn' => (string) ($biblio->isbn ?? ''),
            'deleted_at' => now()->toIso8601String(),
            'ip' => (string) request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ];

        if ($biblio->items()->exists()) {
            return redirect()
                ->back()
                ->with('error', 'Tidak bisa menghapus bibliografi yang masih memiliki eksemplar. Hapus/kelola eksemplar dulu.');
        }

        // Note:… hapus cover jika ada
        try {
            if (!empty($biblio->cover_path)) {
                Storage::disk('public')->delete($biblio->cover_path);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $biblio->authors()->detach();
        $biblio->subjects()->detach();
        $biblio->tags()->detach();

        $biblio->delete();

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'delete',
                'format' => 'biblio',
                'status' => 'success',
                'meta' => $auditMeta,
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        return redirect()
            ->route('katalog.index')
            ->with('success', 'Bibliografi berhasil dihapus.');
    }

    private function generateUniqueCode(string $prefix, string $column): string
    {
        $date = now()->format('Ymd');
        for ($tries = 0; $tries < 20; $tries++) {
            $code = $prefix . '-' . $date . '-' . Str::upper(Str::random(6));
            $exists = Item::query()->where($column, $code)->exists();
            if (!$exists) return $code;
        }

        return $prefix . '-' . $date . '-' . Str::upper(Str::random(10));
    }

    public function apiSearch(Request $request)
    {
        $institutionId = $this->currentInstitutionId();
        $q = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 12);
        $perPage = max(1, min(50, $perPage));
        $sort = strtolower((string) $request->query('sort', 'relevant'));
        $onlyAvailable = (bool) $request->boolean('only_available');

        $query = Biblio::query()
            ->where('institution_id', $institutionId)
            ->with(['authors:id,name', 'subjects:id,term,name', 'tags:id,name'])
            ->withCount(['items', 'availableItems as available_items_count']);

        if ($q !== '') {
            $qLike = '%' . $q . '%';
            $query->where(function ($w) use ($qLike) {
                $w->where('title', 'like', $qLike)
                    ->orWhere('subtitle', 'like', $qLike)
                    ->orWhere('isbn', 'like', $qLike)
                    ->orWhere('issn', 'like', $qLike)
                    ->orWhere('publisher', 'like', $qLike)
                    ->orWhere('call_number', 'like', $qLike)
                    ->orWhereHas('authors', fn ($a) => $a->where('name', 'like', $qLike))
                    ->orWhereHas('subjects', fn ($s) => $s->where(function ($sq) use ($qLike) {
                        $sq->where('term', 'like', $qLike)->orWhere('name', 'like', $qLike);
                    }))
                    ->orWhereHas('tags', fn ($t) => $t->where('name', 'like', $qLike));
            });
        }

        if ($onlyAvailable) {
            $query->whereHas('availableItems');
        }

        if ($sort === 'latest') {
            $query->orderByDesc('updated_at');
        } elseif ($sort === 'title') {
            $query->orderBy('title');
        } elseif ($sort === 'available') {
            $query->orderByDesc('available_items_count')->orderBy('title');
        } else {
            $query->orderByDesc('available_items_count')->orderBy('title');
        }

        $results = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'ok' => true,
            'query' => [
                'q' => $q,
                'sort' => $sort,
                'only_available' => $onlyAvailable,
            ],
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
            ],
            'data' => collect($results->items())->map(fn ($b) => $this->mapBiblioApiRow($b))->values(),
        ]);
    }

    public function apiShow($id)
    {
        $institutionId = $this->currentInstitutionId();

        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->with(['authors:id,name', 'subjects:id,term,name', 'tags:id,name'])
            ->withCount(['items', 'availableItems as available_items_count'])
            ->findOrFail($id);

        return response()->json([
            'ok' => true,
            'data' => $this->mapBiblioApiRow($biblio),
        ]);
    }

    private function mapBiblioApiRow(Biblio $biblio): array
    {
        return [
            'id' => (int) $biblio->id,
            'title' => (string) ($biblio->display_title ?? $biblio->title ?? ''),
            'subtitle' => (string) ($biblio->subtitle ?? ''),
            'isbn' => (string) ($biblio->isbn ?? ''),
            'issn' => (string) ($biblio->issn ?? ''),
            'publisher' => (string) ($biblio->publisher ?? ''),
            'publish_year' => (string) ($biblio->publish_year ?? ''),
            'language' => (string) ($biblio->language ?? ''),
            'material_type' => (string) ($biblio->material_type ?? ''),
            'media_type' => (string) ($biblio->media_type ?? ''),
            'call_number' => (string) ($biblio->call_number ?? ''),
            'ddc' => (string) ($biblio->ddc ?? ''),
            'cover_url' => !empty($biblio->cover_path) ? asset('storage/' . ltrim((string) $biblio->cover_path, '/')) : null,
            'items_count' => (int) ($biblio->items_count ?? 0),
            'available_items_count' => (int) ($biblio->available_items_count ?? 0),
            'authors' => $biblio->authors->pluck('name')->filter()->values()->all(),
            'subjects' => $biblio->subjects
                ->map(fn ($s) => trim((string) ($s->term ?? $s->name ?? '')))
                ->filter()
                ->values()
                ->all(),
            'tags' => $biblio->tags->pluck('name')->filter()->values()->all(),
            'updated_at' => optional($biblio->updated_at)->toIso8601String(),
        ];
    }

    public function show($id)
    {
        $institutionId = $this->currentInstitutionId();
        $isPublic = request()->routeIs('opac.*');

        $biblio = Biblio::query()
            ->where('institution_id', $institutionId)
            ->with(['authors', 'subjects', 'tags', 'attachments'])
            ->withCount([
                'items',
                'availableItems as available_items_count'
            ])
            ->findOrFail($id);

        $branchId = (int) (auth()->user()->branch_id ?? 0);
        $branchId = $branchId > 0 ? $branchId : null;

        app(BiblioInteractionService::class)
            ->recordClick((int) $biblio->id, $institutionId, auth()->id(), $branchId);

        // Note:… Items + lokasi (tanpa N+1): LEFT JOIN branches & shelves
        // Tetap aman multi-institusi: join juga dibatasi institution_id
        $itemsQuery = Item::query()
            ->where('items.institution_id', $institutionId)
            ->where('items.biblio_id', $biblio->id)
            ->select('items.*');

        if (Schema::hasTable('branches')) {
            $itemsQuery->leftJoin('branches as br', function ($j) use ($institutionId) {
                $j->on('br.id', '=', 'items.branch_id')
                  ->where('br.institution_id', '=', $institutionId);
            })->addSelect([
                DB::raw('br.name as branch_name'),
            ]);
        }

        if (Schema::hasTable('shelves')) {
            $itemsQuery->leftJoin('shelves as sh', function ($j) use ($institutionId) {
                $j->on('sh.id', '=', 'items.shelf_id')
                  ->where('sh.institution_id', '=', $institutionId);
            })->addSelect([
                // kompatibel untuk blade kamu (pakai rack_name / shelf_name)
                DB::raw('sh.name as rack_name'),
                DB::raw('sh.name as shelf_name'),
            ]);
        }

        $items = $itemsQuery
            ->orderByRaw("CASE items.status WHEN 'available' THEN 0 WHEN 'reserved' THEN 1 WHEN 'borrowed' THEN 2 WHEN 'maintenance' THEN 3 WHEN 'damaged' THEN 4 WHEN 'lost' THEN 5 ELSE 99 END")
            ->orderBy('items.barcode')
            ->paginate(20)
            ->withQueryString();

        $authorIds = $biblio->authors?->pluck('id')->filter()->values() ?? collect();
        $subjectIds = $biblio->subjects?->pluck('id')->filter()->values() ?? collect();

        $relatedBiblios = collect();
        if ($authorIds->isNotEmpty() || $subjectIds->isNotEmpty()) {
            $relatedQuery = Biblio::query()
                ->where('institution_id', $institutionId)
                ->where('id', '<>', $biblio->id)
                ->with(['authors:id,name', 'subjects:id,term,name'])
                ->withCount([
                    'items',
                    'availableItems as available_items_count'
                ])
                ->where(function ($q) use ($authorIds, $subjectIds) {
                    if ($authorIds->isNotEmpty()) {
                        $q->orWhereHas('authors', function ($a) use ($authorIds) {
                            $a->whereIn('authors.id', $authorIds);
                        });
                    }
                    if ($subjectIds->isNotEmpty()) {
                        $q->orWhereHas('subjects', function ($s) use ($subjectIds) {
                            $s->whereIn('subjects.id', $subjectIds);
                        });
                    }
                });

            $subjectIdList = $subjectIds->implode(',');
            $authorIdList = $authorIds->implode(',');

            if ($subjectIdList !== '' || $authorIdList !== '') {
                $scoreSql = "CASE";
                if ($subjectIdList !== '') {
                    $scoreSql .= " WHEN EXISTS (SELECT 1 FROM biblio_subject bs WHERE bs.biblio_id = biblio.id AND bs.subject_id IN ($subjectIdList)) THEN 2";
                }
                if ($authorIdList !== '') {
                    $scoreSql .= " WHEN EXISTS (SELECT 1 FROM biblio_author ba WHERE ba.biblio_id = biblio.id AND ba.author_id IN ($authorIdList)) THEN 1";
                }
                $scoreSql .= " ELSE 0 END";
                $relatedQuery->addSelect(DB::raw("$scoreSql as match_score"))->orderByDesc('match_score');
            }

            $relatedBiblios = $relatedQuery
                ->orderByDesc('available_items_count')
                ->orderByDesc('items_count')
                ->orderBy('title')
                ->limit(6)
                ->get();
        }

        $attachmentsQuery = $biblio->attachments()->orderByDesc('created_at');
        if (!$this->canManageCatalog()) {
            if (auth()->check()) {
                $attachmentsQuery->whereIn('visibility', ['public', 'member']);
            } else {
                $attachmentsQuery->where('visibility', 'public');
            }
        }
        $attachments = $attachmentsQuery->get();

        return view('katalog.show', [
            'biblio' => $biblio,
            'items' => $items,
            'relatedBiblios' => $relatedBiblios,
            'attachments' => $attachments,
            'indexRouteName' => $isPublic ? 'opac.index' : 'katalog.index',
            'showRouteName' => $isPublic ? 'opac.show' : 'katalog.show',
            'isPublic' => $isPublic,
            'canManage' => auth()->check() ? $this->canManageCatalog() : false,
        ]);
    }
}



