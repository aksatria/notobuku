<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanReturnFineSeeder extends Seeder
{
    private const COLLECTION_MARKER = 'SEED-KOLEKSI-2026';
    private const LOAN_MARKER = 'SEED-LOAN-2026';
    private const LEGACY_ITEM_NOTE = 'Seeded item for loan/return/fine demo.';
    private const LEGACY_LOAN_NOTE = 'Seeded loan for dashboard demo.';
    private const LEGACY_BIBLIO_NOTE = 'Seeded biblio for loan/return demo.';
    private const LOAN_PREFIX = 'DEMO-LN-';

    public function run(): void
    {
        $now = Carbon::now();

        $institutionId = DB::table('institutions')->orderBy('id')->value('id');
        if (!$institutionId) {
            $this->command?->warn('LoanReturnFineSeeder: institutions kosong, seeder dibatalkan.');
            return;
        }

        DB::beginTransaction();
        try {
            $this->cleanupLegacySeed($institutionId);

            $branchId = DB::table('branches')
                ->where('institution_id', $institutionId)
                ->orderBy('id')
                ->value('id');

            $createdBy = DB::table('users')
                ->where('institution_id', $institutionId)
                ->whereIn('role', ['super_admin', 'admin', 'staff'])
                ->orderBy('id')
                ->value('id');

            $memberIds = DB::table('members')
                ->where('institution_id', $institutionId)
                ->orderBy('id')
                ->limit(6)
                ->pluck('id')
                ->all();

            if (count($memberIds) < 3) {
                $this->seedMembers($institutionId, $now);
                $memberIds = DB::table('members')
                    ->where('institution_id', $institutionId)
                    ->orderBy('id')
                    ->limit(6)
                    ->pluck('id')
                    ->all();
            }

            $this->call(DemoCollectionsSeeder::class);

            $seedItemIds = DB::table('items')
                ->where('institution_id', $institutionId)
                ->where('notes', self::COLLECTION_MARKER)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $seedItemIds = array_values(array_filter($seedItemIds, fn ($id) => (int) $id > 0));

            if (count($seedItemIds) < 12) {
                $this->command?->warn('LoanReturnFineSeeder: item demo kurang, seeder dibatalkan.');
                DB::rollBack();
                return;
            }

            $itemResetPayload = ['status' => 'available', 'updated_at' => $now];
            if (Schema::hasColumn('items', 'circulation_status')) {
                $itemResetPayload['circulation_status'] = 'circulating';
            }
            DB::table('items')->whereIn('id', $seedItemIds)->update($itemResetPayload);

            $memberA = $memberIds[0] ?? null;
            $memberB = $memberIds[1] ?? $memberA;
            $memberC = $memberIds[2] ?? $memberA;

            if (!$memberA) {
                $this->command?->warn('LoanReturnFineSeeder: members kosong, seeder dibatalkan.');
                DB::rollBack();
                return;
            }

            $loanPlans = $this->buildLoanPlans($now, $seedItemIds, $memberA, $memberB, $memberC, $branchId);

            $loanItemSnapshots = [];
            $itemStatusMap = [];

            foreach ($loanPlans as $plan) {
                $loanId = $this->upsertLoan(
                    institutionId: $institutionId,
                    branchId: $branchId,
                    memberId: $plan['member_id'],
                    createdBy: $createdBy,
                    plan: $plan,
                    now: $now
                );

                foreach ($plan['items'] as $li) {
                    $status = empty($li['returned_at']) ? 'borrowed' : 'returned';

                    $loanItemId = $this->upsertLoanItem($loanId, $li, $status, $now);

                    $loanItemSnapshots[] = [
                        'loan_item_id' => $loanItemId,
                        'member_id' => $plan['member_id'],
                        'due_at' => $li['due_at'],
                        'returned_at' => $li['returned_at'],
                    ];

                    $itemId = (int) $li['item_id'];
                    $itemStatusMap[$itemId] = $status === 'borrowed' ? 'borrowed' : ($itemStatusMap[$itemId] ?? 'available');
                }
            }

            foreach ($itemStatusMap as $itemId => $status) {
                DB::table('items')
                    ->where('id', $itemId)
                    ->update(['status' => $status, 'updated_at' => $now]);
            }

            $this->seedFines($institutionId, $loanItemSnapshots, $now);

            DB::commit();
            $this->command?->info('LoanReturnFineSeeder OK');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function seedMembers(int $institutionId, Carbon $now): void
    {
        $memberRows = [
            ['code' => 'MBR-DEMO-1001', 'name' => 'Anggota Demo 1'],
            ['code' => 'MBR-DEMO-1002', 'name' => 'Anggota Demo 2'],
            ['code' => 'MBR-DEMO-1003', 'name' => 'Anggota Demo 3'],
        ];

        $hasMemberType = Schema::hasColumn('members', 'member_type');

        foreach ($memberRows as $row) {
            $data = [
                'institution_id' => $institutionId,
                'user_id' => null,
                'member_code' => $row['code'],
                'full_name' => $row['name'],
                'phone' => null,
                'address' => self::LOAN_MARKER,
                'status' => 'active',
                'joined_at' => $now->toDateString(),
                'updated_at' => $now,
                'created_at' => $now,
            ];

            if ($hasMemberType) {
                $data['member_type'] = 'member';
            }

            DB::table('members')->updateOrInsert(
                ['member_code' => $row['code']],
                $data
            );
        }
    }

    private function buildLoanPlans(
        Carbon $now,
        array $items,
        int $memberA,
        int $memberB,
        int $memberC,
        ?int $branchId
    ): array {
        $todayMorning = $now->copy()->setTime(9, 10, 0);
        $todayNoon = $now->copy()->setTime(12, 45, 0);
        $loan1Borrow = $todayMorning->copy();
        $loan1Due = $now->copy()->addDays(7)->setTime(23, 59, 0);

        $loan2Borrow = $now->copy()->setTime(10, 30, 0);
        $loan2Due = $now->copy()->addDays(5)->setTime(23, 59, 0);

        $loan3Borrow = $now->copy()->subDays(12)->setTime(10, 0, 0);
        $loan3Due = $now->copy()->subDays(5)->setTime(23, 59, 0);

        $loan4Borrow = $now->copy()->subDays(18)->setTime(9, 0, 0);
        $loan4Due = $now->copy()->subDays(10)->setTime(23, 59, 0);
        $loan4Return = $now->copy()->subDays(2)->setTime(14, 0, 0);

        $loan5Borrow = $now->copy()->subDays(35)->setTime(10, 0, 0);
        $loan5Due = $now->copy()->subDays(25)->setTime(23, 59, 0);
        $loan5Return = $now->copy()->subDays(5)->setTime(11, 30, 0);

        $loan6Borrow = $now->copy()->subDays(6)->setTime(15, 0, 0);
        $loan6Due = $now->copy()->subDays(2)->setTime(23, 59, 0);
        $loan6Return = $todayNoon->copy();

        return [
            [
                'code' => self::LOAN_PREFIX . '0001',
                'member_id' => $memberA,
                'branch_id' => $branchId,
                'items' => [
                    [
                        'item_id' => $items[0] ?? 0,
                        'borrowed_at' => $loan1Borrow,
                        'due_at' => $loan1Due,
                        'returned_at' => $todayNoon->copy(),
                    ],
                    [
                        'item_id' => $items[1] ?? 0,
                        'borrowed_at' => $loan1Borrow,
                        'due_at' => $loan1Due,
                        'returned_at' => null,
                    ],
                ],
            ],
            [
                'code' => self::LOAN_PREFIX . '0002',
                'member_id' => $memberB,
                'branch_id' => $branchId,
                'items' => [
                    [
                        'item_id' => $items[2] ?? 0,
                        'borrowed_at' => $loan2Borrow,
                        'due_at' => $loan2Due,
                        'returned_at' => null,
                    ],
                ],
            ],
            [
                'code' => self::LOAN_PREFIX . '0003',
                'member_id' => $memberC,
                'branch_id' => $branchId,
                'items' => [
                    [
                        'item_id' => $items[3] ?? 0,
                        'borrowed_at' => $loan3Borrow,
                        'due_at' => $loan3Due,
                        'returned_at' => null,
                    ],
                    [
                        'item_id' => $items[4] ?? 0,
                        'borrowed_at' => $loan3Borrow,
                        'due_at' => $loan3Due,
                        'returned_at' => null,
                    ],
                ],
            ],
            [
                'code' => self::LOAN_PREFIX . '0004',
                'member_id' => $memberA,
                'branch_id' => $branchId,
                'items' => [
                    [
                        'item_id' => $items[5] ?? 0,
                        'borrowed_at' => $loan4Borrow,
                        'due_at' => $loan4Due,
                        'returned_at' => $loan4Return,
                    ],
                ],
            ],
            [
                'code' => self::LOAN_PREFIX . '0005',
                'member_id' => $memberB,
                'branch_id' => $branchId,
                'items' => [
                    [
                        'item_id' => $items[6] ?? 0,
                        'borrowed_at' => $loan5Borrow,
                        'due_at' => $loan5Due,
                        'returned_at' => $loan5Return,
                    ],
                ],
            ],
            [
                'code' => self::LOAN_PREFIX . '0006',
                'member_id' => $memberC,
                'branch_id' => $branchId,
                'items' => [
                    [
                        'item_id' => $items[7] ?? 0,
                        'borrowed_at' => $loan6Borrow,
                        'due_at' => $loan6Due,
                        'returned_at' => $loan6Return,
                    ],
                ],
            ],
            [
                'code' => self::LOAN_PREFIX . '0007',
                'member_id' => $memberA,
                'branch_id' => $branchId,
                'items' => [
                    [
                        'item_id' => $items[8] ?? 0,
                        'borrowed_at' => $todayMorning->copy(),
                        'due_at' => $now->copy()->addDays(10)->setTime(23, 59, 0),
                        'returned_at' => null,
                    ],
                ],
            ],
            [
                'code' => self::LOAN_PREFIX . '0008',
                'member_id' => $memberB,
                'branch_id' => $branchId,
                'items' => [
                    [
                        'item_id' => $items[9] ?? 0,
                        'borrowed_at' => $now->copy()->subDays(9)->setTime(13, 0, 0),
                        'due_at' => $now->copy()->subDays(1)->setTime(23, 59, 0),
                        'returned_at' => null,
                    ],
                ],
            ],
        ];
    }

    private function upsertLoan(
        int $institutionId,
        ?int $branchId,
        int $memberId,
        ?int $createdBy,
        array $plan,
        Carbon $now
    ): int {
        $items = $plan['items'] ?? [];
        $loanedAt = !empty($items[0]['borrowed_at']) ? $items[0]['borrowed_at'] : $now;
        $dueAt = null;

        $hasOpen = false;
        $hasOverdue = false;
        $lastReturned = null;

        foreach ($items as $it) {
            if (!empty($it['due_at'])) {
                if ($dueAt instanceof Carbon) {
                    if ($it['due_at']->gt($dueAt)) {
                        $dueAt = $it['due_at'];
                    }
                } else {
                    $dueAt = $it['due_at'];
                }
            }

            if (empty($it['returned_at'])) {
                $hasOpen = true;
                if (!empty($it['due_at']) && $it['due_at']->lt($now)) {
                    $hasOverdue = true;
                }
            } else {
                if ($lastReturned instanceof Carbon) {
                    if ($it['returned_at']->gt($lastReturned)) {
                        $lastReturned = $it['returned_at'];
                    }
                } else {
                    $lastReturned = $it['returned_at'];
                }
            }
        }

        $status = 'closed';
        if ($hasOpen && $hasOverdue) $status = 'overdue';
        elseif ($hasOpen) $status = 'open';

        DB::table('loans')->updateOrInsert(
            ['loan_code' => $plan['code']],
            [
                'institution_id' => $institutionId,
                'branch_id' => $branchId,
                'member_id' => $memberId,
                'loan_code' => $plan['code'],
                'status' => $status,
                'loaned_at' => $loanedAt,
                'due_at' => $dueAt,
                'closed_at' => $status === 'closed' ? $lastReturned : null,
                'created_by' => $createdBy,
                'notes' => self::LOAN_MARKER,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        return (int) DB::table('loans')
            ->where('loan_code', $plan['code'])
            ->value('id');
    }

    private function upsertLoanItem(int $loanId, array $li, string $status, Carbon $now): int
    {
        $dueDate = null;
        if (!empty($li['due_at'])) {
            $dueDate = $li['due_at']->copy()->toDateString();
        }

        $data = [
            'status' => $status,
            'borrowed_at' => $li['borrowed_at'],
            'due_at' => $li['due_at'],
            'returned_at' => $li['returned_at'],
            'updated_at' => $now,
            'created_at' => $now,
        ];

        if (Schema::hasColumn('loan_items', 'due_date')) {
            $data['due_date'] = $dueDate;
        }
        if (Schema::hasColumn('loan_items', 'renew_count')) {
            $data['renew_count'] = 0;
        }

        DB::table('loan_items')->updateOrInsert(
            [
                'loan_id' => $loanId,
                'item_id' => $li['item_id'],
            ],
            $data
        );

        return (int) DB::table('loan_items')
            ->where('loan_id', $loanId)
            ->where('item_id', $li['item_id'])
            ->value('id');
    }

    private function seedFines(int $institutionId, array $loanItemSnapshots, Carbon $now): void
    {
        if (!Schema::hasTable('fines')) {
            return;
        }

        $hasStatus = Schema::hasColumn('fines', 'status');
        $hasDaysLate = Schema::hasColumn('fines', 'days_late');
        $hasRate = Schema::hasColumn('fines', 'rate');
        $hasAmount = Schema::hasColumn('fines', 'amount');
        $hasPaidAmount = Schema::hasColumn('fines', 'paid_amount');
        $hasPaidAt = Schema::hasColumn('fines', 'paid_at');
        $hasNotes = Schema::hasColumn('fines', 'notes');
        $hasReason = Schema::hasColumn('fines', 'reason');
        $hasCurrency = Schema::hasColumn('fines', 'currency');
        $hasAssessedAt = Schema::hasColumn('fines', 'assessed_at');

        $rate = 1000;
        if (Schema::hasTable('institutions') && Schema::hasColumn('institutions', 'fine_rate_per_day')) {
            $val = DB::table('institutions')->where('id', $institutionId)->value('fine_rate_per_day');
            if (is_numeric($val) && (int) $val > 0) {
                $rate = (int) $val;
            }
        }

        foreach ($loanItemSnapshots as $snap) {
            $daysLate = $this->calcDaysLate($snap['due_at'] ?? null, $snap['returned_at'] ?? null);
            if ($daysLate <= 0) continue;

            $amount = $daysLate * $rate;
            $status = empty($snap['returned_at']) ? 'unpaid' : 'paid';

            $data = [
                'institution_id' => $institutionId,
                'member_id' => $snap['member_id'],
                'loan_item_id' => $snap['loan_item_id'],
                'updated_at' => $now,
                'created_at' => $now,
            ];

            if ($hasStatus) $data['status'] = $status;
            if ($hasDaysLate) $data['days_late'] = $daysLate;
            if ($hasRate) $data['rate'] = $rate;
            if ($hasAmount) $data['amount'] = $amount;
            if ($hasPaidAmount) $data['paid_amount'] = $status === 'paid' ? $amount : 0;
            if ($hasPaidAt) $data['paid_at'] = $status === 'paid' ? ($snap['returned_at'] ?? $now) : null;
            if ($hasNotes) $data['notes'] = self::LOAN_MARKER;
            if ($hasReason) $data['reason'] = 'Terlambat pengembalian';
            if ($hasCurrency) $data['currency'] = 'IDR';
            if ($hasAssessedAt) $data['assessed_at'] = $now;

            DB::table('fines')->updateOrInsert(
                [
                    'institution_id' => $institutionId,
                    'loan_item_id' => $snap['loan_item_id'],
                ],
                $data
            );
        }
    }

    private function calcDaysLate($dueAt, $returnedAt): int
    {
        if (empty($dueAt)) return 0;

        $due = strtotime((string) $dueAt);
        if ($due === false) return 0;

        $end = $returnedAt ? strtotime((string) $returnedAt) : time();
        if ($end === false) $end = time();

        $diff = $end - $due;
        if ($diff <= 0) return 0;

        return (int) floor($diff / 86400);
    }

    private function cleanupLegacySeed(int $institutionId): void
    {
        if (Schema::hasTable('fines') && Schema::hasTable('loan_items') && Schema::hasTable('loans')) {
            $legacyLoanIds = DB::table('loans')
                ->where('institution_id', $institutionId)
                ->where(function ($q) {
                    $q->where('loan_code', 'like', 'SEED-LN-%')
                        ->orWhere('loan_code', 'like', self::LOAN_PREFIX . '%')
                        ->orWhere('notes', self::LEGACY_LOAN_NOTE)
                        ->orWhere('notes', self::LOAN_MARKER);
                })
                ->pluck('id')
                ->all();

            if (!empty($legacyLoanIds)) {
                $loanItemIds = DB::table('loan_items')
                    ->whereIn('loan_id', $legacyLoanIds)
                    ->pluck('id')
                    ->all();

                if (!empty($loanItemIds) && Schema::hasTable('fines')) {
                    DB::table('fines')->whereIn('loan_item_id', $loanItemIds)->delete();
                }

                if (!empty($loanItemIds)) {
                    DB::table('loan_items')->whereIn('id', $loanItemIds)->delete();
                }

                DB::table('loans')->whereIn('id', $legacyLoanIds)->delete();
            }
        }

        if (Schema::hasTable('items')) {
            DB::table('items')
                ->where('institution_id', $institutionId)
                ->where(function ($q) {
                    $q->where('barcode', 'like', 'SEED-LN-%')
                        ->orWhere('notes', self::LEGACY_ITEM_NOTE);
                })
                ->delete();
        }

        if (Schema::hasTable('biblio')) {
            $legacyBiblioIds = DB::table('biblio')
                ->where('institution_id', $institutionId)
                ->where(function ($q) {
                    $q->where('title', 'like', 'Seeded Title%')
                        ->orWhere('notes', self::LEGACY_BIBLIO_NOTE);
                })
                ->pluck('id')
                ->all();

            if (!empty($legacyBiblioIds) && Schema::hasTable('biblio_author')) {
                DB::table('biblio_author')->whereIn('biblio_id', $legacyBiblioIds)->delete();
            }
            if (!empty($legacyBiblioIds)) {
                DB::table('biblio')->whereIn('id', $legacyBiblioIds)->delete();
            }
        }

        if (Schema::hasTable('members')) {
            DB::table('members')
                ->where('institution_id', $institutionId)
                ->where('member_code', 'like', 'MBR-SEED-%')
                ->delete();
        }
    }
}
