@extends('layouts.notobuku')

@section('title', 'Stock Opname - NOTOBUKU')

@section('content')
<style>
  .page{ max-width:1180px; margin:0 auto; display:flex; flex-direction:column; gap:14px; }
  .card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; }
  .title{ margin:0; font-size:20px; font-weight:700; }
  .muted{ color:var(--nb-muted); font-size:13px; }
  .alert{ border-radius:12px; border:1px solid var(--nb-border); padding:10px 12px; font-size:13px; }
  .alert.ok{ border-color:rgba(16,185,129,.35); color:#065f46; background:rgba(16,185,129,.08); }
  .alert.err{ border-color:rgba(239,68,68,.35); color:#991b1b; background:rgba(239,68,68,.08); }
  .grid{ display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; }
  .field label{ display:block; font-size:12px; color:var(--nb-muted); font-weight:600; margin-bottom:6px; }
  .nb-field{ width:100%; border:1px solid var(--nb-border); border-radius:12px; padding:10px 12px; background:var(--nb-surface); }
  .btn{ display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--nb-border); border-radius:12px; padding:10px 12px; font-weight:600; }
  .btn-primary{ background:linear-gradient(90deg,#1e88e5,#1565c0); color:#fff; border-color:transparent; }
  .btn-link{ color:#1e88e5; font-weight:700; }
  .badge{ display:inline-flex; align-items:center; border-radius:999px; padding:3px 8px; font-size:11px; font-weight:700; border:1px solid var(--nb-border); }
  .badge.draft{ background:rgba(148,163,184,.10); color:#475569; }
  .badge.in_progress{ background:rgba(59,130,246,.10); color:#1d4ed8; border-color:rgba(59,130,246,.35); }
  .badge.completed{ background:rgba(16,185,129,.10); color:#047857; border-color:rgba(16,185,129,.35); }
  .kpi{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:10px; }
  .kpi-item{ border:1px solid var(--nb-border); border-radius:12px; padding:10px; }
  .kpi-item.var-netral{ background:rgba(148,163,184,.10); }
  .kpi-item.var-proses{ background:rgba(59,130,246,.10); }
  .kpi-item.var-selesai{ background:rgba(16,185,129,.10); }
  .kpi-item.var-risiko{ background:rgba(239,68,68,.10); }
  .kpi-item .label{ font-size:11px; color:var(--nb-muted); }
  .kpi-item .value{ margin-top:6px; font-size:20px; font-weight:700; }
  table{ width:100%; border-collapse:collapse; table-layout:auto; }
  th, td{ border-bottom:1px solid var(--nb-border); padding:9px 8px; font-size:13px; text-align:left; vertical-align:top; }
  th{ color:var(--nb-muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
  .col-session{ width:32%; }
  .col-status{ width:12%; }
  .col-scope{ width:24%; }
  .col-mini{ width:7%; text-align:right; }
  .col-action{ width:8%; text-align:right; }
  .cell-right{ text-align:right; }
  .status-cell{ white-space:nowrap; }
  .badge{ white-space:nowrap; min-width:84px; justify-content:center; }
  .scope-cell{ word-break:normal; }
  @media (max-width:1100px){
    .grid{ grid-template-columns:1fr; }
    .kpi{ grid-template-columns:repeat(2,minmax(0,1fr)); }
    table{ table-layout:auto; }
  }
</style>

<div class="page">
  <div class="card">
    <h1 class="title">Stock Opname</h1>
    <div class="muted">Buat sesi, pindai barcode, lalu selesaikan sesi untuk mendapatkan daftar item ditemukan, hilang, dan tak terduga.</div>
  </div>

  @if(session('success'))
    <div class="alert ok">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert err">{{ session('error') }}</div>
  @endif
  @if($errors->any())
    <div class="alert err">{{ $errors->first() }}</div>
  @endif

  <div class="card">
    <h2 class="title" style="font-size:16px;">Buat Sesi Baru</h2>
    <form method="POST" action="{{ route('stock_takes.store') }}" class="grid" style="margin-top:12px;">
      @csrf
      <div class="field" style="grid-column:span 2;">
        <label>Nama Sesi</label>
        <input name="name" required placeholder="Mis: Opname Semester 1 Rak Referensi" class="nb-field" value="{{ old('name') }}" />
      </div>
      <div class="field">
        <label>Cabang</label>
        <select name="branch_id" class="nb-field">
          <option value="">Semua cabang</option>
          @foreach($branches as $branch)
            <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>Rak</label>
        <select name="shelf_id" class="nb-field">
          <option value="">Semua rak</option>
          @foreach($shelves as $shelf)
            <option value="{{ $shelf->id }}" @selected((string) old('shelf_id') === (string) $shelf->id)>{{ $shelf->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>Filter Status Item</label>
        <select name="scope_status" class="nb-field">
          <option value="all" @selected(old('scope_status') === 'all')>Semua status item</option>
          <option value="available" @selected(old('scope_status') === 'available')>Hanya tersedia</option>
          <option value="borrowed" @selected(old('scope_status') === 'borrowed')>Hanya dipinjam</option>
          <option value="lost" @selected(old('scope_status') === 'lost')>Hanya hilang</option>
          <option value="damaged" @selected(old('scope_status') === 'damaged')>Hanya rusak</option>
          <option value="maintenance" @selected(old('scope_status') === 'maintenance')>Hanya perawatan</option>
        </select>
      </div>
      <div class="field" style="grid-column:span 4;">
        <label>Catatan</label>
        <textarea name="notes" rows="2" placeholder="Catatan sesi (opsional)" class="nb-field">{{ old('notes') }}</textarea>
      </div>
      <div style="display:flex; align-items:flex-end;">
        <button type="submit" class="btn btn-primary" style="width:100%;">Buat Sesi</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
      <h2 class="title" style="font-size:16px;">Riwayat Sesi Opname</h2>
      <div class="muted">{{ method_exists($stockTakes, 'total') ? number_format((int)$stockTakes->total()) : number_format((int)$stockTakes->count()) }} sesi</div>
    </div>

    <div class="kpi">
      <div class="kpi-item var-netral"><div class="label">DRAF</div><div class="value">{{ number_format((int) $stockTakes->where('status', 'draft')->count()) }}</div></div>
      <div class="kpi-item var-proses"><div class="label">SEDANG BERJALAN</div><div class="value">{{ number_format((int) $stockTakes->where('status', 'in_progress')->count()) }}</div></div>
      <div class="kpi-item var-selesai"><div class="label">SELESAI</div><div class="value">{{ number_format((int) $stockTakes->where('status', 'completed')->count()) }}</div></div>
      <div class="kpi-item var-risiko"><div class="label">TOTAL ITEM HILANG</div><div class="value">{{ number_format((int) $stockTakes->sum('missing_items_count')) }}</div></div>
    </div>

    <div style="margin-top:12px;">
      <table>
        <thead>
          <tr>
            <th class="col-session">Sesi</th>
            <th class="col-status">Status</th>
            <th class="col-scope">Cakupan</th>
            <th class="col-mini">Target</th>
            <th class="col-mini">Temuan</th>
            <th class="col-mini">Hilang</th>
            <th class="col-mini">Tak Terduga</th>
            <th class="col-action">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($stockTakes as $s)
            @php
              $statusLabel = match($s->status){
                'draft' => 'DRAF',
                'in_progress' => 'BERJALAN',
                'completed' => 'SELESAI',
                'cancelled' => 'DIBATALKAN',
                default => strtoupper((string) $s->status),
              };
            @endphp
            <tr>
              <td>
                <div style="font-weight:700;">{{ $s->name }}</div>
                <div class="muted">{{ optional($s->created_at)->format('d M Y H:i') }} | {{ $s->user->name ?? '-' }}</div>
              </td>
              <td class="status-cell"><span class="badge {{ $s->status }}">{{ $statusLabel }}</span></td>
              <td class="scope-cell">{{ $s->branch->name ?? 'Semua cabang' }} / {{ $s->shelf->name ?? 'Semua rak' }}</td>
              <td class="cell-right">{{ number_format($s->expected_items_count) }}</td>
              <td class="cell-right">{{ number_format($s->found_items_count) }}</td>
              <td class="cell-right">{{ number_format($s->missing_items_count) }}</td>
              <td class="cell-right">{{ number_format($s->unexpected_items_count) }}</td>
              <td class="cell-right"><a href="{{ route('stock_takes.show', $s->id) }}" class="btn-link">Buka</a></td>
            </tr>
          @empty
            <tr><td colspan="8" class="muted" style="padding:18px 8px;">Belum ada sesi opname.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="margin-top:10px;">{{ $stockTakes->links() }}</div>
  </div>
</div>
@endsection
