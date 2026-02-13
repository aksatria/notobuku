@extends('layouts.notobuku')

@section('title', 'UAT Produksi ILS Core - NOTOBUKU')

@section('content')
<div class="mx-auto max-w-5xl px-4 pb-16 pt-10">
  <div class="rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-6">
    <div class="text-xs font-semibold text-[var(--nb-muted)]">NOTOBUKU - UAT</div>
    <h1 class="mt-2 text-2xl font-extrabold">Checklist UAT Produksi ILS Core</h1>
    <p class="mt-2 text-sm text-[var(--nb-muted)]">
      Validasi standar sebelum rilis untuk menjaga skor modul inti tetap 10/10 secara operasional.
    </p>
  </div>

  <div class="mt-6 grid gap-4">
    <section class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-5">
      <h2 class="text-lg font-extrabold">1. Prasyarat</h2>
      <ul class="mt-3 space-y-2 text-sm">
        <li>- Snapshot backup database dan storage sudah dibuat.</li>
        <li>- Environment produksi aktif: <code>APP_ENV=production</code>, <code>APP_DEBUG=false</code>.</li>
        <li>- Migrasi dijalankan aman: <code>php artisan migrate --force</code> (tanpa fresh).</li>
      </ul>
    </section>

    <section class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-5">
      <h2 class="text-lg font-extrabold">2. Modul Inti</h2>
      <ul class="mt-3 space-y-2 text-sm">
        <li>- Katalog: tambah/edit bibliografi + eksemplar, pencarian dan detail berjalan.</li>
        <li>- Sirkulasi: pinjam, kembali, perpanjang, dan denda tervalidasi.</li>
        <li>- Anggota: import CSV preview/confirm/undo + export history CSV/XLSX.</li>
        <li>- Laporan: filter tanggal/cabang + export semua tipe (CSV/XLSX).</li>
        <li>- Serial: alur expected/claimed/received/missing + export CSV/XLSX.</li>
      </ul>
    </section>

    <section class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-5">
      <h2 class="text-lg font-extrabold">3. Otorisasi dan Stabilitas</h2>
      <ul class="mt-3 space-y-2 text-sm">
        <li>- Matrix role sesuai: staff/admin boleh endpoint operasional, member dibatasi.</li>
        <li>- Tidak ada 500 error di <code>storage/logs/laravel.log</code> selama skenario UAT.</li>
        <li>- Scheduler dan snapshot rutin berjalan sesuai jadwal.</li>
      </ul>
    </section>

    <section class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-5">
      <h2 class="text-lg font-extrabold">4. Go-Live Gate</h2>
      <ul class="mt-3 space-y-2 text-sm">
        <li>- Semua checklist lulus.</li>
        <li>- Tidak ada blocker severity tinggi.</li>
        <li>- Approval tim operasional dan admin sudah diberikan.</li>
      </ul>
      <div class="mt-4">
        <a class="rounded-xl border border-[var(--nb-border)] px-3 py-2 text-sm font-semibold" href="{{ route('docs.index') }}">Kembali ke Documentation Center</a>
      </div>
    </section>
  </div>
</div>
@endsection
