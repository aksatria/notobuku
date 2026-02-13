<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EmhaTitlesSeeder extends Seeder
{
    public function run(): void
    {
        $institutionId = DB::table('institutions')->where('code', 'NOTO-01')->value('id');
        if (!$institutionId) {
            $institutionId = DB::table('institutions')->value('id');
        }

        if (!$institutionId) {
            $this->command?->warn('No institutions found. Run NotobukuSeeder first.');
            return;
        }

        $titles = [
            'Slilit Sang Kiai',
            'Markesot Bertutur',
            'Markesot Bertutur Lagi',
            'Tuhan Pun “Berpuasa”',
            'Hidup Itu Harus Pintar Ngegas & Ngerem',
            'Indonesia Bagian dari Desa Saya',
            '99 untuk Tuhanku',
            'Gelandangan di Kampung Sendiri',
            'BH',
            'Kiai Bejo, Kiai Untung, Kiai Hoki',
            'Jejak Tinju Pak Kiai',
            'Surat kepada Kanjeng Nabi',
            'Orang Maiyah',
            'Gerakan Punakawan Atawa Arus Bawah',
            'Seribu Masjid Satu Jumlahnya: Tahajjud Cinta Seorang Hamba',
            'Iblis Nusantara Dajjal Dunia',
            'Secangkir Kopi Jon Pakir',
            'Demokrasi La Roiba Fih',
            'Syair Lautan Jilbab',
            'Kiai Hologram',
            'Ibu, Tamparlah Mulut Anakmu',
            'Kafir Liberal',
            'Tidak, Jibril Tidak Pensiun',
            'Sesobek Buku Harian Indonesia',
            'Allah Tidak Cerewet Seperti Kita',
        ];

        $now = now();
        $hasNormalizedTitle = Schema::hasColumn('biblio', 'normalized_title');
        $hasMaterialType = Schema::hasColumn('biblio', 'material_type');
        $hasMediaType = Schema::hasColumn('biblio', 'media_type');

        $defaults = [
            'publisher' => null,
            'place_of_publication' => null,
            'publish_year' => null,
            'language' => 'id',
            'edition' => null,
            'physical_desc' => null,
            'ddc' => null,
        ];

        $catalog = [
            'Slilit Sang Kiai' => [
                'publisher' => 'Pustaka Utama Grafiti',
                'place_of_publication' => 'Jakarta',
                'publish_year' => 1991,
                'isbn' => '9794441783',
                'ddc' => '899.214',
                'call_number' => '899.214 Nad S',
                'physical_desc' => 'xv, 243 hlm.; 21 cm',
                'subjects' => ['Tokoh agama', 'Biografi'],
                'tags' => ['biografi', 'keagamaan', 'esai'],
                'notes' => 'Biografi dan refleksi tentang sosok kiai dalam tradisi pesantren.',
            ],
            'Markesot Bertutur' => [
                'publisher' => 'Mizan',
                'place_of_publication' => 'Ujungberung, Bandung',
                'publish_year' => 2012,
                'isbn' => '9789794337233',
                'physical_desc' => '471 hlm.; 21 cm',
                'edition' => 'Ed. baru, cet. 1',
                'subjects' => ['Sastra Indonesia', 'Esai', 'Sosial'],
                'tags' => ['esai', 'sosial', 'sastra'],
                'notes' => 'Kumpulan esai reflektif tokoh Markesot tentang kehidupan dan masyarakat.',
            ],
            'Markesot Bertutur Lagi' => [
                'publisher' => 'Mizan',
                'place_of_publication' => 'Jakarta',
                'publish_year' => 2013,
                'isbn' => '9789794337639',
                'physical_desc' => '342 hlm.; 21 cm',
                'edition' => 'Edisi baru, cet. 1',
                'subjects' => ['Sastra Indonesia', 'Esai', 'Sosial'],
                'tags' => ['esai', 'sosial', 'sastra'],
                'notes' => 'Lanjutan renungan dan dialog Markesot tentang realitas sosial.',
            ],
            'Tuhan Pun â€œBerpuasaâ€' => [
                'publisher' => 'Gramedia Pustaka Utama',
                'place_of_publication' => 'Jakarta',
                'publish_year' => 2012,
                'isbn' => '9789797096564',
                'ddc' => '297.413',
                'call_number' => '297.413 NAD t',
                'physical_desc' => 'ix, 235 hlm.; 21 cm',
                'edition' => 'Cet. 3',
                'subjects' => ['Islam', 'Spiritualitas'],
                'tags' => ['agama', 'spiritual', 'esai'],
                'notes' => 'Refleksi spiritual tentang puasa dan keheningan batin.',
            ],
            'Hidup Itu Harus Pintar Ngegas & Ngerem' => [
                'publisher' => 'Noura Books',
                'place_of_publication' => 'Jagakarsa, Jakarta; Ujungberung, Bandung',
                'publish_year' => 2018,
                'isbn' => '9786023851508',
                'physical_desc' => 'x, 230 hlm.; 21 cm',
                'edition' => 'Cet. 9',
                'subjects' => ['Islam', 'Motivasi', 'Etika'],
                'tags' => ['agama', 'motivasi', 'esai'],
                'notes' => 'Wejangan dan refleksi tentang keseimbangan hidup.',
            ],
            '99 untuk Tuhanku' => [
                'publisher' => 'Bentang Pustaka',
                'place_of_publication' => 'Sleman, Yogyakarta',
                'publish_year' => 2016,
                'isbn' => '9786022910657',
                'physical_desc' => '109 hlm.; 21 cm',
                'edition' => 'Cet. 3',
                'subjects' => ['Puisi Indonesia', 'Spiritualitas Islam'],
                'tags' => ['puisi', 'sastra', 'spiritual'],
                'notes' => 'Kumpulan puisi zikir dan renungan spiritual.',
            ],
            'Indonesia Bagian dari Desa Saya' => [
                'publisher' => 'Sipress',
                'place_of_publication' => 'Yogyakarta',
                'publish_year' => 1992,
                'isbn' => '9798251032',
                'ddc' => '808.88',
                'call_number' => '808.88 NAD i',
                'physical_desc' => 'xviii, 231 hlm.; 20 cm',
                'edition' => 'Cet. 2',
                'subjects' => ['Sosial', 'Budaya', 'Indonesia'],
                'tags' => ['sosial', 'budaya', 'esai'],
                'notes' => 'Esai dan kritik sosial tentang realitas Indonesia.',
            ],
            'Gelandangan di Kampung Sendiri' => [
                'publisher' => 'Bentang Pustaka',
                'place_of_publication' => null,
                'publish_year' => 2018,
                'isbn' => '9786022914723',
                'physical_desc' => '304 hlm.; 21 cm',
                'edition' => null,
                'subjects' => ['Sosial', 'Budaya'],
                'tags' => ['sosial', 'budaya', 'esai'],
                'notes' => 'Esai sosial-budaya tentang masyarakat dan kehidupan sehari-hari.',
            ],
            'BH' => [
                'publisher' => 'Penerbit Buku Kompas',
                'place_of_publication' => 'Jakarta',
                'publish_year' => 2005,
                'isbn' => '9797091686',
                'physical_desc' => 'x, 246 hlm.; 21 cm',
                'subjects' => ['Sastra Indonesia', 'Esai'],
                'tags' => ['sastra', 'esai'],
                'notes' => 'Kumpulan esai dan renungan sosial-budaya.',
            ],
            'Kiai Bejo, Kiai Untung, Kiai Hoki' => [
                'publisher' => 'Penerbit Buku Kompas',
                'place_of_publication' => 'Jakarta',
                'publish_year' => 2007,
                'isbn' => '9789797093112',
                'ddc' => '808.84',
                'call_number' => '808.84 NAD k',
                'physical_desc' => 'vi, 258 hlm.; 21 cm',
                'subjects' => ['Esai', 'Sosial'],
                'tags' => ['esai', 'sosial', 'budaya'],
                'notes' => 'Esai tentang dinamika pemikiran dan realitas sosial.',
            ],
            'Jejak Tinju Pak Kiai' => [
                'publisher' => 'Penerbit Buku Kompas',
                'place_of_publication' => 'Jakarta',
                'publish_year' => 2008,
                'isbn' => '9789797093631',
                'physical_desc' => 'xiii, 240 hlm.; 21 cm',
                'subjects' => ['Esai', 'Sosial'],
                'tags' => ['esai', 'sosial', 'budaya'],
                'notes' => 'Kumpulan esai tentang kehidupan sosial dan budaya.',
            ],
            'Surat kepada Kanjeng Nabi' => [
                'publisher' => 'Mizan',
                'place_of_publication' => 'Bandung',
                'publish_year' => 1996,
                'isbn' => '9794331058',
                'physical_desc' => 'xxxii, 454 hlm.; 21 cm',
                'subjects' => ['Islam', 'Spiritualitas'],
                'tags' => ['agama', 'spiritual', 'esai'],
                'notes' => 'Renungan dan surat-surat spiritual kepada Nabi.',
            ],
            'Gerakan Punakawan Atawa Arus Bawah' => [
                'publisher' => 'Bentang',
                'place_of_publication' => 'Jakarta',
                'publish_year' => 1994,
                'isbn' => null,
                'physical_desc' => 'viii, 228 hlm.; 21 cm',
                'subjects' => ['Sosial', 'Budaya'],
                'tags' => ['sosial', 'budaya', 'esai'],
                'notes' => 'Esai sosial tentang arus bawah dan kebudayaan.',
            ],
            'Seribu Masjid Satu Jumlahnya: Tahajjud Cinta Seorang Hamba' => [
                'publisher' => 'PT Mizan Pustaka',
                'place_of_publication' => 'Bandung',
                'publish_year' => 2016,
                'isbn' => '9789794339237',
                'ddc' => '811',
                'call_number' => '811 NAD s',
                'physical_desc' => '196 hlm.; 20.5 cm',
                'edition' => 'Cet. 1',
                'subjects' => ['Puisi Indonesia', 'Spiritualitas Islam'],
                'tags' => ['puisi', 'spiritual', 'sastra'],
                'notes' => 'Kumpulan puisi dan renungan spiritual.',
            ],
            'Iblis Nusantara Dajjal Dunia' => [
                'publisher' => 'Zaituna',
                'place_of_publication' => 'Yogyakarta',
                'publish_year' => 1998,
                'isbn' => '9799010047',
                'physical_desc' => '244 hlm.; 21 cm',
                'subjects' => ['Islam', 'Spiritualitas'],
                'tags' => ['agama', 'spiritual', 'esai'],
                'notes' => 'Esai dan renungan tentang spiritualitas dan moralitas.',
            ],
            'Secangkir Kopi Jon Pakir' => [
                'publisher' => 'Mizan Pustaka',
                'place_of_publication' => 'Bandung',
                'publish_year' => 2016,
                'isbn' => '9789794339732',
                'physical_desc' => '345 hlm.; 21 cm',
                'edition' => 'Edisi 2, cet. 2',
                'subjects' => ['Sastra Indonesia', 'Esai'],
                'tags' => ['sastra', 'esai'],
                'notes' => 'Kumpulan cerita dan esai reflektif.',
            ],
            'Demokrasi La Roiba Fih' => [
                'publisher' => 'Penerbit Buku Kompas',
                'place_of_publication' => 'Jakarta',
                'publish_year' => 2009,
                'isbn' => '9789797094270',
                'physical_desc' => 'vi, 282 hlm.; 21 cm',
                'subjects' => ['Politik', 'Sosial'],
                'tags' => ['politik', 'sosial', 'esai'],
                'notes' => 'Esai tentang demokrasi dan dinamika sosial-politik.',
            ],
            'Syair Lautan Jilbab' => [
                'publisher' => 'Sipress',
                'place_of_publication' => 'Yogyakarta',
                'publish_year' => 1994,
                'isbn' => null,
                'ddc' => '808.8',
                'call_number' => '808.8 NAJ s',
                'physical_desc' => 'vi, 52 hlm.; 19 cm',
                'edition' => 'Cet. 3',
                'subjects' => ['Puisi Indonesia'],
                'tags' => ['puisi', 'sastra'],
                'notes' => 'Kumpulan puisi bertema spiritual dan sosial.',
            ],
            'Kiai Hologram' => [
                'publisher' => 'Bentang Pustaka',
                'place_of_publication' => 'Yogyakarta',
                'publish_year' => 2018,
                'isbn' => '9786022914686',
                'physical_desc' => 'vii, 285 hlm.; 21 cm',
                'edition' => 'Cet. 1',
                'subjects' => ['Islam', 'Sosial', 'Budaya'],
                'tags' => ['agama', 'sosial', 'esai'],
                'notes' => 'Renungan keislaman dan kebudayaan dalam konteks sosial Indonesia.',
            ],
            'Ibu, Tamparlah Mulut Anakmu' => [
                'publisher' => 'Zaituna',
                'place_of_publication' => 'Yogyakarta',
                'publish_year' => 2000,
                'isbn' => '9799010098',
                'physical_desc' => '142 hlm.; 18 cm',
                'edition' => 'Cet. 1',
                'subjects' => ['Hubungan Orangtua dan Anak'],
                'tags' => ['keluarga', 'pendidikan', 'esai'],
                'notes' => 'Catatan reflektif tentang relasi orangtua dan anak.',
            ],
            'Kafir Liberal' => [
                'publisher' => 'Progres',
                'place_of_publication' => 'Yogyakarta',
                'publish_year' => 2005,
                'isbn' => '9799010128',
                'physical_desc' => '56 hlm.; 21 cm',
                'edition' => 'Cet. 1',
                'subjects' => ['Islam', 'Politik', 'Indonesia'],
                'tags' => ['agama', 'politik', 'esai'],
                'notes' => 'Esai ringkas tentang relasi agama, wacana publik, dan kebebasan berpikir.',
            ],
            'Sesobek Buku Harian Indonesia' => [
                'publisher' => 'Bentang',
                'place_of_publication' => 'Yogyakarta',
                'publish_year' => 2017,
                'isbn' => '9786022912866',
                'ddc' => '899.2211',
                'call_number' => '899.2211 NAD s',
                'physical_desc' => 'viii, 124 hlm.; 20.5 cm',
                'subjects' => ['Indonesia', 'Puisi Indonesia'],
                'tags' => ['puisi', 'sastra', 'indonesia'],
                'notes' => 'Kumpulan puisi dan catatan tentang Indonesia.',
            ],
            'Allah Tidak Cerewet Seperti Kita' => [
                'publisher' => 'Noura Books',
                'place_of_publication' => 'Jakarta',
                'publish_year' => 2019,
                'isbn' => '9786023858125',
                'ddc' => '297.07',
                'call_number' => '297.07 NAD a',
                'physical_desc' => '238 hlm.; 21 cm',
                'edition' => 'Cet. 1',
                'subjects' => ['Islam', 'Ceramah', 'Etika'],
                'tags' => ['agama', 'ceramah', 'spiritual'],
                'notes' => 'Kumpulan ceramah yang menekankan Islam yang luwes dan menenangkan.',
            ],
        ];

        $authorName = 'Emha Ainun Nadjib';
        $authorNormalized = Str::of($authorName)->lower()->squish()->toString();
        $authorId = DB::table('authors')->updateOrInsert(
            ['normalized_name' => $authorNormalized],
            [
                'name' => $authorName,
                'normalized_name' => $authorNormalized,
                'type' => 'personal',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $authorId = DB::table('authors')->where('normalized_name', $authorNormalized)->value('id');

        $branchId = DB::table('branches')
            ->where('institution_id', $institutionId)
            ->where('is_active', 1)
            ->orderBy('id')
            ->value('id');

        $shelfId = null;
        if ($branchId) {
            $shelfId = DB::table('shelves')
                ->where('branch_id', $branchId)
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->value('id');
        }

        foreach ($titles as $title) {
            $meta = $catalog[$title] ?? [];
            $merged = array_merge($defaults, $meta);

            $callNumber = $merged['call_number'] ?? null;
            if (!$callNumber && !empty($merged['ddc'])) {
                $initial = Str::of($title)->lower()->ascii()->squish()->substr(0, 1)->toString();
                $callNumber = trim($merged['ddc'] . ' NAD ' . $initial);
            }

            $notes = $merged['notes'] ?? null;
            if (!$notes) {
                $ddc = (string)($merged['ddc'] ?? '');
                $firstDigit = $ddc !== '' ? $ddc[0] : '';
                if ($firstDigit === '2') {
                    $notes = 'Esai dan renungan keislaman serta spiritualitas.';
                } elseif ($firstDigit === '3') {
                    $notes = 'Esai sosial-budaya tentang masyarakat dan kehidupan sehari-hari.';
                } elseif ($firstDigit === '8') {
                    $notes = 'Kumpulan puisi dan renungan sastra.';
                } elseif ($firstDigit === '9') {
                    $notes = 'Biografi dan refleksi tentang tokoh dan tradisi.';
                } else {
                    $notes = 'Esai reflektif tentang kehidupan dan budaya.';
                }
            }

            $payload = [
                'institution_id' => $institutionId,
                'title' => $title,
                'responsibility_statement' => $authorName,
                'isbn' => $merged['isbn'] ?? null,
                'publisher' => $merged['publisher'] ?? null,
                'place_of_publication' => $merged['place_of_publication'] ?? null,
                'publish_year' => $merged['publish_year'] ?? null,
                'language' => $merged['language'] ?? 'id',
                'edition' => $merged['edition'] ?? null,
                'physical_desc' => $merged['physical_desc'] ?? null,
                'ddc' => $merged['ddc'] ?? null,
                'call_number' => $callNumber,
                'notes' => $notes,
                'ai_status' => 'approved',
                'updated_at' => $now,
                'created_at' => $now,
            ];

            if ($hasNormalizedTitle) {
                $payload['normalized_title'] = Str::of($title)->lower()->squish()->toString();
            }

            if ($hasMaterialType) {
                $payload['material_type'] = 'buku';
            }

            if ($hasMediaType) {
                $payload['media_type'] = 'teks';
            }

            DB::table('biblio')->updateOrInsert(
                ['institution_id' => $institutionId, 'title' => $title],
                $payload
            );

            $biblioId = DB::table('biblio')
                ->where('institution_id', $institutionId)
                ->where('title', $title)
                ->value('id');

            if ($biblioId && $authorId) {
                DB::table('biblio_author')->updateOrInsert(
                    [
                        'biblio_id' => $biblioId,
                        'author_id' => $authorId,
                        'role' => 'pengarang',
                    ],
                    [
                        'sort_order' => 1,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                // Buat 3 eksemplar per judul (idempotent via barcode)
                for ($i = 1; $i <= 3; $i++) {
                    $barcode = sprintf('EMHA-%05d-%02d', $biblioId, $i);
                    $accession = sprintf('ACC-%05d-%02d', $biblioId, $i);

                    DB::table('items')->updateOrInsert(
                        ['barcode' => $barcode],
                        [
                            'institution_id' => $institutionId,
                            'branch_id' => $branchId,
                            'shelf_id' => $shelfId,
                            'biblio_id' => $biblioId,
                            'barcode' => $barcode,
                            'accession_number' => $accession,
                            'inventory_code' => null,
                            'status' => 'available',
                            'acquired_at' => $now->toDateString(),
                            'price' => null,
                            'source' => 'seed',
                            'notes' => null,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }
            }

            $subjects = $meta['subjects'] ?? null;
            if (!$subjects) {
                $ddc = (string)($merged['ddc'] ?? '');
                $firstDigit = $ddc !== '' ? $ddc[0] : '';
                if ($firstDigit === '2') {
                    $subjects = ['Islam', 'Spiritualitas'];
                } elseif ($firstDigit === '3') {
                    $subjects = ['Sosial', 'Budaya'];
                } elseif ($firstDigit === '8') {
                    $subjects = ['Puisi Indonesia'];
                } elseif ($firstDigit === '9') {
                    $subjects = ['Biografi'];
                } else {
                    $subjects = ['Sastra Indonesia'];
                }
            }

            foreach ($subjects as $subjectName) {
                DB::table('subjects')->updateOrInsert(
                    ['name' => $subjectName],
                    ['updated_at' => $now, 'created_at' => $now]
                );

                $subjectId = DB::table('subjects')->where('name', $subjectName)->value('id');
                if ($subjectId && $biblioId) {
                    DB::table('biblio_subject')->updateOrInsert(
                        ['biblio_id' => $biblioId, 'subject_id' => $subjectId],
                        [
                            'source' => 'manual',
                            'status' => 'approved',
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }
            }

            $tags = $meta['tags'] ?? null;
            if (!$tags) {
                $ddc = (string)($merged['ddc'] ?? '');
                $firstDigit = $ddc !== '' ? $ddc[0] : '';
                if ($firstDigit === '2') {
                    $tags = ['agama', 'spiritual', 'esai'];
                } elseif ($firstDigit === '3') {
                    $tags = ['sosial', 'budaya', 'esai'];
                } elseif ($firstDigit === '8') {
                    $tags = ['puisi', 'sastra'];
                } elseif ($firstDigit === '9') {
                    $tags = ['biografi', 'sejarah'];
                } else {
                    $tags = ['sastra', 'esai'];
                }
            }

            foreach ($tags as $tagName) {
                $tagNorm = Str::of($tagName)->lower()->squish()->toString();
                DB::table('tags')->updateOrInsert(
                    ['name' => $tagName],
                    [
                        'normalized_name' => $tagNorm,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                $tagId = DB::table('tags')->where('name', $tagName)->value('id');
                if ($tagId && $biblioId) {
                    DB::table('biblio_tag')->updateOrInsert(
                        ['biblio_id' => $biblioId, 'tag_id' => $tagId],
                        [
                            'source' => 'manual',
                            'status' => 'approved',
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }
            }
        }

        $this->command?->info('EmhaTitlesSeeder OK: ' . count($titles) . ' judul + author + items + metadata.');
    }
}
