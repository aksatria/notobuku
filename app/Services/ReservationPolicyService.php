<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReservationPolicyService
{
    /**
     * Resolve rule paling spesifik.
     */
    public function resolveRule(int $institutionId, array $ctx = []): array
    {
        $defaults = [
            'id' => null,
            'label' => 'Default',
            'branch_id' => null,
            'member_type' => null,
            'collection_type' => null,
            'max_active_reservations' => (int) config('notobuku.reservations.rule_default.max_active_reservations', 5),
            'max_queue_per_biblio' => (int) config('notobuku.reservations.rule_default.max_queue_per_biblio', 30),
            'hold_hours' => (int) config('notobuku.reservations.rule_default.hold_hours', 48),
            'priority_weight' => 0,
            'is_enabled' => true,
        ];

        if (!Schema::hasTable('reservation_policy_rules')) {
            return $defaults;
        }

        $branchId = (int) ($ctx['branch_id'] ?? 0);
        $memberType = strtolower(trim((string) ($ctx['member_type'] ?? '')));
        $collectionType = strtolower(trim((string) ($ctx['collection_type'] ?? '')));

        $rule = DB::table('reservation_policy_rules')
            ->where('institution_id', $institutionId)
            ->where('is_enabled', 1)
            ->where(function ($q) use ($branchId) {
                if ($branchId > 0) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    return;
                }
                $q->whereNull('branch_id');
            })
            ->where(function ($q) use ($memberType) {
                if ($memberType !== '') {
                    $q->whereNull('member_type')->orWhereRaw('LOWER(member_type) = ?', [$memberType]);
                    return;
                }
                $q->whereNull('member_type');
            })
            ->where(function ($q) use ($collectionType) {
                if ($collectionType !== '') {
                    $q->whereNull('collection_type')->orWhereRaw('LOWER(collection_type) = ?', [$collectionType]);
                    return;
                }
                $q->whereNull('collection_type');
            })
            ->orderByRaw('CASE WHEN branch_id IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN member_type IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByRaw('CASE WHEN collection_type IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByDesc('id')
            ->first();

        if (!$rule) {
            return $defaults;
        }

        return array_merge($defaults, [
            'id' => (int) ($rule->id ?? 0),
            'label' => (string) ($rule->label ?? 'Rule #' . (int) ($rule->id ?? 0)),
            'branch_id' => $rule->branch_id !== null ? (int) $rule->branch_id : null,
            'member_type' => $rule->member_type !== null ? (string) $rule->member_type : null,
            'collection_type' => $rule->collection_type !== null ? (string) $rule->collection_type : null,
            'max_active_reservations' => max(1, (int) ($rule->max_active_reservations ?? $defaults['max_active_reservations'])),
            'max_queue_per_biblio' => max(1, (int) ($rule->max_queue_per_biblio ?? $defaults['max_queue_per_biblio'])),
            'hold_hours' => max(1, (int) ($rule->hold_hours ?? $defaults['hold_hours'])),
            'priority_weight' => (int) ($rule->priority_weight ?? 0),
            'is_enabled' => (bool) ($rule->is_enabled ?? true),
        ]);
    }

    public function resolvePriorityScore(array $ctx, array $rule): int
    {
        $weights = (array) config('notobuku.reservations.auto_priority.member_type_weights', []);
        $memberType = strtolower(trim((string) ($ctx['member_type'] ?? 'member')));
        $memberTypeWeight = (int) ($weights[$memberType] ?? 0);

        return (int) ($rule['priority_weight'] ?? 0) + $memberTypeWeight;
    }
}
