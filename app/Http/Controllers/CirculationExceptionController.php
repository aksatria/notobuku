<?php

namespace App\Http\Controllers;

use App\Support\CirculationSlaClock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CirculationExceptionController extends Controller
{
    public function index(Request $request)
    {
        $viewMode = $this->resolveViewMode($request);
        $prepared = $this->prepareRows($request);
        $rows = $prepared['rows'];
        $selectedDate = $prepared['selectedDate'];
        $dates = $prepared['dates'];
        $ackEnabled = $prepared['ackEnabled'];
        $sla = $prepared['sla'];
        $filters = $prepared['filters'];
        $owners = $prepared['owners'];

        return view('transaksi.exceptions', [
            'title' => 'Exception Operations',
            'dates' => $dates,
            'selectedDate' => $selectedDate,
            'rows' => $rows,
            'filters' => $filters,
            'ackEnabled' => $ackEnabled,
            'sla' => $sla,
            'owners' => $owners,
            'viewMode' => $viewMode,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $prepared = $this->prepareRows($request);
        $rows = $prepared['rows'];
        $selectedDate = (string) ($prepared['selectedDate'] ?? now()->toDateString());

        $filename = 'circulation-exceptions-' . $selectedDate . '-' . now()->format('His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'snapshot_date',
                'exception_type',
                'severity',
                'status',
                'age_hours',
                'loan_code',
                'loan_id',
                'loan_item_id',
                'item_id',
                'barcode',
                'member_id',
                'member_code',
                'detail',
                'ack_note',
                'ack_by',
                'ack_at',
                'resolved_by',
                'resolved_at',
                'owner',
                'owner_assigned_at',
                'detected_at',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    (string) ($row['snapshot_date'] ?? ''),
                    (string) ($row['exception_type'] ?? ''),
                    (string) ($row['severity'] ?? ''),
                    (string) ($row['status'] ?? 'open'),
                    (int) ($row['age_hours'] ?? 0),
                    (string) ($row['loan_code'] ?? ''),
                    (int) ($row['loan_id'] ?? 0),
                    (int) ($row['loan_item_id'] ?? 0),
                    (int) ($row['item_id'] ?? 0),
                    (string) ($row['barcode'] ?? ''),
                    (int) ($row['member_id'] ?? 0),
                    (string) ($row['member_code'] ?? ''),
                    (string) ($row['detail'] ?? ''),
                    (string) ($row['ack_note'] ?? ''),
                    (string) ($row['ack_by_name'] ?? ''),
                    (string) ($row['ack_at'] ?? ''),
                    (string) ($row['resolved_by_name'] ?? ''),
                    (string) ($row['resolved_at'] ?? ''),
                    (string) ($row['owner_name'] ?? ''),
                    (string) ($row['owner_assigned_at'] ?? ''),
                    (string) ($row['detected_at'] ?? ''),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportXlsx(Request $request): StreamedResponse
    {
        $prepared = $this->prepareRows($request);
        $rows = $prepared['rows'];
        $selectedDate = (string) ($prepared['selectedDate'] ?? now()->toDateString());

        $headers = [
            'snapshot_date',
            'exception_type',
            'severity',
            'status',
            'age_hours',
            'loan_code',
            'loan_id',
            'loan_item_id',
            'item_id',
            'barcode',
            'member_id',
            'member_code',
            'detail',
            'ack_note',
            'ack_by',
            'ack_at',
            'resolved_by',
            'resolved_at',
            'owner',
            'owner_assigned_at',
            'detected_at',
        ];
        $sheetRows = array_map(function (array $row) {
            return [
                (string) ($row['snapshot_date'] ?? ''),
                (string) ($row['exception_type'] ?? ''),
                (string) ($row['severity'] ?? ''),
                (string) ($row['status'] ?? 'open'),
                (int) ($row['age_hours'] ?? 0),
                (string) ($row['loan_code'] ?? ''),
                (int) ($row['loan_id'] ?? 0),
                (int) ($row['loan_item_id'] ?? 0),
                (int) ($row['item_id'] ?? 0),
                (string) ($row['barcode'] ?? ''),
                (int) ($row['member_id'] ?? 0),
                (string) ($row['member_code'] ?? ''),
                (string) ($row['detail'] ?? ''),
                (string) ($row['ack_note'] ?? ''),
                (string) ($row['ack_by_name'] ?? ''),
                (string) ($row['ack_at'] ?? ''),
                (string) ($row['resolved_by_name'] ?? ''),
                (string) ($row['resolved_at'] ?? ''),
                (string) ($row['owner_name'] ?? ''),
                (string) ($row['owner_assigned_at'] ?? ''),
                (string) ($row['detected_at'] ?? ''),
            ];
        }, $rows);

        $xlsxPath = $this->buildSimpleXlsx('CirculationExceptions', $headers, $sheetRows);
        $filename = 'circulation-exceptions-' . $selectedDate . '-' . now()->format('His') . '.xlsx';

        return response()->streamDownload(function () use ($xlsxPath) {
            $stream = fopen($xlsxPath, 'rb');
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
            @unlink($xlsxPath);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function assignOwner(Request $request)
    {
        if (!Schema::hasTable('circulation_exception_acknowledgements')) {
            return $this->redirectWithFilters($request)->with('error', 'Tabel acknowledgement exception belum tersedia.');
        }

        $data = $request->validate([
            'snapshot_date' => ['required', 'date'],
            'fingerprint' => ['required', 'string', 'size:40'],
            'owner_user_id' => ['required', 'integer', 'min:1'],
            'exception_type' => ['nullable', 'string', 'max:80'],
            'severity' => ['nullable', 'string', 'max:20'],
            'loan_id' => ['nullable', 'integer'],
            'loan_item_id' => ['nullable', 'integer'],
            'item_id' => ['nullable', 'integer'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'member_id' => ['nullable', 'integer'],
            'detail' => ['nullable', 'string', 'max:2000'],
        ]);

        $ownerUserId = (int) ($data['owner_user_id'] ?? 0);
        $institutionId = (int) ($request->user()->institution_id ?? 0);
        $ownerExists = DB::table('users')
            ->where('id', $ownerUserId)
            ->where('institution_id', $institutionId)
            ->whereIn('role', ['super_admin', 'admin', 'staff'])
            ->exists();
        if (!$ownerExists) {
            return $this->redirectWithFilters($request)->with('error', 'PIC tidak valid untuk institusi aktif.');
        }

        $base = [
            'institution_id' => $institutionId,
            'snapshot_date' => (string) $data['snapshot_date'],
            'fingerprint' => (string) $data['fingerprint'],
        ];
        $payload = [
            'exception_type' => (string) ($data['exception_type'] ?? ''),
            'severity' => (string) ($data['severity'] ?? ''),
            'loan_id' => !empty($data['loan_id']) ? (int) $data['loan_id'] : null,
            'loan_item_id' => !empty($data['loan_item_id']) ? (int) $data['loan_item_id'] : null,
            'item_id' => !empty($data['item_id']) ? (int) $data['item_id'] : null,
            'barcode' => (string) ($data['barcode'] ?? ''),
            'member_id' => !empty($data['member_id']) ? (int) $data['member_id'] : null,
            'owner_user_id' => $ownerUserId,
            'owner_assigned_at' => now(),
            'metadata' => json_encode([
                'detail' => (string) ($data['detail'] ?? ''),
            ], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];

        DB::table('circulation_exception_acknowledgements')->updateOrInsert(
            $base,
            array_merge(['created_at' => now()], $payload)
        );

        return $this->redirectWithFilters($request)->with('success', 'PIC exception berhasil ditetapkan.');
    }

    public function bulkAssignOwner(Request $request)
    {
        if (!Schema::hasTable('circulation_exception_acknowledgements')) {
            return $this->redirectWithFilters($request)->with('error', 'Tabel acknowledgement exception belum tersedia.');
        }

        $data = $request->validate([
            'snapshot_date' => ['required', 'date'],
            'fingerprints' => ['required', 'array', 'min:1'],
            'fingerprints.*' => ['required', 'string', 'size:40'],
            'owner_user_id' => ['required', 'integer', 'min:1'],
        ]);

        $snapshotDate = (string) $data['snapshot_date'];
        $fingerprints = array_values(array_unique(array_map('strval', (array) $data['fingerprints'])));
        $ownerUserId = (int) ($data['owner_user_id'] ?? 0);
        $institutionId = (int) ($request->user()->institution_id ?? 0);

        $ownerExists = DB::table('users')
            ->where('id', $ownerUserId)
            ->where('institution_id', $institutionId)
            ->whereIn('role', ['super_admin', 'admin', 'staff'])
            ->exists();
        if (!$ownerExists) {
            return $this->redirectWithFilters($request)->with('error', 'PIC bulk tidak valid untuk institusi aktif.');
        }

        $filePath = $this->snapshotPathForDate($snapshotDate);
        if ($filePath === '') {
            return $this->redirectWithFilters($request)->with('error', 'File snapshot untuk tanggal tersebut tidak ditemukan.');
        }

        $rows = $this->loadSnapshotRows($filePath, $snapshotDate);
        $map = [];
        foreach ($rows as $row) {
            $fp = (string) ($row['fingerprint'] ?? '');
            if ($fp !== '') {
                $map[$fp] = $row;
            }
        }

        $updated = 0;
        foreach ($fingerprints as $fp) {
            $row = $map[$fp] ?? null;
            if (!$row) {
                continue;
            }

            $base = [
                'institution_id' => $institutionId,
                'snapshot_date' => $snapshotDate,
                'fingerprint' => $fp,
            ];

            $payload = [
                'exception_type' => (string) ($row['exception_type'] ?? ''),
                'severity' => (string) ($row['severity'] ?? ''),
                'loan_id' => (int) ($row['loan_id'] ?? 0) ?: null,
                'loan_item_id' => (int) ($row['loan_item_id'] ?? 0) ?: null,
                'item_id' => (int) ($row['item_id'] ?? 0) ?: null,
                'barcode' => (string) ($row['barcode'] ?? ''),
                'member_id' => (int) ($row['member_id'] ?? 0) ?: null,
                'owner_user_id' => $ownerUserId,
                'owner_assigned_at' => now(),
                'metadata' => json_encode([
                    'detail' => (string) ($row['detail'] ?? ''),
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ];

            DB::table('circulation_exception_acknowledgements')->updateOrInsert(
                $base,
                array_merge(['created_at' => now()], $payload)
            );
            $updated++;
        }

        if ($updated <= 0) {
            return $this->redirectWithFilters($request)->with('error', 'Tidak ada item valid untuk bulk assign PIC.');
        }

        return $this->redirectWithFilters($request)->with('success', 'Bulk assign PIC berhasil: ' . $updated . ' item.');
    }

    public function acknowledge(Request $request)
    {
        return $this->applyStatus($request, 'ack');
    }

    public function resolve(Request $request)
    {
        return $this->applyStatus($request, 'resolved');
    }

    public function bulkUpdate(Request $request)
    {
        if (!Schema::hasTable('circulation_exception_acknowledgements')) {
            return $this->redirectWithFilters($request)->with('error', 'Tabel acknowledgement exception belum tersedia.');
        }

        $data = $request->validate([
            'snapshot_date' => ['required', 'date'],
            'fingerprints' => ['required', 'array', 'min:1'],
            'fingerprints.*' => ['required', 'string', 'size:40'],
            'bulk_action' => ['required', 'in:ack,resolved'],
            'ack_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $snapshotDate = (string) $data['snapshot_date'];
        $fingerprints = array_values(array_unique(array_map('strval', (array) $data['fingerprints'])));
        $targetStatus = (string) $data['bulk_action'];
        $note = trim((string) ($data['ack_note'] ?? ''));

        $filePath = $this->snapshotPathForDate($snapshotDate);
        if ($filePath === '') {
            return $this->redirectWithFilters($request)->with('error', 'File snapshot untuk tanggal tersebut tidak ditemukan.');
        }

        $rows = $this->loadSnapshotRows($filePath, $snapshotDate);
        $map = [];
        foreach ($rows as $row) {
            $fp = (string) ($row['fingerprint'] ?? '');
            if ($fp !== '') {
                $map[$fp] = $row;
            }
        }

        $institutionId = (int) ($request->user()->institution_id ?? 0);
        $userId = (int) ($request->user()->id ?? 0);
        $updated = 0;

        foreach ($fingerprints as $fp) {
            $row = $map[$fp] ?? null;
            if (!$row) {
                continue;
            }

            $base = [
                'institution_id' => $institutionId,
                'snapshot_date' => $snapshotDate,
                'fingerprint' => $fp,
            ];

            $payload = [
                'exception_type' => (string) ($row['exception_type'] ?? ''),
                'severity' => (string) ($row['severity'] ?? ''),
                'loan_id' => (int) ($row['loan_id'] ?? 0) ?: null,
                'loan_item_id' => (int) ($row['loan_item_id'] ?? 0) ?: null,
                'item_id' => (int) ($row['item_id'] ?? 0) ?: null,
                'barcode' => (string) ($row['barcode'] ?? ''),
                'member_id' => (int) ($row['member_id'] ?? 0) ?: null,
                'status' => $targetStatus,
                'ack_note' => $note !== '' ? $note : null,
                'metadata' => json_encode([
                    'detail' => (string) ($row['detail'] ?? ''),
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ];

            if ($targetStatus === 'ack') {
                $payload['ack_by'] = $userId > 0 ? $userId : null;
                $payload['ack_at'] = now();
            }
            if ($targetStatus === 'resolved') {
                $payload['resolved_by'] = $userId > 0 ? $userId : null;
                $payload['resolved_at'] = now();
                $payload['ack_by'] = $userId > 0 ? $userId : null;
                $payload['ack_at'] = now();
            }

            DB::table('circulation_exception_acknowledgements')->updateOrInsert(
                $base,
                array_merge(['created_at' => now()], $payload)
            );
            $updated++;
        }

        if ($updated <= 0) {
            return $this->redirectWithFilters($request)->with('error', 'Tidak ada item valid yang dapat diupdate.');
        }

        return $this->redirectWithFilters($request)->with('success', 'Bulk update berhasil: ' . $updated . ' item.');
    }

    private function applyStatus(Request $request, string $targetStatus)
    {
        if (!Schema::hasTable('circulation_exception_acknowledgements')) {
            return $this->redirectWithFilters($request)->with('error', 'Tabel acknowledgement exception belum tersedia.');
        }

        $data = $request->validate([
            'snapshot_date' => ['required', 'date'],
            'fingerprint' => ['required', 'string', 'size:40'],
            'exception_type' => ['nullable', 'string', 'max:80'],
            'severity' => ['nullable', 'string', 'max:20'],
            'loan_id' => ['nullable', 'integer'],
            'loan_item_id' => ['nullable', 'integer'],
            'item_id' => ['nullable', 'integer'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'member_id' => ['nullable', 'integer'],
            'detail' => ['nullable', 'string', 'max:2000'],
            'ack_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $institutionId = (int) ($request->user()->institution_id ?? 0);
        $userId = (int) ($request->user()->id ?? 0);
        $note = trim((string) ($data['ack_note'] ?? ''));

        $base = [
            'institution_id' => $institutionId,
            'snapshot_date' => (string) $data['snapshot_date'],
            'fingerprint' => (string) $data['fingerprint'],
        ];

        $payload = [
            'exception_type' => (string) ($data['exception_type'] ?? ''),
            'severity' => (string) ($data['severity'] ?? ''),
            'loan_id' => !empty($data['loan_id']) ? (int) $data['loan_id'] : null,
            'loan_item_id' => !empty($data['loan_item_id']) ? (int) $data['loan_item_id'] : null,
            'item_id' => !empty($data['item_id']) ? (int) $data['item_id'] : null,
            'barcode' => (string) ($data['barcode'] ?? ''),
            'member_id' => !empty($data['member_id']) ? (int) $data['member_id'] : null,
            'status' => $targetStatus,
            'ack_note' => $note !== '' ? $note : null,
            'metadata' => json_encode([
                'detail' => (string) ($data['detail'] ?? ''),
            ], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];

        if ($targetStatus === 'ack') {
            $payload['ack_by'] = $userId > 0 ? $userId : null;
            $payload['ack_at'] = now();
        }
        if ($targetStatus === 'resolved') {
            $payload['resolved_by'] = $userId > 0 ? $userId : null;
            $payload['resolved_at'] = now();
            if (!isset($payload['ack_by'])) {
                $payload['ack_by'] = $userId > 0 ? $userId : null;
                $payload['ack_at'] = now();
            }
        }

        DB::table('circulation_exception_acknowledgements')->updateOrInsert(
            $base,
            array_merge(['created_at' => now()], $payload)
        );

        return $this->redirectWithFilters($request)->with('success', $targetStatus === 'resolved' ? 'Exception ditandai resolved.' : 'Exception ditandai acknowledged.');
    }

    private function snapshotFiles(): array
    {
        $all = Storage::disk('local')->files('reports/circulation-exceptions');
        $rows = [];
        foreach ($all as $path) {
            if (!preg_match('/circulation-exceptions-(\d{4}-\d{2}-\d{2})\.csv$/', $path, $m)) {
                continue;
            }
            $rows[] = [
                'path' => $path,
                'date' => $m[1],
            ];
        }
        usort($rows, fn($a, $b) => strcmp((string) $b['date'], (string) $a['date']));
        return $rows;
    }

    private function prepareRows(Request $request): array
    {
        $institutionId = (int) ($request->user()->institution_id ?? 0);
        $files = $this->snapshotFiles();
        $dates = array_values(array_map(fn($row) => $row['date'], $files));
        $sessionFilters = (array) $request->session()->get('transaksi_exception_filters', []);

        $selectedDate = $request->query->has('date')
            ? trim((string) $request->query('date', ''))
            : trim((string) ($sessionFilters['date'] ?? ''));
        if ($selectedDate === '' || !in_array($selectedDate, $dates, true)) {
            $selectedDate = $dates[0] ?? '';
        }

        $type = $request->query->has('type')
            ? trim((string) $request->query('type', ''))
            : trim((string) ($sessionFilters['type'] ?? ''));
        $severity = $request->query->has('severity')
            ? trim((string) $request->query('severity', ''))
            : trim((string) ($sessionFilters['severity'] ?? ''));
        $status = $request->query->has('status')
            ? trim((string) $request->query('status', ''))
            : trim((string) ($sessionFilters['status'] ?? ''));
        $q = $request->query->has('q')
            ? trim((string) $request->query('q', ''))
            : trim((string) ($sessionFilters['q'] ?? ''));

        $rows = [];
        if ($selectedDate !== '') {
            $path = collect($files)->firstWhere('date', $selectedDate)['path'] ?? '';
            if ($path !== '') {
                $rows = $this->loadSnapshotRows($path, $selectedDate);
            }
        }

        $ackEnabled = Schema::hasTable('circulation_exception_acknowledgements');
        $ackMap = [];
        $userMap = [];
        $owners = [];
        if ($ackEnabled && !empty($rows)) {
            $fingerprints = array_values(array_unique(array_map(fn($r) => (string) ($r['fingerprint'] ?? ''), $rows)));
            $ackRows = DB::table('circulation_exception_acknowledgements')
                ->where('institution_id', $institutionId)
                ->where('snapshot_date', $selectedDate)
                ->whereIn('fingerprint', $fingerprints)
                ->get();
            $ackMap = $ackRows->keyBy('fingerprint')->all();

            $userIds = [];
            foreach ($ackRows as $row) {
                $ackBy = (int) ($row->ack_by ?? 0);
                $resolvedBy = (int) ($row->resolved_by ?? 0);
                $ownerUserId = (int) ($row->owner_user_id ?? 0);
                if ($ackBy > 0) {
                    $userIds[] = $ackBy;
                }
                if ($resolvedBy > 0) {
                    $userIds[] = $resolvedBy;
                }
                if ($ownerUserId > 0) {
                    $userIds[] = $ownerUserId;
                }
            }
            $userIds = array_values(array_unique($userIds));
            if (!empty($userIds) && Schema::hasTable('users')) {
                $userMap = DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id')->all();
            }
        }

        if (Schema::hasTable('users')) {
            $owners = DB::table('users')
                ->where('institution_id', $institutionId)
                ->whereIn('role', ['super_admin', 'admin', 'staff'])
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'role'])
                ->map(fn($u) => [
                    'id' => (int) $u->id,
                    'name' => (string) ($u->name ?: ('User #' . (int) $u->id)),
                    'role' => (string) ($u->role ?? ''),
                ])
                ->all();
        }

        $rows = array_map(function (array $row) use ($ackMap, $userMap) {
            $fp = (string) ($row['fingerprint'] ?? '');
            $ack = $ackMap[$fp] ?? null;
            if ($ack) {
                $row['status'] = (string) ($ack->status ?? 'open');
                $row['ack_note'] = (string) ($ack->ack_note ?? '');
                $row['ack_at'] = $ack->ack_at ? (string) $ack->ack_at : '';
                $row['resolved_at'] = $ack->resolved_at ? (string) $ack->resolved_at : '';
                $row['owner_user_id'] = (int) ($ack->owner_user_id ?? 0);
                $row['owner_assigned_at'] = $ack->owner_assigned_at ? (string) $ack->owner_assigned_at : '';
                $row['ack_by_name'] = $ack->ack_by ? (string) ($userMap[$ack->ack_by] ?? ('User #' . (int) $ack->ack_by)) : '';
                $row['resolved_by_name'] = $ack->resolved_by ? (string) ($userMap[$ack->resolved_by] ?? ('User #' . (int) $ack->resolved_by)) : '';
                $row['owner_name'] = !empty($row['owner_user_id']) ? (string) ($userMap[$row['owner_user_id']] ?? ('User #' . (int) $row['owner_user_id'])) : '';
            } else {
                $row['status'] = 'open';
                $row['ack_note'] = '';
                $row['ack_at'] = '';
                $row['resolved_at'] = '';
                $row['owner_user_id'] = 0;
                $row['owner_assigned_at'] = '';
                $row['ack_by_name'] = '';
                $row['resolved_by_name'] = '';
                $row['owner_name'] = '';
            }
            $row['age_hours'] = $this->ageHours((string) ($row['detected_at'] ?? ''), (string) ($row['snapshot_date'] ?? ''));
            return $row;
        }, $rows);

        $sla = $this->buildSlaSummary($rows);

        if ($type !== '') {
            $rows = array_values(array_filter($rows, fn($r) => (string) ($r['exception_type'] ?? '') === $type));
        }
        if ($severity !== '') {
            $rows = array_values(array_filter($rows, fn($r) => (string) ($r['severity'] ?? '') === $severity));
        }
        if ($status !== '' && in_array($status, ['open', 'ack', 'resolved'], true)) {
            $rows = array_values(array_filter($rows, fn($r) => (string) ($r['status'] ?? 'open') === $status));
        }
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $rows = array_values(array_filter($rows, function ($r) use ($needle) {
                $hay = implode(' | ', [
                    (string) ($r['exception_type'] ?? ''),
                    (string) ($r['severity'] ?? ''),
                    (string) ($r['loan_code'] ?? ''),
                    (string) ($r['barcode'] ?? ''),
                    (string) ($r['member_code'] ?? ''),
                    (string) ($r['detail'] ?? ''),
                ]);
                return str_contains(mb_strtolower($hay), $needle);
            }));
        }

        $request->session()->put('transaksi_exception_filters', [
            'date' => $selectedDate,
            'type' => $type,
            'severity' => $severity,
            'status' => $status,
            'q' => $q,
            'mode' => $this->resolveViewMode($request),
        ]);

        return [
            'rows' => $rows,
            'dates' => $dates,
            'selectedDate' => $selectedDate,
            'ackEnabled' => $ackEnabled,
            'sla' => $sla,
            'owners' => $owners,
            'filters' => [
                'type' => $type,
                'severity' => $severity,
                'status' => $status,
                'q' => $q,
                'mode' => $this->resolveViewMode($request),
            ],
        ];
    }

    private function redirectWithFilters(Request $request)
    {
        return redirect()->route('transaksi.exceptions.index', $this->currentFiltersFromRequest($request));
    }

    private function currentFiltersFromRequest(Request $request): array
    {
        $sessionFilters = (array) $request->session()->get('transaksi_exception_filters', []);
        $query = [
            'date' => trim((string) $request->input('current_date', '')),
            'type' => trim((string) $request->input('current_type', '')),
            'severity' => trim((string) $request->input('current_severity', '')),
            'status' => trim((string) $request->input('current_status', '')),
            'q' => trim((string) $request->input('current_q', '')),
            'mode' => trim((string) $request->input('current_mode', $request->query('mode', ''))),
        ];

        foreach ($query as $key => $value) {
            if ($value === '' && isset($sessionFilters[$key]) && is_string($sessionFilters[$key])) {
                $query[$key] = trim((string) $sessionFilters[$key]);
            }
        }

        if (!in_array(($query['mode'] ?? ''), ['compact', 'full'], true)) {
            $query['mode'] = $this->resolveViewMode($request);
        }

        return $query;
    }

    private function resolveViewMode(Request $request): string
    {
        $sessionKey = 'ui.transaksi.exceptions.mode';
        $request->session()->put($sessionKey, 'compact');
        return 'compact';
    }

    private function snapshotPathForDate(string $date): string
    {
        foreach ($this->snapshotFiles() as $row) {
            if ((string) ($row['date'] ?? '') === $date) {
                return (string) ($row['path'] ?? '');
            }
        }
        return '';
    }

    private function loadSnapshotRows(string $path, string $fallbackDate): array
    {
        if (!Storage::disk('local')->exists($path)) {
            return [];
        }

        $content = Storage::disk('local')->get($path);
        if (!is_string($content) || trim($content) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\n|\r/', $content);
        if (!$lines || count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv((string) array_shift($lines));
        $headers = array_map(fn($h) => trim((string) $h), $headers);

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line);
            $assoc = [];
            foreach ($headers as $idx => $header) {
                $assoc[$header] = (string) ($values[$idx] ?? '');
            }

            $snapshotDate = trim((string) ($assoc['snapshot_date'] ?? '')) ?: $fallbackDate;
            $row = [
                'snapshot_date' => $snapshotDate,
                'exception_type' => (string) ($assoc['exception_type'] ?? ''),
                'severity' => (string) ($assoc['severity'] ?? ''),
                'institution_id' => (int) ($assoc['institution_id'] ?? 0),
                'branch_id' => (int) ($assoc['branch_id'] ?? 0),
                'loan_id' => (int) ($assoc['loan_id'] ?? 0),
                'loan_code' => (string) ($assoc['loan_code'] ?? ''),
                'loan_item_id' => (int) ($assoc['loan_item_id'] ?? 0),
                'item_id' => (int) ($assoc['item_id'] ?? 0),
                'barcode' => (string) ($assoc['barcode'] ?? ''),
                'member_id' => (int) ($assoc['member_id'] ?? 0),
                'member_code' => (string) ($assoc['member_code'] ?? ''),
                'detail' => (string) ($assoc['detail'] ?? ''),
                'days_late' => (int) ($assoc['days_late'] ?? 0),
                'detected_at' => (string) ($assoc['detected_at'] ?? ''),
            ];
            $row['fingerprint'] = $this->fingerprint($row);
            $rows[] = $row;
        }

        return $rows;
    }

    private function fingerprint(array $row): string
    {
        $parts = [
            (string) ($row['snapshot_date'] ?? ''),
            (string) ($row['exception_type'] ?? ''),
            (string) ((int) ($row['loan_id'] ?? 0)),
            (string) ((int) ($row['loan_item_id'] ?? 0)),
            (string) ((int) ($row['item_id'] ?? 0)),
            (string) ($row['barcode'] ?? ''),
            (string) ((int) ($row['member_id'] ?? 0)),
            trim((string) ($row['detail'] ?? '')),
        ];
        return sha1(implode('|', $parts));
    }

    private function ageHours(string $detectedAt, string $snapshotDate): int
    {
        return CirculationSlaClock::elapsedHoursFrom($detectedAt, $snapshotDate);
    }

    private function buildSlaSummary(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'open' => 0,
            'ack' => 0,
            'resolved' => 0,
            'open_over_24h' => 0,
            'open_over_72h' => 0,
            'ack_over_24h' => 0,
            'ack_over_72h' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'open');
            $age = (int) ($row['age_hours'] ?? 0);
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
            if ($status === 'open') {
                if ($age >= 24) {
                    $summary['open_over_24h']++;
                }
                if ($age >= 72) {
                    $summary['open_over_72h']++;
                }
            }
            if ($status === 'ack') {
                if ($age >= 24) {
                    $summary['ack_over_24h']++;
                }
                if ($age >= 72) {
                    $summary['ack_over_72h']++;
                }
            }
        }

        return $summary;
    }

    private function buildSimpleXlsx(string $sheetName, array $headers, array $rows): string
    {
        if (!class_exists(\ZipArchive::class)) {
            abort(500, 'ZipArchive extension tidak tersedia untuk export XLSX.');
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'nbk_xlsx_');
        if ($tmpPath === false) {
            abort(500, 'Tidak bisa membuat file sementara XLSX.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpPath, \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Gagal membuat arsip XLSX.');
        }

        $safeSheetName = preg_replace('/[^A-Za-z0-9 _-]/', '', $sheetName) ?: 'Sheet1';
        $sheetXml = $this->buildSheetXml($headers, $rows);
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . htmlspecialchars($safeSheetName, ENT_XML1) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
        $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            . 'xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>NOTOBUKU Circulation Exceptions</dc:title>'
            . '<dc:creator>NOTOBUKU</dc:creator>'
            . '<cp:lastModifiedBy>NOTOBUKU</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . now()->toIso8601String() . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . now()->toIso8601String() . '</dcterms:modified>'
            . '</cp:coreProperties>';
        $app = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>NOTOBUKU</Application>'
            . '</Properties>';

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->addFromString('docProps/core.xml', $core);
        $zip->addFromString('docProps/app.xml', $app);
        $zip->close();

        return $tmpPath;
    }

    private function buildSheetXml(array $headers, array $rows): string
    {
        $allRows = [];
        $allRows[] = $headers;
        foreach ($rows as $row) {
            $allRows[] = is_array($row) ? array_values($row) : [(string) $row];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';

        foreach ($allRows as $rIndex => $rowValues) {
            $rowNumber = $rIndex + 1;
            $xml .= '<row r="' . $rowNumber . '">';
            foreach ($rowValues as $cIndex => $value) {
                $cellRef = $this->xlsxColName($cIndex + 1) . $rowNumber;
                if (is_numeric($value) && !preg_match('/^0\d+/', (string) $value)) {
                    $xml .= '<c r="' . $cellRef . '" t="n"><v>' . (0 + $value) . '</v></c>';
                } else {
                    $escaped = htmlspecialchars((string) $value, ENT_XML1);
                    $xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function xlsxColName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $index = intdiv($index - 1, 26);
        }
        return $name;
    }
}
