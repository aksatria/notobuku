<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VisitorCounterSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('visitor_counters')) {
            return;
        }

        $institutionId = 1;
        if (Schema::hasTable('institutions')) {
            $institutionId = (int) (DB::table('institutions')->value('id') ?? 1);
            if ($institutionId <= 0) {
                $institutionId = 1;
            }
        }

        $branchIds = [];
        if (Schema::hasTable('branches')) {
            $branchIds = DB::table('branches')
                ->where('institution_id', $institutionId)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $members = collect();
        if (Schema::hasTable('members')) {
            $members = DB::table('members')
                ->where('institution_id', $institutionId)
                ->select('id', 'member_code', 'full_name')
                ->orderBy('id')
                ->limit(3)
                ->get();
        }

        $createdBy = null;
        if (Schema::hasTable('users')) {
            $createdBy = DB::table('users')
                ->where('institution_id', $institutionId)
                ->orderBy('id')
                ->value('id');
        }

        $today = Carbon::today();
        $rows = [];

        foreach ($members as $idx => $member) {
            $checkin = $today->copy()->setTime(8 + $idx, 10 + ($idx * 15), 0);
            $checkout = $idx === 0 ? $checkin->copy()->addHours(2) : null;

            $rows[] = [
                'institution_id' => $institutionId,
                'branch_id' => $branchIds[$idx % max(1, count($branchIds))] ?? null,
                'member_id' => (int) $member->id,
                'visitor_type' => 'member',
                'visitor_name' => (string) ($member->full_name ?? 'Member'),
                'member_code_snapshot' => (string) ($member->member_code ?? ''),
                'purpose' => 'Membaca di tempat',
                'checkin_at' => $checkin,
                'checkout_at' => $checkout,
                'notes' => $idx === 0 ? 'Seeder demo: member selesai kunjungan' : null,
                'created_by' => $createdBy ? (int) $createdBy : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $rows[] = [
            'institution_id' => $institutionId,
            'branch_id' => $branchIds[0] ?? null,
            'member_id' => null,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Rina Prasetyo',
            'member_code_snapshot' => null,
            'purpose' => 'Referensi tugas',
            'checkin_at' => $today->copy()->setTime(10, 5, 0),
            'checkout_at' => $today->copy()->setTime(12, 0, 0),
            'notes' => 'Seeder demo non-member',
            'created_by' => $createdBy ? (int) $createdBy : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $rows[] = [
            'institution_id' => $institutionId,
            'branch_id' => $branchIds[1] ?? ($branchIds[0] ?? null),
            'member_id' => null,
            'visitor_type' => 'non_member',
            'visitor_name' => 'Andi Nugroho',
            'member_code_snapshot' => null,
            'purpose' => 'Cari koleksi sejarah',
            'checkin_at' => $today->copy()->setTime(13, 20, 0),
            'checkout_at' => null,
            'notes' => 'Seeder demo: masih di tempat',
            'created_by' => $createdBy ? (int) $createdBy : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('visitor_counters')
            ->where('institution_id', $institutionId)
            ->whereDate('checkin_at', $today->toDateString())
            ->delete();

        DB::table('visitor_counters')->insert($rows);
    }
}

