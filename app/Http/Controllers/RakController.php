<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CatalogAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RakController extends Controller
{
    use CatalogAccess;

    /* =========================================================
     | Helpers
     ========================================================= */

    private function institutionId(): int
    {
        $id = (int)(auth()->user()->institution_id ?? 0);
        return $id > 0 ? $id : 1;
    }

    private function ensureManage(): void
    {
        abort_unless(
            auth()->check() &&
            $this->canManageCatalog(),
            403
        );
    }

    private function branchOptions(int $institutionId)
    {
        if (!Schema::hasTable('branches')) return collect();

        return DB::table('branches')
            ->where('institution_id', $institutionId)
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    private function shelfOrFail(int $id)
    {
        $institutionId = $this->institutionId();

        $row = DB::table('shelves')
            ->where('institution_id', $institutionId)
            ->where('id', $id)
            ->first();

        abort_if(!$row, 404);

        return $row;
    }

    private function ddcHundredsLabel(int $hundreds): string
    {
        return match ($hundreds) {
            0 => 'Karya Umum, Ilmu Komputer, Informasi',
            1 => 'Filsafat dan Psikologi',
            2 => 'Agama',
            3 => 'Ilmu Sosial',
            4 => 'Bahasa',
            5 => 'Sains',
            6 => 'Teknologi',
            7 => 'Seni dan Rekreasi',
            8 => 'Sastra',
            9 => 'Sejarah dan Geografi',
            default => 'Subjek Umum',
        };
    }

    private function parseDdc3(?string $value): ?string
    {
        $ddc = trim((string) $value);
        if ($ddc === '') return null;
        if (!preg_match('/(\d{3})/', $ddc, $m)) return null;
        return $m[1];
    }

    private function generateDdcShelvesForInstitution(int $institutionId): array
    {
        $branches = DB::table('branches')
            ->where('institution_id', $institutionId)
            ->where('is_active', 1)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        if ($branches->isEmpty()) {
            return ['created_or_updated' => 0, 'branches' => 0];
        }

        $affected = 0;
        foreach ($branches as $branch) {
            for ($hundreds = 0; $hundreds <= 9; $hundreds++) {
                for ($tens = 0; $tens <= 9; $tens++) {
                    $code3 = (string) ($hundreds * 100 + $tens * 10);
                    $code3 = str_pad($code3, 3, '0', STR_PAD_LEFT);
                    $code = 'DDC-' . $code3;

                    $rangeStart = $code3;
                    $rangeEnd = str_pad((string) ((int) $code3 + 9), 3, '0', STR_PAD_LEFT);
                    $hundredsLabel = $this->ddcHundredsLabel($hundreds);

                    $name = $tens === 0
                        ? $code3 . ' ' . $hundredsLabel
                        : $code3 . '-' . $rangeEnd . ' Subkelas ' . $hundredsLabel;

                    $location = 'Zona DDC ' . $code3 . '-' . $rangeEnd;
                    $notes = 'Auto-generate DDC ' . $code3 . '-' . $rangeEnd . ' (' . $branch->name . ')';

                    DB::table('shelves')->updateOrInsert(
                        [
                            'institution_id' => $institutionId,
                            'branch_id' => (int) $branch->id,
                            'code' => $code,
                        ],
                        [
                            'name' => $name,
                            'location' => $location,
                            'notes' => $notes,
                            'sort_order' => (($hundreds * 10) + $tens + 1) * 10,
                            'is_active' => 1,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                    $affected++;
                }
            }
        }

        return ['created_or_updated' => $affected, 'branches' => $branches->count()];
    }

    /* =========================================================
     | INDEX
     ========================================================= */

    public function index(Request $request)
    {
        $this->ensureManage();

        $institutionId = $this->institutionId();

        $q = trim((string)$request->query('q', ''));
        $branchId = (string)$request->query('branch_id', '');
        $status = (string)$request->query('status', ''); // 1 / 0 / ''

        $query = DB::table('shelves')
            ->where('shelves.institution_id', $institutionId)
            ->leftJoin('branches', function ($j) use ($institutionId) {
                $j->on('branches.id', '=', 'shelves.branch_id')
                  ->where('branches.institution_id', '=', $institutionId);
            })
            ->select([
                'shelves.*',
                'branches.name as branch_name',
            ]);

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('shelves.name', 'like', "%{$q}%")
                   ->orWhere('shelves.code', 'like', "%{$q}%")
                   ->orWhere('shelves.location', 'like', "%{$q}%");
            });
        }

        if ($branchId !== '') {
            $query->where('shelves.branch_id', (int)$branchId);
        }

        if ($status !== '') {
            $query->where('shelves.is_active', (int)$status);
        }

        $shelves = $query
            ->orderByDesc('shelves.is_active')
            ->orderBy('branches.name')
            ->orderBy('shelves.sort_order')
            ->orderBy('shelves.name')
            ->paginate(20)
            ->withQueryString();

        $branches = $this->branchOptions($institutionId);

        return view('rak.index', [
            'shelves'  => $shelves,
            'branches' => $branches,
            'q'        => $q,
            'branchId' => $branchId,
            'status'   => $status,
        ]);
    }

    /* =========================================================
     | CREATE
     ========================================================= */

    public function create()
    {
        $this->ensureManage();

        $institutionId = $this->institutionId();
        $branches = $this->branchOptions($institutionId);

        return view('rak.create', [
            'branches' => $branches,
        ]);
    }

    /* =========================================================
     | STORE
     ========================================================= */

    public function store(Request $request)
    {
        $this->ensureManage();

        $institutionId = $this->institutionId();

        $data = $request->validate([
            'branch_id'  => ['required','integer'],
            'name'       => ['required','string','max:150'],
            'code'       => ['nullable','string','max:50'],
            'location'   => ['nullable','string','max:255'],
            'notes'      => ['nullable','string'],
            'sort_order' => ['nullable','integer','min:0'],
        ]);

        // pastikan cabang valid milik institution
        $branchExists = DB::table('branches')
            ->where('institution_id', $institutionId)
            ->where('id', (int)$data['branch_id'])
            ->exists();

        if (!$branchExists) {
            return back()->withInput()->withErrors([
                'branch_id' => 'Cabang tidak valid.',
            ]);
        }

        // code unik per institution + cabang (jika diisi)
        if (!empty($data['code'])) {
            $exists = DB::table('shelves')
                ->where('institution_id', $institutionId)
                ->where('branch_id', (int)$data['branch_id'])
                ->where('code', $data['code'])
                ->exists();

            if ($exists) {
                return back()
                    ->withInput()
                    ->withErrors(['code' => 'Kode rak sudah digunakan di cabang ini.']);
            }
        }

        DB::table('shelves')->insert([
            'institution_id' => $institutionId,
            'branch_id'      => (int)$data['branch_id'],
            'name'           => $data['name'],
            'code'           => $data['code'] ?? null,
            'location'       => $data['location'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'sort_order'     => isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
            'is_active'      => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return redirect()
            ->route('rak.index')
            ->with('success', 'Rak berhasil ditambahkan.');
    }

    /* =========================================================
     | EDIT
     ========================================================= */

    public function edit($id)
    {
        $this->ensureManage();

        $shelf = $this->shelfOrFail((int)$id);
        $institutionId = $this->institutionId();
        $branches = $this->branchOptions($institutionId);

        return view('rak.edit', [
            'shelf'    => $shelf,
            'branches' => $branches,
        ]);
    }

    /* =========================================================
     | UPDATE
     ========================================================= */

    public function update(Request $request, $id)
    {
        $this->ensureManage();

        $institutionId = $this->institutionId();
        $shelfId = (int)$id;

        $shelf = $this->shelfOrFail($shelfId);

        $data = $request->validate([
            'branch_id'  => ['required','integer'],
            'name'       => ['required','string','max:150'],
            'code'       => ['nullable','string','max:50'],
            'location'   => ['nullable','string','max:255'],
            'notes'      => ['nullable','string'],
            'sort_order' => ['nullable','integer','min:0'],
        ]);

        $branchExists = DB::table('branches')
            ->where('institution_id', $institutionId)
            ->where('id', (int)$data['branch_id'])
            ->exists();

        if (!$branchExists) {
            return back()->withInput()->withErrors([
                'branch_id' => 'Cabang tidak valid.',
            ]);
        }

        if (!empty($data['code'])) {
            $exists = DB::table('shelves')
                ->where('institution_id', $institutionId)
                ->where('branch_id', (int)$data['branch_id'])
                ->where('code', $data['code'])
                ->where('id', '!=', $shelfId)
                ->exists();

            if ($exists) {
                return back()
                    ->withInput()
                    ->withErrors(['code' => 'Kode rak sudah digunakan di cabang ini.']);
            }
        }

        DB::table('shelves')
            ->where('id', $shelfId)
            ->update([
                'branch_id'  => (int)$data['branch_id'],
                'name'       => $data['name'],
                'code'       => $data['code'] ?? null,
                'location'   => $data['location'] ?? null,
                'notes'      => $data['notes'] ?? null,
                'sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('rak.index')
            ->with('success', 'Rak berhasil diperbarui.');
    }

    /* =========================================================
     | TOGGLE ACTIVE
     ========================================================= */

    public function toggleActive($id)
    {
        $this->ensureManage();

        $shelf = $this->shelfOrFail((int)$id);

        DB::table('shelves')
            ->where('id', $shelf->id)
            ->update([
                'is_active'  => $shelf->is_active ? 0 : 1,
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('rak.index')
            ->with('success', 'Status rak diperbarui.');
    }

    public function generateDdc(Request $request)
    {
        $this->ensureManage();
        $institutionId = $this->institutionId();

        $result = $this->generateDdcShelvesForInstitution($institutionId);
        if ((int) $result['created_or_updated'] === 0) {
            return redirect()
                ->route('rak.index')
                ->with('error', 'Tidak ada cabang aktif. Rak DDC belum dibuat.');
        }

        return redirect()
            ->route('rak.index')
            ->with(
                'success',
                'Rak DDC detail berhasil diproses: ' .
                $result['created_or_updated'] .
                ' rak untuk ' .
                $result['branches'] .
                ' cabang.'
            );
    }

    public function mapItemsByDdc(Request $request)
    {
        $this->ensureManage();
        $institutionId = $this->institutionId();

        // Pastikan rak DDC siap sebelum mapping.
        $this->generateDdcShelvesForInstitution($institutionId);

        $ddcShelves = DB::table('shelves')
            ->where('institution_id', $institutionId)
            ->where('is_active', 1)
            ->where('code', 'like', 'DDC-%')
            ->select('id', 'branch_id', 'code')
            ->get();

        $shelfMap = [];
        foreach ($ddcShelves as $s) {
            $shelfMap[(int) $s->branch_id][(string) $s->code] = (int) $s->id;
        }

        $query = DB::table('items')
            ->join('biblio', 'biblio.id', '=', 'items.biblio_id')
            ->leftJoin('shelves as current_shelf', 'current_shelf.id', '=', 'items.shelf_id')
            ->where('items.institution_id', $institutionId)
            ->whereNotNull('items.branch_id')
            ->whereNotNull('biblio.ddc')
            ->select([
                'items.id',
                'items.branch_id',
                'items.shelf_id',
                'biblio.ddc',
                'current_shelf.code as current_shelf_code',
            ]);

        $mapped = 0;
        $skipped = 0;

        $query->orderBy('items.id')->chunk(500, function ($rows) use (&$mapped, &$skipped, $shelfMap) {
            foreach ($rows as $row) {
                $branchId = (int) $row->branch_id;
                $ddc3 = $this->parseDdc3((string) $row->ddc);
                if (!$ddc3) {
                    $skipped++;
                    continue;
                }

                $targetId = null;
                $exactCode = 'DDC-' . $ddc3;
                $hundredsCode = 'DDC-' . $ddc3[0] . '00';

                if (isset($shelfMap[$branchId][$exactCode])) {
                    $targetId = (int) $shelfMap[$branchId][$exactCode];
                } elseif (isset($shelfMap[$branchId][$hundredsCode])) {
                    $targetId = (int) $shelfMap[$branchId][$hundredsCode];
                }

                if (!$targetId) {
                    $skipped++;
                    continue;
                }

                $currentCode = (string) ($row->current_shelf_code ?? '');
                $isCurrentDdcShelf = str_starts_with($currentCode, 'DDC-');

                // Update jika shelf kosong, atau shelf DDC lama perlu diselaraskan.
                if ($row->shelf_id === null || (int) $row->shelf_id === 0 || $isCurrentDdcShelf) {
                    if ((int) $row->shelf_id !== $targetId) {
                        DB::table('items')
                            ->where('id', (int) $row->id)
                            ->update([
                                'shelf_id' => $targetId,
                                'updated_at' => now(),
                            ]);
                        $mapped++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
            }
        });

        return redirect()
            ->route('rak.index')
            ->with('success', "Mapping item→rak DDC selesai. Terpetakan: {$mapped}, dilewati: {$skipped}.");
    }

    /* =========================================================
     | DESTROY (AMAN)
     ========================================================= */

    public function destroy($id)
    {
        $this->ensureManage();

        $shelfId = (int)$id;
        $shelf = $this->shelfOrFail($shelfId);

        // Cegah hapus jika dipakai item
        if (Schema::hasTable('items')) {
            $used = DB::table('items')
                ->where('shelf_id', $shelfId)
                ->exists();

            if ($used) {
                return back()
                    ->with('error', 'Rak tidak bisa dihapus karena masih digunakan eksemplar.');
            }
        }

        DB::table('shelves')->where('id', $shelfId)->delete();

        return redirect()
            ->route('rak.index')
            ->with('success', 'Rak berhasil dihapus.');
    }
}
