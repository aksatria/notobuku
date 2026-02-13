<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\LoanPolicyService;

class MemberLoanService
{
    public function normalizeFilter(?string $filter): string
    {
        $f = strtolower(trim((string)$filter));
        return in_array($f, ['aktif', 'overdue', 'selesai', 'semua'], true) ? $f : 'aktif';
    }

    /**
     * List pinjaman member (dengan agregasi item).
     *
     * Catatan penting:
     * - Sumber kebenaran jatuh tempo untuk member = loan_items.due_date (DATE) bila ada.
     * - Kalau due_date belum ada, sistem tetap jalan:
     *   - nearest_due = NULL
     *   - items_overdue = 0
     *
     * Branch:
     * - Jika $activeBranchId diberikan, filter item (agg+titles) di branch tersebut.
     * - Jika loans punya branch_id, filter loan juga (opsional; aman).
     */
    public function paginateLoans(
        int $institutionId,
        int $memberId,
        ?int $activeBranchId,
        string $filter = 'aktif',
        int $perPage = 15
    ): LengthAwarePaginator {
        $filter = $this->normalizeFilter($filter);

        // Pakai tanggal lokal aplikasi (Carbon sudah ikut timezone app)
        $today = Carbon::today()->toDateString();

        // loans flags
        $loanHasReturnedAt = Schema::hasColumn('loans', 'returned_at');
        $loanHasStatus     = Schema::hasColumn('loans', 'status');
        $loanHasBranchId   = Schema::hasColumn('loans', 'branch_id');

        // loan_items flags
        $liHasDueDate      = Schema::hasColumn('loan_items', 'due_date');
        $liHasReturnedAt   = Schema::hasColumn('loan_items', 'returned_at');
        $liHasRenewCount   = Schema::hasColumn('loan_items', 'renew_count');

        // IMPORTANT:
        // Jangan refer l.due_date di subquery agg (loans tidak di-join di subquery).
        // Jika due_date tidak ada, nearest_due = NULL & items_overdue = 0.
        $dueExpr      = $liHasDueDate ? 'li.due_date' : 'NULL';
        $returnedExpr = $liHasReturnedAt ? 'li.returned_at' : 'NULL';

        // =========================
        // Aggregasi item per transaksi
        // =========================
        $agg = DB::table('loan_items as li')
            ->join('items as i', 'i.id', '=', 'li.item_id')
            ->where('i.institution_id', $institutionId)
            ->when($activeBranchId, fn($q) => $q->where('i.branch_id', $activeBranchId))
            ->select([
                'li.loan_id',
                DB::raw('COUNT(*) as items_total'),
                DB::raw("SUM(CASE WHEN {$returnedExpr} IS NULL THEN 1 ELSE 0 END) as items_active"),
                DB::raw("MIN(CASE WHEN {$returnedExpr} IS NULL THEN {$dueExpr} ELSE NULL END) as nearest_due"),
                DB::raw("SUM(CASE WHEN {$returnedExpr} IS NULL AND {$dueExpr} IS NOT NULL AND {$dueExpr} < '{$today}' THEN 1 ELSE 0 END) as items_overdue"),
                $liHasRenewCount
                    ? DB::raw('MAX(COALESCE(li.renew_count,0)) as max_renew_count')
                    : DB::raw('0 as max_renew_count'),
            ])
            ->groupBy('li.loan_id');

        // =========================
        // Judul ringkas per transaksi
        // =========================
        $titles = DB::table('loan_items as li')
            ->join('items as i', 'i.id', '=', 'li.item_id')
            ->leftJoin('biblio as b', 'b.id', '=', 'i.biblio_id')
            ->where('i.institution_id', $institutionId)
            ->when($activeBranchId, fn($q) => $q->where('i.branch_id', $activeBranchId))
            ->select([
                'li.loan_id',
                DB::raw("GROUP_CONCAT(DISTINCT b.title ORDER BY b.title SEPARATOR ' • ') as titles"),
            ])
            ->groupBy('li.loan_id');

        // =========================
        // Query utama loans
        // =========================
        $q = DB::table('loans as l')
            ->joinSub($agg, 'a', fn($j) => $j->on('a.loan_id', '=', 'l.id'))
            ->leftJoinSub($titles, 't', fn($j) => $j->on('t.loan_id', '=', 'l.id'))
            ->where('l.institution_id', $institutionId)
            ->where('l.member_id', $memberId)
            ->when($loanHasBranchId && $activeBranchId, fn($qq) => $qq->where('l.branch_id', $activeBranchId))
            ->select([
                'l.id',
                'l.created_at',
                $loanHasReturnedAt ? 'l.returned_at' : DB::raw('NULL as returned_at'),
                $loanHasStatus ? 'l.status' : DB::raw('NULL as status'),
                DB::raw('a.items_total'),
                DB::raw('a.items_active'),
                DB::raw('a.items_overdue'),
                DB::raw('a.nearest_due'),
                DB::raw('a.max_renew_count'),
                DB::raw('t.titles'),
            ]);

        // =========================
        // Filter
        // =========================
        if ($filter === 'aktif') {
            if ($loanHasReturnedAt) {
                $q->whereNull('l.returned_at');
            } elseif ($loanHasStatus) {
                $q->whereNotIn('l.status', ['returned', 'closed', 'finished', 'completed']);
            }
            $q->where('a.items_active', '>', 0);
        }

        if ($filter === 'overdue') {
            if ($loanHasReturnedAt) {
                $q->whereNull('l.returned_at');
            }
            $q->where('a.items_active', '>', 0)
              ->where('a.items_overdue', '>', 0);
        }

        if ($filter === 'selesai') {
            if ($loanHasReturnedAt) {
                $q->whereNotNull('l.returned_at');
            } elseif ($loanHasStatus) {
                $q->whereIn('l.status', ['returned', 'closed', 'finished', 'completed']);
            } else {
                $q->where('a.items_active', '=', 0);
            }
        }

        // =========================
        // Sorting
        // =========================
        if (in_array($filter, ['aktif', 'overdue'], true)) {
            // nearest_due bisa NULL kalau schema tidak punya due_date
            $q->orderByRaw('a.nearest_due IS NULL ASC')
              ->orderBy('a.nearest_due', 'asc')
              ->orderByDesc('l.id');
        } else {
            $q->orderByDesc('l.id');
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * Detail transaksi + daftar item.
     * - due_date dari loan_items (DATE) jika ada
     * - returned_at dari loan_items jika ada
     */
    public function getLoanDetail(
        int $institutionId,
        int $memberId,
        int $loanId,
        ?int $activeBranchId
    ): array {
        $loan = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->where('member_id', $memberId)
            ->where('id', $loanId)
            ->first();

        if (!$loan) {
            return ['ok' => false, 'message' => 'Transaksi tidak ditemukan.'];
        }

        $liHasDueDate    = Schema::hasColumn('loan_items', 'due_date');
        $liHasReturnedAt = Schema::hasColumn('loan_items', 'returned_at');

        $items = DB::table('loan_items as li')
            ->join('items as i', 'i.id', '=', 'li.item_id')
            ->leftJoin('biblio as b', 'b.id', '=', 'i.biblio_id')
            ->where('i.institution_id', $institutionId)
            ->where('li.loan_id', $loanId)
            ->when($activeBranchId, fn($q) => $q->where('i.branch_id', $activeBranchId))
            ->select([
                'li.id as loan_item_id',
                'i.id as item_id',
                'i.barcode',
                'i.accession_number',
                'i.inventory_code',
                'i.branch_id',
                'i.status as item_status',
                DB::raw('b.title as title'),
                $liHasDueDate ? 'li.due_date' : DB::raw('NULL as due_date'),
                $liHasReturnedAt ? 'li.returned_at' : DB::raw('NULL as returned_at'),
                Schema::hasColumn('loan_items', 'renew_count')
                    ? 'li.renew_count'
                    : DB::raw('0 as renew_count'),
            ])
            ->orderByDesc('li.id')
            ->get();

        $today = Carbon::today();
        $activeCount = 0;
        $overdueCount = 0;
        $nearestDue = null;
        $maxOverdueDays = 0;
        $sumOverdueDays = 0;

        foreach ($items as $it) {
            $isReturned = !empty($it->returned_at);
            if (!$isReturned) {
                $activeCount++;
                if (!empty($it->due_date)) {
                    $d = Carbon::parse($it->due_date);
                    if ($d->lt($today)) $overdueCount++;
                    if ($nearestDue === null || $d->lt($nearestDue)) $nearestDue = $d;
                    if ($d->lt($today)) {
                        $daysLate = $today->diffInDays($d);
                        if ($daysLate > $maxOverdueDays) $maxOverdueDays = $daysLate;
                        $sumOverdueDays += $daysLate;
                    }
                }
            }
        }

        $fineRate = 1000;
        try {
            if (Schema::hasTable('institutions') && Schema::hasColumn('institutions', 'fine_rate_per_day')) {
                $val = DB::table('institutions')
                    ->where('id', $institutionId)
                    ->value('fine_rate_per_day');
                if (is_numeric($val) && (int)$val > 0) $fineRate = (int)$val;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $fineEstimate = $sumOverdueDays > 0 ? ($sumOverdueDays * $fineRate) : 0;

        return [
            'ok' => true,
            'loan' => $loan,
            'items' => $items,
            'summary' => [
                'items_active' => $activeCount,
                'items_overdue' => $overdueCount,
                'nearest_due' => $nearestDue ? $nearestDue->toDateString() : null,
                'overdue_days_max' => $maxOverdueDays,
                'fine_rate' => $fineRate,
                'fine_estimate' => $fineEstimate,
            ],
        ];
    }

    /**
     * Member extend (perpanjang) berbasis loan_items.due_date.
     *
     * Aturan:
     * - Tidak bisa extend kalau ada item overdue
     * - Extend hanya item aktif (returned_at NULL)
     * - Jika due_date NULL pada item aktif -> set ke (today + extendDays)
     *
     * Kompatibilitas:
     * - Jika loan_items.due_date tidak ada, return error yang jelas (fitur belum tersedia).
     * - Update juga loan_items.due_at (DATETIME) bila ada kolomnya, supaya sinkron dengan modul staff.
     */
    public function extendLoan(
        int $institutionId,
        int $memberId,
        int $loanId,
        ?int $activeBranchId
    ): array {
        $member = DB::table('members')
            ->select(['id', 'institution_id', 'status', 'member_type'])
            ->where('id', $memberId)
            ->first();

        $policySvc = app(LoanPolicyService::class);
        $policy = $policySvc->forRole($policySvc->resolveMemberRole($member));

        $detail = $this->getLoanDetail($institutionId, $memberId, $loanId, $activeBranchId);
        if (!($detail['ok'] ?? false)) return $detail;

        $summary = $detail['summary'] ?? [];
        if ((int)($summary['items_active'] ?? 0) <= 0) {
            return ['ok' => false, 'message' => 'Tidak ada item aktif untuk diperpanjang.'];
        }
        if ((int)($summary['items_overdue'] ?? 0) > 0) {
            return ['ok' => false, 'message' => 'Tidak bisa perpanjang karena ada item yang overdue.'];
        }

        $extendDays = (int)($policy['extend_days'] ?? config('notobuku.loans.extend_days', 7));
        if ($extendDays <= 0) $extendDays = 7;
        $maxRenewals = (int)($policy['max_renewals'] ?? config('notobuku.loans.max_renewals', 2));
        if ($maxRenewals <= 0) $maxRenewals = 2;

        $liHasDueDate = Schema::hasColumn('loan_items', 'due_date');
        if (!$liHasDueDate) {
            return ['ok' => false, 'message' => 'Perpanjang belum tersedia karena sistem belum punya due_date pada item.'];
        }

        $liHasRenewCount = Schema::hasColumn('loan_items', 'renew_count');
        $liHasDueAt = Schema::hasColumn('loan_items', 'due_at'); // optional, tapi kalau ada kita sync

        // (opsional) validasi pemilik loan: pastikan loan masih milik member & institusi
        $loanExists = DB::table('loans')
            ->where('id', $loanId)
            ->where('institution_id', $institutionId)
            ->where('member_id', $memberId)
            ->exists();

        if (!$loanExists) {
            return ['ok' => false, 'message' => 'Transaksi tidak ditemukan.'];
        }

        if ($liHasRenewCount) {
            $overLimit = DB::table('loan_items')
                ->where('loan_id', $loanId)
                ->whereNull('returned_at')
                ->where('renew_count', '>=', $maxRenewals)
                ->count();

            if ($overLimit > 0) {
                return ['ok' => false, 'message' => 'Perpanjangan ditolak: batas maksimal perpanjang sudah tercapai.'];
            }
        }

        DB::beginTransaction();
        try {
            $today = Carbon::today();
            $newDateForNull = $today->copy()->addDays($extendDays)->toDateString();

            // item aktif yang sudah punya due_date -> +extendDays
            DB::table('loan_items')
                ->where('loan_id', $loanId)
                ->whereNull('returned_at')
                ->whereNotNull('due_date')
                ->update([
                    'due_date' => DB::raw("DATE_ADD(due_date, INTERVAL {$extendDays} DAY)"),
                    'updated_at' => now(),
                ]);
            if ($liHasRenewCount) {
                DB::table('loan_items')
                    ->where('loan_id', $loanId)
                    ->whereNull('returned_at')
                    ->whereNotNull('due_date')
                    ->update([
                        'renew_count' => DB::raw('COALESCE(renew_count,0) + 1'),
                        'updated_at' => now(),
                    ]);
            }

            // item aktif tapi due_date NULL -> set from today + extendDays
            DB::table('loan_items')
                ->where('loan_id', $loanId)
                ->whereNull('returned_at')
                ->whereNull('due_date')
                ->update([
                    'due_date' => $newDateForNull,
                    'updated_at' => now(),
                ]);
            if ($liHasRenewCount) {
                DB::table('loan_items')
                    ->where('loan_id', $loanId)
                    ->whereNull('returned_at')
                    ->whereNull('due_date')
                    ->update([
                        'renew_count' => DB::raw('COALESCE(renew_count,0) + 1'),
                        'updated_at' => now(),
                    ]);
            }

            // Sinkronkan due_at (DATETIME) kalau kolomnya ada:
            // - set due_at ke akhir hari dari due_date (agar UI staff tidak konflik).
            //   (Kalau kamu mau jam tertentu, ganti '23:59:59')
            if ($liHasDueAt) {
                DB::table('loan_items')
                    ->where('loan_id', $loanId)
                    ->whereNull('returned_at')
                    ->whereNotNull('due_date')
                    ->update([
                        'due_at' => DB::raw("CONCAT(due_date, ' 23:59:59')"),
                        'updated_at' => now(),
                    ]);
            }

            // (opsional) update loans.due_at kalau kolomnya ada agar konsisten
            if (Schema::hasColumn('loans', 'due_at')) {
                $maxDueDate = DB::table('loan_items')
                    ->where('loan_id', $loanId)
                    ->whereNull('returned_at')
                    ->max('due_date');

                if (!empty($maxDueDate)) {
                    DB::table('loans')
                        ->where('id', $loanId)
                        ->update([
                            'due_at' => $liHasDueAt ? (string)$maxDueDate . ' 23:59:59' : DB::raw('due_at'),
                            'updated_at' => now(),
                        ]);
                }
            }

            DB::commit();
            return ['ok' => true, 'message' => "Berhasil diperpanjang {$extendDays} hari."];
        } catch (\Throwable $e) {
            DB::rollBack();
            return ['ok' => false, 'message' => 'Gagal memperpanjang.'];
        }
    }
}
