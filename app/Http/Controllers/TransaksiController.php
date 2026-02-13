<?php
namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\ReservationService;
use App\Services\LoanPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TransaksiController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | PINJAM - FORM
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request); // cabang wajib utk admin/staff (super_admin bebas)

        $institutionId = $this->currentInstitutionId();
        $branchId = $this->staffBranchId();

        $sampleBarcodesQ = DB::table('items')
            ->where('institution_id', $institutionId)
            ->whereNotNull('barcode')
            ->where('status', 'available')
            ->orderByDesc('id')
            ->limit(8);

        // lock cabang utk admin/staff
        if ($this->shouldLockBranch()) {
            if (Schema::hasColumn('items', 'branch_id')) {
                $sampleBarcodesQ->where(function ($w) use ($branchId) {
                    $w->whereNull('items.branch_id')
                      ->orWhere('items.branch_id', (int)$branchId);
                });
            }
        }

        $sampleBarcodes = $sampleBarcodesQ->pluck('barcode')->values()->all();

        return view('transaksi.pinjam', [
            'title' => 'Transaksi Pinjam',
            'sampleBarcodes' => $sampleBarcodes,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PINJAM - SUCCESS PAGE
    |--------------------------------------------------------------------------
    */
    public function pinjamSuccess($id)
    {
        $this->ensureStaff();
        $this->requireStaffBranch();

        $institutionId = $this->currentInstitutionId();
        $branchId = $this->staffBranchId();

        $loanQ = DB::table('loans')
            ->join('members', 'members.id', '=', 'loans.member_id')
            ->leftJoin('branches', 'branches.id', '=', 'loans.branch_id')
            ->leftJoin('users', 'users.id', '=', 'loans.created_by')
            ->where('loans.institution_id', $institutionId)
            ->where('loans.id', (int)$id)
            ->select([
                'loans.*',
                'members.id as member_id',
                'members.full_name as member_name',
                'members.member_code as member_code',
                'members.phone as member_phone',
                DB::raw('COALESCE(branches.name, "-") as branch_name'),
                DB::raw('COALESCE(users.name, users.username, "-") as created_by_name'),
            ]);

        if ($this->shouldLockBranch()) {
            $loanQ->where('loans.branch_id', (int)$branchId);
        }

        $loan = $loanQ->first();
        if (!$loan) abort(404);

        $biblioTable = null;
        if (Schema::hasTable('biblios')) $biblioTable = 'biblios';
        elseif (Schema::hasTable('biblio')) $biblioTable = 'biblio';

        $itemsQ = DB::table('loan_items')
            ->join('items', 'items.id', '=', 'loan_items.item_id')
            ->where('loan_items.loan_id', (int)$id)
            ->select([
                'loan_items.id as loan_item_id',
                'loan_items.status as loan_item_status',
                'loan_items.borrowed_at',
                'loan_items.due_at as item_due_at',
                Schema::hasColumn('loan_items', 'due_date')
                    ? 'loan_items.due_date as item_due_date'
                    : DB::raw('NULL as item_due_date'),
                'loan_items.returned_at',
                Schema::hasColumn('loan_items', 'renew_count')
                    ? 'loan_items.renew_count'
                    : DB::raw('0 as renew_count'),
                'items.id as item_id',
                'items.barcode',
                'items.accession_number',
                'items.status as item_status',
                'items.biblio_id',
            ]);

        if ($this->shouldLockBranch()) {
            if (Schema::hasColumn('items', 'branch_id')) {
                $itemsQ->where(function ($w) use ($branchId) {
                    $w->whereNull('items.branch_id')
                      ->orWhere('items.branch_id', (int)$branchId);
                });
            }
        }

        if ($biblioTable) {
            $itemsQ->leftJoin($biblioTable, "{$biblioTable}.id", '=', 'items.biblio_id');
            $itemsQ->addSelect([
                DB::raw("{$biblioTable}.title as title"),
                DB::raw("{$biblioTable}.call_number as call_number"),
            ]);
        } else {
            $itemsQ->addSelect([
                DB::raw("NULL as title"),
                DB::raw("NULL as call_number"),
            ]);
        }

        $items = $itemsQ->orderByDesc('loan_items.id')->get();

        // fallback cabang bila loans.branch_id null (data lama)
        try {
            $branchName = (string)($loan->branch_name ?? '-');
            $hasLoanBranch = !empty($loan->branch_id);

            if (!$hasLoanBranch && ($branchName === '-' || trim($branchName) === '')) {
                if (Schema::hasColumn('items', 'branch_id')) {
                    $fallbackQ = DB::table('loan_items')
                        ->join('items', 'items.id', '=', 'loan_items.item_id')
                        ->leftJoin('branches', 'branches.id', '=', 'items.branch_id')
                        ->where('loan_items.loan_id', (int)$id)
                        ->whereNotNull('items.branch_id')
                        ->orderByDesc('loan_items.id');

                    if ($this->shouldLockBranch()) {
                        $fallbackQ->where('items.branch_id', (int)$branchId);
                    }

                    $fallback = $fallbackQ->value('branches.name');

                    if (!empty($fallback)) {
                        $loan->branch_name = (string)$fallback;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return view('transaksi.pinjam_success', [
            'title' => 'Transaksi Berhasil',
            'loan' => $loan,
            'items' => $items,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | KEMBALI - SUCCESS PAGE
    |--------------------------------------------------------------------------
    */
    public function kembaliSuccess($id)
    {
        $this->ensureStaff();
        $this->requireStaffBranch();

        $institutionId = $this->currentInstitutionId();
        $branchId = $this->staffBranchId();

        $loanQ = DB::table('loans')
            ->join('members', 'members.id', '=', 'loans.member_id')
            ->leftJoin('branches', 'branches.id', '=', 'loans.branch_id')
            ->leftJoin('users', 'users.id', '=', 'loans.created_by')
            ->where('loans.institution_id', $institutionId)
            ->where('loans.id', (int)$id)
            ->select([
                'loans.*',
                'members.id as member_id',
                'members.full_name as member_name',
                'members.member_code as member_code',
                'members.phone as member_phone',
                DB::raw('COALESCE(branches.name, "-") as branch_name'),
                DB::raw('COALESCE(users.name, users.username, "-") as created_by_name'),
            ]);

        if ($this->shouldLockBranch()) {
            $loanQ->where('loans.branch_id', (int)$branchId);
        }

        $loan = $loanQ->first();
        if (!$loan) abort(404);

        $biblioTable = null;
        if (Schema::hasTable('biblios')) $biblioTable = 'biblios';
        elseif (Schema::hasTable('biblio')) $biblioTable = 'biblio';

        $itemsQ = DB::table('loan_items')
            ->join('items', 'items.id', '=', 'loan_items.item_id')
            ->where('loan_items.loan_id', (int)$id)
            ->select([
                'loan_items.id as loan_item_id',
                'loan_items.status as loan_item_status',
                'loan_items.borrowed_at',
                'loan_items.due_at as item_due_at',
                Schema::hasColumn('loan_items', 'due_date')
                    ? 'loan_items.due_date as item_due_date'
                    : DB::raw('NULL as item_due_date'),
                'loan_items.returned_at',
                'items.id as item_id',
                'items.barcode',
                'items.accession_number',
                'items.status as item_status',
                'items.biblio_id',
            ]);

        if ($this->shouldLockBranch()) {
            if (Schema::hasColumn('items', 'branch_id')) {
                $itemsQ->where(function ($w) use ($branchId) {
                    $w->whereNull('items.branch_id')
                      ->orWhere('items.branch_id', (int)$branchId);
                });
            }
        }

        if ($biblioTable) {
            $itemsQ->leftJoin($biblioTable, "{$biblioTable}.id", '=', 'items.biblio_id');
            $itemsQ->addSelect([
                DB::raw("{$biblioTable}.title as title"),
                DB::raw("{$biblioTable}.call_number as call_number"),
            ]);
        } else {
            $itemsQ->addSelect([
                DB::raw("NULL as title"),
                DB::raw("NULL as call_number"),
            ]);
        }

        $items = $itemsQ->orderByDesc('loan_items.id')->get();

        // fallback cabang bila loans.branch_id null (data lama)
        try {
            $branchName = (string)($loan->branch_name ?? '-');
            $hasLoanBranch = !empty($loan->branch_id);

            if (!$hasLoanBranch && ($branchName === '-' || trim($branchName) === '')) {
                if (Schema::hasColumn('items', 'branch_id')) {
                    $fallbackQ = DB::table('loan_items')
                        ->join('items', 'items.id', '=', 'loan_items.item_id')
                        ->leftJoin('branches', 'branches.id', '=', 'items.branch_id')
                        ->where('loan_items.loan_id', (int)$id)
                        ->whereNotNull('items.branch_id')
                        ->orderByDesc('loan_items.id');

                    if ($this->shouldLockBranch()) {
                        $fallbackQ->where('items.branch_id', (int)$branchId);
                    }

                    $fallback = $fallbackQ->value('branches.name');

                    if (!empty($fallback)) {
                        $loan->branch_name = (string)$fallback;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $kembaliResult = session('kembali_result', []);
        $returnedLoanItemIds = array_values(array_unique(array_map('intval', $kembaliResult['loan_item_ids'] ?? [])));
        $batchLoanIds = array_values(array_unique(array_map('intval', $kembaliResult['loan_ids'] ?? [])));

        return view('transaksi.kembali_success', [
            'title' => 'Pengembalian Berhasil',
            'loan' => $loan,
            'items' => $items,
            'returned_loan_item_ids' => $returnedLoanItemIds,
            'batch_loan_ids' => $batchLoanIds,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PINJAM - AJAX: Cari Member
    |--------------------------------------------------------------------------
    */
    public function cariMember(Request $request)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request);

        $q = trim((string)$request->query('q', ''));
        $institutionId = $this->currentInstitutionId();

        if ($q === '') {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $memberCols = ['id', 'member_code', 'full_name', 'phone', 'status'];
        if (Schema::hasColumn('members', 'member_type')) {
            $memberCols[] = 'member_type';
        }

        $rows = DB::table('members')
            ->select($memberCols)
            ->where('institution_id', $institutionId)
            ->where('status', 'active')
            ->where(function ($w) use ($q) {
                $w->where('full_name', 'like', "%{$q}%")
                  ->orWhere('member_code', 'like', "%{$q}%");
            })
            ->orderBy('full_name')
            ->limit(20)
            ->get();
        $policySvc = app(LoanPolicyService::class);

        $mapped = $rows->map(function ($m) use ($policySvc) {
            $role = $policySvc->resolveMemberRole($m);
            $policy = $policySvc->forRole($role);
            return [
                'id' => (int)$m->id,
                'name' => (string)$m->full_name,
                'username' => (string)$m->member_code,
                'email' => (string)($m->phone ?? '-'),
                'label' => (string)$m->full_name . ' • ' . (string)$m->member_code,
                'role' => (string)($m->member_type ?? $role),
                'max_items' => (int)($policy['max_items'] ?? 0),
            ];
        });

        return response()->json(['ok' => true, 'data' => $mapped]);
    }

    /*
    |--------------------------------------------------------------------------
    | PINJAM - AJAX: Info Member (policy + active count)
    |--------------------------------------------------------------------------
    */
    public function memberInfo(Request $request, int $id)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request);

        $institutionId = $this->currentInstitutionId();
        $branchId = $this->staffBranchId();

        $memberInfoCols = ['id', 'institution_id', 'status', 'full_name', 'member_code'];
        if (Schema::hasColumn('members', 'member_type')) {
            $memberInfoCols[] = 'member_type';
        }

        $member = DB::table('members')
            ->select($memberInfoCols)
            ->where('id', $id)
            ->first();

        if (!$member || (int)$member->institution_id !== (int)$institutionId) {
            return response()->json(['ok' => false, 'message' => 'Member tidak ditemukan.'], 404);
        }

        $policySvc = app(LoanPolicyService::class);
        $role = $policySvc->resolveMemberRole($member);
        $policy = $policySvc->forRole($role);

        $liHasReturnedAt = Schema::hasColumn('loan_items', 'returned_at');
        $loanHasReturnedAt = Schema::hasColumn('loans', 'returned_at');

        $activeItemsQ = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->join('items', 'items.id', '=', 'loan_items.item_id')
            ->where('loans.institution_id', $institutionId)
            ->where('loans.member_id', (int)$member->id);

        if ($liHasReturnedAt) {
            $activeItemsQ->whereNull('loan_items.returned_at');
        } elseif ($loanHasReturnedAt) {
            $activeItemsQ->whereNull('loans.returned_at');
        }

        if ($this->shouldLockBranch()) {
            if (Schema::hasColumn('items', 'branch_id')) {
                $activeItemsQ->where(function ($w) use ($branchId) {
                    $w->whereNull('items.branch_id')
                      ->orWhere('items.branch_id', (int)$branchId);
                });
            }
        }

        $activeItems = (int)$activeItemsQ->count();

        return response()->json([
            'ok' => true,
            'data' => [
                'member_id' => (int)$member->id,
                'member_name' => (string)$member->full_name,
                'member_code' => (string)$member->member_code,
                'member_type' => (string)($member->member_type ?? $role),
                'status' => (string)$member->status,
                'active_items' => $activeItems,
                'policy' => $policy,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PINJAM - AJAX: Cek Barcode
    |--------------------------------------------------------------------------
    */
    public function cekBarcode(Request $request)
    {
        $this->ensureStaff();

        $barcode = trim((string)$request->query('barcode', ''));
        $institutionId = $this->currentInstitutionId();

        $staffBranchId = (int)($this->staffBranchId() ?? 0);

        if ($barcode === '') {
            return response()->json(['ok' => false, 'message' => 'Barcode kosong.'], 422);
        }

        $item = Item::query()
            ->where('institution_id', $institutionId)
            ->where('barcode', $barcode)
            ->first();

        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Barcode tidak ditemukan.'], 404);
        }

        if (!Schema::hasColumn('items', 'branch_id')) {
            return response()->json([
                'ok' => false,
                'message' => 'Konfigurasi database belum mendukung branch_id pada item.',
            ], 500);
        }

        $itemBranchId = (int)($item->branch_id ?? 0);
        if ($itemBranchId <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Item belum memiliki cabang (branch kosong). Tidak bisa dipinjam sebelum diperbaiki.',
            ], 422);
        }

        // lock hanya utk admin/staff; super_admin bebas lintas cabang
        if (!$this->isSuperAdmin() && $this->shouldLockBranch() && $itemBranchId !== (int)$staffBranchId) {
            return response()->json([
                'ok' => false,
                'message' => 'Item ini dari cabang lain. Akses dibatasi sesuai cabang akun Anda.',
            ], 422);
        }


        $status = (string)($item->status ?? 'available');

        // izinkan scan reserved, validasi final tetap di storePinjam
        if (!in_array($status, ['available', 'reserved'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Item tidak tersedia. Status: ' . (string)$item->status,
            ], 422);
        }

        $title = null;
        try {
            if (method_exists($item, 'biblio') && $item->biblio) {
                $title = (string)($item->biblio->title ?? null);
            }
        } catch (\Throwable $e) {
            $title = null;
        }

        $reservationInfo = null;
        if ($status === 'reserved') {
            try {
                if (Schema::hasTable('reservations') && Schema::hasColumn('reservations', 'item_id')) {
                    $r = DB::table('reservations')
                        ->where('institution_id', $institutionId)
                        ->where('status', 'ready')
                        ->where('item_id', (int)$item->id)
                        ->whereNull('fulfilled_at')
                        ->where(function ($w) {
                            $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        })
                        ->orderByDesc('id')
                        ->first();

                    if ($r) {
                        $reservationInfo = [
                            'member_id' => (int)($r->member_id ?? 0),
                            'expires_at' => $r->expires_at,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $reservationInfo = null;
            }
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => (int)$item->id,
                'barcode' => (string)$item->barcode,
                'status' => $status,
                'branch_id' => $itemBranchId,
                'title' => $title,
                'reservation' => $reservationInfo,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PINJAM - SIMPAN
    |--------------------------------------------------------------------------
    */
    public function storePinjam(Request $request)
    {
        $this->ensureStaff();

        // cabang WAJIB dari user login, tidak boleh dari request
        if ($request->has('branch_id')) {
            return back()->withInput()->with('error', 'Request tidak valid (branch_id tidak boleh dikirim).');
        }

        $institutionId = $this->currentInstitutionId();
        $user = Auth::user();

        $staffBranchId = (int)($this->staffBranchId() ?? 0);
        $role = (string)($user?->role ?? 'member');

        // admin/staff wajib punya cabang; super_admin boleh lintas cabang
        if ($staffBranchId <= 0 && $role !== 'super_admin') {
            $msg = 'Akun Anda belum memiliki cabang. Set branch_id pada user terlebih dahulu.';
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $msg], 422);
            }
            abort(403, $msg);
        }

        if (!Schema::hasColumn('items', 'branch_id') || !Schema::hasColumn('loans', 'branch_id')) {
            abort(500, 'Konfigurasi database belum mendukung branch_id untuk transaksi pinjam.');
        }

        $data = $request->validate([
            'member_id' => ['required', 'integer'],
            'barcodes' => ['required', 'array', 'min:1', 'max:20'],
            'barcodes.*' => ['required', 'string', 'max:80'],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $memberId = (int)$data['member_id'];

        $barcodes = collect($data['barcodes'])
            ->map(fn($x) => trim((string)$x))
            ->filter()
            ->unique()
            ->values();

        if ($barcodes->isEmpty()) {
            return back()->withInput()->with('error', 'Minimal 1 barcode harus diisi.');
        }

        $storeMemberCols = ['id', 'institution_id', 'status', 'full_name', 'member_code'];
        if (Schema::hasColumn('members', 'member_type')) {
            $storeMemberCols[] = 'member_type';
        }

        $member = DB::table('members')
            ->select($storeMemberCols)
            ->where('id', $memberId)
            ->first();

        if (!$member) {
            return back()->withInput()->with('error', 'Member tidak ditemukan.');
        }
        if ((int)$member->institution_id !== (int)$institutionId) {
            return back()->withInput()->with('error', 'Member bukan dari institusi yang sama.');
        }
        if (!in_array((string)$member->status, ['active'], true)) {
            return back()->withInput()->with('error', 'Member tidak aktif. Status: ' . (string)$member->status);
        }

        $policySvc = app(LoanPolicyService::class);
        $policy = $policySvc->forRole($policySvc->resolveMemberRole($member));
        $loanDefaultDays = (int)($policy['default_days'] ?? 7);
        if ($loanDefaultDays <= 0) $loanDefaultDays = 7;

        $loanMaxItems = (int)($policy['max_items'] ?? 3);
        if ($loanMaxItems <= 0) $loanMaxItems = 3;

        $activeItemsCount = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->where('loans.member_id', $memberId)
            ->whereNull('loan_items.returned_at')
            ->count();

        if (($activeItemsCount + $barcodes->count()) > $loanMaxItems) {
            return back()->withInput()->with(
                'error',
                "Batas pinjam aktif tercapai. Maksimal {$loanMaxItems} buku per orang. Saat ini aktif: {$activeItemsCount}."
            );
        }

        $dueAt = !empty($data['due_at'])
            ? date('Y-m-d H:i:s', strtotime((string)$data['due_at']))
            : date('Y-m-d H:i:s', strtotime('+' . $loanDefaultDays . ' days'));

        $notes = isset($data['notes']) ? trim((string)$data['notes']) : null;
        $notes = ($notes !== '' ? $notes : null);

        try {
            $result = DB::transaction(function () use (
                $institutionId,
                $memberId,
                $barcodes,
                $dueAt,
                $notes,
                $staffBranchId,
                $user
            ) {
                $items = Item::query()
                    ->where('institution_id', $institutionId)
                    ->whereIn('barcode', $barcodes->all())
                    ->lockForUpdate()
                    ->get();
        $policySvc = app(LoanPolicyService::class);

                if ($items->count() !== $barcodes->count()) {
                    $found = $items->pluck('barcode')->all();
                    $missing = $barcodes->reject(fn($b) => in_array($b, $found, true))->values()->all();
                    throw new \RuntimeException('Barcode tidak ditemukan: ' . implode(', ', $missing));
                }

                $role = (string)($user?->role ?? 'member');
                $branchIds = $items->map(fn($it) => (int)($it->branch_id ?? 0))->unique()->values();

                if ($branchIds->isEmpty() || $branchIds->contains(fn($x) => (int)$x <= 0)) {
                    $list = $items->map(function ($it) {
                        $bid = (int)($it->branch_id ?? 0);
                        return $it->barcode . ' (cabang item ' . ($bid > 0 ? $bid : 'kosong') . ')';
                    })->values()->all();

                    throw new \RuntimeException('Item belum memiliki cabang (branch kosong). Perbaiki data: ' . implode(', ', $list));
                }

                $loanBranchId = (int)$staffBranchId;

                if ($role === 'super_admin') {
                    if ($branchIds->count() !== 1) {
                        throw new \RuntimeException('Transaksi pinjam harus 1 cabang. Item terdeteksi multi-cabang: ' . implode(', ', $branchIds->all()));
                    }
                    $loanBranchId = (int)$branchIds->first();
                } else {
                    if ($this->shouldLockBranch()) {
                        $bad = $items->filter(fn($it) => (int)($it->branch_id ?? 0) !== (int)$staffBranchId);
                        if ($bad->isNotEmpty()) {
                            $list = $bad->map(fn($it) => $it->barcode . ' (cabang item ' . (int)($it->branch_id ?? 0) . ' â‰  cabang akun ' . (int)$staffBranchId . ')')->values()->all();
                            throw new \RuntimeException('Validasi cabang gagal (ditolak): ' . implode(', ', $list));
                        }
                    }
                }

                $allowReservedFlow = Schema::hasTable('reservations') && Schema::hasColumn('reservations', 'item_id');

                $notAllowed = collect();
                foreach ($items as $it) {
                    $st = (string)($it->status ?? '');

                    if ($st === 'available') {
                        continue;
                    }

                    if ($st === 'reserved' && $allowReservedFlow) {
                        $ok = DB::table('reservations')
                            ->where('institution_id', $institutionId)
                            ->where('status', 'ready')
                            ->where('item_id', (int)$it->id)
                            ->where('member_id', $memberId)
                            ->whereNull('fulfilled_at')
                            ->where(function ($w) {
                                $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
                            })
                            ->exists();

                        if ($ok) {
                            continue;
                        }
                    }

                    $notAllowed->push($it);
                }

                if ($notAllowed->isNotEmpty()) {
                    $list = $notAllowed->map(fn($it) => $it->barcode . ' (' . (string)$it->status . ')')->values()->all();
                    throw new \RuntimeException('Ada item tidak tersedia / tidak boleh dipinjam: ' . implode(', ', $list));
                }

                $reservedItemIdsForMember = [];
                if ($allowReservedFlow) {
                    $reservedItemIdsForMember = $items
                        ->filter(fn($it) => (string)($it->status ?? '') === 'reserved')
                        ->pluck('id')
                        ->map(fn($x) => (int)$x)
                        ->values()
                        ->all();
                }

                $loanCode = $this->generateLoanCode();

                $loanId = DB::table('loans')->insertGetId([
                    'institution_id' => $institutionId,
                    'branch_id' => (int)$loanBranchId,
                    'member_id' => $memberId,
                    'loan_code' => $loanCode,
                    'status' => 'open',
                    'loaned_at' => now(),
                    'due_at' => $dueAt,
                    'closed_at' => null,
                    'created_by' => $user?->id,
                    'notes' => $notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($items as $it) {
                    $dueDate = null;
                    if (Schema::hasColumn('loan_items', 'due_date')) {
                        try {
                            $dueDate = date('Y-m-d', strtotime((string)$dueAt));
                        } catch (\Throwable $e) {
                            $dueDate = null;
                        }
                    }

                    $insert = [
                        'loan_id' => $loanId,
                        'item_id' => $it->id,
                        'status' => 'borrowed',
                        'borrowed_at' => now(),
                        'due_at' => $dueAt,
                        'due_date' => Schema::hasColumn('loan_items', 'due_date') ? $dueDate : null,
                        'returned_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    if (Schema::hasColumn('loan_items', 'renew_count')) {
                        $insert['renew_count'] = 0;
                    }
                    DB::table('loan_items')->insert($insert);

                    $it->status = 'borrowed';
                    $it->save();
                }

                if ($allowReservedFlow && !empty($reservedItemIdsForMember)) {
                    DB::table('reservations')
                        ->where('institution_id', $institutionId)
                        ->where('member_id', $memberId)
                        ->whereIn('item_id', $reservedItemIdsForMember)
                        ->where('status', 'ready')
                        ->whereNull('fulfilled_at')
                        ->update([
                            'status' => 'fulfilled',
                            'fulfilled_at' => now(),
                            'updated_at' => now(),
                        ]);
                }

                return [
                    'ok' => true,
                    'loan_id' => $loanId,
                    'loan_code' => $loanCode,
                ];
            }, 3);

            return redirect()
                ->route('transaksi.pinjam.success', ['id' => $result['loan_id']])
                ->with('success', 'Transaksi pinjam berhasil dibuat. Kode: ' . $result['loan_code']);

        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | KEMBALI - FORM
    |--------------------------------------------------------------------------
    */
    public function kembaliForm(Request $request)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request);

        $loanId = (int)$request->query('loan_id', 0);

        return view('transaksi.kembali', [
            'title' => 'Transaksi Kembali',
            'loan_id' => $loanId,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | KEMBALI - AJAX: Cek Barcode
    |--------------------------------------------------------------------------
    */
    public function cekBarcodeKembali(Request $request)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request);

        $barcode = trim((string)$request->query('barcode', ''));
        $institutionId = $this->currentInstitutionId();
        $branchId = $this->staffBranchId();

        if ($barcode === '') {
            return response()->json(['ok' => false, 'message' => 'Barcode kosong.'], 422);
        }

        $item = Item::query()
            ->where('institution_id', $institutionId)
            ->where('barcode', $barcode)
            ->first();

        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Barcode tidak ditemukan.'], 404);
        }

        if ($this->shouldLockBranch() && Schema::hasColumn('items', 'branch_id')) {
            $itemBranch = (int)($item->branch_id ?? 0);
            if ($itemBranch > 0 && $itemBranch !== (int)$branchId) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Item ini dari cabang lain. Akses dibatasi sesuai cabang akun Anda.',
                ], 422);
            }
        }

        $loanItemQ = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loan_items.item_id', (int)$item->id)
            ->whereNull('loan_items.returned_at')
            ->where('loans.institution_id', $institutionId)
            ->orderByDesc('loan_items.id')
            ->select([
                'loan_items.id as loan_item_id',
                'loans.id as loan_id',
                'loans.loan_code',
                'loans.status as loan_status',
                'loan_items.due_at',
                Schema::hasColumn('loan_items', 'due_date')
                    ? 'loan_items.due_date'
                    : DB::raw('NULL as due_date'),
            ]);

        if ($this->shouldLockBranch()) {
            $loanItemQ->where('loans.branch_id', (int)$branchId);
        }

        $loanItem = $loanItemQ->first();

        if (!$loanItem) {
            return response()->json([
                'ok' => false,
                'message' => 'Item tidak sedang dipinjam. Status: ' . (string)($item->status ?? 'available'),
            ], 422);
        }

        $statusMismatch = ((string)($item->status ?? '')) !== 'borrowed';

        $syncedItemStatus = false;
        if ($statusMismatch) {
            $item->status = 'borrowed';
            $item->save();
            $syncedItemStatus = true;
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'item_id' => (int)$item->id,
                'barcode' => (string)$item->barcode,
                'item_status' => (string)($item->status ?? 'available'),
                'status_mismatch' => (bool)$statusMismatch,
                'synced_item_status' => (bool)$syncedItemStatus,
                'loan_item_id' => (int)$loanItem->loan_item_id,
                'loan_id' => (int)$loanItem->loan_id,
                'loan_code' => (string)$loanItem->loan_code,
                'due_at' => $loanItem->due_at,
                'due_date' => $loanItem->due_date ?? null,
                'detail_url' => route('transaksi.riwayat.detail', ['id' => $loanItem->loan_id]),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | KEMBALI - SIMPAN
    |--------------------------------------------------------------------------
    */
    public function storeKembali(Request $request)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request);

        $data = $request->validate([
            'loan_item_ids' => ['required', 'array', 'min:1'],
            'loan_item_ids.*' => ['required', 'integer'],
        ]);

        $user = Auth::user();
        $institutionId = $this->currentInstitutionId();
        $branchId = $this->staffBranchId();

        try {
            $result = DB::transaction(function () use ($data, $user, $institutionId, $branchId) {
                $loanItemIds = array_values(array_unique(array_map('intval', $data['loan_item_ids'])));

                $loanItemsQ = DB::table('loan_items')
                    ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                    ->join('items', 'items.id', '=', 'loan_items.item_id')
                    ->whereIn('loan_items.id', $loanItemIds)
                    ->where('loans.institution_id', $institutionId)
                    ->select([
                        'loan_items.id',
                        'loan_items.loan_id',
                        'loan_items.item_id',
                        'loan_items.returned_at',
                        'loan_items.status',
                        'loans.status as loan_status',
                        'loans.branch_id as loan_branch_id',
                        'items.branch_id as item_branch_id',
                    ])
                    ->lockForUpdate();

                if ($this->shouldLockBranch()) {
                    $loanItemsQ->where('loans.branch_id', (int)$branchId);
                }

                $loanItems = $loanItemsQ->get();

                if ($this->shouldLockBranch()) {
                    foreach ($loanItems as $li) {
                        if (empty($li->item_branch_id)) {
                            throw new \RuntimeException('Item belum memiliki cabang. Hubungi admin untuk melengkapi data item.');
                        }
                        if ((int)$li->item_branch_id !== (int)$branchId) {
                            throw new \RuntimeException('Item berasal dari cabang lain. Transaksi ditolak.');
                        }
                        if (empty($li->loan_branch_id)) {
                            throw new \RuntimeException('Data pinjam tidak memiliki cabang. Hubungi admin untuk perbaiki data pinjam.');
                        }
                        if ((int)$li->loan_branch_id !== (int)$branchId) {
                            throw new \RuntimeException('Data pinjam berasal dari cabang lain. Transaksi ditolak.');
                        }
                    }
                }

                if ($loanItems->isEmpty()) {
                    throw new \RuntimeException('Data pengembalian tidak ditemukan.');
                }

                foreach ($loanItems as $li) {
                    if (!empty($li->returned_at)) {
                        continue;
                    }

                    DB::table('loan_items')->where('id', $li->id)->update([
                        'status' => 'returned',
                        'returned_at' => now(),
                        'updated_at' => now(),
                    ]);

                    Item::where('id', $li->item_id)->update([
                        'status' => 'available',
                        'updated_at' => now(),
                    ]);

                    try {
                        app(ReservationService::class)->onItemAvailable(
                            itemId: (int)$li->item_id,
                            institutionId: (int)$institutionId,
                            actorUserId: $user?->id
                        );
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }

                $loanIds = $loanItems->pluck('loan_id')->unique()->values()->all();

                foreach ($loanIds as $loanId) {
                    $stillBorrowed = DB::table('loan_items')
                        ->where('loan_id', $loanId)
                        ->whereNull('returned_at')
                        ->exists();

                    if (!$stillBorrowed) {
                        DB::table('loans')->where('id', $loanId)->update([
                            'status' => 'closed',
                            'closed_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $overdue = DB::table('loan_items')
                            ->where('loan_id', $loanId)
                            ->whereNull('returned_at')
                            ->whereNotNull('due_at')
                            ->where('due_at', '<', now())
                            ->exists();

                        if ($overdue) {
                            DB::table('loans')->where('id', $loanId)->update([
                                'status' => 'overdue',
                                'updated_at' => now(),
                            ]);
                        } else {
                            DB::table('loans')->where('id', $loanId)->where('status', 'overdue')->update([
                                'status' => 'open',
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }

                $this->writeAudit(
                    action: 'loan.return',
                    module: 'sirkulasi',
                    auditableType: 'LoanItem',
                    auditableId: null,
                    metadata: [
                        'loan_item_ids' => $loanItemIds,
                        'loan_ids' => $loanIds,
                    ],
                    institutionId: $institutionId,
                    actorUserId: $user?->id,
                    actorRole: $user?->role ?? null
                );

                $primaryLoanId = (int)($loanIds[0] ?? 0);

                return [
                    'primary_loan_id' => $primaryLoanId,
                    'loan_ids' => $loanIds,
                    'loan_item_ids' => $loanItemIds,
                ];
            });

            if (empty($result['primary_loan_id'])) {
                return redirect()
                    ->route('transaksi.kembali.form')
                    ->with('success', 'Pengembalian berhasil disimpan.');
            }

            return redirect()
                ->route('transaksi.kembali.success', ['id' => $result['primary_loan_id']])
                ->with('success', 'Pengembalian berhasil disimpan.')
                ->with('kembali_result', [
                    'primary_loan_id' => $result['primary_loan_id'],
                    'loan_ids' => $result['loan_ids'],
                    'loan_item_ids' => $result['loan_item_ids'],
                ]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PERPANJANG - FORM
    |--------------------------------------------------------------------------
    */
    public function perpanjangForm()
    {
        $this->ensureStaff();
        $this->requireStaffBranch();

        return view('transaksi.perpanjang', [
            'title' => 'Perpanjang Peminjaman',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PERPANJANG - AJAX: Cek Barcode
    |--------------------------------------------------------------------------
    */
    public function cekBarcodePerpanjang(Request $request)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request);

        $barcode = trim((string)$request->query('barcode', ''));
        $institutionId = $this->currentInstitutionId();
        $branchId = $this->staffBranchId();

        if ($barcode === '') {
            return response()->json(['ok' => false, 'message' => 'Barcode kosong.'], 422);
        }

        $item = Item::query()
            ->where('institution_id', $institutionId)
            ->where('barcode', $barcode)
            ->first();

        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Barcode tidak ditemukan.'], 404);
        }

        if ($this->shouldLockBranch() && Schema::hasColumn('items', 'branch_id')) {
            $itemBranch = (int)($item->branch_id ?? 0);
            if ($itemBranch > 0 && $itemBranch !== (int)$branchId) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Item ini dari cabang lain. Akses dibatasi sesuai cabang akun Anda.',
                ], 422);
            }
        }

        $liQ = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loan_items.item_id', $item->id)
            ->whereNull('loan_items.returned_at')
            ->where('loans.institution_id', $institutionId)
            ->orderByDesc('loan_items.id')
            ->select([
                'loan_items.id as loan_item_id',
                'loan_items.due_at as item_due_at',
                Schema::hasColumn('loan_items', 'due_date')
                    ? 'loan_items.due_date as item_due_date'
                    : DB::raw('NULL as item_due_date'),
                Schema::hasColumn('loan_items', 'renew_count')
                    ? 'loan_items.renew_count'
                    : DB::raw('0 as renew_count'),
                'loans.id as loan_id',
                'loans.loan_code',
                'loans.due_at as loan_due_at',
                'loans.status as loan_status',
            ]);

        if ($this->shouldLockBranch()) {
            $liQ->where('loans.branch_id', (int)$branchId);
        }

        $li = $liQ->first();

        if (!$li) {
            return response()->json(['ok' => false, 'message' => 'Data peminjaman aktif tidak ditemukan.'], 404);
        }

        if (($item->status ?? '') !== 'borrowed') {
            $item->status = 'borrowed';
            $item->save();
        }

        $dueOld = $li->item_due_at ?: $li->loan_due_at;

        try {
            $base = $dueOld ? new \DateTime(str_replace(' ', 'T', (string)$dueOld)) : new \DateTime();
        } catch (\Throwable $e) {
            $base = new \DateTime();
        }
        $extendDays = (int)config('notobuku.loans.extend_days', 7);
        if ($extendDays <= 0) $extendDays = 7;
        $base->modify('+' . $extendDays . ' days');
        $dueNew = $base->format('Y-m-d H:i:s');

        $statusForUi = 'borrowed';
        if ((string)$li->loan_status === 'overdue') {
            $statusForUi = 'overdue';
        } else {
            if (!empty($dueOld)) {
                $t = strtotime((string)$dueOld);
                if ($t !== false && $t < time()) {
                    $statusForUi = 'overdue';
                }
            }
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'barcode' => (string)$item->barcode,
                'item_id' => (int)$item->id,
                'loan_item_id' => (int)$li->loan_item_id,
                'loan_id' => (int)$li->loan_id,
                'loan_code' => (string)$li->loan_code,
                'status' => $statusForUi,
                'due_old' => $dueOld,
                'due_new' => $dueNew,
                'due_at' => $dueOld,
                'due_date' => $li->item_due_date ?? null,
                'renew_count' => (int)($li->renew_count ?? 0),
                'detail_url' => route('transaksi.riwayat.detail', ['id' => $li->loan_id]),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PERPANJANG - SIMPAN
    |--------------------------------------------------------------------------
    */
    public function storePerpanjang(Request $request)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request);

        $data = $request->validate([
            'loan_item_ids' => ['required', 'array', 'min:1'],
            'loan_item_ids.*' => ['required', 'integer'],
            'new_due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = Auth::user();
        $institutionId = $this->currentInstitutionId();
        $branchId = (int)$this->staffBranchId();

        $notes = trim((string)($data['notes'] ?? ''));
        $notes = $notes !== '' ? $notes : null;

        $overrideNewDueAt = null;
        if (!empty($data['new_due_at'])) {
            $overrideNewDueAt = date('Y-m-d H:i:s', strtotime((string)$data['new_due_at']));
        }

        $extendDays = (int)config('notobuku.loans.extend_days', 7);
        if ($extendDays <= 0) $extendDays = 7;
        $maxRenewals = (int)config('notobuku.loans.max_renewals', 2);
        if ($maxRenewals <= 0) $maxRenewals = 2;
        $liHasRenewCount = Schema::hasColumn('loan_items', 'renew_count');

        try {
            DB::transaction(function () use ($data, $institutionId, $overrideNewDueAt, $user, $notes, $branchId, $liHasRenewCount, $maxRenewals, $extendDays) {
                $loanItemIds = array_values(array_unique(array_map('intval', $data['loan_item_ids'])));

                $loanItemsQ = DB::table('loan_items')
                    ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                    ->join('items', 'items.id', '=', 'loan_items.item_id')
                    ->whereIn('loan_items.id', $loanItemIds)
                    ->where('loans.institution_id', $institutionId)
                    ->select([
                        'loan_items.id',
                        'loan_items.loan_id',
                        'loan_items.due_at as item_due_at',
                        Schema::hasColumn('loan_items', 'due_date')
                            ? 'loan_items.due_date as item_due_date'
                            : DB::raw('NULL as item_due_date'),
                        'loan_items.returned_at',
                        'loan_items.status',
                        'loan_items.item_id',
                        $liHasRenewCount ? 'loan_items.renew_count' : DB::raw('0 as renew_count'),
                        'loans.due_at as loan_due_at',
                        'loans.status as loan_status',
                        'loans.branch_id as loan_branch_id',
                        'items.branch_id as item_branch_id',
                    ])
                    ->lockForUpdate();

                $loanItems = $loanItemsQ->get();

                if ($loanItems->isEmpty()) {
                    throw new \RuntimeException('Data perpanjang tidak ditemukan.');
                }

                if ($loanItems->count() !== count($loanItemIds)) {
                    throw new \RuntimeException('Sebagian item tidak ditemukan atau akses ditolak. Transaksi dibatalkan.');
                }

                if ($this->shouldLockBranch()) {
                    foreach ($loanItems as $li) {
                        $itemBranch = (int)($li->item_branch_id ?? 0);
                        $loanBranch = (int)($li->loan_branch_id ?? 0);

                        if ($itemBranch <= 0) {
                            throw new \RuntimeException('Item belum memiliki cabang. Hubungi admin untuk melengkapi data item.');
                        }
                        if ($loanBranch <= 0) {
                            throw new \RuntimeException('Data pinjam tidak memiliki cabang. Hubungi admin untuk perbaiki data pinjam.');
                        }
                        if ($itemBranch !== (int)$branchId) {
                            throw new \RuntimeException('Item berasal dari cabang lain. Transaksi ditolak.');
                        }
                        if ($loanBranch !== (int)$branchId) {
                            throw new \RuntimeException('Data pinjam berasal dari cabang lain. Transaksi ditolak.');
                        }
                        if ($loanBranch !== $itemBranch) {
                            throw new \RuntimeException('Cabang loan tidak sinkron dengan cabang item. Transaksi ditolak.');
                        }
                    }
                }

                foreach ($loanItems as $li) {
                    if (!empty($li->returned_at)) {
                        throw new \RuntimeException('Ada item yang sudah dikembalikan. Tidak bisa diperpanjang.');
                    }
                    if ($liHasRenewCount && (int)($li->renew_count ?? 0) >= $maxRenewals) {
                        throw new \RuntimeException('Perpanjangan ditolak: batas maksimal perpanjang sudah tercapai.');
                    }

                    $dueOld = $li->item_due_at ?: $li->loan_due_at;
                    $newDueAt = $overrideNewDueAt;

                    if (!$newDueAt) {
                        try {
                            $base = $dueOld ? new \DateTime(str_replace(' ', 'T', (string)$dueOld)) : new \DateTime();
                        } catch (\Throwable $e) {
                            $base = new \DateTime();
                        }
                        $base->modify('+' . $extendDays . ' days');
                        $newDueAt = $base->format('Y-m-d H:i:s');
                    }

                    $newDueDate = null;
                    if (Schema::hasColumn('loan_items', 'due_date')) {
                        try {
                            $newDueDate = date('Y-m-d', strtotime((string)$newDueAt));
                        } catch (\Throwable $e) {
                            $newDueDate = null;
                        }
                    }

                    $update = [
                        'due_at' => $newDueAt,
                        'due_date' => Schema::hasColumn('loan_items', 'due_date') ? $newDueDate : DB::raw('due_date'),
                        'updated_at' => now(),
                    ];
                    if ($liHasRenewCount) {
                        $update['renew_count'] = DB::raw('renew_count + 1');
                    }
                    DB::table('loan_items')->where('id', $li->id)->update($update);
                }

                $loanIds = $loanItems->pluck('loan_id')->unique()->values();

                foreach ($loanIds as $loanId) {
                    $maxDue = DB::table('loan_items')
                        ->where('loan_id', $loanId)
                        ->whereNull('returned_at')
                        ->max('due_at');

                    DB::table('loans')->where('id', $loanId)->update([
                        'due_at' => $maxDue,
                        'status' => 'open',
                        'updated_at' => now(),
                    ]);
                }

                $this->writeAudit(
                    action: 'loan.extend',
                    module: 'sirkulasi',
                    auditableType: 'LoanItem',
                    auditableId: null,
                    metadata: [
                        'loan_item_ids' => $loanItemIds,
                        'new_due_at_override' => $overrideNewDueAt,
                        'notes' => $notes,
                    ],
                    institutionId: $institutionId,
                    actorUserId: $user?->id,
                    actorRole: $user?->role ?? null
                );
            });

            return redirect()
                ->route('transaksi.perpanjang.form')
                ->with('success', 'Perpanjangan berhasil disimpan.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RIWAYAT (TAB: transaksi / denda) - LIST + FILTER
    |--------------------------------------------------------------------------
    */
    public function riwayat(Request $request)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request);

        $institutionId = $this->currentInstitutionId();
        $branchId = $this->staffBranchId();

        $overdueUpdateQ = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->where('status', 'open')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());

        if ($this->shouldLockBranch()) {
            $overdueUpdateQ->where('branch_id', (int)$branchId);
        }

        $overdueUpdateQ->update(['status' => 'overdue', 'updated_at' => now()]);

        $tab = trim((string)$request->query('tab', 'transaksi'));
        if (!in_array($tab, ['transaksi', 'denda'], true)) $tab = 'transaksi';

        $q = trim((string)$request->query('q', ''));
        $status = trim((string)$request->query('status', ''));
        $from = trim((string)$request->query('from', ''));
        $to = trim((string)$request->query('to', ''));
        $overdue = trim((string)$request->query('overdue', ''));
        $perPage = (int)$request->query('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50], true) ? $perPage : 15;

        $range = trim((string)$request->query('range', ''));
        if (in_array($range, ['today', 'week', 'month'], true)) {
            if ($range === 'today') {
                $from = now()->toDateString();
                $to = now()->toDateString();
            } elseif ($range === 'week') {
                $from = now()->startOfWeek()->toDateString();
                $to = now()->endOfWeek()->toDateString();
            } else {
                $from = now()->startOfMonth()->toDateString();
                $to = now()->endOfMonth()->toDateString();
            }
        }

        $sort = trim((string)$request->query('sort', 'loaned_at'));
        $dir = strtolower(trim((string)$request->query('dir', 'desc')));
        $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';

        $sortMap = [
            'loan_code' => 'loans.loan_code',
            'member' => 'members.full_name',
            'branch' => 'branches.name',
            'loaned_at' => 'loans.loaned_at',
            'due_at' => 'loans.due_at',
            'status' => 'loans.status',
        ];
        $sortCol = $sortMap[$sort] ?? 'loans.loaned_at';

        $loans = null;

        if ($tab === 'transaksi') {
            $base = DB::table('loans')
                ->join('members', 'members.id', '=', 'loans.member_id')
                ->leftJoin('branches', 'branches.id', '=', 'loans.branch_id')
                ->where('loans.institution_id', $institutionId)
                  ->select([
                      'loans.id',
                      'loans.loan_code',
                      'loans.status',
                      'loans.loaned_at',
                      'loans.due_at',
                      'loans.closed_at',
                      'members.id as member_id',
                      'members.full_name as member_name',
                      'members.member_code as member_code',
                      Schema::hasColumn('members', 'member_type')
                          ? 'members.member_type as member_type'
                          : DB::raw('NULL as member_type'),
                      DB::raw('COALESCE(branches.name, "-") as branch_name'),
                      DB::raw('(select count(*) from loan_items li where li.loan_id = loans.id) as items_total'),
                      DB::raw('(select count(*) from loan_items li where li.loan_id = loans.id and li.returned_at is null) as items_open'),
                  ]);

            if ($this->shouldLockBranch()) {
                $base->where('loans.branch_id', (int)$branchId);
            }

            if ($q !== '') {
                $base->where(function ($w) use ($q) {
                    $w->where('loans.loan_code', 'like', "%{$q}%")
                      ->orWhere('members.full_name', 'like', "%{$q}%")
                      ->orWhere('members.member_code', 'like', "%{$q}%");
                });
            }

            if ($status !== '' && in_array($status, ['open', 'closed', 'overdue'], true)) {
                $base->where('loans.status', $status);
            }

            if ($from !== '') $base->whereDate('loans.loaned_at', '>=', $from);
            if ($to !== '') $base->whereDate('loans.loaned_at', '<=', $to);

            if ($overdue === '1') {
                $base->where(function ($w) {
                    $w->where('loans.status', 'overdue')
                      ->orWhere(function ($q) {
                          $q->where('loans.status', 'open')
                            ->whereNotNull('loans.due_at')
                            ->where('loans.due_at', '<', now());
                      });
                });
            }

            $loans = $base
                ->orderBy($sortCol, $dir)
                ->paginate($perPage)
                ->withQueryString();
        }

        $fineRate = $this->fineRatePerDay($institutionId);
        $fines = null;
        $fineSummary = [
            'count_unpaid' => 0,
            'sum_unpaid' => 0,
            'count_paid' => 0,
            'sum_paid' => 0,
        ];

        $fineQ = trim((string)$request->query('fine_q', ''));
        $fineStatus = trim((string)$request->query('fine_status', 'unpaid'));
        if (!in_array($fineStatus, ['unpaid', 'paid', 'all'], true)) $fineStatus = 'unpaid';

        $finePerPage = (int)$request->query('fine_per_page', 15);
        $finePerPage = in_array($finePerPage, [10, 15, 25, 50], true) ? $finePerPage : 15;

        if ($tab === 'denda') {
            $this->syncFinesSnapshot($institutionId, $fineRate);

            $hasFines = Schema::hasTable('fines');

            $qb = DB::table('loan_items')
                ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->join('members', 'members.id', '=', 'loans.member_id')
                ->join('items', 'items.id', '=', 'loan_items.item_id')
                ->where('loans.institution_id', $institutionId)
                ->whereNotNull('loan_items.due_at')
                ->where(function ($w) {
                    $w->where(function ($q) {
                        $q->whereNull('loan_items.returned_at')
                          ->where('loan_items.due_at', '<', now());
                    })->orWhere(function ($q) {
                        $q->whereNotNull('loan_items.returned_at')
                          ->whereColumn('loan_items.returned_at', '>', 'loan_items.due_at');
                    });
                })
                ->select([
                    'loan_items.id as loan_item_id',
                    'loans.id as loan_id',
                    'loans.loan_code',
                    'members.id as member_id',
                    'members.full_name as member_name',
                    'members.member_code as member_code',
                    'items.barcode',
                    'loan_items.due_at',
                    'loan_items.returned_at',
                ]);

            if ($this->shouldLockBranch()) {
                $qb->where('loans.branch_id', (int)$branchId);
            }

            if ($hasFines) {
                $qb->leftJoin('fines', function ($j) use ($institutionId) {
                    $j->on('fines.loan_item_id', '=', 'loan_items.id')
                      ->where('fines.institution_id', '=', $institutionId);
                });

                $cols = [];
                foreach (['status', 'amount', 'paid_amount', 'paid_at'] as $c) {
                    if (Schema::hasColumn('fines', $c)) $cols[] = "fines.{$c} as fine_{$c}";
                }
                if ($cols) $qb->addSelect(array_map(fn($x) => DB::raw($x), $cols));
            }

            if ($fineQ !== '') {
                $qb->where(function ($w) use ($fineQ) {
                    $w->where('loans.loan_code', 'like', "%{$fineQ}%")
                      ->orWhere('members.full_name', 'like', "%{$fineQ}%")
                      ->orWhere('members.member_code', 'like', "%{$fineQ}%")
                      ->orWhere('items.barcode', 'like', "%{$fineQ}%");
                });
            }

            if ($fineStatus !== 'all' && Schema::hasTable('fines') && Schema::hasColumn('fines', 'status')) {
                $qb->where('fines.status', $fineStatus);
            } elseif ($fineStatus === 'paid' && (!Schema::hasTable('fines') || !Schema::hasColumn('fines', 'status'))) {
                $qb->whereRaw('1=0');
            }

            $fines = $qb
                ->orderByDesc('loan_items.due_at')
                ->paginate($finePerPage)
                ->through(function ($r) use ($fineRate) {
                    $daysLate = $this->calcDaysLate($r->due_at, $r->returned_at);
                    $amount = $daysLate * (int)$fineRate;

                    $status = $r->fine_status ?? 'unpaid';
                    if (!in_array($status, ['unpaid', 'paid', 'void'], true)) $status = 'unpaid';

                    return (object)[
                        'loan_item_id' => (int)$r->loan_item_id,
                        'loan_id' => (int)$r->loan_id,
                        'loan_code' => (string)$r->loan_code,
                        'member_id' => (int)($r->member_id ?? 0),
                        'member_name' => (string)$r->member_name,
                        'member_code' => (string)$r->member_code,
                        'barcode' => (string)$r->barcode,
                        'due_at' => $r->due_at,
                        'returned_at' => $r->returned_at,
                        'days_late' => $daysLate,
                        'rate' => (int)$fineRate,
                        'amount' => (int)$amount,
                        'fine_status' => $status,
                        'paid_amount' => (int)($r->fine_paid_amount ?? 0),
                        'paid_at' => $r->fine_paid_at ?? null,
                    ];
                })
                ->withQueryString();

            if (Schema::hasTable('fines')) {
                $sumQ = DB::table('fines')->where('institution_id', $institutionId);

                if (Schema::hasColumn('fines', 'status')) {
                    $fineSummary['count_unpaid'] = (clone $sumQ)->where('status', 'unpaid')->count();
                    $fineSummary['count_paid'] = (clone $sumQ)->where('status', 'paid')->count();

                    $fineSummary['sum_unpaid'] = (int)(clone $sumQ)->where('status', 'unpaid')->sum('amount');
                    $fineSummary['sum_paid'] = (int)(clone $sumQ)->where('status', 'paid')->sum('paid_amount');
                }
            }
        }

        return view('transaksi.riwayat', [
            'title' => 'Riwayat Transaksi',
            'tab' => $tab,

            'loans' => $loans,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'from' => $from,
                'to' => $to,
                'overdue' => $overdue,
                'per_page' => (string)$perPage,
                'range' => $range,
                'sort' => $sort,
                'dir' => $dir,
            ],

            'fine_rate' => (int)$fineRate,
            'fines' => $fines,
            'fine_filters' => [
                'fine_q' => $fineQ,
                'fine_status' => $fineStatus,
                'fine_per_page' => (string)$finePerPage,
            ],
            'fine_summary' => $fineSummary,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | RIWAYAT - DETAIL
    |--------------------------------------------------------------------------
    */
    public function detail($id)
    {
        $this->ensureStaff();
        $this->requireStaffBranch();

        $institutionId = $this->currentInstitutionId();
        $branchId = $this->staffBranchId();

        $loanQ = DB::table('loans')
            ->join('members', 'members.id', '=', 'loans.member_id')
            ->leftJoin('branches', 'branches.id', '=', 'loans.branch_id')
            ->leftJoin('users', 'users.id', '=', 'loans.created_by')
            ->where('loans.institution_id', $institutionId)
            ->where('loans.id', (int)$id)
            ->select([
                'loans.*',
                'members.id as member_id',
                'members.full_name as member_name',
                'members.member_code as member_code',
                'members.phone as member_phone',
                DB::raw('COALESCE(branches.name, "-") as branch_name'),
                DB::raw('COALESCE(users.name, users.username, "-") as created_by_name'),
            ]);

        if ($this->shouldLockBranch()) {
            $loanQ->where('loans.branch_id', (int)$branchId);
        }

        $loan = $loanQ->first();
        if (!$loan) abort(404);

        $biblioTable = null;
        if (Schema::hasTable('biblios')) $biblioTable = 'biblios';
        elseif (Schema::hasTable('biblio')) $biblioTable = 'biblio';

        $itemsQ = DB::table('loan_items')
            ->join('items', 'items.id', '=', 'loan_items.item_id')
            ->where('loan_items.loan_id', (int)$id)
            ->select([
                'loan_items.id as loan_item_id',
                'loan_items.status as loan_item_status',
                'loan_items.borrowed_at',
                'loan_items.due_at as item_due_at',
                Schema::hasColumn('loan_items', 'due_date')
                    ? 'loan_items.due_date as item_due_date'
                    : DB::raw('NULL as item_due_date'),
                'loan_items.returned_at',
                Schema::hasColumn('loan_items', 'renew_count')
                    ? 'loan_items.renew_count'
                    : DB::raw('NULL as renew_count'),
                'items.id as item_id',
                'items.barcode',
                'items.accession_number',
                'items.status as item_status',
                'items.biblio_id',
            ]);

        if ($this->shouldLockBranch()) {
            if (Schema::hasColumn('items', 'branch_id')) {
                $itemsQ->where(function ($w) use ($branchId) {
                    $w->whereNull('items.branch_id')
                      ->orWhere('items.branch_id', (int)$branchId);
                });
            }
        }

        if ($biblioTable) {
            $itemsQ->leftJoin($biblioTable, "{$biblioTable}.id", '=', 'items.biblio_id');
            $itemsQ->addSelect([
                DB::raw("{$biblioTable}.title as title"),
                DB::raw("{$biblioTable}.call_number as call_number"),
            ]);
        } else {
            $itemsQ->addSelect([
                DB::raw("NULL as title"),
                DB::raw("NULL as call_number"),
            ]);
        }

        $items = $itemsQ->orderByDesc('loan_items.id')->get();

        $isOverdue = false;
        if ((string)$loan->status === 'overdue') {
            $isOverdue = true;
        } elseif ((string)$loan->status === 'open' && !empty($loan->due_at)) {
            $isOverdue = strtotime((string)$loan->due_at) < time();
        }

        return view('transaksi.detail', [
            'title' => 'Detail Transaksi',
            'loan' => $loan,
            'items' => $items,
            'isOverdue' => $isOverdue,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT SLIP / NOTA (58mm / 80mm)
    |--------------------------------------------------------------------------
    */
    public function printSlip(Request $request, $id)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request);

        $institutionId = $this->currentInstitutionId();
        $branchId = $this->staffBranchId();

        $size = trim((string)$request->query('size', '80'));
        if (!in_array($size, ['58', '80'], true)) $size = '80';

        $loanQ = DB::table('loans')
            ->join('members', 'members.id', '=', 'loans.member_id')
            ->leftJoin('branches', 'branches.id', '=', 'loans.branch_id')
            ->leftJoin('users', 'users.id', '=', 'loans.created_by')
            ->where('loans.institution_id', $institutionId)
            ->where('loans.id', (int)$id)
            ->select([
                'loans.*',
                'members.id as member_id',
                'members.full_name as member_name',
                'members.member_code as member_code',
                'members.phone as member_phone',
                DB::raw('COALESCE(branches.name, "-") as branch_name'),
                DB::raw('COALESCE(users.name, users.username, "-") as created_by_name'),
            ]);

        if ($this->shouldLockBranch()) {
            $loanQ->where('loans.branch_id', (int)$branchId);
        }

        $loan = $loanQ->first();
        if (!$loan) abort(404);

        $biblioTable = null;
        if (Schema::hasTable('biblios')) $biblioTable = 'biblios';
        elseif (Schema::hasTable('biblio')) $biblioTable = 'biblio';

        $itemsQ = DB::table('loan_items')
            ->join('items', 'items.id', '=', 'loan_items.item_id')
            ->where('loan_items.loan_id', (int)$id)
            ->select([
                'loan_items.id as loan_item_id',
                'loan_items.status as loan_item_status',
                'loan_items.borrowed_at',
                'loan_items.due_at as item_due_at',
                Schema::hasColumn('loan_items', 'due_date')
                    ? 'loan_items.due_date as item_due_date'
                    : DB::raw('NULL as item_due_date'),
                'loan_items.returned_at',
                'items.id as item_id',
                'items.barcode',
                'items.accession_number',
                'items.status as item_status',
                'items.biblio_id',
            ]);

        if ($this->shouldLockBranch()) {
            if (Schema::hasColumn('items', 'branch_id')) {
                $itemsQ->where(function ($w) use ($branchId) {
                    $w->whereNull('items.branch_id')
                      ->orWhere('items.branch_id', (int)$branchId);
                });
            }
        }

        if ($biblioTable) {
            $itemsQ->leftJoin($biblioTable, "{$biblioTable}.id", '=', 'items.biblio_id');
            $itemsQ->addSelect([
                DB::raw("{$biblioTable}.title as title"),
                DB::raw("{$biblioTable}.call_number as call_number"),
            ]);
        } else {
            $itemsQ->addSelect([
                DB::raw("NULL as title"),
                DB::raw("NULL as call_number"),
            ]);
        }

        $items = $itemsQ->orderBy('loan_items.id')->get();

        $view = $size === '58' ? 'transaksi.print_58' : 'transaksi.print_80';

        return view($view, [
            'title' => 'Print Slip',
            'paper' => $size,
            'loan' => $loan,
            'items' => $items,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | QUICK SEARCH (JSON)
    |--------------------------------------------------------------------------
    */
    public function quickSearch(Request $request)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request);

        $institutionId = $this->currentInstitutionId();
        $branchId = $this->staffBranchId();
        $q = trim((string)$request->query('q', ''));

        if ($q === '') {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $limit = 8;

        $loanRowsQ = DB::table('loans')
            ->join('members', 'members.id', '=', 'loans.member_id')
            ->where('loans.institution_id', $institutionId)
            ->where('loans.loan_code', 'like', "%{$q}%")
            ->orderByDesc('loans.loaned_at')
            ->limit($limit);

        if ($this->shouldLockBranch()) {
            $loanRowsQ->where('loans.branch_id', (int)$branchId);
        }

        $loanRows = $loanRowsQ->get([
            'loans.id',
            'loans.loan_code',
            'loans.status',
            'loans.loaned_at',
            'loans.due_at',
            'members.id as member_id',
            'members.full_name as member_name',
            'members.member_code as member_code',
        ]);

        $itemRowsQ = DB::table('items')
            ->where('items.institution_id', $institutionId)
            ->whereNotNull('items.barcode')
            ->where('items.barcode', 'like', "%{$q}%")
            ->orderByDesc('items.id')
            ->limit($limit);

        if ($this->shouldLockBranch() && Schema::hasColumn('items', 'branch_id')) {
            $itemRowsQ->where(function ($w) use ($branchId) {
                $w->whereNull('items.branch_id')
                  ->orWhere('items.branch_id', (int)$branchId);
            });
        }

        $itemRows = $itemRowsQ->get(['items.id', 'items.barcode', 'items.status']);

        $itemsMapped = [];
        foreach ($itemRows as $it) {
            $active = null;

            if ((string)$it->status === 'borrowed') {
                $activeQ = DB::table('loan_items')
                    ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                    ->where('loan_items.item_id', $it->id)
                    ->whereNull('loan_items.returned_at')
                    ->where('loans.institution_id', $institutionId)
                    ->orderByDesc('loan_items.id');

                if ($this->shouldLockBranch()) {
                    $activeQ->where('loans.branch_id', (int)$branchId);
                }

                $active = $activeQ->first(['loans.id as loan_id', 'loans.loan_code', 'loans.status', 'loans.due_at']);
            }

            $itemsMapped[] = [
                'type' => 'item',
                'label' => $it->barcode . ' • ' . (string)$it->status,
                'barcode' => $it->barcode,
                'status' => $it->status,
                'url' => $active
                    ? route('transaksi.riwayat.detail', ['id' => $active->loan_id])
                    : route('transaksi.index') . '?barcode=' . urlencode((string)$it->barcode),
                'meta' => $active ? ['loan_code' => $active->loan_code, 'due_at' => $active->due_at, 'loan_status' => $active->status] : null,
            ];
        }

        $loansMapped = $loanRows->map(function ($l) {
            return [
                'type' => 'loan',
                'label' => $l->loan_code . ' • ' . $l->member_name,
                'loan_code' => $l->loan_code,
                'status' => $l->status,
                'url' => route('transaksi.riwayat.detail', ['id' => $l->id]),
                'meta' => [
                    'member_code' => $l->member_code,
                    'due_at' => $l->due_at,
                ],
            ];
        })->values()->all();

        $data = array_slice(array_merge($loansMapped, $itemsMapped), 0, 12);

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD STATISTIK SIRKULASI
    |--------------------------------------------------------------------------
    */
    public function dashboard()
    {
        $this->ensureStaff();
        $this->requireStaffBranch();

        $institutionId = $this->currentInstitutionId();
        $branchId = $this->staffBranchId();

        $updQ = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->where('status', 'open')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());

        if ($this->shouldLockBranch()) {
            $updQ->where('branch_id', (int)$branchId);
        }

        $updQ->update(['status' => 'overdue', 'updated_at' => now()]);

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $loansTodayQ = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->whereDate('loaned_at', $today);

        if ($this->shouldLockBranch()) $loansTodayQ->where('branch_id', (int)$branchId);
        $loansToday = $loansTodayQ->count();

        $returnsTodayQ = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->whereNotNull('loan_items.returned_at')
            ->whereDate('loan_items.returned_at', $today);

        if ($this->shouldLockBranch()) $returnsTodayQ->where('loans.branch_id', (int)$branchId);
        $returnsToday = $returnsTodayQ->count();

        $loansMonthQ = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->whereDate('loaned_at', '>=', $monthStart)
            ->whereDate('loaned_at', '<=', $monthEnd);

        if ($this->shouldLockBranch()) $loansMonthQ->where('branch_id', (int)$branchId);
        $loansMonth = $loansMonthQ->count();

        $returnsMonthQ = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->whereNotNull('loan_items.returned_at')
            ->whereDate('loan_items.returned_at', '>=', $monthStart)
            ->whereDate('loan_items.returned_at', '<=', $monthEnd);

        if ($this->shouldLockBranch()) $returnsMonthQ->where('loans.branch_id', (int)$branchId);
        $returnsMonth = $returnsMonthQ->count();

        $openLoansQ = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->where('status', 'open');

        if ($this->shouldLockBranch()) $openLoansQ->where('branch_id', (int)$branchId);
        $openLoans = $openLoansQ->count();

        $overdueLoansQ = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->where('status', 'overdue');

        if ($this->shouldLockBranch()) $overdueLoansQ->where('branch_id', (int)$branchId);
        $overdueLoans = $overdueLoansQ->count();

        $overdueItemsQ = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->whereNull('loan_items.returned_at')
            ->whereNotNull('loan_items.due_at')
            ->where('loan_items.due_at', '<', now());

        if ($this->shouldLockBranch()) $overdueItemsQ->where('loans.branch_id', (int)$branchId);
        $overdueItems = $overdueItemsQ->count();

        $trendLoanQ = DB::table('loans')
            ->where('institution_id', $institutionId)
            ->whereDate('loaned_at', '>=', now()->subDays(13)->toDateString())
            ->select([DB::raw('DATE(loaned_at) as d'), DB::raw('COUNT(*) as c')])
            ->groupBy(DB::raw('DATE(loaned_at)'))
            ->orderBy('d');

        if ($this->shouldLockBranch()) $trendLoanQ->where('branch_id', (int)$branchId);
        $trendLoan = $trendLoanQ->get();

        $mapLoan = [];
        foreach ($trendLoan as $t) $mapLoan[$t->d] = (int)$t->c;

        $trendRetQ = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->whereNotNull('loan_items.returned_at')
            ->whereDate('loan_items.returned_at', '>=', now()->subDays(13)->toDateString())
            ->select([DB::raw('DATE(loan_items.returned_at) as d'), DB::raw('COUNT(*) as c')])
            ->groupBy(DB::raw('DATE(loan_items.returned_at)'))
            ->orderBy('d');

        if ($this->shouldLockBranch()) $trendRetQ->where('loans.branch_id', (int)$branchId);
        $trendRet = $trendRetQ->get();

        $mapRet = [];
        foreach ($trendRet as $t) $mapRet[$t->d] = (int)$t->c;

        $trend14 = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $trend14[] = [
                'date' => $d,
                'loans' => (int)($mapLoan[$d] ?? 0),
                'returns' => (int)($mapRet[$d] ?? 0),
            ];
        }

        return view('transaksi.dashboard', [
            'title' => 'Dashboard Sirkulasi',
            'kpi' => [
                'loans_today' => $loansToday,
                'returns_today' => $returnsToday,
                'loans_month' => $loansMonth,
                'returns_month' => $returnsMonth,
                'open_loans' => $openLoans,
                'overdue_loans' => $overdueLoans,
                'overdue_items' => $overdueItems,
            ],
            'trend14' => $trend14,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DENDA - Index (HTML + JSON)
    |--------------------------------------------------------------------------
    */
    public function finesIndex(Request $request)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request);

        $isJson = $request->expectsJson()
            || $request->wantsJson()
            || $request->ajax()
            || $request->query('format') === 'json';

        if (!Schema::hasTable('fines')) {
            if ($isJson) {
                return response()->json(['ok' => false, 'message' => 'Tabel fines belum tersedia.'], 422);
            }
            return redirect()->back()->with('error', 'Tabel fines belum tersedia.');
        }

        $institutionId = $this->currentInstitutionId();
        $staffBranchId = $this->staffBranchId();

        // Pastikan snapshot denda up-to-date agar jumlah baris konsisten
        try {
            $rate = $this->fineRatePerDay($institutionId);
            if (method_exists($this, 'syncFinesSnapshot')) {
                $this->syncFinesSnapshot($institutionId, $rate);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $q = trim((string)$request->query('q', ''));
        $status = trim((string)$request->query('status', ''));
        $showZero = (string)$request->query('show_zero', '0') === '1';

        // Filter cabang:
        // - admin/staff: dikunci ke cabang aktif user
        // - super_admin: boleh pilih (atau semua)
        $branchId = null;
        if ($this->shouldLockBranch()) {
            $branchId = (int)$staffBranchId;
        } else {
            $branchId = $request->filled('branch_id') ? (int)$request->query('branch_id') : null;
            if ($branchId !== null && $branchId <= 0) $branchId = null;
        }

        // Filter tanggal (range kalender) — untuk mode "laporan"
        // Dipakai untuk memfilter daftar (default pakai fines.updated_at jika ada).
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $from = null;
        $to = null;
        try {
            if ($dateFrom) $from = \Carbon\Carbon::parse($dateFrom)->startOfDay();
            if ($dateTo) $to = \Carbon\Carbon::parse($dateTo)->endOfDay();
        } catch (\Throwable $e) {
            // abaikan jika format salah (tidak lempar error)
            $from = null;
            $to = null;
        }

        $dateCol = null;
        if (Schema::hasColumn('fines', 'updated_at')) {
            $dateCol = 'fines.updated_at';
        } elseif (Schema::hasColumn('fines', 'created_at')) {
            $dateCol = 'fines.created_at';
        }

        // Reusable base query (joins + scoping)
        $base = function () use ($institutionId) {
            return DB::table('fines')
                ->leftJoin('loan_items', 'loan_items.id', '=', 'fines.loan_item_id')
                ->leftJoin('items', 'items.id', '=', 'loan_items.item_id')
                ->leftJoin('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->leftJoin('members', 'members.id', '=', 'loans.member_id')
                ->where('fines.institution_id', $institutionId);
        };

        $applyCommonFilters = function ($rowsQ) use ($status, $q, $showZero, $branchId) {
            $rowsQ
                ->when($status !== '', function ($w) use ($status) {
                    $w->where('fines.status', $status);
                })
                ->when($q !== '', function ($w) use ($q) {
                    $w->where(function ($x) use ($q) {
                        $x->where('loans.loan_code', 'like', "%{$q}%")
                          ->orWhere('members.full_name', 'like', "%{$q}%")
                          ->orWhere('members.member_code', 'like', "%{$q}%")
                                                    ->orWhere('items.barcode', 'like', "%{$q}%");
                    });
                });

            if ($branchId !== null) {
                $rowsQ->where('loans.branch_id', (int)$branchId);
            }

            if (!$showZero && Schema::hasColumn('fines', 'amount')) {
                $rowsQ->where('fines.amount', '>', 0);
            }

            return $rowsQ;
        };

        $applyDateFilter = function ($rowsQ) use ($from, $to, $dateCol) {
            if ($dateCol && ($from || $to)) {
                if ($from && $to) {
                    $rowsQ->whereBetween($dateCol, [$from, $to]);
                } elseif ($from) {
                    $rowsQ->where($dateCol, '>=', $from);
                } elseif ($to) {
                    $rowsQ->where($dateCol, '<=', $to);
                }
            }
            return $rowsQ;
        };

        // List data (pakai filter tanggal)
        $rowsQ = $applyDateFilter($applyCommonFilters($base()));

        // urutan default: telat terbesar, lalu due paling lama
        if (Schema::hasColumn('fines', 'days_late')) {
            $rowsQ->orderByDesc('fines.days_late');
        }
        if (Schema::hasColumn('loan_items', 'due_at')) {
            $rowsQ->orderBy('loan_items.due_at', 'asc');
        }
        $rowsQ->orderByDesc('fines.updated_at');

        $rows = $rowsQ
            ->limit(200)
            ->select([
                'fines.id',
                'fines.institution_id',
                'fines.loan_item_id',
                'fines.member_id',
                'fines.status',
                'fines.days_late',
                'fines.rate',
                'fines.amount',
                'fines.paid_amount',
                'fines.paid_at',
                'fines.notes',
                'loans.loan_code',
                'loans.id as loan_id',
                'members.full_name as member_name',
                'members.member_code as member_code',
                'loan_items.due_at',
                'loan_items.returned_at',
            ])
            ->get();

        $data = $rows->map(function ($r) {
            return [
                'fine_id' => (int)$r->id,
                'loan_item_id' => (int)$r->loan_item_id,
                'loan_id' => (int)($r->loan_id ?? 0),
                'loan_code' => (string)($r->loan_code ?? ''),
                'member_id' => (int)($r->member_id ?? 0),
                'member_name' => (string)($r->member_name ?? ''),
                'member_code' => (string)($r->member_code ?? ''),
                'due_at' => $r->due_at,
                'returned_at' => $r->returned_at,
                'days_late' => (int)($r->days_late ?? 0),
                'rate' => (int)($r->rate ?? 0),
                'amount' => (int)($r->amount ?? 0),
                'fine_status' => (string)($r->status ?? 'unpaid'),
                'paid_amount' => (int)($r->paid_amount ?? 0),
                'paid_at' => $r->paid_at,
                'notes' => $r->notes,
            ];
        })->values();

        // Rekap (WAJIB) — tidak memakai filter tanggal, agar "hari ini/bulan ini" tetap bermakna.
        $recap = [
            'outstanding' => 0,
            'paid_today' => 0,
            'paid_month' => 0,
            'tx_today' => 0,
            'tx_month' => 0,
        ];

        $hasAmount = Schema::hasColumn('fines', 'amount');
        $hasPaidAmount = Schema::hasColumn('fines', 'paid_amount');
        $hasPaidAt = Schema::hasColumn('fines', 'paid_at');
        $hasStatus = Schema::hasColumn('fines', 'status');

        if ($hasAmount) {
            $recapQ = $applyCommonFilters($base());

            // outstanding = amount - paid_amount (exclude void)
            if ($hasPaidAmount) {
                $outstandingExpr = DB::raw('GREATEST(COALESCE(fines.amount,0) - COALESCE(fines.paid_amount,0), 0)');
                $recap['outstanding'] = (int) $recapQ
                    ->when($hasStatus, fn($w) => $w->where('fines.status', '!=', 'void'))
                    ->sum($outstandingExpr);
            } else {
                $recap['outstanding'] = (int) $recapQ
                    ->when($hasStatus, fn($w) => $w->where('fines.status', '!=', 'void'))
                    ->sum('fines.amount');
            }

            if ($hasPaidAt && $hasPaidAmount) {
                $todayStart = now()->startOfDay();
                $todayEnd = now()->endOfDay();
                $monthStart = now()->startOfMonth();
                $monthEnd = now()->endOfMonth();

                $paidBase = function () use ($applyCommonFilters, $base, $hasStatus) {
                    $q = $applyCommonFilters($base());
                    if ($hasStatus) $q->where('fines.status', '!=', 'void');
                    return $q->whereNotNull('fines.paid_at')->where('fines.paid_amount', '>', 0);
                };

                $todayQ = $paidBase()->whereBetween('fines.paid_at', [$todayStart, $todayEnd]);
                $recap['paid_today'] = (int) $todayQ->sum('fines.paid_amount');
                $recap['tx_today'] = (int) $todayQ->count('fines.id');

                $monthQ = $paidBase()->whereBetween('fines.paid_at', [$monthStart, $monthEnd]);
                $recap['paid_month'] = (int) $monthQ->sum('fines.paid_amount');
                $recap['tx_month'] = (int) $monthQ->count('fines.id');
            }
        }

        // Data cabang utk filter (super admin saja)
        $branches = [];
        if (!$this->shouldLockBranch() && Schema::hasTable('branches')) {
            $bq = DB::table('branches');
            if (Schema::hasColumn('branches', 'institution_id')) {
                $bq->where('institution_id', $institutionId);
            }
            if (Schema::hasColumn('branches', 'is_active')) {
                $bq->where('is_active', 1);
            }
            $branches = $bq->orderBy('name')->get(['id', 'name'])->map(fn($b) => [
                'id' => (int)$b->id,
                'name' => (string)$b->name,
            ])->values()->all();
        }

        if ($isJson) {
            return response()->json([
                'ok' => true,
                'data' => $data,
                'recap' => $recap,
                'filters' => [
                    'q' => $q,
                    'status' => $status,
                    'branch_id' => $branchId,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
            ]);
        }

        return view('transaksi.denda.index', [
            'title' => 'Laporan Denda',
            'q' => $q,
            'status' => $status,
            'show_zero' => $showZero,
            'rows' => $data,
            'recap' => $recap,
            'branches' => $branches,
            'branch_id' => $branchId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
    }

    public function finesRecalc(Request $request)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request);

        $institutionId = $this->currentInstitutionId();
        $rate = $this->fineRatePerDay($institutionId);

        $data = $request->validate([
            'loan_item_id' => ['required', 'integer'],
        ]);

        $loanItemId = (int)$data['loan_item_id'];

        $snap = $this->buildFineSnapshotForLoanItem($institutionId, $loanItemId, $rate);
        if (!$snap) return response()->json(['ok' => false, 'message' => 'Data tidak ditemukan.'], 404);

        if (Schema::hasTable('fines')) {
            $this->upsertFineRow($institutionId, $snap);
        }

        $fine = null;
        if (Schema::hasTable('fines')) {
            $fine = DB::table('fines')
                ->where('institution_id', $institutionId)
                ->where('loan_item_id', $loanItemId)
                ->first();
        }

        $snap['fine_status'] = (string)($fine->status ?? ($snap['fine_status'] ?? 'unpaid'));
        $snap['paid_amount'] = (int)($fine->paid_amount ?? 0);
        $snap['paid_at'] = $fine->paid_at ?? null;
        $snap['notes'] = $fine->notes ?? null;

        return response()->json(['ok' => true, 'rate' => $rate, 'data' => [$snap]]);
    }

    /*
    |--------------------------------------------------------------------------
    | DENDA - Bayar
    |--------------------------------------------------------------------------
    */
    public function finesPay(Request $request)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request);

        $isJson = $request->expectsJson()
            || $request->wantsJson()
            || $request->ajax()
            || $request->query('format') === 'json';

        if (!Schema::hasTable('fines')) {
            if ($isJson) {
                return response()->json(['ok' => false, 'message' => 'Tabel fines belum tersedia.'], 422);
            }
            return redirect()->back()->with('error', 'Tabel fines belum tersedia.');
        }

        $institutionId = $this->currentInstitutionId();
        $branchId = $this->staffBranchId();
        $user = Auth::user();

        $data = $request->validate([
            'loan_item_id' => ['required', 'integer'],
            'paid_amount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $loanItemId = (int)$data['loan_item_id'];
        $payNow = array_key_exists('paid_amount', $data) ? (int)($data['paid_amount'] ?? 0) : 0;

        $notes = trim((string)($data['notes'] ?? ''));
        $notes = $notes !== '' ? $notes : null;

        $hasPaidBy = Schema::hasColumn('fines', 'paid_by');
        $hasPaidAt = Schema::hasColumn('fines', 'paid_at');
        $hasUpdatedAt = Schema::hasColumn('fines', 'updated_at');
        $hasNotes = Schema::hasColumn('fines', 'notes');

        $result = DB::transaction(function () use (
            $institutionId,
            $branchId,
            $loanItemId,
            $payNow,
            $notes,
            $user,
            $hasPaidBy,
            $hasPaidAt,
            $hasUpdatedAt,
            $hasNotes
        ) {
            $fineQ = DB::table('fines')
                ->join('loan_items', 'loan_items.id', '=', 'fines.loan_item_id')
                ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->where('fines.institution_id', $institutionId)
                ->where('fines.loan_item_id', $loanItemId);

            if ($this->shouldLockBranch()) {
                $fineQ->where('loans.branch_id', (int)$branchId);
            }

            $fine = $fineQ
                ->select(['fines.*', 'loans.branch_id as loan_branch_id'])
                ->lockForUpdate()
                ->first();

            if (!$fine) {
                return ['ok' => false, 'status' => 404, 'message' => 'Data denda tidak ditemukan atau tidak sesuai cabang.'];
            }

            $status = (string)($fine->status ?? 'unpaid');
            if ($status === 'void') {
                return ['ok' => false, 'status' => 422, 'message' => 'Denda sudah dibatalkan (void).'];
            }

            $amount = (int)($fine->amount ?? 0);
            $currentPaid = (int)($fine->paid_amount ?? 0);
            $remaining = max(0, $amount - $currentPaid);

            // Jika user isi nominal, pakai itu. Jika kosong/0, auto bayar sisa.
            $payNominal = $payNow > 0 ? $payNow : $remaining;

            if ($payNominal <= 0) {
                return ['ok' => false, 'status' => 422, 'message' => 'Nominal pembayaran tidak valid.'];
            }

            $newPaid = $currentPaid + $payNominal;
            if ($newPaid > $amount) $newPaid = $amount;

            $newStatus = ($newPaid >= $amount) ? 'paid' : 'unpaid';

            $finalNotes = $fine->notes ?? null;
            if ($notes && $hasNotes) {
                $line = '[' . now()->format('Y-m-d H:i') . '] ' . $notes;
                $finalNotes = $finalNotes ? (trim((string)$finalNotes) . "
" . $line) : $line;
            }

            $update = [
                'status' => $newStatus,
                'paid_amount' => $newPaid,
            ];

            if ($hasPaidAt) {
                $update['paid_at'] = now();
            }
            if ($hasPaidBy) {
                $update['paid_by'] = $user?->id;
            }
            if ($hasNotes) {
                $update['notes'] = $finalNotes;
            }
            if ($hasUpdatedAt) {
                $update['updated_at'] = now();
            }

            DB::table('fines')
                ->where('institution_id', $institutionId)
                ->where('loan_item_id', $loanItemId)
                ->update($update);

            $this->writeAudit(
                action: 'fine.pay',
                module: 'denda',
                auditableType: 'Fine',
                auditableId: $fine->id ?? null,
                metadata: [
                    'loan_item_id' => $loanItemId,
                    'pay_nominal' => $payNominal,
                    'paid_amount_total' => $newPaid,
                    'status_after' => $newStatus,
                    'notes' => $notes,
                ],
                institutionId: $institutionId,
                actorUserId: $user?->id,
                actorRole: $user?->role ?? null
            );

            return [
                'ok' => true,
                'message' => ($newStatus === 'paid')
                    ? 'Pembayaran denda berhasil. Status: LUNAS.'
                    : 'Pembayaran denda tersimpan. Status: BELUM LUNAS.',
                'fine_status' => $newStatus,
                'paid_amount' => $newPaid,
                'paid_at' => $hasPaidAt ? now()->toDateTimeString() : ($fine->paid_at ?? null),
                'notes' => $hasNotes ? $finalNotes : ($fine->notes ?? null),
            ];
        });

        if (!($result['ok'] ?? false)) {
            if ($isJson) {
                return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Gagal.'], $result['status'] ?? 500);
            }
            return redirect()->back()->with('error', $result['message'] ?? 'Gagal memproses pembayaran denda.');
        }

        if ($isJson) {
            return response()->json($result);
        }

        return redirect()->back()->with('success', $result['message'] ?? 'Pembayaran denda berhasil.');
    }

    /*
    |--------------------------------------------------------------------------
    | DENDA - VOID
    |--------------------------------------------------------------------------
    */
    public function finesVoid(Request $request)
    {
        $this->ensureStaff();
        $this->requireStaffBranch($request);

        $isJson = $request->expectsJson()
            || $request->wantsJson()
            || $request->ajax()
            || $request->query('format') === 'json';

        if (!Schema::hasTable('fines')) {
            if ($isJson) {
                return response()->json(['ok' => false, 'message' => 'Tabel fines belum tersedia.'], 422);
            }
            return redirect()->back()->with('error', 'Tabel fines belum tersedia.');
        }

        $institutionId = $this->currentInstitutionId();
        $branchId = $this->staffBranchId();
        $user = Auth::user();

        $data = $request->validate([
            'loan_item_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $loanItemId = (int)$data['loan_item_id'];

        $notes = trim((string)($data['notes'] ?? ''));
        $notes = $notes !== '' ? $notes : null;

        $hasUpdatedAt = Schema::hasColumn('fines', 'updated_at');
        $hasNotes = Schema::hasColumn('fines', 'notes');

        $result = DB::transaction(function () use ($institutionId, $branchId, $loanItemId, $notes, $user, $hasUpdatedAt, $hasNotes) {
            $fineQ = DB::table('fines')
                ->join('loan_items', 'loan_items.id', '=', 'fines.loan_item_id')
                ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->where('fines.institution_id', $institutionId)
                ->where('fines.loan_item_id', $loanItemId);

            if ($this->shouldLockBranch()) {
                $fineQ->where('loans.branch_id', (int)$branchId);
            }

            $fine = $fineQ
                ->select(['fines.*', 'loans.branch_id as loan_branch_id'])
                ->lockForUpdate()
                ->first();

            if (!$fine) {
                return ['ok' => false, 'status' => 404, 'message' => 'Data denda tidak ditemukan atau tidak sesuai cabang.'];
            }

            $status = (string)($fine->status ?? 'unpaid');
            if ($status === 'paid') {
                return ['ok' => false, 'status' => 422, 'message' => 'Denda sudah lunas. Tidak bisa dibatalkan.'];
            }

            if ($status === 'void') {
                return [
                    'ok' => true,
                    'message' => 'Denda sudah dibatalkan (void).',
                    'fine_status' => 'void',
                    'paid_amount' => (int)($fine->paid_amount ?? 0),
                    'paid_at' => $fine->paid_at,
                    'notes' => $fine->notes,
                ];
            }

            $finalNotes = $fine->notes ?? null;
            if ($notes && $hasNotes) {
                $line = '[VOID ' . now()->format('Y-m-d H:i') . '] ' . $notes;
                $finalNotes = $finalNotes ? (trim((string)$finalNotes) . "
" . $line) : $line;
            }

            $update = [
                'status' => 'void',
            ];

            if ($hasNotes) {
                $update['notes'] = $finalNotes;
            }
            if ($hasUpdatedAt) {
                $update['updated_at'] = now();
            }

            DB::table('fines')
                ->where('institution_id', $institutionId)
                ->where('loan_item_id', $loanItemId)
                ->update($update);

            $this->writeAudit(
                action: 'fine.void',
                module: 'denda',
                auditableType: 'Fine',
                auditableId: $fine->id ?? null,
                metadata: [
                    'loan_item_id' => $loanItemId,
                    'notes' => $notes,
                ],
                institutionId: $institutionId,
                actorUserId: $user?->id,
                actorRole: $user?->role ?? null
            );

            return [
                'ok' => true,
                'message' => 'Denda berhasil dibatalkan (void).',
                'fine_status' => 'void',
                'paid_amount' => (int)($fine->paid_amount ?? 0),
                'paid_at' => $fine->paid_at,
                'notes' => $hasNotes ? $finalNotes : ($fine->notes ?? null),
            ];
        });

        if (!($result['ok'] ?? false)) {
            if ($isJson) {
                return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Gagal.'], $result['status'] ?? 500);
            }
            return redirect()->back()->with('error', $result['message'] ?? 'Gagal membatalkan denda.');
        }

        if ($isJson) {
            return response()->json($result);
        }

        return redirect()->back()->with('success', $result['message'] ?? 'Denda berhasil dibatalkan (void).');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers (Auth / Institution / Branch / Audit)
    |--------------------------------------------------------------------------
    */
    private function ensureStaff(): void
    {
        if (!Auth::check()) abort(403);

        $role = Auth::user()->role ?? 'member';
        if (!in_array($role, ['super_admin', 'admin', 'staff'], true)) abort(403);
    }
    
    private function isSuperAdmin(): bool
    {
        $role = Auth::user()->role ?? 'member';
        return $role === 'super_admin';
    }


    private function requireStaffBranch(?Request $request = null): void
    {
        if (!$this->shouldLockBranch()) return;

        $branchId = (int)(Auth::user()->branch_id ?? 0);
        if ($branchId > 0) return;

        $msg = 'Akun Anda belum memiliki cabang. Set branch_id pada user terlebih dahulu.';

        if ($request && $request->expectsJson()) {
            abort(response()->json(['ok' => false, 'message' => $msg], 422));
        }

        abort(403, $msg);
    }

    private function shouldLockBranch(): bool
    {
        $role = Auth::user()->role ?? 'member';
        return in_array($role, ['admin', 'staff'], true);
    }

    private function canSwitchBranch(): bool
    {
        $role = Auth::user()->role ?? 'member';
        return $role === 'super_admin';
    }

    private function staffBranchId(): ?int
    {
        $user = Auth::user();

        $base = (int)($user->branch_id ?? 0);
        if ($base <= 0) {
            return null;
        }

        // switch hanya untuk super_admin
        $active = (int)session('active_branch_id', 0);
        if ($active > 0 && $this->canSwitchBranch()) {
            $branch = DB::table('branches')
                ->select('id', 'institution_id', 'is_active')
                ->where('id', $active)
                ->first();

            if ($branch && (int)$branch->is_active === 1) {
                $userInstitutionId = (int)($user->institution_id ?? 0);
                if ($userInstitutionId <= 0 || (int)$branch->institution_id === $userInstitutionId) {
                    return $active;
                }
            }
        }

        return $base;
    }

    private function currentInstitutionId(): int
    {
        $id = (int)(Auth::user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    private function generateLoanCode(): string
    {
        do {
            $code = 'L-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        } while (DB::table('loans')->where('loan_code', $code)->exists());

        return $code;
    }

    private function writeAudit(
        string $action,
        ?string $module,
        ?string $auditableType,
        ?int $auditableId,
        array $metadata,
        ?int $institutionId,
        ?int $actorUserId,
        ?string $actorRole
    ): void {
        try {
            DB::table('audits')->insert([
                'institution_id' => $institutionId,
                'actor_user_id' => $actorUserId,
                'actor_role' => $actorRole,
                'action' => $action,
                'module' => $module,
                'auditable_type' => $auditableType,
                'auditable_id' => $auditableId,
                'metadata' => json_encode($metadata),
                'ip' => request()->ip(),
                'user_agent' => substr((string)request()->userAgent(), 0, 1000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers DENDA (snapshot)
    |--------------------------------------------------------------------------
    */
    private function fineRatePerDay(int $institutionId): int
    {
        $rate = 1000;

        try {
            if (Schema::hasTable('institutions') && Schema::hasColumn('institutions', 'fine_rate_per_day')) {
                $val = DB::table('institutions')->where('id', $institutionId)->value('fine_rate_per_day');
                if (is_numeric($val) && (int)$val > 0) $rate = (int)$val;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $rate;
    }

    private function calcDaysLate($dueAt, $returnedAt): int
    {
        if (empty($dueAt)) return 0;

        $due = strtotime((string)$dueAt);
        if ($due === false) return 0;

        $end = $returnedAt ? strtotime((string)$returnedAt) : time();
        if ($end === false) $end = time();

        $diff = $end - $due;
        if ($diff <= 0) return 0;

        return (int)floor($diff / 86400);
    }

    private function syncFinesSnapshot(int $institutionId, int $rate): void
    {
        if (!Schema::hasTable('fines')) return;

        $rows = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.institution_id', $institutionId)
            ->whereNotNull('loan_items.due_at')
            ->where(function ($w) {
                $w->where(function ($q) {
                    $q->whereNull('loan_items.returned_at')
                      ->where('loan_items.due_at', '<', now());
                })->orWhere(function ($q) {
                    $q->whereNotNull('loan_items.returned_at')
                      ->whereColumn('loan_items.returned_at', '>', 'loan_items.due_at');
                });
            })
            ->select([
                'loan_items.id as loan_item_id',
                'loan_items.due_at',
                'loan_items.returned_at',
                'loans.member_id as member_id',
            ])
            ->limit(500)
            ->get();

        foreach ($rows as $r) {
            $days = $this->calcDaysLate($r->due_at, $r->returned_at);
            $amount = $days * $rate;
            $memberId = (int)($r->member_id ?? 0);

            $exists = DB::table('fines')
                ->where('institution_id', $institutionId)
                ->where('loan_item_id', (int)$r->loan_item_id)
                ->first();

            if ($exists && isset($exists->status) && (string)$exists->status === 'paid') {
                continue;
            }

            DB::table('fines')->updateOrInsert(
                [
                    'institution_id' => $institutionId,
                    'loan_item_id' => (int)$r->loan_item_id,
                ],
                [
                    'member_id' => (int)($memberId ?? 0),
                    'status' => 'unpaid',
                    'days_late' => $days,
                    'rate' => $rate,
                    'amount' => $amount,
                    'updated_at' => now(),
                    'created_at' => $exists ? ($exists->created_at ?? now()) : now(),
                ]
            );
        }
    }

    private function buildFineSnapshotForLoanItem(int $institutionId, int $loanItemId, int $rate): ?array
    {
        $r = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->join('members', 'members.id', '=', 'loans.member_id')
            ->join('items', 'items.id', '=', 'loan_items.item_id')
            ->where('loans.institution_id', $institutionId)
            ->where('loan_items.id', $loanItemId)
            ->select([
                'loan_items.id as loan_item_id',
                'loans.id as loan_id',
                'loans.loan_code',
                'members.id as member_id',
                'members.full_name as member_name',
                'members.member_code as member_code',
                'items.barcode',
                'loan_items.due_at',
                'loan_items.returned_at',
            ])
            ->first();

        if (!$r) return null;

        $daysLate = $this->calcDaysLate($r->due_at, $r->returned_at);
        $amount = $daysLate * $rate;

        return [
            'loan_item_id' => (int)$r->loan_item_id,
            'loan_id' => (int)$r->loan_id,
            'loan_code' => (string)$r->loan_code,
            'member_id' => (int)($r->member_id ?? 0),
            'member_name' => (string)$r->member_name,
            'member_code' => (string)$r->member_code,
            'barcode' => (string)$r->barcode,
            'due_at' => $r->due_at,
            'returned_at' => $r->returned_at,
            'days_late' => (int)$daysLate,
            'rate' => (int)$rate,
            'amount' => (int)$amount,
        ];
    }

    private function upsertFineRow(int $institutionId, array $snap): void
    {
        if (!Schema::hasTable('fines')) return;

        $exists = DB::table('fines')
            ->where('institution_id', $institutionId)
            ->where('loan_item_id', (int)$snap['loan_item_id'])
            ->first();

        if ($exists && isset($exists->status) && in_array((string)$exists->status, ['paid', 'void'], true)) {
            return;
        }

        DB::table('fines')->updateOrInsert(
            [
                'institution_id' => $institutionId,
                'loan_item_id' => (int)$snap['loan_item_id'],
            ],
            [
                'member_id' => (int)($snap['member_id'] ?? 0),
                'status' => 'unpaid',
                'days_late' => (int)$snap['days_late'],
                'rate' => (int)$snap['rate'],
                'amount' => (int)$snap['amount'],
                'updated_at' => now(),
                'created_at' => $exists ? ($exists->created_at ?? now()) : now(),
            ]
        );
    }
}








