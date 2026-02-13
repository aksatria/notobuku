<?php

namespace App\Http\Controllers;

use App\Services\MemberDashboardService;
use App\Support\MemberContext;
use Illuminate\Http\Request;

class MemberDashboardController extends Controller
{
    use MemberContext;

    public function __construct(
        protected MemberDashboardService $svc
    ) {
        // Profesional: dashboard member juga pakai role.member (bukan hard-guard di method)
        $this->middleware(['auth', 'role.member']);
    }

    public function index(Request $request)
    {
        $ctx = $this->memberContext($request);

        // kalau resolve memberId gagal total
        if (empty($ctx['memberId'])) {
            return view('member.dashboard', [
                'memberMissing' => true,
                'kpi' => ['active_loans' => 0, 'active_items' => 0, 'overdue_items' => 0, 'max_overdue_days' => 0],
                'dueSoon' => [],
                'trend' => ['days' => [], 'max' => 0],
                'stats' => ['total_loans_month' => 0, 'return_rate_month' => 0, 'avg_duration_days' => null],
                'fines' => ['outstanding' => 0, 'has_fines' => false],
                'notifUnread' => 0,
                'recentLoans' => [],
                'recentNotifications' => [],
                'favorite' => [],
            ]);
        }

        // Optional: tetap manfaatkan resolver di service (kalau ada logic lebih spesifik)
        $resolved = $this->svc->resolveMemberId($ctx['user']);
        if (!empty($resolved)) {
            $ctx['memberId'] = (int) $resolved;
        }

        $data = $this->svc->buildDashboard(
            institutionId: $ctx['institutionId'],
            memberId: (int) $ctx['memberId'],
            activeBranchId: $ctx['activeBranchId']
        );

        return view('member.dashboard', [
            'memberMissing' => false,
            'kpi' => $data['kpi'] ?? [],
            'dueSoon' => $data['due_soon'] ?? [],
            'trend' => $data['trend_14d'] ?? ['days' => [], 'max' => 0],
            'stats' => $data['stats'] ?? [],
            'fines' => $data['fines'] ?? ['outstanding' => 0, 'has_fines' => false],
            'notifUnread' => $data['notif_unread'] ?? 0,
            'recentLoans' => $data['recent_loans'] ?? [],
            'recentNotifications' => $data['recent_notifications'] ?? [],
            'favorite' => $data['favorite_titles'] ?? [],
        ]);
    }
}
