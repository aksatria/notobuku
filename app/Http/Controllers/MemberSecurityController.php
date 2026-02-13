<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
}
