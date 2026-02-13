<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TransaksiDashboardService
{
    public function build(int $institutionId, ?int $branchId = null, bool $lockBranch = false): array
    {
        // update status open -> overdue
        $updQ = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->where('status', 'open')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());

        if ($lockBranch && $branchId) {
            $updQ->where('branch_id', (int)$branchId);
        }

        $updQ->update(['status' => 'overdue', 'updated_at' => now()]);

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $loansTodayQ = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->whereDate('loaned_at', $today);
        if ($lockBranch && $branchId) $loansTodayQ->where('branch_id', (int)$branchId);
        $loansToday = $loansTodayQ->count();

        $returnsTodayQ = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->whereNotNull('loan_items.returned_at')
            ->whereDate('loan_items.returned_at', $today);
        if ($lockBranch && $branchId) $returnsTodayQ->where('loans.branch_id', (int)$branchId);
        $returnsToday = $returnsTodayQ->count();

        $loansMonthQ = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->whereDate('loaned_at', '>=', $monthStart)
            ->whereDate('loaned_at', '<=', $monthEnd);
        if ($lockBranch && $branchId) $loansMonthQ->where('branch_id', (int)$branchId);
        $loansMonth = $loansMonthQ->count();

        $returnsMonthQ = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->whereNotNull('loan_items.returned_at')
            ->whereDate('loan_items.returned_at', '>=', $monthStart)
            ->whereDate('loan_items.returned_at', '<=', $monthEnd);
        if ($lockBranch && $branchId) $returnsMonthQ->where('loans.branch_id', (int)$branchId);
        $returnsMonth = $returnsMonthQ->count();

        $openLoansQ = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->where('status', 'open');
        if ($lockBranch && $branchId) $openLoansQ->where('branch_id', (int)$branchId);
        $openLoans = $openLoansQ->count();

        $overdueLoansQ = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->where('status', 'overdue');
        if ($lockBranch && $branchId) $overdueLoansQ->where('branch_id', (int)$branchId);
        $overdueLoans = $overdueLoansQ->count();

        $overdueItemsQ = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->whereNull('loan_items.returned_at')
            ->whereNotNull('loan_items.due_at')
            ->where('loan_items.due_at', '<', now());
        if ($lockBranch && $branchId) $overdueItemsQ->where('loans.branch_id', (int)$branchId);
        $overdueItems = $overdueItemsQ->count();

        // trend 14 hari
        $trendLoanQ = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->whereDate('loaned_at', '>=', now()->subDays(13)->toDateString())
            ->select([DB::raw('DATE(loaned_at) as d'), DB::raw('COUNT(*) as c')])
            ->groupBy(DB::raw('DATE(loaned_at)'))
            ->orderBy('d');
        if ($lockBranch && $branchId) $trendLoanQ->where('branch_id', (int)$branchId);
        $trendLoan = $trendLoanQ->get();

        $mapLoan = [];
        foreach ($trendLoan as $t) $mapLoan[$t->d] = (int)$t->c;

        $trendRetQ = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->whereNotNull('loan_items.returned_at')
            ->whereDate('loan_items.returned_at', '>=', now()->subDays(13)->toDateString())
            ->select([DB::raw('DATE(loan_items.returned_at) as d'), DB::raw('COUNT(*) as c')])
            ->groupBy(DB::raw('DATE(loan_items.returned_at)'))
            ->orderBy('d');
        if ($lockBranch && $branchId) $trendRetQ->where('loans.branch_id', (int)$branchId);
        $trendRet = $trendRetQ->get();

        $mapRet = [];
        foreach ($trendRet as $t) $mapRet[$t->d] = (int)$t->c;

        $trend14 = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $trend14[] = [
                'date' => $d,
                'loans' => (int)($mapLoan[$d] ?? 0),
                'returns' => (int)($mapRet[$d] ?? 0),
            ];
        }

        return [
            'kpi' => [
                'loans_today' => $loansToday,
                'returns_today' => $returnsToday,
                'loans_month' => $loansMonth,
                'returns_month' => $returnsMonth,
                'open_loans' => $openLoans,
                'overdue_loans' => $overdueLoans,
                'overdue_items' => $overdueItems,
            ],
            'trend14' => $trend14,
        ];
    }
}
