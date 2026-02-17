<?php

namespace App\Services;

class LoanPolicyService
{
    public function __construct(
        private readonly CirculationPolicyEngine $engine
    ) {
    }

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
            'fine_rate_per_day' => (int)($roleConf['fine_rate_per_day'] ?? ($base['fine_rate_per_day'] ?? 1000)),
            'grace_days' => (int)($roleConf['grace_days'] ?? ($base['grace_days'] ?? 0)),
            'can_renew_if_reserved' => (bool)($roleConf['can_renew_if_reserved'] ?? ($base['can_renew_if_reserved'] ?? false)),
            'role' => $roleKey,
            'source' => 'config',
            'rule_id' => null,
            'rule_name' => null,
        ];
    }

    public function forContext(
        int $institutionId,
        ?int $branchId,
        ?string $memberType,
        ?string $collectionType = null
    ): array {
        $role = $memberType ?: 'member';
        $policy = $this->engine->resolvePolicy($institutionId, $branchId, $memberType, $collectionType);

        if (($policy['source'] ?? 'fallback') === 'fallback') {
            $cfg = $this->forRole($role);
            $policy['default_days'] = (int) ($cfg['default_days'] ?? 7);
            $policy['max_items'] = (int) ($cfg['max_items'] ?? 3);
            $policy['max_renewals'] = (int) ($cfg['max_renewals'] ?? 2);
            $policy['extend_days'] = (int) ($cfg['extend_days'] ?? 7);
            $policy['fine_rate_per_day'] = max(0, (int) ($policy['fine_rate_per_day'] ?? ($cfg['fine_rate_per_day'] ?? 1000)));
            $policy['grace_days'] = max(0, (int) ($cfg['grace_days'] ?? 0));
            $policy['can_renew_if_reserved'] = (bool) ($cfg['can_renew_if_reserved'] ?? false);
            $policy['source'] = 'config';
        }

        $policy['role'] = $role;
        return $policy;
    }
}
