<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MemberProfileController extends Controller
{
    /**
     * Show profile form (member).
     */
    public function edit(Request $request)
    {
        $user = Auth::user();

        $member = DB::table('members')
            ->where('user_id', $user->id)
            ->first();

        $profile = null;
        if ($member) {
            $profile = DB::table('member_profiles')
                ->where('member_id', $member->id)
                ->first();
        }

        return view('member.profil', [
            'user' => $user,
            'member' => $member,
            'profile' => $profile,
        ]);
    }

    /**
     * Update profile (member).
     *
     * Catatan:
     * - Tidak menghapus data lama.
     * - Jika row member/profile belum ada, akan dibuat.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required','string','max:120'],
            'email' => ['required','email','max:190', Rule::unique('users','email')->ignore($user->id)],
            'username' => ['nullable','string','max:50', 'regex:/^[a-zA-Z0-9_\.]+$/', Rule::unique('users','username')->ignore($user->id)],

            // data member
            'phone' => ['nullable','string','max:40'],
            'address' => ['nullable','string','max:255'],

            // profile tambahan
            'bio' => ['nullable','string','max:500'],
            'is_public' => ['nullable'],

            // avatar opsional
            'avatar' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
        ], [
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, dan underscore.',
            'email.unique' => 'Email sudah digunakan. Silakan gunakan email lain.',
            'username.unique' => 'Username sudah digunakan. Silakan pilih username lain.',
            'phone.unique' => 'Nomor telepon sudah digunakan. Silakan gunakan nomor lain.',
        ]);

        DB::beginTransaction();
        try {
            // 1) Update USERS (aman)
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->username = $validated['username'] ?? $user->username;
            $user->save();

            // 2) Upsert MEMBERS
            $member = DB::table('members')->where('user_id', $user->id)->first();

            $memberData = [
                'full_name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'updated_at' => now(),
            ];

            if (!$member) {
                // buat member baru TANPA merusak data lama (karena belum ada)
                $code = 'MBR-' . now()->format('ymd') . '-' . str_pad((string)$user->id, 5, '0', STR_PAD_LEFT);

                $memberId = DB::table('members')->insertGetId([
                    'user_id' => $user->id,
                    'member_code' => $code,
                    'full_name' => $validated['name'],
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'status' => 'active',
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $member = DB::table('members')->where('id', $memberId)->first();
            } else {
                DB::table('members')->where('id', $member->id)->update($memberData);
                $member = DB::table('members')->where('id', $member->id)->first();
            }

            // 3) Upsert MEMBER_PROFILES
            $profile = DB::table('member_profiles')->where('member_id', $member->id)->first();

            $isPublic = (bool)($request->input('is_public', 0));
            $profileData = [
                'bio' => $validated['bio'] ?? null,
                'is_public' => $isPublic,
                'updated_at' => now(),
            ];

            // avatar opsional
            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('avatars', 'public');
                $profileData['avatar_path'] = $path;
            }

            if (!$profile) {
                DB::table('member_profiles')->insert(array_merge($profileData, [
                    'member_id' => $member->id,
                    'created_at' => now(),
                ]));
            } else {
                // jangan hapus avatar lama kalau tidak upload
                if (!$request->hasFile('avatar')) {
                    unset($profileData['avatar_path']);
                }
                DB::table('member_profiles')->where('id', $profile->id)->update($profileData);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return redirect()
            ->route('member.profil')
            ->with('success', 'Profil berhasil diperbarui.');
    }
}
