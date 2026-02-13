<?php

namespace App\Http\Controllers;

use App\Models\Biblio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class MemberAiSearchController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role.member']);
    }

    public function search(Request $request)
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'mode' => ['nullable', 'in:chat,search'],
            'clear_history' => ['nullable', 'boolean'],
        ]);

        $mode = (string) ($data['mode'] ?? 'chat');
        session(['ai_mode' => $mode]);

        if ($request->has('clear_history') && $request->input('clear_history') == true) {
            $this->clearChatHistory();
            session()->forget('ai_last_keywords');
            session()->forget('ai_last_books');
            session()->forget('ai_last_items');
            session()->forget('external_items');
            session()->forget('external_source');

            if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'cleared' => true
                ]);
            }
            return redirect()->route('member.ai_search.form');
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return $this->handleQuestion((string) $data['question'], $mode, true);
        }

        return $this->handleQuestion((string) $data['question'], $mode);
    }

    public function form(Request $request)
    {
        $question = trim((string) $request->query('question', ''));
        $mode = (string) $request->query('mode', session('ai_mode', 'chat'));
        $clear = $request->query('clear', false);
        
        if ($clear) {
            $this->clearChatHistory();
        }
        
        if ($question !== '') {
            return $this->handleQuestion($question, $mode);
        }

        $chatHistory = $this->getChatHistory();
        $aiOnline = $this->checkOllama(env('OLLAMA_URL', 'http://localhost:11434'));
        
        return response()->view('member.ai-search-form', [
            'aiMode' => $mode,
            'ai_question' => $question,
            'chatHistory' => $chatHistory,
            'aiOnline' => $aiOnline,
        ]);
    }

    public function debugCatalog(Request $request)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:200'],
        ]);

        $query = trim((string) $data['q']);
        $items = $this->searchBooks($query);

        return response()->json([
            'ok' => true,
            'q' => $query,
            'count' => count($items),
            'titles' => array_map(fn($b) => $b['title'] ?? '', $items),
            'items' => $items,
        ]);
    }

    private function handleQuestion(string $question, string $mode = 'chat', bool $asJson = false)
    {
        $question = trim($question);
        if ($question === '__WELCOME__') {
            return $this->simpleGreetingResponse('', $mode, $asJson);
        }
        $external = ['items' => [], 'source_label' => null, 'error' => null];

        $this->addToChatHistory([
            'role' => 'user',
            'content' => $question,
            'timestamp' => now()->format('H:i:s'),
            'mode' => $mode
        ]);

        $ollamaUrl = env('OLLAMA_URL', 'http://localhost:11434');
        $model = env('OLLAMA_MODEL', 'deepseek-r1:7b');
        
        Log::info('AI Request', [
            'model' => $model,
            'question' => $question,
            'mode' => $mode
        ]);

        $exactTitle = $this->matchExactTitleFromLastBooks($question);
        if ($exactTitle !== '') {
            $detailBooks = $this->searchBooks($exactTitle);
            $selected = $detailBooks[0] ?? $this->getLastBookByTitle($exactTitle);
            $externalResult = $this->searchExternalBooks($exactTitle);
            $externalBest = !empty($externalResult['items'])
                ? $this->pickBestExternalMatch($exactTitle, $externalResult['items'])
                : [];
            if ($selected || !empty($externalBest)) {
                $answerText = $selected ? $this->buildSingleBookAnswer($selected) : '';
                if (!empty($externalBest)) {
                    $answerText = $this->buildSingleExternalAnswer($externalBest, $externalResult['source_label'] ?? 'sumber eksternal');
                    $external = $externalResult;
                }
                $aiData = [
                    'answer' => $answerText,
                    'search_keywords' => $exactTitle,
                    'suggestions' => $this->generateSuggestions($exactTitle, $mode),
                    'follow_up' => 'Apakah Anda ingin melihat detail buku ini di katalog?'
                ];

                $this->addToChatHistory([
                    'role' => 'ai',
                    'content' => $aiData['answer'],
                    'keywords' => $exactTitle,
                    'timestamp' => now()->format('H:i:s'),
                    'mode' => $mode
                ]);

                $responseData = [
                    'ok' => true,
                    'answer' => $aiData['answer'],
                    'q' => $exactTitle,
                    'question' => $question,
                    'mode' => $mode,
                    'show_books' => true,
                    'catalog_url' => route('katalog.index', ['q' => urlencode($exactTitle), 'ai' => 1]),
                    'items' => $detailBooks,
                    'external_items' => $external['items'] ?? [],
                    'external_source' => $external['source_label'] ?? null,
                    'external_error' => $external['error'] ?? null,
                    'suggestions' => $aiData['suggestions'],
                    'follow_up_question' => $aiData['follow_up'],
                    'ai_online' => $this->checkOllama($ollamaUrl),
                    'ai_context' => [
                        'mode' => $mode,
                        'keywords_extracted' => $exactTitle,
                        'book_count' => count($detailBooks),
                        'show_books' => true,
                        'timestamp' => now()->format('H:i:s')
                    ],
                    'model' => $model
                ];

                if ($asJson) {
                    $chatHistory = $this->getChatHistory();
                    session()->flash('ai_answer', $aiData['answer']);
                    session()->flash('ai_q', $exactTitle);
                    session()->flash('ai_question', $question);
                    session()->flash('ai_mode', $mode);
                    session()->flash('ai_suggestions', $responseData['suggestions']);
                    session()->flash('ai_follow_up', $aiData['follow_up']);
                    session()->flash('ai_books', $detailBooks);
                    session()->flash('ai_show_books', true);
                    session()->flash('external_items', $external['items'] ?? []);
                    session()->flash('external_source', $external['source_label'] ?? null);
                    session()->flash('external_error', $external['error'] ?? null);
                    session()->flash('chat_history', $chatHistory);

                    return response()->json($responseData);
                }

                session()->flash('ai_answer', $aiData['answer']);
                session()->flash('ai_q', $exactTitle);
                session()->flash('ai_question', $question);
                session()->flash('ai_mode', $mode);
                session()->flash('ai_suggestions', $responseData['suggestions']);
                session()->flash('ai_follow_up', $aiData['follow_up']);
                session()->flash('ai_books', $detailBooks);
                session()->flash('ai_show_books', true);
                session()->flash('external_items', $external['items'] ?? []);
                session()->flash('external_source', $external['source_label'] ?? null);
                session()->flash('external_error', $external['error'] ?? null);
                session()->flash('chat_history', $this->getChatHistory());

                return redirect()->route('member.ai_search.form');
            }
        }

        if ($this->isSimpleGreeting($question)) {
            return $this->simpleGreetingResponse($question, $mode, $asJson);
        }

        $academicIntent = $this->academicIntentResponse($question, $mode);
        if ($academicIntent !== null) {
            $this->addToChatHistory([
                'role' => 'ai',
                'content' => $academicIntent['answer'],
                'keywords' => $academicIntent['search_keywords'] ?? '',
                'timestamp' => now()->format('H:i:s'),
                'mode' => $mode
            ]);

            $responseData = [
                'ok' => true,
                'answer' => $academicIntent['answer'],
                'q' => $academicIntent['search_keywords'] ?? '',
                'question' => $question,
                'mode' => $mode,
                'show_books' => false,
                'catalog_url' => route('katalog.index', ['ai' => 1]),
                'items' => [],
                'external_items' => [],
                'external_source' => null,
                'external_error' => null,
                'suggestions' => $academicIntent['suggestions'] ?? $this->generateSuggestions('', $mode),
                'follow_up_question' => $academicIntent['follow_up'] ?? '',
                'ai_online' => $this->checkOllama($ollamaUrl),
                'ai_context' => [
                    'mode' => $mode,
                    'keywords_extracted' => '',
                    'book_count' => 0,
                    'show_books' => false,
                    'timestamp' => now()->format('H:i:s')
                ],
                'model' => $model
            ];

            if ($asJson) {
                $chatHistory = $this->getChatHistory();
                session()->flash('ai_answer', $academicIntent['answer']);
                session()->flash('ai_q', '');
                session()->flash('ai_question', $question);
                session()->flash('ai_mode', $mode);
                session()->flash('ai_suggestions', $responseData['suggestions']);
                session()->flash('ai_follow_up', $academicIntent['follow_up'] ?? '');
                session()->flash('ai_books', []);
                session()->flash('ai_show_books', false);
                session()->flash('external_items', []);
                session()->flash('external_source', null);
                session()->flash('external_error', null);
                session()->flash('chat_history', $chatHistory);

                return response()->json($responseData);
            }

            session()->flash('ai_answer', $academicIntent['answer']);
            session()->flash('ai_q', '');
            session()->flash('ai_question', $question);
            session()->flash('ai_mode', $mode);
            session()->flash('ai_suggestions', $responseData['suggestions']);
            session()->flash('ai_follow_up', $academicIntent['follow_up'] ?? '');
            session()->flash('ai_books', []);
            session()->flash('ai_show_books', false);
            session()->flash('external_items', []);
            session()->flash('external_source', null);
            session()->flash('external_error', null);
            session()->flash('chat_history', $this->getChatHistory());

            return redirect()->route('member.ai_search.form');
        }

        if ($this->isSuggestionIntent($question, $mode)) {
            $aiData = $this->suggestionIntentResponse($question, $mode);
            $this->addToChatHistory([
                'role' => 'ai',
                'content' => $aiData['answer'],
                'keywords' => $aiData['search_keywords'] ?? '',
                'timestamp' => now()->format('H:i:s'),
                'mode' => $mode
            ]);

            $responseData = [
                'ok' => true,
                'answer' => $aiData['answer'],
                'q' => $aiData['search_keywords'] ?? '',
                'question' => $question,
                'mode' => $mode,
                'show_books' => false,
                'catalog_url' => route('katalog.index', ['ai' => 1]),
                'items' => [],
                'external_items' => [],
                'external_source' => null,
                'external_error' => null,
                'suggestions' => $aiData['suggestions'] ?? $this->generateSuggestions('', $mode),
                'follow_up_question' => $aiData['follow_up'] ?? 'Ada yang ingin Anda cari di katalog?',
                'ai_online' => $this->checkOllama($ollamaUrl),
                'ai_context' => [
                    'mode' => $mode,
                    'keywords_extracted' => '',
                    'book_count' => 0,
                    'show_books' => false,
                    'timestamp' => now()->format('H:i:s')
                ],
                'model' => $model
            ];

            if ($asJson) {
                $chatHistory = $this->getChatHistory();
                session()->flash('ai_answer', $aiData['answer']);
                session()->flash('ai_q', '');
                session()->flash('ai_question', $question);
                session()->flash('ai_mode', $mode);
                session()->flash('ai_suggestions', $responseData['suggestions']);
                session()->flash('ai_follow_up', $aiData['follow_up'] ?? '');
                session()->flash('ai_books', []);
                session()->flash('ai_show_books', false);
                session()->flash('external_items', []);
                session()->flash('external_source', null);
                session()->flash('external_error', null);
                session()->flash('chat_history', $chatHistory);

                return response()->json($responseData);
            }

            session()->flash('ai_answer', $aiData['answer']);
            session()->flash('ai_q', '');
            session()->flash('ai_question', $question);
            session()->flash('ai_mode', $mode);
            session()->flash('ai_suggestions', $responseData['suggestions']);
            session()->flash('ai_follow_up', $aiData['follow_up'] ?? '');
            session()->flash('ai_books', []);
            session()->flash('ai_show_books', false);
            session()->flash('external_items', []);
            session()->flash('external_source', null);
            session()->flash('external_error', null);
            session()->flash('chat_history', $this->getChatHistory());

            return redirect()->route('member.ai_search.form');
        }

        $keywords = $this->extractKeywordsFromQuestion($question);
        $lastKeywords = (string) session('ai_last_keywords', '');
        
        // DAPATKAN CHAT HISTORY untuk konteks
        $chatHistory = $this->getChatHistory();

        if (str_word_count($question) >= 5 && $mode === '') {
            $mode = 'chat';
            session(['ai_mode' => 'chat']);
        }

        $useCatalog = $this->shouldUseCatalogAnswer($question, $mode);
        $isDetail = $this->isDetailRequest($question);
        $detailTitle = $isDetail ? $this->findTitleInQuestion($question) : '';
        if ($isDetail && $detailTitle === '') {
            $detailTitle = $this->resolveTitleFromLastBooks($question);
        }
        $searchKeywords = $keywords;
        if ($useCatalog && $searchKeywords === '' && $lastKeywords !== '') {
            $searchKeywords = $lastKeywords;
        }

        $shouldShowBooks = $mode === 'search' || $this->shouldShowBooks($question) || $useCatalog;

        $books = $shouldShowBooks && $searchKeywords !== '' ? $this->searchBooks($searchKeywords) : [];

        if ($useCatalog) {
            if ($isDetail && $detailTitle !== '') {
                $detailBooks = $this->searchBooks($detailTitle);
                $selected = $detailBooks[0] ?? $this->getLastBookByTitle($detailTitle);
                if ($selected && empty($detailBooks)) {
                    $detailBooks = [$selected];
                }
                $externalResult = $this->searchExternalBooks($detailTitle);
                $externalBest = !empty($externalResult['items'])
                    ? $this->pickBestExternalMatch($detailTitle, $externalResult['items'])
                    : [];
                if ($selected || !empty($externalBest)) {
                    $answerText = $selected ? $this->buildSingleBookAnswer($selected) : '';
                    if (!empty($externalBest)) {
                        $answerText = $this->buildSingleExternalAnswer($externalBest, $externalResult['source_label'] ?? 'sumber eksternal');
                        $external = $externalResult;
                    }
                    $aiData = [
                        'answer' => $answerText,
                        'search_keywords' => $detailTitle,
                        'suggestions' => $this->generateSuggestions($detailTitle, $mode),
                        'follow_up' => 'Apakah Anda ingin melihat detail buku ini di katalog?'
                    ];
                    $books = $detailBooks;
                    $shouldShowBooks = true;
                }
            }

            if ($isDetail && $detailTitle === '') {
                $matches = $this->getTitleMatchesFromLastBooks($question);
                if (count($matches) === 1) {
                    $detailTitle = $matches[0]['title'];
                } elseif (count($matches) > 1) {
                    $choices = array_map(fn($m) => $m['title'], $matches);
                    $aiData = [
                        'answer' => "Judul yang Anda maksud belum jelas. Pilih salah satu:\n- " . implode("\n- ", $choices),
                        'search_keywords' => $searchKeywords,
                        'suggestions' => $this->generateSuggestions($searchKeywords, $mode),
                        'follow_up' => 'Tulis judul buku yang ingin dibahas.'
                    ];
                }

                if ($detailTitle !== '') {
                    $detailBooks = $this->searchBooks($detailTitle);
                    $selected = $detailBooks[0] ?? $this->getLastBookByTitle($detailTitle);
                    if ($selected && empty($detailBooks)) {
                        $detailBooks = [$selected];
                    }
                    $externalResult = $this->searchExternalBooks($detailTitle);
                    $externalBest = !empty($externalResult['items'])
                        ? $this->pickBestExternalMatch($detailTitle, $externalResult['items'])
                        : [];
                    if ($selected || !empty($externalBest)) {
                        $answerText = $selected ? $this->buildSingleBookAnswer($selected) : '';
                        if (!empty($externalBest)) {
                            $answerText = $this->buildSingleExternalAnswer($externalBest, $externalResult['source_label'] ?? 'sumber eksternal');
                            $external = $externalResult;
                        }
                        $aiData = [
                            'answer' => $answerText,
                            'search_keywords' => $detailTitle,
                            'suggestions' => $this->generateSuggestions($detailTitle, $mode),
                            'follow_up' => 'Apakah Anda ingin melihat detail buku ini di katalog?'
                        ];
                        $books = $detailBooks;
                        $shouldShowBooks = true;
                    }
                }
            }

            if ($isDetail && $detailTitle !== '' && !isset($aiData)) {
                $selected = $this->getLastBookByTitle($detailTitle);
                if ($selected) {
                    $aiData = [
                        'answer' => $this->buildSingleBookAnswer($selected),
                        'search_keywords' => $detailTitle,
                        'suggestions' => $this->generateSuggestions($detailTitle, $mode),
                        'follow_up' => 'Apakah Anda ingin melihat detail buku ini di katalog?'
                    ];
                    $books = [$selected];
                    $shouldShowBooks = true;
                }
            }

            if (!isset($aiData) && count($books) > 0) {
                $aiData = [
                    'answer' => $this->buildCatalogAnswer($books, $question, $searchKeywords),
                    'search_keywords' => $searchKeywords,
                    'suggestions' => $this->generateSuggestions($searchKeywords, $mode),
                    'follow_up' => 'Apakah Anda ingin melihat detail buku tertentu di katalog?'
                ];
            } else {
                if ($isDetail) {
                    if ($detailTitle !== '') {
                        $externalResult = $this->searchExternalBooks($detailTitle);
                        if (!empty($externalResult['items'])) {
                            $bestExternal = $this->pickBestExternalMatch($detailTitle, $externalResult['items']);
                            $aiData = [
                                'answer' => $this->buildSingleExternalAnswer($bestExternal, $externalResult['source_label'] ?? 'sumber eksternal'),
                                'search_keywords' => $detailTitle,
                                'suggestions' => $this->generateSuggestions($detailTitle, $mode),
                                'follow_up' => 'Ingin saya carikan judul lain, atau coba kata kunci berbeda?'
                            ];
                            $external = $externalResult;
                            $books = [];
                            $shouldShowBooks = false;
                        }
                    }

                    if (isset($aiData)) {
                        // external answer already prepared for detail request
                    } else {
                    $lastBooks = session('ai_last_books', []);
                    $suggest = [];
                    if (is_array($lastBooks)) {
                        $suggest = array_slice(array_values(array_filter($lastBooks)), 0, 3);
                    }
                    $message = 'Judul tersebut tidak saya temukan di hasil terakhir.';
                    if (!empty($suggest)) {
                        $message .= " Pilih salah satu:\n- " . implode("\n- ", $suggest);
                    } else {
                        $message .= ' Sebutkan judul yang ada di daftar sebelumnya.';
                    }
                    if (!empty($detailTitle)) {
                        $message .= " Jika maksud Anda \"$detailTitle\", hasil katalog belum tersedia.";
                    }
                    $aiData = [
                        'answer' => $message,
                        'search_keywords' => $searchKeywords,
                        'suggestions' => $this->generateSuggestions($searchKeywords, $mode),
                        'follow_up' => 'Buku mana yang ingin Anda bahas lebih detail?'
                    ];
                    }
                } else {
                    $external = $this->searchExternalBooks($searchKeywords);
                    if (!empty($external['items'])) {
                        $aiData = [
                            'answer' => $this->buildExternalAnswer($external['items'], $searchKeywords, $external['source_label']),
                            'search_keywords' => $searchKeywords,
                            'suggestions' => $this->generateSuggestions($searchKeywords, $mode),
                            'follow_up' => 'Ingin saya carikan judul lain di luar katalog, atau coba kata kunci berbeda?'
                        ];
                    } elseif (!empty($external['error'])) {
                        $aiData = [
                            'answer' => "Katalog NOTOBUKU belum memiliki judul yang cocok untuk \"$searchKeywords\", dan pencarian eksternal sedang tidak tersedia. Coba gunakan kata kunci lain atau ulangi beberapa saat lagi.",
                            'search_keywords' => $searchKeywords,
                            'suggestions' => $this->generateSuggestions($searchKeywords, $mode),
                            'follow_up' => 'Kata kunci lain apa yang ingin Anda coba?'
                        ];
                    } else {
                        $aiData = [
                            'answer' => "Maaf, belum ada buku yang cocok di katalog NOTOBUKU untuk \"$searchKeywords\". Coba gunakan kata kunci yang lebih spesifik atau kategori lain.",
                            'search_keywords' => $searchKeywords,
                            'suggestions' => $this->generateSuggestions($searchKeywords, $mode),
                            'follow_up' => 'Kata kunci lain apa yang ingin Anda coba?'
                        ];
                    }
                }
            }
        } else {
            $aiData = $this->getAiResponse($ollamaUrl, $model, $question, $keywords, $mode, $chatHistory);
            $searchKeywords = !empty($aiData['search_keywords']) ? $aiData['search_keywords'] : $keywords;
            $books = $shouldShowBooks && $searchKeywords !== '' ? $this->searchBooks($searchKeywords) : [];
        }

        if ($searchKeywords !== '' && count($books) > 0) {
            session(['ai_last_keywords' => $searchKeywords]);
            session(['ai_last_books' => array_map(fn($b) => $b['title'] ?? '', $books)]);
            session(['ai_last_items' => $books]);
        }
        
        $finalAnswer = $aiData['answer'];
        if (session('last_ai_answer') === $finalAnswer) {
            $finalAnswer = 'Baik, saya pahami. Bisa Anda jelaskan lebih spesifik kebutuhannya?';
            $aiData['answer'] = $finalAnswer;
        }
        session(['last_ai_answer' => $finalAnswer]);

        $this->addToChatHistory([
            'role' => 'ai',
            'content' => $finalAnswer,
            'keywords' => $aiData['search_keywords'] ?? $keywords,
            'timestamp' => now()->format('H:i:s'),
            'mode' => $mode
        ]);
        
        $personalizedSuggestions = $this->getPersonalizedSuggestions($searchKeywords, $mode);
        
        $responseData = [
            'ok' => true,
            'answer' => $finalAnswer,
            'q' => $searchKeywords,
            'question' => $question,
            'mode' => $mode,
            'show_books' => $shouldShowBooks && count($books) > 0,
            'catalog_url' => route('katalog.index', ['q' => urlencode($searchKeywords), 'ai' => 1]),
            'items' => $books,
            'external_items' => $external['items'] ?? [],
            'external_source' => $external['source_label'] ?? null,
            'external_error' => $external['error'] ?? null,
            'suggestions' => array_merge($aiData['suggestions'], $personalizedSuggestions),
            'follow_up_question' => $aiData['follow_up'],
            'ai_online' => $this->checkOllama($ollamaUrl),
            'ai_context' => [
                'mode' => $mode,
                'keywords_extracted' => $searchKeywords,
                'book_count' => count($books),
                'show_books' => $shouldShowBooks,
                'timestamp' => now()->format('H:i:s')
            ],
            'model' => $model
        ];

        if ($asJson) {
            $chatHistory = $this->getChatHistory();
            // Simpan flash data agar UI bisa render hasil tanpa memanggil AI lagi
            session()->flash('ai_answer', $finalAnswer);
            session()->flash('ai_q', $searchKeywords);
            session()->flash('ai_question', $question);
            session()->flash('ai_mode', $mode);
            session()->flash('ai_suggestions', $responseData['suggestions']);
            session()->flash('ai_follow_up', $aiData['follow_up']);
            session()->flash('ai_books', $books);
            session()->flash('ai_show_books', $shouldShowBooks);
            session()->flash('external_items', $external['items'] ?? []);
            session()->flash('external_source', $external['source_label'] ?? null);
            session()->flash('external_error', $external['error'] ?? null);
            session()->flash('chat_history', $chatHistory);

            return response()->json($responseData);
        }

        $chatHistory = $this->getChatHistory();
        
        session()->flash('ai_answer', $finalAnswer);
        session()->flash('ai_q', $searchKeywords);
        session()->flash('ai_question', $question);
        session()->flash('ai_mode', $mode);
        session()->flash('ai_suggestions', $responseData['suggestions']);
        session()->flash('ai_follow_up', $aiData['follow_up']);
        session()->flash('ai_books', $books);
        session()->flash('ai_show_books', $shouldShowBooks);
        session()->flash('external_items', $external['items'] ?? []);
        session()->flash('external_source', $external['source_label'] ?? null);
        session()->flash('external_error', $external['error'] ?? null);
        session()->flash('chat_history', $chatHistory);

        return redirect()->route('member.ai_search.form');
    }

    private function getChatHistory(): array
    {
        $history = session('ai_chat_history', []);
        
        if (count($history) > 20) {
            $history = array_slice($history, -20);
            session(['ai_chat_history' => $history]);
        }
        
        return $history;
    }
    
    private function addToChatHistory(array $message): void
    {
        $history = $this->getChatHistory();
        $history[] = $message;
        session(['ai_chat_history' => $history]);
    }
    
    private function clearChatHistory(): void
    {
        session()->forget('ai_chat_history');
        session()->forget('ai_last_books');
        session()->forget('ai_last_items');
        session()->flash('chat_cleared', true);
    }

    private function getAiResponse(string $ollamaUrl, string $model, string $question, string $keywords, string $mode, array $chatHistory = []): array
    {
        if ($mode === 'chat') {
            $default = [
                'answer' => "Baik. Saya siap membantu. Anda ingin konsultasi (diskusi/topik/skripsi) atau pencarian buku di katalog? Ceritakan tujuan Anda secara singkat.",
                'search_keywords' => $keywords,
                'suggestions' => $this->generateSuggestions($keywords, $mode),
                'follow_up' => "Apakah ada hal lain yang ingin Anda tanyakan tentang NOTOBUKU?"
            ];
        } else {
            $default = [
                'answer' => "Saya akan membantu Anda mencari \"" . e($keywords) . "\" di perpustakaan NOTOBUKU.",
                'search_keywords' => $keywords,
                'suggestions' => $this->generateSuggestions($keywords, $mode),
                'follow_up' => "Apakah ada topik spesifik yang ingin Anda eksplorasi?"
            ];
        }

        if (!$this->checkOllama($ollamaUrl)) {
            Log::warning('Ollama not available');
            $fallback = $this->basicIntentResponse($question, $mode, $keywords);
            if ($fallback !== null) {
                return $fallback;
            }
            return $default;
        }

        // BUILD PROMPT DENGAN CHAT HISTORY
        $prompt = $this->buildEnhancedPrompt($question, $mode, $keywords, $chatHistory);
        
        try {
            Log::debug('Sending to Ollama', ['prompt_length' => strlen($prompt), 'mode' => $mode]);
            
            $timeout = (int) env('OLLAMA_TIMEOUT', 120);
            $maxTokens = (int) env('OLLAMA_MAX_TOKENS', 240);

            // Retry 1x untuk kasus Ollama "hang" sesaat.
            $response = Http::retry(1, 250)
                ->connectTimeout(5)
                ->timeout($timeout)
                ->post($ollamaUrl . '/api/generate', [
                    'model' => $model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => $mode === 'chat' ? 0.65 : 0.25,
                        'num_predict' => $mode === 'chat' ? $maxTokens : min(180, $maxTokens),
                    ],
                ]);

            if ($response->ok()) {
                $text = (string) ($response->json('response') ?? '');
                Log::debug('Ollama raw response', ['text' => $text, 'mode' => $mode]);

                $parsed = $this->parseAiResponseEnhanced($text, $keywords, $question, $mode, $chatHistory);
                
                if ($parsed) {
                    if ($this->isRefusalResponse($parsed['answer'])) {
                        $fallback = $this->basicIntentResponse($question, $mode, $keywords);
                        if ($fallback !== null) {
                            return $fallback;
                        }
                        return $default;
                    }
                    Log::info('AI response parsed', [
                        'mode' => $mode,
                        'keywords' => $parsed['search_keywords'],
                        'answer_length' => strlen($parsed['answer'])
                    ]);
                    
                    return [
                        'answer' => $parsed['answer'],
                        'search_keywords' => $parsed['search_keywords'],
                        'suggestions' => $this->generateSuggestions($parsed['search_keywords'], $mode),
                        'follow_up' => $parsed['follow_up']
                    ];
                }
                
                Log::warning('Parsing failed, using raw response');
                if ($this->isRefusalResponse($text)) {
                    $fallback = $this->basicIntentResponse($question, $mode, $keywords);
                    if ($fallback !== null) {
                        return $fallback;
                    }
                    return $default;
                }
                return [
                    'answer' => $this->cleanAiResponse($text),
                    'search_keywords' => $keywords,
                    'suggestions' => $this->generateSuggestions($keywords, $mode),
                    'follow_up' => $mode === 'chat' ? "Apakah ada hal lain?" : "Apakah informasi ini membantu?"
                ];
            }
        } catch (\Throwable $e) {
            Log::error('Ollama error', ['error' => $e->getMessage(), 'mode' => $mode]);
        }

        return $default;
    }

    private function buildEnhancedPrompt(string $question, string $mode, string $extractedKeywords, array $chatHistory = []): string
    {
        $baseContext = "Kamu adalah pustakawan AI untuk NOTOBUKU. Jawab dalam bahasa Indonesia, jelas, rapi, dan terstruktur. Jangan mengarang judul buku. Jika diminta rekomendasi, gunakan hasil katalog yang diberikan sistem. Jika info kurang, ajukan pertanyaan klarifikasi. ";
        
        if ($mode === 'chat') {
            $context = $baseContext . "
            Tugas kamu sebagai teman bicara/konsultan perpustakaan:
            1. Dengarkan dan pahami percakapan dengan pengguna
            2. Ingat konteks percakapan sebelumnya
            3. Berikan saran, rekomendasi, atau informasi tentang perpustakaan dengan ramah
            4. Jika relevan, sarankan buku dari koleksi NOTOBUKU
            5. Jawab dengan natural seperti manusia (1-4 kalimat)
            6. JANGAN ulang-introduksi diri kecuali diminta
            7. Format output: JAWABAN [KATA_KUNCI_OPSIONAL]
            8. Hindari jawaban generik seperti 'Saya tidak bisa membantu.'
            9. Jika menjawab, gunakan format berikut:
               - Ringkasan singkat (1-2 kalimat)
               - Saran tindak lanjut (1 kalimat)
            
            Koleksi NOTOBUKU mencakup:
            - Buku pemrograman & teknologi (Python, JavaScript, PHP, Laravel, Web Development)
            - Novel & fiksi (romance, misteri, thriller, fantasi, sastra Indonesia)
            - Buku akademik & pendidikan (matematika, sains, ekonomi, bisnis)
            - Buku self-improvement & motivasi
            - Buku agama & spiritualitas
            
            Contoh percakapan kontekstual:
            - Pengguna: 'halo'
            - Asisten: 'Halo! Ada yang bisa saya bantu?'
            - Pengguna: 'siapa namamu?'
            - Asisten: 'Saya asisten AI untuk NOTOBUKU, nama saya Pustakawan Digital. Siap membantu Anda!'
            - Pengguna: 'apa tugasmu?'
            - Asisten: 'Tugas saya membantu Anda menemukan buku, memberikan rekomendasi, dan menjawab pertanyaan tentang perpustakaan NOTOBUKU.'
            ";
        } else {
            $context = $baseContext . "
            Tugas kamu sebagai pencari buku khusus:
            1. Analisis permintaan pencarian buku dengan tepat
            2. Tentukan KATA KUNCI PENCARIAN yang paling relevan untuk sistem katalog (maksimal 3 kata)
            3. Berikan jawaban SINGKAT tentang ketersediaan koleksi NOTOBUKU (1-2 kalimat)
            4. Format output HARUS: [KATA_KUNCI_PENCARIAN] [JAWABAN_SINGKAT]
            5. Hindari jawaban generik seperti 'Saya tidak bisa membantu.'
            6. Jangan mengarang judul buku.
            
            Koleksi NOTOBUKU mencakup:
            - Buku pemrograman & teknologi (Python, JavaScript, PHP, Laravel, Web Development)
            - Novel & fiksi (romance, misteri, thriller, fantasi, sastra Indonesia)
            - Buku akademik & pendidikan (matematika, sains, ekonomi, bisnis)
            - Buku self-improvement & motivasi
            - Buku agama & spiritualitas
            
            Contoh format mode search:
            - Pertanyaan: 'cari buku belajar pemrograman web untuk pemula'
            - Output: [pemrograman web pemula] [NOTOBUKU memiliki beberapa buku pemrograman web untuk pemula, termasuk HTML/CSS, JavaScript, dan framework modern.]
            
            - Pertanyaan: 'novel romance terbaru'
            - Output: [novel romance] [Berikut rekomendasi novel romance terbaru di koleksi NOTOBUKU. Beberapa judul populer tersedia.]
            ";
        }
        
        // TAMBAHKAN CHAT HISTORY KE PROMPT (hanya beberapa pesan terakhir)
        if (count($chatHistory) > 1) {
            $context .= "\n\n=== RIWAYAT PERCAKAPAN ===\n";
            
            // Ambil maksimal 6 pesan terakhir (3 pasang user-ai)
            $recentHistory = array_slice($chatHistory, -6);
            
            foreach ($recentHistory as $msg) {
                $role = $msg['role'] === 'user' ? 'PENGGUNA' : 'ASISTEN';
                $content = $msg['content'];
                $context .= "{$role}: {$content}\n";
            }
            
            $context .= "=== AKHIR RIWAYAT ===\n\n";
        }
        
        $context .= "\nKata kunci yang diekstrak: \"{$extractedKeywords}\"";
        $context .= "\nMode: {$mode}";
        
        if (count($chatHistory) > 1) {
            $context .= "\n\nSekarang jawab percakapan terbaru pengguna: \"{$question}\"";
            $context .= "\n\nIngat: Jawab sesuai konteks percakapan sebelumnya, jangan ulang-introduksi kecuali diminta.";
        } else {
            $context .= "\n\nSekarang jawab pertanyaan pengguna: \"{$question}\"";
        }
        
        return $context;
    }

    private function parseAiResponseEnhanced(string $text, string $fallbackKeywords, string $originalQuestion, string $mode, array $chatHistory = []): ?array
    {
        $text = trim($text);
        Log::debug('Parsing AI response', ['text' => $text, 'mode' => $mode]);
        
        if ($mode === 'search') {
            if (preg_match('/\[([^\]]+)\]\s*\[([^\]]+)\]/', $text, $matches)) {
                $keywords = trim($matches[1]);
                $answer = trim($matches[2]);
                
                return [
                    'answer' => $this->enhanceAnswerWithNOTOBUKU($answer, $chatHistory),
                    'search_keywords' => $this->cleanKeywords($keywords),
                    'follow_up' => "Ingin melihat koleksi lengkap di katalog NOTOBUKU?"
                ];
            }
        }
        
        // Untuk mode chat, kita ingin jawaban yang kontekstual
        // Hilangkan format parsing yang ketat untuk chat
        if ($mode === 'chat') {
            $answer = $this->cleanAiResponse($text);
            
            // Cek jika ada kata kunci dalam kurung siku
            if (preg_match('/\[([^\]]+)\]/', $answer, $matches)) {
                $keywords = trim($matches[1]);
                $answer = str_replace("[$keywords]", "", $answer);
                $answer = trim($answer);
            } else {
                $keywords = $this->extractKeywordsFromText($answer) ?: $fallbackKeywords;
            }
            
            return [
                'answer' => $this->enhanceAnswerWithNOTOBUKU($answer, $chatHistory),
                'search_keywords' => $this->cleanKeywords($keywords),
                'follow_up' => $this->generateFollowUp($originalQuestion, $mode, $chatHistory)
            ];
        }
        
        // Fallback untuk mode search
        if (preg_match('/kata kunci.*?[:"]\s*([^".]+)/i', $text, $matches)) {
            $keywords = trim($matches[1]);
            $answer = $this->extractAnswerFromText($text);
            
            return [
                'answer' => $this->enhanceAnswerWithNOTOBUKU($answer, $chatHistory),
                'search_keywords' => $this->cleanKeywords($keywords),
                'follow_up' => $mode === 'chat' ? "Ada pertanyaan lain?" : "Mau cari dengan kata kunci ini di katalog?"
            ];
        }
        
        $extractedKeywords = $this->extractKeywordsFromText($text);
        
        return [
            'answer' => $this->enhanceAnswerWithNOTOBUKU($text, $chatHistory),
            'search_keywords' => $extractedKeywords ?: $fallbackKeywords,
            'follow_up' => $this->generateFollowUp($originalQuestion, $mode, $chatHistory)
        ];
    }

    private function enhanceAnswerWithNOTOBUKU(string $answer, array $chatHistory = []): string
    {
        $answer = trim($answer);
        
        // Cek apakah dalam chat history sudah ada introduksi
        $hasIntroduced = false;
        foreach ($chatHistory as $msg) {
            if ($msg['role'] === 'ai' && 
                (str_contains(strtolower($msg['content']), 'asisten') || 
                 str_contains(strtolower($msg['content']), 'notobuku'))) {
                $hasIntroduced = true;
                break;
            }
        }
        
        // Jika sudah ada introduksi dan answer pendek, jangan tambahkan "Di NOTOBUKU"
        if ($hasIntroduced && strlen($answer) < 100) {
            return $answer;
        }
        
        // Jika belum pernah menyebut NOTOBUKU sama sekali
        $mentionsNotobuku = false;
        foreach ($chatHistory as $msg) {
            if (str_contains(strtolower($msg['content']), 'notobuku')) {
                $mentionsNotobuku = true;
                break;
            }
        }
        
        if (!$mentionsNotobuku && !str_contains(strtolower($answer), 'notobuku')) {
            if ($this->containsAny(strtolower($answer), ['perpustakaan', 'koleksi', 'buku'])) {
                return $answer;
            }
            return "Di NOTOBUKU, " . lcfirst($answer);
        }
        
        return $answer;
    }

    private function generateFollowUp(string $question, string $mode, array $chatHistory = []): string
    {
        $question = strtolower(trim($question));
        
        // Cek jenis pertanyaan
        if ($this->containsAny($question, ['nama', 'siapa'])) {
            return "Ada hal lain tentang layanan NOTOBUKU yang ingin Anda tanyakan?";
        }
        
        if ($this->containsAny($question, ['tugas', 'fungsi', 'tujuan'])) {
            return "Apakah Anda ingin tahu lebih lanjut tentang koleksi buku kami?";
        }
        
        if ($mode === 'chat') {
            $recentQuestions = array_slice($chatHistory, -4);
            $questionCount = 0;
            foreach ($recentQuestions as $msg) {
                if ($msg['role'] === 'user') $questionCount++;
            }
            
            if ($questionCount >= 3) {
                return "Apakah ada topik spesifik tentang perpustakaan yang ingin Anda eksplorasi?";
            }
            
            return "Ada pertanyaan lain tentang NOTOBUKU?";
        } else {
            return "Apakah Anda ingin mencari lebih lanjut di katalog NOTOBUKU?";
        }
    }

    private function shouldShowBooks(string $question): bool
    {
        $bookRelatedKeywords = [
            'buku', 'novel', 'bacaan', 'referensi', 'materi', 'belajar',
            'panduan', 'tutorial', 'ajaran', 'pelajaran', 'modul',
            'kamus', 'ensiklopedi', 'komik', 'cerita', 'dongeng',
            'sejarah', 'sains', 'teknologi', 'programming', 'pemrograman',
            'coding', 'web', 'mobile', 'desain', 'art', 'seni',
            'bisnis', 'ekonomi', 'manajemen', 'marketing', 'keuangan',
            'kesehatan', 'medis', 'kedokteran', 'psikologi', 'filsafat',
            'agama', 'spiritual', 'motivasi', 'inspirasi', 'self-help'
        ];
        
        $question = strtolower($question);
        
        foreach ($bookRelatedKeywords as $keyword) {
            if (str_contains($question, $keyword)) {
                return true;
            }
        }
        
        return false;
    }

    private function extractKeywordsFromQuestion(string $question): string
    {
        $question = trim(mb_strtolower($question));
        if (empty($question)) return '';
        
        $removePhrases = [
            'tolong', 'carikan', 'cari', 'pencarian', 'saya', 'aku', 'ingin', 'mau',
            'buku', 'referensi', 'tentang', 'untuk', 'yang', 'dengan', 'di', 'ke',
            'dari', 'dan', 'atau', 'seputar', 'materi', 'ada', 'tidak', 'apakah',
            'bagaimana', 'bisa', 'boleh', 'mohon', 'butuh', 'perlu', 'mencari',
            'notobuku', 'perpustakaan', 'koleksi', 'punya', 'memiliki', 'bisa', 'bantu'
        ];
        
        $words = preg_split('/\s+/', $question);
        $keywords = [];
        
        foreach ($words as $word) {
            $word = trim(preg_replace('/[^\p{L}\p{N}]/u', '', $word));
            if (empty($word) || mb_strlen($word) < 2) continue;
            if (in_array($word, $removePhrases)) continue;
            $keywords[] = $word;
        }
        
        return implode(' ', array_slice($keywords, 0, 3));
    }

    private function extractKeywordsFromText(string $text): string
    {
        $text = strtolower($text);
        
        if (preg_match('/"([^"]+)"/', $text, $matches)) {
            return $this->cleanKeywords($matches[1]);
        }
        
        $keywordPatterns = [
            '/kata kunci.*?[:]\s*([^\n\.]+)/i',
            '/keywords?.*?[:]\s*([^\n\.]+)/i',
            '/cari.*?[:]\s*([^\n\.]+)/i',
            '/search.*?[:]\s*([^\n\.]+)/i',
            '/rekomendasi.*?[:]\s*([^\n\.]+)/i',
        ];
        
        foreach ($keywordPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $this->cleanKeywords(trim($matches[1]));
            }
        }
        
        return '';
    }

    private function extractAnswerFromText(string $text): string
    {
        $text = preg_replace('/Kata Kunci Pencarian.*?[:"].*?["\n]/i', '', $text);
        $text = preg_replace('/Jawaban Singkat.*?[:"]/i', '', $text);
        $text = preg_replace('/\[.*?\]/', '', $text);
        $text = preg_replace('/rekomendasi.*?[:]/i', '', $text);
        
        return trim($text);
    }

    private function cleanKeywords(string $keywords): string
    {
        $keywords = trim(mb_strtolower($keywords));
        $keywords = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $keywords);
        $keywords = preg_replace('/\s+/', ' ', $keywords);
        
        $stopWords = ['yang', 'dengan', 'untuk', 'dari', 'dan', 'atau', 'di', 'ke', 'pada', 'ada'];
        $words = explode(' ', $keywords);
        $words = array_diff($words, $stopWords);
        
        return implode(' ', array_slice($words, 0, 3));
    }

    private function cleanAiResponse(string $text): string
    {
        // Hapus format yang tidak perlu untuk chat
        $text = preg_replace('/\{.*?\}/', '', $text);
        $text = preg_replace('/\[.*?\]/', '', $text);
        $text = preg_replace('/".*?"/', '', $text);
        
        // Hapus prefix "Jawaban:" jika ada
        $text = preg_replace('/^(Jawaban|Answer|Response):\s*/i', '', $text);
        
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    private function generateSuggestions(string $keywords, string $mode): array
    {
        if (empty($keywords)) {
            if ($mode === 'chat') {
                return [
                    'Tanya tentang layanan perpustakaan',
                    'Minta rekomendasi bacaan',
                    'Tanya jam operasional'
                ];
            } else {
                return [
                    'Cari buku bestseller NOTOBUKU',
                    'Lihat koleksi baru',
                    'Rekomendasi berdasarkan minat'
                ];
            }
        }
        
        if ($mode === 'chat') {
            return [
                "Lanjutkan percakapan",
                "Minta rekomendasi spesifik",
                "Cari: " . $keywords
            ];
        } else {
            return [
                "Cari di NOTOBUKU: " . $keywords,
                "Lihat kategori terkait",
                "Cek ketersediaan eksemplar"
            ];
        }
    }

    private function getPersonalizedSuggestions(string $keywords, string $mode): array
    {
        $suggestions = [];
        
        if ($mode === 'chat') {
            $suggestions[] = "Konsultasi pustakawan AI";
            $suggestions[] = "Info layanan NOTOBUKU";
            $suggestions[] = "Buku rekomendasi hari ini";
        } else {
            $suggestions[] = "Pencarian lanjutan";
            $suggestions[] = "Statistik koleksi";
            $suggestions[] = "Buku baru di NOTOBUKU";
        }
        
        $lowerKeywords = strtolower($keywords);
        
        if (str_contains($lowerKeywords, 'pemrograman') || str_contains($lowerKeywords, 'programming')) {
            $suggestions[] = "Lihat koleksi teknologi & coding";
        }
        
        if (str_contains($lowerKeywords, 'novel') || str_contains($lowerKeywords, 'fiksi')) {
            $suggestions[] = "Eksplorasi koleksi fiksi & novel";
        }
        
        if (str_contains($lowerKeywords, 'belajar') || str_contains($lowerKeywords, 'pemula')) {
            $suggestions[] = "Buku untuk pemula & pembelajaran";
        }
        
        if (str_contains($lowerKeywords, 'bisnis') || str_contains($lowerKeywords, 'ekonomi')) {
            $suggestions[] = "Koleksi bisnis & ekonomi";
        }
        
        return array_slice($suggestions, 0, 3);
    }

        private function checkOllama(string $url): bool
    {
        // IMPORTANT:
        // - Jangan hardcode localhost:11434, karena Ollama bisa jalan di host/port lain (docker/service/remote).
        // - Pakai host & port dari $url (OLLAMA_URL).
        try {
            $parts = parse_url($url);
            $host = $parts['host'] ?? 'localhost';
            $port = (int) ($parts['port'] ?? 11434);

            $fp = @fsockopen($host, $port, $errno, $errstr, 2);
            if ($fp) {
                fclose($fp);
                return true;
            }

            Log::debug('Ollama socket check failed', [
                'host' => $host,
                'port' => $port,
                'errno' => $errno ?? null,
                'errstr' => $errstr ?? null,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::debug('Ollama socket check exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function isSimpleGreeting(string $text): bool
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        
        $greetings = [
            'halo', 'hai', 'hi', 'hello', 'hey',
            'apa kabar', 'selamat pagi', 'selamat siang', 
            'selamat sore', 'selamat malam', 'permisi'
        ];
        
        return in_array($text, $greetings, true);
    }

    private function simpleGreetingResponse(string $question, string $mode, bool $asJson): mixed
    {
        $text = strtolower(trim($question));
        $responses = [
            'halo' => 'Halo! Saya asisten perpustakaan AI untuk NOTOBUKU. Ada yang bisa saya bantu cari di koleksi kami?',
            'hai' => 'Hai! Senang membantu Anda menjelajahi koleksi NOTOBUKU. Ada buku spesifik yang ingin dicari?',
            'selamat malam' => 'Selamat malam! Siap membantu Anda menjelajahi NOTOBUKU sebelum tidur.',
            'selamat pagi' => 'Selamat pagi! Hari yang cerah untuk membaca koleksi NOTOBUKU.',
            'selamat siang' => 'Selamat siang! Mari jelajahi koleksi buku NOTOBUKU.',
            'selamat sore' => 'Selamat sore! Waktu yang tepat untuk membaca di NOTOBUKU.',
            'default' => 'Hai! Ada yang bisa saya bantu cari di perpustakaan NOTOBUKU?'
        ];
        
        $answer = $responses['default'];
        foreach ($responses as $key => $response) {
            if (str_contains($text, $key)) {
                $answer = $response;
                break;
            }
        }
        
        $this->addToChatHistory([
            'role' => 'ai',
            'content' => $answer,
            'timestamp' => now()->format('H:i:s'),
            'mode' => $mode
        ]);
        
        $data = [
            'ok' => true,
            'answer' => $answer,
            'q' => '',
            'question' => $question,
            'mode' => $mode,
            'show_books' => false,
            'catalog_url' => route('katalog.index'),
            'items' => [],
            'suggestions' => $mode === 'chat' ? 
                ['Tanya tentang layanan', 'Minta rekomendasi', 'Info perpustakaan'] : 
                ['Buku populer NOTOBUKU', 'Koleksi baru', 'Rekomendasi spesial'],
            'follow_up_question' => $mode === 'chat' ? 'Ada yang ingin Anda tanyakan tentang NOTOBUKU?' : 'Topik apa yang menarik minat Anda di NOTOBUKU?',
            'ai_context' => [
                'type' => 'greeting',
                'mode' => $mode,
                'timestamp' => now()->format('H:i:s')
            ]
        ];
        
        if ($asJson) {
            $chatHistory = $this->getChatHistory();
            session()->flash('ai_answer', $answer);
            session()->flash('ai_q', '');
            session()->flash('ai_question', $question);
            session()->flash('ai_mode', $mode);
            session()->flash('ai_suggestions', $data['suggestions']);
            session()->flash('ai_follow_up', $data['follow_up_question']);
            session()->flash('ai_show_books', false);
            session()->flash('chat_history', $chatHistory);

            return response()->json($data);
        }
        
        $chatHistory = $this->getChatHistory();
        
        session()->flash('ai_answer', $answer);
        session()->flash('ai_q', '');
        session()->flash('ai_question', $question);
        session()->flash('ai_mode', $mode);
        session()->flash('ai_suggestions', $data['suggestions']);
        session()->flash('ai_follow_up', $data['follow_up_question']);
        session()->flash('ai_show_books', false);
        session()->flash('chat_history', $chatHistory);
        
        return redirect()->route('member.ai_search.form');
    }

    private function searchBooks(string $query): array
    {
        if (trim($query) === '') {
            return [];
        }

        try {
            $terms = $this->expandSearchTerms($query);

            $books = Biblio::query()
                ->where('institution_id', $this->currentInstitutionId())
                ->with(['authors:id,name'])
                ->withCount([
                    'items',
                    'availableItems as available_items_count'
                ])
                ->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $q->orWhere('title', 'like', "%{$term}%")
                          ->orWhere('subtitle', 'like', "%{$term}%")
                          ->orWhere('isbn', 'like', "%{$term}%")
                          ->orWhere('publisher', 'like', "%{$term}%")
                          ->orWhere('notes', 'like', "%{$term}%")
                          ->orWhere('general_note', 'like', "%{$term}%")
                          ->orWhere('bibliography_note', 'like', "%{$term}%")
                          ->orWhere('ai_summary', 'like', "%{$term}%")
                          ->orWhereHas('authors', function ($a) use ($term) {
                              $a->where('name', 'like', "%{$term}%");
                          })
                          ->orWhereHas('subjects', function ($s) use ($term) {
                              $s->where('term', 'like', "%{$term}%")
                                ->orWhere('name', 'like', "%{$term}%");
                          });
                    }
                })
                ->orderBy('title')
                ->limit(6)
                ->get()
                ->map(function ($biblio) {
                    $summary = (string) ($biblio->ai_summary
                        ?? $biblio->notes
                        ?? $biblio->general_note
                        ?? $biblio->bibliography_note
                        ?? '');
                    $summary = html_entity_decode(strip_tags($summary));
                    $summary = trim(preg_replace('/\s+/', ' ', $summary));

                    return [
                        'id' => $biblio->id,
                        'title' => (string) $biblio->title,
                        'subtitle' => (string) ($biblio->subtitle ?? ''),
                        'authors' => $biblio->authors->pluck('name')->toArray(),
                        'available' => (int) ($biblio->available_items_count ?? 0),
                        'total' => (int) ($biblio->items_count ?? 0),
                        'url' => route('katalog.show', $biblio->id),
                        'cover_url' => $biblio->cover_path 
                            ? Storage::disk('public')->url($biblio->cover_path)
                            : null,
                        'isbn' => $biblio->isbn ?? null,
                        'publisher' => $biblio->publisher ?? null,
                        'year' => $biblio->publish_year ?? null,
                        'summary' => trim($summary),
                    ];
                })
                ->toArray();
                
            Log::info('AI Book search results', ['query' => $query, 'count' => count($books)]);
            return $books;
            
        } catch (\Throwable $e) {
            Log::error('AI Book search failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function currentInstitutionId(): int
    {
        return (int) (auth()->user()->institution_id ?? 1);
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function shouldUseCatalogAnswer(string $question, string $mode): bool
    {
        if ($mode === 'search') {
            return true;
        }

        $text = strtolower(trim($question));
        if ($this->containsAny($text, ['mana', 'judul', 'judulnya', 'judul buku', 'buku apa'])) {
            return true;
        }

        return $this->containsAny($text, [
            'rekomendasi',
            'rekomendasikan',
            'sarankan',
            'saran buku',
            'judul buku',
            'buku apa',
            'cari buku'
        ]) || $this->shouldShowBooks($question);
    }

    private function buildCatalogAnswer(array $books, string $question, string $keywords): string
    {
        $lines = [];
        $title = trim($keywords) !== '' ? $keywords : trim($question);
        $lines[] = "Ringkasan: Berikut hasil katalog NOTOBUKU untuk \"$title\".";
        $lines[] = "Daftar buku:";

        $count = 0;
        foreach ($books as $book) {
            $count++;
            if ($count > 3) break;

            $line = ($count) . ". " . $book['title'];
            if (!empty($book['authors'])) {
                $line .= " (Penulis: " . implode(', ', $book['authors']) . ")";
            }
            if (!empty($book['year'])) {
                $line .= " - " . $book['year'];
            }

            $highlight = $this->extractBookHighlights($book);
            if ($highlight !== '') {
                $line .= ": " . $highlight;
            } else {
                $line .= ": Ringkasan isi belum tersedia di katalog.";
            }

            $lines[] = $line;
        }

        $lines[] = "Saran: Tulis judul buku yang ingin dibahas lebih detail.";
        return implode("\n", $lines);
    }

    private function extractBookHighlights(array $book): string
    {
        $summary = trim((string) ($book['summary'] ?? ''));
        if ($summary === '') {
            return '';
        }

        $summary = html_entity_decode(strip_tags($summary));
        $summary = preg_replace('/\s+/', ' ', $summary);
        $parts = preg_split('/[.!?]\s+/', $summary);
        $parts = array_values(array_filter($parts, fn($x) => trim($x) !== ''));
        $snippet = implode('. ', array_slice($parts, 0, 2));

        if ($snippet === '') {
            $snippet = $summary;
        }

        if (mb_strlen($snippet) > 200) {
            $snippet = mb_substr($snippet, 0, 200) . '...';
        }

        return $snippet;
    }

    private function isDetailRequest(string $question): bool
    {
        $text = strtolower(trim($question));
        return $this->containsAny($text, ['apa isi', 'isi buku', 'ringkasan', 'sinopsis', 'tentang buku', 'ceritakan buku']);
    }

    private function findTitleInQuestion(string $question): string
    {
        $question = trim($question);
        $history = array_reverse($this->getChatHistory());

        foreach ($history as $msg) {
            if (($msg['role'] ?? '') !== 'ai') continue;
            $content = (string) ($msg['content'] ?? '');
            if (preg_match('/^(?:-|\d+\.)\\s+(.+?)(\\s+\\(Penulis:|\\s+-\\s+\\d{4}|:)/m', $content, $m)) {
                $title = trim($m[1]);
                if ($title !== '' && stripos($question, $title) !== false) {
                    return $title;
                }
            }
        }

        if (preg_match('/buku\\s+dari\\s+(.+)/i', $question, $m)) {
            return $this->cleanTitleCandidate($m[1]);
        }
        if (preg_match('/buku\\s+(.+)/i', $question, $m)) {
            return $this->cleanTitleCandidate($m[1]);
        }
        if (preg_match('/\"([^\"]+)\"/', $question, $m)) {
            return $this->cleanTitleCandidate($m[1]);
        }

        return '';
    }

    private function cleanTitleCandidate(string $title): string
    {
        $title = trim($title);
        $title = preg_replace('/[^\p{L}\p{N}\s\-\:\(\)]/u', '', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        return trim($title);
    }

    private function isSuggestionIntent(string $question, string $mode): bool
    {
        $text = strtolower(trim($question));
        $intents = [
            'lanjutkan percakapan',
            'minta rekomendasi spesifik',
            'konsultasi pustakawan ai',
            'info layanan notobuku',
            'buku rekomendasi hari ini',
        ];

        foreach ($intents as $intent) {
            if ($text === $intent) {
                return true;
            }
        }

        return false;
    }

    private function suggestionIntentResponse(string $question, string $mode): array
    {
        $text = strtolower(trim($question));
        if ($text === 'minta rekomendasi spesifik') {
            return [
                'answer' => 'Topik apa yang Anda cari? Contoh: filsafat, teknologi, ekonomi, atau sastra.',
                'search_keywords' => '',
                'suggestions' => $this->generateSuggestions('', $mode),
                'follow_up' => 'Sebutkan topik atau kata kunci yang diinginkan.'
            ];
        }

        if ($text === 'info layanan notobuku') {
            return [
                'answer' => 'Layanan NOTOBUKU meliputi pencarian katalog, peminjaman, reservasi, dan notifikasi. Ada layanan tertentu yang ingin Anda ketahui?',
                'search_keywords' => '',
                'suggestions' => $this->generateSuggestions('', $mode),
                'follow_up' => 'Ingin tahu tentang peminjaman, reservasi, atau jam layanan?'
            ];
        }

        if ($text === 'konsultasi pustakawan ai' || $text === 'lanjutkan percakapan') {
            return [
                'answer' => 'Baik, silakan jelaskan kebutuhan Anda. Saya bantu mencarikan buku yang tepat.',
                'search_keywords' => '',
                'suggestions' => $this->generateSuggestions('', $mode),
                'follow_up' => 'Topik apa yang ingin Anda eksplorasi?'
            ];
        }

        if ($text === 'buku rekomendasi hari ini') {
            return [
                'answer' => 'Sebutkan minat Anda agar rekomendasi lebih tepat. Contoh: filsafat, sosial, teknologi, atau sastra.',
                'search_keywords' => '',
                'suggestions' => $this->generateSuggestions('', $mode),
                'follow_up' => 'Minat Anda apa?'
            ];
        }

        return [
            'answer' => 'Baik, silakan jelaskan topik atau kebutuhan Anda.',
            'search_keywords' => '',
            'suggestions' => $this->generateSuggestions('', $mode),
            'follow_up' => 'Topik apa yang ingin Anda cari?'
        ];
    }

    private function matchExactTitleFromLastBooks(string $question): string
    {
        $titles = session('ai_last_books', []);
        if (!is_array($titles) || empty($titles)) return '';

        $q = mb_strtolower(trim($question));
        $q = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $q);
        $q = preg_replace('/\s+/', ' ', $q);

        foreach ($titles as $title) {
            $t = mb_strtolower((string) $title);
            $t = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t);
            $t = preg_replace('/\s+/', ' ', $t);
            if ($t !== '' && $q === $t) {
                return $title;
            }
        }

        return '';
    }

    private function resolveTitleFromLastBooks(string $question): string
    {
        $titles = session('ai_last_books', []);
        if (!is_array($titles) || empty($titles)) return '';

        $q = mb_strtolower($question);
        $q = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $q);
        $q = preg_replace('/\s+/', ' ', trim($q));

        foreach ($titles as $title) {
            $t = mb_strtolower((string) $title);
            $t = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t);
            $t = preg_replace('/\s+/', ' ', trim($t));
            if ($t !== '' && str_contains($q, $t)) {
                return $title;
            }
        }

        // Fallback: match by token overlap (at least 2 tokens)
        $qTokens = array_values(array_filter(preg_split('/\s+/', $q)));
        foreach ($titles as $title) {
            $t = mb_strtolower((string) $title);
            $t = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t);
            $t = preg_replace('/\s+/', ' ', trim($t));
            $tTokens = array_values(array_filter(preg_split('/\s+/', $t)));
            if (count($tTokens) < 2) continue;
            $overlap = array_intersect($qTokens, $tTokens);
            if (count($overlap) >= 2) {
                return $title;
            }
        }

        return '';
    }

    private function getLastBookByTitle(string $title): ?array
    {
        $items = session('ai_last_items', []);
        if (!is_array($items) || empty($items)) return null;

        $needle = $this->normalizeTitle($title);
        foreach ($items as $item) {
            $itemTitle = $this->normalizeTitle((string) ($item['title'] ?? ''));
            if ($itemTitle !== '' && $itemTitle === $needle) {
                return $item;
            }
        }

        foreach ($items as $item) {
            $itemTitle = $this->normalizeTitle((string) ($item['title'] ?? ''));
            if ($itemTitle !== '' && str_contains($itemTitle, $needle)) {
                return $item;
            }
        }

        return null;
    }

    private function getTitleMatchesFromLastBooks(string $question): array
    {
        $titles = session('ai_last_books', []);
        if (!is_array($titles) || empty($titles)) return [];

        $q = mb_strtolower($question);
        $q = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $q);
        $q = preg_replace('/\s+/', ' ', trim($q));
        $qTokens = array_values(array_filter(preg_split('/\s+/', $q)));

        $scored = [];
        foreach ($titles as $title) {
            $t = mb_strtolower((string) $title);
            $t = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t);
            $t = preg_replace('/\s+/', ' ', trim($t));
            if ($t === '') continue;

            if (str_contains($q, $t)) {
                $scored[] = ['title' => $title, 'score' => 99];
                continue;
            }

            $tTokens = array_values(array_filter(preg_split('/\s+/', $t)));
            if (count($tTokens) < 2) continue;
            $overlap = array_intersect($qTokens, $tTokens);
            $score = count($overlap);
            if ($score >= 2) {
                $scored[] = ['title' => $title, 'score' => $score];
            }
        }

        if (empty($scored)) return [];

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $top = $scored[0]['score'];
        $filtered = array_values(array_filter($scored, fn($x) => $x['score'] >= $top - 1));

        // Limit to 3 suggestions to avoid noise
        return array_slice($filtered, 0, 3);
    }

    private function buildSingleBookAnswer(array $book): string
    {
        $title = $book['title'] ?? 'Buku';
        $author = !empty($book['authors']) ? implode(', ', $book['authors']) : 'Tidak diketahui';
        $year = !empty($book['year']) ? $book['year'] : 'Tahun tidak diketahui';
        $highlight = $this->extractBookHighlights($book);
        if ($highlight === '') {
            $highlight = 'Ringkasan isi belum tersedia di katalog.';
        }

        $lines = [];
        $lines[] = "Judul: $title";
        $lines[] = "Penulis: $author";
        $lines[] = "Tahun: $year";
        $lines[] = "Ringkasan:";
        $lines[] = $highlight;
        $lines[] = "Saran: Apakah Anda ingin melihat detail buku ini di katalog?";
        return implode("\n", $lines);
    }

    private function pickBestExternalMatch(string $title, array $items): array
    {
        if (empty($items)) return [];

        $needle = $this->normalizeTitle($title);
        foreach ($items as $item) {
            $hay = $this->normalizeTitle((string) ($item['title'] ?? ''));
            if ($hay !== '' && $needle === $hay) {
                return $item;
            }
        }

        foreach ($items as $item) {
            $hay = $this->normalizeTitle((string) ($item['title'] ?? ''));
            if ($hay !== '' && str_contains($hay, $needle)) {
                return $item;
            }
        }

        return $items[0];
    }

    private function normalizeTitle(string $title): string
    {
        $title = mb_strtolower(trim($title));
        $title = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        return trim($title);
    }

    private function buildSingleExternalAnswer(array $item, string $sourceLabel): string
    {
        $title = $item['title'] ?? 'Buku';
        $author = !empty($item['authors']) ? (is_array($item['authors']) ? implode(', ', $item['authors']) : $item['authors']) : 'Tidak diketahui';
        $year = !empty($item['year']) ? $item['year'] : 'Tahun tidak diketahui';
        $summary = trim((string) ($item['summary'] ?? ''));
        if ($summary === '') {
            $summary = 'Ringkasan belum tersedia dari sumber.';
        }

        $lines = [];
        $lines[] = "Judul: $title";
        $lines[] = "Penulis: $author";
        $lines[] = "Tahun: $year";
        $lines[] = "Sumber: $sourceLabel";
        $lines[] = "Ringkasan:";
        $lines[] = $summary;
        $lines[] = "Saran: Jika judul ini tidak ada di katalog, saya bisa cari judul lain di luar katalog.";
        return implode("\n", $lines);
    }

    private function searchExternalBooks(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['items' => [], 'source_label' => null, 'error' => null];
        }

        if (!filter_var(env('AI_EXTERNAL_SEARCH', true), FILTER_VALIDATE_BOOLEAN)) {
            return ['items' => [], 'source_label' => null, 'error' => 'external_disabled'];
        }

        $cacheTtl = (int) env('AI_EXTERNAL_CACHE_TTL', 1800);
        $cacheKey = 'ai_ext_search_' . md5(mb_strtolower($query));

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['items'])) {
            return $cached;
        }

        $error = null;
        $google = $this->filterExternalItems($this->searchGoogleBooks($query), $query);
        if (!empty($google)) {
            $payload = ['items' => $google, 'source_label' => 'Google Books', 'error' => null];
            Cache::put($cacheKey, $payload, $cacheTtl);
            return $payload;
        }

        $openLibrary = $this->filterExternalItems($this->searchOpenLibrary($query), $query);
        if (!empty($openLibrary)) {
            $payload = ['items' => $openLibrary, 'source_label' => 'Open Library', 'error' => null];
            Cache::put($cacheKey, $payload, $cacheTtl);
            return $payload;
        }

        $payload = ['items' => [], 'source_label' => null, 'error' => $error];
        Cache::put($cacheKey, $payload, $cacheTtl);
        return $payload;
    }

    private function searchGoogleBooks(string $query): array
    {
        try {
            $params = [
                'q' => $query,
                'maxResults' => 5,
                'langRestrict' => 'id',
            ];

            $apiKey = env('GOOGLE_BOOKS_API_KEY', '');
            if ($apiKey !== '') {
                $params['key'] = $apiKey;
            }

            $response = Http::timeout(15)->get('https://www.googleapis.com/books/v1/volumes', $params);
            if (!$response->ok()) {
                return [];
            }

            $items = $response->json('items', []);
            $results = [];
            foreach ($items as $item) {
                $info = $item['volumeInfo'] ?? [];
                $title = (string) ($info['title'] ?? '');
                if ($title === '') continue;

                $authors = $info['authors'] ?? [];
                $published = (string) ($info['publishedDate'] ?? '');
                $desc = (string) ($info['description'] ?? '');
                $desc = html_entity_decode(strip_tags($desc));
                $desc = trim(preg_replace('/\s+/', ' ', $desc));
                if (mb_strlen($desc) > 240) {
                    $desc = mb_substr($desc, 0, 240) . '...';
                }

                $thumb = $info['imageLinks']['thumbnail'] ?? null;
                $infoLink = $info['infoLink'] ?? null;

                $results[] = [
                    'title' => $title,
                    'authors' => is_array($authors) ? $authors : [],
                    'year' => $published,
                    'summary' => $desc,
                    'cover_url' => $thumb,
                    'url' => $infoLink,
                    'source' => 'Google Books'
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            Log::warning('Google Books search failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function searchOpenLibrary(string $query): array
    {
        try {
            $response = Http::timeout(15)->get('https://openlibrary.org/search.json', [
                'q' => $query,
                'limit' => 5,
                'fields' => 'key,title,author_name,first_publish_year,cover_i,subject',
                'lang' => 'id'
            ]);
            if (!$response->ok()) {
                return [];
            }

            $docs = $response->json('docs', []);
            $results = [];
            foreach ($docs as $doc) {
                $title = (string) ($doc['title'] ?? '');
                if ($title === '') continue;
                $authors = $doc['author_name'] ?? [];
                $year = isset($doc['first_publish_year']) ? (string) $doc['first_publish_year'] : '';
                $subject = $doc['subject'] ?? '';
                if (is_array($subject)) {
                    $summary = implode(', ', array_slice($subject, 0, 8));
                } else {
                    $summary = (string) $subject;
                }
                $summary = trim($summary);
                if (mb_strlen($summary) > 240) {
                    $summary = mb_substr($summary, 0, 240) . '...';
                }

                $coverId = $doc['cover_i'] ?? null;
                $coverUrl = $coverId ? "https://covers.openlibrary.org/b/id/{$coverId}-M.jpg" : null;
                $key = $doc['key'] ?? null;
                $url = $key ? "https://openlibrary.org{$key}" : null;

                $results[] = [
                    'title' => $title,
                    'authors' => is_array($authors) ? $authors : [],
                    'year' => $year,
                    'summary' => $summary,
                    'cover_url' => $coverUrl,
                    'url' => $url,
                    'source' => 'Open Library'
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            Log::warning('Open Library search failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function buildExternalAnswer(array $items, string $query, string $sourceLabel): string
    {
        $label = $sourceLabel !== '' ? $sourceLabel : 'sumber eksternal';
        $lines = [];
        $lines[] = "Katalog NOTOBUKU belum memiliki judul yang cocok untuk \"$query\".";
        $lines[] = "Berikut rekomendasi dari $label:";

        $count = 0;
        foreach ($items as $item) {
            $count++;
            if ($count > 3) break;
            $line = "- " . $item['title'];
            if (!empty($item['authors'])) {
                $line .= " (Penulis: " . implode(', ', $item['authors']) . ")";
            }
            if (!empty($item['year'])) {
                $line .= " - " . $item['year'];
            }
            $summary = trim((string) ($item['summary'] ?? ''));
            if ($summary !== '') {
                $line .= ": " . $summary;
            } else {
                $line .= ": Ringkasan belum tersedia dari sumber.";
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function filterExternalItems(array $items, string $query): array
    {
        $terms = $this->expandSearchTerms($query);
        if (empty($terms)) return $items;

        $filtered = [];
        foreach ($items as $item) {
            $hay = mb_strtolower(
                trim(
                    ($item['title'] ?? '') . ' ' . ($item['summary'] ?? '')
                )
            );

            foreach ($terms as $term) {
                if (mb_strlen($term) < 4) continue;
                if (str_contains($hay, mb_strtolower($term))) {
                    $filtered[] = $item;
                    break;
                }
            }
        }

        return $filtered;
    }

    private function expandSearchTerms(string $query): array
    {
        $query = trim(mb_strtolower($query));
        if ($query === '') return [];

        $terms = [$query];
        $words = preg_split('/\s+/', $query);
        foreach ($words as $w) {
            if (mb_strlen($w) >= 3) {
                $terms[] = $w;
            }
        }

        $synonyms = [
            'filsafat' => ['philosophy', 'philosophical'],
            'neurosains' => ['neuroscience'],
            'psikologi' => ['psychology'],
            'ekonomi' => ['economics'],
            'politik' => ['politics', 'political'],
        ];

        foreach ($synonyms as $key => $alts) {
            if (str_contains($query, $key)) {
                foreach ($alts as $alt) {
                    $terms[] = $alt;
                }
            }
        }

        $unique = array_values(array_unique(array_filter($terms)));
        return $unique;
    }

    private function isRefusalResponse(string $text): bool
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return $this->containsAny($text, [
            'i cant help with that',
            'i cannot help with that',
            'i cant help',
            'i cannot help',
            'tidak bisa membantu',
            'tidak dapat membantu',
            'maaf saya tidak bisa',
            'maaf saya tidak dapat'
        ]);
    }

        private function basicIntentResponse(string $question, string $mode, string $keywords): ?array
    {
        $text = strtolower(trim($question));
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        // Fallback yang "tetap terasa pustakawan" saat Ollama down/timeout.
        // Jangan mengulang onboarding terus menerus — langsung tanggapi maksud user.

        if ($mode === 'chat') {
            // 1) Identitas & fungsi
            if ($this->containsAny($text, ['nama', 'namamu', 'siapa', 'name', 'your name'])) {
                return [
                    'answer' => 'Saya Pustakawan Digital untuk NOTOBUKU. Saya bantu konsultasi dan membantu mencari buku di katalog.',
                    'search_keywords' => $keywords,
                    'suggestions' => $this->generateSuggestions($keywords, $mode),
                    'follow_up' => 'Anda sedang butuh bantuan apa? (mis. cari buku, rekomendasi, atau informasi layanan)'
                ];
            }

            if ($this->containsAny($text, ['tugas', 'fungsi', 'tujuan', 'kamu bisa apa', 'kamu ngapain'])) {
                return [
                    'answer' => 'Saya bisa membantu: (1) konsultasi topik bacaan/riset, (2) mencari buku di katalog NOTOBUKU, dan (3) memberi rekomendasi berdasarkan kata kunci.',
                    'search_keywords' => $keywords,
                    'suggestions' => $this->generateSuggestions($keywords, $mode),
                    'follow_up' => 'Anda mau konsultasi atau langsung cari buku di katalog?'
                ];
            }

            // 2) Konteks akademik (skripsi/tugas/penelitian)
            if ($this->containsAny($text, ['skripsi', 'tugas akhir', 'tesis', 'disertasi', 'penelitian', 'paper', 'jurnal', 'makalah'])) {
                return [
                    'answer' => "Siap. Untuk kebutuhan akademik, Anda ingin bantuan di bagian mana: ide topik, rumusan masalah, kerangka teori, metode, atau referensi buku/jurnal?",
                    'search_keywords' => $keywords,
                    'suggestions' => $this->generateSuggestions($keywords, $mode),
                    'follow_up' => 'Jika boleh tahu, bidang/topiknya apa? (mis. literasi informasi, perpustakaan digital, layanan referensi, metadata)'
                ];
            }

            // 3) Jika user menyebut “ilmu informasi/perpustakaan”
            if ($this->containsAny($text, ['ilmu informasi', 'ilmu perpustakaan', 'informasi dan perpustakaan', 'perpustakaan', 'kepustakawanan'])) {
                return [
                    'answer' => "Baik. Untuk Ilmu Informasi & Perpustakaan, Anda ingin fokus ke topik apa? Contoh: literasi informasi, perpustakaan digital, pengelolaan koleksi, layanan pemustaka, atau temu kembali informasi.",
                    'search_keywords' => $keywords,
                    'suggestions' => $this->generateSuggestions($keywords, $mode),
                    'follow_up' => 'Kalau Anda sebutkan topiknya, saya bisa bantu buat kata kunci pencarian dan rekomendasi bacaan.'
                ];
            }
        }

        // Mode search: kalau user menulis kalimat panjang tapi AI down, tetap arahkan ke kata kunci.
        if ($mode === 'search') {
            if ($keywords === '') {
                $keywords = $this->extractKeywordsFromQuestion($question);
            }
            if ($keywords === '') {
                $keywords = trim($question);
            }

            return [
                'answer' => "Baik. Saya akan bantu cari dengan kata kunci: \"$keywords\".",
                'search_keywords' => $keywords,
                'suggestions' => $this->generateSuggestions($keywords, $mode),
                'follow_up' => "Silakan tekan tombol Cari, atau coba kata kunci yang lebih spesifik."
            ];
        }

        return null;
    }
}
