<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\MemberDashboardService;
use App\Support\MemberContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BerandaController extends Controller
{
    use MemberContext;

    public function __construct(
        protected DashboardService $dashboardService,
        protected MemberDashboardService $memberDashboardService
    ) {
        // Beranda boleh untuk guest (landing), jadi tidak dipaksa middleware auth di constructor.
        // Role-based dilakukan saat user login.
    }

    public function index(Request $request)
    {
        // Guest: tampilkan landing / welcome (aman, tidak merusak perilaku awal)
        if (!Auth::check()) {
            // kamu punya welcome.blade.php
            return view('welcome');
        }

        $user = Auth::user();
        $role = (string) ($user->role ?? 'member');

        // Context umum
        // - untuk member: MemberContext biasanya sudah lengkap (institutionId, activeBranchId, memberId)
        // - untuk admin/staff: pakai dari user + helper User::activeBranchId()
        $ctx = $this->memberContext($request);

        $institutionId = (int) ($ctx['institutionId'] ?? ($user->institution_id ?? 0));
        if ($institutionId <= 0) {
            // fallback aman (kalau project kamu memang selalu ada)
            $institutionId = 1;
        }

        // branch aktif:
        // - super_admin bisa switch cabang via session (User::activeBranchId())
        // - admin/staff pakai branch_id
        // - member pakai activeBranchId dari context (kalau ada)
        $activeBranchId = null;
        try {
            if (method_exists($user, 'activeBranchId')) {
                $activeBranchId = $user->activeBranchId();
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $activeBranchId = $ctx['activeBranchId'] ?? ($activeBranchId ?? ($user->branch_id ?? null));
        $activeBranchId = $activeBranchId ? (int) $activeBranchId : null;

        // Nama cabang untuk header (opsional)
        $activeBranchName = null;
        if ($activeBranchId) {
            try {
                $activeBranchName = DB::table('branches')->where('id', $activeBranchId)->value('name');
            } catch (\Throwable $e) {
                $activeBranchName = null;
            }
        }

        // =========================
        // MODE: MEMBER
        // =========================
        if ($role === 'member') {
            $memberId = (int) ($ctx['memberId'] ?? 0);

            // tetap gunakan resolver (sesuai pola MemberDashboardController kamu)
            try {
                $resolved = $this->memberDashboardService->resolveMemberId($user);
                if (!empty($resolved)) $memberId = (int) $resolved;
            } catch (\Throwable $e) {
                // ignore
            }

            // Kalau memberId kosong: tampilkan beranda “aman”
            $data = [
                'kpi' => ['active_loans' => 0, 'active_items' => 0, 'overdue_items' => 0, 'max_overdue_days' => 0],
                'due_soon' => [],
                'trend_14d' => ['days' => [], 'max' => 0],
                'stats' => [],
                'favorite_titles' => [],
            ];

            if ($memberId > 0) {
                $data = $this->memberDashboardService->buildDashboard(
                    institutionId: $institutionId,
                    memberId: $memberId,
                    activeBranchId: $activeBranchId
                );
            }

            $popularTitles = $this->memberDashboardService->popularTitles(
                institutionId: $institutionId,
                branchId: $activeBranchId,
                limit: 5
            );

            $latestTitles = $this->memberDashboardService->latestTitles(
                institutionId: $institutionId,
                branchId: $activeBranchId,
                limit: 5
            );

            $kpi = $data['kpi'] ?? [];

            $stats = [
                [
                    'label' => 'Pinjaman Aktif',
                    'value' => (int)($kpi['active_loans'] ?? 0),
                    'tone'  => 'green',
                    'icon'  => '#nb-icon-rotate',
                    'hint'  => 'Sedang berjalan',
                    'dot'   => 'green',
                ],
                [
                    'label' => 'Item Dipinjam',
                    'value' => (int)($kpi['active_items'] ?? 0),
                    'tone'  => 'blue',
                    'icon'  => '#nb-icon-book',
                    'hint'  => 'Total eksemplar',
                    'dot'   => 'blue',
                ],
                [
                    'label' => 'Terlambat',
                    'value' => (int)($kpi['overdue_items'] ?? 0),
                    'tone'  => 'indigo',
                    'icon'  => '#nb-icon-alert',
                    'hint'  => 'Perlu perhatian',
                    'dot'   => 'indigo',
                ],
                [
                    'label' => 'Max Telat (hari)',
                    'value' => (int)($kpi['max_overdue_days'] ?? 0),
                    'tone'  => 'teal',
                    'icon'  => '#nb-icon-clock',
                    'hint'  => 'Terburuk',
                    'dot'   => 'teal',
                ],
            ];

            return view('beranda', [
                'mode' => 'member',
                'activeBranchName' => $activeBranchName,
                'stats' => $stats,

                // konten member
                'dueSoon' => $data['due_soon'] ?? [],
                'favorite' => $data['favorite_titles'] ?? [],
                'popularTitles' => $popularTitles ?? [],
                'latestTitles' => $latestTitles ?? [],
            ]);
        }

        // =========================
        // MODE: ADMIN / STAFF
        // =========================
        $dash = $this->dashboardService->build(
            institutionId: $institutionId,
            activeBranchId: $activeBranchId,
            rangeDays: 14
        );

        $kpi = $dash['kpi'] ?? [];
        $health = $dash['health'] ?? [];

        $stats = [
            [
                'label' => 'Pinjam Hari Ini',
                'value' => (int)($kpi['loans_today'] ?? $kpi['loansToday'] ?? 0),
                'tone'  => 'blue',
                'icon'  => '#nb-icon-rotate',
                'hint'  => 'Transaksi masuk',
                'dot'   => 'blue',
            ],
            [
                'label' => 'Kembali Hari Ini',
                'value' => (int)($kpi['returns_today'] ?? $kpi['returnsToday'] ?? 0),
                'tone'  => 'green',
                'icon'  => '#nb-icon-clipboard',
                'hint'  => 'Item dikembalikan',
                'dot'   => 'green',
            ],
            [
                'label' => 'Pinjaman Aktif',
                'value' => (int)($kpi['open_loans'] ?? $kpi['openLoans'] ?? 0),
                'tone'  => 'indigo',
                'icon'  => '#nb-icon-book',
                'hint'  => 'Sedang berjalan',
                'dot'   => 'indigo',
            ],
            [
                'label' => 'Terlambat (Item)',
                'value' => (int)($kpi['overdue_items'] ?? $kpi['overdueItems'] ?? 0),
                'tone'  => 'teal',
                'icon'  => '#nb-icon-alert',
                'hint'  => 'Butuh tindak lanjut',
                'dot'   => 'teal',
            ],
        ];

        return view('beranda', [
            'mode' => 'admin',
            'activeBranchName' => $activeBranchName,
            'stats' => $stats,

            // konten admin
            'health' => $health,
            'topTitles' => $dash['top_titles'] ?? [],
            'topOverdueMembers' => $dash['top_overdue_members'] ?? [],
        ]);
    }
}
