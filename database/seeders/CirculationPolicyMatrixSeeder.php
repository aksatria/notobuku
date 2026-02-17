<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CirculationPolicyMatrixSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('institutions')) {
            return;
        }

        $institutions = DB::table('institutions')->get(['id']);
        foreach ($institutions as $inst) {
            $institutionId = (int) ($inst->id ?? 0);
            if ($institutionId <= 0) {
                continue;
            }

            $this->seedCalendar($institutionId);
            $this->seedPolicyRules($institutionId);
        }
    }

    private function seedCalendar(int $institutionId): void
    {
        if (!Schema::hasTable('circulation_service_calendars')) {
            return;
        }

        DB::table('circulation_service_calendars')->updateOrInsert(
            [
                'institution_id' => $institutionId,
                'branch_id' => null,
                'name' => 'Kalender Layanan Default',
            ],
            [
                'is_active' => true,
                'exclude_weekends' => true,
                'priority' => 10,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function seedPolicyRules(int $institutionId): void
    {
        if (!Schema::hasTable('circulation_loan_policy_rules')) {
            return;
        }

        $rows = [
            [
                'name' => 'Default Member',
                'member_type' => 'member',
                'collection_type' => null,
                'max_items' => 3,
                'default_days' => 7,
                'extend_days' => 7,
                'max_renewals' => 2,
                'fine_rate_per_day' => 1000,
                'grace_days' => 0,
                'can_renew_if_reserved' => false,
                'priority' => 50,
            ],
            [
                'name' => 'Default Student',
                'member_type' => 'student',
                'collection_type' => null,
                'max_items' => 3,
                'default_days' => 7,
                'extend_days' => 7,
                'max_renewals' => 2,
                'fine_rate_per_day' => 1000,
                'grace_days' => 0,
                'can_renew_if_reserved' => false,
                'priority' => 55,
            ],
            [
                'name' => 'Default Staff',
                'member_type' => 'staff',
                'collection_type' => null,
                'max_items' => 5,
                'default_days' => 14,
                'extend_days' => 7,
                'max_renewals' => 3,
                'fine_rate_per_day' => 1000,
                'grace_days' => 0,
                'can_renew_if_reserved' => true,
                'priority' => 60,
            ],
            [
                'name' => 'Fallback All Member Types',
                'member_type' => null,
                'collection_type' => null,
                'max_items' => 3,
                'default_days' => 7,
                'extend_days' => 7,
                'max_renewals' => 2,
                'fine_rate_per_day' => 1000,
                'grace_days' => 0,
                'can_renew_if_reserved' => false,
                'priority' => 10,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('circulation_loan_policy_rules')->updateOrInsert(
                [
                    'institution_id' => $institutionId,
                    'branch_id' => null,
                    'member_type' => $row['member_type'],
                    'collection_type' => $row['collection_type'],
                    'name' => $row['name'],
                ],
                [
                    'max_items' => $row['max_items'],
                    'default_days' => $row['default_days'],
                    'extend_days' => $row['extend_days'],
                    'max_renewals' => $row['max_renewals'],
                    'fine_rate_per_day' => $row['fine_rate_per_day'],
                    'grace_days' => $row['grace_days'],
                    'can_renew_if_reserved' => $row['can_renew_if_reserved'],
                    'is_active' => true,
                    'priority' => $row['priority'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
