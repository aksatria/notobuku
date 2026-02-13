<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoCollectionsSeeder extends Seeder
{
    private const MARKER = 'SEED-KOLEKSI-2026';
    private const ITEM_PREFIX = 'KOL-';
    private const ACC_PREFIX = 'ACC-KOL-';

    public function run(): void
    {
        $now = Carbon::now();

        $institutionId = DB::table('institutions')->orderBy('id')->value('id');
        if (!$institutionId) {
            $this->command?->warn('DemoCollectionsSeeder: institutions kosong, seeder dibatalkan.');
            return;
        }

        $branchId = DB::table('branches')
            ->where('institution_id', $institutionId)
            ->orderBy('id')
            ->value('id');

        $collections = $this->collections($now);

        foreach ($collections as $i => $c) {
            $title = $c['title'];
            $authorNames = $this->collectAuthors($c);
            $responsibility = $c['responsibility'] ?? null;
            if ($responsibility === null && !empty($authorNames)) {
                $responsibility = 'oleh ' . implode(', ', $authorNames);
            }

            $payload = [
                'institution_id' => $institutionId,
                'title' => $title,
                'subtitle' => $c['subtitle'] ?? null,
                'isbn' => $c['isbn'] ?? $this->fakeIsbn($i),
                'publisher' => $c['publisher'] ?? 'Noto Press',
                'publish_year' => $c['year'] ?? (int) $now->format('Y'),
                'language' => $c['language'] ?? 'ind',
                'ddc' => $c['ddc'] ?? null,
                'call_number' => $c['call_number'] ?? null,
                'notes' => self::MARKER,
                'updated_at' => $now,
                'created_at' => $now,
            ];

            if (Schema::hasColumn('biblio', 'material_type')) {
                $payload['material_type'] = $c['material_type'] ?? 'buku';
            }
            if (Schema::hasColumn('biblio', 'media_type')) {
                $payload['media_type'] = $c['media_type'] ?? 'teks';
            }
            if (Schema::hasColumn('biblio', 'audience')) {
                $payload['audience'] = $c['audience'] ?? 'umum';
            }
            if (Schema::hasColumn('biblio', 'place_of_publication')) {
                $payload['place_of_publication'] = $c['place'] ?? 'Jakarta';
            }
            if (Schema::hasColumn('biblio', 'responsibility_statement')) {
                $payload['responsibility_statement'] = $responsibility;
            }
            if (Schema::hasColumn('biblio', 'physical_desc')) {
                $payload['physical_desc'] = $c['physical_desc'] ?? 'xii, 240 hlm';
            }
            if (Schema::hasColumn('biblio', 'extent')) {
                $payload['extent'] = $c['extent'] ?? '240 halaman';
            }
            if (Schema::hasColumn('biblio', 'dimensions')) {
                $payload['dimensions'] = $c['dimensions'] ?? '23 cm';
            }
            if (Schema::hasColumn('biblio', 'illustrations')) {
                $payload['illustrations'] = $c['illustrations'] ?? 'ilustrasi';
            }
            if (Schema::hasColumn('biblio', 'general_note')) {
                $payload['general_note'] = $c['general_note'] ?? 'Demo koleksi untuk MARC21 + RDA Core';
            }
            if (Schema::hasColumn('biblio', 'normalized_title')) {
                $payload['normalized_title'] = $this->normalize($title);
            }
            if (Schema::hasColumn('biblio', 'ai_status')) {
                $payload['ai_status'] = 'approved';
            }

            DB::table('biblio')->updateOrInsert(
                [
                    'institution_id' => $institutionId,
                    'title' => $title,
                    'notes' => self::MARKER,
                ],
                $payload
            );

            $biblioId = (int) DB::table('biblio')
                ->where('institution_id', $institutionId)
                ->where('title', $title)
                ->where('notes', self::MARKER)
                ->value('id');

            if (!$biblioId) {
                continue;
            }

            $this->syncAuthors($biblioId, $authorNames, $now);
            $this->ensureCover($biblioId, $title, $now);

            $copies = (int) ($c['copies'] ?? 1);
            $copies = max(1, min($copies, 3));

            for ($copy = 1; $copy <= $copies; $copy++) {
                $code = sprintf('%02d-%02d', $i + 1, $copy);
                $barcode = self::ITEM_PREFIX . $code;
                $acc = self::ACC_PREFIX . $code;

                $itemPayload = [
                    'institution_id' => $institutionId,
                    'branch_id' => $branchId,
                    'shelf_id' => null,
                    'biblio_id' => $biblioId,
                    'barcode' => $barcode,
                    'accession_number' => $acc,
                    'inventory_code' => $acc,
                    'status' => 'available',
                    'acquired_at' => $now->copy()->subDays(60 + ($i * 2))->toDateString(),
                    'notes' => self::MARKER,
                    'updated_at' => $now,
                    'created_at' => $now,
                ];

                if (Schema::hasColumn('items', 'acquisition_source')) {
                    $itemPayload['acquisition_source'] = 'beli';
                }
                if (Schema::hasColumn('items', 'price')) {
                    $itemPayload['price'] = 0;
                }
                if (Schema::hasColumn('items', 'inventory_number')) {
                    $itemPayload['inventory_number'] = 'INV-' . $code;
                }
                if (Schema::hasColumn('items', 'circulation_status')) {
                    $itemPayload['circulation_status'] = 'circulating';
                }
                if (Schema::hasColumn('items', 'is_reference')) {
                    $itemPayload['is_reference'] = (bool) ($c['is_reference'] ?? false);
                }
                if (Schema::hasColumn('items', 'location_note')) {
                    $itemPayload['location_note'] = 'Demo koleksi';
                }

                DB::table('items')->updateOrInsert(
                    ['barcode' => $barcode],
                    $itemPayload
                );
            }
        }

        $this->command?->info('DemoCollectionsSeeder OK');
    }

    private function normalize(string $title): string
    {
        return (string) Str::of($title)
            ->lower()
            ->replaceMatches('/[^\p{L}\p{N}\s]/u', ' ')
            ->squish();
    }

    private function fakeIsbn(int $i): string
    {
        return '978-602-000' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
    }

    private function collectAuthors(array $c): array
    {
        $authors = $c['author'] ?? $c['authors'] ?? [];
        if (is_string($authors)) {
            $authors = [$authors];
        }
        if (!is_array($authors)) {
            return [];
        }
        return collect($authors)
            ->map(fn($a) => trim((string) $a))
            ->filter(fn($a) => $a !== '')
            ->values()
            ->all();
    }

    private function syncAuthors(int $biblioId, array $authors, Carbon $now): void
    {
        if (empty($authors)) {
            return;
        }
        if (!Schema::hasTable('authors') || !Schema::hasTable('biblio_author')) {
            return;
        }

        DB::table('biblio_author')
            ->where('biblio_id', $biblioId)
            ->where('role', 'pengarang')
            ->delete();

        foreach ($authors as $i => $name) {
            $normalized = $this->normalize($name);
            $payload = [
                'name' => $name,
                'updated_at' => $now,
                'created_at' => $now,
            ];

            $unique = ['name' => $name];
            if (Schema::hasColumn('authors', 'normalized_name')) {
                $payload['normalized_name'] = $normalized;
                $unique = ['normalized_name' => $normalized];
            }

            DB::table('authors')->updateOrInsert($unique, $payload);
            $authorId = (int) DB::table('authors')->where($unique)->value('id');
            if ($authorId <= 0) {
                continue;
            }

            DB::table('biblio_author')->updateOrInsert(
                [
                    'biblio_id' => $biblioId,
                    'author_id' => $authorId,
                    'role' => 'pengarang',
                ],
                [
                    'sort_order' => $i + 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function ensureCover(int $biblioId, string $title, Carbon $now): void
    {
        if (!Schema::hasColumn('biblio', 'cover_path')) {
            return;
        }

        $existing = (string) DB::table('biblio')->where('id', $biblioId)->value('cover_path');
        if ($existing !== '') {
            return;
        }

        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = 'koleksi';
        }
        $filename = 'covers/demo-' . $slug . '.svg';
        $disk = Storage::disk('public');
        if (!$disk->exists($filename)) {
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
        }

        DB::table('biblio')
            ->where('id', $biblioId)
            ->update([
                'cover_path' => $filename,
                'updated_at' => $now,
            ]);
    }

    private function collections(Carbon $now): array
    {
        return [
            [
                'title' => 'Filsafat Dasar',
                'author' => 'Nadia Puspita',
                'ddc' => '100',
                'call_number' => '100 FIL',
                'year' => 2021,
                'copies' => 2,
                'place' => 'Jakarta',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Etika Modern',
                'author' => 'Raka Suryanto',
                'ddc' => '170',
                'call_number' => '170 ETI',
                'year' => 2020,
                'place' => 'Bandung',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Logika Berpikir Kritis',
                'author' => 'Siti Rahmawati',
                'ddc' => '160',
                'call_number' => '160 LOG',
                'year' => 2019,
                'place' => 'Yogyakarta',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Sejarah Filsafat Dunia',
                'author' => 'Bimo Prakoso',
                'ddc' => '109',
                'call_number' => '109 SEJ',
                'year' => 2018,
                'place' => 'Jakarta',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Pengantar Ilmu Umum',
                'author' => 'Fajar Nugroho',
                'ddc' => '000',
                'call_number' => '000 PENG',
                'year' => 2022,
                'copies' => 2,
                'place' => 'Surabaya',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Ensiklopedia Ringkas',
                'author' => 'Tim Referensi',
                'ddc' => '030',
                'call_number' => '030 ENS',
                'year' => 2017,
                'place' => 'Jakarta',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
                'illustrations' => 'ilustrasi',
            ],
            [
                'title' => 'Metode Riset Umum',
                'author' => 'Ahmad Syahrul',
                'ddc' => '001',
                'call_number' => '001 MET',
                'year' => 2023,
                'place' => 'Malang',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Novel Nusantara',
                'author' => 'Dita Laras',
                'ddc' => '899.221',
                'call_number' => '899.221 NOV',
                'year' => 2016,
                'copies' => 2,
                'place' => 'Denpasar',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Novel Klasik Dunia',
                'author' => 'John Carter',
                'ddc' => '823',
                'call_number' => '823 NOV',
                'year' => 2015,
                'place' => 'Jakarta',
                'language' => 'eng',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Cerita Pendek Modern',
                'author' => 'Reni Maharani',
                'ddc' => '808.83',
                'call_number' => '808.83 CER',
                'year' => 2019,
                'place' => 'Semarang',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Pengantar Ilmu Komputer',
                'author' => 'Lina Kartika',
                'ddc' => '004',
                'call_number' => '004 PENG',
                'year' => 2024,
                'copies' => 2,
                'place' => 'Jakarta',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Algoritma dan Struktur Data',
                'author' => 'Farhan Idris',
                'ddc' => '005.1',
                'call_number' => '005.1 ALG',
                'year' => 2023,
                'copies' => 2,
                'place' => 'Bandung',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Pemrograman Web Modern',
                'author' => 'Raka Suryanto',
                'ddc' => '005.133',
                'call_number' => '005.133 WEB',
                'year' => 2022,
                'copies' => 2,
                'place' => 'Jakarta',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Basis Data Modern',
                'author' => 'Siti Rahmawati',
                'ddc' => '005.74',
                'call_number' => '005.74 BAS',
                'year' => 2022,
                'place' => 'Surabaya',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Jaringan Komputer',
                'author' => 'Farhan Idris',
                'ddc' => '004.6',
                'call_number' => '004.6 JAR',
                'year' => 2021,
                'place' => 'Jakarta',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Kecerdasan Buatan',
                'author' => 'Nadia Puspita',
                'ddc' => '006.3',
                'call_number' => '006.3 KEC',
                'year' => 2024,
                'copies' => 2,
                'place' => 'Jakarta',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Sains Populer',
                'author' => 'Tim Sains',
                'ddc' => '500',
                'call_number' => '500 SAI',
                'year' => 2018,
                'place' => 'Bogor',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Fisika Dasar',
                'author' => 'Bimo Prakoso',
                'ddc' => '530',
                'call_number' => '530 FIS',
                'year' => 2020,
                'place' => 'Jakarta',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Ekonomi Dasar',
                'author' => 'Mira Andini',
                'ddc' => '330',
                'call_number' => '330 EKO',
                'year' => 2021,
                'place' => 'Jakarta',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
            [
                'title' => 'Pendidikan dan Pengajaran',
                'author' => 'Reni Maharani',
                'ddc' => '370',
                'call_number' => '370 PEND',
                'year' => 2019,
                'place' => 'Jakarta',
                'language' => 'ind',
                'material_type' => 'buku',
                'media_type' => 'teks',
            ],
        ];
    }
}
