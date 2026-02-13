<?php

namespace App\Services\PustakawanDigital;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ChatService extends BaseService
{
    private $ollamaUrl;
    private $ollamaModel;
    private $fallbackEnabled;
    private $forceMockMode;

    public function __construct()
    {
        $this->ollamaUrl = config('services.ollama.url', 'http://localhost:11434');
        $this->ollamaModel = config('services.ollama.model', 'qwen2.5:3b');
        $this->fallbackEnabled = config('services.ollama.fallback_enabled', true);
        $this->forceMockMode = config('services.ai_debug.force_mock_responses', true);
        
        $this->log('ChatService initialized', [
            'model' => $this->ollamaModel,
            'url' => $this->ollamaUrl,
            'force_mock_mode' => $this->forceMockMode,
            'freedom_mode' => config('services.ai_features.freedom_mode', true),
        ]);
    }

    /**
     * Deteksi intent dari pertanyaan user
     */
    public function detectIntent(string $question): string
    {
        $question = trim($question);
        $normalized = $this->normalizeText($question);
        
        $this->log('Detecting intent', ['question' => $question, 'normalized' => $normalized]);

        // Mode kebebasan: untuk semua pertanyaan terkait buku, gunakan hybrid_free
        if ($this->isBookRelatedQuestion($normalized) || 
            $this->isAcademicIntent($normalized) ||
            $this->isRecommendIntent($normalized) ||
            $this->isSearchIntent($normalized)) {
            return 'hybrid_free';
        }

        if ($this->isAskIntent($normalized)) {
            return 'ask';
        }
        
        return 'hybrid_free';
    }

    /**
     * Cek apakah pertanyaan terkait buku
     */
    private function isBookRelatedQuestion(string $normalizedText): bool
    {
        $patterns = [
            '/\b(buku|novel|bacaan|literatur|karya|tulisan|penulis|pengarang)\b/i',
            '/\b(membaca|bacaan|literasi|sastra|fiksi|nonfiksi|cerita)\b/i',
            '/\b(genre|jenis|kategori)\s+(buku|novel)\b/i',
            '/\b(referensi|rujukan|sumber|daftar\s+bacaan)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek apakah intent pencarian
     */
    private function isSearchIntent(string $normalizedText): bool
    {
        $searchPatterns = [
            '/\b(cari|carikan|mencari|pencarian|search|find)\b/',
            '/\b(buku|novel|bacaan|referensi|materi|judul)\b.*\b(apa|mana|dimana)\b/',
            '/\b(buku|novel|bacaan|referensi)\s+(tentang|mengenai|untuk)\b/',
            '/\b(ada\s+dimana|dimana\s+saya\s+bisa)\s+(baca|pinjam|temukan)\b/',
        ];
        
        foreach ($searchPatterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Cek apakah intent bertanya
     */
    private function isAskIntent(string $normalizedText): bool
    {
        $questionWords = [
            'apa', 'apakah', 'bagaimana', 'mengapa', 'kenapa',
            'kapan', 'dimana', 'berapa', 'siapa', 'bisa',
            'bisakah', 'boleh', 'bolehkah', 'tolong', 'jelaskan',
            'terangkan', 'artinya', 'definisi', 'pengertian',
        ];
        
        foreach ($questionWords as $word) {
            if (str_contains($normalizedText, $word)) {
                return true;
            }
        }
        
        if (str_contains($normalizedText, '?') && !str_contains($normalizedText, 'http')) {
            return true;
        }
        
        return false;
    }

    /**
     * Cek apakah intent rekomendasi
     */
    private function isRecommendIntent(string $normalizedText): bool
    {
        $recommendPatterns = [
            '/\b(rekomendasi|sarankan|saran|rekomendasikan|anjuran|rekom)\b/',
            '/\b(baca|bacaan|buku)\s+(apa|yang)\s+(bagus|baik|rekomendasi|terbaik)\b/',
            '/\b(butuh|perlu|ingin|mau|pingin)\s+buku\s+(untuk|tentang|tentang)\b/',
            '/\b(saya|aku)\s+(baru|mulai|ingin)\s+(belajar|membaca|eksplor)\b/',
            '/\b(buku|novel)\s+(wajib|harus|pantas|rekomended)\s+baca\b/i',
        ];
        
        foreach ($recommendPatterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Cek apakah intent akademik/penelitian
     */
    private function isAcademicIntent(string $normalizedText): bool
    {
        $patterns = [
            '/\b(skripsi|tesis|disertasi|penelitian|riset|research)\b/i',
            '/\b(tema|topik|ide)\b.*\b(riset|penelitian|skripsi|tesis)\b/i',
            '/\b(metodologi|metode|kerangka teori|tinjauan pustaka|literature review|rumusan masalah)\b/i',
            '/\b(variabel|hipotesis|studi kasus|studi literatur|survei|wawancara|observasi)\b/i',
            '/\b(literasi informasi|bibliometrik|bibliometrika)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Dapatkan response dari AI dengan fallback yang kuat
     */
    public function getAiResponse(string $question, array $context = []): array
    {
        $startTime = microtime(true);
        $this->log('Getting AI response', [
            'question' => substr($question, 0, 100),
            'force_mock' => $this->forceMockMode,
            'timestamp' => now()->format('H:i:s'),
        ]);
        
        // Jika force mock mode aktif, langsung return mock response
        if ($this->forceMockMode) {
            $this->log('Using forced mock response');
            return $this->getEnhancedFallbackResponse($question);
        }
        
        // Cek apakah Ollama available dengan timeout singkat
        if (!$this->checkOllamaAvailableFast()) {
            $this->log('Ollama not available, using fallback');
            return $this->getEnhancedFallbackResponse($question);
        }
        
        try {
            $prompt = $this->buildPrompt($question, $context);
            
            $timeout = (int) config('services.ollama.timeout', 120);
            $connectTimeout = (int) config('services.ollama.connect_timeout', 10);
            $response = Http::timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->post($this->ollamaUrl . '/api/generate', [
                    'model' => $this->ollamaModel,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.85,
                        'num_predict' => 600,
                        'stop' => ['\n\n', 'User:', 'Assistant:'],
                    ],
                ]);
            
            if ($response->successful()) {
                $aiResponse = $response->json('response', '');
                $parsed = $this->parseAiResponse($aiResponse, $question);
                
                $this->log('AI response successful', [
                    'response_time' => round(microtime(true) - $startTime, 2) . 's',
                    'length' => strlen($parsed['answer']),
                ]);
                
                return $parsed;
            } else {
                $this->log('AI response failed', [
                    'status' => $response->status(),
                    'response_time' => round(microtime(true) - $startTime, 2) . 's',
                ]);
                return $this->getEnhancedFallbackResponse($question);
            }
        } catch (\Exception $e) {
            $this->log('AI request exception', [
                'error' => $e->getMessage(),
                'response_time' => round(microtime(true) - $startTime, 2) . 's',
            ], 'error');
            
            return $this->getEnhancedFallbackResponse($question);
        }
    }

    /**
     * Cek ketersediaan Ollama dengan cepat
     */
    private function checkOllamaAvailableFast(): bool
    {
        if (config('services.ai_debug.skip_ollama_check', false)) {
            return false; // Skip check and use fallback
        }
        
        $cacheKey = 'ollama_fast_check_' . md5($this->ollamaUrl);
        
        return Cache::remember($cacheKey, 30, function () {
            try {
                $response = Http::timeout(3)
                    ->connectTimeout(2)
                    ->get($this->ollamaUrl . '/api/tags');
                
                $available = $response->successful();
                
                $this->log('Ollama fast check', [
                    'available' => $available,
                    'status' => $response->status(),
                ]);
                
                return $available;
            } catch (\Exception $e) {
                $this->log('Fast Ollama check failed', [
                    'error' => $e->getMessage(),
                ], 'warning');
                return false;
            }
        });
    }

    /**
     * Build prompt untuk AI - MODE BEBAS
     */
    private function buildPrompt(string $question, array $context = []): string
    {
        $academicMode = (bool) ($context['academic_mode'] ?? false);
        $avoidTitles = (bool) ($context['avoid_titles'] ?? false);
        $previousTopic = $this->getPreviousTopicText($context, $question);
        $topicShift = $previousTopic ? $this->isTopicShift($question, $previousTopic) : false;

        $basePrompt = <<<PROMPT
        Anda adalah Pustakawan Digital AI untuk NOTOBUKU.
        
        Tujuan utama: menjawab pertanyaan pengguna dengan akurat, jelas, dan jujur.
        
        Aturan penting:
        - Jangan mengarang fakta, kutipan, ayat, atau sumber. Jika tidak yakin, katakan "saya tidak yakin".
        - Jangan mengklaim akses real-time, katalog, atau database.
        - Jawaban harus relevan, natural, dan tidak bertele-tele.
        - Gunakan bahasa Indonesia yang rapi, hangat, dan profesional.
        
        Format jawaban (wajib):
        - Gunakan heading & sub-heading yang jelas.
        - Gunakan bullet/numbering untuk poin penting.
        - Akhiri dengan ringkasan singkat (2-4 poin).
        - Sorot poin penting dengan **tebal**.
        PROMPT;

        if ($previousTopic) {
            $basePrompt .= "\n\nKonteks terakhir: {$previousTopic}";
        }

        if ($topicShift) {
            $basePrompt .= <<<PROMPT

            Perubahan topik terdeteksi:
            - Awali jawaban dengan 1 kalimat konfirmasi bahwa topik berganti.
            - Tetap jawab secara singkat dan relevan.
            - Akhiri dengan 1 pertanyaan klarifikasi singkat.
            PROMPT;
        }

        if ($academicMode) {
            $basePrompt .= <<<PROMPT
            
            Mode akademik:
            - Beri saran topik/kerangka/ide yang realistis dan bisa diteliti.
            - Tawarkan opsi metodologi (kualitatif/kuantitatif/mixed) sesuai konteks.
            - Ajukan 1-2 pertanyaan untuk memperjelas fokus penelitian.
            PROMPT;
        }

        if ($avoidTitles) {
            $basePrompt .= <<<PROMPT
            
            Batasan judul:
            - Jangan menyebut judul buku spesifik kecuali pengguna secara eksplisit meminta rekomendasi buku.
            PROMPT;
        }


        if (!empty($context['user_profile'])) {
            $profile = $context['user_profile'];
            $interests = $profile['interests'] ?? [];
            $recent = $profile['recent_searches'] ?? [];

            $basePrompt .= "\n\nProfil pengguna:\n";
            if (!empty($interests)) {
                $basePrompt .= "- Minat terbaru: " . implode(', ', array_slice($interests, -5)) . "\n";
            }
            if (!empty($recent)) {
                $basePrompt .= "- Pencarian terbaru: " . implode(' | ', array_slice($recent, -3)) . "\n";
            }
        }

        if (!empty($context['recent_messages'])) {
            $contextPrompt = "\n\nKonteks percakapan sebelumnya:\n";
            foreach ($context['recent_messages'] as $msg) {
                $role = $msg['role'] === 'user' ? 'Pengguna' : 'Anda';
                $contextPrompt .= "{$role}: {$msg['content']}\n";
            }
            $basePrompt .= $contextPrompt;
        }

        $basePrompt .= "\n\nPertanyaan pengguna: {$question}";
        $basePrompt .= "\n\nJawaban:";

        return $basePrompt;
    }

    private function getPreviousTopicText(array $context, string $currentQuestion): ?string
    {
        if (!empty($context['recent_messages'])) {
            $messages = array_reverse($context['recent_messages']);
            foreach ($messages as $msg) {
                if (($msg['role'] ?? '') === 'user' && !empty($msg['content'])) {
                    if (trim($msg['content']) === trim($currentQuestion)) {
                        continue;
                    }
                    return $msg['content'];
                }
            }
        }

        if (!empty($context['user_profile']['recent_searches'])) {
            $recent = $context['user_profile']['recent_searches'];
            if (is_array($recent) && !empty($recent)) {
                return end($recent) ?: null;
            }
        }

        return null;
    }

    private function isTopicShift(string $currentQuestion, string $previousText): bool
    {
        $currentKeywords = $this->extractKeywords($currentQuestion);
        $previousKeywords = $this->extractKeywords($previousText);

        if (empty($currentKeywords) || empty($previousKeywords)) {
            return false;
        }

        $overlap = array_intersect($currentKeywords, $previousKeywords);
        $overlapRatio = count($overlap) / max(1, min(count($currentKeywords), count($previousKeywords)));

        return $overlapRatio < 0.2;
    }



    /**
     * Parse response dari AI
     */
    private function parseAiResponse(string $rawResponse, string $originalQuestion): array
    {
        $response = trim($rawResponse);

        $response = preg_replace('/^.*?(Assistant|AI|Pustakawan):\s*/i', '', $response);
        $response = preg_replace('/\n{3,}/', "\n\n", $response);

        $confidence = 0.85;
        $keywords = $this->extractKeywords($originalQuestion);

        return [
            'answer' => $response,
            'confidence' => $confidence,
            'keywords' => $keywords,
            'sources' => ['ai_knowledge'],
            'mode' => 'free',
        ];
    }

    /**
     * Enhanced fallback response
     */
    private function getEnhancedFallbackResponse(string $question): array
    {
        $normalized = $this->normalizeText($question);
        $keywords = $this->extractKeywords($question);

        $commonQuestions = [
            'nama' => 'Saya Pustakawan Digital NOTOBUKU. Saya bisa membantu menjelaskan topik, memberi saran riset, dan rekomendasi bacaan umum.',
            'tugas' => 'Tugas saya adalah membantu menjawab pertanyaan seputar literasi, penelitian, dan rekomendasi bacaan umum dengan jawaban yang rapi dan relevan.',
            'bantuan' => 'Saya bisa bantu: 1) Menjelaskan topik, 2) Saran ide riset, 3) Rekomendasi bacaan umum, 4) Menyusun langkah belajar.',
        ];

        foreach ($commonQuestions as $key => $answer) {
            if (str_contains($normalized, $key)) {
                return [
                    'answer' => $answer,
                    'confidence' => 0.7,
                    'keywords' => $keywords,
                    'sources' => ['fallback_safe'],
                    'mode' => 'free',
                ];
            }
        }

        return $this->getExpertResponse($question, $keywords);
    }

    /**
     * Expert response based on question analysis
     */
    private function getExpertResponse(string $question, array $keywords): array
    {
        $topic = !empty($keywords) ? implode(' ', array_slice($keywords, 0, 3)) : 'topik ini';

        return [
            'answer' => "Saya bisa bantu jelaskan tentang **{$topic}**. Agar jawabannya lebih tepat, boleh jelaskan fokus yang Anda mau?\n\nContoh yang bisa dipilih:\n- Pengertian dasar\n- Latar sejarah\n- Konsep kunci\n- Penerapan modern\n\nKalau Anda beri konteks sedikit (misalnya untuk tugas/riset atau sekadar pengetahuan umum), saya bisa jawab lebih lengkap.",
            'confidence' => 0.7,
            'keywords' => $keywords,
            'sources' => ['fallback_safe'],
            'mode' => 'free',
        ];
    }

    /**
     * Public method untuk check Ollama availability
     */
    public function checkOllamaAvailable(): bool
    {
        if ($this->forceMockMode) {
            return false;
        }

        return $this->checkOllamaAvailableFast();
    }

    /**
     * Generate conversation title dari pertanyaan pertama
     */
    public function generateConversationTitle(string $firstQuestion): string
    {
        $keywords = $this->extractKeywords($firstQuestion);

        if (empty($keywords)) {
            return 'Diskusi Literasi';
        }

        $title = 'Topik: ' . implode(' ', array_slice($keywords, 0, 3));
        $title = ucfirst($title);

        if (strlen($title) > 40) {
            $title = substr($title, 0, 37) . '...';
        }

        return $title ?: 'Diskusi Literasi';
    }

    /**
     * Get follow-up questions berdasarkan konteks
     */
    public function getFollowupQuestions(array $conversationContext): array
    {
        $followups = [
            'Ingin penjelasan lebih fokus di bagian mana?',
            'Ada konteks tugas/riset yang bisa saya bantu?',
            'Mau saya ringkas poin pentingnya?',
        ];

        return array_slice($followups, 0, 3);
    }
}
