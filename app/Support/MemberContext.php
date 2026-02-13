<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait MemberContext
{
    /**
     * Context standar untuk member area (single source of truth).
     */
    protected function memberContext(Request $request): array
    {
        $user = $request->user();

        $institutionId = (int) ($user->institution_id ?? 0);
        if ($institutionId <= 0) $institutionId = 1;

        $activeBranchId = session('active_branch_id') ?: ($user->branch_id ?? null);
        $activeBranchId = $activeBranchId ? (int) $activeBranchId : null;

        $memberId = $this->resolveMemberId($user);

        return [
            'user' => $user,
            'role' => (string) ($user->role ?? 'member'),
            'institutionId' => $institutionId,
            'activeBranchId' => $activeBranchId,
            'memberId' => $memberId, // bisa null kalau benar-benar tidak ketemu
        ];
    }

    /**
     * Resolve member id yang konsisten untuk semua controller member.
     * Prioritas:
     * 1) user->member_id
     * 2) cocokkan ke table members (email / member_code / full_name) jika ada
     * 3) fallback user->id (terakhir)
     */
    protected function resolveMemberId($user): ?int
    {
        $direct = (int) ($user->member_id ?? 0);
        if ($direct > 0) return $direct;

        // Jika table members ada, coba mapping
        if (Schema::hasTable('members')) {

            $email = (string) ($user->email ?? '');
            if ($email !== '' && Schema::hasColumn('members', 'email')) {
                $id = (int) DB::table('members')->where('email', $email)->value('id');
                if ($id > 0) return $id;
            }

            $username = (string) ($user->username ?? '');
            if ($username !== '' && Schema::hasColumn('members', 'member_code')) {
                $id = (int) DB::table('members')->where('member_code', $username)->value('id');
                if ($id > 0) return $id;
            }

            $name = (string) ($user->name ?? '');
            if ($name !== '' && Schema::hasColumn('members', 'full_name')) {
                $id = (int) DB::table('members')->where('full_name', $name)->value('id');
                if ($id > 0) return $id;
            }
        }

        // fallback terakhir
        $fallback = (int) ($user->id ?? 0);
        return $fallback > 0 ? $fallback : null;
    }
}
