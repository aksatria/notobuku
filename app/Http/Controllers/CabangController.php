<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CatalogAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CabangController extends Controller
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

    private function branchOrFail(int $id)
    {
        // DB::table() TIDAK punya firstOrFail()
        $row = DB::table('branches')
            ->where('institution_id', $this->institutionId())
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
        $status = (string)$request->query('status', '');

        $query = DB::table('branches')
            ->where('institution_id', $institutionId);

        if ($status !== '') {
            // status harus 0/1
            $query->where('is_active', (int)$status);
        }

        $branches = $query
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('cabang.index', [
            'branches' => $branches,
            'status'   => $status,
        ]);
    }

    /* =========================================================
     | CREATE
     ========================================================= */

    public function create()
    {
        $this->ensureManage();

        return view('cabang.create');
    }

    /* =========================================================
     | STORE
     ========================================================= */

    public function store(Request $request)
    {
        $this->ensureManage();

        $institutionId = $this->institutionId();

        $data = $request->validate([
            'name'    => ['required','string','max:150'],
            'code'    => ['nullable','string','max:50'],
            'address' => ['nullable','string','max:255'],
            'notes'   => ['nullable','string'],
        ]);

        // code unik per institution (jika diisi)
        if (!empty($data['code'])) {
            $exists = DB::table('branches')
                ->where('institution_id', $institutionId)
                ->where('code', $data['code'])
                ->exists();

            if ($exists) {
                return back()
                    ->withInput()
                    ->withErrors(['code' => 'Kode cabang sudah digunakan.']);
            }
        }

        DB::table('branches')->insert([
            'institution_id' => $institutionId,
            'name'           => $data['name'],
            'code'           => $data['code'] ?? null,
            'address'        => $data['address'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'is_active'      => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return redirect()
            ->route('cabang.index')
            ->with('success', 'Cabang berhasil ditambahkan.');
    }

    /* =========================================================
     | EDIT
     ========================================================= */

    public function edit($id)
    {
        $this->ensureManage();

        $branch = $this->branchOrFail((int)$id);

        return view('cabang.edit', [
            'branch' => $branch,
        ]);
    }

    /* =========================================================
     | UPDATE
     ========================================================= */

    public function update(Request $request, $id)
    {
        $this->ensureManage();

        $institutionId = $this->institutionId();
        $branchId = (int)$id;

        // pastikan milik institusi ini
        $branch = $this->branchOrFail($branchId);

        $data = $request->validate([
            'name'    => ['required','string','max:150'],
            'code'    => ['nullable','string','max:50'],
            'address' => ['nullable','string','max:255'],
            'notes'   => ['nullable','string'],
        ]);

        if (!empty($data['code'])) {
            $exists = DB::table('branches')
                ->where('institution_id', $institutionId)
                ->where('code', $data['code'])
                ->where('id', '!=', $branchId)
                ->exists();

            if ($exists) {
                return back()
                    ->withInput()
                    ->withErrors(['code' => 'Kode cabang sudah digunakan.']);
            }
        }

        DB::table('branches')
            ->where('institution_id', $institutionId)
            ->where('id', $branchId)
            ->update([
                'name'       => $data['name'],
                'code'       => $data['code'] ?? null,
                'address'    => $data['address'] ?? null,
                'notes'      => $data['notes'] ?? null,
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('cabang.index')
            ->with('success', 'Cabang berhasil diperbarui.');
    }

    /* =========================================================
     | TOGGLE ACTIVE (AMAN)
     ========================================================= */

    public function toggleActive($id)
    {
        $this->ensureManage();

        $institutionId = $this->institutionId();
        $branch = $this->branchOrFail((int)$id);

        DB::table('branches')
            ->where('institution_id', $institutionId)
            ->where('id', $branch->id)
            ->update([
                'is_active'  => $branch->is_active ? 0 : 1,
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('cabang.index')
            ->with('success', 'Status cabang diperbarui.');
    }

    /* =========================================================
     | DESTROY (OPTIONAL & AMAN)
     ========================================================= */

    public function destroy($id)
    {
        $this->ensureManage();

        $institutionId = $this->institutionId();
        $branchId = (int)$id;

        $branch = $this->branchOrFail($branchId);

        // Cegah hapus jika dipakai item
        if (Schema::hasTable('items')) {
            $used = DB::table('items')
                ->where('branch_id', $branchId)
                ->exists();

            if ($used) {
                return back()
                    ->with('error', 'Cabang tidak bisa dihapus karena masih digunakan eksemplar.');
            }
        }

        DB::table('branches')
            ->where('institution_id', $institutionId)
            ->where('id', $branchId)
            ->delete();

        return redirect()
            ->route('cabang.index')
            ->with('success', 'Cabang berhasil dihapus.');
    }
}
