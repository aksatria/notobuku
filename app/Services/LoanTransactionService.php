<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Services\LoanPolicyService;
use App\Services\BiblioInteractionService;

class LoanTransactionService
{
    public function __construct(
        protected ReservationService $reservationService
    ) {
    }

    /**
     * Payload yang disarankan:
     * - institution_id (int, wajib)
     * - member_id (int, wajib)
     * - barcodes (array<string>, wajib)
     * - due_at (string datetime, optional)
     * - notes (string|null, optional)
     *
     * Branch context:
     * - actor_role (string|null) -> 'super_admin' | 'admin' | 'staff'
     * - staff_branch_id (int|null) -> untuk admin/staff (biasanya user->branch_id)
     * - active_branch_id (int|null) -> untuk super_admin (session active branch)
     *
     * Actor:
     * - actor_user_id (int|null)
     *
     * @return array{ok:bool, loan_id?:int, loan_code?:string}
     */
    public function createLoan(array $payload): array
    {
        $institutionId = (int)($payload['institution_id'] ?? 0);
        $memberId      = (int)($payload['member_id'] ?? 0);
        $barcodes      = $payload['barcodes'] ?? [];
        $dueAt         = (string)($payload['due_at'] ?? '');
        $notes         = $payload['notes'] ?? null;

        $actorUserId   = isset($payload['actor_user_id']) ? (int)$payload['actor_user_id'] : null;
        $actorRole     = trim((string)($payload['actor_role'] ?? ''));
        if ($actorRole === '') $actorRole = 'member';

        $staffBranchId  = (int)($payload['staff_branch_id'] ?? 0);
        $activeBranchId = (int)($payload['active_branch_id'] ?? 0);

        // =========================
        // Basic validation
        // =========================
        if ($institutionId <= 0) {
            throw new \RuntimeException('institution_id tidak valid.');
        }
        if ($memberId <= 0) {
            throw new \RuntimeException('member_id tidak valid.');
        }

        // Hard requirement
        if (!Schema::hasColumn('items', 'branch_id') || !Schema::hasColumn('loans', 'branch_id')) {
            throw new \RuntimeException('Konfigurasi database belum mendukung branch_id untuk transaksi pinjam.');
        }

        $isSuperAdmin   = ($actorRole === 'super_admin');
        $mustLockBranch = in_array($actorRole, ['admin', 'staff'], true);

        // Tentukan cabang transaksi yang dipakai untuk validasi & loans.branch_id
        // - super_admin -> wajib active_branch_id (biar transaksi jelas cabangnya)
        // - admin/staff -> wajib staff_branch_id
        $effectiveBranchId = 0;

        if ($isSuperAdmin) {
            if ($activeBranchId <= 0) {
                throw new \RuntimeException('Super admin harus memilih cabang aktif (active_branch_id).');
            }
            $effectiveBranchId = $activeBranchId;
        } elseif ($mustLockBranch) {
            if ($staffBranchId <= 0) {
                throw new \RuntimeException('Akun Anda belum memiliki cabang. Set branch_id pada user terlebih dahulu.');
            }
            $effectiveBranchId = $staffBranchId;
        }

        $barcodes = collect($barcodes)
            ->map(fn ($x) => trim((string)$x))
            ->filter()
            ->unique()
            ->values();

        if ($barcodes->isEmpty()) {
            throw new \RuntimeException('Minimal 1 barcode harus diisi.');
        }

        if (trim($dueAt) === '') {
            // placeholder, actual set after policy resolved below
        }

        $notes = is_string($notes) ? trim($notes) : null;
        $notes = ($notes !== '' ? $notes : null);

        // Validasi member
        $member = DB::table('members')
            ->select(['id', 'institution_id', 'status', 'member_type'])
            ->where('id', $memberId)
            ->first();

        if (!$member) {
            throw new \RuntimeException('Member tidak ditemukan.');
        }
        if ((int)$member->institution_id !== $institutionId) {
            throw new \RuntimeException('Member bukan dari institusi yang sama.');
        }
        if (!in_array((string)$member->status, ['active'], true)) {
            throw new \RuntimeException('Member tidak aktif. Status: ' . (string)$member->status);
        }

        $activeItemsCount = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->where('loans.member_id', $memberId)
            ->whereNull('loan_items.returned_at')
            ->count();

        if (($activeItemsCount + $barcodes->count()) > $loanMaxItems) {
            throw new \RuntimeException(
                "Batas pinjam aktif tercapai. Maksimal {$loanMaxItems} buku per orang. Saat ini aktif: {$activeItemsCount}."
            );
        }

        $policySvc = app(LoanPolicyService::class);
        $policy = $policySvc->forRole($policySvc->resolveMemberRole($member));
        $loanDefaultDays = (int)($policy['default_days'] ?? 7);
        if ($loanDefaultDays <= 0) $loanDefaultDays = 7;

        $loanMaxItems = (int)($policy['max_items'] ?? 3);
        if ($loanMaxItems <= 0) $loanMaxItems = 3;

        if (trim($dueAt) === '') {
            $dueAt = date('Y-m-d H:i:s', strtotime('+' . $loanDefaultDays . ' days'));
        }

        // =========================
        // TRANSACTION (retry 3)
        // =========================
        return DB::transaction(function () use (
            $institutionId,
            $memberId,
            $barcodes,
            $dueAt,
            $notes,
            $actorUserId,
            $isSuperAdmin,
            $mustLockBranch,
            $effectiveBranchId
        ) {
            /** @var Collection<int, Item> $items */
            $items = Item::query()
                ->where('institution_id', $institutionId)
                ->whereIn('barcode', $barcodes->all())
                ->lockForUpdate()
                ->get();

            if ($items->count() !== $barcodes->count()) {
                $found = $items->pluck('barcode')->all();
                $missing = $barcodes->reject(fn ($b) => in_array($b, $found, true))->values()->all();
                throw new \RuntimeException('Barcode tidak ditemukan: ' . implode(', ', $missing));
            }

            // =========================
            // CABANG VALIDATION
            // =========================
            $branchIds = $items
                ->map(fn ($it) => (int)($it->branch_id ?? 0))
                ->unique()
                ->values();

            // item branch wajib ada
            if ($branchIds->contains(fn ($x) => $x <= 0)) {
                $list = $items->map(function ($it) {
                    $bid = (int)($it->branch_id ?? 0);
                    return $it->barcode . ' (cabang item ' . ($bid > 0 ? $bid : 'kosong') . ')';
                })->values()->all();

                throw new \RuntimeException('Item belum memiliki cabang (branch kosong). Perbaiki data: ' . implode(', ', $list));
            }

            // 1 transaksi hanya boleh 1 cabang (baik admin/staff maupun super_admin)
            if ($branchIds->count() !== 1) {
                throw new \RuntimeException('Transaksi pinjam hanya boleh 1 cabang. Pisahkan transaksi per cabang.');
            }

            $itemsBranchId = (int)$branchIds->first();

            // admin/staff: item cabang harus sama dengan cabang akun
            if ($mustLockBranch) {
                if ($itemsBranchId !== (int)$effectiveBranchId) {
                    throw new \RuntimeException(
                        'Validasi cabang gagal: cabang item ' . $itemsBranchId . ' ≠ cabang akun ' . (int)$effectiveBranchId
                    );
                }
            }

            // super_admin: item cabang harus sama dengan active branch yang dipilih
            if ($isSuperAdmin) {
                if ($itemsBranchId !== (int)$effectiveBranchId) {
                    throw new \RuntimeException(
                        'Cabang item (' . $itemsBranchId . ') tidak sesuai cabang aktif (' . (int)$effectiveBranchId . ').'
                    );
                }
            }

            // =========================
            // Validasi status item
            // =========================
            $allowReservedFlow = Schema::hasTable('reservations') && Schema::hasColumn('reservations', 'item_id');

            $notAllowed = collect();
            foreach ($items as $it) {
                $st = (string)($it->status ?? '');

                if ($st === 'available') {
                    continue;
                }

                if ($st === 'reserved' && $allowReservedFlow) {
                    $ok = DB::table('reservations')
                        ->where('institution_id', $institutionId)
                        ->where('status', 'ready')
                        ->where('item_id', (int)$it->id)
                        ->where('member_id', $memberId)
                        ->whereNull('fulfilled_at')
                        ->where(function ($w) {
                            $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        })
                        ->exists();

                    if ($ok) {
                        continue;
                    }
                }

                $notAllowed->push($it);
            }

            if ($notAllowed->isNotEmpty()) {
                $list = $notAllowed->map(fn ($it) => $it->barcode . ' (' . (string)$it->status . ')')->values()->all();
                throw new \RuntimeException('Ada item tidak tersedia / tidak boleh dipinjam: ' . implode(', ', $list));
            }

            $reservedItemIdsForMember = [];
            if ($allowReservedFlow) {
                $reservedItemIdsForMember = $items
                    ->filter(fn ($it) => (string)($it->status ?? '') === 'reserved')
                    ->pluck('id')
                    ->map(fn ($x) => (int)$x)
                    ->values()
                    ->all();
            }

            // =========================
            // Insert Loan + Loan Items
            // =========================
            $loanCode = $this->generateLoanCode();

            $loanId = DB::table('loans')->insertGetId([
                'institution_id' => $institutionId,
                'branch_id'      => (int)$effectiveBranchId, // INI YANG PENTING
                'member_id'      => $memberId,
                'loan_code'      => $loanCode,
                'status'         => 'open',
                'loaned_at'      => now(),
                'due_at'         => $dueAt,
                'closed_at'      => null,
                'created_by'     => $actorUserId,
                'notes'          => $notes,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            foreach ($items as $it) {
                $dueDate = null;
                if (Schema::hasColumn('loan_items', 'due_date')) {
                    try {
                        $dueDate = date('Y-m-d', strtotime((string)$dueAt));
                    } catch (\Throwable $e) {
                        $dueDate = null;
                    }
                }

                $insert = [
                    'loan_id'     => $loanId,
                    'item_id'     => $it->id,
                    'status'      => 'borrowed',
                    'borrowed_at' => now(),
                    'due_at'      => $dueAt,
                    'due_date'    => Schema::hasColumn('loan_items', 'due_date') ? $dueDate : null,
                    'returned_at' => null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
                if (Schema::hasColumn('loan_items', 'renew_count')) {
                    $insert['renew_count'] = 0;
                }
                DB::table('loan_items')->insert($insert);

                $it->status = 'borrowed';
                $it->save();
            }

            $biblioIds = $items->pluck('biblio_id')->filter()->unique()->values()->all();
            if (!empty($biblioIds)) {
                app(BiblioInteractionService::class)->recordBorrow(
                    $biblioIds,
                    $institutionId,
                    $actorUserId,
                    $effectiveBranchId > 0 ? $effectiveBranchId : null
                );
            }

            if ($allowReservedFlow && !empty($reservedItemIdsForMember)) {
                DB::table('reservations')
                    ->where('institution_id', $institutionId)
                    ->where('member_id', $memberId)
                    ->whereIn('item_id', $reservedItemIdsForMember)
                    ->where('status', 'ready')
                    ->whereNull('fulfilled_at')
                    ->update([
                        'status'       => 'fulfilled',
                        'fulfilled_at' => now(),
                        'updated_at'   => now(),
                    ]);
            }

            return [
                'ok'        => true,
                'loan_id'   => (int)$loanId,
                'loan_code' => (string)$loanCode,
            ];
        }, 3);
    }

    private function generateLoanCode(): string
    {
        do {
            $code = 'L-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        } while (DB::table('loans')->where('loan_code', $code)->exists());

        return $code;
    }
}
