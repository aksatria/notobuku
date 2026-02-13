@extends('layouts.notobuku')

@section('title','Buat Postingan • NOTOBUKU')

@section('content')
  <div class="rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-card)] shadow-sm overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-[var(--nb-border)]"
         style="background: linear-gradient(135deg, rgba(39,174,96,.16), rgba(74,144,226,.10));">
      <div class="text-xs font-semibold text-[var(--nb-muted)]">KOMUNITAS</div>
      <h1 class="mt-1 text-2xl font-extrabold tracking-tight" style="color:var(--nb-navy)">Buat Postingan</h1>
      <p class="mt-2 text-sm text-[var(--nb-muted)]">Placeholder halaman buat posting. Step 4C akan menambah upload gambar & teks.</p>
    </div>
    <div class="p-5 sm:p-6">
      <div class="rounded-2xl border border-[var(--nb-border)] p-4">
        <div class="text-sm font-semibold">Form placeholder</div>
        <div class="text-xs text-[var(--nb-muted)] mt-1">Nanti ada input teks + upload gambar + privasi.</div>

        <div class="mt-4 flex gap-2">
          <x-button tone="primary" href="{{ route('komunitas.feed') }}">Kembali ke Feed</x-button>
          <x-button tone="outline" x-on:click="openSearch()">Buka Pencarian</x-button>
        </div>
      </div>
    </div>
  </div>
@endsection
