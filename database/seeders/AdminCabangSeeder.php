<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminCabangSeeder extends Seeder
{
    public function run(): void
    {
        $institutionId = 1;

        $branches = Branch::where('institution_id', $institutionId)->get();

        foreach ($branches as $branch) {

            // username unik per cabang
            $username = match ($branch->id) {
                1 => 'admin_pusat',
                2 => 'admin_surabaya',
                default => 'admin_cabang_' . $branch->id,
            };

            // email unik per cabang
            $email = match ($branch->id) {
                1 => 'admin.pusat@notobuku.test',
                2 => 'admin.surabaya@notobuku.test',
                default => 'admin.cabang.' . $branch->id . '@notobuku.test',
            };

            User::firstOrCreate(
                [
                    'username' => $username,
                ],
                [
                    'name' => 'Admin ' . $branch->name,
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'institution_id' => $institutionId,
                    'branch_id' => $branch->id,
                    'status' => 'active',
                ]
            );
        }
    }
}
