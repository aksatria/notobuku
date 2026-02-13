<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Biblio;
use App\Models\Item;
use App\Models\Subject;
use App\Models\Tag;
use App\Services\MetadataMappingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KatalogCollectionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $institutionId = DB::table('institutions')->value('id') ?? 1;
            $branchId = DB::table('branches')->where('institution_id', $institutionId)->value('id');

            $demoCity = (string) env('NB_DEMO_CITY', 'Jakarta');
            $demoPublisher = (string) env('NB_DEMO_PUBLISHER', 'Pustaka Ilmu');
            $collections = array_map(
                fn(array $row) => $this->enrichRow($row, $demoCity, $demoPublisher),
                $this->collections($demoCity, $demoPublisher)
            );
            $mapping = app(MetadataMappingService::class);

            foreach ($collections as $row) {
                $unique = ['institution_id' => $institutionId];
                if (!empty($row['isbn'])) {
                    $unique['isbn'] = $row['isbn'];
                } else {
                    $unique['title'] = $row['title'];
                    $unique['publish_year'] = $row['publish_year'];
                }

                $biblio = Biblio::query()->updateOrCreate($unique, [
                    'title' => $row['title'],
                    'subtitle' => $row['subtitle'] ?? null,
                    'normalized_title' => $this->normalize($row['title'] . ' ' . ($row['subtitle'] ?? '')),
                    'responsibility_statement' => $row['responsibility_statement'] ?? null,
                    'place_of_publication' => $row['place_of_publication'] ?? $demoCity,
                    'publisher' => $row['publisher'] ?? $demoPublisher,
                    'publish_year' => $row['publish_year'] ?? null,
                    'language' => $row['language'] ?? 'ind',
                    'edition' => $row['edition'] ?? null,
                    'series_title' => $row['series_title'] ?? null,
                    'physical_desc' => $row['physical_desc'] ?? null,
                    'extent' => $row['extent'] ?? null,
                    'dimensions' => $row['dimensions'] ?? null,
                    'illustrations' => $row['illustrations'] ?? null,
                    'ddc' => $row['ddc'] ?? null,
                    'call_number' => $row['call_number'] ?? null,
                    'isbn' => $row['isbn'] ?? null,
                    'issn' => $row['issn'] ?? null,
                    'material_type' => $row['material_type'] ?? 'buku',
                    'media_type' => $row['media_type'] ?? 'teks',
                    'audience' => $row['audience'] ?? null,
                    'is_reference' => (bool) ($row['is_reference'] ?? false),
                    'frequency' => $row['frequency'] ?? null,
                    'former_frequency' => $row['former_frequency'] ?? null,
                    'serial_beginning' => $row['serial_beginning'] ?? null,
                    'serial_ending' => $row['serial_ending'] ?? null,
                    'serial_first_issue' => $row['serial_first_issue'] ?? null,
                    'serial_last_issue' => $row['serial_last_issue'] ?? null,
                    'serial_source_note' => $row['serial_source_note'] ?? null,
                    'serial_preceding_title' => $row['serial_preceding_title'] ?? null,
                    'serial_preceding_issn' => $row['serial_preceding_issn'] ?? null,
                    'serial_succeeding_title' => $row['serial_succeeding_title'] ?? null,
                    'serial_succeeding_issn' => $row['serial_succeeding_issn'] ?? null,
                    'holdings_summary' => $row['holdings_summary'] ?? null,
                    'holdings_supplement' => $row['holdings_supplement'] ?? null,
                    'holdings_index' => $row['holdings_index'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'bibliography_note' => $row['bibliography_note'] ?? null,
                    'general_note' => $row['general_note'] ?? null,
                    'ai_status' => 'draft',
                ]);

                $authorIds = [];
                foreach ($row['authors'] ?? [] as $i => $authorRow) {
                    [$name, $role] = $this->extractAuthor($authorRow);
                    if ($name === '') {
                        continue;
                    }
                    $author = Author::query()->firstOrCreate(
                        ['normalized_name' => $this->normalize($name)],
                        ['name' => $name, 'normalized_name' => $this->normalize($name)]
                    );
                    $authorIds[$author->id] = [
                        'role' => $role !== '' ? $role : 'pengarang',
                        'sort_order' => $i + 1,
                    ];
                }
                if (!empty($authorIds)) {
                    $biblio->authors()->sync($authorIds);
                }

                $subjectIds = [];
                foreach ($row['subjects'] ?? [] as $i => $subjectRow) {
                    [$term, $type, $scheme] = $this->extractSubject($subjectRow);
                    if ($term === '') {
                        continue;
                    }
                    $subject = Subject::query()->firstOrCreate(
                        [
                            'normalized_term' => $this->normalize($term),
                            'scheme' => $scheme,
                        ],
                        [
                            'name' => $term,
                            'term' => $term,
                            'normalized_term' => $this->normalize($term),
                            'scheme' => $scheme,
                        ]
                    );
                    $subjectIds[$subject->id] = ['type' => $type, 'sort_order' => $i + 1];
                }
                if (!empty($subjectIds)) {
                    $biblio->subjects()->sync($subjectIds);
                }

                $tagIds = [];
                foreach ($row['tags'] ?? [] as $i => $tagName) {
                    $tag = Tag::query()->firstOrCreate(
                        ['normalized_name' => $this->normalize($tagName)],
                        ['name' => $tagName, 'normalized_name' => $this->normalize($tagName)]
                    );
                    $tagIds[$tag->id] = ['sort_order' => $i + 1];
                }
                if (!empty($tagIds)) {
                    $biblio->tags()->sync($tagIds);
                }

                $mapping->syncMetadataForBiblio($biblio, null, $row['identifiers'] ?? []);

                if (empty($biblio->cover_path)) {
                    $coverPath = $this->ensureDummyCover($biblio->title);
                    if ($coverPath) {
                        $biblio->cover_path = $coverPath;
                        $biblio->save();
                    }
                }

                if ($biblio->items()->count() === 0) {
                    $copies = (int) ($row['copies'] ?? 1);
                    $available = (int) ($row['available'] ?? $copies);
                    for ($i = 0; $i < $copies; $i++) {
                        Item::create([
                            'institution_id' => $institutionId,
                            'branch_id' => $branchId,
                            'shelf_id' => null,
                            'biblio_id' => $biblio->id,
                            'barcode' => $this->makeCode('NB'),
                            'accession_number' => $this->makeCode('ACC'),
                            'inventory_code' => null,
                            'status' => $i < $available ? 'available' : 'borrowed',
                            'acquired_at' => now()->subDays(rand(30, 900))->toDateString(),
                            'price' => rand(50, 250) * 1000,
                            'source' => 'Pengadaan',
                            'notes' => null,
                        ]);
                    }
                }
            }

            DB::commit();
            $this->command->info('✅ KatalogCollectionsSeeder OK (' . count($collections) . ' koleksi)');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function makeCode(string $prefix): string
    {
        return $prefix . '-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
    }

    private function normalize(string $text): string
    {
        return (string) Str::of($text)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]/u', ' ')
            ->squish();
    }

    private function collections(string $demoCity, string $demoPublisher): array
    {
        return [
            [
                'title' => 'Manajemen Perpustakaan Modern',
                'subtitle' => 'Strategi layanan dan tata kelola',
                'responsibility_statement' => 'oleh Nadia Puspita',
                'place_of_publication' => $demoCity,
                'publisher' => $demoPublisher,
                'publish_year' => 2023,
                'language' => 'ind',
                'edition' => 'Edisi 2',
                'series_title' => 'Seri Manajemen Informasi',
                'physical_desc' => 'xii + 268 hlm',
                'extent' => '268 halaman',
                'dimensions' => '23 cm',
                'illustrations' => 'ilustrasi',
                'ddc' => '020.2',
                'call_number' => '020.2 NAD',
                'isbn' => '9786024001000',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'notes' => 'Fokus pada kebijakan layanan, koleksi, dan SDM.',
                'general_note' => 'Termasuk studi kasus perpustakaan daerah.',
                'authors' => ['Nadia Puspita'],
                'subjects' => ['Manajemen perpustakaan', 'Layanan perpustakaan'],
                'tags' => ['manajemen', 'layanan'],
                'copies' => 3,
                'available' => 2,
            ],
            [
                'title' => 'Katalogisasi MARC21 Praktis',
                'subtitle' => 'Dari input ke export',
                'responsibility_statement' => 'Raka Suryanto',
                'place_of_publication' => 'Bandung',
                'publisher' => 'Gramedia',
                'publish_year' => 2022,
                'language' => 'ind',
                'ddc' => '025.3',
                'call_number' => '025.3 RAK',
                'isbn' => '9786024001001',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'notes' => 'Panduan field MARC21 dan praktik RDA.',
                'authors' => ['Raka Suryanto'],
                'subjects' => ['Katalogisasi', 'MARC21'],
                'tags' => ['marc', 'rda'],
                'copies' => 2,
                'available' => 2,
            ],
            [
                'title' => 'Metadata dan Interoperabilitas',
                'subtitle' => 'Dublin Core, MODS, dan skema lokal',
                'responsibility_statement' => 'Siti Rahmawati',
                'place_of_publication' => 'Yogyakarta',
                'publisher' => $demoPublisher,
                'publish_year' => 2024,
                'language' => 'ind',
                'ddc' => '025.3',
                'call_number' => '025.3 SIT',
                'isbn' => '9786024001002',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'notes' => 'Strategi metadata lintas sistem.',
                'authors' => ['Siti Rahmawati'],
                'subjects' => ['Metadata', 'Interoperabilitas'],
                'tags' => ['metadata'],
                'copies' => 2,
                'available' => 1,
            ],
            [
                'title' => 'Pengantar Ilmu Informasi',
                'subtitle' => 'Dasar teori dan praktik',
                'responsibility_statement' => 'Fajar Nugroho',
                'place_of_publication' => 'Surabaya',
                'publisher' => 'Nusantara Media',
                'publish_year' => 2021,
                'language' => 'ind',
                'ddc' => '020',
                'call_number' => '020 FAJ',
                'isbn' => '9786024001003',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'notes' => 'Konsep dasar literasi informasi.',
                'authors' => ['Fajar Nugroho'],
                'subjects' => ['Ilmu informasi', 'Literasi informasi'],
                'tags' => ['literasi'],
                'copies' => 4,
                'available' => 3,
            ],
            [
                'title' => 'Dasar Pemrograman untuk Pustakawan',
                'subtitle' => 'Python dan otomasi kerja',
                'responsibility_statement' => 'Lina Kartika',
                'place_of_publication' => 'Jakarta',
                'publisher' => 'TeknoPress',
                'publish_year' => 2020,
                'language' => 'ind',
                'ddc' => '005.133',
                'call_number' => '005.133 LIN',
                'isbn' => '9786024001004',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'notes' => 'Contoh skrip otomasi katalog.',
                'authors' => ['Lina Kartika'],
                'subjects' => ['Pemrograman', 'Perpustakaan digital'],
                'tags' => ['python'],
                'copies' => 3,
                'available' => 2,
            ],
            [
                'title' => 'Peta Nusantara',
                'subtitle' => 'Atlas tematik Indonesia',
                'responsibility_statement' => 'Badan Informasi Geospasial',
                'place_of_publication' => 'Bogor',
                'publisher' => 'BIG',
                'publish_year' => 2018,
                'language' => 'ind',
                'ddc' => '912',
                'call_number' => '912 PET',
                'isbn' => '9786024001005',
                'material_type' => 'peta',
                'media_type' => 'teks',
                'notes' => 'Atlas tematik dengan data demografi.',
                'authors' => ['Badan Informasi Geospasial'],
                'subjects' => ['Geografi', 'Peta Indonesia'],
                'tags' => ['atlas'],
                'copies' => 1,
                'available' => 1,
            ],
            [
                'title' => 'Serial Jurnal Perpustakaan',
                'subtitle' => 'Vol. 12 (2022)',
                'responsibility_statement' => 'Asosiasi Pustakawan',
                'place_of_publication' => 'Jakarta',
                'publisher' => 'Asosiasi Pustakawan',
                'publish_year' => 2022,
                'language' => 'ind',
                'ddc' => '020',
                'call_number' => '020 JUR',
                'issn' => '1234-5678',
                'material_type' => 'serial',
                'media_type' => 'teks',
                'notes' => 'Edisi khusus pengembangan layanan.',
                'authors' => [
                    ['name' => 'Asosiasi Pustakawan', 'role' => 'penerbit'],
                ],
                'subjects' => ['Jurnal perpustakaan', 'Serial'],
                'tags' => ['jurnal'],
                'frequency' => 'Bulanan',
                'serial_beginning' => 'Vol. 12, no. 1 (2022)-',
                'serial_source_note' => 'Deskripsi berdasarkan: Vol. 12, no. 1 (2022).',
                'copies' => 2,
                'available' => 2,
            ],
            [
                'title' => 'Audiobook: Sejarah Nusantara',
                'subtitle' => 'Edisi audio',
                'responsibility_statement' => 'Dinar Lestari (narator)',
                'place_of_publication' => 'Bandung',
                'publisher' => 'AudioPustaka',
                'publish_year' => 2020,
                'language' => 'ind',
                'ddc' => '959.8',
                'call_number' => '959.8 DIN',
                'isbn' => '9786024001006',
                'material_type' => 'audio',
                'media_type' => 'audio',
                'notes' => 'Versi audiobook.',
                'authors' => [
                    ['name' => 'Dinar Lestari', 'role' => 'narator'],
                ],
                'subjects' => ['Sejarah Indonesia', 'Audiobook'],
                'tags' => ['audio'],
                'identifiers' => [
                    ['scheme' => 'uri', 'value' => 'https://katalog.example.id/audio/sejarah-nusantara', 'uri' => 'https://katalog.example.id/audio/sejarah-nusantara'],
                ],
                'copies' => 1,
                'available' => 1,
            ],
            [
                'title' => 'Video Pembelajaran Katalog',
                'subtitle' => 'Series 1',
                'responsibility_statement' => 'Studio NOTOBUKU',
                'place_of_publication' => 'Jakarta',
                'publisher' => 'NOTOBUKU Media',
                'publish_year' => 2023,
                'language' => 'ind',
                'ddc' => '025.3',
                'call_number' => '025.3 VID',
                'isbn' => '9786024001007',
                'material_type' => 'video',
                'media_type' => 'video',
                'notes' => 'Materi video katalogisasi.',
                'authors' => [
                    ['name' => 'Studio NOTOBUKU', 'role' => 'produser'],
                ],
                'subjects' => ['Katalogisasi', 'Video pembelajaran'],
                'tags' => ['video'],
                'identifiers' => [
                    ['scheme' => 'uri', 'value' => 'https://katalog.example.id/video/katalog-series-1', 'uri' => 'https://katalog.example.id/video/katalog-series-1'],
                ],
                'copies' => 1,
                'available' => 1,
            ],
            [
                'title' => 'Literasi Digital untuk Remaja',
                'subtitle' => 'Etika dan keamanan daring',
                'responsibility_statement' => 'Reni Maharani',
                'place_of_publication' => 'Semarang',
                'publisher' => 'Nusantara Media',
                'publish_year' => 2021,
                'language' => 'ind',
                'ddc' => '004.678',
                'call_number' => '004.678 REN',
                'isbn' => '9786024001008',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'audience' => 'remaja',
                'notes' => 'Panduan keamanan digital untuk remaja.',
                'authors' => ['Reni Maharani'],
                'subjects' => ['Literasi digital', 'Keamanan siber'],
                'tags' => ['literasi', 'internet'],
                'copies' => 3,
                'available' => 2,
            ],
            [
                'title' => 'Riset Kualitatif untuk Pustakawan',
                'subtitle' => 'Metode dan studi kasus',
                'responsibility_statement' => 'Ahmad Syahrul',
                'place_of_publication' => 'Malang',
                'publisher' => 'TeknoPress',
                'publish_year' => 2019,
                'language' => 'ind',
                'ddc' => '001.42',
                'call_number' => '001.42 AHM',
                'isbn' => '9786024001009',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'notes' => 'Metodologi riset sosial dan perpustakaan.',
                'authors' => ['Ahmad Syahrul'],
                'subjects' => ['Metodologi penelitian', 'Perpustakaan'],
                'tags' => ['riset'],
                'copies' => 2,
                'available' => 1,
            ],
            [
                'title' => 'Klasifikasi DDC untuk Pemula',
                'subtitle' => 'Latihan dan contoh',
                'responsibility_statement' => 'Mira Andini',
                'place_of_publication' => 'Jakarta',
                'publisher' => $demoPublisher,
                'publish_year' => 2020,
                'language' => 'ind',
                'ddc' => '025.431',
                'call_number' => '025.431 MIR',
                'isbn' => '9786024001010',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'notes' => 'Latihan klasifikasi dan penentuan nomor panggil.',
                'authors' => ['Mira Andini'],
                'subjects' => ['Klasifikasi DDC'],
                'tags' => ['ddc'],
                'copies' => 3,
                'available' => 3,
            ],
            [
                'title' => 'Panduan Layanan Referensi',
                'subtitle' => 'Prosedur dan best practice',
                'responsibility_statement' => 'Bimo Prakoso',
                'place_of_publication' => 'Bandung',
                'publisher' => 'Nusantara Media',
                'publish_year' => 2018,
                'language' => 'ind',
                'ddc' => '025.52',
                'call_number' => '025.52 BIM',
                'isbn' => '9786024001011',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'is_reference' => true,
                'notes' => 'Panduan layanan referensi dan rujukan.',
                'authors' => ['Bimo Prakoso'],
                'subjects' => ['Layanan referensi'],
                'tags' => ['referensi'],
                'copies' => 2,
                'available' => 1,
            ],
            [
                'title' => 'Cerita Rakyat Nusantara',
                'subtitle' => 'Kumpulan cerita pendek',
                'responsibility_statement' => 'Tim Budaya Nusantara',
                'place_of_publication' => 'Denpasar',
                'publisher' => 'Budaya Press',
                'publish_year' => 2017,
                'language' => 'ind',
                'ddc' => '398.2',
                'call_number' => '398.2 TIM',
                'isbn' => '9786024001012',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'notes' => 'Koleksi cerita rakyat dari berbagai daerah.',
                'authors' => ['Tim Budaya Nusantara'],
                'subjects' => ['Cerita rakyat', 'Sastra daerah'],
                'tags' => ['cerita'],
                'copies' => 3,
                'available' => 2,
            ],
            [
                'title' => 'English for Librarians',
                'subtitle' => 'Communication & service',
                'responsibility_statement' => 'John Carter',
                'place_of_publication' => 'London',
                'publisher' => 'Library Skills Press',
                'publish_year' => 2016,
                'language' => 'eng',
                'ddc' => '428.007',
                'call_number' => '428.007 JOH',
                'isbn' => '9786024001013',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'notes' => 'Komunikasi bahasa Inggris untuk pustakawan.',
                'authors' => ['John Carter'],
                'subjects' => ['Bahasa Inggris', 'Layanan perpustakaan'],
                'tags' => ['english'],
                'copies' => 2,
                'available' => 2,
            ],
            [
                'title' => 'Arsip Digital dan Preservasi',
                'subtitle' => 'Strategi jangka panjang',
                'responsibility_statement' => 'Rina Laksmi',
                'place_of_publication' => 'Jakarta',
                'publisher' => 'TeknoPress',
                'publish_year' => 2021,
                'language' => 'ind',
                'ddc' => '025.84',
                'call_number' => '025.84 RIN',
                'isbn' => '9786024001014',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'notes' => 'Preservasi digital dan migrasi format.',
                'authors' => ['Rina Laksmi'],
                'subjects' => ['Preservasi digital', 'Arsip'],
                'tags' => ['arsip'],
                'copies' => 2,
                'available' => 1,
            ],
            [
                'title' => 'Open Source ILS',
                'subtitle' => 'Koha dan Evergreen',
                'responsibility_statement' => 'Farhan Idris',
                'place_of_publication' => 'Jakarta',
                'publisher' => 'TeknoPress',
                'publish_year' => 2024,
                'language' => 'ind',
                'ddc' => '025.3',
                'call_number' => '025.3 FAR',
                'isbn' => '9786024001015',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'notes' => 'Studi perbandingan sistem ILS open source.',
                'authors' => ['Farhan Idris'],
                'subjects' => ['Sistem perpustakaan', 'Open source'],
                'tags' => ['ils'],
                'copies' => 2,
                'available' => 2,
            ],
            [
                'title' => 'Koleksi Anak: Sains Seru',
                'subtitle' => 'Eksperimen sederhana',
                'responsibility_statement' => 'Dewi Larasati',
                'place_of_publication' => 'Malang',
                'publisher' => 'EduKids',
                'publish_year' => 2020,
                'language' => 'ind',
                'ddc' => '507.8',
                'call_number' => '507.8 DEW',
                'isbn' => '9786024001016',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'audience' => 'anak',
                'notes' => 'Buku sains ringan untuk anak.',
                'authors' => ['Dewi Larasati'],
                'subjects' => ['Sains anak', 'Eksperimen'],
                'tags' => ['anak'],
                'copies' => 3,
                'available' => 3,
            ],
            [
                'title' => 'Koleksi Referensi: Ensiklopedia Budaya',
                'subtitle' => 'Vol. 1',
                'responsibility_statement' => 'Pusat Dokumentasi Budaya',
                'place_of_publication' => 'Jakarta',
                'publisher' => 'Budaya Press',
                'publish_year' => 2015,
                'language' => 'ind',
                'ddc' => '030',
                'call_number' => '030 ENS',
                'isbn' => '9786024001017',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'is_reference' => true,
                'notes' => 'Ensiklopedia budaya sebagai referensi.',
                'authors' => ['Pusat Dokumentasi Budaya'],
                'subjects' => ['Ensiklopedia', 'Budaya'],
                'tags' => ['referensi'],
                'copies' => 1,
                'available' => 1,
            ],
            [
                'title' => 'Majalah Teknologi Informasi',
                'subtitle' => 'Edisi Jan 2024',
                'responsibility_statement' => 'Redaksi TI',
                'place_of_publication' => $demoCity,
                'publisher' => 'TechMag',
                'publish_year' => 2024,
                'language' => 'ind',
                'ddc' => '004',
                'call_number' => '004 MAJ',
                'issn' => '2722-1122',
                'material_type' => 'serial',
                'media_type' => 'teks',
                'notes' => 'Majalah bulanan teknologi informasi.',
                'authors' => [
                    ['name' => 'Redaksi TI', 'role' => 'penerbit'],
                ],
                'subjects' => ['Teknologi informasi', 'Majalah'],
                'tags' => ['serial'],
                'frequency' => 'Bulanan',
                'serial_beginning' => 'Edisi Jan 2024-',
                'serial_source_note' => 'Deskripsi berdasarkan: Edisi Jan 2024.',
                'copies' => 2,
                'available' => 2,
            ],
            [
                'title' => 'Audiobook: Dasar Pemrograman',
                'subtitle' => 'Edisi audio',
                'responsibility_statement' => 'Nara Aksa (narator)',
                'place_of_publication' => $demoCity,
                'publisher' => 'AudioPustaka',
                'publish_year' => 2021,
                'language' => 'ind',
                'ddc' => '005.133',
                'call_number' => '005.133 NAR',
                'isbn' => '9786024001018',
                'material_type' => 'audio',
                'media_type' => 'audio',
                'notes' => 'Audio pembelajaran pemrograman dasar.',
                'authors' => [
                    ['name' => 'Nara Aksa', 'role' => 'narator'],
                ],
                'subjects' => ['Pemrograman', 'Audiobook'],
                'tags' => ['audio'],
                'identifiers' => [
                    ['scheme' => 'uri', 'value' => 'https://katalog.example.id/audio/dasar-pemrograman', 'uri' => 'https://katalog.example.id/audio/dasar-pemrograman'],
                ],
                'copies' => 1,
                'available' => 1,
            ],
            [
                'title' => 'Video: Literasi Informasi',
                'subtitle' => 'Pelatihan pustakawan',
                'responsibility_statement' => 'Studio Literasi',
                'place_of_publication' => $demoCity,
                'publisher' => 'NOTOBUKU Media',
                'publish_year' => 2022,
                'language' => 'ind',
                'ddc' => '025.5',
                'call_number' => '025.5 VID',
                'isbn' => '9786024001019',
                'material_type' => 'video',
                'media_type' => 'video',
                'notes' => 'Video pelatihan literasi informasi.',
                'authors' => [
                    ['name' => 'Studio Literasi', 'role' => 'produser'],
                ],
                'subjects' => ['Literasi informasi', 'Video pelatihan'],
                'tags' => ['video'],
                'identifiers' => [
                    ['scheme' => 'uri', 'value' => 'https://katalog.example.id/video/literasi-informasi', 'uri' => 'https://katalog.example.id/video/literasi-informasi'],
                ],
                'copies' => 1,
                'available' => 1,
            ],
            [
                'title' => 'Petualangan Sains Anak',
                'subtitle' => 'Eksperimen di rumah',
                'responsibility_statement' => 'Dita Laras',
                'place_of_publication' => $demoCity,
                'publisher' => 'EduKids',
                'publish_year' => 2019,
                'language' => 'ind',
                'ddc' => '507.8',
                'call_number' => '507.8 DIT',
                'isbn' => '9786024001020',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'audience' => 'anak',
                'notes' => 'Eksperimen sains sederhana untuk anak.',
                'authors' => ['Dita Laras'],
                'subjects' => ['Sains anak', 'Eksperimen'],
                'tags' => ['anak'],
                'copies' => 3,
                'available' => 3,
            ],
            [
                'title' => 'Komik Edukasi: Lingkungan',
                'subtitle' => 'Bumi yang Bersih',
                'responsibility_statement' => 'Rafi Pratama',
                'place_of_publication' => $demoCity,
                'publisher' => 'Budaya Press',
                'publish_year' => 2018,
                'language' => 'ind',
                'ddc' => '363.7',
                'call_number' => '363.7 RAF',
                'isbn' => '9786024001021',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'audience' => 'anak',
                'notes' => 'Komik edukasi lingkungan untuk anak.',
                'authors' => ['Rafi Pratama'],
                'subjects' => ['Lingkungan', 'Komik edukasi'],
                'tags' => ['anak', 'komik'],
                'copies' => 2,
                'available' => 2,
            ],
            [
                'title' => 'E-book: Manuskrip Nusantara',
                'subtitle' => 'Koleksi digital',
                'responsibility_statement' => 'Pusat Manuskrip',
                'place_of_publication' => $demoCity,
                'publisher' => $demoPublisher,
                'publish_year' => 2024,
                'language' => 'ind',
                'ddc' => '091',
                'call_number' => '091 MAN',
                'isbn' => '9786024001022',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'notes' => 'Koleksi manuskrip digital.',
                'authors' => ['Pusat Manuskrip'],
                'subjects' => ['Manuskrip', 'Digital'],
                'tags' => ['digital'],
                'identifiers' => [
                    ['scheme' => 'uri', 'value' => 'https://katalog.example.id/ebook/manuskrip-nusantara', 'uri' => 'https://katalog.example.id/ebook/manuskrip-nusantara'],
                ],
                'copies' => 1,
                'available' => 1,
            ],
        ];
    }

    private function enrichRow(array $row, string $demoCity, string $demoPublisher): array
    {
        $material = strtolower(trim((string) ($row['material_type'] ?? 'buku')));
        $media = strtolower(trim((string) ($row['media_type'] ?? '')));
        if ($media === '') {
            $media = match ($material) {
                'audio' => 'audio',
                'video' => 'video',
                default => 'teks',
            };
        }

        $row['material_type'] = $material;
        $row['media_type'] = $media;
        $row['place_of_publication'] = $this->fillIfBlank($row['place_of_publication'] ?? null, $demoCity);
        $row['publisher'] = $this->fillIfBlank($row['publisher'] ?? null, $demoPublisher);
        $row['language'] = $this->fillIfBlank($row['language'] ?? null, 'ind');

        $authorLabel = $this->authorLabel($row['authors'] ?? []);
        $row['responsibility_statement'] = $this->fillIfBlank(
            $row['responsibility_statement'] ?? null,
            $authorLabel !== '' ? 'oleh ' . $authorLabel : null
        );

        $defaults = $this->defaultsForMaterial($material, $this->isOnlineRow($row));
        foreach ($defaults as $key => $value) {
            $row[$key] = $this->fillIfBlank($row[$key] ?? null, $value);
        }

        if ($this->isBlank($row['notes'] ?? null)) {
            $row['notes'] = $this->defaultNotes($row);
        }

        if ($this->isBlank($row['audience'] ?? null)) {
            $tags = collect($row['tags'] ?? [])
                ->map(fn($t) => strtolower(trim((string) $t)))
                ->filter()
                ->values()
                ->all();
            if (in_array('anak', $tags, true)) {
                $row['audience'] = 'anak';
            } elseif (in_array('remaja', $tags, true)) {
                $row['audience'] = 'remaja';
            }
        }

        return $row;
    }

    private function defaultsForMaterial(string $material, bool $isOnline): array
    {
        if ($material === 'serial') {
            return [
                'edition' => null,
                'series_title' => 'Seri Berkala NOTOBUKU',
                'physical_desc' => '1 volume ; 24 cm',
                'extent' => '1 volume',
                'dimensions' => '24 cm',
                'illustrations' => null,
                'bibliography_note' => null,
                'general_note' => 'Terbit berkala.',
                'frequency' => 'Bulanan',
                'serial_beginning' => 'Vol. 1 (2015)-',
                'serial_source_note' => 'Deskripsi berdasarkan: Vol. 12, no. 1 (2022).',
                'holdings_summary' => 'Vol. 10 (2020)-Vol. 12 (2022)',
            ];
        }

        if ($material === 'audio') {
            return [
                'edition' => 'Edisi audio',
                'series_title' => null,
                'physical_desc' => $isOnline ? '1 online audio file (320 min.)' : '1 audio disc (320 min.)',
                'extent' => $isOnline ? '1 file audio' : '1 keping audio',
                'dimensions' => $isOnline ? null : '12 cm',
                'illustrations' => null,
                'bibliography_note' => null,
                'general_note' => 'Rekaman audio; durasi bervariasi.',
            ];
        }

        if ($material === 'video') {
            return [
                'edition' => 'Edisi video',
                'series_title' => null,
                'physical_desc' => $isOnline ? '1 online video file (45 min.) : sound, color' : '1 video disc (45 min.) : sound, color',
                'extent' => $isOnline ? '1 file video' : '1 video disc',
                'dimensions' => $isOnline ? null : '12 cm',
                'illustrations' => 'berwarna',
                'bibliography_note' => null,
                'general_note' => 'Video pembelajaran.',
            ];
        }

        if ($material === 'peta') {
            return [
                'edition' => 'Edisi revisi',
                'series_title' => null,
                'physical_desc' => '1 peta : berwarna',
                'extent' => '1 lembar',
                'dimensions' => '60 x 80 cm',
                'illustrations' => 'berwarna',
                'bibliography_note' => null,
                'general_note' => 'Peta tematik dengan skala bervariasi.',
            ];
        }

        if ($isOnline) {
            return [
                'edition' => 'Edisi digital',
                'series_title' => 'Seri Koleksi Digital',
                'physical_desc' => '1 sumber daring (PDF)',
                'extent' => '1 berkas (PDF)',
                'dimensions' => null,
                'illustrations' => null,
                'bibliography_note' => null,
                'general_note' => 'Akses daring melalui katalog.',
            ];
        }

        return [
            'edition' => 'Edisi 1',
            'series_title' => 'Seri Koleksi NOTOBUKU',
            'physical_desc' => 'xii, 240 hlm',
            'extent' => '240 halaman',
            'dimensions' => '23 cm',
            'illustrations' => 'ilustrasi',
            'bibliography_note' => 'Bibliografi: hlm. 230-235',
            'general_note' => 'Termasuk indeks.',
        ];
    }

    private function defaultNotes(array $row): string
    {
        $material = trim((string) ($row['material_type'] ?? 'buku'));
        $subject = '';
        $subjects = $row['subjects'] ?? [];
        if (!empty($subjects)) {
            $first = $subjects[0];
            if (is_array($first)) {
                $subject = trim((string) ($first['term'] ?? $first['name'] ?? ''));
            } else {
                $subject = trim((string) $first);
            }
        }

        if ($subject !== '') {
            return ucfirst($material) . ' tentang ' . $subject . '.';
        }

        return 'Koleksi ' . $material . ' untuk pengembangan literasi dan layanan.';
    }

    private function authorLabel(array $authors): string
    {
        $names = [];
        foreach ($authors as $authorRow) {
            $name = is_array($authorRow)
                ? trim((string) ($authorRow['name'] ?? ''))
                : trim((string) $authorRow);
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return implode(', ', $names);
    }

    private function extractAuthor($authorRow): array
    {
        if (is_array($authorRow)) {
            $name = trim((string) ($authorRow['name'] ?? ''));
            $role = trim((string) ($authorRow['role'] ?? ''));
            return [$name, $role];
        }

        return [trim((string) $authorRow), ''];
    }

    private function extractSubject($subjectRow): array
    {
        if (is_array($subjectRow)) {
            $term = trim((string) ($subjectRow['term'] ?? $subjectRow['name'] ?? ''));
            $type = trim((string) ($subjectRow['type'] ?? 'topic')) ?: 'topic';
            $scheme = trim((string) ($subjectRow['scheme'] ?? 'local')) ?: 'local';
            return [$term, $type, $scheme];
        }

        return [trim((string) $subjectRow), 'topic', 'local'];
    }

    private function isOnlineRow(array $row): bool
    {
        $identifiers = $row['identifiers'] ?? [];
        foreach ($identifiers as $id) {
            if (!is_array($id)) {
                continue;
            }
            $scheme = strtolower(trim((string) ($id['scheme'] ?? '')));
            $uri = trim((string) ($id['uri'] ?? ''));
            $value = trim((string) ($id['value'] ?? ''));
            if (in_array($scheme, ['uri', 'url'], true)) {
                return true;
            }
            if ($uri !== '') {
                return true;
            }
            if ($scheme === 'doi' && str_starts_with($value, 'http')) {
                return true;
            }
        }
        return false;
    }

    private function fillIfBlank(?string $value, $fallback)
    {
        return $this->isBlank($value) ? $fallback : $value;
    }

    private function isBlank(?string $value): bool
    {
        return trim((string) $value) === '';
    }

    private function ensureDummyCover(string $title): ?string
    {
        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = 'koleksi';
        }
        $filename = 'covers/demo-' . $slug . '.svg';
        $disk = Storage::disk('public');
        if ($disk->exists($filename)) {
            return $filename;
        }

        $safeTitle = htmlspecialchars(mb_strimwidth($title, 0, 34, '...', 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="480" height="640" viewBox="0 0 480 640">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#0f172a"/>
      <stop offset="1" stop-color="#1e3a8a"/>
    </linearGradient>
  </defs>
  <rect width="480" height="640" rx="28" fill="url(#g)"/>
  <rect x="36" y="36" width="408" height="568" rx="22" fill="#f8fafc" opacity="0.08"/>
  <text x="48" y="120" font-family="Arial, Helvetica, sans-serif" font-size="26" font-weight="700" fill="#e2e8f0">NOTOBUKU</text>
  <text x="48" y="180" font-family="Arial, Helvetica, sans-serif" font-size="34" font-weight="800" fill="#ffffff">{$safeTitle}</text>
  <text x="48" y="580" font-family="Arial, Helvetica, sans-serif" font-size="16" font-weight="600" fill="#cbd5f5">Demo Collection</text>
</svg>
SVG;

        $disk->put($filename, $svg);
        return $filename;
    }
}
