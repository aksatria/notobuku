@extends('layouts.notobuku')

@section('title','Komunitas • NOTOBUKU')

@section('content')
  <div class="rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-card)] shadow-sm overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-[var(--nb-border)]"
         style="background: linear-gradient(135deg, rgba(74,144,226,.16), rgba(39,174,96,.12));">
      <div class="text-xs font-semibold text-[var(--nb-muted)]">KOMUNITAS</div>
      <h1 class="mt-1 text-2xl font-extrabold tracking-tight" style="color:var(--nb-navy)">Beranda Komunitas</h1>
      <p class="mt-2 text-sm text-[var(--nb-muted)]">Placeholder feed komunitas. Step 4C akan mengaktifkan posting, komentar, like, follow.</p>
    </div>
    <div class="p-5 sm:p-6">
      <x-badge tone="success">Siap diaktifkan</x-badge>
      <div class="mt-4 flex gap-2">
        <x-button tone="primary" href="{{ route('komunitas.buat') }}">Buat Postingan</x-button>
        <x-button tone="ghost" x-on:click="openSearch()">Cari Menu</x-button>
      </div>
    </div>
  </div>
@endsection
