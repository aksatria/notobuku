<?php

namespace App\Services\PustakawanDigital;

class MockAiService extends BaseService
{
    private $mockResponses = [];
    private $expertKnowledge = [];
    
    public function __construct()
    {
        $this->loadMockResponses();
        $this->loadExpertKnowledge();
    }
    
    private function loadMockResponses(): void
    {
        $this->mockResponses = [
            // Programming - dengan pengetahuan luas
            'python' => [
                'answer' => " **SEBAGAI AHLI LITERATUR PEMROGRAMAN:**\n\nBerdasarkan pengetahuan saya tentang literatur coding, ini buku Python TERBAIK yang pernah ada:\n\n **LEGENDARY CLASSICS:**\n1. **'Python Crash Course'** oleh Eric Matthes - Buku terlaris global untuk pemula\n2. **'Fluent Python'** oleh Luciano Ramalho - Masterpiece untuk profesional\n3. **'Automate the Boring Stuff'** oleh Al Sweigart - Revolusioner untuk otomasi\n\n **MODERN MASTERPIECES:**\n- **'Python Cookbook'** - Referensi komprehensif\n- **'Effective Python'** - Best practices dari expert\n- **'Deep Learning with Python'** - Pionir AI/ML\n\n **FUN FACT:** Buku Python sering jadi bestseller karena komunitasnya yang luas!",
                'confidence' => 0.95,
                'type' => 'expert_literature'
            ],
            'javascript' => [
                'answer' => " **EXPERTISE LITERATUR JAVASCRIPT:**\n\nSebagai ahli literatur web development, saya tahu buku JavaScript LEGENDARY ini:\n\n **FOUNDATIONAL WORKS:**\n1. **'JavaScript: The Good Parts'** oleh Douglas Crockford - Buku kultus yang membentuk JS modern\n2. **'Eloquent JavaScript'** oleh Marijn Haverbeke - Dianggap 'buku wajib' developer\n3. **'You Don't Know JS' series** oleh Kyle Simpson - Encyclopedia komprehensif\n\n **MODERN ESSENTIALS:**\n- **'JavaScript: The Definitive Guide'** - Referensi O'Reilly terpercaya\n- **'Learning JavaScript Design Patterns'** - Pola desain dari Addy Osmani\n- **'React: Up & Running'** - Untuk framework modern\n\n **TRIVIA:** 'The Good Parts' terjual jutaan copy dan mempengaruhi desain JS!",
                'confidence' => 0.95,
                'type' => 'expert_literature'
            ],
            
            // Novels - dengan pengetahuan sastra luas
            'novel romance' => [
                'answer' => " **PENGETAHUAN SASTRA ROMANCE MENDALAM:**\n\nSebagai ahli literatur romance, ini karya-karya LEGENDARY yang wajib diketahui:\n\n **CLASSIC MASTERPIECES:**\n1. **'Pride and Prejudice'** - Jane Austen (1813) - Dasar romance modern\n2. **'Jane Eyre'** - Charlotte Bront (1847) - Romance psikologis pertama\n3. **'Wuthering Heights'** - Emily Bront (1847) - Romance gelap epik\n\n **MODERN PHENOMENA:**\n- **'The Notebook'** - Nicholas Sparks (1996) - Romance kontemporer ikonik\n- **'Me Before You'** - Jojo Moyes (2012) - Romance bestseller global\n- **'The Kiss Quotient'** - Helen Hoang (2018) - Romance neurodiverse\n\n **SASTRA INDONESIA UNGGULAN:**\n- **'Bumi Manusia'** - Pramoedya Ananta Toer (1980)\n- **'Saman'** - Ayu Utami (1998)\n- **'Perahu Kertas'** - Dee Lestari (2009)\n\n **GENRE VARIASI:** Historical, Contemporary, Paranormal, LGBTQ+",
                'confidence' => 0.95,
                'type' => 'literature_expert'
            ],
            'novel misteri' => [
                'answer' => "- **EXPERTISE LITERATUR MISTERI & THRILLER:**\n\nSebagai ahli genre misteri, ini karya-karya LANDMARK:\n\n **GOLDEN AGE CLASSICS:**\n1. **Sherlock Holmes series** - Arthur Conan Doyle (1887-1927) - Bapak detective fiction\n2. **'Murder on the Orient Express'** - Agatha Christie (1934) - Masterpiece whodunit\n3. **'The Maltese Falcon'** - Dashiell Hammett (1930) - Hardboiled noir pertama\n\n **MODERN THRILLER PHENOMENA:**\n- **'Gone Girl'** - Gillian Flynn (2012) - Thriller psikologis revolusioner\n- **'The Girl on the Train'** - Paula Hawkins (2015) - Global sensation\n- **'The Da Vinci Code'** - Dan Brown (2003) - Conspiracy thriller monumental\n\n **INTERNATIONAL GEMS:**\n- **'The Devotion of Suspect X'** - Keigo Higashino (Jepang)\n- **'My Sweet Orange Tree'** - Jos Mauro de Vasconcelos (Brasil)\n\n **FUN FACT:** Misteri/thriller adalah genre terlaris setelah romance!",
                'confidence' => 0.95,
                'type' => 'literature_expert'
            ],
            
            // Academic - dengan pengetahuan referensi luas
            'skripsi' => [
                'answer' => " **PENGETAHUAN LITERATUR AKADEMIK MENDALAM:**\n\nSebagai ahli literatur penelitian, ini referensi LEGENDARY untuk akademisi:\n\n **FOUNDATIONAL METHODOLOGY:**\n1. **'Metodologi Penelitian'** oleh Prof. Dr. Sugiyono - Referensi WAJIB Indonesia\n2. **'Research Design: Qualitative, Quantitative, and Mixed Methods'** oleh John W. Creswell - Standar global\n3. **'The Craft of Research'** oleh Wayne C. Booth - Klasik University of Chicago\n\n **DISCIPLINE-SPECIFIC MASTERWORKS:**\n- **Sosial:** 'The Practice of Social Research' - Earl Babbie\n- **Pendidikan:** 'Educational Research' - John W. Creswell\n- **Bisnis:** 'Business Research Methods' - William G. Zikmund\n- **Teknik:** 'Research Methods for Engineers' - David V. Thiel\n\n **WRITING & PUBLICATION:**\n- **'How to Write a Lot'** - Paul J. Silvia\n- **'They Say/I Say'** - Gerald Graff & Cathy Birkenstein\n- **'Writing Your Journal Article in 12 Weeks'** - Wendy Laura Belcher\n\n **PRO TIP:** Mulai dengan literature review menggunakan buku-buku landmark di bidang Anda!",
                'confidence' => 0.95,
                'type' => 'academic_expert'
            ],
            'research' => [
                'answer' => " **EXPERTISE LITERATUR RISET KOMPREHENSIF:**\n\nSebagai ahli literatur penelitian, ini framework dan karya PENTING:\n\n **RESEARCH PARADIGMS LANDMARK:**\n1. **Positivism:** 'The Logic of Scientific Discovery' - Karl Popper (1934)\n2. **Constructivism:** 'The Structure of Scientific Revolutions' - Thomas Kuhn (1962)\n3. **Critical Theory:** 'Dialectic of Enlightenment' - Horkheimer & Adorno (1947)\n\n **METHODOLOGY MASTERWORKS:**\n- **Kualitatif:** 'Qualitative Inquiry and Research Design' - John Creswell\n- **Kuantitatif:** 'Statistics for People Who Hate Statistics' - Neil J. Salkind\n- **Mixed Methods:** 'Designing and Conducting Mixed Methods Research' - Creswell & Plano Clark\n\n **INDONESIAN SCHOLARSHIP:**\n- **'Ilmu Pengetahuan dan Metodenya'** - Jujun S. Suriasumantri\n- **'Filsafat Ilmu'** - The Liang Gie\n- **'Metodologi Studi Islam'** - Amin Abdullah\n\n **CONTEMPORARY TRENDS:** Open Science, Digital Humanities, Transdisciplinary Research",
                'confidence' => 0.95,
                'type' => 'academic_expert'
            ],
            
            // General recommendations - dengan kurasi ahli
            'recommend' => [
                'answer' => " **KURASI AHLI LITERATUR - REKOMENDASI TERBAIK SEJAK MASA KE MASA:**\n\nBerdasarkan pengetahuan literatur global saya, ini MASTERPIECES yang wajib diketahui:\n\n **LIFE-CHANGING NON-FICTION:**\n1. **'Sapiens: A Brief History of Humankind'** - Yuval Noah Harari (2011) - Revolusi sejarah manusia\n2. **'Thinking, Fast and Slow'** - Daniel Kahneman (2011) - Nobel-winning psychology\n3. **'The Power of Habit'** - Charles Duhigg (2012) - Science of habit formation\n\n **FICTION THAT DEFINES GENERATIONS:**\n- **'1984'** - George Orwell (1949) - Dystopian masterpiece\n- **'To Kill a Mockingbird'** - Harper Lee (1960) - Social justice classic\n- **'One Hundred Years of Solitude'** - Gabriel Garca Mrquez (1967) - Magical realism pinnacle\n\n **INDONESIAN LITERARY TREASURES:**\n- **'Laskar Pelangi'** - Andrea Hirata (2005) - Bildungsroman fenomenal\n- **'Negeri 5 Menara'** - A. Fuadi (2009) - Inspirasi pendidikan\n- **'Pulang'** - Leila S. Chudori (2012) - Sejarah politik masterpiece\n\n **WORLD LITERATURE GEMS:** Murakami, Coelho, Pamuk, Adichie, Yan",
                'confidence' => 0.95,
                'type' => 'expert_curation'
            ],
            
            // Categories dengan pengetahuan ahli
            'self improvement' => [
                'answer' => " **PENGETAHUAN AHLI LITERATUR PENGEMBANGAN DIRI:**\n\nSebagai kurator literatur self-help, ini karya-karya REVOLUTIONARY:\n\n **TIMELESS CLASSICS:**\n1. **'How to Win Friends and Influence People'** - Dale Carnegie (1936) - Buku self-help terlaris sepanjang masa\n2. **'The 7 Habits of Highly Effective People'** - Stephen Covey (1989) - Framework efektivitas global\n3. **'Think and Grow Rich'** - Napoleon Hill (1937) - Filosofi kesuksesan klasik\n\n **MODERN NEUROSCIENCE-BASED:**\n- **'Atomic Habits'** - James Clear (2018) - Sistem pembentukan kebiasaan\n- **'Mindset: The New Psychology of Success'** - Carol Dweck (2006) - Growth vs fixed mindset\n- **'The Subtle Art of Not Giving a F*ck'** - Mark Manson (2016) - Kontra-intuitif bestseller\n\n **WELLNESS & MINDFULNESS:**\n- **'The Power of Now'** - Eckhart Tolle (1997) - Spiritual awakening\n- **'The Miracle of Mindfulness'** - Thich Nhat Hanh (1975) - Buddhist wisdom\n- **'Digital Minimalism'** - Cal Newport (2019) - Tech-life balance\n\n **STAT:** Self-help adalah genre non-fiksi terlaris!",
                'confidence' => 0.95,
                'type' => 'genre_expert'
            ],
            'business' => [
                'answer' => " **EXPERTISE LITERATUR BISNIS & ENTREPRENEURSHIP:**\n\nSebagai ahli literatur bisnis, ini buku-buku LANDMARK yang membentuk industri:\n\n **MANAGEMENT CANON:**\n1. **'The Innovator\'s Dilemma'** - Clayton Christensen (1997) - Disruptive innovation theory\n2. **'Good to Great'** - Jim Collins (2001) - Studi perusahaan exceptional\n3. **'Competitive Strategy'** - Michael Porter (1980) - Framework strategi bisnis\n\n **STARTUP REVOLUTION:**\n- **'The Lean Startup'** - Eric Ries (2011) - Methodology startup global\n- **'Zero to One'** - Peter Thiel (2014) - Monopoli vs kompetisi\n- **'The $100 Startup'** - Chris Guillebeau (2012) - Micro-entrepreneurship\n\n **FINANCE & INVESTING MASTERWORKS:**\n- **'The Intelligent Investor'** - Benjamin Graham (1949) - Value investing bible\n- **'Rich Dad Poor Dad'** - Robert Kiyosaki (1997) - Financial literacy phenomenon\n- **'The Psychology of Money'** - Morgan Housel (2020) - Behavioral finance\n\n **ASIAN BUSINESS WISDOM:**\n- **'The Art of War'** - Sun Tzu (abad ke-5 SM) - Strategi bisnis klasik\n- **'Business Sutra'** - Devdutt Pattanaik (2013) - Mitologi bisnis India",
                'confidence' => 0.95,
                'type' => 'genre_expert'
            ]
        ];
    }
    
    private function loadExpertKnowledge(): void
    {
        $this->expertKnowledge = [
            'authors' => [
                'indonesia' => ['Pramoedya Ananta Toer', 'Andrea Hirata', 'Dee Lestari', 'Ayu Utami', 'Eka Kurniawan', 'Leila S. Chudori'],
                'international' => ['Haruki Murakami', 'Paulo Coelho', 'J.K. Rowling', 'Stephen King', 'Margaret Atwood', 'Chimamanda Ngozi Adichie'],
                'classical' => ['William Shakespeare', 'Jane Austen', 'Fyodor Dostoevsky', 'Leo Tolstoy', 'Charles Dickens', 'Victor Hugo']
            ],
            'genres' => [
                'fiction' => ['Literary Fiction', 'Science Fiction', 'Fantasy', 'Mystery', 'Romance', 'Historical Fiction'],
                'nonfiction' => ['Biography', 'History', 'Science', 'Philosophy', 'Business', 'Self-Help']
            ],
            'awards' => [
                'nobel' => ['Bob Dylan', 'Toni Morrison', 'Mario Vargas Llosa', 'Orhan Pamuk', 'Mo Yan'],
                'booker' => ['Margaret Atwood', 'Salman Rushdie', 'Hilary Mantel', 'Arundhati Roy', 'Yann Martel']
            ]
        ];
    }
    
    public function getMockResponse(string $question): array
    {
        $normalized = $this->normalizeText($question);
        $keywords = $this->extractKeywords($question);

        // Mode bebas: SEMUA pertanyaan dapat jawaban ahli tanpa batasan
        $isBookRelated = $this->isBookRelated($normalized);
        $isAcademic = $this->isAcademic($normalized);
        
        // Jika terkait buku atau akademik, beri jawaban ahli
        if ($isBookRelated || $isAcademic) {
            return $this->getExpertBookResponse($question, $keywords, $isAcademic);
        }

        // Check for exact matches
        foreach ($this->mockResponses as $key => $response) {
            if (str_contains($normalized, $key)) {
                return [
                    'answer' => $response['answer'],
                    'confidence' => $response['confidence'],
                    'keywords' => $keywords,
                    'sources' => ['expert_mock_ai'],
                    'type' => $response['type'] ?? 'expert_response',
                    'mode' => 'free'
                ];
            }
        }
        
        // Check for keyword matches
        foreach ($keywords as $keyword) {
            foreach ($this->mockResponses as $key => $response) {
                if (str_contains($key, $keyword)) {
                    return [
                        'answer' => $response['answer'],
                        'confidence' => $response['confidence'] * 0.9,
                        'keywords' => $keywords,
                        'sources' => ['expert_mock_partial'],
                        'type' => $response['type'] ?? 'expert_response',
                        'mode' => 'free'
                    ];
                }
            }
        }
        
        // Generic expert response
        return $this->generateExpertResponse($question, $keywords);
    }
    
    private function isBookRelated(string $text): bool
    {
        $patterns = [
            '/\b(buku|novel|bacaan|sastra|literatur|karya|tulisan|penulis)\b/i',
            '/\b(membaca|pembaca|pengarang|penerbit|penerbitan)\b/i',
            '/\b(genre|jenis|kategori)\s+(buku|novel)\b/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }
    
    private function isAcademic(string $text): bool
    {
        $patterns = [
            '/\b(skripsi|tesis|disertasi|penelitian|riset|akademik|ilmiah)\b/i',
            '/\b(referensi|rujukan|sumber|daftar\s+pustaka|bibliografi)\b/i',
            '/\b(jurnal|paper|artikel|publikasi|konferensi)\b/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }
    
    private function getExpertBookResponse(string $question, array $keywords, bool $isAcademic): array
    {
        $topic = !empty($keywords) ? implode(' ', array_slice($keywords, 0, 2)) : 'literatur';
        
        if ($isAcademic) {
            return [
                'answer' => " **SEBAGAI AHLI LITERATUR AKADEMIK:**\n\nUntuk penelitian tentang \"{$topic}\", pengetahuan saya mencakup:\n\n **REFERENSI LANDMARK:**\n1. Buku-buku METHODOLOGI klasik dan kontemporer\n2. Karya TEORETIS foundational di bidang terkait\n3. STUDI KASUS seminal yang membentuk disiplin ilmu\n4. ANTHOLOGI penting yang mengumpulkan pemikiran kunci\n\n **PEMIKIR UTAMA:**\n- Teoretisi PIONIR yang mendefinisikan bidang\n- Peneliti CONTEMPORARY yang memajukan wacana\n- Scholar INTERDISIPLINER yang menghubungkan bidang\n\n **FRAMEWORK ANALITIS:**\n- Paradigma penelitian yang RELEVAN\n- Metodologi yang APPROPRIATE\n- Alat analisis yang ROBUST\n- Standar etika dan kualitas\n\n **LACAKAN HISTORIS:** Perkembangan pemikiran dari masa ke masa",
                'confidence' => 0.93,
                'keywords' => $keywords,
                'sources' => ['academic_expert_knowledge'],
                'type' => 'academic_expertise',
                'mode' => 'free'
            ];
        } else {
            return [
                'answer' => " **SEBAGAI AHLI LITERATUR DENGAN PENGETAHUAN LUAS:**\n\nUntuk \"{$topic}\", expertise saya meliputi:\n\n **BUKU-BUKU LEGENDARY:**\n1. Karya-karya CANONICAL yang membentuk genre\n2. Bestseller GLOBAL yang mendefinisikan era\n3. Masterpiece UNDERAPPRECIATED yang patut ditemukan\n4. Buku PIONIR yang membuka jalan baru\n\n **PENULIS-PENULIS MASTER:**\n- Sastrawan dengan VOICE unik dan berpengaruh\n- Storyteller dengan CRAFT mengagumkan\n- Thinker dengan IDEAS transformative\n- Stylist dengan PROSE memorable\n\n **KONTEKS BUDAYA:**\n- Literatur dari berbagai BENUA dan tradisi\n- Karya yang merefleksikan REALITAS sosial\n- Buku yang menantang PARADIGMA established\n- Sastra yang membangun EMPATHY lintas batas\n\n **ESTETIKA & CRAFT:** Plot, karakter, tema, gaya, struktur",
                'confidence' => 0.93,
                'keywords' => $keywords,
                'sources' => ['literature_expert_knowledge'],
                'type' => 'literature_expertise',
                'mode' => 'free'
            ];
        }
    }
    
    private function generateExpertResponse(string $question, array $keywords): array
    {
        $topic = !empty($keywords) ? implode(' ', array_slice($keywords, 0, 3)) : 'topik ini';
        $wordCount = str_word_count($question);
        
        $responses = [
            " **SEBAGAI PUSTAKAWAN DIGITAL DENGAN PENGETAHUAN EKSPANSIF:**\n\nUntuk \"{$topic}\", saya memiliki akses ke pengetahuan tentang:\n\n **LITERARY CANON:** Karya-karya yang membentuk peradaban baca\n **CRITICAL PERSPECTIVES:** Analisis mendalam dari para ahli\n **GLOBAL TRENDS:** Arus utama sastra dunia kontemporer\n **HIDDEN GEMS:** Karya luar biasa yang kurang dikenal\n\nMau eksplorasi aspek khusus apa?",
            
            " **PENGETAHUAN LITERATUR KOMPREHENSIF:**\n\nTopik \"{$topic}\" muncul dalam berbagai konteks literatur:\n\n1. **Historis:** Perkembangan dari masa ke masa\n2. **Geografis:** Perspektif dari berbagai budaya\n3. **Genre-based:** Ekspresi dalam berbagai bentuk\n4. **Thematic:** Perlakuan tema dalam karya berbeda\n\nIngin fokus ke dimensi mana?",
            
            " **EXPERT LITERATURE ANALYSIS:**\n\nSebagai ahli literatur, saya melihat \"{$topic}\" melalui lensa:\n\n- **Signifikansi Budaya:** Dampak pada masyarakat\n- **Nilai Sastra:** Kualitas artistik dan teknik\n- **Relevansi Kontemporer:** Keterkaitan dengan isu kini\n- **Warisan Abadi:** Pengaruh jangka panjang\n\nMau analisis mendalam tentang aspek tertentu?"
        ];
        
        $responseIndex = array_rand($responses);
        
        return [
            'answer' => $responses[$responseIndex],
            'confidence' => 0.85,
            'keywords' => $keywords,
            'sources' => ['expert_general_knowledge'],
            'type' => 'expert_general_response',
            'mode' => 'free'
        ];
    }
    
    /**
     * Simulate AI summary generation - Expert version
     */
    public function getMockSummary(array $bookData): string
    {
        $title = $bookData['title'] ?? 'Buku';
        $author = $bookData['author'] ?? 'Penulis';
        $year = $bookData['year'] ?? '';
        
        $summaries = [
            " **ANALISIS AHLI:** \"{$title}\" oleh {$author}" . ($year ? " ({$year})" : "") . 
            " adalah karya SIGNIFIKAN dalam kanon literatur. Buku ini menawarkan:\n\n" .
            " **CONTRIBUTION UNIK:** Pendekatan inovatif yang mempengaruhi bidangnya\n" .
            " **INSIGHT MENDALAM:** Analisis yang mengubah perspektif pembaca\n" .
            " **RELEVANSI GLOBAL:** Tematik yang resonan lintas budaya\n" .
            " **PENGARUH ABADI:** Warisan yang terus menginspirasi generasi\n\n" .
            "Sebagai ahli literatur, saya menganggap karya ini ESSENTIAL READING.",
            
            " **EXPERT ASSESSMENT:** Dalam \"{$title}\", {$author} mencapai:\n\n" .
            " **PENCAPAIAN ARTISTIK:** Penguasaan craft sastra yang mengagumkan\n" .
            " **KEDALAMAN INTELEKTUAL:** Argumen yang rigorously developed\n" .
            " **RESONANSI EMOSIONAL:** Koneksi yang powerful dengan pembaca\n" .
            " **IMPACT KULTURAL:** Pengaruh measurable pada wacana publik\n\n" .
            "Karya ini menempati posisi TERHORMAT dalam literatur kontemporer.",
            
            " **LITERARY CRITIQUE:** \"{$title}\" merupakan:\n\n" .
            " **LANDMARK PUBLICATION:** Titik balik dalam evolusi genre\n" .
            " **MASTERFUL EXECUTION:** Realisasi visi artistik yang sempurna\n" .
            " **FERTILE GROUND:** Sumber inspirasi bagi karya-karya berikutnya\n" .
            " **CULTURAL BRIDGE:** Menghubungkan tradisi dengan inovasi\n\n" .
            "Sebagai kurator literatur, saya merekomendasikan buku ini HIGHLY."
        ];
        
        return $summaries[array_rand($summaries)];
    }
    
    /**
     * Get expert knowledge by category
     */
    public function getExpertKnowledge(string $category): array
    {
        return $this->expertKnowledge[$category] ?? [
            'message' => 'Pengetahuan ahli tersedia untuk kategori ini',
            'category' => $category
        ];
    }
}