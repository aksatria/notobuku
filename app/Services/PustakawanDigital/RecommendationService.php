<?php

namespace App\Services\PustakawanDigital;

use App\Models\Biblio;
use App\Models\AiMessage;
use Illuminate\Support\Facades\DB;

class RecommendationService extends BaseService
{
    private $searchService;
    
    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }
    
    /**
     * Get personalized recommendations for user
     */
    public function getPersonalizedRecommendations(int $userId, int $limit = 5): array
    {
        $this->log('Getting personalized recommendations', ['user_id' => $userId]);
        
        $userInterests = $this->analyzeUserInterests($userId);
        
        if (empty($userInterests['keywords'])) {
            // Jika belum ada data, beri rekomendasi umum
            return $this->getGeneralRecommendations($limit);
        }
        
        // Cari buku berdasarkan minat user
        $recommendations = $this->getRecommendationsByInterests($userInterests, $limit);
        
        return [
            'books' => $recommendations,
            'based_on' => $userInterests['based_on'],
            'interests' => $userInterests['keywords'],
            'confidence' => $userInterests['confidence'],
        ];
    }
    
    /**
     * Analyze user interests from conversation history
     */
    private function analyzeUserInterests(int $userId): array
    {
        // Ambil pesan user dari conversation history
        $userMessages = AiMessage::whereHas('conversation', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->where('role', 'user')
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->pluck('content');
        
        if ($userMessages->isEmpty()) {
            return [
                'keywords' => [],
                'based_on' => 'Belum ada data percakapan',
                'confidence' => 0.1,
            ];
        }
        
        // Extract keywords dari semua pesan
        $allKeywords = [];
        foreach ($userMessages as $message) {
            $keywords = $this->extractKeywords($message);
            $allKeywords = array_merge($allKeywords, $keywords);
        }
        
        // Hitung frekuensi keyword
        $keywordCounts = array_count_values($allKeywords);
        arsort($keywordCounts);
        
        // Ambil top keywords
        $topKeywords = array_slice(array_keys($keywordCounts), 0, 5);
        $keywordScores = [];
        
        foreach ($topKeywords as $keyword) {
            $score = min(1.0, $keywordCounts[$keyword] / 5); // Normalize score
            $keywordScores[$keyword] = $score;
        }
        
        // Cek confidence level
        $confidence = $this->calculateConfidence($keywordCounts);
        $basedOn = $this->getInterestBasedOnMessage($userMessages->first());
        
        return [
            'keywords' => $keywordScores,
            'based_on' => $basedOn,
            'confidence' => $confidence,
        ];
    }
    
    /**
     * Calculate confidence level for recommendations
     */
    private function calculateConfidence(array $keywordCounts): float
    {
        $totalKeywords = array_sum($keywordCounts);
        $uniqueKeywords = count($keywordCounts);
        
        if ($totalKeywords < 3) {
            return 0.2; // Low confidence
        }
        
        if ($uniqueKeywords < 2) {
            return 0.4; // Medium-low confidence
        }
        
        // Higher confidence jika ada keyword yang sering muncul
        $maxCount = max($keywordCounts);
        $confidence = min(0.9, $maxCount / 5);
        
        return $confidence;
    }
    
    /**
     * Generate "based on" description
     */
    private function getInterestBasedOnMessage(string $lastMessage): string
    {
        $keywords = $this->extractKeywords($lastMessage);
        
        if (count($keywords) >= 3) {
            return 'topik yang Anda bahas sebelumnya';
        } elseif (count($keywords) >= 1) {
            return 'minat Anda dalam ' . implode(', ', array_slice($keywords, 0, 2));
        }
        
        return 'percakapan Anda sebelumnya';
    }
    
    /**
     * Get recommendations based on user interests
     */
    private function getRecommendationsByInterests(array $userInterests, int $limit): array
    {
        $keywords = array_keys($userInterests['keywords']);
        
        if (empty($keywords)) {
            return [];
        }
        
        $allResults = [];
        
        // Cari untuk setiap keyword
        foreach ($keywords as $keyword) {
            $results = $this->searchService->searchLocal($keyword, 3);
            
            foreach ($results['books'] as $book) {
                // Hitung relevance score
                $relevance = $this->calculateRelevance($book, $keywords, $userInterests['keywords']);
                
                // Tambahkan ke results jika belum ada
                $bookId = $book['id'] ?? $book['title'];
                if (!isset($allResults[$bookId])) {
                    $book['relevance_score'] = $relevance;
                    $book['match_reason'] = $this->getMatchReason($book, $keyword);
                    $allResults[$bookId] = $book;
                } else {
                    // Update score if higher
                    if ($relevance > $allResults[$bookId]['relevance_score']) {
                        $allResults[$bookId]['relevance_score'] = $relevance;
                        $allResults[$bookId]['match_reason'] = $this->getMatchReason($book, $keyword);
                    }
                }
            }
        }
        
        // Sort by relevance score
        usort($allResults, function ($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });
        
        return array_slice($allResults, 0, $limit);
    }
    
    /**
     * Calculate relevance score for a book
     */
    private function calculateRelevance(array $book, array $keywords, array $keywordScores): float
    {
        $score = 0;
        $matchedKeywords = [];
        
        // Check title
        $title = strtolower($book['title'] . ' ' . ($book['subtitle'] ?? ''));
        foreach ($keywords as $keyword) {
            if (str_contains($title, strtolower($keyword))) {
                $score += $keywordScores[$keyword] * 0.5;
                $matchedKeywords[] = $keyword;
            }
        }
        
        // Check subjects
        $subjects = array_map('strtolower', $book['subjects'] ?? []);
        foreach ($keywords as $keyword) {
            foreach ($subjects as $subject) {
                if (str_contains($subject, strtolower($keyword))) {
                    $score += $keywordScores[$keyword] * 0.3;
                    $matchedKeywords[] = $keyword;
                    break;
                }
            }
        }
        
        // Check author
        $author = strtolower($book['author'] ?? '');
        foreach ($keywords as $keyword) {
            if (str_contains($author, strtolower($keyword))) {
                $score += $keywordScores[$keyword] * 0.2;
                $matchedKeywords[] = $keyword;
            }
        }
        
        // Bonus for availability
        if ($book['available'] ?? false) {
            $score += 0.1;
        }
        
        // Normalize score
        return min(1.0, $score);
    }
    
    /**
     * Generate match reason for recommendation
     */
    private function getMatchReason(array $book, string $matchedKeyword): string
    {
        $title = strtolower($book['title']);
        $keyword = strtolower($matchedKeyword);
        
        if (str_contains($title, $keyword)) {
            return 'judul mengandung "' . $matchedKeyword . '"';
        }
        
        $subjects = array_map('strtolower', $book['subjects'] ?? []);
        foreach ($subjects as $subject) {
            if (str_contains($subject, $keyword)) {
                return 'topik "' . $matchedKeyword . '"';
            }
        }
        
        $author = strtolower($book['author'] ?? '');
        if (str_contains($author, $keyword)) {
            return 'penulis terkait "' . $matchedKeyword . '"';
        }
        
        return 'relevan dengan minat Anda';
    }
    
    /**
     * Get general recommendations (fallback)
     */
    private function getGeneralRecommendations(int $limit): array
    {
        $popularCategories = $this->searchService->getPopularCategories(3);
        
        $allRecommendations = [];
        
        foreach ($popularCategories as $category) {
            $results = $this->searchService->searchByCategory($category['name'], 2);
            
            foreach ($results['books'] as $book) {
                $book['relevance_score'] = 0.5;
                $book['match_reason'] = 'buku populer di kategori ' . $category['name'];
                $allRecommendations[] = $book;
            }
        }
        
        // Sort by availability
        usort($allRecommendations, function ($a, $b) {
            if ($a['available'] === $b['available']) {
                return $b['relevance_score'] <=> $a['relevance_score'];
            }
            return $b['available'] <=> $a['available'];
        });
        
        return array_slice($allRecommendations, 0, $limit);
    }
    
    /**
     * Get similar books based on a book
     */
    public function getSimilarBooks(int $bookId, int $limit = 4): array
    {
        $institutionId = $this->getCurrentInstitutionId();
        
        // Get the book details
        $book = Biblio::with(['subjects', 'authors'])
            ->where('institution_id', $institutionId)
            ->find($bookId);
        
        if (!$book) {
            return [];
        }
        
        // Get books with same subjects
        $subjectIds = $book->subjects->pluck('id')->toArray();
        $authorIds = $book->authors->pluck('id')->toArray();
        
        $similarBooks = Biblio::where('institution_id', $institutionId)
            ->where('id', '!=', $bookId)
            ->where(function ($query) use ($subjectIds, $authorIds) {
                // Same subjects
                if (!empty($subjectIds)) {
                    $query->orWhereHas('subjects', function ($q) use ($subjectIds) {
                        $q->whereIn('subjects.id', $subjectIds);
                    });
                }
                
                // Same author
                if (!empty($authorIds)) {
                    $query->orWhereHas('authors', function ($q) use ($authorIds) {
                        $q->whereIn('authors.id', $authorIds);
                    });
                }
                
                // Similar title (remove common words)
                $titleWords = $this->extractKeywords($book->title);
                foreach ($titleWords as $word) {
                    if (strlen($word) >= 4) {
                        $query->orWhere('title', 'like', "%{$word}%");
                    }
                }
            })
            ->with(['authors:id,name'])
            ->withCount([
                'items',
                'availableItems as available_items_count'
            ])
            ->orderBy('available_items_count', 'desc')
            ->limit($limit * 2) // Get extra to filter
            ->get();
        
        // Calculate similarity scores
        $scoredBooks = [];
        foreach ($similarBooks as $similarBook) {
            $score = $this->calculateBookSimilarity($book, $similarBook);
            
            if ($score > 0.2) { // Minimum similarity threshold
                $formattedBook = $this->searchService->formatBookResult($similarBook);
                $formattedBook['similarity_score'] = $score;
                $formattedBook['similarity_reason'] = $this->getSimilarityReason($book, $similarBook, $score);
                $scoredBooks[] = $formattedBook;
            }
        }
        
        // Sort by similarity score
        usort($scoredBooks, function ($a, $b) {
            return $b['similarity_score'] <=> $a['similarity_score'];
        });
        
        return array_slice($scoredBooks, 0, $limit);
    }
    
    /**
     * Calculate similarity between two books
     */
    private function calculateBookSimilarity(Biblio $book1, Biblio $book2): float
    {
        $score = 0;
        
        // Subject similarity (40%)
        $subjects1 = $book1->subjects->pluck('term')->map('strtolower')->toArray();
        $subjects2 = $book2->subjects->pluck('term')->map('strtolower')->toArray();
        $subjectOverlap = count(array_intersect($subjects1, $subjects2));
        $subjectUnion = count(array_unique(array_merge($subjects1, $subjects2)));
        
        if ($subjectUnion > 0) {
            $score += ($subjectOverlap / $subjectUnion) * 0.4;
        }
        
        // Author similarity (30%)
        $authors1 = $book1->authors->pluck('name')->map('strtolower')->toArray();
        $authors2 = $book2->authors->pluck('name')->map('strtolower')->toArray();
        $authorOverlap = count(array_intersect($authors1, $authors2));
        
        if ($authorOverlap > 0) {
            $score += 0.3;
        }
        
        // Title similarity (20%)
        $title1 = strtolower($book1->title);
        $title2 = strtolower($book2->title);
        $titleWords1 = explode(' ', $title1);
        $titleWords2 = explode(' ', $title2);
        $titleOverlap = count(array_intersect($titleWords1, $titleWords2));
        $titleUnion = count(array_unique(array_merge($titleWords1, $titleWords2)));
        
        if ($titleUnion > 0) {
            $score += ($titleOverlap / $titleUnion) * 0.2;
        }
        
        // Year proximity (10%)
        $year1 = $book1->publish_year;
        $year2 = $book2->publish_year;
        
        if ($year1 && $year2) {
            $yearDiff = abs($year1 - $year2);
            if ($yearDiff <= 5) {
                $score += 0.1;
            } elseif ($yearDiff <= 10) {
                $score += 0.05;
            }
        }
        
        return min(1.0, $score);
    }
    
    /**
     * Get similarity reason
     */
    private function getSimilarityReason(Biblio $book1, Biblio $book2, float $score): string
    {
        $reasons = [];
        
        // Check subjects
        $subjects1 = $book1->subjects->pluck('term')->map('strtolower')->toArray();
        $subjects2 = $book2->subjects->pluck('term')->map('strtolower')->toArray();
        $commonSubjects = array_intersect($subjects1, $subjects2);
        
        if (!empty($commonSubjects)) {
            $reasons[] = 'subjek sama: ' . implode(', ', array_slice($commonSubjects, 0, 2));
        }
        
        // Check authors
        $authors1 = $book1->authors->pluck('name')->toArray();
        $authors2 = $book2->authors->pluck('name')->toArray();
        $commonAuthors = array_intersect($authors1, $authors2);
        
        if (!empty($commonAuthors)) {
            $reasons[] = 'penulis sama: ' . implode(', ', $commonAuthors);
        }
        
        if (empty($reasons)) {
            return 'buku dengan topik serupa';
        }
        
        return implode(', ', $reasons);
    }
    
    /**
     * Get reading list suggestions
     */
    public function getReadingListSuggestions(array $userInterests, string $purpose = 'general'): array
    {
        $suggestions = [];
        
        switch ($purpose) {
            case 'learning':
                $suggestions = $this->getLearningPathSuggestions($userInterests);
                break;
            case 'entertainment':
                $suggestions = $this->getEntertainmentSuggestions($userInterests);
                break;
            case 'research':
                $suggestions = $this->getResearchSuggestions($userInterests);
                break;
            default:
                $suggestions = $this->getGeneralReadingList($userInterests);
        }
        
        return $suggestions;
    }
    
    /**
     * Get learning path suggestions
     */
    private function getLearningPathSuggestions(array $userInterests): array
    {
        $paths = [
            'pemrograman' => [
                'title' => '🚀 Jalur Belajar Pemrograman',
                'books' => [
                    ['level' => 'Pemula', 'suggestion' => 'Python Crash Course - Eric Matthes'],
                    ['level' => 'Menengah', 'suggestion' => 'Clean Code - Robert C. Martin'],
                    ['level' => 'Lanjut', 'suggestion' => 'Design Patterns - Gang of Four'],
                ]
            ],
            'data_science' => [
                'title' => '📊 Jalur Belajar Data Science',
                'books' => [
                    ['level' => 'Pemula', 'suggestion' => 'Python for Data Analysis - Wes McKinney'],
                    ['level' => 'Menengah', 'suggestion' => 'Hands-On Machine Learning - Aurélien Géron'],
                    ['level' => 'Lanjut', 'suggestion' => 'Deep Learning - Ian Goodfellow'],
                ]
            ],
            'bisnis' => [
                'title' => '💼 Jalur Belajar Bisnis',
                'books' => [
                    ['level' => 'Dasar', 'suggestion' => 'Business Model Generation - Alexander Osterwalder'],
                    ['level' => 'Strategi', 'suggestion' => 'Good Strategy Bad Strategy - Richard Rumelt'],
                    ['level' => 'Leadership', 'suggestion' => 'The 7 Habits of Highly Effective People - Stephen Covey'],
                ]
            ],
        ];
        
        // Find matching path based on interests
        foreach ($userInterests['keywords'] as $interest => $score) {
            if (isset($paths[$interest])) {
                return $paths[$interest];
            }
        }
        
        // Default path
        return $paths['pemrograman'];
    }
    
    /**
     * Get entertainment suggestions
     */
    private function getEntertainmentSuggestions(array $userInterests): array
    {
        $genres = [
            'romance' => ['Novel Romantis Terbaik', 'sweet romance', 'romance drama'],
            'mystery' => ['Misteri & Thriller Seru', 'crime mystery', 'psychological thriller'],
            'fantasy' => ['Fantasi & Petualangan', 'epic fantasy', 'urban fantasy'],
            'comedy' => ['Komedi & Humor', 'light comedy', 'satire'],
        ];
        
        $matchedGenre = 'general';
        foreach ($userInterests['keywords'] as $interest => $score) {
            if (isset($genres[$interest])) {
                $matchedGenre = $interest;
                break;
            }
        }
        
        return [
            'title' => $genres[$matchedGenre][0],
            'description' => 'Rekomendasi bacaan ' . $genres[$matchedGenre][1] . ' untuk hiburan',
            'suggestions' => [
                'Baca novel seri populer',
                'Coba genre ' . $genres[$matchedGenre][2],
                'Baca karya penulis pemenang penghargaan',
            ]
        ];
    }
    
    /**
     * Get research suggestions
     */
    private function getResearchSuggestions(array $userInterests): array
    {
        return [
            'title' => '📚 Panduan Riset & Akademik',
            'description' => 'Rekomendasi untuk penelitian dan penulisan akademik',
            'suggestions' => [
                'Mulai dengan literature review',
                'Gunakan buku metodologi penelitian',
                'Baca jurnal terkini di bidang Anda',
                'Gunakan buku referensi sebagai sumber primer',
                'Perhatikan daftar pustaka untuk referensi lanjutan',
            ]
        ];
    }
    
    /**
     * Get general reading list
     */
    private function getGeneralReadingList(array $userInterests): array
    {
        $topics = array_keys($userInterests['keywords']);
        
        return [
            'title' => '📖 Daftar Bacaan Personal',
            'description' => 'Disusun berdasarkan minat Anda dalam: ' . implode(', ', array_slice($topics, 0, 3)),
            'suggestions' => [
                'Baca 1 buku dasar tentang topik utama',
                'Eksplor 2-3 buku dengan perspektif berbeda',
                'Baca 1 buku terbaru di bidang tersebut',
                'Coba 1 buku di luar zona nyaman untuk perbandingan',
                'Buat catatan atau ringkasan untuk setiap buku',
            ]
        ];
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
}