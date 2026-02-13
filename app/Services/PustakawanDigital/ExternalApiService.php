<?php

namespace App\Services\PustakawanDigital;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ExternalApiService extends BaseService
{
    private $googleBooksApiKey;
    private $enableGoogleBooks;
    private $enableOpenLibrary;
    
    public function __construct()
    {
        $this->googleBooksApiKey = config('services.google_books.api_key', '');
        $this->enableGoogleBooks = config('services.external.google_books', true);
        $this->enableOpenLibrary = config('services.external.open_library', true);
        
        $this->log('ExternalApiService initialized', [
            'google_books_enabled' => $this->enableGoogleBooks && !empty($this->googleBooksApiKey),
            'open_library_enabled' => $this->enableOpenLibrary,
        ]);
    }
    
    /**
     * Search books in external APIs
     */
    public function searchBooks(string $query, int $limit = 5): array
    {
        $this->log('Searching external APIs', ['query' => $query]);
        
        $cacheKey = 'external_search_' . md5($query . '_' . $limit);
        
        return $this->cache($cacheKey, function () use ($query, $limit) {
            $results = [];
            
            // Try Google Books first
            if ($this->enableGoogleBooks && !empty($this->googleBooksApiKey)) {
                $googleResults = $this->searchGoogleBooks($query, $limit);
                if (!empty($googleResults['books'])) {
                    $results = $googleResults;
                    $results['source'] = 'Google Books';
                    $this->log('External search results', [
                        'source' => 'Google Books',
                        'query' => $query,
                        'found' => count($results['books']),
                        'total' => $results['total'] ?? 0,
                    ]);
                    return $results;
                }
            }
            
            // Try Open Library if Google Books fails or disabled
            if ($this->enableOpenLibrary) {
                $openLibraryResults = $this->searchOpenLibrary($query, $limit);
                if (!empty($openLibraryResults['books'])) {
                    $results = $openLibraryResults;
                    $results['source'] = 'Open Library';
                    $this->log('External search results', [
                        'source' => 'Open Library',
                        'query' => $query,
                        'found' => count($results['books']),
                        'total' => $results['total'] ?? 0,
                    ]);
                    return $results;
                }
            }
            
            // Return empty results if both fail
            return [
                'books' => [],
                'total' => 0,
                'source' => null,
                'query' => $query,
            ];
        }, 1800); // Cache for 30 minutes
    }
    
    /**
     * Search Google Books API
     */
    private function searchGoogleBooks(string $query, int $limit = 5): array
    {
        try {
            $params = [
                'q' => $query,
                'maxResults' => $limit,
                'printType' => 'books',
                'orderBy' => 'relevance',
            ];
            
            if (!empty($this->googleBooksApiKey)) {
                $params['key'] = $this->googleBooksApiKey;
            }
            
            $response = Http::timeout(15)
                ->retry(2, 100)
                ->get('https://www.googleapis.com/books/v1/volumes', $params);
            
            if (!$response->successful()) {
                $this->log('Google Books API failed', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                ], 'warning');
                return ['books' => [], 'total' => 0];
            }
            
            $data = $response->json();
            $items = $data['items'] ?? [];
            $total = $data['totalItems'] ?? 0;
            
            $books = [];
            foreach ($items as $item) {
                $book = $this->formatGoogleBookResult($item);
                if ($book) {
                    $books[] = $book;
                }
            }
            
            $this->log('Google Books search completed', [
                'query' => $query,
                'found' => count($books),
                'total_items' => $total,
            ]);
            
            return [
                'books' => $books,
                'total' => $total,
                'query' => $query,
            ];
            
        } catch (\Exception $e) {
            $this->log('Google Books search exception', [
                'error' => $e->getMessage(),
                'query' => $query,
            ], 'error');
            return ['books' => [], 'total' => 0];
        }
    }
    
    /**
     * Format Google Books API result
     */
    private function formatGoogleBookResult(array $item): ?array
    {
        $volumeInfo = $item['volumeInfo'] ?? [];
        
        // Required fields
        $title = $volumeInfo['title'] ?? '';
        if (empty($title)) {
            return null;
        }
        
        // Extract authors
        $authors = $volumeInfo['authors'] ?? [];
        if (is_array($authors)) {
            $authorString = implode(', ', array_slice($authors, 0, 3));
        } else {
            $authorString = (string) $authors;
        }
        
        // Extract description
        $description = $volumeInfo['description'] ?? '';
        $description = strip_tags($description);
        $description = preg_replace('/\s+/', ' ', $description);
        $description = trim($description);
        
        if (strlen($description) > 300) {
            $description = substr($description, 0, 297) . '...';
        }
        
        // Extract published date
        $publishedDate = $volumeInfo['publishedDate'] ?? '';
        $year = '';
        if (preg_match('/(\d{4})/', $publishedDate, $matches)) {
            $year = $matches[1];
        }
        
        // Extract cover image
        $coverUrl = null;
        $imageLinks = $volumeInfo['imageLinks'] ?? [];
        if (!empty($imageLinks['thumbnail'])) {
            $coverUrl = str_replace('http://', 'https://', $imageLinks['thumbnail']);
        } elseif (!empty($imageLinks['smallThumbnail'])) {
            $coverUrl = str_replace('http://', 'https://', $imageLinks['smallThumbnail']);
        }
        
        // Extract ISBN
        $isbn = '';
        $industryIdentifiers = $volumeInfo['industryIdentifiers'] ?? [];
        foreach ($industryIdentifiers as $identifier) {
            if ($identifier['type'] === 'ISBN_13') {
                $isbn = $identifier['identifier'];
                break;
            } elseif ($identifier['type'] === 'ISBN_10' && empty($isbn)) {
                $isbn = $identifier['identifier'];
            }
        }
        
        // Extract categories
        $categories = $volumeInfo['categories'] ?? [];
        $category = !empty($categories) ? $categories[0] : '';
        
        // Extract page count
        $pageCount = $volumeInfo['pageCount'] ?? 0;
        
        // Extract preview link
        $previewLink = $volumeInfo['previewLink'] ?? '';
        $infoLink = $volumeInfo['infoLink'] ?? $previewLink;
        
        return [
            'external_id' => $item['id'] ?? '',
            'title' => $title,
            'subtitle' => $volumeInfo['subtitle'] ?? '',
            'authors' => $authors,
            'author' => $authorString,
            'description' => $description,
            'year' => $year,
            'published_date' => $publishedDate,
            'publisher' => $volumeInfo['publisher'] ?? '',
            'isbn' => $isbn,
            'page_count' => $pageCount,
            'category' => $category,
            'cover_url' => $coverUrl,
            'preview_link' => $previewLink,
            'info_link' => $infoLink,
            'language' => $volumeInfo['language'] ?? 'id',
            'average_rating' => $volumeInfo['averageRating'] ?? 0,
            'ratings_count' => $volumeInfo['ratingsCount'] ?? 0,
            'source' => 'Google Books',
        ];
    }
    
    /**
     * Search Open Library API
     */
    private function searchOpenLibrary(string $query, int $limit = 5): array
    {
        try {
            $response = Http::timeout(15)
                ->retry(2, 100)
                ->get('https://openlibrary.org/search.json', [
                    'q' => $query,
                    'limit' => $limit,
                    'fields' => 'key,title,author_name,first_publish_year,cover_i,subject,isbn,number_of_pages_median,publisher',
                ]);
            
            if (!$response->successful()) {
                $this->log('Open Library API failed', [
                    'status' => $response->status(),
                    'error' => $response->body(),
                ], 'warning');
                return ['books' => [], 'total' => 0];
            }
            
            $data = $response->json();
            $docs = $data['docs'] ?? [];
            $total = $data['numFound'] ?? 0;
            
            $books = [];
            foreach ($docs as $doc) {
                $book = $this->formatOpenLibraryResult($doc);
                if ($book) {
                    $books[] = $book;
                }
            }
            
            $this->log('Open Library search completed', [
                'query' => $query,
                'found' => count($books),
                'total_found' => $total,
            ]);
            
            return [
                'books' => $books,
                'total' => $total,
                'query' => $query,
            ];
            
        } catch (\Exception $e) {
            $this->log('Open Library search exception', [
                'error' => $e->getMessage(),
                'query' => $query,
            ], 'error');
            return ['books' => [], 'total' => 0];
        }
    }
    
    /**
     * Format Open Library result
     */
    private function formatOpenLibraryResult(array $doc): ?array
    {
        $title = $doc['title'] ?? '';
        if (empty($title)) {
            return null;
        }
        
        // Extract authors
        $authors = $doc['author_name'] ?? [];
        $authorString = '';
        if (is_array($authors)) {
            $authorString = implode(', ', array_slice($authors, 0, 3));
        }
        
        // Extract subjects for description
        $subjects = $doc['subject'] ?? [];
        $description = '';
        if (is_array($subjects)) {
            $description = implode(', ', array_slice($subjects, 0, 5));
        } elseif (is_string($subjects)) {
            $description = $subjects;
        }
        
        if (strlen($description) > 300) {
            $description = substr($description, 0, 297) . '...';
        }
        
        // Extract cover image
        $coverUrl = null;
        $coverId = $doc['cover_i'] ?? null;
        if ($coverId) {
            $coverUrl = "https://covers.openlibrary.org/b/id/{$coverId}-M.jpg";
        }
        
        // Extract year
        $year = $doc['first_publish_year'] ?? '';
        
        // Extract ISBN
        $isbn = '';
        $isbns = $doc['isbn'] ?? [];
        if (is_array($isbns) && !empty($isbns)) {
            $isbn = $isbns[0];
        }
        
        // Extract page count
        $pageCount = $doc['number_of_pages_median'] ?? 0;
        
        // Build Open Library URL
        $key = $doc['key'] ?? '';
        $infoLink = '';
        if ($key) {
            $infoLink = "https://openlibrary.org{$key}";
        }
        
        // Extract publisher
        $publishers = $doc['publisher'] ?? [];
        $publisher = '';
        if (is_array($publishers) && !empty($publishers)) {
            $publisher = $publishers[0];
        } elseif (is_string($publishers)) {
            $publisher = $publishers;
        }
        
        return [
            'external_id' => $key,
            'title' => $title,
            'subtitle' => '',
            'authors' => $authors,
            'author' => $authorString,
            'description' => $description,
            'year' => $year,
            'published_date' => $year,
            'publisher' => $publisher,
            'isbn' => $isbn,
            'page_count' => $pageCount,
            'category' => !empty($subjects) ? (is_array($subjects) ? $subjects[0] : $subjects) : '',
            'cover_url' => $coverUrl,
            'preview_link' => $infoLink,
            'info_link' => $infoLink,
            'language' => 'id',
            'average_rating' => 0,
            'ratings_count' => 0,
            'source' => 'Open Library',
        ];
    }
    
    /**
     * Get book details by ISBN
     */
    public function getBookByIsbn(string $isbn): ?array
    {
        $this->log('Getting book by ISBN', ['isbn' => $isbn]);
        
        $cacheKey = 'book_isbn_' . md5($isbn);
        
        return $this->cache($cacheKey, function () use ($isbn) {
            // Try Google Books
            if ($this->enableGoogleBooks && !empty($this->googleBooksApiKey)) {
                $book = $this->getGoogleBookByIsbn($isbn);
                if ($book) {
                    return $book;
                }
            }
            
            // Try Open Library
            if ($this->enableOpenLibrary) {
                $book = $this->getOpenLibraryBookByIsbn($isbn);
                if ($book) {
                    return $book;
                }
            }
            
            return null;
        }, 3600); // Cache for 1 hour
    }
    
    /**
     * Get Google Book by ISBN
     */
    private function getGoogleBookByIsbn(string $isbn): ?array
    {
        try {
            $params = [
                'q' => 'isbn:' . $isbn,
                'maxResults' => 1,
                'key' => $this->googleBooksApiKey,
            ];
            
            $response = Http::timeout(10)
                ->get('https://www.googleapis.com/books/v1/volumes', $params);
            
            if ($response->successful()) {
                $data = $response->json();
                $items = $data['items'] ?? [];
                
                if (!empty($items)) {
                    return $this->formatGoogleBookResult($items[0]);
                }
            }
        } catch (\Exception $e) {
            $this->log('Google Books ISBN search exception', [
                'error' => $e->getMessage(),
                'isbn' => $isbn,
            ], 'warning');
        }
        
        return null;
    }
    
    /**
     * Get Open Library book by ISBN
     */
    private function getOpenLibraryBookByIsbn(string $isbn): ?array
    {
        try {
            $response = Http::timeout(10)
                ->get("https://openlibrary.org/api/books", [
                    'bibkeys' => 'ISBN:' . $isbn,
                    'format' => 'json',
                    'jscmd' => 'data',
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $key = 'ISBN:' . $isbn;
                
                if (isset($data[$key])) {
                    $bookData = $data[$key];
                    
                    return [
                        'external_id' => $bookData['key'] ?? '',
                        'title' => $bookData['title'] ?? '',
                        'authors' => $bookData['authors'] ?? [],
                        'author' => !empty($bookData['authors']) 
                            ? implode(', ', array_column($bookData['authors'], 'name'))
                            : '',
                        'description' => $bookData['subtitle'] ?? '',
                        'year' => $bookData['publish_date'] ?? '',
                        'publisher' => $bookData['publishers'][0]['name'] ?? '',
                        'isbn' => $isbn,
                        'cover_url' => $bookData['cover']['medium'] ?? $bookData['cover']['large'] ?? null,
                        'info_link' => $bookData['url'] ?? '',
                        'source' => 'Open Library',
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->log('Open Library ISBN search exception', [
                'error' => $e->getMessage(),
                'isbn' => $isbn,
            ], 'warning');
        }
        
        return null;
    }
    
    /**
     * Check if a book exists in external sources
     */
    public function checkBookAvailability(string $title, string $author = ''): array
    {
        $query = $title;
        if (!empty($author)) {
            $query .= ' ' . $author;
        }
        
        $results = $this->searchBooks($query, 3);
        
        $availability = [
            'exists' => !empty($results['books']),
            'sources' => [],
            'books' => [],
        ];
        
        if ($availability['exists']) {
            $availability['source'] = $results['source'];
            $availability['books'] = array_slice($results['books'], 0, 2);
            
            foreach ($results['books'] as $book) {
                $availability['sources'][] = [
                    'name' => $book['source'],
                    'has_preview' => !empty($book['preview_link']),
                    'has_cover' => !empty($book['cover_url']),
                ];
            }
            
            $availability['sources'] = array_unique($availability['sources'], SORT_REGULAR);
        }
        
        return $availability;
    }
    
    /**
     * Get book preview/summary
     */
    public function getBookPreview(string $title, string $author = ''): ?array
    {
        $query = $title . ' ' . $author;
        $results = $this->searchBooks($query, 1);
        
        if (!empty($results['books'])) {
            $book = $results['books'][0];
            
            return [
                'title' => $book['title'],
                'author' => $book['author'],
                'description' => $book['description'],
                'year' => $book['year'],
                'cover_url' => $book['cover_url'],
                'preview_link' => $book['preview_link'],
                'source' => $book['source'],
            ];
        }
        
        return null;
    }
}
