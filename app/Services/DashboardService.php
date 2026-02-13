<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function build(
        int $institutionId,
        ?int $activeBranchId,
        int $rangeDays = 14
    ): array {
        $now = Carbon::now();

        $rangeDays = $this->normalizeRangeDays($rangeDays);

        $kpi = $this->kpi($institutionId, $activeBranchId, $now);
        $health = $this->health($institutionId, $activeBranchId, $now, $kpi);

        return [
            'kpi' => $kpi,
            'health' => $health,
            'range_days' => $rangeDays,
            'trend' => $this->trendLoansReturns($institutionId, $activeBranchId, $now, $rangeDays),
            'aging_overdue' => $this->agingOverdue($institutionId, $activeBranchId, $now),
            'top_titles' => $this->topTitles($institutionId, $activeBranchId, $now, $rangeDays, 8),
            'top_overdue_members' => $this->topOverdueMembers($institutionId, $activeBranchId, $now, 8),
        ];
    }

    public function normalizeRangeDays(int $rangeDays): int
    {
        $allowed = [7, 14, 30];
        return in_array($rangeDays, $allowed, true) ? $rangeDays : 14;
    }

    public function kpi(
        int $institutionId,
        ?int $activeBranchId,
        Carbon $now
    ): array {
        $today = $now->toDateString();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        $loansToday = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->when($activeBranchId, fn ($q) => $q->where('branch_id', $activeBranchId))
            ->whereDate('loaned_at', $today)
            ->count();

        $returnsToday = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->when($activeBranchId, fn ($q) => $q->where('loans.branch_id', $activeBranchId))
            ->whereNotNull('loan_items.returned_at')
            ->whereDate('loan_items.returned_at', $today)
            ->count();

        $loansMonth = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->when($activeBranchId, fn ($q) => $q->where('branch_id', $activeBranchId))
            ->whereBetween(DB::raw('DATE(loaned_at)'), [$monthStart, $monthEnd])
            ->count();

        $returnsMonth = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->when($activeBranchId, fn ($q) => $q->where('loans.branch_id', $activeBranchId))
            ->whereNotNull('loan_items.returned_at')
            ->whereBetween(DB::raw('DATE(loan_items.returned_at)'), [$monthStart, $monthEnd])
            ->count();

        /**
         * IMPORTANT:
         * Untuk konsistensi "real data", open/overdue loan dihitung dari loan_items,
         * bukan dari loans.status (yang bisa tidak sinkron pada data lama).
         */
        $openLoans = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->when($activeBranchId, fn ($q) => $q->where('loans.branch_id', $activeBranchId))
            ->whereNull('loan_items.returned_at')
            ->distinct('loans.id')
            ->count('loans.id');

        $overdueLoans = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->when($activeBranchId, fn ($q) => $q->where('loans.branch_id', $activeBranchId))
            ->whereNull('loan_items.returned_at')
            ->whereNotNull('loan_items.due_at')
            ->where('loan_items.due_at', '<', $now->toDateTimeString())
            ->distinct('loans.id')
            ->count('loans.id');

        $overdueItems = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->when($activeBranchId, fn ($q) => $q->where('loans.branch_id', $activeBranchId))
            ->whereNull('loan_items.returned_at')
            ->whereNotNull('loan_items.due_at')
            ->where('loan_items.due_at', '<', $now->toDateTimeString())
            ->count();

        return [
            'loans_today' => (int) $loansToday,
            'returns_today' => (int) $returnsToday,
            'loans_month' => (int) $loansMonth,
            'returns_month' => (int) $returnsMonth,
            'open_loans' => (int) $openLoans,
            'overdue_loans' => (int) $overdueLoans,
            'overdue_items' => (int) $overdueItems,

            // alias camelCase biar view kamu tetap aman
            'loansToday' => (int) $loansToday,
            'returnsToday' => (int) $returnsToday,
            'loansMonth' => (int) $loansMonth,
            'returnsMonth' => (int) $returnsMonth,
            'openLoans' => (int) $openLoans,
            'overdueLoans' => (int) $overdueLoans,
            'overdueItems' => (int) $overdueItems,
        ];
    }

    public function health(
        int $institutionId,
        ?int $activeBranchId,
        Carbon $now,
        array $kpi
    ): array {
        $loansMonth = (int) ($kpi['loans_month'] ?? $kpi['loansMonth'] ?? 0);
        $returnsMonth = (int) ($kpi['returns_month'] ?? $kpi['returnsMonth'] ?? 0);

        $returnRate = $loansMonth > 0
            ? round(($returnsMonth / $loansMonth) * 100, 1)
            : 0.0;

        $openItems = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->when($activeBranchId, fn ($q) => $q->where('loans.branch_id', $activeBranchId))
            ->whereNull('loan_items.returned_at')
            ->count();

        $overdueItems = (int) ($kpi['overdue_items'] ?? $kpi['overdueItems'] ?? 0);

        $overdueRatio = $openItems > 0
            ? round(($overdueItems / $openItems) * 100, 1)
            : 0.0;

        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        $returnsBase = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->when($activeBranchId, fn ($q) => $q->where('loans.branch_id', $activeBranchId))
            ->whereNotNull('loan_items.returned_at')
            ->whereBetween(DB::raw('DATE(loan_items.returned_at)'), [$monthStart, $monthEnd]);

        $returnsTotal = (clone $returnsBase)->count();

        $returnsOnTime = (clone $returnsBase)
            ->whereNotNull('loan_items.due_at')
            ->whereColumn('loan_items.returned_at', '<=', 'loan_items.due_at')
            ->count();

        $onTimeRate = $returnsTotal > 0
            ? round(($returnsOnTime / $returnsTotal) * 100, 1)
            : 0.0;

        return [
            'return_rate' => $returnRate,
            'overdue_ratio' => $overdueRatio,
            'on_time_rate' => $onTimeRate,
            'open_items' => (int) $openItems,
            'returns_on_time_month' => (int) $returnsOnTime,
            'returns_total_month' => (int) $returnsTotal,
        ];
    }

    public function trendLoansReturns(
        int $institutionId,
        ?int $activeBranchId,
        Carbon $now,
        int $days = 14
    ): array {
        $days = $this->normalizeRangeDays((int) $days);

        $start = $now->copy()
            ->subDays($days - 1)
            ->startOfDay();

        $dateKeys = [];
        for ($i = 0; $i < $days; $i++) {
            $dateKeys[] = $start->copy()->addDays($i)->toDateString();
        }

        $loansByDay = DB::table('loans')
            ->selectRaw('DATE(loaned_at) as d, COUNT(*) as c')
            ->where('institution_id', $institutionId)
            ->when($activeBranchId, fn ($q) => $q->where('branch_id', $activeBranchId))
            ->whereDate('loaned_at', '>=', $start->toDateString())
            ->groupBy('d')
            ->pluck('c', 'd');

        $returnsByDay = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->selectRaw('DATE(loan_items.returned_at) as d, COUNT(*) as c')
            ->where('loans.institution_id', $institutionId)
            ->when($activeBranchId, fn ($q) => $q->where('loans.branch_id', $activeBranchId))
            ->whereNotNull('loan_items.returned_at')
            ->whereDate('loan_items.returned_at', '>=', $start->toDateString())
            ->groupBy('d')
            ->pluck('c', 'd');

        $out = [];
        foreach ($dateKeys as $d) {
            $out[] = [
                'date' => $d,
                'loans' => (int) ($loansByDay[$d] ?? 0),
                'returns' => (int) ($returnsByDay[$d] ?? 0),
            ];
        }

        return $out;
    }

    public function agingOverdue(
        int $institutionId,
        ?int $activeBranchId,
        Carbon $now
    ): array {
        $rows = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->when($activeBranchId, fn ($q) => $q->where('loans.branch_id', $activeBranchId))
            ->whereNull('loan_items.returned_at')
            ->whereNotNull('loan_items.due_at')
            ->where('loan_items.due_at', '<', $now->toDateTimeString())
            ->selectRaw('DATEDIFF(?, loan_items.due_at) as days_overdue', [$now->toDateString()])
            ->get();

        $buckets = [
            '1-3' => 0,
            '4-7' => 0,
            '8-14' => 0,
            '15-30' => 0,
            '30+' => 0,
        ];

        foreach ($rows as $r) {
            $d = (int) ($r->days_overdue ?? 0);
            if ($d <= 0) continue;

            if ($d <= 3) $buckets['1-3']++;
            elseif ($d <= 7) $buckets['4-7']++;
            elseif ($d <= 14) $buckets['8-14']++;
            elseif ($d <= 30) $buckets['15-30']++;
            else $buckets['30+']++;
        }

        return $buckets;
    }

    public function topTitles(
        int $institutionId,
        ?int $activeBranchId,
        Carbon $now,
        int $days = 14,
        int $limit = 8
    ): array {
        $days = $this->normalizeRangeDays((int) $days);
        $limit = max(1, (int) $limit);

        $start = $now->copy()->subDays($days - 1)->startOfDay();

        $rows = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->join('items', 'items.id', '=', 'loan_items.item_id')
            ->leftJoin('biblio', 'biblio.id', '=', 'items.biblio_id')
            ->where('loans.institution_id', $institutionId)
            ->when($activeBranchId, fn ($q) => $q->where('loans.branch_id', $activeBranchId))
            ->whereDate('loans.loaned_at', '>=', $start->toDateString())
            ->selectRaw('biblio.title as title, COUNT(*) as total')
            ->groupBy('biblio.title')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $titles = $rows->map(fn ($r) => (string) ($r->title ?? '-'))->all();
        $stockByTitle = [];
        if (!empty($titles)) {
            $stockByTitle = DB::table('items')
                ->leftJoin('biblio', 'biblio.id', '=', 'items.biblio_id')
                ->where('items.institution_id', $institutionId)
                ->when($activeBranchId, fn ($q) => $q->where('items.branch_id', $activeBranchId))
                ->whereIn('biblio.title', $titles)
                ->selectRaw('biblio.title as title, COUNT(*) as stock')
                ->groupBy('biblio.title')
                ->pluck('stock', 'title')
                ->all();
        }

        return $rows->map(function ($r) use ($stockByTitle) {
            $title = (string) ($r->title ?? '-');
            $total = (int) ($r->total ?? 0);
            $stock = (int) ($stockByTitle[$title] ?? 0);
            $pressure = $stock > 0 ? round($total / $stock, 2) : 0.0;

            return [
                'title' => $title,
                'total' => $total,
                'stock' => $stock,
                'stock_pressure' => $pressure,
            ];
        })->all();
    }

    public function topOverdueMembers(
        int $institutionId,
        ?int $activeBranchId,
        Carbon $now,
        int $limit = 8
    ): array {
        if (!$this->tableExists('members')) {
            return [];
        }

        $nameCol = $this->detectExistingColumn('members', ['name', 'nama', 'full_name']) ?: 'name';
        $codeCol = $this->detectExistingColumn('members', ['code', 'kode', 'member_code', 'nomor']);

        $nowDate = $now->toDateString();
        $limit = max(1, (int) $limit);

        $rows = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->join('members', 'members.id', '=', 'loans.member_id')
            ->where('loans.institution_id', $institutionId)
            ->when($activeBranchId, fn ($q) => $q->where('loans.branch_id', $activeBranchId))
            ->whereNull('loan_items.returned_at')
            ->whereNotNull('loan_items.due_at')
            ->where('loan_items.due_at', '<', $now->toDateTimeString())
            ->selectRaw(
                'members.id as member_id,
                 members.' . $nameCol . ' as name,
                 ' . ($codeCol ? ('members.' . $codeCol . ' as code,') : ('NULL as code,')) . '
                 COUNT(*) as overdue_items,
                 MAX(DATEDIFF(?, loan_items.due_at)) as max_days_overdue',
                [$nowDate]
            )
            ->groupBy('members.id', 'members.' . $nameCol)
            ->when($codeCol, fn ($q) => $q->groupBy('members.' . $codeCol))
            ->orderByDesc('overdue_items')
            ->orderByDesc('max_days_overdue')
            ->limit($limit)
            ->get();

        return $rows->map(function ($r) {
            return [
                'member_id' => (int) ($r->member_id ?? 0),
                'name' => (string) ($r->name ?? '-'),
                'code' => $r->code !== null ? (string) $r->code : '-',
                'overdue_items' => (int) ($r->overdue_items ?? 0),
                'max_days_overdue' => (int) ($r->max_days_overdue ?? 0),
            ];
        })->all();
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function detectExistingColumn(string $table, array $candidates): ?string
    {
        try {
            $schema = DB::getSchemaBuilder();
            foreach ($candidates as $col) {
                if ($schema->hasColumn($table, $col)) {
                    return $col;
                }
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
