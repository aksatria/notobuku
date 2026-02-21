<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\VisitorCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisitorCounterController extends Controller
{
    private function allowedAuditActions(): array
    {
        return [
            'visitor_counter.checkin',
            'visitor_counter.checkout',
            'visitor_counter.checkout_bulk',
            'visitor_counter.checkout_selected',
            'visitor_counter.undo_checkout',
            'visitor_counter.checkout_skipped',
            'visitor_counter.undo_checkout_skipped',
            'visitor_counter.undo_checkout_denied',
        ];
    }

    private function allowedAuditRoles(): array
    {
        return ['super_admin', 'admin', 'staff', 'member'];
    }

    private function allowedAuditSorts(): array
    {
        return ['latest', 'oldest'];
    }

    private function institutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    private function resolveDateFilter(string $rawDate, string $preset): array
    {
        $today = now()->toDateString();
        $date = $rawDate !== '' ? $rawDate : $today;
        $preset = trim($preset);

        if (!in_array($preset, ['today', 'yesterday', 'last7', 'custom'], true)) {
            $preset = 'custom';
        }

        if ($preset === 'today') {
            $date = $today;
            return [$date, $date, $date, $preset];
        }

        if ($preset === 'yesterday') {
            $date = now()->subDay()->toDateString();
            return [$date, $date, $date, $preset];
        }

        if ($preset === 'last7') {
            $to = $today;
            $from = now()->subDays(6)->toDateString();
            $date = $to;
            return [$date, $from, $to, $preset];
        }

        // custom fallback to single selected day
        return [$date, $date, $date, 'custom'];
    }

    private function buildFilteredBaseQuery(int $institutionId, string $dateFrom, string $dateTo, int $branchId, string $keyword = '', bool $activeOnly = false)
    {
        return VisitorCounter::query()
            ->where('visitor_counters.institution_id', $institutionId)
            ->whereDate('visitor_counters.checkin_at', '>=', $dateFrom)
            ->whereDate('visitor_counters.checkin_at', '<=', $dateTo)
            ->when($branchId > 0, fn ($q) => $q->where('visitor_counters.branch_id', $branchId))
            ->when($activeOnly, fn ($q) => $q->whereNull('visitor_counters.checkout_at'))
            ->leftJoin('members as m', 'm.id', '=', 'visitor_counters.member_id')
            ->leftJoin('branches as b', 'b.id', '=', 'visitor_counters.branch_id')
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($qq) use ($keyword) {
                    $qq->where('visitor_counters.visitor_name', 'like', '%' . $keyword . '%')
                        ->orWhere('visitor_counters.member_code_snapshot', 'like', '%' . $keyword . '%')
                        ->orWhere('visitor_counters.purpose', 'like', '%' . $keyword . '%')
                        ->orWhere('m.full_name', 'like', '%' . $keyword . '%')
                        ->orWhere('m.member_code', 'like', '%' . $keyword . '%')
                        ->orWhere('b.name', 'like', '%' . $keyword . '%');
                });
            });
    }

    private function branchScope(): ?int
    {
        $user = auth()->user();
        $role = (string) ($user->role ?? 'member');
        if (!in_array($role, ['admin', 'staff'], true)) {
            return null;
        }

        $branchId = (int) ($user->branch_id ?? 0);
        if ($branchId <= 0) {
            abort(403, 'Akun Anda belum memiliki cabang.');
        }

        return $branchId;
    }

    private function normalizeBranchFilter(int $requestedBranchId, ?int $allowedBranchId): int
    {
        if ($allowedBranchId !== null) {
            return $allowedBranchId;
        }
        return $requestedBranchId > 0 ? $requestedBranchId : 0;
    }

    private function ensureRequestedBranchAllowed(int $requestedBranchId, ?int $allowedBranchId): void
    {
        if ($allowedBranchId === null || $requestedBranchId <= 0) {
            return;
        }
        if ($requestedBranchId !== $allowedBranchId) {
            abort(403, 'Anda tidak berhak mengakses cabang ini.');
        }
    }

    private function writeAudit(string $action, ?string $auditableType, ?int $auditableId, array $metadata = []): void
    {
        $user = auth()->user();
        try {
            DB::table('audits')->insert([
                'institution_id' => $this->institutionId(),
                'actor_user_id' => (int) ($user->id ?? 0) ?: null,
                'actor_role' => (string) ($user->role ?? null),
                'action' => $action,
                'module' => 'visitor_counter',
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
                'metadata' => json_encode($metadata),
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 1000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // keep feature flow stable even when audit write fails
        }
    }

    private function buildAuditBaseQuery(
        int $institutionId,
        ?int $allowedBranchId,
        string $auditAction = '',
        string $auditRole = '',
        string $auditKeyword = '',
        ?string $dateFrom = null,
        ?string $dateTo = null
    ) {
        $auditKeyword = trim($auditKeyword);
        $auditBase = DB::table('audits as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
            ->where('a.institution_id', $institutionId)
            ->where('a.module', 'visitor_counter')
            ->when($auditAction !== '', fn ($q) => $q->where('a.action', $auditAction))
            ->when($auditRole !== '', fn ($q) => $q->where('a.actor_role', $auditRole))
            ->when($auditKeyword !== '', function ($q) use ($auditKeyword) {
                $q->where(function ($qq) use ($auditKeyword) {
                    $qq->where('a.action', 'like', '%' . $auditKeyword . '%')
                        ->orWhere('u.name', 'like', '%' . $auditKeyword . '%')
                        ->orWhere('a.actor_role', 'like', '%' . $auditKeyword . '%')
                        ->orWhere('a.metadata', 'like', '%' . $auditKeyword . '%');

                    if (ctype_digit($auditKeyword)) {
                        $qq->orWhere('a.auditable_id', (int) $auditKeyword);
                    }
                });
            })
            ->when(!empty($dateFrom), fn ($q) => $q->whereDate('a.created_at', '>=', $dateFrom))
            ->when(!empty($dateTo), fn ($q) => $q->whereDate('a.created_at', '<=', $dateTo));

        if ($allowedBranchId !== null) {
            $auditBase->where(function ($q) use ($allowedBranchId) {
                $q->whereExists(function ($sq) use ($allowedBranchId) {
                    $sq->select(DB::raw(1))
                        ->from('visitor_counters as vc')
                        ->whereColumn('vc.id', 'a.auditable_id')
                        ->where('vc.branch_id', $allowedBranchId);
                })->orWhere('a.metadata', 'like', '%"branch_id":' . $allowedBranchId . '%');
            });
        }

        return $auditBase;
    }

    public function index(Request $request)
    {
        $institutionId = $this->institutionId();
        $allowedBranchId = $this->branchScope();
        $rawDate = trim((string) $request->query('date', ''));
        $rawPreset = trim((string) $request->query('preset', 'custom'));
        [$date, $dateFrom, $dateTo, $datePreset] = $this->resolveDateFilter($rawDate, $rawPreset);
        $branchId = $this->normalizeBranchFilter((int) $request->query('branch_id', 0), $allowedBranchId);
        $keyword = trim((string) $request->query('q', ''));
        $activeOnly = $request->boolean('active_only');
        $perPage = (int) $request->query('per_page', 20);
        if (!in_array($perPage, [20, 50, 100], true)) {
            $perPage = 20;
        }
        $auditAction = trim((string) $request->query('audit_action', ''));
        $auditRole = trim((string) $request->query('audit_role', ''));
        $auditKeyword = trim((string) $request->query('audit_q', ''));
        $auditSort = trim((string) $request->query('audit_sort', 'latest'));
        $auditPerPage = (int) $request->query('audit_per_page', 15);
        $allowedAuditActions = $this->allowedAuditActions();
        $allowedAuditRoles = $this->allowedAuditRoles();
        $allowedAuditSorts = $this->allowedAuditSorts();
        if ($auditAction !== '' && !in_array($auditAction, $allowedAuditActions, true)) {
            $auditAction = '';
        }
        if ($auditRole !== '' && !in_array($auditRole, $allowedAuditRoles, true)) {
            $auditRole = '';
        }
        if (!in_array($auditSort, $allowedAuditSorts, true)) {
            $auditSort = 'latest';
        }
        if (!in_array($auditPerPage, [10, 15, 25, 50], true)) {
            $auditPerPage = 15;
        }

        $branches = collect();
        if (Schema::hasTable('branches')) {
            $branchesQuery = DB::table('branches')
                ->where('institution_id', $institutionId)
                ->select('id', 'name');
            if ($allowedBranchId !== null) {
                $branchesQuery->where('id', $allowedBranchId);
            }
            $branches = $branchesQuery->orderBy('name')->get();
        }

        $base = $this->buildFilteredBaseQuery($institutionId, $dateFrom, $dateTo, $branchId, $keyword, $activeOnly);

        $rows = (clone $base)
            ->select([
                'visitor_counters.*',
                'm.full_name as member_name',
                'm.member_code as member_code',
                'b.name as branch_name',
            ])
            ->orderByDesc('visitor_counters.checkin_at')
            ->paginate($perPage)
            ->withQueryString();

        $stats = [
            'total' => (clone $base)->count('visitor_counters.id'),
            'member' => (clone $base)->where('visitor_counters.visitor_type', 'member')->count('visitor_counters.id'),
            'non_member' => (clone $base)->where('visitor_counters.visitor_type', 'non_member')->count('visitor_counters.id'),
            'active_inside' => (clone $base)->whereNull('visitor_counters.checkout_at')->count('visitor_counters.id'),
        ];

        $auditRows = null;
        $auditStats = ['checkin' => 0, 'checkout' => 0, 'undo' => 0];
        if (Schema::hasTable('audits')) {
            $auditBase = $this->buildAuditBaseQuery($institutionId, $allowedBranchId, $auditAction, $auditRole, $auditKeyword, $dateFrom, $dateTo);
            $auditStats = [
                'checkin' => (clone $auditBase)->where('a.action', 'visitor_counter.checkin')->count('a.id'),
                'checkout' => (clone $auditBase)->whereIn('a.action', [
                    'visitor_counter.checkout',
                    'visitor_counter.checkout_bulk',
                    'visitor_counter.checkout_selected',
                ])->count('a.id'),
                'undo' => (clone $auditBase)->where('a.action', 'visitor_counter.undo_checkout')->count('a.id'),
            ];

            $auditRows = $auditBase
                ->select([
                    'a.id',
                    'a.action',
                    'a.auditable_id',
                    'a.created_at',
                    'a.metadata',
                    'u.name as actor_name',
                    'a.actor_role',
                ])
                ->orderBy('a.id', $auditSort === 'oldest' ? 'asc' : 'desc')
                ->paginate($auditPerPage, ['*'], 'audit_page')
                ->withQueryString();

            $auditRows->getCollection()->transform(function ($row) {
                    $row->metadata_array = [];
                    if (!empty($row->metadata)) {
                        $decoded = json_decode((string) $row->metadata, true);
                        if (is_array($decoded)) {
                            $row->metadata_array = $decoded;
                        }
                    }
                    return $row;
                });
        }

        return view('visitor_counter.index', [
            'rows' => $rows,
            'branches' => $branches,
            'date' => $date,
            'datePreset' => $datePreset,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'branchId' => $branchId > 0 ? (string) $branchId : '',
            'keyword' => $keyword,
            'activeOnly' => $activeOnly,
            'perPage' => $perPage,
            'stats' => $stats,
            'auditRows' => $auditRows,
            'auditStats' => $auditStats,
            'auditAction' => $auditAction,
            'auditRole' => $auditRole,
            'auditKeyword' => $auditKeyword,
            'auditSort' => $auditSort,
            'auditPerPage' => $auditPerPage,
            'allowedAuditActions' => $allowedAuditActions,
            'allowedAuditRoles' => $allowedAuditRoles,
            'allowedAuditSorts' => $allowedAuditSorts,
        ]);
    }

    public function exportAuditCsv(Request $request): StreamedResponse
    {
        $institutionId = $this->institutionId();
        $allowedBranchId = $this->branchScope();
        $rawDate = trim((string) $request->query('date', ''));
        $rawPreset = trim((string) $request->query('preset', 'custom'));
        [, $dateFrom, $dateTo, ] = $this->resolveDateFilter($rawDate, $rawPreset);
        $auditAction = trim((string) $request->query('audit_action', ''));
        $auditRole = trim((string) $request->query('audit_role', ''));
        $auditKeyword = trim((string) $request->query('audit_q', ''));
        $auditSort = trim((string) $request->query('audit_sort', 'latest'));
        if ($auditAction !== '' && !in_array($auditAction, $this->allowedAuditActions(), true)) {
            $auditAction = '';
        }
        if ($auditRole !== '' && !in_array($auditRole, $this->allowedAuditRoles(), true)) {
            $auditRole = '';
        }
        if (!in_array($auditSort, $this->allowedAuditSorts(), true)) {
            $auditSort = 'latest';
        }

        $rows = $this->buildAuditBaseQuery($institutionId, $allowedBranchId, $auditAction, $auditRole, $auditKeyword, $dateFrom, $dateTo)
            ->select([
                'a.created_at',
                'a.action',
                'a.auditable_id',
                'a.actor_role',
                'u.name as actor_name',
                'a.metadata',
                'a.ip',
            ])
            ->orderBy('a.id', $auditSort === 'oldest' ? 'asc' : 'desc')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['created_at', 'action', 'actor', 'role', 'auditable_id', 'branch_id', 'ip', 'metadata']);

            foreach ($rows as $row) {
                $metadata = [];
                if (!empty($row->metadata)) {
                    $decoded = json_decode((string) $row->metadata, true);
                    if (is_array($decoded)) {
                        $metadata = $decoded;
                    }
                }
                $branchId = isset($metadata['branch_id']) ? (string) $metadata['branch_id'] : '';

                fputcsv($out, [
                    (string) ($row->created_at ?? ''),
                    (string) ($row->action ?? ''),
                    (string) ($row->actor_name ?? ''),
                    (string) ($row->actor_role ?? ''),
                    (string) ($row->auditable_id ?? ''),
                    $branchId,
                    (string) ($row->ip ?? ''),
                    json_encode($metadata, JSON_UNESCAPED_UNICODE),
                ]);
            }

            fclose($out);
        }, 'visitor-counter-audit.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportAuditJson(Request $request)
    {
        $institutionId = $this->institutionId();
        $allowedBranchId = $this->branchScope();
        $rawDate = trim((string) $request->query('date', ''));
        $rawPreset = trim((string) $request->query('preset', 'custom'));
        [, $dateFrom, $dateTo, ] = $this->resolveDateFilter($rawDate, $rawPreset);
        $auditAction = trim((string) $request->query('audit_action', ''));
        $auditRole = trim((string) $request->query('audit_role', ''));
        $auditKeyword = trim((string) $request->query('audit_q', ''));
        $auditSort = trim((string) $request->query('audit_sort', 'latest'));

        if ($auditAction !== '' && !in_array($auditAction, $this->allowedAuditActions(), true)) {
            $auditAction = '';
        }
        if ($auditRole !== '' && !in_array($auditRole, $this->allowedAuditRoles(), true)) {
            $auditRole = '';
        }
        if (!in_array($auditSort, $this->allowedAuditSorts(), true)) {
            $auditSort = 'latest';
        }

        $rows = $this->buildAuditBaseQuery($institutionId, $allowedBranchId, $auditAction, $auditRole, $auditKeyword, $dateFrom, $dateTo)
            ->select([
                'a.id',
                'a.created_at',
                'a.action',
                'a.actor_role',
                'u.name as actor_name',
                'a.auditable_type',
                'a.auditable_id',
                'a.metadata',
                'a.ip',
            ])
            ->orderBy('a.id', $auditSort === 'oldest' ? 'asc' : 'desc')
            ->get()
            ->map(function ($row) {
                $metadata = [];
                if (!empty($row->metadata)) {
                    $decoded = json_decode((string) $row->metadata, true);
                    if (is_array($decoded)) {
                        $metadata = $decoded;
                    }
                }
                return [
                    'id' => (int) ($row->id ?? 0),
                    'created_at' => (string) ($row->created_at ?? ''),
                    'action' => (string) ($row->action ?? ''),
                    'actor_name' => (string) ($row->actor_name ?? ''),
                    'actor_role' => (string) ($row->actor_role ?? ''),
                    'auditable_type' => (string) ($row->auditable_type ?? ''),
                    'auditable_id' => $row->auditable_id !== null ? (int) $row->auditable_id : null,
                    'ip' => (string) ($row->ip ?? ''),
                    'metadata' => $metadata,
                ];
            })
            ->values();

        return response()->json([
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'audit_action' => $auditAction !== '' ? $auditAction : null,
                'audit_role' => $auditRole !== '' ? $auditRole : null,
                'audit_q' => $auditKeyword !== '' ? $auditKeyword : null,
                'audit_sort' => $auditSort,
            ],
            'count' => $rows->count(),
            'items' => $rows,
        ]);
    }

    public function bulkCheckout(Request $request)
    {
        $institutionId = $this->institutionId();
        $allowedBranchId = $this->branchScope();
        $rawDate = trim((string) $request->input('date', ''));
        $rawPreset = trim((string) $request->input('preset', 'custom'));
        [$date, $dateFrom, $dateTo, $datePreset] = $this->resolveDateFilter($rawDate, $rawPreset);
        $branchId = $this->normalizeBranchFilter((int) $request->input('branch_id', 0), $allowedBranchId);
        $keyword = trim((string) $request->input('q', ''));
        $perPage = (int) $request->input('per_page', 20);

        $ids = $this->buildFilteredBaseQuery($institutionId, $dateFrom, $dateTo, $branchId, $keyword, true)
            ->pluck('visitor_counters.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($ids)) {
            return redirect()->route('visitor_counter.index', [
                'date' => $date,
                'preset' => $datePreset !== 'custom' ? $datePreset : null,
                'branch_id' => $branchId > 0 ? $branchId : null,
                'q' => $keyword !== '' ? $keyword : null,
                'active_only' => 1,
                'per_page' => $perPage,
            ])->with('success', 'Tidak ada visitor aktif untuk di-checkout.');
        }

        VisitorCounter::query()
            ->where('institution_id', $institutionId)
            ->when($allowedBranchId !== null, fn ($q) => $q->where('branch_id', $allowedBranchId))
            ->whereIn('id', $ids)
            ->whereNull('checkout_at')
            ->update([
                'checkout_at' => now(),
                'updated_at' => now(),
            ]);

        $this->writeAudit('visitor_counter.checkout_bulk', VisitorCounter::class, null, [
            'updated_count' => count($ids),
            'date' => $date,
            'preset' => $datePreset,
            'branch_id' => $branchId > 0 ? $branchId : null,
            'keyword' => $keyword !== '' ? $keyword : null,
        ]);

        return redirect()->route('visitor_counter.index', [
            'date' => $date,
            'preset' => $datePreset !== 'custom' ? $datePreset : null,
            'branch_id' => $branchId > 0 ? $branchId : null,
            'q' => $keyword !== '' ? $keyword : null,
            'per_page' => $perPage,
        ])->with('success', 'Checkout massal berhasil dijalankan.');
    }

    public function checkoutSelected(Request $request)
    {
        $institutionId = $this->institutionId();
        $allowedBranchId = $this->branchScope();
        $rawDate = trim((string) $request->input('date', ''));
        $rawPreset = trim((string) $request->input('preset', 'custom'));
        [$date, , , $datePreset] = $this->resolveDateFilter($rawDate, $rawPreset);
        $branchId = $this->normalizeBranchFilter((int) $request->input('branch_id', 0), $allowedBranchId);
        $keyword = trim((string) $request->input('q', ''));
        $activeOnly = $request->boolean('active_only');
        $perPage = (int) $request->input('per_page', 20);

        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return redirect()->route('visitor_counter.index', [
                'date' => $date,
                'preset' => $datePreset !== 'custom' ? $datePreset : null,
                'branch_id' => $branchId > 0 ? $branchId : null,
                'q' => $keyword !== '' ? $keyword : null,
                'active_only' => $activeOnly ? 1 : null,
                'per_page' => $perPage,
            ])->with('success', 'Tidak ada baris yang dipilih.');
        }

        $updated = VisitorCounter::query()
            ->where('institution_id', $institutionId)
            ->when($allowedBranchId !== null, fn ($q) => $q->where('branch_id', $allowedBranchId))
            ->whereIn('id', $ids)
            ->whereNull('checkout_at')
            ->update([
                'checkout_at' => now(),
                'updated_at' => now(),
            ]);

        $this->writeAudit('visitor_counter.checkout_selected', VisitorCounter::class, null, [
            'selected_count' => count($ids),
            'updated_count' => $updated,
            'date' => $date,
            'preset' => $datePreset,
            'branch_id' => $branchId > 0 ? $branchId : null,
            'keyword' => $keyword !== '' ? $keyword : null,
            'active_only' => $activeOnly,
        ]);

        return redirect()->route('visitor_counter.index', [
            'date' => $date,
            'preset' => $datePreset !== 'custom' ? $datePreset : null,
            'branch_id' => $branchId > 0 ? $branchId : null,
            'q' => $keyword !== '' ? $keyword : null,
            'active_only' => $activeOnly ? 1 : null,
            'per_page' => $perPage,
        ])->with('success', $updated > 0
            ? 'Checkout terpilih berhasil (' . $updated . ' baris).'
            : 'Baris terpilih sudah checkout semua.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $institutionId = $this->institutionId();
        $allowedBranchId = $this->branchScope();
        $rawDate = trim((string) $request->query('date', ''));
        $rawPreset = trim((string) $request->query('preset', 'custom'));
        [$date, $dateFrom, $dateTo, ] = $this->resolveDateFilter($rawDate, $rawPreset);
        $branchId = $this->normalizeBranchFilter((int) $request->query('branch_id', 0), $allowedBranchId);
        $keyword = trim((string) $request->query('q', ''));
        $activeOnly = $request->boolean('active_only');

        $rows = $this->buildFilteredBaseQuery($institutionId, $dateFrom, $dateTo, $branchId, $keyword, $activeOnly)
            ->select([
                'visitor_counters.visitor_type',
                'visitor_counters.visitor_name',
                'visitor_counters.member_code_snapshot',
                'visitor_counters.purpose',
                'visitor_counters.checkin_at',
                'visitor_counters.checkout_at',
                'visitor_counters.notes',
                'm.full_name as member_name',
                'm.member_code as member_code',
                'b.name as branch_name',
            ])
            ->orderByDesc('visitor_counters.checkin_at')
            ->get();

        $filename = 'visitor-counter-' . $date . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'checkin_at',
                'checkout_at',
                'visitor_type',
                'name',
                'member_code',
                'purpose',
                'branch',
                'notes',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    (string) $row->checkin_at,
                    (string) ($row->checkout_at ?? ''),
                    (string) $row->visitor_type,
                    (string) ($row->visitor_name ?: ($row->member_name ?: '')),
                    (string) ($row->member_code_snapshot ?: ($row->member_code ?: '')),
                    (string) ($row->purpose ?? ''),
                    (string) ($row->branch_name ?? ''),
                    (string) ($row->notes ?? ''),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(Request $request)
    {
        $institutionId = $this->institutionId();
        $allowedBranchId = $this->branchScope();

        $data = $request->validate([
            'visitor_type' => ['required', Rule::in(['member', 'non_member'])],
            'member_code' => ['nullable', 'required_if:visitor_type,member', 'string', 'max:80'],
            'visitor_name' => ['nullable', 'required_if:visitor_type,non_member', 'string', 'max:160'],
            'purpose' => ['nullable', 'string', 'max:160'],
            'branch_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ]);

        $branchId = (int) ($data['branch_id'] ?? 0);
        $this->ensureRequestedBranchAllowed($branchId, $allowedBranchId);
        if ($allowedBranchId !== null) {
            $branchId = $allowedBranchId;
        }
        if ($branchId > 0 && Schema::hasTable('branches')) {
            $validBranch = DB::table('branches')
                ->where('institution_id', $institutionId)
                ->where('id', $branchId)
                ->exists();
            if (!$validBranch) {
                return back()->withInput()->withErrors([
                    'branch_id' => 'Cabang tidak valid.',
                ]);
            }
        }

        $memberId = null;
        $memberCodeSnapshot = null;
        $visitorName = trim((string) ($data['visitor_name'] ?? ''));
        $memberCode = trim((string) ($data['member_code'] ?? ''));

        if ($data['visitor_type'] === 'member') {
            $member = Member::query()
                ->where('institution_id', $institutionId)
                ->where('member_code', $memberCode)
                ->first();

            if (!$member) {
                return back()->withInput()->withErrors([
                    'member_code' => 'Member tidak ditemukan.',
                ]);
            }

            $memberId = (int) $member->id;
            $memberCodeSnapshot = (string) $member->member_code;
            $visitorName = (string) ($member->full_name ?? $visitorName);
        }

        $row = VisitorCounter::query()->create([
            'institution_id' => $institutionId,
            'branch_id' => $branchId > 0 ? $branchId : null,
            'member_id' => $memberId,
            'visitor_type' => $data['visitor_type'],
            'visitor_name' => $visitorName !== '' ? $visitorName : null,
            'member_code_snapshot' => $memberCodeSnapshot,
            'purpose' => trim((string) ($data['purpose'] ?? '')) ?: null,
            'checkin_at' => now(),
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_by' => (int) (auth()->id() ?? 0) ?: null,
        ]);

        $this->writeAudit('visitor_counter.checkin', VisitorCounter::class, (int) $row->id, [
            'branch_id' => $branchId > 0 ? $branchId : null,
            'visitor_type' => (string) $row->visitor_type,
            'member_id' => $memberId,
            'purpose' => (string) ($row->purpose ?? ''),
        ]);

        return back()->with('success', 'Visitor check-in berhasil dicatat.');
    }

    public function checkout(int $id)
    {
        $institutionId = $this->institutionId();
        $allowedBranchId = $this->branchScope();
        $row = VisitorCounter::query()
            ->where('institution_id', $institutionId)
            ->when($allowedBranchId !== null, fn ($q) => $q->where('branch_id', $allowedBranchId))
            ->findOrFail($id);

        if ($row->checkout_at) {
            $this->writeAudit('visitor_counter.checkout_skipped', VisitorCounter::class, (int) $row->id, [
                'reason' => 'already_checked_out',
                'branch_id' => (int) ($row->branch_id ?? 0) ?: null,
            ]);
            return back()->with('success', 'Visitor sudah checkout.');
        }

        $row->checkout_at = now();
        $row->save();
        $this->writeAudit('visitor_counter.checkout', VisitorCounter::class, (int) $row->id, [
            'branch_id' => (int) ($row->branch_id ?? 0) ?: null,
            'checkout_at' => (string) $row->checkout_at,
        ]);

        return back()->with('success', 'Checkout visitor berhasil.');
    }

    public function undoCheckout(int $id)
    {
        $institutionId = $this->institutionId();
        $allowedBranchId = $this->branchScope();
        $row = VisitorCounter::query()
            ->where('institution_id', $institutionId)
            ->when($allowedBranchId !== null, fn ($q) => $q->where('branch_id', $allowedBranchId))
            ->findOrFail($id);

        if (!$row->checkout_at) {
            $this->writeAudit('visitor_counter.undo_checkout_skipped', VisitorCounter::class, (int) $row->id, [
                'reason' => 'not_checked_out',
                'branch_id' => (int) ($row->branch_id ?? 0) ?: null,
            ]);
            return back()->with('success', 'Visitor belum checkout.');
        }

        $maxUndoAt = now()->subMinutes(5);
        if ($row->checkout_at < $maxUndoAt) {
            $this->writeAudit('visitor_counter.undo_checkout_denied', VisitorCounter::class, (int) $row->id, [
                'reason' => 'window_expired',
                'branch_id' => (int) ($row->branch_id ?? 0) ?: null,
                'checkout_at' => (string) $row->checkout_at,
            ]);
            return back()->with('success', 'Batas undo checkout (5 menit) sudah lewat.');
        }

        $row->checkout_at = null;
        $row->save();
        $this->writeAudit('visitor_counter.undo_checkout', VisitorCounter::class, (int) $row->id, [
            'branch_id' => (int) ($row->branch_id ?? 0) ?: null,
        ]);

        return back()->with('success', 'Undo checkout berhasil.');
    }
}
