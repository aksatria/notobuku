<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MemberDashboardService
{
    /** @var array<string,bool> */
    private array $hasCol = [];

    /**
     * Resolve memberId dari user:
     * - prioritas: user->member_id
     * - fallback: cocokkan ke table members (email / member_code / full_name) bila ada
     */
    public function resolveMemberId($user): ?int
    {
        $direct = (int) ($user->member_id ?? 0);
        if ($direct > 0) return $direct;

        $email = (string) ($user->email ?? '');
        if ($email !== '' && $this->col('members', 'email')) {
            $id = (int) DB::table('members')->where('email', $email)->value('id');
            if ($id > 0) return $id;
        }

        $username = (string) ($user->username ?? '');
        if ($username !== '' && $this->col('members', 'member_code')) {
            $id = (int) DB::table('members')->where('member_code', $username)->value('id');
            if ($id > 0) return $id;
        }

        $name = (string) ($user->name ?? '');
        if ($name !== '' && $this->col('members', 'full_name')) {
            $id = (int) DB::table('members')->where('full_name', $name)->value('id');
            if ($id > 0) return $id;
        }

        return null;
    }

    public function buildDashboard(int $institutionId, int $memberId, ?int $activeBranchId = null): array
    {
        $today = Carbon::today();
        $now = Carbon::now();

        $active  = $this->activeLoansSummary($institutionId, $memberId, $activeBranchId);
        $overdue = $this->overdueSummary($institutionId, $memberId, $activeBranchId, $today);
        $renew   = $this->renewSummary($institutionId, $memberId, $activeBranchId);
        $dueSoon = $this->dueSoon($institutionId, $memberId, $activeBranchId, $today);
        $trend   = $this->history14Days($institutionId, $memberId, $activeBranchId, $now);
        $stats   = $this->monthlyStats($institutionId, $memberId, $activeBranchId, $now);
        $fav     = $this->favoriteTitles($institutionId, $memberId, $activeBranchId);
        $fines   = $this->finesSummary($institutionId, $memberId);
        $notif   = $this->unreadNotifications($memberId);
        $recentLoans = $this->recentLoans($institutionId, $memberId, $activeBranchId);
        $recentNotifs = $this->recentNotifications($memberId);
        $maxRenewals = (int) config('notobuku.loans.max_renewals', 2);
        $maxRenewCount = (int) ($renew['max_renew_count'] ?? 0);
        $renewRemaining = max(0, $maxRenewals - $maxRenewCount);

        return [
            'kpi' => [
                'active_loans'     => (int) ($active['active_loans'] ?? 0),
                'active_items'     => (int) ($active['active_items'] ?? 0),
                'overdue_items'    => (int) ($overdue['overdue_items'] ?? 0),
                'max_overdue_days' => (int) ($overdue['max_overdue_days'] ?? 0),
                'max_renew_count'  => $maxRenewCount,
                'renew_remaining'  => $renewRemaining,
                'max_renewals'     => $maxRenewals,
            ],
            'due_soon' => $dueSoon,
            'trend_14d' => $trend,
            'stats' => $stats,
            'fines' => $fines,
            'notif_unread' => $notif,
            'recent_loans' => $recentLoans,
            'recent_notifications' => $recentNotifs,
            'favorite_titles' => $fav,
        ];
    }

    private function finesSummary(int $institutionId, int $memberId): array
    {
        if (!Schema::hasTable('fines') || !$this->col('fines', 'member_id')) {
            return ['outstanding' => 0, 'has_fines' => false];
        }

        $q = DB::table('fines')
            ->where('member_id', $memberId);

        if ($this->col('fines', 'institution_id')) {
            $q->where('institution_id', $institutionId);
        }

        if ($this->col('fines', 'status')) {
            $q->where('status', '!=', 'void');
        }

        $hasAmount = $this->col('fines', 'amount');
        $hasPaidAmount = $this->col('fines', 'paid_amount');

        if ($hasAmount && $hasPaidAmount) {
            $outstanding = (int) $q
                ->selectRaw('SUM(GREATEST(COALESCE(amount,0) - COALESCE(paid_amount,0), 0)) as total')
                ->value('total');
        } elseif ($hasAmount) {
            $outstanding = (int) $q->sum('amount');
        } else {
            $outstanding = 0;
        }

        return [
            'outstanding' => max(0, $outstanding),
            'has_fines' => $outstanding > 0,
        ];
    }

    private function unreadNotifications(int $memberId): int
    {
        if (!Schema::hasTable('member_notifications') || !$this->col('member_notifications', 'member_id')) {
            return 0;
        }

        $q = DB::table('member_notifications')->where('member_id', $memberId);

        if ($this->col('member_notifications', 'read_at')) {
            $q->whereNull('read_at');
        } elseif ($this->col('member_notifications', 'is_read')) {
            $q->where('is_read', 0);
        } elseif ($this->col('member_notifications', 'status')) {
            $q->where('status', 'unread');
        }

        return (int) $q->count();
    }

    private function recentNotifications(int $memberId): array
    {
        if (!Schema::hasTable('member_notifications') || !$this->col('member_notifications', 'member_id')) {
            return [];
        }

        $q = DB::table('member_notifications')
            ->where('member_id', $memberId)
            ->orderByDesc($this->col('member_notifications', 'created_at') ? 'created_at' : 'id')
            ->limit(3);

        $select = ['id'];
        if ($this->col('member_notifications', 'message')) {
            $select[] = 'message';
        } elseif ($this->col('member_notifications', 'title')) {
            $select[] = 'title';
        }

        if ($this->col('member_notifications', 'created_at')) {
            $select[] = 'created_at';
        }
        if ($this->col('member_notifications', 'read_at')) {
            $select[] = 'read_at';
        }
        if ($this->col('member_notifications', 'is_read')) {
            $select[] = 'is_read';
        }
        if ($this->col('member_notifications', 'status')) {
            $select[] = 'status';
        }
        if ($this->col('member_notifications', 'type')) {
            $select[] = 'type';
        }

        $rows = $q->get($select);

        return $rows->map(function ($r) {
            $text = (string) ($r->message ?? $r->title ?? 'Notifikasi');
            $read = false;
            if (isset($r->read_at)) {
                $read = !empty($r->read_at);
            } elseif (isset($r->is_read)) {
                $read = (bool) $r->is_read;
            } elseif (isset($r->status)) {
                $read = (string) $r->status === 'read';
            }
            return [
                'id' => (int) $r->id,
                'text' => $text !== '' ? $text : 'Notifikasi',
                'read' => $read,
                'type' => (string) ($r->type ?? ''),
                'created_at' => $r->created_at ?? null,
            ];
        })->all();
    }
    private function recentLoans(int $institutionId, int $memberId, ?int $branchId): array
    {
        $q = DB::table('loans as l');
        $this->scopeInstitution($q, 'loans', 'l', $institutionId);
        $this->scopeBranch($q, 'loans', 'l', $branchId);

        $q->where('l.member_id', $memberId);

        $createdCol = $this->col('loans', 'created_at')
            ? 'l.created_at'
            : ($this->col('loans', 'loaned_at') ? 'l.loaned_at' : 'l.id');

        $statusCol = $this->col('loans', 'status') ? 'l.status' : null;
        $returnedAtCol = $this->col('loans', 'returned_at') ? 'l.returned_at' : null;

        $rows = $q->orderByDesc($createdCol)
            ->limit(5)
            ->get([
                'l.id as loan_id',
                $createdCol . ' as created_at',
                $statusCol ? $statusCol . ' as status' : DB::raw('NULL as status'),
                $returnedAtCol ? $returnedAtCol . ' as returned_at' : DB::raw('NULL as returned_at'),
            ]);

        return $rows->map(function ($r) {
            $status = (string) ($r->status ?? '');
            if ($status === '') {
                $status = !empty($r->returned_at) ? 'closed' : 'active';
            }
            return [
                'loan_id' => (int) $r->loan_id,
                'status' => $status,
                'created_at' => $r->created_at ?? null,
            ];
        })->all();
    }

    private function activeLoansSummary(int $institutionId, int $memberId, ?int $branchId): array
    {
        $loans = DB::table('loans as l');
        $this->scopeInstitution($loans, 'loans', 'l', $institutionId);
        $this->scopeBranch($loans, 'loans', 'l', $branchId);

        $loans->where('l.member_id', $memberId);
        $this->whereActiveLoan($loans, 'l');

        $activeLoans = (int) $loans->distinct('l.id')->count('l.id');

        $items = DB::table('loan_items as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id');

        $this->scopeInstitution($items, 'loans', 'l', $institutionId);
        $this->scopeBranch($items, 'loans', 'l', $branchId);
        $this->scopeInstitution($items, 'loan_items', 'li', $institutionId);
        $this->scopeBranch($items, 'loan_items', 'li', $branchId);

        $items->where('l.member_id', $memberId);
        $this->whereActiveLoan($items, 'l');
        $this->whereNotReturnedItem($items, 'li');

        $activeItems = (int) $items->count('li.id');

        return [
            'active_loans' => $activeLoans,
            'active_items' => $activeItems,
        ];
    }

    private function overdueSummary(int $institutionId, int $memberId, ?int $branchId, Carbon $today): array
    {
        $q = DB::table('loan_items as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id');

        $this->scopeInstitution($q, 'loans', 'l', $institutionId);
        $this->scopeBranch($q, 'loans', 'l', $branchId);
        $this->scopeInstitution($q, 'loan_items', 'li', $institutionId);
        $this->scopeBranch($q, 'loan_items', 'li', $branchId);

        $q->where('l.member_id', $memberId);
        $this->whereActiveLoan($q, 'l');
        $this->whereNotReturnedItem($q, 'li');

        $dueCol = $this->col('loan_items', 'due_date')
            ? 'li.due_date'
            : ($this->col('loans', 'due_date') ? 'l.due_date' : null);

        if (!$dueCol) {
            return ['overdue_items' => 0, 'max_overdue_days' => 0];
        }

        $q->whereDate($dueCol, '<', $today->toDateString());

        $row = $q->selectRaw('COUNT(li.id) as overdue_items')
            ->selectRaw("COALESCE(MAX(DATEDIFF(CURDATE(), {$dueCol})), 0) as max_overdue_days")
            ->first();

        return [
            'overdue_items' => (int) ($row->overdue_items ?? 0),
            'max_overdue_days' => (int) ($row->max_overdue_days ?? 0),
        ];
    }

    private function renewSummary(int $institutionId, int $memberId, ?int $branchId): array
    {
        if (!$this->col('loan_items', 'renew_count')) {
            return ['max_renew_count' => 0];
        }

        $q = DB::table('loan_items as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id');

        $this->scopeInstitution($q, 'loans', 'l', $institutionId);
        $this->scopeBranch($q, 'loans', 'l', $branchId);
        $this->scopeInstitution($q, 'loan_items', 'li', $institutionId);
        $this->scopeBranch($q, 'loan_items', 'li', $branchId);

        $q->where('l.member_id', $memberId);
        $this->whereActiveLoan($q, 'l');
        $this->whereNotReturnedItem($q, 'li');

        $row = $q->selectRaw('COALESCE(MAX(li.renew_count), 0) as max_renew_count')->first();

        return [
            'max_renew_count' => (int) ($row->max_renew_count ?? 0),
        ];
    }

    private function dueSoon(int $institutionId, int $memberId, ?int $branchId, Carbon $today): array
    {
        $q = DB::table('loan_items as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id')
            ->leftJoin('items as i', fn($j) => $j->on('i.id', '=', 'li.item_id'));

        $this->scopeInstitution($q, 'loans', 'l', $institutionId);
        $this->scopeBranch($q, 'loans', 'l', $branchId);
        $this->scopeInstitution($q, 'loan_items', 'li', $institutionId);
        $this->scopeBranch($q, 'loan_items', 'li', $branchId);
        $this->scopeInstitution($q, 'items', 'i', $institutionId);
        $this->scopeBranch($q, 'items', 'i', $branchId);

        $q->where('l.member_id', $memberId);
        $this->whereActiveLoan($q, 'l');
        $this->whereNotReturnedItem($q, 'li');

        $dueCol = $this->col('loan_items', 'due_date')
            ? 'li.due_date'
            : ($this->col('loans', 'due_date') ? 'l.due_date' : null);

        if (!$dueCol) return [];

        // Join judul yang BENAR (katalog atau biblio)
        $titleSelect = $this->applyTitleJoin($q);

        $q->whereDate($dueCol, '>=', $today->toDateString());

        $rows = $q->select([
                'l.id as loan_id',
                DB::raw("{$dueCol} as due_date"),
                'i.barcode as item_barcode',
                $titleSelect,
            ])
            ->orderBy($dueCol)
            ->limit(5)
            ->get();

        return $rows->map(function ($r) {
            $title = (string) ($r->title ?? '');
            $title = $title !== '' ? $title : '—';
            return [
                'loan_id' => (int) $r->loan_id,
                'due_date' => $r->due_date,
                'barcode' => (string) ($r->item_barcode ?? '—'),
                'title' => $title,
            ];
        })->all();
    }

    private function history14Days(int $institutionId, int $memberId, ?int $branchId, Carbon $now): array
    {
        $from = $now->copy()->startOfDay()->subDays(13);
        $to = $now->copy()->endOfDay();

        $borrowQ = DB::table('loans as l');
        $this->scopeInstitution($borrowQ, 'loans', 'l', $institutionId);
        $this->scopeBranch($borrowQ, 'loans', 'l', $branchId);

        $borrowQ->where('l.member_id', $memberId);

        $createdCol = $this->col('loans', 'created_at')
            ? 'l.created_at'
            : ($this->col('loans', 'loaned_at') ? 'l.loaned_at' : null);

        if (!$createdCol) $createdCol = 'l.id';

        $borrow = $borrowQ
            ->whereBetween($createdCol, [$from, $to])
            ->selectRaw("DATE({$createdCol}) as d, COUNT(*) as c")
            ->groupBy('d')
            ->pluck('c', 'd')
            ->all();

        $return = [];
        $retCol = $this->col('loan_items', 'returned_at')
            ? 'li.returned_at'
            : ($this->col('loans', 'returned_at') ? 'l.returned_at' : null);

        if ($retCol) {
            $retQ = DB::table('loan_items as li')
                ->join('loans as l', 'l.id', '=', 'li.loan_id');

            $this->scopeInstitution($retQ, 'loans', 'l', $institutionId);
            $this->scopeBranch($retQ, 'loans', 'l', $branchId);
            $this->scopeInstitution($retQ, 'loan_items', 'li', $institutionId);
            $this->scopeBranch($retQ, 'loan_items', 'li', $branchId);

            $retQ->where('l.member_id', $memberId)
                ->whereNotNull($retCol)
                ->whereBetween($retCol, [$from, $to]);

            $return = $retQ
                ->selectRaw("DATE({$retCol}) as d, COUNT(li.id) as c")
                ->groupBy('d')
                ->pluck('c', 'd')
                ->all();
        }

        $days = [];
        $max = 0;

        for ($i = 0; $i < 14; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $b = (int) ($borrow[$d] ?? 0);
            $r = (int) ($return[$d] ?? 0);
            $max = max($max, $b, $r);

            $days[] = [
                'date' => $d,
                'borrow' => $b,
                'return' => $r,
            ];
        }

        return [
            'days' => $days,
            'max' => $max,
        ];
    }

    private function monthlyStats(int $institutionId, int $memberId, ?int $branchId, Carbon $now): array
    {
        $start = $now->copy()->startOfMonth();
        $end = $now->copy()->endOfMonth();

        $loanCreatedCol = $this->col('loans', 'created_at')
            ? 'l.created_at'
            : ($this->col('loans', 'loaned_at') ? 'l.loaned_at' : null);

        if (!$loanCreatedCol) $loanCreatedCol = 'l.id';

        $loansQ = DB::table('loans as l');
        $this->scopeInstitution($loansQ, 'loans', 'l', $institutionId);
        $this->scopeBranch($loansQ, 'loans', 'l', $branchId);

        $totalLoans = (int) $loansQ
            ->where('l.member_id', $memberId)
            ->whereBetween($loanCreatedCol, [$start, $end])
            ->count('l.id');

        $borrowItemsQ = DB::table('loan_items as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id');

        $this->scopeInstitution($borrowItemsQ, 'loans', 'l', $institutionId);
        $this->scopeBranch($borrowItemsQ, 'loans', 'l', $branchId);
        $this->scopeInstitution($borrowItemsQ, 'loan_items', 'li', $institutionId);
        $this->scopeBranch($borrowItemsQ, 'loan_items', 'li', $branchId);

        $borrowedItems = (int) $borrowItemsQ
            ->where('l.member_id', $memberId)
            ->whereBetween($loanCreatedCol, [$start, $end])
            ->count('li.id');

        $returnedItems = 0;
        $retCol = $this->col('loan_items', 'returned_at') ? 'li.returned_at' : null;

        if ($retCol) {
            $returnedItemsQ = DB::table('loan_items as li')
                ->join('loans as l', 'l.id', '=', 'li.loan_id');

            $this->scopeInstitution($returnedItemsQ, 'loans', 'l', $institutionId);
            $this->scopeBranch($returnedItemsQ, 'loans', 'l', $branchId);
            $this->scopeInstitution($returnedItemsQ, 'loan_items', 'li', $institutionId);
            $this->scopeBranch($returnedItemsQ, 'loan_items', 'li', $branchId);

            $returnedItems = (int) $returnedItemsQ
                ->where('l.member_id', $memberId)
                ->whereBetween($retCol, [$start, $end])
                ->count('li.id');
        }

        $returnRate = $borrowedItems > 0 ? round(($returnedItems / $borrowedItems) * 100, 1) : 0.0;

        $avgDays = null;
        if ($retCol) {
            $borrowAt = $this->col('loan_items', 'borrowed_at')
                ? 'li.borrowed_at'
                : ($this->col('loans', 'created_at') ? 'l.created_at' : null);

            if ($borrowAt) {
                $durQ = DB::table('loan_items as li')
                    ->join('loans as l', 'l.id', '=', 'li.loan_id');

                $this->scopeInstitution($durQ, 'loans', 'l', $institutionId);
                $this->scopeBranch($durQ, 'loans', 'l', $branchId);
                $this->scopeInstitution($durQ, 'loan_items', 'li', $institutionId);
                $this->scopeBranch($durQ, 'loan_items', 'li', $branchId);

                $since = Carbon::now()->subDays(90)->startOfDay();

                $avgDays = (float) ($durQ
                    ->where('l.member_id', $memberId)
                    ->whereNotNull($retCol)
                    ->where($retCol, '>=', $since)
                    ->selectRaw("AVG(GREATEST(DATEDIFF(DATE({$retCol}), DATE({$borrowAt})), 0)) as avg_days")
                    ->value('avg_days') ?? null);

                if ($avgDays !== null) $avgDays = round($avgDays, 1);
            }
        }

        return [
            'total_loans_month' => $totalLoans,
            'return_rate_month' => $returnRate,
            'avg_duration_days' => $avgDays,
        ];
    }

    private function favoriteTitles(int $institutionId, int $memberId, ?int $branchId): array
    {
        // Pilih sumber judul yang benar:
        // - kalau items.katalog_id ada dan table katalog ada => katalog
        // - else kalau items.biblio_id ada dan table biblio ada => biblio
        $source = $this->titleSource();
        if (!$source) return [];

        $q = DB::table('loan_items as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id')
            ->join('items as i', 'i.id', '=', 'li.item_id');

        // join title
        $q->join($source['table'] . ' as t', 't.id', '=', "i.{$source['fk']}");

        $this->scopeInstitution($q, 'loans', 'l', $institutionId);
        $this->scopeBranch($q, 'loans', 'l', $branchId);
        $this->scopeInstitution($q, 'loan_items', 'li', $institutionId);
        $this->scopeBranch($q, 'loan_items', 'li', $branchId);
        $this->scopeInstitution($q, 'items', 'i', $institutionId);
        $this->scopeBranch($q, 'items', 'i', $branchId);
        $this->scopeInstitution($q, $source['table'], 't', $institutionId);
        $this->scopeBranch($q, $source['table'], 't', $branchId);

        $rows = $q->where('l.member_id', $memberId)
            ->selectRaw("t.id as title_id, t.{$source['title_col']} as title, COUNT(li.id) as borrow_count")
            ->groupBy('t.id', "t.{$source['title_col']}")
            ->orderByDesc('borrow_count')
            ->limit(8)
            ->get();

        return $rows->map(fn ($r) => [
            'title_id' => (int) $r->title_id,
            'title' => (string) ($r->title ?? '—'),
            'borrow_count' => (int) ($r->borrow_count ?? 0),
        ])->all();
    }

    public function popularTitles(int $institutionId, ?int $branchId, int $limit = 6): array
    {
        if (!Schema::hasTable('loan_items') || !Schema::hasTable('loans') || !Schema::hasTable('items')) {
            return [];
        }

        $source = $this->titleSource();
        if (!$source) return [];

        $q = DB::table('loan_items as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id')
            ->join('items as i', 'i.id', '=', 'li.item_id')
            ->join($source['table'] . ' as t', 't.id', '=', "i.{$source['fk']}");

        $this->scopeInstitution($q, 'loans', 'l', $institutionId);
        $this->scopeBranch($q, 'loans', 'l', $branchId);
        $this->scopeInstitution($q, 'loan_items', 'li', $institutionId);
        $this->scopeBranch($q, 'loan_items', 'li', $branchId);
        $this->scopeInstitution($q, 'items', 'i', $institutionId);
        $this->scopeBranch($q, 'items', 'i', $branchId);
        $this->scopeInstitution($q, $source['table'], 't', $institutionId);
        $this->scopeBranch($q, $source['table'], 't', $branchId);

        $coverCol = $this->coverColumn($source['table']);
        $select = "t.id as title_id, t.{$source['title_col']} as title, COUNT(li.id) as borrow_count";
        if ($coverCol) {
            $select .= ", MAX(t.{$coverCol}) as cover";
        }

        $rows = $q->selectRaw($select)
            ->groupBy('t.id', "t.{$source['title_col']}")
            ->orderByDesc('borrow_count')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'title_id' => (int) $r->title_id,
            'title' => (string) ($r->title ?? '-'),
            'borrow_count' => (int) ($r->borrow_count ?? 0),
            'cover' => isset($r->cover) ? (string) $r->cover : null,
        ])->all();
    }

    public function latestTitles(int $institutionId, ?int $branchId, int $limit = 6): array
    {
        if (!Schema::hasTable('items')) {
            return [];
        }

        $source = $this->titleSource();
        if (!$source) return [];

        $q = DB::table('items as i')
            ->join($source['table'] . ' as t', 't.id', '=', "i.{$source['fk']}");

        $this->scopeInstitution($q, 'items', 'i', $institutionId);
        $this->scopeBranch($q, 'items', 'i', $branchId);
        $this->scopeInstitution($q, $source['table'], 't', $institutionId);
        $this->scopeBranch($q, $source['table'], 't', $branchId);

        $titleCreated = $this->col($source['table'], 'created_at') ? 't.created_at' : null;
        $itemCreated = $this->col('items', 'created_at') ? 'i.created_at' : null;
        $addedCol = $titleCreated ?? $itemCreated;
        $orderCol = $addedCol ?? 't.id';

        $coverCol = $this->coverColumn($source['table']);
        $select = "t.id as title_id, t.{$source['title_col']} as title";
        if ($addedCol) {
            $select .= ", MAX({$addedCol}) as added_at";
        }
        if ($coverCol) {
            $select .= ", MAX(t.{$coverCol}) as cover";
        }

        $rows = $q->selectRaw($select)
            ->groupBy('t.id', "t.{$source['title_col']}")
            ->orderByDesc($addedCol ? 'added_at' : 't.id')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'title_id' => (int) $r->title_id,
            'title' => (string) ($r->title ?? '-'),
            'added_at' => $r->added_at ?? null,
            'cover' => isset($r->cover) ? (string) $r->cover : null,
        ])->all();
    }
    /**
     * Active loan logic yang aman (tanpa orWhere tanpa clause awal).
     */
    private function whereActiveLoan(Builder $q, string $alias): void
    {
        $statusCol = $this->col('loans', 'status') ? "{$alias}.status" : null;
        $returnedAtCol = $this->col('loans', 'returned_at') ? "{$alias}.returned_at" : null;

        $q->where(function ($w) use ($statusCol, $returnedAtCol) {
            if ($returnedAtCol && $statusCol) {
                $w->whereNull($returnedAtCol)
                  ->orWhereIn($statusCol, ['active', 'ongoing', 'borrowed', 'open']);
                return;
            }

            if ($returnedAtCol) {
                $w->whereNull($returnedAtCol);
                return;
            }

            if ($statusCol) {
                $w->whereIn($statusCol, ['active', 'ongoing', 'borrowed', 'open']);
                return;
            }

            // fallback: kalau schema minim, anggap semua loan "aktif" (tidak ideal, tapi aman)
            $w->whereRaw('1=1');
        });
    }

    private function whereNotReturnedItem(Builder $q, string $alias): void
    {
        if ($this->col('loan_items', 'returned_at')) {
            $q->whereNull("{$alias}.returned_at");
            return;
        }
        if ($this->col('loan_items', 'status')) {
            $q->whereIn("{$alias}.status", ['active', 'borrowed', 'open']);
        }
    }

    /**
     * Join title untuk query dueSoon:
     * - katalog jika tersedia
     * - biblio jika tersedia
     * - fallback NULL
     */
    private function applyTitleJoin(Builder $q): \Illuminate\Database\Query\Expression
    {
        $source = $this->titleSource();

        if (!$source) {
            return DB::raw('NULL as title');
        }

        $q->leftJoin($source['table'] . ' as t', 't.id', '=', "i.{$source['fk']}");
        return DB::raw("t.{$source['title_col']} as title");
    }

    /**
     * Tentukan sumber judul yang paling valid.
     */
    private function coverColumn(string $table): ?string
    {
        $candidates = ['cover', 'cover_image', 'cover_url', 'image', 'gambar', 'thumbnail', 'thumb', 'sampul', 'cover_path'];
        foreach ($candidates as $col) {
            if ($this->col($table, $col)) return $col;
        }
        return null;
    }
    private function titleSource(): ?array
    {
        // Prioritas: katalog
        if ($this->col('items', 'katalog_id') && Schema::hasTable('katalog') && $this->col('katalog', 'judul')) {
            return ['table' => 'katalog', 'fk' => 'katalog_id', 'title_col' => 'judul'];
        }

        // Fallback: biblio
        if ($this->col('items', 'biblio_id') && Schema::hasTable('biblio') && $this->col('biblio', 'title')) {
            return ['table' => 'biblio', 'fk' => 'biblio_id', 'title_col' => 'title'];
        }

        return null;
    }

    private function scopeInstitution(Builder $q, string $table, string $alias, int $institutionId): void
    {
        if ($this->col($table, 'institution_id')) {
            $q->where("{$alias}.institution_id", $institutionId);
        }
    }

    private function scopeBranch(Builder $q, string $table, string $alias, ?int $branchId): void
    {
        if (!$branchId) return;
        if ($this->col($table, 'branch_id')) {
            $q->where("{$alias}.branch_id", $branchId);
        }
    }

    private function col(string $table, string $col): bool
    {
        $key = "{$table}.{$col}";
        if (array_key_exists($key, $this->hasCol)) return $this->hasCol[$key];

        $ok = Schema::hasColumn($table, $col);
        $this->hasCol[$key] = $ok;

        return $ok;
    }
}
