<?php

namespace App\Http\Controllers;

use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MemberReservationController extends Controller
{
    public function __construct(private ReservationService $reservationService) {}

    public function index(Request $request)
    {
        return redirect()->route('reservasi.index', $request->query());
    }

    public function cancel(Request $request, int $id)
    {
        $ctx = $this->memberContext();
        if (!$ctx['ok']) {
            return back()->with('error', $ctx['message']);
        }

        $row = DB::table('reservations')
            ->where('institution_id', $ctx['institution_id'])
            ->where('id', $id)
            ->where('member_id', $ctx['member_id'])
            ->first(['id']);

        if (!$row) {
            return back()->with('error', 'Reservasi tidak ditemukan.');
        }

        $res = $this->reservationService->cancelReservation($ctx['institution_id'], $id, (int) Auth::id());
        if (!($res['ok'] ?? false)) {
            return back()->with('error', $res['message'] ?? 'Gagal membatalkan reservasi.');
        }

        return back()->with('success', 'Reservasi dibatalkan.');
    }

    public function requeue(Request $request, int $id)
    {
        $ctx = $this->memberContext();
        if (!$ctx['ok']) {
            return back()->with('error', $ctx['message']);
        }

        $res = $this->reservationService->requeueReservation(
            $ctx['institution_id'],
            $id,
            $ctx['member_id'],
            (int) Auth::id()
        );

        if (!($res['ok'] ?? false)) {
            return back()->with('error', $res['message'] ?? 'Gagal antre ulang reservasi.');
        }

        return back()->with('success', $res['message'] ?? 'Reservasi diantre ulang.');
    }

    public function status(Request $request)
    {
        $ctx = $this->memberContext();
        if (!$ctx['ok']) {
            return response()->json(['ok' => false, 'message' => $ctx['message']], 422);
        }

        $rows = DB::table('reservations')
            ->where('institution_id', $ctx['institution_id'])
            ->where('member_id', $ctx['member_id'])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'status', 'queue_no', 'ready_at', 'expires_at', 'updated_at']);

        $counts = [
            'queued' => 0,
            'ready' => 0,
            'fulfilled' => 0,
            'cancelled' => 0,
            'expired' => 0,
        ];

        foreach ($rows as $row) {
            $status = strtolower((string) ($row->status ?? ''));
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        return response()->json([
            'ok' => true,
            'counts' => $counts,
            'items' => $rows,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function memberContext(): array
    {
        $user = Auth::user();
        if (!$user) {
            return ['ok' => false, 'message' => 'Tidak terautentikasi.'];
        }

        $institutionId = max(1, (int) ($user->active_institution_id ?? $user->active_inst_id ?? $user->institution_id ?? 1));
        $memberId = (int) DB::table('members')->where('user_id', (int) $user->id)->value('id');
        if ($memberId <= 0) {
            return ['ok' => false, 'message' => 'Akun belum terhubung ke data anggota.'];
        }

        return [
            'ok' => true,
            'institution_id' => $institutionId,
            'member_id' => $memberId,
        ];
    }
}
