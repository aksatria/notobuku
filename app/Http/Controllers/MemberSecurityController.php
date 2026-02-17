<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class MemberSecurityController extends Controller
{
    public function edit()
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        return view('member.keamanan');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        $request->validate([
            'current_password' => ['required'],
            'password' => ['required','min:8','confirmed'],
        ]);

        if (!Hash::check((string)$request->current_password, (string)$user->password)) {
            return back()->withErrors(['current_password' => 'Password lama tidak sesuai.'])->withInput();
        }

        $user->update([
            'password' => Hash::make((string)$request->password),
        ]);

        return back()->with('success', 'Password berhasil diperbarui.');
    }

    public function freezeAccount(Request $request)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        DB::table('members')
            ->where('user_id', (int) $user->id)
            ->update(['status' => 'suspended', 'updated_at' => now()]);

        return back()->with('success', 'Akun anggota dibekukan sementara.');
    }

    public function unfreezeAccount(Request $request)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        DB::table('members')
            ->where('user_id', (int) $user->id)
            ->update(['status' => 'active', 'updated_at' => now()]);

        return back()->with('success', 'Akun anggota diaktifkan kembali.');
    }

    public function sendResetCredentialLink(Request $request)
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        $status = Password::sendResetLink(['email' => (string) $user->email]);
        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('error', 'Tautan reset kredensial gagal dikirim.');
        }

        return back()->with('success', 'Tautan reset kredensial berhasil dikirim ke email Anda.');
    }
}
