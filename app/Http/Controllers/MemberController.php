<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    private const IMPORT_PREVIEW_MAX_ROWS = 3000;
    private const IMPORT_UNDO_MAX_AGE_HOURS = 24;
    private const IMPORT_USED_TOKEN_MAX_AGE_HOURS = 24;
    private const IMPORT_USED_TOKEN_MAX_ITEMS = 200;

    private function institutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    public function index(Request $request)
    {
        $institutionId = $this->institutionId();
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $hasMemberType = Schema::hasColumn('members', 'member_type');
        $hasEmail = Schema::hasColumn('members', 'email');

        $query = Member::query()
            ->where('institution_id', $institutionId)
            ->when($q !== '', function ($builder) use ($q, $hasEmail) {
                $builder->where(function ($w) use ($q, $hasEmail) {
                    $w->where('member_code', 'like', "%{$q}%")
                        ->orWhere('full_name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                    if ($hasEmail) {
                        $w->orWhere('email', 'like', "%{$q}%");
                    }
                });
            })
            ->when(in_array($status, ['active', 'inactive', 'suspended'], true), function ($builder) use ($status) {
                $builder->where('status', $status);
            });

        $select = ['id', 'member_code', 'full_name', 'phone', 'status', 'joined_at'];
        if ($hasMemberType) {
            $select[] = 'member_type';
        }
        if ($hasEmail) {
            $select[] = 'email';
        }

        $members = $query
            ->select($select)
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'total' => (int) Member::query()->where('institution_id', $institutionId)->count(),
            'active' => (int) Member::query()->where('institution_id', $institutionId)->where('status', 'active')->count(),
            'overdue' => $this->countOverdueMembers($institutionId),
            'unpaid' => $this->countUnpaidMembers($institutionId),
            'sparklines' => $this->buildKpiSparklines($institutionId),
        ];

        $preview = $request->session()->get('member_import_preview');
        $this->cleanupUsedImportTokens($request);
        $lastImport = AuditLog::query()
            ->where('user_id', auth()->id())
            ->where('action', 'member_import')
            ->orderByDesc('id')
            ->first();
        $canUndoImport = false;
        if ($lastImport) {
            $meta = is_array($lastImport->meta) ? $lastImport->meta : [];
            $sameInstitution = (int) ($meta['institution_id'] ?? 0) === $institutionId;
            $undone = !empty($meta['undone_at']);
            $recentEnough = $lastImport->created_at && $lastImport->created_at->gte(now()->subHours(self::IMPORT_UNDO_MAX_AGE_HOURS));
            $canUndoImport = $sameInstitution && !$undone && $recentEnough;
        }

        $importMetrics = $this->buildImportMetrics($institutionId);

        return view('anggota.index', [
            'members' => $members,
            'q' => $q,
            'status' => $status,
            'summary' => $summary,
            'hasMemberType' => $hasMemberType,
            'hasEmail' => $hasEmail,
            'importPreview' => $preview,
            'canUndoImport' => $canUndoImport,
            'importMetrics' => $importMetrics,
        ]);
    }

    public function create()
    {
        return view('anggota.create', [
            'hasMemberType' => Schema::hasColumn('members', 'member_type'),
            'hasEmail' => Schema::hasColumn('members', 'email'),
        ]);
    }

    public function store(Request $request)
    {
        $institutionId = $this->institutionId();
        $hasMemberType = Schema::hasColumn('members', 'member_type');
        $hasEmail = Schema::hasColumn('members', 'email');

        $rules = [
            'member_code' => ['required', 'string', 'max:50', Rule::unique('members', 'member_code')],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'joined_at' => ['nullable', 'date'],
        ];

        if ($hasMemberType) {
            $rules['member_type'] = ['nullable', 'string', 'max:30'];
        }
        if ($hasEmail) {
            $rules['email'] = ['nullable', 'email', 'max:255'];
        }

        $validated = $request->validate($rules);
        $payload = [
            'institution_id' => $institutionId,
            'member_code' => $validated['member_code'],
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'status' => $validated['status'],
            'joined_at' => $validated['joined_at'] ?? null,
        ];

        if ($hasMemberType) {
            $payload['member_type'] = trim((string) ($validated['member_type'] ?? '')) ?: 'member';
        }
        if ($hasEmail) {
            $payload['email'] = $validated['email'] ?? null;
        }

        Member::query()->create($payload);

        return redirect()->route('anggota.index')->with('success', 'Anggota berhasil ditambahkan.');
    }

    public function show(int $id)
    {
        $institutionId = $this->institutionId();
        $member = Member::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $stats = $this->memberStats($institutionId, (int) $member->id);
        $recentLoans = $this->memberRecentLoans($institutionId, (int) $member->id);

        return view('anggota.show', [
            'member' => $member,
            'stats' => $stats,
            'recentLoans' => $recentLoans,
            'hasMemberType' => Schema::hasColumn('members', 'member_type'),
            'hasEmail' => Schema::hasColumn('members', 'email'),
        ]);
    }

    public function card(int $id)
    {
        $institutionId = $this->institutionId();
        $member = Member::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $status = strtolower((string) ($member->status ?? 'inactive'));
        $statusLabel = match ($status) {
            'active' => 'AKTIF',
            'suspended' => 'SUSPENDED',
            default => 'INACTIVE',
        };

        $qrData = implode('|', array_filter([
            (string) ($member->member_code ?? ''),
            (string) ($member->full_name ?? ''),
            (string) ($member->id ?? ''),
        ]));
        if ($qrData === '') {
            $qrData = 'member:' . (string) ($member->id ?? '0');
        }
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . urlencode($qrData);

        return view('anggota.card', [
            'member' => $member,
            'statusLabel' => $statusLabel,
            'qrUrl' => $qrUrl,
            'hasMemberType' => Schema::hasColumn('members', 'member_type'),
            'hasEmail' => Schema::hasColumn('members', 'email'),
        ]);
    }

    public function edit(int $id)
    {
        $institutionId = $this->institutionId();
        $member = Member::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        return view('anggota.edit', [
            'member' => $member,
            'hasMemberType' => Schema::hasColumn('members', 'member_type'),
            'hasEmail' => Schema::hasColumn('members', 'email'),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $institutionId = $this->institutionId();
        $member = Member::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $hasMemberType = Schema::hasColumn('members', 'member_type');
        $hasEmail = Schema::hasColumn('members', 'email');

        $rules = [
            'member_code' => ['required', 'string', 'max:50', Rule::unique('members', 'member_code')->ignore($member->id)],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'joined_at' => ['nullable', 'date'],
        ];
        if ($hasMemberType) {
            $rules['member_type'] = ['nullable', 'string', 'max:30'];
        }
        if ($hasEmail) {
            $rules['email'] = ['nullable', 'email', 'max:255'];
        }

        $validated = $request->validate($rules);

        $payload = [
            'member_code' => $validated['member_code'],
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'status' => $validated['status'],
            'joined_at' => $validated['joined_at'] ?? null,
        ];
        if ($hasMemberType) {
            $payload['member_type'] = trim((string) ($validated['member_type'] ?? '')) ?: 'member';
        }
        if ($hasEmail) {
            $payload['email'] = $validated['email'] ?? null;
        }

        $member->update($payload);

        return redirect()->route('anggota.index')->with('success', 'Data anggota diperbarui.');
    }

    public function downloadTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['member_code', 'full_name', 'member_type', 'status', 'phone', 'email', 'joined_at', 'address']);
            fputcsv($out, ['MBR-0001', 'Budi Santoso', 'member', 'active', '08123456789', 'budi@example.com', now()->toDateString(), 'Jl. Mawar 1']);
            fputcsv($out, ['MBR-0002', 'Sari Anjani', 'student', 'active', '08129876543', 'sari@example.com', now()->toDateString(), 'Jl. Melati 2']);
            fclose($out);
        }, 'template-anggota.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importCsv(Request $request)
    {
        // Keep compatibility: direct import now uses preview + confirm flow.
        return $this->previewImportCsv($request);
    }

    public function previewImportCsv(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $institutionId = $this->institutionId();
        $hasMemberType = Schema::hasColumn('members', 'member_type');
        $hasEmail = Schema::hasColumn('members', 'email');
        $parsed = $this->parseMemberCsv($request->file('csv_file'), $hasMemberType, $hasEmail);
        if (!($parsed['ok'] ?? false)) {
            return back()->withErrors(['csv_file' => (string) ($parsed['message'] ?? 'Gagal memproses CSV.')]);
        }

        $rows = $parsed['rows'];
        $codes = collect($rows)->pluck('member_code')->filter()->values()->all();
        $existingCodes = [];
        if (!empty($codes)) {
            $existingCodes = Member::query()
                ->where('institution_id', $institutionId)
                ->whereIn('member_code', $codes)
                ->pluck('member_code')
                ->all();
        }
        $existingSet = array_fill_keys($existingCodes, true);

        $emails = [];
        $phones = [];
        foreach ($rows as $row) {
            if (!empty($row['is_error'])) {
                continue;
            }
            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '') {
                $emails[] = mb_strtolower($email);
            }
            $phone = trim((string) ($row['phone'] ?? ''));
            if ($phone !== '') {
                $phones[] = $phone;
            }
        }
        $emails = array_values(array_unique($emails));
        $phones = array_values(array_unique($phones));

        $dbEmailSet = [];
        $dbPhoneSet = [];
        if (!empty($emails) && Schema::hasColumn('members', 'email')) {
            $dbEmails = Member::query()
                ->where('institution_id', $institutionId)
                ->whereIn(DB::raw('LOWER(email)'), $emails)
                ->whereNotNull('email')
                ->pluck('email')
                ->map(fn($v) => mb_strtolower((string) $v))
                ->all();
            $dbEmailSet = array_fill_keys($dbEmails, true);
        }
        if (!empty($phones)) {
            $dbPhones = Member::query()
                ->where('institution_id', $institutionId)
                ->whereIn('phone', $phones)
                ->whereNotNull('phone')
                ->pluck('phone')
                ->all();
            $dbPhoneSet = array_fill_keys(array_map('strval', $dbPhones), true);
        }

        $csvEmailCount = [];
        $csvPhoneCount = [];
        foreach ($rows as $row) {
            if (!empty($row['is_error'])) {
                continue;
            }
            $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
            $phone = trim((string) ($row['phone'] ?? ''));
            if ($email !== '') {
                $csvEmailCount[$email] = ($csvEmailCount[$email] ?? 0) + 1;
            }
            if ($phone !== '') {
                $csvPhoneCount[$phone] = ($csvPhoneCount[$phone] ?? 0) + 1;
            }
        }

        $valid = 0;
        $errors = 0;
        $updates = 0;
        $inserts = 0;
        $duplicateEmailRows = 0;
        $duplicatePhoneRows = 0;
        foreach ($rows as &$row) {
            if (!empty($row['is_error'])) {
                $errors++;
                continue;
            }
            $row['exists_in_db'] = isset($existingSet[$row['member_code']]);

            $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
            $phone = trim((string) ($row['phone'] ?? ''));
            $dupEmailInDb = ($email !== '' && isset($dbEmailSet[$email]));
            $dupPhoneInDb = ($phone !== '' && isset($dbPhoneSet[$phone]));
            $dupEmailInCsv = ($email !== '' && (($csvEmailCount[$email] ?? 0) > 1));
            $dupPhoneInCsv = ($phone !== '' && (($csvPhoneCount[$phone] ?? 0) > 1));

            $row['dup_email_db'] = $dupEmailInDb;
            $row['dup_phone_db'] = $dupPhoneInDb;
            $row['dup_email_csv'] = $dupEmailInCsv;
            $row['dup_phone_csv'] = $dupPhoneInCsv;
            $row['has_duplicate_contact'] = $dupEmailInDb || $dupPhoneInDb || $dupEmailInCsv || $dupPhoneInCsv;

            $dupReasons = [];
            if ($dupEmailInDb) $dupReasons[] = 'email ada di database';
            if ($dupPhoneInDb) $dupReasons[] = 'phone ada di database';
            if ($dupEmailInCsv) $dupReasons[] = 'email duplikat di CSV';
            if ($dupPhoneInCsv) $dupReasons[] = 'phone duplikat di CSV';
            $row['duplicate_reason'] = !empty($dupReasons) ? implode('; ', $dupReasons) : null;

            if ($row['dup_email_db'] || $row['dup_email_csv']) {
                $duplicateEmailRows++;
            }
            if ($row['dup_phone_db'] || $row['dup_phone_csv']) {
                $duplicatePhoneRows++;
            }

            if ($row['exists_in_db']) {
                $updates++;
            } else {
                $inserts++;
            }
            $valid++;
        }
        unset($row);

        $payload = [
            'institution_id' => $institutionId,
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'valid' => $valid,
                'errors' => $errors,
                'will_insert' => $inserts,
                'will_update' => $updates,
                'duplicate_email_rows' => $duplicateEmailRows,
                'duplicate_phone_rows' => $duplicatePhoneRows,
            ],
            'confirm_token' => bin2hex(random_bytes(16)),
            'created_at' => now()->toDateTimeString(),
        ];
        $request->session()->put('member_import_preview', $payload);

        return redirect()->route('anggota.index')->with('success', 'Pratinjau impor siap. Periksa ringkasan lalu konfirmasi.');
    }

    public function cancelImportPreview(Request $request)
    {
        $request->session()->forget('member_import_preview');
        return redirect()->route('anggota.index')->with('success', 'Pratinjau impor dibatalkan.');
    }

    public function confirmImportCsv(Request $request)
    {
        $payload = $request->session()->get('member_import_preview');
        if (!$payload || empty($payload['rows']) || !is_array($payload['rows'])) {
            return redirect()->route('anggota.index')->withErrors(['csv_file' => 'Pratinjau impor tidak ditemukan.']);
        }

        $institutionId = $this->institutionId();
        if ((int) ($payload['institution_id'] ?? 0) !== $institutionId) {
            return redirect()->route('anggota.index')->withErrors(['csv_file' => 'Pratinjau impor tidak sesuai institusi aktif.']);
        }

        $confirmToken = (string) $request->input('confirm_token', '');
        $sessionToken = (string) ($payload['confirm_token'] ?? '');
        if ($confirmToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $confirmToken)) {
            return redirect()->route('anggota.index')->withErrors(['csv_file' => 'Token konfirmasi tidak valid. Ulangi pratinjau impor.']);
        }
        $usedTokens = $this->cleanupUsedImportTokens($request);
        if (isset($usedTokens[$sessionToken])) {
            return redirect()->route('anggota.index')->withErrors(['csv_file' => 'Konfirmasi import ini sudah diproses.']);
        }

        $forceEmailDup = (string) $request->input('force_email_duplicate', '') === '1';
        $canForce = $this->canForceDuplicateOverride();
        $rows = (array) $payload['rows'];

        $duplicateEmailRows = array_values(array_filter($rows, function ($row) {
            if (!empty($row['is_error'])) {
                return false;
            }
            return !empty($row['dup_email_db']) || !empty($row['dup_email_csv']);
        }));

        if (!empty($duplicateEmailRows) && !($forceEmailDup && $canForce)) {
            $count = count($duplicateEmailRows);
            return redirect()->route('anggota.index')->withErrors([
                'csv_file' => "Terdapat {$count} baris duplikat email. Perbaiki CSV atau gunakan override admin.",
            ]);
        }

        $hasMemberType = Schema::hasColumn('members', 'member_type');
        $hasEmail = Schema::hasColumn('members', 'email');
        $now = now();

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $beforeUpdated = [];
        $insertedCodes = [];
        $batchKey = 'member-import-' . $institutionId . '-' . $now->format('YmdHis') . '-' . auth()->id();

        DB::transaction(function () use (
            $rows,
            $institutionId,
            $hasMemberType,
            $hasEmail,
            $now,
            &$inserted,
            &$updated,
            &$skipped,
            &$beforeUpdated,
            &$insertedCodes
        ) {
            foreach ($rows as $row) {
                if (!empty($row['is_error'])) {
                    $skipped++;
                    continue;
                }

                $memberCode = (string) ($row['member_code'] ?? '');
                $fullName = (string) ($row['full_name'] ?? '');
                if ($memberCode === '' || $fullName === '') {
                    $skipped++;
                    continue;
                }

                $data = [
                    'member_code' => $memberCode,
                    'full_name' => $fullName,
                    'phone' => $row['phone'] ?? null,
                    'address' => $row['address'] ?? null,
                    'status' => $row['status'] ?? 'active',
                    'joined_at' => $row['joined_at'] ?? null,
                    'updated_at' => $now,
                ];
                if ($hasMemberType) {
                    $data['member_type'] = $row['member_type'] ?? 'member';
                }
                if ($hasEmail) {
                    $data['email'] = $row['email'] ?? null;
                }

                $existing = Member::query()
                    ->where('institution_id', $institutionId)
                    ->where('member_code', $memberCode)
                    ->first();

                if ($existing) {
                    $beforeUpdated[] = [
                        'id' => (int) $existing->id,
                        'member_code' => (string) $existing->member_code,
                        'full_name' => (string) $existing->full_name,
                        'phone' => $existing->phone,
                        'address' => $existing->address,
                        'status' => (string) $existing->status,
                        'joined_at' => $existing->joined_at ? \Illuminate\Support\Carbon::parse($existing->joined_at)->toDateString() : null,
                        'member_type' => $hasMemberType ? (string) ($existing->member_type ?? 'member') : null,
                        'email' => $hasEmail ? (string) ($existing->email ?? '') : null,
                    ];
                    $existing->update($data);
                    $updated++;
                } else {
                    $data['institution_id'] = $institutionId;
                    $data['created_at'] = $now;
                    Member::query()->create($data);
                    $inserted++;
                    $insertedCodes[] = $memberCode;
                }
            }
        });

        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'action' => 'member_import',
            'format' => 'csv',
            'status' => 'success',
            'meta' => [
                'institution_id' => $institutionId,
                'batch_key' => $batchKey,
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
                'force_email_duplicate' => $forceEmailDup && $canForce,
                'inserted_codes' => $insertedCodes,
                'before_updated' => $beforeUpdated,
            ],
        ]);

        $usedTokens[$sessionToken] = now()->toDateTimeString();
        $usedTokens = $this->normalizeUsedImportTokens($usedTokens);
        $request->session()->put('member_import_used_tokens', $usedTokens);

        $request->session()->forget('member_import_preview');

        return redirect()->route('anggota.index')->with(
            'success',
            "Import dikonfirmasi. Insert: {$inserted}, Update: {$updated}, Skip: {$skipped}."
        );
    }

    public function downloadImportErrorCsv(Request $request): StreamedResponse
    {
        $payload = $request->session()->get('member_import_preview');
        $rows = (array) ($payload['rows'] ?? []);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['line', 'member_code', 'full_name', 'reason']);
            foreach ($rows as $row) {
                if (empty($row['is_error'])) {
                    continue;
                }
                fputcsv($out, [
                    $row['line'] ?? '',
                    $row['member_code'] ?? '',
                    $row['full_name'] ?? '',
                    $row['error_reason'] ?? 'Invalid row',
                ]);
            }
            fclose($out);
        }, 'anggota-import-errors.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadImportSummaryCsv(Request $request): StreamedResponse
    {
        $payload = $request->session()->get('member_import_preview');
        $rows = (array) ($payload['rows'] ?? []);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'line',
                'member_code',
                'full_name',
                'status',
                'phone',
                'email',
                'db_action',
                'is_error',
                'error_reason',
                'has_duplicate_contact',
                'duplicate_reason',
            ]);

            foreach ($rows as $row) {
                $dbAction = 'skip';
                if (empty($row['is_error'])) {
                    $dbAction = !empty($row['exists_in_db']) ? 'update' : 'insert';
                }
                fputcsv($out, [
                    $row['line'] ?? '',
                    $row['member_code'] ?? '',
                    $row['full_name'] ?? '',
                    $row['status'] ?? '',
                    $row['phone'] ?? '',
                    $row['email'] ?? '',
                    $dbAction,
                    !empty($row['is_error']) ? '1' : '0',
                    $row['error_reason'] ?? '',
                    !empty($row['has_duplicate_contact']) ? '1' : '0',
                    $row['duplicate_reason'] ?? '',
                ]);
            }
            fclose($out);
        }, 'anggota-import-preview-summary.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadImportHistoryCsv(Request $request): StreamedResponse
    {
        $institutionId = $this->institutionId();
        $filters = $this->resolveImportHistoryFilters($request);
        [$rows, $userNames] = $this->loadImportHistoryRows($institutionId, $filters);

        $filename = 'anggota-import-history-' . now()->format('Ymd-His') . '.csv';
        return response()->streamDownload(function () use ($rows, $userNames, $institutionId, $filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['generated_at', now()->toDateTimeString()]);
            fputcsv($out, ['institution_id', (string) $institutionId]);
            fputcsv($out, ['from', $filters['from']->toDateString()]);
            fputcsv($out, ['to', $filters['to']->toDateString()]);
            fputcsv($out, ['action', $filters['action'] ?? 'all']);
            fputcsv($out, ['row_limit', (string) $filters['limit']]);
            fputcsv($out, []);
            fputcsv($out, [
                'created_at',
                'action',
                'status',
                'user_name',
                'batch_key',
                'inserted',
                'updated',
                'skipped',
                'force_email_duplicate',
                'undone_from_audit_id',
            ]);

            foreach ($rows as $row) {
                $meta = is_array($row->meta) ? $row->meta : [];
                fputcsv($out, [
                    $row->created_at ? $row->created_at->toDateTimeString() : '',
                    (string) $row->action,
                    (string) ($row->status ?? ''),
                    (string) ($userNames[$row->user_id] ?? 'System'),
                    (string) ($meta['batch_key'] ?? ''),
                    (string) ((int) ($meta['inserted'] ?? 0)),
                    (string) ((int) ($meta['updated'] ?? 0)),
                    (string) ((int) ($meta['skipped'] ?? 0)),
                    !empty($meta['force_email_duplicate']) ? '1' : '0',
                    (string) ((int) ($meta['undone_from_audit_id'] ?? 0)),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadImportHistoryXlsx(Request $request): StreamedResponse
    {
        $institutionId = $this->institutionId();
        $filters = $this->resolveImportHistoryFilters($request);
        [$rows, $userNames] = $this->loadImportHistoryRows($institutionId, $filters);

        $headers = [
            'created_at',
            'action',
            'status',
            'user_name',
            'batch_key',
            'inserted',
            'updated',
            'skipped',
            'force_email_duplicate',
            'undone_from_audit_id',
        ];

        $sheetRows = [];
        foreach ($rows as $row) {
            $meta = is_array($row->meta) ? $row->meta : [];
            $sheetRows[] = [
                $row->created_at ? $row->created_at->toDateTimeString() : '',
                (string) $row->action,
                (string) ($row->status ?? ''),
                (string) ($userNames[$row->user_id] ?? 'System'),
                (string) ($meta['batch_key'] ?? ''),
                (int) ($meta['inserted'] ?? 0),
                (int) ($meta['updated'] ?? 0),
                (int) ($meta['skipped'] ?? 0),
                !empty($meta['force_email_duplicate']) ? 1 : 0,
                (int) ($meta['undone_from_audit_id'] ?? 0),
            ];
        }

        $xlsxPath = $this->buildSimpleXlsx('Import History', $headers, $sheetRows);
        $filename = 'anggota-import-history-' . now()->format('Ymd-His') . '.xlsx';

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

    public function undoImportBatch()
    {
        $institutionId = $this->institutionId();
        $last = AuditLog::query()
            ->where('user_id', auth()->id())
            ->where('action', 'member_import')
            ->orderByDesc('id')
            ->first();

        if (!$last) {
            return redirect()->route('anggota.index')->with('error', 'Tidak ada batch import untuk di-undo.');
        }

        $meta = is_array($last->meta) ? $last->meta : [];
        if ((int) ($meta['institution_id'] ?? 0) !== $institutionId) {
            return redirect()->route('anggota.index')->with('error', 'Batch import terakhir bukan institusi aktif.');
        }
        if (!empty($meta['undone_at'])) {
            return redirect()->route('anggota.index')->with('error', 'Batch import terakhir sudah di-undo.');
        }
        if ($last->created_at && $last->created_at->lt(now()->subHours(self::IMPORT_UNDO_MAX_AGE_HOURS))) {
            return redirect()->route('anggota.index')->with('error', 'Undo hanya diizinkan untuk batch 24 jam terakhir.');
        }

        $insertedCodes = (array) ($meta['inserted_codes'] ?? []);
        $beforeUpdated = (array) ($meta['before_updated'] ?? []);
        $hasMemberType = Schema::hasColumn('members', 'member_type');
        $hasEmail = Schema::hasColumn('members', 'email');
        $now = now();

        DB::transaction(function () use ($institutionId, $insertedCodes, $beforeUpdated, $hasMemberType, $hasEmail, $now, $last, $meta) {
            if (!empty($insertedCodes)) {
                Member::query()
                    ->where('institution_id', $institutionId)
                    ->whereIn('member_code', $insertedCodes)
                    ->delete();
            }

            foreach ($beforeUpdated as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $update = [
                    'member_code' => $row['member_code'] ?? '',
                    'full_name' => $row['full_name'] ?? '',
                    'phone' => $row['phone'] ?? null,
                    'address' => $row['address'] ?? null,
                    'status' => $row['status'] ?? 'active',
                    'joined_at' => $row['joined_at'] ?? null,
                    'updated_at' => $now,
                ];
                if ($hasMemberType) {
                    $update['member_type'] = $row['member_type'] ?? 'member';
                }
                if ($hasEmail) {
                    $email = $row['email'] ?? null;
                    $update['email'] = $email !== '' ? $email : null;
                }

                Member::query()
                    ->where('institution_id', $institutionId)
                    ->where('id', $id)
                    ->update($update);
            }

            $meta['undone_at'] = $now->toDateTimeString();
            $last->meta = $meta;
            $last->save();

            AuditLog::query()->create([
                'user_id' => auth()->id(),
                'action' => 'member_import_undo',
                'format' => 'csv',
                'status' => 'success',
                'meta' => [
                    'institution_id' => $institutionId,
                    'batch_key' => $meta['batch_key'] ?? null,
                    'undone_from_audit_id' => (int) $last->id,
                ],
            ]);
        });

        return redirect()->route('anggota.index')->with('success', 'Undo batch import berhasil.');
    }

    public function importMetrics(Request $request)
    {
        $institutionId = $this->institutionId();
        $metrics = $this->buildImportMetrics($institutionId);

        $recentLimit = (int) $request->query('recent_limit', 10);
        if ($recentLimit < 1) {
            $recentLimit = 1;
        }
        if ($recentLimit > 50) {
            $recentLimit = 50;
        }
        $metrics['recent'] = array_slice((array) ($metrics['recent'] ?? []), 0, $recentLimit);

        return response()->json([
            'ok' => true,
            'institution_id' => $institutionId,
            'generated_at' => now()->toIso8601String(),
            'metrics' => $metrics,
        ]);
    }

    public function importMetricsChart(Request $request)
    {
        $institutionId = $this->institutionId();
        $metrics = $this->buildImportMetrics($institutionId);

        $window = (int) $request->query('window', 30);
        if (!in_array($window, [7, 30], true)) {
            $window = 30;
        }

        $daily = $window === 7
            ? (array) ($metrics['daily_7d'] ?? [])
            : (array) ($metrics['daily_30d'] ?? []);

        $series = [
            'import_runs' => [],
            'undo_runs' => [],
            'inserted' => [],
        ];

        foreach ($daily as $row) {
            $date = (string) ($row['date'] ?? '');
            $series['import_runs'][] = [
                'date' => $date,
                'value' => (int) ($row['import_runs'] ?? 0),
            ];
            $series['undo_runs'][] = [
                'date' => $date,
                'value' => (int) ($row['undo_runs'] ?? 0),
            ];
            $series['inserted'][] = [
                'date' => $date,
                'value' => (int) ($row['inserted'] ?? 0),
            ];
        }

        return response()->json([
            'ok' => true,
            'institution_id' => $institutionId,
            'generated_at' => now()->toIso8601String(),
            'window' => $window,
            'labels' => array_values(array_map(fn($r) => (string) ($r['date'] ?? ''), $daily)),
            'series' => $series,
        ]);
    }

    public function kpiMetrics(Request $request)
    {
        $institutionId = $this->institutionId();

        return response()->json([
            'ok' => true,
            'institution_id' => $institutionId,
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total' => (int) Member::query()->where('institution_id', $institutionId)->count(),
                'active' => (int) Member::query()->where('institution_id', $institutionId)->where('status', 'active')->count(),
                'overdue' => $this->countOverdueMembers($institutionId),
                'unpaid' => $this->countUnpaidMembers($institutionId),
                'sparklines' => $this->buildKpiSparklines($institutionId),
            ],
        ]);
    }

    private function resolveImportHistoryFilters(Request $request): array
    {
        $days = (int) $request->query('days', 30);
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 180) {
            $days = 180;
        }

        $fromInput = trim((string) $request->query('from', ''));
        $toInput = trim((string) $request->query('to', ''));

        try {
            $from = $fromInput !== '' ? \Illuminate\Support\Carbon::parse($fromInput)->startOfDay() : now()->subDays($days)->startOfDay();
        } catch (\Throwable $e) {
            $from = now()->subDays($days)->startOfDay();
        }
        try {
            $to = $toInput !== '' ? \Illuminate\Support\Carbon::parse($toInput)->endOfDay() : now()->endOfDay();
        } catch (\Throwable $e) {
            $to = now()->endOfDay();
        }
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $action = trim((string) $request->query('action', ''));
        if (!in_array($action, ['member_import', 'member_import_undo'], true)) {
            $action = null;
        }

        $limit = (int) $request->query('limit', 500);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 2000) {
            $limit = 2000;
        }

        return [
            'from' => $from,
            'to' => $to,
            'action' => $action,
            'limit' => $limit,
        ];
    }

    private function loadImportHistoryRows(int $institutionId, array $filters): array
    {
        $query = AuditLog::query()
            ->whereIn('action', ['member_import', 'member_import_undo'])
            ->where('meta->institution_id', $institutionId)
            ->where('created_at', '>=', $filters['from'])
            ->where('created_at', '<=', $filters['to']);

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        $rows = $query
            ->orderByDesc('id')
            ->limit((int) $filters['limit'])
            ->get(['id', 'user_id', 'action', 'status', 'meta', 'created_at']);

        $userNames = DB::table('users')
            ->whereIn('id', $rows->pluck('user_id')->filter()->unique()->values()->all())
            ->pluck('name', 'id');

        return [$rows, $userNames];
    }

    private function parseMemberCsv(UploadedFile $file, bool $hasMemberType, bool $hasEmail): array
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            return ['ok' => false, 'message' => 'File CSV tidak bisa dibaca.'];
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return ['ok' => false, 'message' => 'CSV kosong.'];
        }

        $headerMap = [];
        foreach ($header as $i => $col) {
            $key = mb_strtolower(trim((string) $col));
            if ($key !== '') {
                $headerMap[$key] = $i;
            }
        }

        if (!isset($headerMap['member_code']) || !isset($headerMap['full_name'])) {
            fclose($handle);
            return ['ok' => false, 'message' => 'Header wajib: member_code, full_name'];
        }

        $seenCodes = [];
        $rows = [];
        $line = 1;
        while (($csvRow = fgetcsv($handle)) !== false) {
            $line++;
            if (count($rows) >= self::IMPORT_PREVIEW_MAX_ROWS) {
                fclose($handle);
                return [
                    'ok' => false,
                    'message' => 'Melebihi batas pratinjau impor (' . self::IMPORT_PREVIEW_MAX_ROWS . ' baris). Pecah file CSV.',
                ];
            }
            $memberCode = trim((string) ($csvRow[$headerMap['member_code']] ?? ''));
            $fullName = trim((string) ($csvRow[$headerMap['full_name']] ?? ''));
            $statusRaw = mb_strtolower(trim((string) ($csvRow[$headerMap['status']] ?? 'active')));
            $status = in_array($statusRaw, ['active', 'inactive', 'suspended'], true) ? $statusRaw : 'active';

            $joinedAtRaw = trim((string) ($csvRow[$headerMap['joined_at']] ?? ''));
            $joinedAt = null;
            $errorReason = null;

            if ($memberCode === '' || $fullName === '') {
                $errorReason = 'member_code atau full_name kosong';
            }

            $dupKey = mb_strtolower($memberCode);
            if (!$errorReason && $dupKey !== '') {
                if (isset($seenCodes[$dupKey])) {
                    $errorReason = 'Duplikat member_code dalam CSV';
                }
                $seenCodes[$dupKey] = true;
            }

            if (!$errorReason && $joinedAtRaw !== '') {
                try {
                    $joinedAt = \Illuminate\Support\Carbon::parse($joinedAtRaw)->toDateString();
                } catch (\Throwable $e) {
                    $errorReason = 'Format joined_at tidak valid';
                }
            }

            $email = null;
            if ($hasEmail) {
                $emailRaw = trim((string) ($csvRow[$headerMap['email']] ?? ''));
                if ($emailRaw !== '') {
                    if (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
                        $errorReason = $errorReason ?: 'Format email tidak valid';
                    } else {
                        $email = $emailRaw;
                    }
                }
            }

            $rows[] = [
                'line' => $line,
                'member_code' => $memberCode,
                'full_name' => $fullName,
                'member_type' => $hasMemberType ? (trim((string) ($csvRow[$headerMap['member_type']] ?? '')) ?: 'member') : null,
                'status' => $status,
                'phone' => trim((string) ($csvRow[$headerMap['phone']] ?? '')) ?: null,
                'email' => $email,
                'joined_at' => $joinedAt,
                'address' => trim((string) ($csvRow[$headerMap['address']] ?? '')) ?: null,
                'is_error' => $errorReason !== null,
                'error_reason' => $errorReason,
            ];
        }

        fclose($handle);
        return ['ok' => true, 'rows' => $rows];
    }

    private function canForceDuplicateOverride(): bool
    {
        $role = (string) (auth()->user()->role ?? 'member');
        return in_array($role, ['super_admin', 'admin', 'staff'], true);
    }

    private function cleanupUsedImportTokens(Request $request): array
    {
        $tokens = (array) $request->session()->get('member_import_used_tokens', []);
        $tokens = $this->normalizeUsedImportTokens($tokens);
        $request->session()->put('member_import_used_tokens', $tokens);
        return $tokens;
    }

    private function normalizeUsedImportTokens(array $tokens): array
    {
        $minTime = now()->subHours(self::IMPORT_USED_TOKEN_MAX_AGE_HOURS);
        $normalized = [];

        foreach ($tokens as $token => $when) {
            if (!is_string($token) || trim($token) === '') {
                continue;
            }
            try {
                $ts = \Illuminate\Support\Carbon::parse((string) $when);
            } catch (\Throwable $e) {
                continue;
            }
            if ($ts->lt($minTime)) {
                continue;
            }
            $normalized[$token] = $ts->toDateTimeString();
        }

        if (count($normalized) > self::IMPORT_USED_TOKEN_MAX_ITEMS) {
            uasort($normalized, fn($a, $b) => strcmp((string) $b, (string) $a));
            $normalized = array_slice($normalized, 0, self::IMPORT_USED_TOKEN_MAX_ITEMS, true);
        }

        return $normalized;
    }

    private function buildImportMetrics(int $institutionId): array
    {
        $now = now();
        $since = $now->copy()->subDays(30);
        $since7 = $now->copy()->subDays(7);
        $rows = AuditLog::query()
            ->whereIn('action', ['member_import', 'member_import_undo'])
            ->where('meta->institution_id', $institutionId)
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'user_id', 'action', 'status', 'meta', 'created_at']);

        $imports = $rows->where('action', 'member_import');
        $undos = $rows->where('action', 'member_import_undo');
        $imports7 = $imports->filter(fn($r) => $r->created_at && $r->created_at->gte($since7));
        $undos7 = $undos->filter(fn($r) => $r->created_at && $r->created_at->gte($since7));

        $userNames = DB::table('users')
            ->whereIn('id', $rows->pluck('user_id')->filter()->unique()->values()->all())
            ->pluck('name', 'id');

        $dailyMap = [];
        for ($d = 29; $d >= 0; $d--) {
            $date = $now->copy()->subDays($d)->toDateString();
            $dailyMap[$date] = [
                'date' => $date,
                'import_runs' => 0,
                'undo_runs' => 0,
                'inserted' => 0,
                'updated' => 0,
                'skipped' => 0,
            ];
        }
        foreach ($rows as $row) {
            if (!$row->created_at) {
                continue;
            }
            $date = $row->created_at->toDateString();
            if (!isset($dailyMap[$date])) {
                continue;
            }
            $meta = is_array($row->meta) ? $row->meta : [];
            if ((string) $row->action === 'member_import') {
                $dailyMap[$date]['import_runs']++;
                $dailyMap[$date]['inserted'] += (int) ($meta['inserted'] ?? 0);
                $dailyMap[$date]['updated'] += (int) ($meta['updated'] ?? 0);
                $dailyMap[$date]['skipped'] += (int) ($meta['skipped'] ?? 0);
            } elseif ((string) $row->action === 'member_import_undo') {
                $dailyMap[$date]['undo_runs']++;
            }
        }
        $daily30 = array_values($dailyMap);
        $daily7 = array_slice($daily30, -7);

        return [
            'last_7d_import_runs' => (int) $imports7->count(),
            'last_7d_undo_runs' => (int) $undos7->count(),
            'last_7d_inserted' => (int) $imports7->sum(fn($r) => (int) (($r->meta['inserted'] ?? 0))),
            'last_7d_updated' => (int) $imports7->sum(fn($r) => (int) (($r->meta['updated'] ?? 0))),
            'last_7d_skipped' => (int) $imports7->sum(fn($r) => (int) (($r->meta['skipped'] ?? 0))),
            'last_30d_import_runs' => (int) $imports->count(),
            'last_30d_undo_runs' => (int) $undos->count(),
            'last_30d_inserted' => (int) $imports->sum(fn($r) => (int) (($r->meta['inserted'] ?? 0))),
            'last_30d_updated' => (int) $imports->sum(fn($r) => (int) (($r->meta['updated'] ?? 0))),
            'last_30d_skipped' => (int) $imports->sum(fn($r) => (int) (($r->meta['skipped'] ?? 0))),
            'last_30d_override_email_dup' => (int) $imports->filter(fn($r) => !empty($r->meta['force_email_duplicate']))->count(),
            'daily_7d' => $daily7,
            'daily_30d' => $daily30,
            'recent' => $rows->map(function ($r) use ($userNames) {
                $meta = is_array($r->meta) ? $r->meta : [];
                return [
                    'action' => (string) $r->action,
                    'status' => (string) ($r->status ?? ''),
                    'user_name' => (string) ($userNames[$r->user_id] ?? 'System'),
                    'inserted' => (int) ($meta['inserted'] ?? 0),
                    'updated' => (int) ($meta['updated'] ?? 0),
                    'skipped' => (int) ($meta['skipped'] ?? 0),
                    'force_email_duplicate' => !empty($meta['force_email_duplicate']),
                    'created_at' => $r->created_at,
                ];
            })->values()->all(),
        ];
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
            . '<dc:title>NOTOBUKU Member Import History</dc:title>'
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

    private function countOverdueMembers(int $institutionId): int
    {
        if (!Schema::hasTable('loans') || !Schema::hasTable('loan_items')) {
            return 0;
        }

        return (int) DB::table('loan_items as li')
            ->join('loans as l', 'l.id', '=', 'li.loan_id')
            ->where('l.institution_id', $institutionId)
            ->where('li.status', 'borrowed')
            ->whereNotNull('li.due_at')
            ->where('li.due_at', '<', now())
            ->distinct('l.member_id')
            ->count('l.member_id');
    }

    private function countUnpaidMembers(int $institutionId): int
    {
        if (!Schema::hasTable('fines')) {
            return 0;
        }

        return (int) DB::table('fines')
            ->where('institution_id', $institutionId)
            ->where('status', 'unpaid')
            ->distinct('member_id')
            ->count('member_id');
    }

    private function memberStats(int $institutionId, int $memberId): array
    {
        $activeLoans = 0;
        $overdueItems = 0;
        $unpaidFines = 0;

        if (Schema::hasTable('loans') && Schema::hasTable('loan_items')) {
            $activeLoans = (int) DB::table('loans')
                ->where('institution_id', $institutionId)
                ->where('member_id', $memberId)
                ->whereIn('status', ['open', 'overdue'])
                ->count();

            $overdueItems = (int) DB::table('loan_items as li')
                ->join('loans as l', 'l.id', '=', 'li.loan_id')
                ->where('l.institution_id', $institutionId)
                ->where('l.member_id', $memberId)
                ->where('li.status', 'borrowed')
                ->whereNotNull('li.due_at')
                ->where('li.due_at', '<', now())
                ->count();
        }

        if (Schema::hasTable('fines')) {
            $unpaidFines = (int) DB::table('fines')
                ->where('institution_id', $institutionId)
                ->where('member_id', $memberId)
                ->where('status', 'unpaid')
                ->sum('amount');
        }

        return [
            'active_loans' => $activeLoans,
            'overdue_items' => $overdueItems,
            'unpaid_fines' => $unpaidFines,
        ];
    }

    private function buildKpiSparklines(int $institutionId): array
    {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $days[] = now()->subDays($i)->toDateString();
        }
        $from = $days[0];
        $to = $days[count($days) - 1];

        $total = array_fill_keys($days, 0);
        $active = array_fill_keys($days, 0);
        $overdue = array_fill_keys($days, 0);
        $unpaid = array_fill_keys($days, 0);

        if (Schema::hasTable('members')) {
            $memberRows = DB::table('members')
                ->selectRaw('DATE(COALESCE(joined_at, created_at)) as day, COUNT(*) as cnt')
                ->where('institution_id', $institutionId)
                ->whereRaw('DATE(COALESCE(joined_at, created_at)) >= ?', [$from])
                ->whereRaw('DATE(COALESCE(joined_at, created_at)) <= ?', [$to])
                ->groupBy('day')
                ->get();
            foreach ($memberRows as $row) {
                $day = (string) ($row->day ?? '');
                if (isset($total[$day])) {
                    $total[$day] = (int) ($row->cnt ?? 0);
                }
            }

            $activeRows = DB::table('members')
                ->selectRaw('DATE(COALESCE(joined_at, created_at)) as day, COUNT(*) as cnt')
                ->where('institution_id', $institutionId)
                ->where('status', 'active')
                ->whereRaw('DATE(COALESCE(joined_at, created_at)) >= ?', [$from])
                ->whereRaw('DATE(COALESCE(joined_at, created_at)) <= ?', [$to])
                ->groupBy('day')
                ->get();
            foreach ($activeRows as $row) {
                $day = (string) ($row->day ?? '');
                if (isset($active[$day])) {
                    $active[$day] = (int) ($row->cnt ?? 0);
                }
            }
        }

        if (Schema::hasTable('loans') && Schema::hasTable('loan_items')) {
            $overdueRows = DB::table('loan_items as li')
                ->join('loans as l', 'l.id', '=', 'li.loan_id')
                ->selectRaw('DATE(li.due_at) as day, COUNT(DISTINCT l.member_id) as cnt')
                ->where('l.institution_id', $institutionId)
                ->where('li.status', 'borrowed')
                ->whereNotNull('li.due_at')
                ->whereRaw('DATE(li.due_at) >= ?', [$from])
                ->whereRaw('DATE(li.due_at) <= ?', [$to])
                ->groupBy('day')
                ->get();
            foreach ($overdueRows as $row) {
                $day = (string) ($row->day ?? '');
                if (isset($overdue[$day])) {
                    $overdue[$day] = (int) ($row->cnt ?? 0);
                }
            }
        }

        if (Schema::hasTable('fines')) {
            $unpaidRows = DB::table('fines')
                ->selectRaw('DATE(COALESCE(assessed_at, created_at)) as day, COUNT(DISTINCT member_id) as cnt')
                ->where('institution_id', $institutionId)
                ->where('status', 'unpaid')
                ->whereRaw('DATE(COALESCE(assessed_at, created_at)) >= ?', [$from])
                ->whereRaw('DATE(COALESCE(assessed_at, created_at)) <= ?', [$to])
                ->groupBy('day')
                ->get();
            foreach ($unpaidRows as $row) {
                $day = (string) ($row->day ?? '');
                if (isset($unpaid[$day])) {
                    $unpaid[$day] = (int) ($row->cnt ?? 0);
                }
            }
        }

        return [
            'labels' => $days,
            'total' => array_values($total),
            'active' => array_values($active),
            'overdue' => array_values($overdue),
            'unpaid' => array_values($unpaid),
        ];
    }

    private function memberRecentLoans(int $institutionId, int $memberId): array
    {
        if (!Schema::hasTable('loans')) {
            return [];
        }

        $query = DB::table('loans as l')
            ->where('l.institution_id', $institutionId)
            ->where('l.member_id', $memberId)
            ->orderByDesc('l.loaned_at')
            ->limit(8)
            ->select(['l.id', 'l.loan_code', 'l.status', 'l.loaned_at', 'l.due_at', 'l.closed_at']);

        return $query->get()->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'loan_code' => (string) $row->loan_code,
                'status' => (string) $row->status,
                'loaned_at' => $row->loaned_at,
                'due_at' => $row->due_at,
                'closed_at' => $row->closed_at,
            ];
        })->all();
    }
}
