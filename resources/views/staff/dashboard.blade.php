{{-- resources/views/staff/dashboard.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Beranda Staff • NOTOBUKU')

@section('content')
<div class="rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-card)] shadow-sm overflow-hidden">
  <div style="height:6px;background:var(--nb-grad)"></div>
  <div class="p-6">
    <div class="flex items-center gap-3">
      <span class="inline-flex items-center justify-center h-12 w-12 rounded-2xl"
            style="background: var(--nb-soft-green); color: var(--nb-green);">
        @include('partials.icons',['name'=>'repeat'])
      </span>
      <div>
        <h1 class="text-xl font-extrabold m-0">Beranda Staff</h1>
        <div class="text-sm text-[var(--nb-muted)]">
          Anda masuk sebagai <b>{{ auth()->user()->role }}</b> — {{ auth()->user()->email }}
        </div>
      </div>
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
      <a class="nb-btn-primary" href="{{ route('app') }}">Buka Portal Saya</a>
      <a class="rounded-2xl px-4 py-2 text-sm font-extrabold border border-[var(--nb-border)] bg-[var(--nb-card)] hover:shadow-sm"
         href="{{ route('transaksi.index') }}">Transaksi</a>

      <button type="button" class="rounded-2xl px-4 py-2 text-sm font-extrabold border border-[var(--nb-border)] bg-[var(--nb-card)] hover:shadow-sm"
              @click="openSearch()">
        Ctrl+K Cari Cepat
      </button>

      <form method="POST" action="{{ route('keluar') }}" class="m-0">
        @csrf
        <button class="rounded-2xl px-4 py-2 text-sm font-extrabold border border-[var(--nb-border)] bg-[var(--nb-card)] hover:shadow-sm"
                type="submit">Keluar</button>
      </form>
    </div>

    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-card)] p-5">
        <div class="text-xs font-extrabold text-[var(--nb-muted)]">Fokus</div>
        <div class="mt-2 font-extrabold">Sirkulasi Cepat</div>
        <div class="text-sm text-[var(--nb-muted)] mt-1">Scan anggota + barcode buku</div>
      </div>

      <div class="rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-card)] p-5">
        <div class="text-xs font-extrabold text-[var(--nb-muted)]">Fokus</div>
        <div class="mt-2 font-extrabold">Cari Anggota</div>
        <div class="text-sm text-[var(--nb-muted)] mt-1">Temukan anggota dengan cepat</div>
      </div>

      <div class="rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-card)] p-5">
        <div class="text-xs font-extrabold text-[var(--nb-muted)]">Fokus</div>
        <div class="mt-2 font-extrabold">Kelola Eksemplar</div>
        <div class="text-sm text-[var(--nb-muted)] mt-1">CRUD eksemplar + barcode</div>
      </div>
    </div>
  </div>
</div>
@endsection
