<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class NotobukuSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------
            | 1. Institution (idempotent)
            |--------------------------------------------------
            */
            $institution = DB::table('institutions')->updateOrInsert(
                ['code' => 'NOTO-01'],
                [
                    'name' => 'Perpustakaan NOTOBUKU',
                    'address' => 'Indonesia',
                    'phone' => '080000000000',
                    'email' => 'info@notobuku.test',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $institutionId = DB::table('institutions')
                ->where('code', 'NOTO-01')
                ->value('id');

            /*
            |--------------------------------------------------
            | 2. Branches
            |--------------------------------------------------
            */
            $branches = [
                [
                    'code' => 'PUSAT',
                    'name' => 'Perpustakaan Pusat',
                ],
                [
                    'code' => 'SBY',
                    'name' => 'Perpustakan Kota Surabaya',
                ],
            ];

            foreach ($branches as $b) {
                DB::table('branches')->updateOrInsert(
                    [
                        'institution_id' => $institutionId,
                        'code' => $b['code'],
                    ],
                    [
                        'name' => $b['name'],
                        'is_active' => 1,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            $branchPusatId = DB::table('branches')
                ->where('institution_id', $institutionId)
                ->where('code', 'PUSAT')
                ->value('id');

            $branchSurabayaId = DB::table('branches')
                ->where('institution_id', $institutionId)
                ->where('code', 'SBY')
                ->value('id');

            /*
            |--------------------------------------------------
            | 3. Super Admin
            |--------------------------------------------------
            */
            $this->firstOrKeepUser(
                ['email' => 'adhe5381@gmail.com'],
                [
                    'name' => 'Super Admin',
                    'username' => 'aksatria',
                    'password' => Hash::make('71100907'),
                    'role' => 'super_admin',
                    'institution_id' => $institutionId,
                    'branch_id' => $branchPusatId,
                    'status' => 'active',
                ]
            );

            /*
            |--------------------------------------------------
            | 4. Admin Cabang
            |--------------------------------------------------
            User::updateOrCreate(
                ['email' => 'admin.pusat@notobuku.test'],
                [
                    'name' => 'Admin Perpustakaan Pusat',
                    'username' => 'admin_pusat',
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'institution_id' => $institutionId,
                    'branch_id' => $branchPusatId,
                    'status' => 'active',
                ]
            );

            User::updateOrCreate(
                ['email' => 'admin.surabaya@notobuku.test'],
                [
                    'name' => 'Admin Perpustakan Kota Surabaya',
                    'username' => 'admin_surabaya',
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'institution_id' => $institutionId,
                    'branch_id' => $branchSurabayaId,
                    'status' => 'active',
                ]
            );

            /*
            |--------------------------------------------------
            | 5. Member User
            |--------------------------------------------------
            */
            $memberUser = $this->firstOrKeepUser(
                ['email' => 'member@notobuku.test'],
                [
                    'name' => 'Member NOTOBUKU',
                    'username' => 'member_1',
                    'password' => Hash::make('password123'),
                    'role' => 'member',
                    'institution_id' => $institutionId,
                    'status' => 'active',
                ]
            );

            /*
            |--------------------------------------------------
            | 6. Member Data (IDEMPOTENT)
            |--------------------------------------------------
            */
            DB::table('members')->updateOrInsert(
                [
                    'institution_id' => $institutionId,
                    'member_code' => 'MBR-0001',
                ],
                [
                    'user_id' => $memberUser->id,
                    'full_name' => 'Member NOTOBUKU',
                    'phone' => '080000000000',
                    'address' => 'Indonesia',
                    'status' => 'active',
                    'joined_at' => now()->toDateString(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            DB::commit();

            $this->command->info('✅ NotobukuSeeder OK (idempotent)');
            $this->command->info('Super Admin : adhe5381@gmail.com / 71100907');
            $this->command->info('Admin Pusat : admin.pusat@notobuku.test / password');
            $this->command->info('Admin SBY   : admin.surabaya@notobuku.test / password');
            $this->command->info('Member      : member@notobuku.test / password123');

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function firstOrKeepUser(array $unique, array $attributes): User
    {
        $user = User::firstOrCreate($unique, $attributes);
        if ($user->wasRecentlyCreated) {
            return $user;
        }

        $updates = [];
        foreach ($attributes as $key => $value) {
            if ($key === 'password') {
                if (empty($user->password) && !empty($value)) {
                    $updates[$key] = $value;
                }
                continue;
            }
            if ($key === 'username') {
                if (empty($user->username) && !empty($value)) {
                    $updates[$key] = $value;
                }
                continue;
            }
            if (in_array($key, ['name', 'role', 'institution_id', 'branch_id', 'status'], true)) {
                $current = $user->{$key} ?? null;
                if (($current === null || $current === '') && $value !== null && $value !== '') {
                    $updates[$key] = $value;
                }
            }
        }

        if (!empty($updates)) {
            $user->fill($updates);
            $user->save();
        }

        return $user;
    }
}
