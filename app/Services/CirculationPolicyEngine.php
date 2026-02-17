<?php

namespace App\Services;

use App\Models\CirculationLoanPolicyRule;
use App\Models\CirculationServiceCalendar;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CirculationPolicyEngine
{
    public function resolvePolicy(
        int $institutionId,
        ?int $branchId,
        ?string $memberType,
        ?string $collectionType = null
    ): array {
        $memberType = $this->norm($memberType);
        $collectionType = $this->norm($collectionType);

        $fallback = [
            'max_items' => 3,
            'default_days' => 7,
            'extend_days' => 7,
            'max_renewals' => 2,
            'fine_rate_per_day' => $this->defaultFineRatePerDay($institutionId),
            'grace_days' => 0,
            'can_renew_if_reserved' => false,
            'source' => 'fallback',
            'rule_id' => null,
            'rule_name' => null,
        ];

        if (!Schema::hasTable('circulation_loan_policy_rules')) {
            return $fallback;
        }

        $q = CirculationLoanPolicyRule::query()
            ->where('is_active', true)
            ->where(function ($w) use ($institutionId) {
                $w->whereNull('institution_id')->orWhere('institution_id', $institutionId);
            })
            ->when($branchId && $branchId > 0, function ($w) use ($branchId) {
                $w->where(function ($x) use ($branchId) {
                    $x->whereNull('branch_id')->orWhere('branch_id', $branchId);
                });
            }, function ($w) {
                $w->whereNull('branch_id');
            })
            ->where(function ($w) use ($memberType) {
                if ($memberType !== null) {
                    $w->whereNull('member_type')->orWhereRaw('LOWER(member_type) = ?', [$memberType]);
                    return;
                }
                $w->whereNull('member_type');
            })
            ->where(function ($w) use ($collectionType) {
                if ($collectionType !== null) {
                    $w->whereNull('collection_type')->orWhereRaw('LOWER(collection_type) = ?', [$collectionType]);
                    return;
                }
                $w->whereNull('collection_type');
            })
            ->orderByDesc('priority')
            ->orderByRaw('CASE WHEN institution_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN branch_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN member_type IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN collection_type IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByDesc('id');

        $rule = $q->first();
        if (!$rule) {
            return $fallback;
        }

        return [
            'max_items' => max(1, (int) $rule->max_items),
            'default_days' => max(1, (int) $rule->default_days),
            'extend_days' => max(1, (int) $rule->extend_days),
            'max_renewals' => max(0, (int) $rule->max_renewals),
            'fine_rate_per_day' => max(0, (int) $rule->fine_rate_per_day),
            'grace_days' => max(0, (int) $rule->grace_days),
            'can_renew_if_reserved' => (bool) $rule->can_renew_if_reserved,
            'source' => 'rule_matrix',
            'rule_id' => (int) $rule->id,
            'rule_name' => (string) ($rule->name ?? ''),
        ];
    }

    public function computeDueAtByBusinessDays(
        int $institutionId,
        ?int $branchId,
        int $days,
        ?Carbon $from = null,
        ?bool $excludeWeekends = null
    ): Carbon {
        $days = max(1, $days);
        $cursor = ($from ?: now())->copy();
        $remaining = $days;

        while ($remaining > 0) {
            $cursor->addDay();
            if ($this->isClosedDay($institutionId, $branchId, $cursor, $excludeWeekends)) {
                continue;
            }
            $remaining--;
        }

        return $cursor;
    }

    public function elapsedLateDays(
        int $institutionId,
        ?int $branchId,
        ?string $dueAt,
        ?string $returnedAt = null,
        int $graceDays = 0,
        ?bool $excludeWeekends = null
    ): int {
        if (!$dueAt) {
            return 0;
        }

        try {
            $due = Carbon::parse($dueAt);
        } catch (\Throwable $e) {
            return 0;
        }

        try {
            $end = $returnedAt ? Carbon::parse($returnedAt) : now();
        } catch (\Throwable $e) {
            $end = now();
        }

        if ($end->lessThanOrEqualTo($due)) {
            return 0;
        }

        $days = 0;
        $cursor = $due->copy();
        while ($cursor->lt($end)) {
            $cursor->addDay();
            if ($this->isClosedDay($institutionId, $branchId, $cursor, $excludeWeekends)) {
                continue;
            }
            $days++;
        }

        return max(0, $days - max(0, $graceDays));
    }

    public function isClosedDay(
        int $institutionId,
        ?int $branchId,
        Carbon $date,
        ?bool $excludeWeekends = null
    ): bool {
        $calendar = $this->resolveCalendar($institutionId, $branchId);
        $excludeWeekend = $excludeWeekends;
        if ($excludeWeekend === null) {
            $excludeWeekend = $calendar['exclude_weekends'] ?? (bool) config('notobuku.circulation.sla.exclude_weekends', true);
        }

        if ($excludeWeekend && $date->isWeekend()) {
            return true;
        }

        if (!$calendar['id']) {
            return false;
        }

        if (!Schema::hasTable('circulation_service_closures')) {
            return false;
        }

        $ymd = $date->toDateString();
        $md = $date->format('m-d');

        return DB::table('circulation_service_closures')
            ->where('calendar_id', (int) $calendar['id'])
            ->where(function ($w) use ($ymd, $md) {
                $w->where(function ($x) use ($ymd) {
                    $x->where('is_recurring_yearly', false)->whereDate('closed_on', $ymd);
                })->orWhere(function ($x) use ($md) {
                    $x->where('is_recurring_yearly', true)->whereRaw("DATE_FORMAT(closed_on, '%m-%d') = ?", [$md]);
                });
            })
            ->exists();
    }

    private function resolveCalendar(int $institutionId, ?int $branchId): array
    {
        if (!Schema::hasTable('circulation_service_calendars')) {
            return ['id' => null, 'exclude_weekends' => (bool) config('notobuku.circulation.sla.exclude_weekends', true)];
        }

        $q = CirculationServiceCalendar::query()
            ->where('is_active', true)
            ->where(function ($w) use ($institutionId) {
                $w->whereNull('institution_id')->orWhere('institution_id', $institutionId);
            });

        if ($branchId && $branchId > 0) {
            $q->where(function ($w) use ($branchId) {
                $w->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        } else {
            $q->whereNull('branch_id');
        }

        $row = $q
            ->orderByDesc('priority')
            ->orderByRaw('CASE WHEN institution_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN branch_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByDesc('id')
            ->first(['id', 'exclude_weekends']);

        if (!$row) {
            return ['id' => null, 'exclude_weekends' => (bool) config('notobuku.circulation.sla.exclude_weekends', true)];
        }

        return ['id' => (int) $row->id, 'exclude_weekends' => (bool) $row->exclude_weekends];
    }

    private function defaultFineRatePerDay(int $institutionId): int
    {
        $rate = 1000;
        if (!Schema::hasTable('institutions') || !Schema::hasColumn('institutions', 'fine_rate_per_day')) {
            return $rate;
        }

        try {
            $val = DB::table('institutions')->where('id', $institutionId)->value('fine_rate_per_day');
            if (is_numeric($val) && (int) $val > 0) {
                $rate = (int) $val;
            }
        } catch (\Throwable $e) {
            return $rate;
        }

        return $rate;
    }

    private function norm(?string $value): ?string
    {
        $v = strtolower(trim((string) $value));
        return $v !== '' ? $v : null;
    }
}
