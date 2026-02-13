<?php

namespace App\Services\PustakawanDigital;

use App\Models\Biblio;
use App\Models\Item;
use App\Models\Author;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class SearchService extends BaseService
{
    /**
     * Search books in local catalog
     */
    public function searchLocal(string $query, int $limit = 10, int $offset = 0, string $sort = 'relevant', bool $availableOnly = false): array
    {
        $query = $this->normalizeAliases($query);
        $this->log('Searching local catalog', ['query' => $query, 'limit' => $limit]);
        
        $keywords = $this->extractKeywords($query);
        $normalizedQuery = $this->normalizeText($query);
        
        if (empty($keywords)) {
            return [
                'books' => [],
                'total' => 0,
                'query' => $query,
                'keywords' => [],
            ];
        }
        
        $cacheKey = 'search_local_' . md5($query . '_' . $limit . '_' . $offset);
        
        return $this->cache($cacheKey, function () use ($keywords, $normalizedQuery, $limit, $offset, $sort, $availableOnly) {
            $institutionId = $this->getCurrentInstitutionId();
            
            // Build query
            $query = Biblio::query()
                ->where('institution_id', $institutionId)
                ->with(['authors:id,name'])
                ->withCount([
                    'items',
                    'availableItems as available_items_count'
                ]);

            if ($sort === 'popular') {
                $query->withCount(['loans as loan_count']);
            }

            if ($availableOnly) {
                $query->whereHas('items', function ($q) {
                    $q->where('status', 'available');
                });
            }
            
            // Apply search conditions
            $this->applySearchConditions($query, $keywords, $normalizedQuery);
            
            // Get total count
            $total = $query->count();
            
            // Get results with limit
            if ($sort === 'latest') {
                $query->orderByDesc('publish_year');
            } elseif ($sort === 'popular') {
                $query->orderByDesc('loan_count')->orderByDesc('available_items_count');
            } else {
                $query->orderBy('title');
            }

            $biblios = $query
                ->skip($offset)
                ->take($limit)
                ->get();
            
            // Format results
            $books = $biblios->map(function ($biblio) {
                return $this->formatBookResult($biblio);
            })->toArray();
            
            return [
                'books' => $books,
                'total' => $total,
                'query' => $normalizedQuery,
                'keywords' => $keywords,
            ];
        }, 300); // Cache for 5 minutes
    }

    /**
     * Apply search conditions to query
     */
    private function applySearchConditions($query, array $keywords, string $normalizedQuery): void
    {
        // Jika hanya 1-2 kata, coba exact match dulu
        if (count($keywords) <= 2) {
            $query->where(function ($q) use ($keywords, $normalizedQuery) {
                // Exact title match (use where first to avoid an empty OR group)
                $q->where('title', 'like', "%{$normalizedQuery}%")
                  ->orWhere('normalized_title', 'like', "%{$normalizedQuery}%");
                
                // Individual keywords
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) >= 2) {
                        $q->orWhere('title', 'like', "%{$keyword}%")
                          ->orWhere('subtitle', 'like', "%{$keyword}%")
                          ->orWhere('isbn', 'like', "%{$keyword}%")
                          ->orWhere('publisher', 'like', "%{$keyword}%");
                    }
                }
                
                // Search in authors
                $q->orWhereHas('authors', function ($authorQuery) use ($keywords) {
                    $first = true;
                    foreach ($keywords as $keyword) {
                        if (strlen($keyword) >= 2) {
                            if ($first) {
                                $authorQuery->where('name', 'like', "%{$keyword}%");
                                $first = false;
                            } else {
                                $authorQuery->orWhere('name', 'like', "%{$keyword}%");
                            }
                        }
                    }
                });
                
                // Search in subjects
                $q->orWhereHas('subjects', function ($subjectQuery) use ($keywords) {
                    $first = true;
                    foreach ($keywords as $keyword) {
                        if (strlen($keyword) >= 2) {
                            if ($first) {
                                $subjectQuery->where('term', 'like', "%{$keyword}%")
                                             ->orWhere('name', 'like', "%{$keyword}%");
                                $first = false;
                            } else {
                                $subjectQuery->orWhere('term', 'like', "%{$keyword}%")
                                             ->orWhere('name', 'like', "%{$keyword}%");
                            }
                        }
                    }
                });
            });
        } else {
            // Untuk banyak keywords, gunakan full-text search jika available
            $this->applyFullTextSearch($query, $keywords);
        }
    }

    /**
     * Apply full-text search conditions
     */
    private function applyFullTextSearch($query, array $keywords): void
    {
        // Cek apakah database support full-text
        $dbDriver = config('database.default');
        
        if ($dbDriver === 'mysql' && $this->hasFullTextIndex()) {
            // MySQL full-text search
            $searchTerms = implode(' ', array_map(function ($kw) {
                return '+' . $kw . '*';
            }, $keywords));
            
            $query->whereRaw("MATCH(title, subtitle, notes, general_note) AGAINST(? IN BOOLEAN MODE)", [$searchTerms])
                  ->orderByRaw("MATCH(title, subtitle, notes, general_note) AGAINST(?) DESC", [$searchTerms]);
        } else {
            // Fallback: simple LIKE for each keyword
            $query->where(function ($q) use ($keywords) {
                $first = true;
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) >= 2) {
                        if ($first) {
                            $q->where('title', 'like', "%{$keyword}%")
                              ->orWhere('subtitle', 'like', "%{$keyword}%")
                              ->orWhere('notes', 'like', "%{$keyword}%")
                              ->orWhere('general_note', 'like', "%{$keyword}%");
                            $first = false;
                        } else {
                            $q->orWhere('title', 'like', "%{$keyword}%")
                              ->orWhere('subtitle', 'like', "%{$keyword}%")
                              ->orWhere('notes', 'like', "%{$keyword}%")
                              ->orWhere('general_note', 'like', "%{$keyword}%");
                        }
                    }
                }
            });
        }
    }

    /**
     * Check if full-text index exists on biblio table for required columns
     */
    private function hasFullTextIndex(): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `biblio` WHERE Index_type = 'FULLTEXT'");
            if (empty($indexes)) {
                return false;
            }

            $columns = [];
            foreach ($indexes as $idx) {
                if (!empty($idx->Column_name)) {
                    $columns[] = $idx->Column_name;
                }
            }

            $needed = ['title', 'subtitle', 'notes', 'general_note'];
            foreach ($needed as $col) {
                if (!in_array($col, $columns, true)) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Format book result untuk response
     */
    private function formatBookResult(Biblio $biblio): array
    {
        $summary = $this->extractBookSummary($biblio);
        $isbn = $biblio->isbn;
        $fallbackCover = $isbn ? "https://covers.openlibrary.org/b/isbn/{$isbn}-M.jpg" : null;
        
        return [
            'id' => $biblio->id,
            'title' => $biblio->title,
            'subtitle' => $biblio->subtitle ?? '',
            'author' => $biblio->authors->pluck('name')->join(', '),
            'authors' => $biblio->authors->pluck('name')->toArray(),
            'year' => $biblio->publish_year,
            'publisher' => $biblio->publisher,
            'isbn' => $isbn,
            'cover_url' => $biblio->cover_path ? asset('storage/' . $biblio->cover_path) : $fallbackCover,
            'summary' => $summary,
            'summary_short' => mb_substr($summary, 0, 150) . (strlen($summary) > 150 ? '...' : ''),
            'available' => ($biblio->available_items_count ?? 0) > 0,
            'available_count' => $biblio->available_items_count ?? 0,
            'total_count' => $biblio->items_count ?? 0,
            'url' => route('katalog.show', $biblio->id),
            'call_number' => $biblio->call_number,
            'ddc' => $biblio->ddc,
            'subjects' => $biblio->subjects->pluck('term')->toArray(),
        ];
    }

    /**
     * Extract summary from book data
     */
    private function extractBookSummary(Biblio $biblio): string
    {
        $sources = [
            $biblio->ai_summary,
            $biblio->notes,
            $biblio->general_note,
            $biblio->bibliography_note,
        ];
        
        foreach ($sources as $source) {
            if (!empty($source) && trim($source) !== '') {
                $summary = strip_tags($source);
                $summary = preg_replace('/\s+/', ' ', $summary);
                $summary = trim($summary);
                
                if (strlen($summary) > 50) {
                    return $summary;
                }
            }
        }
        
        return 'Ringkasan belum tersedia untuk buku ini.';
    }

    /**
     * Get book recommendations for user
     */
    public function getRecommendations(int $userId, string $query = '', int $limit = 5): array
    {
        $this->log('Getting recommendations', ['user_id' => $userId, 'query' => $query]);
        
        $cacheKey = 'recommendations_' . $userId . '_' . md5($query);
        
        return $this->cache($cacheKey, function () use ($userId, $query, $limit) {
            $institutionId = $this->getCurrentInstitutionId();
            
            // Jika ada query, cari berdasarkan query
            if (!empty($query)) {
                $keywords = $this->extractKeywords($query);
                $recommendations = $this->getRecommendationsByKeywords($keywords, $limit);
                $basedOn = "kata kunci \"{$query}\"";
            } else {
                // Jika tidak ada query, beri rekomendasi umum
                $recommendations = $this->getGeneralRecommendations($limit);
                $basedOn = "koleksi terpopuler";
            }
            
            // Format results
            $books = $recommendations->map(function ($biblio, $index) {
                $book = $this->formatBookResult($biblio);
                $book['reason'] = $this->getRecommendationReason($biblio, $index);
                $book['match_score'] = rand(70, 95) / 100; // Simulated match score
                return $book;
            })->toArray();
            
            return [
                'books' => $books,
                'based_on' => $basedOn,
                'total' => count($books),
            ];
        }, 600); // Cache for 10 minutes
    }

    /**
     * Get recommendations by keywords
     */
    private function getRecommendationsByKeywords(array $keywords, int $limit)
    {
        $institutionId = $this->getCurrentInstitutionId();
        
        return Biblio::query()
            ->where('institution_id', $institutionId)
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    if (strlen($keyword) >= 2) {
                        $query->orWhere('title', 'like', "%{$keyword}%")
                              ->orWhere('subtitle', 'like', "%{$keyword}%");
                    }
                }
            })
            ->with(['authors:id,name'])
            ->withCount([
                'items',
                'availableItems as available_items_count'
            ])
            ->whereHas('items', function ($q) {
                $q->where('status', 'available');
            })
            ->orderBy('available_items_count', 'desc')
            ->orderBy('publish_year', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get general recommendations (popular books)
     */
    private function getGeneralRecommendations(int $limit)
    {
        $institutionId = $this->getCurrentInstitutionId();
        
        return Biblio::query()
            ->where('institution_id', $institutionId)
            ->with(['authors:id,name'])
            ->withCount([
                'items',
                'availableItems as available_items_count',
                'loans as loan_count' // Asumsi ada relationship loans
            ])
            ->whereHas('items', function ($q) {
                $q->where('status', 'available');
            })
            ->orderBy('loan_count', 'desc')
            ->orderBy('available_items_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recommendation reason
     */
    private function getRecommendationReason(Biblio $biblio, int $index): string
    {
        $reasons = [
            "Buku populer di kategori terkait",
            "Sering dipinjam oleh anggota lain",
            "Edisi terbaru dan terlengkap",
            "Cocok untuk pemula dan mahir",
            "Memiliki rating tinggi dari pembaca",
            "Referensi utama di bidangnya",
            "Buku wajib untuk topik ini",
            "Edisi khusus dengan konten eksklusif",
        ];
        
        $reasonIndex = $index % count($reasons);
        return $reasons[$reasonIndex];
    }

    /**
     * Get search suggestions for failed searches
     */
    public function getSearchSuggestions(string $failedQuery): array
    {
        $failedQuery = $this->normalizeAliases($failedQuery);
        $keywords = $this->extractKeywords($failedQuery);
        
        if (empty($keywords)) {
            return [
                'Coba kata kunci lebih umum',
                'Gunakan bahasa Indonesia yang baku',
                'Cari berdasarkan kategori',
                'Lihat koleksi terpopuler',
            ];
        }
        
        $suggestions = [];
        
        // Tambahkan saran typo (fuzzy)
        $fuzzySuggestions = $this->getFuzzySuggestions($keywords);
        foreach ($fuzzySuggestions as $s) {
            $suggestions[] = "Mungkin maksud Anda: \"{$s}\"";
        }

        // Suggestion berdasarkan keywords
        foreach ($keywords as $keyword) {
            if (strlen($keyword) >= 3) {
                $suggestions[] = "Coba cari: \"{$keyword}\" saja";
                
                // Tambahkan sinonim umum
                $synonyms = $this->getSynonyms($keyword);
                foreach ($synonyms as $synonym) {
                    $suggestions[] = "Atau: \"{$synonym}\"";
                }
            }
        }
        
        // Tambahkan suggestion umum
        $generalSuggestions = [
            'Periksa ejaan kata kunci',
            'Gunakan istilah yang lebih umum',
            'Cari berdasarkan nama penulis',
            'Lihat kategori buku populer',
            'Jelajahi koleksi baru',
        ];
        
        $suggestions = array_merge($suggestions, $generalSuggestions);
        
        return array_slice(array_unique($suggestions), 0, 5);
    }

    /**
     * Fuzzy suggestions for typos (simple levenshtein)
     */
    private function getFuzzySuggestions(array $keywords, int $limit = 2): array
    {
        $candidates = collect();
        try {
            $institutionId = $this->getCurrentInstitutionId();

            // Ambil kandidat judul populer (ringan)
            $titles = Biblio::query()
                ->where('institution_id', $institutionId)
                ->select('title')
                ->limit(200)
                ->pluck('title');

            $authors = Author::query()
                ->select('name')
                ->limit(200)
                ->pluck('name');

            $candidates = $titles->merge($authors)->filter()->unique();
        } catch (\Throwable $e) {
            return [];
        }

        $results = [];
        foreach ($keywords as $kw) {
            $kwNorm = $this->normalizeText($kw);
            if (mb_strlen($kwNorm) < 3) {
                continue;
            }

            $best = [];
            foreach ($candidates as $cand) {
                $candNorm = $this->normalizeText($cand);
                if ($candNorm === '') continue;

                $dist = levenshtein($kwNorm, $candNorm);
                if ($dist <= 3) {
                    $best[] = ['term' => $cand, 'dist' => $dist];
                }
            }

            usort($best, fn($a, $b) => $a['dist'] <=> $b['dist']);
            foreach (array_slice($best, 0, $limit) as $b) {
                $results[] = $b['term'];
            }
        }

        return array_slice(array_unique($results), 0, $limit);
    }

    /**
     * Get synonyms for a word (simple version)
     */
    private function getSynonyms(string $word): array
    {
        $synonymMap = [
            'pemrograman' => ['programming', 'coding', 'software development'],
            'novel' => ['fiksi', 'cerita', 'karya sastra'],
            'belajar' => ['pembelajaran', 'tutorial', 'panduan'],
            'pemula' => ['beginner', 'dasar', 'awal'],
            'lanjut' => ['advanced', 'mahir', 'expert'],
            'buku' => ['bacaan', 'literatur', 'referensi'],
            'anak' => ['children', 'kids', 'balita'],
            'bisnis' => ['business', 'usaha', 'perusahaan'],
            'ekonomi' => ['economic', 'keuangan', 'finansial'],
            'sejarah' => ['history', 'histori', 'masa lalu'],
        ];
        
        $wordLower = strtolower($word);
        return $synonymMap[$wordLower] ?? [];
    }

    /**
     * Normalize common aliases / nicknames
     */
    private function normalizeAliases(string $query): string
    {
        $q = $this->normalizeText($query);
        $map = [
            'cak nun' => 'emha ainun nadjib',
            'caknun' => 'emha ainun nadjib',
            'cak-nun' => 'emha ainun nadjib',
            'emha ainun' => 'emha ainun nadjib',
        ];

        foreach ($map as $needle => $replace) {
            if (str_contains($q, $needle)) {
                $q = str_replace($needle, $replace, $q);
            }
        }

        return $q;
    }

    /**
     * Check if a book title exists in local catalog
     */
    public function checkBookExists(string $title): bool
    {
        $normalizedTitle = $this->normalizeText($title);
        if ($normalizedTitle === '') {
            return false;
        }

        $institutionId = $this->getCurrentInstitutionId();

        return Biblio::query()
            ->where('institution_id', $institutionId)
            ->where(function ($q) use ($title, $normalizedTitle) {
                $q->where('title', 'like', "%{$title}%")
                  ->orWhere('normalized_title', 'like', "%{$normalizedTitle}%");
            })
            ->exists();
    }

    /**
     * Get current institution ID
     */
    private function getCurrentInstitutionId(): int
    {
        if (auth()->check()) {
            return (int) (auth()->user()->institution_id ?? 1);
        }
        
        return 1; // Default institution
    }

    /**
     * Search by category
     */
    public function searchByCategory(string $category, int $limit = 10): array
    {
        $this->log('Searching by category', ['category' => $category]);
        
        $institutionId = $this->getCurrentInstitutionId();
        
        $biblios = Biblio::query()
            ->where('institution_id', $institutionId)
            ->where(function ($query) use ($category) {
                $query->where('title', 'like', "%{$category}%")
                      ->orWhere('subtitle', 'like', "%{$category}%")
                      ->orWhereHas('subjects', function ($subjectQuery) use ($category) {
                          $subjectQuery->where('term', 'like', "%{$category}%")
                                      ->orWhere('name', 'like', "%{$category}%");
                      })
                      ->orWhereHas('tags', function ($tagQuery) use ($category) {
                          $tagQuery->where('name', 'like', "%{$category}%");
                      });
            })
            ->with(['authors:id,name'])
            ->withCount([
                'items',
                'availableItems as available_items_count'
            ])
            ->orderBy('available_items_count', 'desc')
            ->limit($limit)
            ->get();
        
        $books = $biblios->map(function ($biblio) {
            return $this->formatBookResult($biblio);
        })->toArray();
        
        return [
            'books' => $books,
            'total' => count($books),
            'category' => $category,
        ];
    }

    /**
     * Get popular categories
     */
    public function getPopularCategories(int $limit = 8): array
    {
        $categories = [
            ['name' => 'Pemrograman', 'count' => 45, 'icon' => '💻'],
            ['name' => 'Novel', 'count' => 120, 'icon' => '📚'],
            ['name' => 'Bisnis', 'count' => 65, 'icon' => '💼'],
            ['name' => 'Self-Improvement', 'count' => 38, 'icon' => '🚀'],
            ['name' => 'Anak & Remaja', 'count' => 85, 'icon' => '👦'],
            ['name' => 'Agama', 'count' => 72, 'icon' => '🕌'],
            ['name' => 'Sains', 'count' => 42, 'icon' => '🔬'],
            ['name' => 'Sejarah', 'count' => 58, 'icon' => '🏛️'],
            ['name' => 'Seni & Desain', 'count' => 31, 'icon' => '🎨'],
            ['name' => 'Kesehatan', 'count' => 47, 'icon' => '🏥'],
            ['name' => 'Travel', 'count' => 29, 'icon' => '✈️'],
            ['name' => 'Memasak', 'count' => 36, 'icon' => '🍳'],
        ];
        
        return array_slice($categories, 0, $limit);
    }
}
