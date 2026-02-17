<?php

namespace App\Http\Controllers;

use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReservasiController extends Controller
{
    private function currentInstitutionId(): int
    {
        $user = Auth::user();

        $inst = (int)($user->active_institution_id
            ?? $user->active_inst_id
            ?? $user->institution_id
            ?? 1);

        return $inst > 0 ? $inst : 1;
    }

    /**
     * Resolve member_id (FK ke members.id) dari user login.
     * Jika belum ada record member, bisa dibuat.
     */
    private function resolveMemberIdForUser(int $institutionId, bool $createIfMissing = true): int
    {
        $user = Auth::user();
        if (!$user) return 0;

        $memberId = (int) DB::table('members')->where('user_id', $user->id)->value('id');
        if ($memberId > 0) return $memberId;

        if (!$createIfMissing) return 0;

        $next = (int) DB::table('members')->max('id');
        $next = $next > 0 ? $next + 1 : 1;
        $memberCode = 'MBR-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

        $fullName = (string)($user->name ?? $user->full_name ?? 'Member');
        if (trim($fullName) === '' && !empty($user->email)) {
            $fullName = (string) $user->email;
        }

        $now = now();

        $memberId = (int) DB::table('members')->insertGetId([
            'institution_id' => $institutionId,
            'user_id'        => $user->id,
            'member_code'    => $memberCode,
            'full_name'      => $fullName,
            'status'         => 'active',
            'joined_at'      => $now,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        // Optional: buat profile kalau tabelnya ada
        if ($memberId > 0 && Schema::hasTable('member_profiles')) {
            $hasProfile = (bool) DB::table('member_profiles')->where('member_id', $memberId)->exists();
            if (!$hasProfile) {
                DB::table('member_profiles')->insert([
                    'member_id'   => $memberId,
                    'phone'       => null,
                    'address'     => null,
                    'bio'         => null,
                    'avatar_path' => null,
                    'is_public'   => false,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }

        return $memberId;
    }

    /**
     * HALAMAN /reservasi
     * View kamu butuh: mode, scopeLabel, items, filter, q, canCreate, canManage, memberLinked
     */
    public function index(Request $request, ReservationService $svc)
    {
        $institutionId = $this->currentInstitutionId();
        $user = Auth::user();

        $role = (string) ($user->role ?? 'member');
        $isStaff = in_array($role, ['staff', 'admin'], true);

        $mode = $isStaff ? 'staff' : 'member';
        $scopeLabel = $isStaff ? 'Semua Reservasi (monitoring)' : 'Reservasi Anda';

        $filter = (string) $request->query('filter', 'all'); // all|queued|ready|done
        $q = (string) $request->query('q', '');

        $memberId = null;
        $memberLinked = true;

        if (!$isStaff) {
            $mid = $this->resolveMemberIdForUser($institutionId, false);
            if ($mid <= 0) {
                $memberLinked = false;
                $memberId = 0; // biar service tahu "unlinked"
            } else {
                $memberId = $mid;
            }
        }

        $result = $svc->listReservations(
            $institutionId,
            $isStaff ? null : $memberId,
            $filter,
            $q,
            $mode
        );

        // Sesuaikan dengan Blade
        $canCreate = true;     // kalau mau dimatikan: false
        $canManage = $isStaff; // staff/admin bisa batalkan + fulfill

        return view('reservasi.index', [
            'mode'         => $mode,
            'scopeLabel'   => $scopeLabel,
            'items'        => $result['items'] ?? collect(),
            'filter'       => $filter,
            'q'            => $q,
            'canCreate'    => $canCreate,
            'canManage'    => $canManage,
            'memberLinked' => $result['memberLinked'] ?? $memberLinked,
        ]);
    }

    /**
     * CREATE RESERVASI - BARCODE ONLY âœ…
     */
    public function store(Request $request, ReservationService $svc)
    {
        $user = Auth::user();
        $role = (string) ($user->role ?? 'member');
        $isStaff = in_array($role, ['staff', 'admin'], true);

        $institutionId = (int) ($request->input('institution_id') ?? 0);
        if ($institutionId <= 0) $institutionId = $this->currentInstitutionId();

        $validated = $request->validate([
            'barcode'        => ['nullable', 'string', 'min:1', 'max:80'],
            'biblio_id'      => ['nullable', 'string', 'min:1', 'max:80'],
            'institution_id' => ['nullable', 'integer', 'min:1'],
            'member_id'      => ['nullable', 'integer', 'min:1'],
            'notes'          => ['nullable', 'string', 'max:255'],
        ]);

        $barcode = trim((string)($validated['barcode'] ?? ''));
        if ($barcode === '') {
            $barcode = trim((string)($validated['biblio_id'] ?? ''));
        }
        $notes = $validated['notes'] ?? null;

        if ($barcode === '') {
            return back()->with('error', 'Barcode wajib diisi.');
        }

        if ($isStaff) {
            $memberId = (int) ($validated['member_id'] ?? 0);
            if ($memberId <= 0) return back()->with('error', 'member_id wajib untuk staff/admin.');
            $actorUserId = (int) $user->id;
        } else {
            $memberId = $this->resolveMemberIdForUser($institutionId, true);
            if ($memberId <= 0) return back()->with('error', 'Akun member belum siap. Hubungi petugas.');
            $actorUserId = null;
        }

        try {
            $res = $svc->createReservationByBarcode($institutionId, $memberId, $barcode, $notes, $actorUserId);

            if (!($res['ok'] ?? false)) {
                return back()->with('error', $res['message'] ?? 'Gagal membuat reservasi.');
            }

            return back()->with('success', $res['message'] ?? 'Reservasi berhasil dibuat.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, ReservationService $svc, int $id)
    {
        $institutionId = $this->currentInstitutionId();
        $userId = (int) Auth::id();

        try {
            $res = $svc->cancelReservation($institutionId, $id, $userId);
            if (!($res['ok'] ?? false)) {
                return back()->with('error', $res['message'] ?? 'Gagal membatalkan.');
            }
            return back()->with('success', 'Reservasi dibatalkan.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function fulfill(Request $request, ReservationService $svc, int $id)
    {
        $institutionId = $this->currentInstitutionId();
        $userId = (int) Auth::id();

        try {
            $res = $svc->fulfillReservation($institutionId, $id, $userId);
            if (!($res['ok'] ?? false)) {
                return back()->with('error', $res['message'] ?? 'Gagal memenuhi.');
            }
            return back()->with('success', 'Reservasi dipenuhi.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
