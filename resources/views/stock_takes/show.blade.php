@extends('layouts.notobuku')

@section('title', 'Detail Stock Opname - NOTOBUKU')

@section('content')
<style>
  .page{ max-width:1180px; margin:0 auto; display:flex; flex-direction:column; gap:14px; }
  .card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; }
  .title{ margin:0; font-size:20px; font-weight:700; }
  .muted{ color:var(--nb-muted); font-size:13px; }
  .alert{ border-radius:12px; border:1px solid var(--nb-border); padding:10px 12px; font-size:13px; }
  .alert.ok{ border-color:rgba(16,185,129,.35); color:#065f46; background:rgba(16,185,129,.08); }
  .alert.err{ border-color:rgba(239,68,68,.35); color:#991b1b; background:rgba(239,68,68,.08); }
  .btn{ display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--nb-border); border-radius:12px; padding:10px 12px; font-weight:600; }
  .btn-primary{ background:linear-gradient(90deg,#1e88e5,#1565c0); color:#fff; border-color:transparent; }
  .btn-success{ background:linear-gradient(90deg,#10b981,#059669); color:#fff; border-color:transparent; }
  .kpi-grid{ display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; }
  .kpi{ border:1px solid var(--nb-border); border-radius:12px; padding:10px; }
  .kpi.var-target{ background:rgba(59,130,246,.10); }
  .kpi.var-temuan{ background:rgba(16,185,129,.10); }
  .kpi.var-hilang{ background:rgba(239,68,68,.10); }
  .kpi.var-takterduga{ background:rgba(245,158,11,.12); }
  .kpi.var-scan{ background:rgba(99,102,241,.10); }
  .kpi .label{ font-size:11px; color:var(--nb-muted); }
  .kpi .value{ margin-top:6px; font-size:22px; font-weight:700; }
  .kpi .value.good{ color:#047857; }
  .kpi .value.warn{ color:#b91c1c; }
  .kpi .value.info{ color:#92400e; }
  .scan-grid{ display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; }
  .field label{ display:block; font-size:12px; color:var(--nb-muted); font-weight:600; margin-bottom:6px; }
  .nb-field{ width:100%; border:1px solid var(--nb-border); border-radius:12px; padding:10px 12px; background:var(--nb-surface); }
  table{ width:100%; border-collapse:collapse; table-layout:fixed; }
  th, td{ border-bottom:1px solid var(--nb-border); padding:9px 8px; font-size:13px; text-align:left; vertical-align:top; word-break:break-word; }
  th{ color:var(--nb-muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
  .pill{ display:inline-flex; align-items:center; border-radius:999px; border:1px solid var(--nb-border); padding:3px 8px; font-size:11px; font-weight:700; }
  .pill.found{ color:#047857; border-color:rgba(16,185,129,.35); background:rgba(16,185,129,.10); }
  .pill.missing{ color:#b91c1c; border-color:rgba(239,68,68,.35); background:rgba(239,68,68,.10); }
  .pill.unexpected{ color:#92400e; border-color:rgba(245,158,11,.35); background:rgba(245,158,11,.10); }
  .pill.pending{ color:#475569; background:rgba(148,163,184,.10); }
  @media (max-width:1100px){
    .kpi-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); }
    .scan-grid{ grid-template-columns:1fr; }
    table{ table-layout:auto; }
  }
</style>

<div class="page">
  <div class="card">
    @php
      $statusLabel = match($stockTake->status){
        'draft' => 'DRAF',
        'in_progress' => 'SEDANG BERJALAN',
        'completed' => 'SELESAI',
        'cancelled' => 'DIBATALKAN',
        default => strtoupper((string) $stockTake->status),
      };
    @endphp
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap;">
      <div>
        <h1 class="title">{{ $stockTake->name }}</h1>
        <div class="muted" style="margin-top:4px;">
          Status: <b>{{ $statusLabel }}</b> |
          Cakupan: {{ $stockTake->branch->name ?? 'Semua cabang' }} / {{ $stockTake->shelf->name ?? 'Semua rak' }} |
          Filter status item: {{ strtoupper($stockTake->scope_status) }}
        </div>
      </div>
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="{{ route('stock_takes.index') }}" class="btn">Kembali</a>
        <a href="{{ route('stock_takes.export.csv', $stockTake->id) }}" class="btn">Export CSV</a>
        @if($stockTake->status === 'draft')
          <form method="POST" action="{{ route('stock_takes.start', $stockTake->id) }}">@csrf
            <button class="btn btn-primary" type="submit">Mulai Opname</button>
          </form>
        @endif
        @if($stockTake->status === 'in_progress')
          <form method="POST" action="{{ route('stock_takes.complete', $stockTake->id) }}">@csrf
            <button class="btn btn-success" type="submit">Selesaikan Sesi</button>
          </form>
        @endif
      </div>
    </div>
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

  <div class="kpi-grid">
    <div class="kpi var-target"><div class="label">TARGET ITEM</div><div class="value">{{ $summary['expected_items_count'] ?? 0 }}</div></div>
    <div class="kpi var-temuan"><div class="label">ITEM DITEMUKAN</div><div class="value good">{{ $summary['found_items_count'] ?? 0 }}</div></div>
    <div class="kpi var-hilang"><div class="label">ITEM HILANG</div><div class="value warn">{{ $summary['missing_items_count'] ?? 0 }}</div></div>
    <div class="kpi var-takterduga"><div class="label">ITEM TAK TERDUGA</div><div class="value info">{{ $summary['unexpected_items_count'] ?? 0 }}</div></div>
    <div class="kpi var-scan"><div class="label">TOTAL TERPINDAI</div><div class="value">{{ $summary['scanned_items_count'] ?? 0 }}</div></div>
  </div>

  @if($stockTake->status === 'in_progress')
    <div class="card">
      <h2 class="title" style="font-size:16px;">Pindai Barcode</h2>
      <form method="POST" action="{{ route('stock_takes.scan', $stockTake->id) }}" class="scan-grid" style="margin-top:10px;">
        @csrf
        <div class="field" style="grid-column:span 2;">
          <label>Barcode</label>
          <input name="barcode" autofocus required placeholder="Scan / ketik barcode" class="nb-field" style="font-family:monospace;" />
        </div>
        <div class="field" style="grid-column:span 2;">
          <label>Catatan</label>
          <input name="notes" placeholder="Catatan pindai (opsional)" class="nb-field" />
        </div>
        <div style="display:flex; align-items:flex-end;">
          <button class="btn btn-primary" type="submit" style="width:100%;">Proses Pindai</button>
        </div>
      </form>
    </div>
  @endif

  <div class="card">
    <h2 class="title" style="font-size:16px;">Rincian Baris</h2>
    <div style="margin-top:10px;">
      <table>
        <thead>
          <tr>
            <th>Barcode</th>
            <th>Judul</th>
            <th>Target</th>
            <th>Temuan</th>
            <th>Status Pindai</th>
            <th>Status Item</th>
            <th>Kondisi</th>
            <th>Scanned At</th>
          </tr>
        </thead>
        <tbody>
          @forelse($lines as $line)
            @php
              $scanClass = match($line->scan_status){
                'found' => 'found',
                'missing' => 'missing',
                'unexpected', 'out_of_scope' => 'unexpected',
                default => 'pending',
              };
            @endphp
            <tr>
              <td style="font-family:monospace;">{{ $line->barcode }}</td>
              <td>{{ $line->title_snapshot ?: '-' }}</td>
              <td>{{ $line->expected ? 'Ya' : 'Tidak' }}</td>
              <td>{{ $line->found ? 'Ya' : 'Tidak' }}</td>
              <td><span class="pill {{ $scanClass }}">{{ strtoupper($line->scan_status) }}</span></td>
              <td>{{ $line->status_snapshot ?: '-' }}</td>
              <td>{{ $line->condition_snapshot ?: '-' }}</td>
              <td>{{ optional($line->scanned_at)->format('d M Y H:i:s') ?: '-' }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="muted" style="padding:18px 8px;">Belum ada line.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div style="margin-top:10px;">{{ $lines->links() }}</div>
  </div>
</div>
@endsection
