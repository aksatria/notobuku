<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $today = $now->toDateString();

        // Pakai upsert berdasarkan member_code (UNIQUE)
        DB::table('members')->upsert(
            [
                [
                    'institution_id' => 1,
                    'user_id' => null,
                    'member_code' => 'MBR-0001',
                    'full_name' => 'Member Demo 1',
                    'phone' => '081234567890',
                    'address' => 'Alamat demo 1',
                    'status' => 'active',
                    'joined_at' => $today,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'institution_id' => 1,
                    'user_id' => null,
                    'member_code' => 'MBR-0002',
                    'full_name' => 'Member Demo 2',
                    'phone' => '081298765432',
                    'address' => 'Alamat demo 2',
                    'status' => 'active',
                    'joined_at' => $today,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['member_code'], // key unik
            ['full_name','phone','address','status','joined_at','institution_id','user_id','updated_at'] // kolom yang diupdate
        );
    }
}
