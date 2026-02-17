{{-- resources/views/member/keamanan.blade.php --}}
@extends('layouts.member')

@section('title', 'Keamanan • NOTOBUKU')
@section('member_title','Keamanan')
@section('member_subtitle','Ubah password akun Anda')

@section('member.content')
<div class="max-w-2xl">
  <div class="rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-6">
    <form method="POST" action="{{ route('member.security.password') }}">
      @csrf

      <div class="grid grid-cols-1 gap-4">
        <div>
          <label class="block text-xs font-black text-[rgba(0,0,0,.60)] mb-2">Password Lama</label>
          <input name="current_password" type="password"
                 class="w-full px-4 py-3 rounded-2xl border border-[var(--nb-border)] bg-white/70 focus:outline-none focus:ring-2 focus:ring-[rgba(30,136,229,.18)]" />
          @error('current_password')
            <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
          @enderror
        </div>

        <div>
          <label class="block text-xs font-black text-[rgba(0,0,0,.60)] mb-2">Password Baru</label>
          <input name="password" type="password"
                 class="w-full px-4 py-3 rounded-2xl border border-[var(--nb-border)] bg-white/70 focus:outline-none focus:ring-2 focus:ring-[rgba(30,136,229,.18)]" />
          @error('password')
            <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
          @enderror
        </div>

        <div>
          <label class="block text-xs font-black text-[rgba(0,0,0,.60)] mb-2">Konfirmasi Password Baru</label>
          <input name="password_confirmation" type="password"
                 class="w-full px-4 py-3 rounded-2xl border border-[var(--nb-border)] bg-white/70 focus:outline-none focus:ring-2 focus:ring-[rgba(30,136,229,.18)]" />
        </div>

        <div class="pt-2">
          <button type="submit"
                  class="px-5 py-3 rounded-2xl bg-[var(--nb-blue)] text-white font-bold hover:opacity-95">
            Update Password
          </button>
        </div>
      </div>
    </form>
  </div>

  <div class="rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-6 mt-4">
    <h3 class="text-base font-bold text-[var(--nb-ink)] mb-2">Kontrol Akun</h3>
    <p class="text-sm text-[rgba(0,0,0,.6)] mb-4">Gunakan bila akun perlu dibekukan sementara atau butuh reset kredensial via email.</p>

    <div class="flex flex-wrap gap-2">
      <form method="POST" action="{{ route('member.security.freeze') }}">
        @csrf
        <button type="submit"
                class="px-4 py-2 rounded-xl border border-amber-300 bg-amber-50 text-amber-800 font-semibold">
          Bekukan Akun
        </button>
      </form>

      <form method="POST" action="{{ route('member.security.unfreeze') }}">
        @csrf
        <button type="submit"
                class="px-4 py-2 rounded-xl border border-emerald-300 bg-emerald-50 text-emerald-800 font-semibold">
          Aktifkan Kembali
        </button>
      </form>

      <form method="POST" action="{{ route('member.security.reset_credential') }}">
        @csrf
        <button type="submit"
                class="px-4 py-2 rounded-xl border border-sky-300 bg-sky-50 text-sky-800 font-semibold">
          Kirim Link Reset Kredensial
        </button>
      </form>
    </div>
  </div>
</div>
@endsection
