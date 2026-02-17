<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReservationPolicyMatrixSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('reservation_policy_rules')) {
            return;
        }

        $institutionIds = [];
        if (Schema::hasTable('institutions')) {
            $institutionIds = DB::table('institutions')->pluck('id')->map(fn ($x) => (int) $x)->all();
        }
        if (empty($institutionIds)) {
            $institutionIds = [1];
        }

        foreach ($institutionIds as $institutionId) {
            $rows = [
                [
                    'label' => 'Default Umum',
                    'branch_id' => null,
                    'member_type' => null,
                    'collection_type' => null,
                    'max_active_reservations' => 5,
                    'max_queue_per_biblio' => 30,
                    'hold_hours' => 48,
                    'priority_weight' => 0,
                ],
                [
                    'label' => 'Prioritas Dosen',
                    'branch_id' => null,
                    'member_type' => 'dosen',
                    'collection_type' => null,
                    'max_active_reservations' => 8,
                    'max_queue_per_biblio' => 40,
                    'hold_hours' => 72,
                    'priority_weight' => 30,
                ],
                [
                    'label' => 'Prioritas Disabilitas',
                    'branch_id' => null,
                    'member_type' => 'disabilitas',
                    'collection_type' => null,
                    'max_active_reservations' => 8,
                    'max_queue_per_biblio' => 40,
                    'hold_hours' => 72,
                    'priority_weight' => 60,
                ],
                [
                    'label' => 'Koleksi Serial Ketat',
                    'branch_id' => null,
                    'member_type' => null,
                    'collection_type' => 'serial',
                    'max_active_reservations' => 3,
                    'max_queue_per_biblio' => 15,
                    'hold_hours' => 24,
                    'priority_weight' => 0,
                ],
            ];

            foreach ($rows as $row) {
                DB::table('reservation_policy_rules')->updateOrInsert(
                    [
                        'institution_id' => $institutionId,
                        'label' => $row['label'],
                    ],
                    array_merge($row, [
                        'institution_id' => $institutionId,
                        'is_enabled' => true,
                        'notes' => 'Seeder policy matrix reservasi',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ])
                );
            }
        }
    }
}
