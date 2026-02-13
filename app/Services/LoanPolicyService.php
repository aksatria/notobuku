<?php

namespace App\Services;

class LoanPolicyService
{
    public function resolveMemberRole(?object $member): string
    {
        if (!$member) return 'member';

        foreach (['member_type', 'type', 'role'] as $col) {
            if (isset($member->{$col}) && is_string($member->{$col}) && $member->{$col} !== '') {
                return strtolower(trim((string)$member->{$col}));
            }
        }

        return 'member';
    }

    public function forRole(?string $role): array
    {
        $base = config('notobuku.loans', []);
        $roles = $base['roles'] ?? [];
        $roleKey = $role ?: 'member';

        $roleConf = $roles[$roleKey] ?? [];

        return [
            'default_days' => (int)($roleConf['default_days'] ?? ($base['default_days'] ?? 7)),
            'max_items' => (int)($roleConf['max_items'] ?? ($base['max_items'] ?? 3)),
            'max_renewals' => (int)($roleConf['max_renewals'] ?? ($base['max_renewals'] ?? 2)),
            'extend_days' => (int)($roleConf['extend_days'] ?? ($base['extend_days'] ?? 7)),
            'role' => $roleKey,
        ];
    }
}
