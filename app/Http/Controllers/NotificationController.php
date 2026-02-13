<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    /**
     * Halaman daftar notifikasi (staff bisa lihat semua notifikasi member dalam institusi).
     * Member bisa lihat notifikasi miliknya (jika bisa dipetakan ke tabel members).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $filter = (string) $request->query('filter', 'all'); // all|unread

        $scope = $this->resolveScope($user);
        if ($scope['mode'] === 'none') {
            return view('notifikasi.index', [
                'items' => collect(),
                'filter' => $filter,
                'unreadCount' => 0,
                'canMarkRead' => false,
                'scopeMode' => 'none',
                'scopeLabel' => 'Akun ini belum terhubung dengan data member, jadi notifikasi tidak dapat ditampilkan.',
            ]);
        }

        $q = DB::table('member_notifications as n')
            ->leftJoin('members as m', 'm.id', '=', 'n.member_id')
            ->select([
                'n.id',
                'n.institution_id',
                'n.member_id',
                'n.loan_id',
                'n.type',
                'n.plan_key',
                'n.channel',
                'n.status',
                'n.read_at',
                'n.sent_at',
                'n.scheduled_for',
                'n.payload',
                'n.created_at',
                'm.full_name as member_name',
                'm.member_code as member_code',
            ])
            ->orderByDesc('n.created_at');

        if ($scope['mode'] === 'staff') {
            if (!is_null($scope['institution_id'])) {
                $q->where('n.institution_id', '=', (int) $scope['institution_id']);
            }
        } else {
            // member
            $q->where('n.member_id', '=', (int) $scope['member_id']);
        }

        if ($filter === 'unread') {
            $q->whereNull('n.read_at');
        }

        $paginator = $q->paginate(15)->withQueryString();

        $unreadCount = $this->unreadCountInternal($scope);
        $canMarkRead = ($scope['mode'] === 'member');

        // map payload â†’ ringkasan yang rapi
        $paginator->getCollection()->transform(function ($row) {
            $payload = [];
            if (!empty($row->payload)) {
                $decoded = json_decode($row->payload, true);
                if (is_array($decoded)) $payload = $decoded;
            }

            $type = (string) ($row->type ?? '');
            $label = $type === 'overdue' ? 'Terlambat' : 'Jatuh Tempo';
            $emoji = $type === 'overdue' ? 'â°' : '“š';

            $loanCode = (string) ($payload['loan_code'] ?? '-');
            $memberName = (string) ($payload['member_name'] ?? $row->member_name ?? '-');
            $dueDateRaw = (string) ($payload['due_date'] ?? '');
            $duePretty = $dueDateRaw;
            try {
                if (!empty($dueDateRaw)) {
                    $duePretty = Carbon::parse($dueDateRaw)->translatedFormat('d M Y, H:i');
                }
            } catch (\Throwable $e) {
                // ignore
            }

            $message = $type === 'overdue'
                ? "Halo {$memberName}, buku Anda telah melewati tanggal jatuh tempo. Mohon segera dikembalikan. Kode transaksi: {$loanCode}."
                : "Halo {$memberName}, buku Anda akan jatuh tempo besok. Kode transaksi: {$loanCode}.";

            return (object) array_merge((array) $row, [
                'ui_label' => $label,
                'ui_emoji' => $emoji,
                'ui_due_pretty' => $duePretty,
                'ui_loan_code' => $loanCode,
                'ui_member_name' => $memberName,
                'ui_message' => $message,
            ]);
        });

        return view('notifikasi.index', [
            'items' => $paginator,
            'filter' => $filter,
            'unreadCount' => $unreadCount,
            'canMarkRead' => $canMarkRead,
            'scopeMode' => $scope['mode'],
            'scopeLabel' => $scope['mode'] === 'staff' ? 'Notifikasi pengingat (monitoring staff)' : 'Notifikasi Anda',
        ]);
    }

    /**
     * Mark 1 notifikasi sebagai dibaca (khusus akun member).
     */
    public function markRead(Request $request, int $id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Tidak terautentikasi.'], 401);
        }

        $scope = $this->resolveScope($user);
        if ($scope['mode'] !== 'member') {
            return response()->json(['ok' => false, 'message' => 'Tindakan ini hanya untuk akun member.'], 403);
        }

        $updated = DB::table('member_notifications')
            ->where('id', $id)
            ->where('member_id', (int) $scope['member_id'])
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
            'updated' => (int) $updated,
            'unread_count' => $this->unreadCountInternal($scope),
        ]);
    }

    /**
     * Mark semua notifikasi sebagai dibaca (khusus akun member).
     */
    public function markAllRead(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Tidak terautentikasi.'], 401);
        }

        $scope = $this->resolveScope($user);
        if ($scope['mode'] !== 'member') {
            return response()->json(['ok' => false, 'message' => 'Tindakan ini hanya untuk akun member.'], 403);
        }

        $updated = DB::table('member_notifications')
            ->where('member_id', (int) $scope['member_id'])
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
            'updated' => (int) $updated,
            'unread_count' => 0,
        ]);
    }

    /**
     * Hitung notifikasi belum dibaca untuk badge lonceng.
     * - staff/admin: jumlah unread di institusi
     * - member: jumlah unread miliknya
     */
    public function unreadCount(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false, 'count' => 0], 401);
        }

        $scope = $this->resolveScope($user);
        if ($scope['mode'] === 'none') {
            return response()->json(['ok' => true, 'count' => 0]);
        }

        return response()->json([
            'ok' => true,
            'count' => $this->unreadCountInternal($scope),
            'mode' => $scope['mode'],
        ]);
    }

    private function unreadCountInternal(array $scope): int
    {
        $q = DB::table('member_notifications')->whereNull('read_at');
        if ($scope['mode'] === 'staff') {
            if (!is_null($scope['institution_id'])) {
                $q->where('institution_id', '=', (int) $scope['institution_id']);
            }
        } elseif ($scope['mode'] === 'member') {
            $q->where('member_id', '=', (int) $scope['member_id']);
        } else {
            return 0;
        }
        return (int) $q->count();
    }

    /**
     * Menentukan scope pengguna:
     * - staff/admin/super_admin: lihat semua notifikasi member dalam institusi
     * - member: lihat notifikasi miliknya (dipetakan ke tabel members)
     */
    private function resolveScope($user): array
    {
        $role = $user->role ?? 'member';
        $isStaff = in_array($role, ['staff', 'admin', 'super_admin'], true);

        if ($isStaff) {
            return [
                'mode' => 'staff',
                'institution_id' => $user->institution_id ?? null,
            ];
        }

        // Member: coba mapping ke tabel members
        $memberId = null;
        $hasMembersEmail = Schema::hasColumn('members', 'email');
        $hasMembersCode = Schema::hasColumn('members', 'member_code');

        if ($hasMembersEmail && !empty($user->email)) {
            $memberId = DB::table('members')->where('email', '=', $user->email)->value('id');
        }
        if (!$memberId && $hasMembersCode && !empty($user->username)) {
            $memberId = DB::table('members')->where('member_code', '=', $user->username)->value('id');
        }
        if (!$memberId && !empty($user->name)) {
            // fallback paling aman: nama persis (opsional)
            $memberId = DB::table('members')->where('full_name', '=', $user->name)->value('id');
        }

        if (!$memberId) {
            return ['mode' => 'none'];
        }

        return [
            'mode' => 'member',
            'member_id' => (int) $memberId,
        ];
    }
}
