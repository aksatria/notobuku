<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarcPolicySet;
use App\Services\MarcPolicyService;
use Illuminate\Http\Request;

class MarcPolicyApiController extends Controller
{
    public function index(Request $request, MarcPolicyService $policyService)
    {
        $institutionId = (int) (auth()->user()->institution_id ?? 0) ?: null;

        $draft = MarcPolicySet::query()
            ->where('status', 'draft')
            ->when($institutionId === null, fn($q) => $q->whereNull('institution_id'))
            ->when($institutionId !== null, fn($q) => $q->where('institution_id', $institutionId))
            ->orderByDesc('version')
            ->first();

        $published = MarcPolicySet::query()
            ->where('status', 'published')
            ->when($institutionId === null, fn($q) => $q->whereNull('institution_id'))
            ->when($institutionId !== null, fn($q) => $q->where('institution_id', $institutionId))
            ->orderByDesc('version')
            ->first();

        $default = $policyService->getActivePolicy($institutionId);

        return response()->json([
            'ok' => true,
            'data' => [
                'draft' => $draft ? $draft->toArray() : null,
                'published' => $published ? $published->toArray() : null,
                'default' => $default,
            ],
        ]);
    }

    public function draft(Request $request, MarcPolicyService $policyService)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'payload' => ['required', 'array'],
        ]);

        $errors = $policyService->validatePayload($data['payload']);
        if (!empty($errors)) {
            return response()->json([
                'ok' => false,
                'message' => 'Policy payload tidak valid.',
                'errors' => $errors,
            ], 422);
        }

        $name = trim((string) ($data['name'] ?? 'RDA Core'));
        if ($name === '') $name = 'RDA Core';

        $institutionId = (int) (auth()->user()->institution_id ?? 0) ?: null;
        $policy = $policyService->createDraftPolicy($name, $data['payload'], $institutionId, auth()->user()?->id);

        return response()->json([
            'ok' => true,
            'data' => $policy,
        ]);
    }

    public function publish(Request $request, MarcPolicyService $policyService)
    {
        $data = $request->validate([
            'policy_id' => ['required', 'integer'],
        ]);

        $policy = MarcPolicySet::query()->findOrFail((int) $data['policy_id']);
        $institutionId = (int) (auth()->user()->institution_id ?? 0) ?: null;
        if ($institutionId !== ($policy->institution_id ?? null)) {
            return response()->json([
                'ok' => false,
                'message' => 'Tidak berhak publish policy ini.',
            ], 403);
        }
        $policyService->publishPolicy($policy->id, auth()->user()?->id);

        return response()->json([
            'ok' => true,
            'data' => $policy->fresh(),
        ]);
    }

    public function audits(Request $request)
    {
        $query = $this->buildAuditQuery($request);
        $perPage = $this->sanitizePerPage($request->query('per_page'));
        $page = max(1, (int) $request->query('page', 1));

        $audits = $query->paginate($perPage, ['*'], 'page', $page);
        $items = collect($audits->items());
        $payload = $this->mapAuditRows($items);

        return response()->json([
            'ok' => true,
            'data' => $payload,
            'meta' => [
                'page' => $audits->currentPage(),
                'per_page' => $audits->perPage(),
                'total' => $audits->total(),
                'last_page' => $audits->lastPage(),
            ],
        ]);
    }

    public function auditsCsv(Request $request)
    {
        $query = $this->buildAuditQuery($request);
        $limit = $this->sanitizeCsvLimit($request->query('limit'));
        $rows = $this->mapAuditRows($query->orderByDesc('id')->limit($limit)->get());

        $selected = $this->resolveCsvColumns($request);
        $headers = $selected;

        return response()->streamDownload(function () use ($rows, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
                $flat = [
                    'id' => $row['id'] ?? '',
                    'action' => $row['action'] ?? '',
                    'status' => $row['status'] ?? '',
                    'user_id' => $row['user_id'] ?? '',
                    'user_name' => $row['user_name'] ?? '',
                    'user_email' => $row['user_email'] ?? '',
                    'user_role' => $row['user_role'] ?? '',
                    'policy_id' => $meta['policy_id'] ?? '',
                    'policy_name' => $meta['name'] ?? '',
                    'policy_version' => $meta['version'] ?? '',
                    'institution_id' => $meta['institution_id'] ?? '',
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
                $line = [];
                foreach ($headers as $h) {
                    $line[] = $flat[$h] ?? '';
                }
                fputcsv($handle, $line);
            }
            fclose($handle);
        }, 'marc_policy_audit.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildAuditQuery(Request $request)
    {
        $user = auth()->user();
        $institutionId = (int) ($user?->institution_id ?? 0) ?: null;
        $requestedInstitution = $request->query('institution_id');
        $includeGlobal = $request->query('include_global', '1') !== '0';

        if ($user?->isSuperAdmin() && $requestedInstitution !== null) {
            $institutionId = (int) $requestedInstitution ?: null;
        }

        $auditsQuery = \App\Models\AuditLog::query()->where('format', 'marc_policy');
        if ($institutionId === null) {
            $auditsQuery->whereNull('meta->institution_id');
        } else {
            $auditsQuery->where(function ($q) use ($institutionId, $includeGlobal) {
                if ($includeGlobal) {
                    $q->whereNull('meta->institution_id')
                      ->orWhere('meta->institution_id', $institutionId);
                } else {
                    $q->where('meta->institution_id', $institutionId);
                }
            });
        }

        $start = $this->parseDate($request->query('start_date'));
        $end = $this->parseDate($request->query('end_date'));
        if ($start) {
            $auditsQuery->whereDate('created_at', '>=', $start);
        }
        if ($end) {
            $auditsQuery->whereDate('created_at', '<=', $end);
        }
        $action = trim((string) $request->query('action', ''));
        if ($action !== '') {
            $auditsQuery->where('action', $action);
        }
        $status = trim((string) $request->query('status', ''));
        if ($status !== '') {
            $auditsQuery->where('status', $status);
        }

        return $auditsQuery;
    }

    private function mapAuditRows($audits)
    {
        $items = collect($audits);
        $userIds = $items->pluck('user_id')->filter()->unique()->values();
        $users = $userIds->isEmpty()
            ? collect()
            : \App\Models\User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        return $items->map(function ($row) use ($users) {
            $u = $users[$row->user_id] ?? null;
            return [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'user_name' => $u?->name,
                'user_email' => $u?->email,
                'user_role' => $u?->role,
                'action' => $row->action,
                'format' => $row->format,
                'status' => $row->status,
                'meta' => $row->meta,
                'created_at' => $row->created_at,
            ];
        })->values();
    }

    private function sanitizePerPage($value): int
    {
        $v = (int) $value;
        if ($v <= 0) return 20;
        if ($v > 100) return 100;
        return $v;
    }

    private function resolveCsvColumns(Request $request): array
    {
        $allowed = [
            'id', 'action', 'status', 'user_id', 'user_name', 'user_email',
            'user_role', 'policy_id', 'policy_name', 'policy_version',
            'institution_id', 'created_at',
        ];
        $cols = $request->query('columns');
        if (is_array($cols)) {
            $parts = array_values(array_filter($cols, fn($c) => is_string($c) && in_array($c, $allowed, true)));
            if (!empty($parts)) {
                return $parts;
            }
        } elseif (is_string($cols) && trim($cols) !== '') {
            $parts = array_map('trim', explode(',', $cols));
            $parts = array_values(array_filter($parts, fn($c) => in_array($c, $allowed, true)));
            if (!empty($parts)) {
                return $parts;
            }
        }
        return $allowed;
    }

    private function sanitizeCsvLimit($value): int
    {
        $v = (int) $value;
        if ($v <= 0) return 200;
        if ($v > 1000) return 1000;
        return $v;
    }

    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $ts = strtotime($value);
        if ($ts === false) return null;
        return date('Y-m-d', $ts);
    }
}
