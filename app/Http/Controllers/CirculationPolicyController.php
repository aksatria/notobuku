<?php

namespace App\Http\Controllers;

use App\Models\CirculationLoanPolicyRule;
use App\Models\CirculationServiceCalendar;
use App\Models\CirculationServiceClosure;
use App\Services\CirculationPolicyEngine;
use App\Services\LoanPolicyService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CirculationPolicyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $institutionId = (int) ($user->institution_id ?? 0);

        $rules = CirculationLoanPolicyRule::query()
            ->where(function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId)->orWhereNull('institution_id');
            })
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        $calendars = CirculationServiceCalendar::query()
            ->where(function ($q) use ($institutionId) {
                $q->where('institution_id', $institutionId)->orWhereNull('institution_id');
            })
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        $calendarIds = $calendars->pluck('id')->map(fn ($v) => (int) $v)->all();
        $closures = collect();
        if (!empty($calendarIds)) {
            $closures = CirculationServiceClosure::query()
                ->whereIn('calendar_id', $calendarIds)
                ->orderByDesc('closed_on')
                ->orderByDesc('id')
                ->limit(300)
                ->get();
        }

        $branches = DB::table('branches')
            ->where('institution_id', $institutionId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('transaksi.policies.index', [
            'title' => 'Kebijakan Sirkulasi',
            'rules' => $rules,
            'calendars' => $calendars,
            'closures' => $closures,
            'branches' => $branches,
            'simulation' => session('circulation_policy_simulation'),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $institutionId = (int) ($request->user()?->institution_id ?? 0);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer'],
            'member_type' => ['nullable', 'string', 'max:60'],
            'collection_type' => ['nullable', 'string', 'max:60'],
            'max_items' => ['required', 'integer', 'min:1', 'max:99'],
            'default_days' => ['required', 'integer', 'min:1', 'max:365'],
            'extend_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_renewals' => ['required', 'integer', 'min:0', 'max:20'],
            'fine_rate_per_day' => ['required', 'integer', 'min:0', 'max:10000000'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'can_renew_if_reserved' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        CirculationLoanPolicyRule::create([
            'institution_id' => $institutionId,
            'branch_id' => $this->nullableInt($data['branch_id'] ?? null),
            'member_type' => $this->nullableStr($data['member_type'] ?? null),
            'collection_type' => $this->nullableStr($data['collection_type'] ?? null),
            'max_items' => (int) $data['max_items'],
            'default_days' => (int) $data['default_days'],
            'extend_days' => (int) $data['extend_days'],
            'max_renewals' => (int) $data['max_renewals'],
            'fine_rate_per_day' => (int) $data['fine_rate_per_day'],
            'grace_days' => (int) ($data['grace_days'] ?? 0),
            'can_renew_if_reserved' => (bool) ($data['can_renew_if_reserved'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'priority' => (int) ($data['priority'] ?? 0),
            'name' => $this->nullableStr($data['name'] ?? null),
        ]);

        return redirect()->route('transaksi.policies.index')->with('success', 'Rule policy berhasil ditambahkan.');
    }

    public function updateRule(Request $request, int $id): RedirectResponse
    {
        $institutionId = (int) ($request->user()?->institution_id ?? 0);
        $rule = CirculationLoanPolicyRule::query()->where('institution_id', $institutionId)->findOrFail($id);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer'],
            'member_type' => ['nullable', 'string', 'max:60'],
            'collection_type' => ['nullable', 'string', 'max:60'],
            'max_items' => ['required', 'integer', 'min:1', 'max:99'],
            'default_days' => ['required', 'integer', 'min:1', 'max:365'],
            'extend_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_renewals' => ['required', 'integer', 'min:0', 'max:20'],
            'fine_rate_per_day' => ['required', 'integer', 'min:0', 'max:10000000'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'can_renew_if_reserved' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $rule->update([
            'branch_id' => $this->nullableInt($data['branch_id'] ?? null),
            'member_type' => $this->nullableStr($data['member_type'] ?? null),
            'collection_type' => $this->nullableStr($data['collection_type'] ?? null),
            'max_items' => (int) $data['max_items'],
            'default_days' => (int) $data['default_days'],
            'extend_days' => (int) $data['extend_days'],
            'max_renewals' => (int) $data['max_renewals'],
            'fine_rate_per_day' => (int) $data['fine_rate_per_day'],
            'grace_days' => (int) ($data['grace_days'] ?? 0),
            'can_renew_if_reserved' => (bool) ($data['can_renew_if_reserved'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'priority' => (int) ($data['priority'] ?? 0),
            'name' => $this->nullableStr($data['name'] ?? null),
        ]);

        return redirect()->route('transaksi.policies.index')->with('success', 'Rule policy berhasil diperbarui.');
    }

    public function deleteRule(Request $request, int $id): RedirectResponse
    {
        $institutionId = (int) ($request->user()?->institution_id ?? 0);
        $rule = CirculationLoanPolicyRule::query()->where('institution_id', $institutionId)->findOrFail($id);
        $rule->delete();

        return redirect()->route('transaksi.policies.index')->with('success', 'Rule policy berhasil dihapus.');
    }

    public function storeCalendar(Request $request): RedirectResponse
    {
        $institutionId = (int) ($request->user()?->institution_id ?? 0);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer'],
            'exclude_weekends' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        CirculationServiceCalendar::create([
            'institution_id' => $institutionId,
            'branch_id' => $this->nullableInt($data['branch_id'] ?? null),
            'name' => trim((string) $data['name']),
            'exclude_weekends' => (bool) ($data['exclude_weekends'] ?? true),
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('transaksi.policies.index')->with('success', 'Kalender layanan berhasil ditambahkan.');
    }

    public function storeClosure(Request $request): RedirectResponse
    {
        $institutionId = (int) ($request->user()?->institution_id ?? 0);
        $data = $request->validate([
            'calendar_id' => ['required', 'integer'],
            'closed_on' => ['required', 'date'],
            'is_recurring_yearly' => ['nullable', 'boolean'],
            'label' => ['nullable', 'string', 'max:160'],
        ]);

        $calendar = CirculationServiceCalendar::query()
            ->where('institution_id', $institutionId)
            ->findOrFail((int) $data['calendar_id']);

        CirculationServiceClosure::updateOrCreate(
            [
                'calendar_id' => (int) $calendar->id,
                'closed_on' => $data['closed_on'],
                'is_recurring_yearly' => (bool) ($data['is_recurring_yearly'] ?? false),
            ],
            [
                'label' => $this->nullableStr($data['label'] ?? null),
            ]
        );

        return redirect()->route('transaksi.policies.index')->with('success', 'Hari libur/penutupan berhasil disimpan.');
    }

    public function deleteClosure(Request $request, int $id): RedirectResponse
    {
        $institutionId = (int) ($request->user()?->institution_id ?? 0);

        $closure = CirculationServiceClosure::query()->findOrFail($id);
        $calendar = CirculationServiceCalendar::query()->find((int) $closure->calendar_id);
        if (!$calendar || (int) ($calendar->institution_id ?? 0) !== $institutionId) {
            abort(403, 'Akses ditolak.');
        }

        $closure->delete();
        return redirect()->route('transaksi.policies.index')->with('success', 'Hari libur dihapus.');
    }

    public function simulate(Request $request, CirculationPolicyEngine $engine, LoanPolicyService $policyService): RedirectResponse
    {
        $institutionId = (int) ($request->user()?->institution_id ?? 0);
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'member_type' => ['nullable', 'string', 'max:60'],
            'collection_type' => ['nullable', 'string', 'max:60'],
            'action' => ['required', 'in:issue,renew,fine'],
            'base_date' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'returned_at' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $branchId = $this->nullableInt($data['branch_id'] ?? null);
        $memberType = $this->nullableStr($data['member_type'] ?? null);
        $collectionType = $this->nullableStr($data['collection_type'] ?? null);
        $policy = $policyService->forContext($institutionId, $branchId, $memberType, $collectionType);

        $action = (string) $data['action'];
        $days = (int) ($data['days'] ?? ($action === 'renew' ? ($policy['extend_days'] ?? 7) : ($policy['default_days'] ?? 7)));
        $base = !empty($data['base_date']) ? Carbon::parse((string) $data['base_date']) : now();

        $result = [
            'policy' => $policy,
            'action' => $action,
            'input' => [
                'branch_id' => $branchId,
                'member_type' => $memberType,
                'collection_type' => $collectionType,
                'days' => $days,
                'base_date' => $base->toDateString(),
                'due_at' => $data['due_at'] ?? null,
                'returned_at' => $data['returned_at'] ?? null,
            ],
            'output' => [],
        ];

        if ($action === 'issue' || $action === 'renew') {
            $due = $engine
                ->computeDueAtByBusinessDays($institutionId, $branchId, max(1, $days), $base)
                ->setTime(23, 59, 59)
                ->toDateTimeString();
            $result['output'] = ['computed_due_at' => $due];
        }

        if ($action === 'fine') {
            $daysLate = $engine->elapsedLateDays(
                $institutionId,
                $branchId,
                !empty($data['due_at']) ? (string) $data['due_at'] : null,
                !empty($data['returned_at']) ? (string) $data['returned_at'] : null,
                (int) ($policy['grace_days'] ?? 0)
            );
            $rate = (int) ($policy['fine_rate_per_day'] ?? 0);
            $result['output'] = [
                'days_late' => $daysLate,
                'fine_rate_per_day' => $rate,
                'fine_amount' => $daysLate * $rate,
            ];
        }

        return redirect()
            ->route('transaksi.policies.index')
            ->with('circulation_policy_simulation', $result)
            ->with('success', 'Simulasi policy selesai.');
    }

    private function nullableStr($value): ?string
    {
        $v = trim((string) $value);
        return $v !== '' ? $v : null;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (int) $value;
        return $n > 0 ? $n : null;
    }
}
