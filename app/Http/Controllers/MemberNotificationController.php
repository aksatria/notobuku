<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MemberNotificationController extends Controller
{
    /**
     * Daftar notifikasi untuk MEMBER saja.
     * Mengambil data dari tabel member_notifications.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        $filter = (string) $request->query('filter', 'all'); // all|unread

        $memberId = $this->resolveMemberId($user);
        if (!$memberId) {
            return view('member.notifikasi', [
                'items' => collect(),
                'filter' => $filter,
                'unreadCount' => 0,
                'scopeLabel' => 'Akun ini belum terhubung dengan data member, jadi notifikasi tidak dapat ditampilkan.',
            ]);
        }

        if (!Schema::hasTable('member_notifications')) {
            return view('member.notifikasi', [
                'items' => collect(),
                'filter' => $filter,
                'unreadCount' => 0,
                'scopeLabel' => 'Tabel notifikasi tidak ditemukan (member_notifications).',
            ]);
        }

        $q = DB::table('member_notifications as n')
            ->select([
                'n.id',
                'n.type',
                'n.plan_key',
                'n.channel',
                'n.status',
                'n.read_at',
                'n.sent_at',
                'n.scheduled_for',
                'n.payload',
                'n.created_at',
                'n.loan_id',
            ])
            ->where('n.member_id', '=', (int) $memberId)
            ->orderByDesc('n.created_at');

        if ($filter === 'unread') {
            $q->whereNull('n.read_at');
        }

        $paginator = $q->paginate(15)->withQueryString();

        $unreadCount = (int) DB::table('member_notifications')
            ->where('member_id', '=', (int) $memberId)
            ->whereNull('read_at')
            ->count();

        // ringkas payload agar enak ditampilkan
        $items = $paginator->getCollection()->map(function ($row) {
            $payload = [];
            try {
                $payload = is_string($row->payload) ? (json_decode($row->payload, true) ?: []) : (array) $row->payload;
            } catch (\Throwable $e) {
                $payload = [];
            }

            $title = $payload['title'] ?? $payload['subject'] ?? $payload['message'] ?? 'Notifikasi';
            $body  = $payload['body'] ?? $payload['text'] ?? $payload['message'] ?? '';

            return (object) [
                'id' => (int) $row->id,
                'title' => (string) $title,
                'body' => (string) $body,
                'type' => (string) ($row->type ?? ''),
                'status' => (string) ($row->status ?? ''),
                'read_at' => $row->read_at,
                'created_at' => $row->created_at,
                'loan_id' => $row->loan_id,
            ];
        });

        // pakai view member/notifikasi.blade.php (baru)
        return view('member.notifikasi', [
            'items' => $items,
            'paginator' => $paginator,
            'filter' => $filter,
            'unreadCount' => $unreadCount,
            'scopeLabel' => 'Notifikasi Anda',
        ]);
    }

    public function markRead(Request $request, int $id)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        $memberId = $this->resolveMemberId($user);
        if (!$memberId) return back()->with('error', 'Akun belum terhubung ke data member.');

        if (!Schema::hasTable('member_notifications')) {
            return back()->with('error', 'Tabel notifikasi tidak ditemukan.');
        }

        DB::table('member_notifications')
            ->where('id', '=', $id)
            ->where('member_id', '=', (int) $memberId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function markAllRead(Request $request)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        $memberId = $this->resolveMemberId($user);
        if (!$memberId) return back()->with('error', 'Akun belum terhubung ke data member.');

        if (!Schema::hasTable('member_notifications')) {
            return back()->with('error', 'Tabel notifikasi tidak ditemukan.');
        }

        DB::table('member_notifications')
            ->where('member_id', '=', (int) $memberId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    private function resolveMemberId($user): ?int
    {
        if (!Schema::hasTable('members')) return null;

        $memberId = null;
        $hasMembersEmail = Schema::hasColumn('members', 'email');
        $hasMembersCode  = Schema::hasColumn('members', 'member_code');

        if ($hasMembersEmail && !empty($user->email)) {
            $memberId = DB::table('members')->where('email', '=', $user->email)->value('id');
        }
        if (!$memberId && $hasMembersCode && !empty($user->username)) {
            $memberId = DB::table('members')->where('member_code', '=', $user->username)->value('id');
        }
        if (!$memberId && !empty($user->name)) {
            $memberId = DB::table('members')->where('full_name', '=', $user->name)->value('id');
        }

        return $memberId ? (int) $memberId : null;
    }
}
