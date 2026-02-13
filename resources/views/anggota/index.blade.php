@extends('layouts.notobuku')

@section('title','Data Anggota • NOTOBUKU')

@section('content')
  <div class="rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-card)] shadow-sm overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-[var(--nb-border)]"
         style="background: linear-gradient(135deg, rgba(74,144,226,.14), rgba(31,58,95,.08));">
      <div class="text-xs font-semibold text-[var(--nb-muted)]">KEANGGOTAAN</div>
      <h1 class="mt-1 text-2xl font-extrabold tracking-tight" style="color:var(--nb-navy)">Data Anggota</h1>
      <p class="mt-2 text-sm text-[var(--nb-muted)]">Placeholder halaman anggota. Step 4B akan mengisi listing, detail, dan status aktif.</p>
    </div>
    <div class="p-5 sm:p-6">
      <x-badge tone="info">Belum terhubung database</x-badge>
      <div class="mt-4 flex gap-2">
        <x-button tone="primary" href="{{ route('staff.beranda') }}">Dashboard Staff</x-button>
        <x-button tone="outline" href="{{ route('katalog.index') }}">Ke Katalog</x-button>
      </div>
    </div>
  </div>
@endsection
