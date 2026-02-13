<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShelvesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        /**
         * Catatan desain:
         * - Rak bersifat PER CABANG
         * - code unik dalam 1 cabang
         * - DDC-friendly (000–900)
         */

        // Ambil semua cabang aktif
        $branches = DB::table('branches')
            ->where('is_active', 1)
            ->get(['id', 'institution_id']);

        foreach ($branches as $branch) {

            $institutionId = $branch->institution_id;
            $branchId      = $branch->id;

            $shelves = [

                // =========================
                // UMUM
                // =========================
                [
                    'name'       => 'Referensi',
                    'code'       => 'REF',
                    'location'   => 'Dekat meja layanan',
                    'notes'      => 'Ensiklopedia, kamus, atlas',
                    'sort_order' => 1,
                ],
                [
                    'name'       => 'Fiksi Dewasa',
                    'code'       => 'FIK',
                    'location'   => 'Area umum',
                    'notes'      => 'Novel, cerpen, sastra',
                    'sort_order' => 10,
                ],
                [
                    'name'       => 'Fiksi Anak',
                    'code'       => 'FIK-A',
                    'location'   => 'Area anak',
                    'notes'      => 'Cerita anak & remaja',
                    'sort_order' => 11,
                ],

                // =========================
                // DDC 000–900
                // =========================
                [
                    'name' => '000 – Karya Umum & Ilmu Komputer',
                    'code' => 'DDC-000',
                    'location' => 'Rak DDC',
                    'notes' => 'Ensiklopedia, komputer, informasi',
                    'sort_order' => 100,
                ],
                [
                    'name' => '100 – Filsafat & Psikologi',
                    'code' => 'DDC-100',
                    'location' => 'Rak DDC',
                    'notes' => 'Filsafat, psikologi',
                    'sort_order' => 110,
                ],
                [
                    'name' => '200 – Agama',
                    'code' => 'DDC-200',
                    'location' => 'Rak DDC',
                    'notes' => 'Agama & kepercayaan',
                    'sort_order' => 120,
                ],
                [
                    'name' => '300 – Ilmu Sosial',
                    'code' => 'DDC-300',
                    'location' => 'Rak DDC',
                    'notes' => 'Sosiologi, ekonomi, hukum',
                    'sort_order' => 130,
                ],
                [
                    'name' => '400 – Bahasa',
                    'code' => 'DDC-400',
                    'location' => 'Rak DDC',
                    'notes' => 'Bahasa & linguistik',
                    'sort_order' => 140,
                ],
                [
                    'name' => '500 – Ilmu Murni',
                    'code' => 'DDC-500',
                    'location' => 'Rak DDC',
                    'notes' => 'Matematika, IPA',
                    'sort_order' => 150,
                ],
                [
                    'name' => '600 – Ilmu Terapan',
                    'code' => 'DDC-600',
                    'location' => 'Rak DDC',
                    'notes' => 'Teknologi, kesehatan',
                    'sort_order' => 160,
                ],
                [
                    'name' => '700 – Seni & Olahraga',
                    'code' => 'DDC-700',
                    'location' => 'Rak DDC',
                    'notes' => 'Seni, musik, olahraga',
                    'sort_order' => 170,
                ],
                [
                    'name' => '800 – Sastra',
                    'code' => 'DDC-800',
                    'location' => 'Rak DDC',
                    'notes' => 'Puisi, drama, kritik sastra',
                    'sort_order' => 180,
                ],
                [
                    'name' => '900 – Sejarah & Geografi',
                    'code' => 'DDC-900',
                    'location' => 'Rak DDC',
                    'notes' => 'Sejarah dunia & Indonesia',
                    'sort_order' => 190,
                ],

                // =========================
                // KHUSUS
                // =========================
                [
                    'name'       => 'Koleksi Lokal',
                    'code'       => 'LOK',
                    'location'   => 'Rak khusus',
                    'notes'      => 'Karya lokal / muatan lokal',
                    'sort_order' => 300,
                ],
                [
                    'name'       => 'Terbitan Berkala',
                    'code'       => 'PER',
                    'location'   => 'Rak majalah',
                    'notes'      => 'Majalah & jurnal',
                    'sort_order' => 310,
                ],
            ];

            foreach ($shelves as $shelf) {
                DB::table('shelves')->updateOrInsert(
                    [
                        'institution_id' => $institutionId,
                        'branch_id'      => $branchId,
                        'code'           => $shelf['code'], // 🔑 patokan unik
                    ],
                    [
                        'name'        => $shelf['name'],
                        'location'    => $shelf['location'] ?? null,
                        'notes'       => $shelf['notes'] ?? null,
                        'sort_order'  => $shelf['sort_order'] ?? 0,
                        'is_active'   => 1,
                        'updated_at'  => $now,
                        'created_at'  => $now,
                    ]
                );
            }
        }
    }
}
