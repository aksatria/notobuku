<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class UserPreferenceController extends Controller
{
    /**
     * Persist sidebar collapsed preference to users.sidebar_collapsed.
     */
    public function setSidebar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'collapsed' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->sidebar_collapsed = (bool) $data['collapsed'];
        $user->save();

        return response()->json([
            'ok' => true,
            'collapsed' => (bool) $user->sidebar_collapsed,
        ]);
    }

    /**
     * Simpan preferensi UI katalog (admin/staff).
     * - skeleton_enabled: boolean
     * - preload_mode: normal | aggressive
     */
    public function setKatalogUi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'skeleton_enabled' => ['required', 'boolean'],
            'preload_margin' => ['nullable', 'integer', 'in:300,500,800'],
            'preload_mode' => ['nullable', 'in:normal,aggressive'],
        ]);

        $user = $request->user();
        $user->katalog_skeleton_enabled = (bool) $data['skeleton_enabled'];
        $preloadMargin = $data['preload_margin'] ?? null;
        if ($preloadMargin === null && isset($data['preload_mode'])) {
            $preloadMargin = $data['preload_mode'] === 'aggressive' ? 800 : 300;
        }
        $user->katalog_preload_margin = (int) ($preloadMargin ?? 300);
        $user->katalog_preload_set = true;
        $user->save();

        return response()->json([
            'ok' => true,
            'skeleton_enabled' => (bool) $user->katalog_skeleton_enabled,
            'preload_margin' => (int) $user->katalog_preload_margin,
        ]);
    }

    /**
     * (Opsional) Halaman UI switch cabang. Boleh tidak dipakai.
     * Tetap disediakan agar kompatibel bila masih ada link lama.
     */
    public function switchBranchPage(Request $request)
    {
        $user = $request->user();

        if (($user->role ?? 'member') !== 'super_admin') {
            abort(403, 'Akses ditolak. Hanya super admin yang dapat mengganti cabang aktif.');
        }

        $branches = DB::table('branches')
            ->select('id', 'name', 'is_active', 'institution_id')
            ->orderBy('name')
            ->get();

        return view('admin.switch_cabang', [
            'branches' => $branches,
            'active_branch_id' => (int) $request->session()->get('active_branch_id', 0),
        ]);
    }

    /**
     * Switch cabang aktif untuk transaksi.
     *
     * - HANYA super_admin
     * - Simpan di session: active_branch_id
     * - Validasi: branch ada, aktif, (jika user institution_id > 0) harus satu institusi
     *
     * NOTE:
     * - Jika request AJAX/JSON => balas JSON (tanpa redirect)
     * - Jika request biasa => back() agar kompatibel
     */
    public function setActiveBranch(Request $request)
    {
        $user = $request->user();

        if (($user->role ?? 'member') !== 'super_admin') {
            abort(403, 'Akses ditolak. Hanya super admin yang dapat mengganti cabang aktif.');
        }

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'min:1'],
        ]);

        $branchId = (int) $data['branch_id'];

        $branch = DB::table('branches')
            ->select('id', 'institution_id', 'is_active', 'name')
            ->where('id', $branchId)
            ->first();

        if (!$branch) {
            return $this->branchError($request, 'Cabang tidak ditemukan.');
        }

        if ((int) $branch->is_active !== 1) {
            return $this->branchError($request, 'Cabang tidak aktif.');
        }

        $userInstitutionId = (int) ($user->institution_id ?? 0);
        if ($userInstitutionId > 0 && (int) $branch->institution_id !== $userInstitutionId) {
            abort(403, 'Cabang tidak sesuai institusi akun Anda.');
        }

        $request->session()->put('active_branch_id', $branchId);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'active_branch_id' => $branchId,
                'active_branch_name' => (string) $branch->name,
            ]);
        }

        return back()->with('status', 'Cabang aktif berhasil diubah ke: ' . $branch->name);
    }

    /**
     * Reset cabang aktif (hapus session active_branch_id => kembali ke users.branch_id).
     * AJAX/JSON supported.
     */
    public function resetActiveBranch(Request $request)
    {
        $user = $request->user();

        if (($user->role ?? 'member') !== 'super_admin') {
            abort(403, 'Akses ditolak. Hanya super admin yang dapat mereset cabang aktif.');
        }

        $request->session()->forget('active_branch_id');

        $fallbackId = (int) ($user->branch_id ?? 0);
        $fallbackName = null;

        if ($fallbackId > 0) {
            $fallbackName = DB::table('branches')->where('id', $fallbackId)->value('name');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'active_branch_id' => $fallbackId,
                'active_branch_name' => $fallbackName,
                'reset' => true,
            ]);
        }

        return back()->with('status', 'Cabang aktif direset (mengikuti cabang akun).');
    }

    private function branchError(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], 422);
        }

        return back()->withErrors(['branch_id' => $message])->withInput();
    }
}
