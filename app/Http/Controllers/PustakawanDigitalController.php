<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\PustakawanDigital\ChatService;
use App\Services\PustakawanDigital\SearchService;
use App\Services\PustakawanDigital\ExternalApiService;
use App\Services\PustakawanDigital\MockAiService;
use App\Services\PustakawanDigital\AiAnalyticsService;
use App\Services\PustakawanDigital\AiContextService;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\BookRequest;

class PustakawanDigitalController extends Controller
{
    protected $chatService;
    protected $searchService;
    protected $externalService;
    protected $mockAiService;
    protected $useMockAi;
    protected $analyticsService;
    protected $contextService;
    protected $aiMode;
    protected $aiOnly;

    public function __construct()
    {
        $this->middleware(['auth', 'role.member']);

        $this->chatService = new ChatService();
        $this->searchService = new SearchService();
        $this->externalService = new ExternalApiService();
        $this->mockAiService = new MockAiService();
        $this->analyticsService = new AiAnalyticsService();
        $this->contextService = new AiContextService();
        $this->aiOnly = (bool) config('services.pustakawan.ai_only', false);

        $this->useMockAi = $this->shouldUseMockAi();
        $this->aiMode = $this->useMockAi ? 'mock' : 'real';
    }

    private function shouldUseMockAi(): bool
    {
        if (config('app.use_mock_ai', false)) {
            return true;
        }

        try {
            return !$this->chatService->checkOllamaAvailable();
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $requestedConversationId = $request->query('conversation');
        $conversation = null;

        if ($requestedConversationId) {
            $conversation = AiConversation::where('user_id', $user->id)
                ->where('id', $requestedConversationId)
                ->first();

            if ($conversation) {
                AiConversation::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
                $conversation->update(['is_active' => true]);
            }
        }

        if (!$conversation) {
            $conversation = AiConversation::where('user_id', $user->id)
                ->where('is_active', true)
                ->latest()
                ->first();
        }

        if (!$conversation) {
            $conversation = AiConversation::create([
                'user_id' => $user->id,
                'title' => 'Percakapan Baru',
                'is_active' => true,
            ]);

            AiMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'ai',
                'content' => $this->getWelcomeMessage(),
            ]);
        }

        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();
        $messages->transform(function ($msg) {
            $msg->content = $this->fixMojibake((string) $msg->content);
            return $msg;
        });

        return view('member.pustakawan-digital', [
            'conversation' => $conversation,
            'messages' => $messages,
            'ai_mode' => $this->aiMode,
            'ai_only' => $this->aiOnly,
        ]);
    }

    public function handleQuestion(Request $request)
    {
        try {
            $data = $request->validate([
                'question' => ['required', 'string', 'max:2000'],
                'conversation_id' => ['nullable'],
                'page' => ['nullable', 'integer', 'min:1'],
                'available_only' => ['nullable', 'boolean'],
                'sort' => ['nullable', 'string'],
            ]);

            $user = Auth::user();
            $question = trim((string) ($data['question'] ?? ''));
            $conversationId = (int) ($data['conversation_id'] ?? 0);

            if ($question === '') {
                return response()->json(['success' => false, 'message' => 'Pertanyaan kosong.'], 422);
            }

        $conversation = AiConversation::where('user_id', $user->id)
            ->when($conversationId > 0, fn($q) => $q->where('id', $conversationId))
            ->first();

        if (!$conversation) {
            $conversation = AiConversation::create([
                'user_id' => $user->id,
                'title' => 'Percakapan Baru',
                'is_active' => true,
            ]);
        }

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $question,
        ]);

        $intent = $this->chatService->detectIntent($question);
        $userContext = $this->contextService->updateFromQuestion($user->id, $question, $intent);

        $recentMessages = AiMessage::where('conversation_id', $conversation->id)
            ->latest()
            ->limit(6)
            ->get()
            ->reverse()
            ->map(function ($msg) {
                return [
                    'role' => $msg->role,
                    'content' => $msg->content,
                ];
            })
            ->values()
            ->toArray();

        $context = [
            'user_profile' => $userContext,
            'recent_messages' => $recentMessages,
        ];

        $aiResponse = $this->useMockAi
            ? $this->mockAiService->getMockResponse($question)
            : $this->chatService->getAiResponse($question, $context);

        $page = (int) ($data['page'] ?? 1);
        $perPage = 6;
        $offset = ($page - 1) * $perPage;
        $availableOnly = (bool) ($data['available_only'] ?? false);
        $sort = (string) ($data['sort'] ?? 'relevant');

        $catalogResults = $this->searchService->searchLocal($question, $perPage, $offset, $sort, $availableOnly);
        $hasCatalogResults = (int) ($catalogResults['total'] ?? 0) > 0;

        $message = $this->formatFreeResponse($aiResponse, $catalogResults, $question, $hasCatalogResults);
        $message = $this->fixMojibake($message);

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'ai',
            'content' => $message,
            'metadata' => [
                'mode' => $aiResponse['mode'] ?? 'free',
                'confidence' => $aiResponse['confidence'] ?? 0.7,
            ],
        ]);

        $this->updateConversationTitle((string) $conversation->id, $question);

            return response()->json([
                'success' => true,
                'response' => [
                    'message' => $message,
                    'data' => [
                        'books' => $catalogResults['books'] ?? [],
                        'total' => $catalogResults['total'] ?? 0,
                        'page' => $page,
                        'per_page' => $perPage,
                        'query' => $question,
                        'source' => 'local',
                    ],
                    'mode' => $aiResponse['mode'] ?? 'free',
                    'keywords' => $aiResponse['keywords'] ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            $msg = strtolower((string) $e->getMessage());
            $isQuota = str_contains($msg, 'usage limit')
                || str_contains($msg, 'quota')
                || str_contains($msg, 'rate limit')
                || str_contains($msg, '429');

            Log::warning('PustakawanDigital degraded response', [
                'error' => $e->getMessage(),
                'is_quota' => $isQuota,
                'user_id' => Auth::id(),
            ]);

            $fallback = $isQuota
                ? "Layanan AI sedang mencapai batas kuota. Fitur tetap bisa dipakai: cari buku di katalog, cek pinjaman, dan reservasi."
                : "Layanan AI sedang sibuk. Coba beberapa saat lagi, atau lanjut gunakan pencarian katalog.";

            return response()->json([
                'success' => true,
                'response' => [
                    'message' => $fallback,
                    'data' => [
                        'books' => [],
                        'total' => 0,
                        'page' => 1,
                        'per_page' => 6,
                        'query' => '',
                        'source' => 'degraded',
                    ],
                    'mode' => 'degraded',
                    'keywords' => [],
                ],
            ]);
        }
    }

    public function startNewConversation(Request $request)
    {
        $user = Auth::user();

        AiConversation::where('user_id', $user->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'title' => 'Percakapan Baru',
            'is_active' => true,
        ]);

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'ai',
            'content' => $this->getWelcomeMessage(),
        ]);

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
        ]);
    }

    public function deleteConversation($conversationId)
    {
        $user = Auth::user();

        $conversation = AiConversation::where('user_id', $user->id)
            ->where('id', $conversationId)
            ->first();

        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Percakapan tidak ditemukan.'], 404);
        }

        $conversation->delete();

        return response()->json(['success' => true]);
    }

    public function getConversationHistory(Request $request)
    {
        $user = Auth::user();

        $conversations = AiConversation::where('user_id', $user->id)
            ->with(['messages' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->orderBy('updated_at', 'desc')
            ->paginate(10);

        $conversations->getCollection()->transform(function ($conversation) {
            $lastMessage = $conversation->messages->first();
            $conversation->last_message_preview = $lastMessage
                ? mb_substr(trim($this->fixMojibake((string) $lastMessage->content)), 0, 80)
                : '';
            $conversation->last_message_at = $lastMessage?->created_at;
            $conversation->last_message_role = $lastMessage?->role;
            return $conversation;
        });

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
            'freedom_mode' => true,
        ]);
    }

    public function getConversationMessages(Request $request, $conversationId)
    {
        $user = Auth::user();

        $conversation = AiConversation::where('user_id', $user->id)
            ->where('id', $conversationId)
            ->first();

        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Percakapan tidak ditemukan.'], 404);
        }

        $messages = $conversation->messages()->orderBy('created_at', 'asc')->get();
        $messages->transform(function ($msg) {
            $msg->content = $this->fixMojibake((string) $msg->content);
            return $msg;
        });

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    public function requestBook(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:500'],
            'conversation_id' => ['nullable'],
        ]);

        $user = Auth::user();
        $conversationId = (int) ($data['conversation_id'] ?? 0);

        $bookRequest = BookRequest::create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId ?: null,
            'title' => $data['title'],
            'author' => $data['author'] ?? null,
            'isbn' => $data['isbn'] ?? null,
            'reason' => $data['reason'] ?? 'Permintaan dari Pustakawan Digital',
            'status' => 'pending',
        ]);

        if ($conversationId) {
            AiMessage::create([
                'conversation_id' => $conversationId,
                'role' => 'system',
                'content' => "[REQUEST] **REQUEST AHLI:** Permintaan buku \"{$data['title']}\" telah dikirim ke admin.\n" .
                            "[SUMBER] **Sumber:** Permintaan Pengguna\n" .
                            "[WAKTU] **Waktu:** " . now()->format('d/m/Y H:i'),
                'metadata' => [
                    'type' => 'expert_book_request',
                    'request_id' => $bookRequest->id,
                    'is_expert_recommendation' => false,
                    'freedom_mode' => true,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permintaan buku berhasil dikirim',
            'request_id' => $bookRequest->id,
            'freedom_mode' => true,
        ]);
    }

    public function checkAiStatus()
    {
        return response()->json([
            'success' => true,
            'ai_mode' => $this->aiMode,
            'mock_mode' => $this->useMockAi,
            'freedom_mode' => true,
        ]);
    }

    public function testMockResponses(Request $request)
    {
        $questions = [
            'rekomendasi novel terbaik sepanjang masa',
            'buku filsafat yang mengubah hidup',
            'penulis sastra indonesia terhebat',
            'buku science fiction landmark',
            'karya sastra dunia wajib baca',
        ];

        $results = [];
        foreach ($questions as $question) {
            $response = $this->mockAiService->getMockResponse($question);
            $results[] = [
                'question' => $question,
                'answer' => $this->fixMojibake($response['answer'] ?? ''),
                'confidence' => $response['confidence'] ?? 0.7,
                'keywords' => $response['keywords'] ?? [],
                'type' => $response['type'] ?? 'expert_response',
                'mode' => $response['mode'] ?? 'free',
            ];
        }

        return response()->json([
            'success' => true,
            'mock_mode' => true,
            'freedom_mode' => true,
            'results' => $results,
            'message' => 'Mock AI expert responses working in freedom mode',
        ]);
    }

    public function getExpertKnowledge(Request $request)
    {
        $category = (string) $request->query('category', '');
        $data = $category !== ''
            ? $this->mockAiService->getExpertKnowledge($category)
            : ['message' => 'Pengetahuan ahli tersedia.'];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function getPopularCategories()
    {
        return response()->json([
            'success' => true,
            'categories' => [
                ['name' => 'Pemrograman', 'count' => 45, 'icon' => 'PC'],
                ['name' => 'Novel', 'count' => 120, 'icon' => 'BK'],
                ['name' => 'Bisnis', 'count' => 65, 'icon' => 'BS'],
                ['name' => 'Self-Improvement', 'count' => 38, 'icon' => 'SI'],
                ['name' => 'Anak & Remaja', 'count' => 85, 'icon' => 'AR'],
                ['name' => 'Agama', 'count' => 72, 'icon' => 'AG'],
                ['name' => 'Sains', 'count' => 42, 'icon' => 'SN'],
                ['name' => 'Sejarah', 'count' => 58, 'icon' => 'SJ'],
            ],
        ]);
    }

    private function formatFreeResponse(array $aiResponse, array $catalogResults, string $question, bool $hasCatalogResults): string
    {
        $aiAnswer = $aiResponse['answer'] ?? '';
        $message = "[INFO] **SEBAGAI AHLI LITERATUR DENGAN PENGETAHUAN LUAS:**\n\n";
        $message .= $aiAnswer . "\n\n";

        if ($hasCatalogResults) {
            $bookCount = $catalogResults['total'] ?? 0;
            $books = $catalogResults['books'] ?? [];

            $message .= "[KOLEKSI] **RELEVANSI DENGAN KOLEKSI NOTOBUKU:**\n";
            $message .= "Saya menemukan **{$bookCount} buku terkait** di katalog:\n\n";

            foreach (array_slice($books, 0, 3) as $index => $book) {
                $num = $index + 1;
                $message .= $num . '. **' . ($book['title'] ?? '-') . '**';
                if (!empty($book['author'])) {
                    $message .= ' oleh ' . $book['author'];
                }
                if ($book['available'] ?? false) {
                    $message .= ' (Tersedia)';
                }
                $message .= "\n";
            }

            if ($bookCount > 3) {
                $message .= "... dan " . ($bookCount - 3) . " buku lainnya\n";
            }

            $message .= "\n[CATATAN] **CATATAN:** Rekomendasi di atas didasarkan pada pengetahuan literatur luas saya. ";
            $message .= "Beberapa mungkin belum tersedia di katalog, tapi bisa direquest!";
        } else {
            $message .= "[PENCARIAN] **PENCARIAN KOLEKSI:**\n";
            $message .= "Belum ada buku spesifik untuk \"{$question}\" di katalog saat ini.\n\n";
            $message .= "[INFO] **NAMUN INI BUKAN HALANGAN!** Sebagai ahli literatur, saya tetap bisa:\n";
            $message .= "- Memberi rekomendasi buku TERBAIK di bidangnya\n";
            $message .= "- Menjelaskan tentang penulis dan genre TERKENAL\n";
            $message .= "- Membagikan pengetahuan sastra yang BERMANFAAT\n";
            $message .= "- Menyarankan alternatif dan karya SERUPA\n\n";
            $message .= "[REQUEST] **INGIN BUKU TERTENTU?** Bisa request ke admin!";
        }

        return $message;
    }

    private function getWelcomeMessage(): string
    {
        $modeNotice = $this->useMockAi
            ? "\n\n[MODE DEMO] **Mode Demo:** Menggunakan pengetahuan literatur simulasi tingkat ahli."
            : "\n\n[AI REAL] **AI Real Aktif:** Mengakses pengetahuan literatur luas secara real-time.";

        return "[SELAMAT DATANG] **SELAMAT DATANG DI PUSTAKAWAN DIGITAL - MODE AHLI!**" . $modeNotice . "\n\n" .
               "[AHLI] **SAYA ADALAH AHLI LITERATUR VIRTUAL** dengan pengetahuan tentang:\n\n" .
               "- **Buku-buku LEGENDARY** dari seluruh dunia\n" .
               "- **Penulis-penulis MASTER** berbagai genre\n" .
               "- **Trend sastra GLOBAL** terkini\n" .
               "- **Karya-karya HIDDEN GEM** yang patut ditemukan\n\n" .
               "[MODUS] **MODUS: KEBEBASAN PENUH**\n" .
               "- Saya BOLEH merekomendasikan buku APA SAJA\n" .
               "- Saya BOLEH membahas penulis SIAPA SAJA\n" .
               "- Saya BOLEH berbagi pengetahuan SELUAS-LUASNYA\n" .
               "- Fokus pada KUALITAS informasi, bukan batasan katalog\n\n" .
               "[SARAN AWAL] **SARAN AWAL:**\n" .
               "Tanyakan tentang genre favorit, penulis idol, atau rekomendasi buku TERBAIK!";
    }

    private function updateConversationTitle(string $conversationId, string $question): void
    {
        $conversation = AiConversation::find($conversationId);
        if (!$conversation) {
            return;
        }

        if ($conversation->title === 'Percakapan Baru') {
            $title = $this->generateConversationTitle($question);
            $conversation->update(['title' => $title]);
        }
    }

    private function generateConversationTitle(string $question): string
    {
        $words = preg_split('/\s+/', $question);
        $titleWords = array_slice($words, 0, min(5, count($words)));
        $title = 'Diskusi: ' . implode(' ', $titleWords);

        if (strlen($title) > 40) {
            $title = substr($title, 0, 37) . '...';
        }

        return $title ?: 'Diskusi Literatur Bebas';
    }

    private function fixMojibake(string $text): string
    {
        if ($text === '') {
            return $text;
        }

                $score = function (string $s): int {
            return substr_count($s, "\xC3")
                + substr_count($s, "\xC3\x83")
                + substr_count($s, "\xE2")
                + substr_count($s, "\xF0\x9F")
                + substr_count($s, "\xEF\xBF\xBD");
        };

        if ($score($text) === 0) {
            return $text;
        }

        $candidates = [$text];
        foreach (['Windows-1252', 'ISO-8859-1'] as $enc) {
            $once = @mb_convert_encoding($text, 'UTF-8', $enc);
            if ($once) {
                $candidates[] = $once;
                $twice = @mb_convert_encoding($once, 'UTF-8', $enc);
                if ($twice) {
                    $candidates[] = $twice;
                }
            }
        }

        $best = $text;
        $bestScore = $score($text);
        foreach ($candidates as $cand) {
            $candScore = $score($cand);
            if ($candScore < $bestScore) {
                $best = $cand;
                $bestScore = $candScore;
            }
        }

        return $best;
    }
}
