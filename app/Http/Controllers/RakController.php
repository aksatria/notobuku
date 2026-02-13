<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RakController extends Controller
{
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
            in_array(auth()->user()->role ?? 'member', ['super_admin','admin','staff'], true),
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
