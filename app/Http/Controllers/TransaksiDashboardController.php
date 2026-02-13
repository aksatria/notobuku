<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class TransaksiDashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboard
    ) {
        $this->middleware(['auth', 'role.any:super_admin,admin,staff']);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $activeBranchId = session('active_branch_id')
            ?: ($user->branch_id ?? null);

        $sessionKey = 'dashboard_range_days';

        $rangeFromQuery = $request->query('range');
        if ($rangeFromQuery !== null && $rangeFromQuery !== '') {
            $rangeDays = $this->dashboard->normalizeRangeDays((int) $rangeFromQuery);
            session([$sessionKey => $rangeDays]);
        } else {
            $rangeDays = (int) session($sessionKey, 14);
            $rangeDays = $this->dashboard->normalizeRangeDays($rangeDays);
            session([$sessionKey => $rangeDays]);
        }

        $data = $this->dashboard->build(
            institutionId: (int) $user->institution_id,
            activeBranchId: $activeBranchId ? (int) $activeBranchId : null,
            rangeDays: $rangeDays
        );

        return view('transaksi.dashboard', [
            'title' => 'Dashboard Sirkulasi',
            'range_days' => (int) ($data['range_days'] ?? $rangeDays),
            'kpi' => $data['kpi'] ?? [],
            'health' => $data['health'] ?? [],
            'trend' => $data['trend'] ?? [],
            'aging_overdue' => $data['aging_overdue'] ?? [],
            'top_titles' => $data['top_titles'] ?? [],
            'top_overdue_members' => $data['top_overdue_members'] ?? [],
            'active_branch_id' => $activeBranchId ? (int) $activeBranchId : null,
        ]);
    }
}
