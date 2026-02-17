<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class SearchSynonymController extends Controller
{
    private function currentInstitutionId(): int
    {
        $id = (int) (auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    private function canManage(): bool
    {
        $role = auth()->user()->role ?? 'member';
        return in_array($role, ['super_admin', 'admin', 'staff'], true);
    }

    public function index(Request $request)
    {
        abort_unless($this->canManage(), 403);

        $institutionId = $this->currentInstitutionId();
        $branchId = $request->query('branch_id');
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $rows = DB::table('search_synonyms')
            ->where('institution_id', $institutionId)
            ->when($branchId !== null && $branchId !== '', fn($query) => $query->where('branch_id', $branchId))
            ->when($q !== '', fn($query) => $query->where('term', 'like', '%' . $q . '%'))
            ->when($status !== '' && in_array($status, ['pending', 'approved', 'rejected'], true), fn($query) => $query->where('status', $status))
            ->orderBy('term')
            ->paginate(20)
            ->withQueryString();

        $branches = DB::table('branches')
            ->select('id', 'name')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        $zeroQueue = collect();
        if (Schema::hasTable('search_queries')) {
            $zeroQueue = DB::table('search_queries')
                ->where('institution_id', $institutionId)
                ->where('last_hits', '<=', 0)
                ->whereIn('zero_result_status', ['open', 'ignored'])
                ->orderByDesc('search_count')
                ->orderByDesc('last_searched_at')
                ->limit(20)
                ->get();
        }

        return view('admin.search-synonyms', [
            'rows' => $rows,
            'branches' => $branches,
            'q' => $q,
            'branchId' => $branchId,
            'statusFilter' => $status,
            'prefillTerm' => trim((string) $request->query('term', '')),
            'prefillSynonyms' => trim((string) $request->query('synonyms', '')),
            'zeroQueue' => $zeroQueue,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($this->canManage(), 403);

        $institutionId = $this->currentInstitutionId();
        $max = (int) config('search.synonym_max', 10);
        $data = $request->validate([
            'term' => ['required', 'string', 'max:120'],
            'synonyms' => ['required', 'string'],
            'branch_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:pending,approved'],
        ]);

        $term = trim($data['term']);
        $synonyms = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,\\n]+/', $data['synonyms'])))));
        $branchId = $data['branch_id'] ?? null;
        $status = (string) ($data['status'] ?? 'approved');

        if (empty($synonyms)) {
            return back()->withErrors(['synonyms' => 'Sinonim tidak boleh kosong.']);
        }
        if ($max > 0 && count($synonyms) > $max) {
            $synonyms = array_slice($synonyms, 0, $max);
        }

        $now = now();
        $existing = DB::table('search_synonyms')
            ->where('institution_id', $institutionId)
            ->where('branch_id', $branchId)
            ->where('term', $term)
            ->first();

        if ($existing) {
            $current = (array) json_decode((string) $existing->synonyms, true);
            $merged = array_values(array_unique(array_merge($current, $synonyms)));
            if ($max > 0 && count($merged) > $max) {
                $merged = array_slice($merged, 0, $max);
            }
            DB::table('search_synonyms')
                ->where('id', $existing->id)
                ->update([
                    'synonyms' => json_encode($merged),
                    'status' => $status,
                    'source' => 'manual',
                    'submitted_by' => auth()->id(),
                    'approved_by' => $status === 'approved' ? auth()->id() : null,
                    'approved_at' => $status === 'approved' ? $now : null,
                    'rejected_at' => null,
                    'rejection_note' => null,
                    'updated_at' => $now,
                ]);
        } else {
            DB::table('search_synonyms')->insert([
                'institution_id' => $institutionId,
                'branch_id' => $branchId,
                'term' => $term,
                'synonyms' => json_encode($synonyms),
                'status' => $status,
                'source' => 'manual',
                'submitted_by' => auth()->id(),
                'approved_by' => $status === 'approved' ? auth()->id() : null,
                'approved_at' => $status === 'approved' ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return back()->with('status', $status === 'approved' ? 'Sinonim tersimpan dan aktif.' : 'Sinonim tersimpan sebagai pending.');
    }

    public function destroy(int $id)
    {
        abort_unless($this->canManage(), 403);
        $institutionId = $this->currentInstitutionId();

        DB::table('search_synonyms')
            ->where('institution_id', $institutionId)
            ->where('id', $id)
            ->delete();

        return back()->with('status', 'Sinonim dihapus.');
    }

    public function syncAuto(Request $request)
    {
        abort_unless($this->canManage(), 403);

        $institutionId = $this->currentInstitutionId();
        $limit = (int) $request->input('limit', 300);
        $min = (int) $request->input('min', 2);
        $lev = (int) $request->input('lev', 2);
        $prefix = (int) $request->input('prefix', 3);
        $aggressive = (int) $request->input('aggressive', 1);

        Artisan::call('notobuku:sync-search-synonyms', [
            '--institution' => $institutionId,
            '--limit' => $limit,
            '--min' => $min,
            '--lev' => $lev,
            '--prefix' => $prefix,
            '--aggressive' => $aggressive,
        ]);

        return back()->with('status', 'Synonyms otomatis diperbarui.');
    }

    public function importCsv(Request $request)
    {
        abort_unless($this->canManage(), 403);

        $institutionId = $this->currentInstitutionId();
        $max = (int) config('search.synonym_max', 10);
        $data = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $branchId = $data['branch_id'] ?? null;
        $path = $request->file('csv_file')->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            return back()->withErrors(['csv_file' => 'Gagal membaca file.']);
        }

        $inserted = 0;
        $now = now();
        $line = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count($row) < 2) continue;
            $term = trim((string) $row[0]);
            $synRaw = trim((string) $row[1]);
            if ($term === '' || $synRaw === '') continue;
            $synonyms = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,\\n]+/', $synRaw)))));
            if (empty($synonyms)) continue;
            if ($max > 0 && count($synonyms) > $max) {
                $synonyms = array_slice($synonyms, 0, $max);
            }

            $existing = DB::table('search_synonyms')
                ->where('institution_id', $institutionId)
                ->where('branch_id', $branchId)
                ->where('term', $term)
                ->first();

            if ($existing) {
                $current = (array) json_decode((string) $existing->synonyms, true);
                $merged = array_values(array_unique(array_merge($current, $synonyms)));
                if ($max > 0 && count($merged) > $max) {
                    $merged = array_slice($merged, 0, $max);
                }
                DB::table('search_synonyms')->where('id', $existing->id)->update([
                    'synonyms' => json_encode($merged),
                    'status' => 'approved',
                    'source' => 'csv',
                    'submitted_by' => auth()->id(),
                    'approved_by' => auth()->id(),
                    'approved_at' => $now,
                    'rejected_at' => null,
                    'rejection_note' => null,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('search_synonyms')->insert([
                    'institution_id' => $institutionId,
                    'branch_id' => $branchId,
                    'term' => $term,
                    'synonyms' => json_encode($synonyms),
                    'status' => 'approved',
                    'source' => 'csv',
                    'submitted_by' => auth()->id(),
                    'approved_by' => auth()->id(),
                    'approved_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $inserted++;
            }
        }
        fclose($handle);

        return back()->with('status', "Import selesai. Ditambahkan {$inserted} istilah.");
    }

    public function previewImport(Request $request)
    {
        abort_unless($this->canManage(), 403);

        $max = (int) config('search.synonym_max', 10);
        $data = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $branchId = $data['branch_id'] ?? null;
        $path = $request->file('csv_file')->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            return back()->withErrors(['csv_file' => 'Gagal membaca file.']);
        }

        $rows = [];
        $errors = [];
        $dupEstimate = 0;
        $dupTerms = [];
        $seenTerms = [];
        $line = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count($row) < 2) {
                $errors[] = "Baris {$line} format tidak lengkap.";
                $rows[] = [
                    'term' => '',
                    'synonyms' => [],
                    'line' => $line,
                    'is_error' => true,
                    'is_duplicate' => false,
                    'error_reason' => 'Format tidak lengkap (butuh minimal 2 kolom).',
                    'duplicate_reason' => null,
                ];
                continue;
            }
            $term = trim((string) $row[0]);
            $synRaw = trim((string) $row[1]);
            if ($term === '' || $synRaw === '') {
                $errors[] = "Baris {$line} kosong / format salah.";
                $rows[] = [
                    'term' => $term,
                    'synonyms' => [],
                    'line' => $line,
                    'is_error' => true,
                    'is_duplicate' => false,
                    'error_reason' => 'Istilah atau sinonim kosong.',
                    'duplicate_reason' => null,
                ];
                continue;
            }
            $synonyms = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,\\n]+/', $synRaw)))));
            if (empty($synonyms)) {
                $errors[] = "Baris {$line} sinonim kosong.";
                $rows[] = [
                    'term' => $term,
                    'synonyms' => [],
                    'line' => $line,
                    'is_error' => true,
                    'is_duplicate' => false,
                    'error_reason' => 'Sinonim kosong setelah diproses.',
                    'duplicate_reason' => null,
                ];
                continue;
            }
            if ($max > 0 && count($synonyms) > $max) {
                $synonyms = array_slice($synonyms, 0, $max);
            }

            $dupInCsv = false;
            $dupReason = null;
            $termKey = mb_strtolower($term);
            if (isset($seenTerms[$termKey])) {
                $dupInCsv = true;
                $dupReason = 'Duplikat dalam CSV';
            }
            $seenTerms[$termKey] = true;

            $existing = DB::table('search_synonyms')
                ->where('institution_id', $this->currentInstitutionId())
                ->where('branch_id', $branchId)
                ->where('term', $term)
                ->first();
            if ($existing) {
                $dupEstimate++;
                $dupTerms[] = $term;
                if (!$dupInCsv) {
                    $dupInCsv = true;
                    $dupReason = 'Sudah ada di database';
                } elseif ($dupReason) {
                    $dupReason = $dupReason . ' + sudah ada di database';
                }
            }
            if ($dupInCsv && !$existing) {
                $dupEstimate++;
                $dupTerms[] = $term;
            }
            $rows[] = [
                'term' => $term,
                'synonyms' => $synonyms,
                'line' => $line,
                'is_error' => false,
                'is_duplicate' => $dupInCsv,
                'error_reason' => null,
                'duplicate_reason' => $dupReason,
            ];
        }
        fclose($handle);

        if (empty($rows)) {
            return back()->withErrors(['csv_file' => 'Tidak ada baris valid di CSV.']);
        }

        $request->session()->put('synonym_import_preview', [
            'branch_id' => $branchId,
            'rows' => $rows,
            'errors' => $errors,
            'dup_estimate' => $dupEstimate,
            'dup_terms' => array_values(array_unique($dupTerms)),
        ]);

        return back()->with('status', 'Preview siap. Periksa lalu klik Konfirmasi Import.');
    }

    public function cancelPreview(Request $request)
    {
        abort_unless($this->canManage(), 403);
        $request->session()->forget('synonym_import_preview');
        return back()->with('status', 'Preview dibatalkan.');
    }

    public function confirmImport(Request $request)
    {
        abort_unless($this->canManage(), 403);

        $institutionId = $this->currentInstitutionId();
        $max = (int) config('search.synonym_max', 10);
        $overwrite = (string) $request->input('overwrite', '') === '1';
        $payload = $request->session()->pull('synonym_import_preview');
        if (!$payload || empty($payload['rows'])) {
            return back()->withErrors(['csv_file' => 'Preview tidak ditemukan.']);
        }

        $branchId = $payload['branch_id'] ?? null;
        $rows = $payload['rows'];
        $now = now();
        $inserted = 0;
        $duplicates = 0;

        foreach ($rows as $row) {
            if (!empty($row['is_error'])) {
                continue;
            }
            $term = trim((string) ($row['term'] ?? ''));
            $synonyms = (array) ($row['synonyms'] ?? []);
            if ($term === '' || empty($synonyms)) continue;
            if ($max > 0 && count($synonyms) > $max) {
                $synonyms = array_slice($synonyms, 0, $max);
            }

            $existing = DB::table('search_synonyms')
                ->where('institution_id', $institutionId)
                ->where('branch_id', $branchId)
                ->where('term', $term)
                ->first();

            if ($existing) {
                if ($overwrite) {
                    $newSyn = $max > 0 ? array_slice($synonyms, 0, $max) : $synonyms;
                    DB::table('search_synonyms')->where('id', $existing->id)->update([
                        'synonyms' => json_encode($newSyn),
                        'status' => 'approved',
                        'source' => 'csv',
                        'submitted_by' => auth()->id(),
                        'approved_by' => auth()->id(),
                        'approved_at' => $now,
                        'rejected_at' => null,
                        'rejection_note' => null,
                        'updated_at' => $now,
                    ]);
                } else {
                    $current = (array) json_decode((string) $existing->synonyms, true);
                    $merged = array_values(array_unique(array_merge($current, $synonyms)));
                    if ($max > 0 && count($merged) > $max) {
                        $merged = array_slice($merged, 0, $max);
                    }
                    if ($merged === $current) {
                        $duplicates++;
                    } else {
                        DB::table('search_synonyms')->where('id', $existing->id)->update([
                            'synonyms' => json_encode($merged),
                            'status' => 'approved',
                            'source' => 'csv',
                            'submitted_by' => auth()->id(),
                            'approved_by' => auth()->id(),
                            'approved_at' => $now,
                            'rejected_at' => null,
                            'rejection_note' => null,
                            'updated_at' => $now,
                        ]);
                    }
                }
            } else {
                DB::table('search_synonyms')->insert([
                    'institution_id' => $institutionId,
                    'branch_id' => $branchId,
                    'term' => $term,
                    'synonyms' => json_encode($synonyms),
                    'status' => 'approved',
                    'source' => 'csv',
                    'submitted_by' => auth()->id(),
                    'approved_by' => auth()->id(),
                    'approved_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $inserted++;
            }
        }

        $msg = "Import dikonfirmasi. Ditambahkan {$inserted} istilah.";
        if ($duplicates > 0) {
            $msg .= " Duplikat di-skip: {$duplicates}.";
        }
        return back()->with('status', $msg);
    }

    public function downloadTemplate()
    {
        abort_unless($this->canManage(), 403);
        $csv = "istilah,sinonim\n".
               "ilkom,ilmu komputer; informatika; computer science\n".
               "pemrograman,programming; coding; koding\n";
        $filename = 'template-sinonim.csv';
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function downloadErrorCsv(Request $request)
    {
        abort_unless($this->canManage(), 403);
        $payload = $request->session()->get('synonym_import_preview');
        if (!$payload || empty($payload['rows'])) {
            return back()->withErrors(['csv_file' => 'Preview tidak ditemukan.']);
        }

        $rows = $payload['rows'] ?? [];
        $errors = [];
        foreach ($rows as $row) {
            if (!empty($row['is_error'])) {
                $errors[] = [
                    'line' => $row['line'] ?? '',
                    'term' => $row['term'] ?? '',
                    'reason' => $row['error_reason'] ?? 'Format / sinonim tidak valid',
                ];
            }
        }

        if (empty($errors)) {
            return back()->with('status', 'Tidak ada error untuk diunduh.');
        }

        $out = "baris,istilah,alasan\n";
        foreach ($errors as $err) {
            $out .= ($err['line'] ?? '') . "," . str_replace(',', ' ', (string) $err['term']) . "," . $err['reason'] . "\n";
        }

        return response($out, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="error-sinonim.csv"',
        ]);
    }

    public function downloadDuplicateCsv(Request $request)
    {
        abort_unless($this->canManage(), 403);
        $payload = $request->session()->get('synonym_import_preview');
        if (!$payload || empty($payload['rows'])) {
            return back()->withErrors(['csv_file' => 'Preview tidak ditemukan.']);
        }

        $rows = $payload['rows'] ?? [];
        $dups = [];
        foreach ($rows as $row) {
            if (!empty($row['is_duplicate']) && empty($row['is_error'])) {
                $dups[] = [
                    'term' => $row['term'] ?? '',
                    'synonyms' => implode('; ', (array) ($row['synonyms'] ?? [])),
                    'reason' => $row['duplicate_reason'] ?? 'Duplikat',
                ];
            }
        }

        if (empty($dups)) {
            return back()->with('status', 'Tidak ada duplikat untuk diunduh.');
        }

        $out = "istilah,sinonim,alasan\n";
        foreach ($dups as $dup) {
            $out .= str_replace(',', ' ', (string) $dup['term']) . "," . str_replace(',', ' ', (string) $dup['synonyms']) . "," . str_replace(',', ' ', (string) $dup['reason']) . "\n";
        }

        return response($out, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=\"duplikat-sinonim.csv\"',
        ]);
    }

    public function downloadPreviewCsv(Request $request)
    {
        abort_unless($this->canManage(), 403);
        $payload = $request->session()->get('synonym_import_preview');
        if (!$payload || empty($payload['rows'])) {
            return back()->withErrors(['csv_file' => 'Preview tidak ditemukan.']);
        }

        $rows = $payload['rows'] ?? [];
        $out = "istilah,sinonim,status,alasan\n";
        foreach ($rows as $row) {
            $term = (string) ($row['term'] ?? '');
            $syn = implode('; ', (array) ($row['synonyms'] ?? []));
            $status = !empty($row['is_error']) ? 'error' : (!empty($row['is_duplicate']) ? 'duplikat' : 'ok');
            $reason = '';
            if (!empty($row['is_error'])) {
                $reason = (string) ($row['error_reason'] ?? 'Format / sinonim tidak valid');
            } elseif (!empty($row['is_duplicate'])) {
                $reason = (string) ($row['duplicate_reason'] ?? 'Duplikat');
            }
            $out .= str_replace(',', ' ', $term) . "," . str_replace(',', ' ', $syn) . "," . $status . "," . str_replace(',', ' ', $reason) . "\n";
        }

        return response($out, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=\"preview-sinonim.csv\"',
        ]);
    }

    public function resolveZeroResult(Request $request, int $id)
    {
        abort_unless($this->canManage(), 403);
        $institutionId = $this->currentInstitutionId();

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:resolved,ignored,open'],
            'term' => ['nullable', 'string', 'max:120'],
            'synonyms' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'integer'],
            'use_auto_suggestion' => ['nullable', 'boolean'],
        ]);

        $row = DB::table('search_queries')
            ->where('institution_id', $institutionId)
            ->where('id', $id)
            ->first();
        if (!$row) {
            return back()->withErrors(['status' => 'Query zero-result tidak ditemukan.']);
        }

        $status = (string) ($data['status'] ?? 'resolved');
        $note = trim((string) ($data['note'] ?? ''));
        $link = null;

        $term = trim((string) ($data['term'] ?? ''));
        $synonymsRaw = trim((string) ($data['synonyms'] ?? ''));
        $useAutoSuggestion = (bool) ($data['use_auto_suggestion'] ?? false);
        if ($useAutoSuggestion && $term === '' && $synonymsRaw === '') {
            $term = (string) ($row->normalized_query ?: $row->query);
            $synonymsRaw = trim((string) ($row->auto_suggestion_query ?? ''));
        }
        if ($term !== '' && $synonymsRaw !== '') {
            $synonyms = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,\n]+/', $synonymsRaw)))));
            if (!empty($synonyms)) {
                $branchId = $data['branch_id'] ?? null;
                $existing = DB::table('search_synonyms')
                    ->where('institution_id', $institutionId)
                    ->where('branch_id', $branchId)
                    ->where('term', $term)
                    ->first();
                $now = now();
                if ($existing) {
                    $current = (array) json_decode((string) $existing->synonyms, true);
                    $merged = array_values(array_unique(array_merge($current, $synonyms)));
                    DB::table('search_synonyms')->where('id', $existing->id)->update([
                        'synonyms' => json_encode($merged),
                        'status' => 'approved',
                        'source' => 'zero_result',
                        'submitted_by' => auth()->id(),
                        'approved_by' => auth()->id(),
                        'approved_at' => $now,
                        'rejected_at' => null,
                        'rejection_note' => null,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('search_synonyms')->insert([
                        'institution_id' => $institutionId,
                        'branch_id' => $branchId,
                        'term' => $term,
                        'synonyms' => json_encode($synonyms),
                        'status' => 'approved',
                        'source' => 'zero_result',
                        'submitted_by' => auth()->id(),
                        'approved_by' => auth()->id(),
                        'approved_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $link = route('admin.search_synonyms', ['term' => $term]);
                if ($note === '') {
                    $note = 'Resolved via sinonim: ' . $term;
                }
            }
        }

        DB::table('search_queries')
            ->where('institution_id', $institutionId)
            ->where('id', $id)
            ->update([
                'zero_result_status' => $status,
                'zero_resolved_at' => in_array($status, ['resolved', 'ignored', 'resolved_auto'], true) ? now() : null,
                'zero_resolved_by' => in_array($status, ['resolved', 'ignored', 'resolved_auto'], true) ? auth()->id() : null,
                'zero_resolution_note' => $note !== '' ? $note : null,
                'zero_resolution_link' => $link,
                'auto_suggestion_status' => $status === 'resolved' ? 'approved' : ($status === 'ignored' ? 'rejected' : 'open'),
                'updated_at' => now(),
            ]);

        return back()->with('status', 'Zero-result queue diperbarui.');
    }

    public function approve(int $id)
    {
        abort_unless($this->canManage(), 403);
        $institutionId = $this->currentInstitutionId();

        DB::table('search_synonyms')
            ->where('institution_id', $institutionId)
            ->where('id', $id)
            ->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_note' => null,
                'updated_at' => now(),
            ]);

        return back()->with('status', 'Sinonim disetujui.');
    }

    public function reject(Request $request, int $id)
    {
        abort_unless($this->canManage(), 403);
        $institutionId = $this->currentInstitutionId();
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::table('search_synonyms')
            ->where('institution_id', $institutionId)
            ->where('id', $id)
            ->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_note' => trim((string) ($data['note'] ?? '')) ?: 'Ditolak operator',
                'approved_by' => null,
                'approved_at' => null,
                'updated_at' => now(),
            ]);

        return back()->with('status', 'Sinonim ditolak.');
    }
}
