<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SearchEvalFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('institutions')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'NOTOBUKU Public',
                'code' => 'NB-PUBLIC',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $rows = [
            ['id' => 900101, 'title' => 'Pemrograman PHP Modern', 'isbn' => '9786230009001'],
            ['id' => 900205, 'title' => 'Dasar Pemrograman Web dengan PHP', 'isbn' => '9786230009002'],
            ['id' => 900309, 'title' => 'Belajar Pemrograman Backend', 'isbn' => '9786230009003'],
            ['id' => 900077, 'title' => 'Filsafat Ilmu Kontemporer', 'isbn' => '9786230009004'],
            ['id' => 900150, 'title' => 'Pengantar Filsafat Ilmu', 'isbn' => '9786230009005'],
            ['id' => 900018, 'title' => 'Manajemen Perpustakaan Sekolah', 'isbn' => '9786230009006'],
            ['id' => 900028, 'title' => 'Strategi Manajemen Perpustakaan', 'isbn' => '9786230009007'],
            ['id' => 900031, 'title' => 'Operasional Layanan Perpustakaan', 'isbn' => '9786230009008'],
        ];

        foreach ($rows as $row) {
            DB::table('biblio')->updateOrInsert(
                ['id' => (int) $row['id']],
                [
                    'institution_id' => 1,
                    'title' => (string) $row['title'],
                    'subtitle' => null,
                    'isbn' => (string) $row['isbn'],
                    'publisher' => 'Fixture',
                    'publish_year' => 2024,
                    'language' => 'id',
                    'ddc' => null,
                    'call_number' => null,
                    'notes' => 'CI search quality fixture',
                    'ai_status' => 'draft',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
