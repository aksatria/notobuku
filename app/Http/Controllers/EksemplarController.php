<?php

namespace App\Http\Controllers;

use App\Models\Biblio;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EksemplarController extends Controller
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

    private function ensureManage(): void
    {
        abort_unless(auth()->check() && $this->canManage(), 403);
    }

    private function getBiblioOrFail(int $biblioId): Biblio
    {
        $institutionId = $this->currentInstitutionId();

        return Biblio::query()
            ->where('institution_id', $institutionId)
            ->withCount(['items', 'availableItems'])
            ->findOrFail($biblioId);
    }

    private function getItemOrFail(int $biblioId, int $itemId): Item
    {
        $institutionId = $this->currentInstitutionId();

        return Item::query()
            ->where('institution_id', $institutionId)
            ->where('biblio_id', $biblioId)
            ->findOrFail($itemId);
    }

    private function generateUnique(string $prefix, string $column): string
    {
        $date = now()->format('Ymd');

        for ($tries = 0; $tries < 30; $tries++) {
            $code = $prefix . '-' . $date . '-' . Str::upper(Str::random(6));
            if (!Item::query()->where($column, $code)->exists()) return $code;
        }

        return $prefix . '-' . $date . '-' . Str::upper(Str::random(10));
    }

    private function branchesOptions(int $institutionId)
    {
        if (!Schema::hasTable('branches')) return collect();

        // tambah field opsional untuk UI (tidak merusak yg lama)
        $cols = ['id', 'name'];
        if (Schema::hasColumn('branches', 'code')) $cols[] = 'code';
        if (Schema::hasColumn('branches', 'is_active')) $cols[] = 'is_active';

        $q = DB::table('branches')
            ->where('institution_id', $institutionId)
            ->select($cols);

        // kalau ada is_active, tampilkan aktif dulu
        if (in_array('is_active', $cols, true)) {
            $q->orderByDesc('is_active');
        }

        return $q->orderBy('name')->get();
    }

    private function shelvesOptions(int $institutionId)
    {
        if (!Schema::hasTable('shelves')) return collect();

        // tambah field opsional untuk UI (tidak merusak yg lama)
        $cols = ['id', 'name', 'branch_id'];
        if (Schema::hasColumn('shelves', 'code')) $cols[] = 'code';
        if (Schema::hasColumn('shelves', 'location')) $cols[] = 'location';
        if (Schema::hasColumn('shelves', 'notes')) $cols[] = 'notes';
        if (Schema::hasColumn('shelves', 'is_active')) $cols[] = 'is_active';
        if (Schema::hasColumn('shelves', 'sort_order')) $cols[] = 'sort_order';

        $q = DB::table('shelves')
            ->where('institution_id', $institutionId)
            ->select($cols);

        if (in_array('is_active', $cols, true)) $q->orderByDesc('is_active');
        if (in_array('sort_order', $cols, true)) $q->orderBy('sort_order');

        return $q->orderBy('name')->get();
    }

    /**
     * =========================================================
     * VALIDASI RELASI CABANG + RAK (AMAN)
     * - Jika shelf_id diisi, branch_id harus ada
     * - shelf harus milik branch yang dipilih
     * - semua harus dalam institution yang sama
     * =========================================================
     */
    private function validateBranchShelf(int $institutionId, ?int $branchId, ?int $shelfId): ?string
    {
        if (!$shelfId) return null;

        if (!$branchId) {
            return 'Pilih Cabang terlebih dahulu sebelum memilih Rak.';
        }

        // validasi branch dalam institution
        if (Schema::hasTable('branches')) {
            $branchOk = DB::table('branches')
                ->where('institution_id', $institutionId)
                ->where('id', $branchId)
                ->exists();

            if (!$branchOk) return 'Cabang tidak valid.';
        }

        // validasi shelf milik branch dalam institution
        if (Schema::hasTable('shelves')) {
            $shelfOk = DB::table('shelves')
                ->where('institution_id', $institutionId)
                ->where('id', $shelfId)
                ->where('branch_id', $branchId)
                ->exists();

            if (!$shelfOk) return 'Rak tidak valid atau tidak sesuai cabang.';
        }

        return null;
    }

    /**
     * =========================================================
     * INDEX (FIXED & AMAN)
     * - Join Branch dari items.branch_id (bukan dari shelf)
     * - Join Shelf dari items.shelf_id
     * - Filter branch_id dan shelf_id pakai kolom items.* (benar)
     * - Tetap bisa tampil branch_name & shelf_name
     * - Dropdown rak dependent (server-side) tetap dipertahankan
     * =========================================================
     */
    public function index(Request $request, $id)
    {
        $this->ensureManage();

        $biblioId = (int) $id;
        $biblio = $this->getBiblioOrFail($biblioId);
        $institutionId = $this->currentInstitutionId();

        $q = trim((string) $request->query('q', ''));
        $branchId = (string) $request->query('branch_id', '');
        $shelfId  = (string) $request->query('shelf_id', '');
        $status   = (string) $request->query('status', '');

        $hasInventoryNumber = Schema::hasColumn('items', 'inventory_number');

        // Query utama + join untuk label (tanpa mengubah data items)
        $itemsQuery = Item::query()
            ->where('items.institution_id', $institutionId)
            ->where('items.biblio_id', $biblio->id)
            ->leftJoin('branches', function ($join) use ($institutionId) {
                $join->on('branches.id', '=', 'items.branch_id')
                     ->where('branches.institution_id', '=', $institutionId);
            })
            ->leftJoin('shelves', function ($join) use ($institutionId) {
                $join->on('shelves.id', '=', 'items.shelf_id')
                     ->where('shelves.institution_id', '=', $institutionId);
            })
            ->select([
                'items.*',
                'branches.name as branch_name',
                'shelves.name as shelf_name',
            ]);

        if ($q !== '') {
            $itemsQuery->where(function ($qq) use ($q, $hasInventoryNumber) {
                $qq->where('items.barcode', 'like', "%{$q}%")
                   ->orWhere('items.accession_number', 'like', "%{$q}%")
                   ->orWhere('items.inventory_code', 'like', "%{$q}%");

                if ($hasInventoryNumber) {
                    $qq->orWhere('items.inventory_number', 'like', "%{$q}%");
                }
            });
        }

        if ($status !== '') {
            $itemsQuery->where('items.status', $status);
        }

        // Filter yang BENAR: pakai items.*
        if ($branchId !== '') {
            $itemsQuery->where('items.branch_id', (int) $branchId);
        }
        if ($shelfId !== '') {
            $itemsQuery->where('items.shelf_id', (int) $shelfId);
        }

        $items = $itemsQuery
            ->orderByRaw("CASE items.status WHEN 'available' THEN 0 WHEN 'reserved' THEN 1 WHEN 'borrowed' THEN 2 WHEN 'maintenance' THEN 3 WHEN 'damaged' THEN 4 WHEN 'lost' THEN 5 ELSE 99 END")
            ->orderBy('items.barcode')
            ->paginate(20)
            ->withQueryString();

        $branches = $this->branchesOptions($institutionId);

        // Dropdown rak dependent (server-side) tetap kamu pertahankan
        $shelves = collect();
        if ($branchId !== '' && Schema::hasTable('shelves')) {
            $shelves = DB::table('shelves')
                ->where('institution_id', $institutionId)
                ->where('branch_id', (int) $branchId)
                ->select(['id', 'name', 'branch_id'])
                ->orderBy('name')
                ->get();
        }

        return view('eksemplar.index', [
            'biblio' => $biblio,
            'items' => $items,

            'q' => $q,
            'branchId' => $branchId,
            'shelfId' => $shelfId,
            'status' => $status,

            'branches' => $branches,
            'shelves' => $shelves,

            'canManage' => true,
        ]);
    }

    public function create($id)
    {
        $this->ensureManage();

        $biblioId = (int) $id;
        $biblio = $this->getBiblioOrFail($biblioId);
        $institutionId = $this->currentInstitutionId();

        $branches = $this->branchesOptions($institutionId);
        $shelves  = $this->shelvesOptions($institutionId);

        return view('eksemplar.create', [
            'biblio' => $biblio,
            'branches' => $branches,
            'shelves' => $shelves,
            'canManage' => true,
        ]);
    }

    public function store(Request $request, $id)
    {
        $this->ensureManage();

        $biblioId = (int) $id;
        $biblio = $this->getBiblioOrFail($biblioId);
        $institutionId = $this->currentInstitutionId();

        $hasInventoryNumber = Schema::hasColumn('items', 'inventory_number');
        $hasCondition       = Schema::hasColumn('items', 'condition');
        $hasLocationNote    = Schema::hasColumn('items', 'location_note');
        $hasAcqSource       = Schema::hasColumn('items', 'acquisition_source');
        $hasCircStatus      = Schema::hasColumn('items', 'circulation_status');
        $hasIsRef           = Schema::hasColumn('items', 'is_reference');

        $rules = [
            'barcode' => ['nullable', 'string', 'max:80'],
            'accession_number' => ['nullable', 'string', 'max:80'],

            'branch_id' => ['nullable', 'integer'],
            'shelf_id' => ['nullable', 'integer'],

            'status' => ['required', 'in:available,borrowed,reserved,lost,damaged,maintenance'],

            'acquired_at' => ['nullable', 'date'],
            'price' => ['nullable', 'numeric', 'min:0'],

            'inventory_code' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];

        if ($hasInventoryNumber) $rules['inventory_number'] = ['nullable', 'string', 'max:64'];
        if ($hasCondition) $rules['condition'] = ['nullable', 'string', 'max:32'];
        if ($hasLocationNote) $rules['location_note'] = ['nullable', 'string', 'max:255'];
        if ($hasAcqSource) $rules['acquisition_source'] = ['nullable', 'in:beli,hibah,tukar'];
        if ($hasCircStatus) $rules['circulation_status'] = ['nullable', 'string', 'max:32'];
        if ($hasIsRef) $rules['is_reference'] = ['nullable'];

        $data = $request->validate($rules);

        // âœ… VALIDASI RELASI CABANG + RAK
        $branchId = isset($data['branch_id']) && (string)$data['branch_id'] !== '' ? (int)$data['branch_id'] : null;
        $shelfId  = isset($data['shelf_id']) && (string)$data['shelf_id'] !== '' ? (int)$data['shelf_id'] : null;

        $relErr = $this->validateBranchShelf($institutionId, $branchId, $shelfId);
        if ($relErr) {
            return back()->withInput()->withErrors(['shelf_id' => $relErr]);
        }

        $barcode = trim((string)($data['barcode'] ?? ''));
        $acc     = trim((string)($data['accession_number'] ?? ''));

        if ($barcode === '') $barcode = $this->generateUnique('NB', 'barcode');
        if ($acc === '') $acc = $this->generateUnique('ACC', 'accession_number');

        if (Item::query()->where('barcode', $barcode)->exists()) {
            return back()->withInput()->with('error', 'Barcode sudah digunakan. Silakan ubah atau kosongkan untuk auto-generate.');
        }
        if (Item::query()->where('accession_number', $acc)->exists()) {
            return back()->withInput()->with('error', 'Accession sudah digunakan. Silakan ubah atau kosongkan untuk auto-generate.');
        }

        if ($hasInventoryNumber) {
            $invNo = trim((string)($data['inventory_number'] ?? ''));
            if ($invNo !== '' && Item::query()->where('inventory_number', $invNo)->exists()) {
                return back()->withInput()->with('error', 'Nomor inventaris sudah digunakan.');
            }
        }

        $payload = [
            'institution_id' => $institutionId,
            'biblio_id' => $biblio->id,

            // tetap simpan seperti struktur kamu
            'branch_id' => $branchId,
            'shelf_id'  => $shelfId,

            'barcode' => $barcode,
            'accession_number' => $acc,

            'inventory_code' => $data['inventory_code'] ?? null,
            'status' => $data['status'],

            'acquired_at' => $data['acquired_at'] ?? null,
            'price' => $data['price'] ?? null,

            'source' => $data['source'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        if ($hasInventoryNumber) $payload['inventory_number'] = $data['inventory_number'] ?? null;
        if ($hasCondition) $payload['condition'] = $data['condition'] ?? null;
        if ($hasLocationNote) $payload['location_note'] = $data['location_note'] ?? null;
        if ($hasAcqSource) $payload['acquisition_source'] = $data['acquisition_source'] ?? 'beli';
        if ($hasCircStatus) $payload['circulation_status'] = $data['circulation_status'] ?? 'circulating';
        if ($hasIsRef) {
            $payload['is_reference'] = isset($data['is_reference'])
                ? in_array((string)$data['is_reference'], ['1', 'true', 'on', 'yes'], true)
                : false;
        }

        Item::create($payload);

        return redirect()
            ->route('eksemplar.index', $biblio->id)
            ->with('success', 'Eksemplar berhasil ditambahkan.');
    }

    public function edit($id, $item)
    {
        $this->ensureManage();

        $biblioId = (int) $id;
        $itemId = (int) $item;

        $biblio = $this->getBiblioOrFail($biblioId);
        $it = $this->getItemOrFail($biblio->id, $itemId);

        $institutionId = $this->currentInstitutionId();
        $branches = $this->branchesOptions($institutionId);
        $shelves  = $this->shelvesOptions($institutionId);

        return view('eksemplar.edit', [
            'biblio' => $biblio,
            'item' => $it,
            'branches' => $branches,
            'shelves' => $shelves,
            'canManage' => true,
        ]);
    }

    public function update(Request $request, $id, $item)
    {
        $this->ensureManage();

        $biblioId = (int) $id;
        $itemId = (int) $item;

        $biblio = $this->getBiblioOrFail($biblioId);
        $it = $this->getItemOrFail($biblio->id, $itemId);

        $institutionId = $this->currentInstitutionId();

        $hasInventoryNumber = Schema::hasColumn('items', 'inventory_number');
        $hasCondition       = Schema::hasColumn('items', 'condition');
        $hasLocationNote    = Schema::hasColumn('items', 'location_note');
        $hasAcqSource       = Schema::hasColumn('items', 'acquisition_source');
        $hasCircStatus      = Schema::hasColumn('items', 'circulation_status');
        $hasIsRef           = Schema::hasColumn('items', 'is_reference');

        $rules = [
            'barcode' => ['nullable', 'string', 'max:80'],
            'accession_number' => ['nullable', 'string', 'max:80'],

            'branch_id' => ['nullable', 'integer'],
            'shelf_id' => ['nullable', 'integer'],

            'status' => ['required', 'in:available,borrowed,reserved,lost,damaged,maintenance'],

            'acquired_at' => ['nullable', 'date'],
            'price' => ['nullable', 'numeric', 'min:0'],

            'inventory_code' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];

        if ($hasInventoryNumber) $rules['inventory_number'] = ['nullable', 'string', 'max:64'];
        if ($hasCondition) $rules['condition'] = ['nullable', 'string', 'max:32'];
        if ($hasLocationNote) $rules['location_note'] = ['nullable', 'string', 'max:255'];
        if ($hasAcqSource) $rules['acquisition_source'] = ['nullable', 'in:beli,hibah,tukar'];
        if ($hasCircStatus) $rules['circulation_status'] = ['nullable', 'string', 'max:32'];
        if ($hasIsRef) $rules['is_reference'] = ['nullable'];

        $data = $request->validate($rules);

        // âœ… VALIDASI RELASI CABANG + RAK
        $branchId = isset($data['branch_id']) && (string)$data['branch_id'] !== '' ? (int)$data['branch_id'] : null;
        $shelfId  = isset($data['shelf_id']) && (string)$data['shelf_id'] !== '' ? (int)$data['shelf_id'] : null;

        $relErr = $this->validateBranchShelf($institutionId, $branchId, $shelfId);
        if ($relErr) {
            return back()->withInput()->withErrors(['shelf_id' => $relErr]);
        }

        $barcode = trim((string)($data['barcode'] ?? $it->barcode));
        $acc     = trim((string)($data['accession_number'] ?? $it->accession_number));

        if ($barcode !== $it->barcode && Item::query()->where('barcode', $barcode)->exists()) {
            return back()->withInput()->with('error', 'Barcode sudah digunakan.');
        }
        if ($acc !== $it->accession_number && Item::query()->where('accession_number', $acc)->exists()) {
            return back()->withInput()->with('error', 'Accession sudah digunakan.');
        }

        if ($hasInventoryNumber) {
            $invNo = trim((string)($data['inventory_number'] ?? ''));
            if ($invNo !== '' && $invNo !== (string)($it->inventory_number ?? '') &&
                Item::query()->where('inventory_number', $invNo)->exists()
            ) {
                return back()->withInput()->with('error', 'Nomor inventaris sudah digunakan.');
            }
        }

        // tetap simpan struktur kamu
        $it->branch_id = $branchId;
        $it->shelf_id  = $shelfId;

        $it->barcode = $barcode;
        $it->accession_number = $acc;

        $it->inventory_code = $data['inventory_code'] ?? null;
        $it->status = $data['status'];

        $it->acquired_at = $data['acquired_at'] ?? null;
        $it->price = $data['price'] ?? null;

        $it->source = $data['source'] ?? null;
        $it->notes = $data['notes'] ?? null;

        if ($hasInventoryNumber) $it->inventory_number = $data['inventory_number'] ?? null;
        if ($hasCondition) $it->condition = $data['condition'] ?? null;
        if ($hasLocationNote) $it->location_note = $data['location_note'] ?? null;
        if ($hasAcqSource) $it->acquisition_source = $data['acquisition_source'] ?? ($it->acquisition_source ?? 'beli');
        if ($hasCircStatus) $it->circulation_status = $data['circulation_status'] ?? ($it->circulation_status ?? 'circulating');
        if ($hasIsRef) {
            $it->is_reference = isset($data['is_reference'])
                ? in_array((string)$data['is_reference'], ['1', 'true', 'on', 'yes'], true)
                : false;
        }

        $it->save();

        return redirect()
            ->route('eksemplar.index', $biblio->id)
            ->with('success', 'Eksemplar berhasil diperbarui.');
    }

    public function destroy($id, $item)
    {
        $this->ensureManage();

        $biblioId = (int) $id;
        $itemId = (int) $item;

        $biblio = $this->getBiblioOrFail($biblioId);
        $it = $this->getItemOrFail($biblio->id, $itemId);

        $it->delete();

        return redirect()
            ->route('eksemplar.index', $biblio->id)
            ->with('success', 'Eksemplar berhasil dihapus.');
    }
}
