<?php

namespace App\Services\PustakawanDigital;

use App\Models\Biblio;
use Illuminate\Support\Facades\Http;

class SummaryService extends BaseService
{
    private $ollamaUrl;
    private $ollamaModel;
    
    public function __construct()
    {
        $this->ollamaUrl = config('services.ollama.url', 'http://localhost:11434');
        $this->ollamaModel = config('services.ollama.model', 'qwen2.5:3b');
    }
    
    /**
     * Generate summary for a book
     */
    public function generateBookSummary(Biblio $book, string $format = 'short'): array
    {
        $this->log('Generating book summary', [
            'book_id' => $book->id,
            'title' => $book->title,
            'format' => $format,
        ]);
        
        // Check if we already have an AI summary
        if (!empty($book->ai_summary) && $format === 'short') {
            return [
                'summary' => $book->ai_summary,
                'source' => 'cached',
                'confidence' => 0.9,
            ];
        }
        
        // Generate summary using AI
        try {
            $prompt = $this->buildSummaryPrompt($book, $format);
            
            $response = Http::timeout(90)
                ->post($this->ollamaUrl . '/api/generate', [
                    'model' => $this->ollamaModel,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.3,
                        'num_predict' => 500,
                    ],
                ]);
            
            if ($response->successful()) {
                $aiSummary = $response->json('response', '');
                $cleanedSummary = $this->cleanSummary($aiSummary);
                
                // Save to database if short summary
                if ($format === 'short' && !empty($cleanedSummary)) {
                    $book->update(['ai_summary' => $cleanedSummary]);
                }
                
                $this->log('Summary generated successfully', [
                    'book_id' => $book->id,
                    'summary_length' => strlen($cleanedSummary),
                ]);
                
                return [
                    'summary' => $cleanedSummary,
                    'source' => 'ai_generated',
                    'confidence' => 0.8,
                ];
            }
        } catch (\Exception $e) {
            $this->log('Summary generation failed', [
                'error' => $e->getMessage(),
                'book_id' => $book->id,
            ], 'error');
        }
        
        // Fallback to existing summary
        return $this->getFallbackSummary($book);
    }
    
    /**
     * Build prompt for summary generation
     */
    private function buildSummaryPrompt(Biblio $book, string $format): string
    {
        $title = $book->title;
        $subtitle = $book->subtitle ?? '';
        $authors = $book->authors->pluck('name')->join(', ');
        $year = $book->publish_year ?? '';
        $publisher = $book->publisher ?? '';
        $subjects = $book->subjects->pluck('term')->join(', ');
        
        $existingNotes = $this->extractExistingNotes($book);
        
        $formatInstructions = '';
        if ($format === 'short') {
            $formatInstructions = "Buat ringkasan SINGKAT 2-3 kalimat yang mencakup inti buku.";
        } elseif ($format === 'detailed') {
            $formatInstructions = "Buat ringkasan DETAIL 5-7 kalimat yang mencakup:\n1. Tema utama\n2. Argumen/pesan penting\n3. Struktur buku (jika relevan)\n4. Nilai/manfaat membaca buku ini";
        } elseif ($format === 'bullet') {
            $formatInstructions = "Buat ringkasan dalam format POIN-POIN (5-7 poin) yang mencakup ide utama buku.";
        }
        
        $prompt = <<<PROMPT
        Anda adalah asisten perpustakaan AI. Buat ringkasan buku dalam bahasa Indonesia.

        **Informasi Buku:**
        - Judul: {$title}
        - Subjudul: {$subtitle}
        - Penulis: {$authors}
        - Tahun: {$year}
        - Penerbit: {$publisher}
        - Subjek: {$subjects}

        **Informasi Tambahan:**
        {$existingNotes}

        **Instruksi Format:**
        {$formatInstructions}

        **Aturan:**
        1. Gunakan bahasa Indonesia yang jelas dan formal
        2. Jangan menambahkan informasi yang tidak ada di sumber
        3. Fokus pada konten inti buku
        4. Jika informasi kurang, fokus pada apa yang tersedia
        5. Jangan gunakan markdown atau formatting khusus
        6. Jangan menyebutkan bahwa Anda adalah AI

        **Ringkasan:**
        PROMPT;

        return $prompt;
    }
    
    /**
     * Extract existing notes from book
     */
    private function extractExistingNotes(Biblio $book): string
    {
        $notes = [];
        
        $sources = [
            'Catatan Umum' => $book->notes,
            'Catatan Bibliografi' => $book->bibliography_note,
            'Catatan Lain' => $book->general_note,
        ];
        
        foreach ($sources as $label => $content) {
            if (!empty($content) && trim($content) !== '') {
                $cleanContent = strip_tags($content);
                $cleanContent = preg_replace('/\s+/', ' ', $cleanContent);
                $cleanContent = trim($cleanContent);
                
                if (strlen($cleanContent) > 50) {
                    $notes[] = "{$label}: {$cleanContent}";
                }
            }
        }
        
        if (empty($notes)) {
            return "Tidak ada catatan tambahan yang tersedia.";
        }
        
        return implode("\n", $notes);
    }
    
    /**
     * Clean AI-generated summary
     */
    private function cleanSummary(string $summary): string
    {
        // Remove prompt fragments
        $summary = preg_replace('/^(Ringkasan|Summary|Jawaban):\s*/i', '', $summary);
        $summary = preg_replace('/^(Assistant|AI|Pustakawan):\s*/i', '', $summary);
        
        // Remove excessive whitespace
        $summary = preg_replace('/\s+/', ' ', $summary);
        $summary = trim($summary);
        
        // Ensure proper ending
        if (!preg_match('/[.!?]$/', $summary)) {
            $summary .= '.';
        }
        
        return $summary;
    }
    
    /**
     * Get fallback summary
     */
    private function getFallbackSummary(Biblio $book): array
    {
        // Try existing AI summary
        if (!empty($book->ai_summary)) {
            return [
                'summary' => $book->ai_summary,
                'source' => 'existing_ai_summary',
                'confidence' => 0.7,
            ];
        }
        
        // Try notes
        $sources = [
            $book->notes,
            $book->general_note,
            $book->bibliography_note,
        ];
        
        foreach ($sources as $source) {
            if (!empty($source) && trim($source) !== '') {
                $summary = strip_tags($source);
                $summary = preg_replace('/\s+/', ' ', $summary);
                $summary = trim($summary);
                
                if (strlen($summary) > 100) {
                    return [
                        'summary' => $summary,
                        'source' => 'existing_notes',
                        'confidence' => 0.6,
                    ];
                }
            }
        }
        
        // Generate from metadata
        $metadataSummary = $this->generateMetadataSummary($book);
        
        return [
            'summary' => $metadataSummary,
            'source' => 'metadata_based',
            'confidence' => 0.4,
        ];
    }
    
    /**
     * Generate summary from metadata
     */
    private function generateMetadataSummary(Biblio $book): string
    {
        $title = $book->title;
        $authors = $book->authors->pluck('name')->join(', ');
        $year = $book->publish_year ?? '';
        $subjects = $book->subjects->pluck('term')->join(', ');
        
        $summary = "Buku \"{$title}\"";
        
        if (!empty($authors)) {
            $summary .= " karya {$authors}";
        }
        
        if (!empty($year)) {
            $summary .= " terbit tahun {$year}";
        }
        
        if (!empty($subjects)) {
            $summary .= ". Membahas topik: {$subjects}";
        } else {
            $summary .= ". Informasi detail tentang isi buku belum tersedia.";
        }
        
        return $summary;
    }
    
    /**
     * Generate comparative summary of multiple books
     */
    public function generateComparativeSummary(array $books): array
    {
        if (count($books) < 2) {
            return [
                'summary' => 'Perlu setidaknya 2 buku untuk membuat ringkasan komparatif.',
                'source' => 'insufficient_data',
                'confidence' => 0.1,
            ];
        }
        
        $this->log('Generating comparative summary', [
            'book_count' => count($books),
            'book_titles' => array_column($books, 'title'),
        ]);
        
        $bookDescriptions = [];
        foreach ($books as $index => $book) {
            $num = $index + 1;
            $authors = is_array($book['authors']) 
                ? implode(', ', $book['authors'])
                : ($book['author'] ?? 'Penulis tidak diketahui');
            
            $bookDescriptions[] = "Buku {$num}: \"{$book['title']}\" oleh {$authors}";
        }
        
        $booksText = implode("\n", $bookDescriptions);
        
        try {
            $prompt = <<<PROMPT
            Anda adalah ahli literatur. Bandingkan buku-buku berikut dalam 3-4 paragraf:

            {$booksText}

            **Instruksi:**
            1. Identifikasi tema atau topik umum
            2. Bandingkan pendekatan/perspektif masing-masing buku
            3. Saran: buku mana yang cocok untuk pembaca dengan kebutuhan berbeda
            4. Gunakan bahasa Indonesia yang jelas dan objektif
            5. Jangan menunjukkan bias pribadi

            **Perbandingan:**
            PROMPT;
            
            $response = Http::timeout(120)
                ->post($this->ollamaUrl . '/api/generate', [
                    'model' => $this->ollamaModel,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.4,
                        'num_predict' => 800,
                    ],
                ]);
            
            if ($response->successful()) {
                $comparison = $response->json('response', '');
                $cleaned = $this->cleanComparativeSummary($comparison);
                
                $this->log('Comparative summary generated', [
                    'length' => strlen($cleaned),
                ]);
                
                return [
                    'summary' => $cleaned,
                    'source' => 'ai_generated',
                    'confidence' => 0.7,
                ];
            }
        } catch (\Exception $e) {
            $this->log('Comparative summary failed', [
                'error' => $e->getMessage(),
            ], 'error');
        }
        
        // Fallback
        return [
            'summary' => $this->generateSimpleComparison($books),
            'source' => 'fallback_generated',
            'confidence' => 0.5,
        ];
    }
    
    /**
     * Clean comparative summary
     */
    private function cleanComparativeSummary(string $summary): string
    {
        // Remove prompt fragments
        $summary = preg_replace('/^(Perbandingan|Comparison|Analisis):\s*/i', '', $summary);
        $summary = preg_replace('/^(Assistant|AI|Pustakawan):\s*/i', '', $summary);
        
        // Remove excessive whitespace
        $summary = preg_replace('/\n{3,}/', "\n\n", $summary);
        $summary = trim($summary);
        
        return $summary;
    }
    
    /**
     * Generate simple comparison fallback
     */
    private function generateSimpleComparison(array $books): string
    {
        $titles = array_column($books, 'title');
        $authorsList = [];
        
        foreach ($books as $book) {
            $authors = is_array($book['authors']) 
                ? implode(', ', $book['authors'])
                : ($book['author'] ?? 'Penulis tidak diketahui');
            $authorsList[] = $authors;
        }
        
        $comparison = "**Perbandingan Buku:**\n\n";
        $comparison .= "1. **" . $titles[0] . "** oleh " . $authorsList[0] . "\n";
        
        if (isset($titles[1])) {
            $comparison .= "2. **" . $titles[1] . "** oleh " . $authorsList[1] . "\n";
        }
        
        if (isset($titles[2])) {
            $comparison .= "3. **" . $titles[2] . "** oleh " . $authorsList[2] . "\n";
        }
        
        $comparison .= "\n**Saran:**\n";
        $comparison .= "- Untuk pemula: " . ($titles[0] ?? '') . "\n";
        
        if (isset($titles[1])) {
            $comparison .= "- Untuk pembaca menengah: " . $titles[1] . "\n";
        }
        
        if (isset($titles[2])) {
            $comparison .= "- Untuk pembaca lanjutan: " . $titles[2];
        }
        
        return $comparison;
    }
    
    /**
     * Generate reading guide for a book
     */
    public function generateReadingGuide(Biblio $book): array
    {
        $this->log('Generating reading guide', ['book_id' => $book->id]);
        
        $title = $book->title;
        $authors = $book->authors->pluck('name')->join(', ');
        $subjects = $book->subjects->pluck('term')->join(', ');
        
        try {
            $prompt = <<<PROMPT
            Buat panduan membaca untuk buku "{$title}" oleh {$authors}.

            **Topik buku:** {$subjects}

            **Instruksi:**
            Buat panduan dalam format berikut:
            1. **Target Pembaca:** Siapa yang paling cocok membaca buku ini?
            2. **Waktu Membaca:** Estimasi waktu yang dibutuhkan
            3. **Tips Membaca:** Cara terbaik memahami buku ini
            4. **Poin Penting:** 3-5 poin utama yang harus diperhatikan
            5. **Tindak Lanjut:** Apa yang bisa dilakukan setelah membaca buku ini?

            Gunakan bahasa Indonesia yang jelas dan praktis.
            PROMPT;
            
            $response = Http::timeout(90)
                ->post($this->ollamaUrl . '/api/generate', [
                    'model' => $this->ollamaModel,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.5,
                        'num_predict' => 600,
                    ],
                ]);
            
            if ($response->successful()) {
                $guide = $response->json('response', '');
                $cleanedGuide = $this->cleanReadingGuide($guide);
                
                return [
                    'guide' => $cleanedGuide,
                    'source' => 'ai_generated',
                    'confidence' => 0.75,
                ];
            }
        } catch (\Exception $e) {
            $this->log('Reading guide generation failed', [
                'error' => $e->getMessage(),
            ], 'error');
        }
        
        // Fallback guide
        return [
            'guide' => $this->generateBasicReadingGuide($book),
            'source' => 'fallback_generated',
            'confidence' => 0.5,
        ];
    }
    
    /**
     * Clean reading guide
     */
    private function cleanReadingGuide(string $guide): string
    {
        $guide = preg_replace('/^(Panduan|Guide|Reading Guide):\s*/i', '', $guide);
        $guide = preg_replace('/^(Assistant|AI|Pustakawan):\s*/i', '', $guide);
        $guide = preg_replace('/\n{3,}/', "\n\n", $guide);
        
        return trim($guide);
    }
    
    /**
     * Generate basic reading guide fallback
     */
    private function generateBasicReadingGuide(Biblio $book): string
    {
        $title = $book->title;
        $authors = $book->authors->pluck('name')->join(', ');
        $pageCount = $book->items()->first()->physical_desc ?? 'tidak diketahui';
        
        $guide = "**Panduan Membaca untuk \"{$title}\"**\n\n";
        $guide .= "**Penulis:** {$authors}\n\n";
        $guide .= "**1. Target Pembaca:**\n";
        $guide .= "   - Pembaca umum yang tertarik dengan topik ini\n";
        $guide .= "   - Mahasiswa atau peneliti\n";
        $guide .= "   - Praktisi di bidang terkait\n\n";
        
        $guide .= "**2. Estimasi Waktu:**\n";
        $guide .= "   - Bacaan ringan: 2-3 jam\n";
        $guide .= "   - Bacaan mendalam: 5-7 jam\n";
        $guide .= "   - Halaman: {$pageCount}\n\n";
        
        $guide .= "**3. Tips Membaca:**\n";
        $guide .= "   - Baca pendahuluan dan kesimpulan terlebih dahulu\n";
        $guide .= "   - Buat catatan untuk poin penting\n";
        $guide .= "   - Diskusikan dengan teman untuk pemahaman lebih dalam\n\n";
        
        $guide .= "**4. Tindak Lanjut:**\n";
        $guide .= "   - Cari buku serupa di katalog NOTOBUKU\n";
        $guide .= "   - Buat ringkasan pribadi\n";
        $guide .= "   - Terapkan pengetahuan yang didapat";
        
        return $guide;
    }
    
    /**
     * Extract key points from text
     */
    public function extractKeyPoints(string $text, int $maxPoints = 5): array
    {
        if (strlen($text) < 100) {
            return ['Teks terlalu pendek untuk mengekstrak poin penting'];
        }
        
        try {
            $prompt = <<<PROMPT
            Ekstrak {$maxPoints} poin penting dari teks berikut:

            {$text}

            **Instruksi:**
            1. Ambil poin-poin utama saja
            2. Gunakan bahasa Indonesia
            3. Format dalam poin-poin numerik
            4. Maksimal {$maxPoints} poin
            5. Jangan tambahkan komentar atau penjelasan lain

            **Poin Penting:**
            PROMPT;
            
            $response = Http::timeout(60)
                ->post($this->ollamaUrl . '/api/generate', [
                    'model' => $this->ollamaModel,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.2,
                        'num_predict' => 300,
                    ],
                ]);
            
            if ($response->successful()) {
                $points = $response->json('response', '');
                return $this->parseKeyPoints($points, $maxPoints);
            }
        } catch (\Exception $e) {
            $this->log('Key points extraction failed', [
                'error' => $e->getMessage(),
            ], 'error');
        }
        
        // Fallback: simple extraction
        return $this->simpleKeyPointExtraction($text, $maxPoints);
    }
    
    /**
     * Parse key points from AI response
     */
    private function parseKeyPoints(string $response, int $maxPoints): array
    {
        $lines = explode("\n", $response);
        $points = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Match numbered points or bullet points
            if (preg_match('/^(\d+[\.\)]|\-|\*)\s+(.+)$/', $line, $matches)) {
                $point = trim($matches[2]);
                if (!empty($point)) {
                    $points[] = $point;
                }
            } elseif (!empty($line) && !preg_match('/^(Poin|Points|Key)/i', $line)) {
                $points[] = $line;
            }
            
            if (count($points) >= $maxPoints) {
                break;
            }
        }
        
        if (empty($points)) {
            $points = ['Tidak dapat mengekstrak poin penting dari teks ini.'];
        }
        
        return array_slice($points, 0, $maxPoints);
    }
    
    /**
     * Simple key point extraction fallback
     */
    private function simpleKeyPointExtraction(string $text, int $maxPoints): array
    {
        $sentences = preg_split('/[.!?]+/', $text);
        $sentences = array_filter($sentences, function ($sentence) {
            return strlen(trim($sentence)) > 30;
        });
        
        $sentences = array_slice($sentences, 0, $maxPoints * 2);
        $points = [];
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (!empty($sentence) && count($points) < $maxPoints) {
                $points[] = $sentence;
            }
        }
        
        if (empty($points)) {
            $points = ['Teks tidak mengandung poin-poin yang dapat diekstrak.'];
        }
        
        return $points;
    }
}