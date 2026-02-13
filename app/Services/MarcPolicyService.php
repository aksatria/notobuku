<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MarcPolicySet;

class MarcPolicyService
{
    public function getCurrentSchemaVersion(): int
    {
        return (int) config('marc.policy_schema_version', 1);
    }

    public function normalizePayload(array $payload): array
    {
        $current = $this->getCurrentSchemaVersion();
        $schema = (int) ($payload['schema_version'] ?? 0);
        if ($schema <= 0) {
            $payload['schema_version'] = $current;
            return $payload;
        }

        if ($schema < $current) {
            $payload = $this->migratePayload($payload, $schema, $current);
        }

        $payload['schema_version'] = $current;
        return $payload;
    }

    public function migratePayload(array $payload, int $fromVersion, int $toVersion): array
    {
        if ($fromVersion >= $toVersion) {
            $payload['schema_version'] = $toVersion;
            return $payload;
        }

        $migrations = $this->migrationSteps();
        $current = $fromVersion;
        while ($current < $toVersion) {
            $next = $current + 1;
            if (isset($migrations[$next]) && is_callable($migrations[$next])) {
                $payload = $migrations[$next]($payload);
            }
            $current = $next;
        }

        $payload['schema_version'] = $toVersion;
        return $payload;
    }

    public function validatePayload(array $payload): array
    {
        $errors = [];
        $payload = $this->normalizePayload($payload);
        if (!isset($payload['rules']) || !is_array($payload['rules'])) {
            $errors[] = 'Policy harus memiliki key "rules" berupa objek.';
            return $errors;
        }

        $allowedKeys = array_map('strtolower', (array) config('marc.policy_rule_keys', []));
        $allowedKeys = array_filter(array_map('trim', $allowedKeys));
        $allowedSev = ['warn', 'error'];

        foreach ($payload['rules'] as $key => $value) {
            $k = strtolower(trim((string) $key));
            $v = strtolower(trim((string) $value));
            if ($k === '' || !in_array($k, $allowedKeys, true)) {
                $errors[] = 'Rule key tidak dikenal: "' . $key . '".';
                continue;
            }
            if (!in_array($v, $allowedSev, true)) {
                $errors[] = 'Severity rule "' . $key . '" harus "warn" atau "error".';
            }
        }

        return $errors;
    }
    public function getActivePolicy(?int $institutionId = null): array
    {
        $policy = $this->getPublishedPolicy($institutionId);
        if (!empty($policy)) {
            return $this->normalizePayload($policy);
        }

        $policy = $this->getPublishedPolicy(null);
        if (!empty($policy)) {
            return $this->normalizePayload($policy);
        }

        return $this->normalizePayload((array) config('marc.policy_packs.rda_core', []));
    }

    public function createDraftPolicy(
        string $name,
        array $payload,
        ?int $institutionId = null,
        ?int $userId = null
    ): MarcPolicySet {
        $version = $this->nextVersion($institutionId, $name);
        $payload = $this->normalizePayload($payload);

        $policy = MarcPolicySet::query()->create([
            'institution_id' => $institutionId,
            'name' => $name,
            'version' => $version,
            'status' => 'draft',
            'payload_json' => $payload,
            'created_by' => $userId,
        ]);

        $this->logPolicy('marc_policy_draft', $policy, $userId);

        return $policy;
    }

    public function publishPolicy(int $policyId, ?int $userId = null): MarcPolicySet
    {
        $policy = MarcPolicySet::query()->findOrFail($policyId);
        $policy->status = 'published';
        $policy->approved_by = $userId;
        $policy->approved_at = now();
        $policy->save();

        $this->logPolicy('marc_policy_publish', $policy, $userId);

        return $policy;
    }

    private function getPublishedPolicy(?int $institutionId): array
    {
        $query = MarcPolicySet::query()->where('status', 'published');
        if ($institutionId === null) {
            $query->whereNull('institution_id');
        } else {
            $query->where('institution_id', $institutionId);
        }

        $row = $query->orderByDesc('version')->first();
        $payload = $row?->payload_json;

        return is_array($payload) ? $payload : [];
    }

    private function nextVersion(?int $institutionId, string $name): int
    {
        $query = MarcPolicySet::query()->where('name', $name);
        if ($institutionId === null) {
            $query->whereNull('institution_id');
        } else {
            $query->where('institution_id', $institutionId);
        }

        $current = (int) ($query->max('version') ?? 0);
        return $current + 1;
    }

    private function logPolicy(string $action, MarcPolicySet $policy, ?int $userId = null): void
    {
        try {
            AuditLog::create([
                'user_id' => $userId,
                'action' => $action,
                'format' => 'marc_policy',
                'status' => $policy->status,
                'meta' => [
                    'policy_id' => $policy->id,
                    'name' => $policy->name,
                    'version' => $policy->version,
                    'institution_id' => $policy->institution_id,
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore audit failures
        }
    }

    private function migrationSteps(): array
    {
        return [
            2 => function (array $payload): array {
                $rules = (array) ($payload['rules'] ?? []);
                if (array_key_exists('relator_unknown', $rules) && !array_key_exists('relator_uncontrolled', $rules)) {
                    $rules['relator_uncontrolled'] = $rules['relator_unknown'];
                }
                if (array_key_exists('relator_unknown', $rules)) {
                    unset($rules['relator_unknown']);
                }
                $payload['rules'] = $rules;
                return $payload;
            },
            3 => function (array $payload): array {
                $payload['rules'] = (array) ($payload['rules'] ?? []);
                return $payload;
            },
        ];
    }
}
