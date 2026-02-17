<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DdcShelvesSeeder extends Seeder
{
    private function ddcHundredsLabel(int $hundreds): string
    {
        return match ($hundreds) {
            0 => 'Karya Umum, Ilmu Komputer, Informasi',
            1 => 'Filsafat dan Psikologi',
            2 => 'Agama',
            3 => 'Ilmu Sosial',
            4 => 'Bahasa',
            5 => 'Sains',
            6 => 'Teknologi',
            7 => 'Seni dan Rekreasi',
            8 => 'Sastra',
            9 => 'Sejarah dan Geografi',
            default => 'Subjek Umum',
        };
    }

    public function run(): void
    {
        $branches = DB::table('branches')
            ->select('id', 'institution_id', 'name')
            ->where('is_active', 1)
            ->orderBy('institution_id')
            ->orderBy('name')
            ->get();

        if ($branches->isEmpty()) {
            $this->command?->warn('DdcShelvesSeeder: tidak ada cabang aktif.');
            return;
        }

        foreach ($branches as $branch) {
            for ($hundreds = 0; $hundreds <= 9; $hundreds++) {
                for ($tens = 0; $tens <= 9; $tens++) {
                    $code3 = str_pad((string) ($hundreds * 100 + $tens * 10), 3, '0', STR_PAD_LEFT);
                    $end = str_pad((string) ((int) $code3 + 9), 3, '0', STR_PAD_LEFT);
                    $code = 'DDC-' . $code3;

                    $name = $tens === 0
                        ? $code3 . ' ' . $this->ddcHundredsLabel($hundreds)
                        : $code3 . '-' . $end . ' Subkelas ' . $this->ddcHundredsLabel($hundreds);

                    DB::table('shelves')->updateOrInsert(
                        [
                            'institution_id' => (int) $branch->institution_id,
                            'branch_id' => (int) $branch->id,
                            'code' => $code,
                        ],
                        [
                            'name' => $name,
                            'location' => 'Zona DDC ' . $code3 . '-' . $end,
                            'notes' => 'Rak referensi kelas DDC ' . $code3 . '-' . $end . ' untuk cabang ' . $branch->name,
                            'sort_order' => (($hundreds * 10) + $tens + 1) * 10,
                            'is_active' => 1,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        }

        $this->command?->info('DdcShelvesSeeder: rak DDC detail 000-990 berhasil di-upsert per cabang.');
    }
}
