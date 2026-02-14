@extends('layouts.notobuku')

@section('title', 'Copy Cataloging - NOTOBUKU')

@section('content')
<style>
  .page{ max-width:1180px; margin:0 auto; display:flex; flex-direction:column; gap:14px; }
  .card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; }
  .title{ margin:0; font-size:20px; font-weight:700; }
  .muted{ color:var(--nb-muted); font-size:13px; }
  .alert{ border-radius:12px; border:1px solid var(--nb-border); padding:10px 12px; font-size:13px; }
  .alert.ok{ border-color:rgba(16,185,129,.35); color:#065f46; background:rgba(16,185,129,.08); }
  .alert.err{ border-color:rgba(239,68,68,.35); color:#991b1b; background:rgba(239,68,68,.08); }
  .grid{ display:grid; gap:10px; }
  .grid.search{ grid-template-columns:repeat(5,minmax(0,1fr)); }
  .grid.source{ grid-template-columns:repeat(4,minmax(0,1fr)); }
  .field label{ display:block; font-size:12px; color:var(--nb-muted); font-weight:600; margin-bottom:6px; }
  .nb-field{ width:100%; border:1px solid var(--nb-border); border-radius:12px; padding:10px 12px; background:var(--nb-surface); }
  .btn{ display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--nb-border); border-radius:12px; padding:10px 12px; font-weight:600; }
  .btn-primary{ background:linear-gradient(90deg,#1e88e5,#1565c0); color:#fff; border-color:transparent; }
  .btn-dark{ background:#0f172a; color:#fff; border-color:transparent; }
  .btn-sm{ padding:7px 10px; border-radius:10px; font-size:12px; }
  .kpi{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:10px; }
  .kpi-item{ border:1px solid var(--nb-border); border-radius:12px; padding:10px; }
  .kpi-item.var-aktif{ background:rgba(16,185,129,.10); }
  .kpi-item.var-total{ background:rgba(59,130,246,.10); }
  .kpi-item.var-hasil{ background:rgba(99,102,241,.10); }
  .kpi-item.var-import{ background:rgba(245,158,11,.12); }
  .kpi-item .label{ font-size:11px; color:var(--nb-muted); }
  .kpi-item .value{ margin-top:6px; font-size:20px; font-weight:700; }
  .proto{ display:inline-flex; align-items:center; border-radius:999px; border:1px solid var(--nb-border); padding:2px 8px; font-size:11px; font-weight:700; }
  .proto.sru{ color:#1d4ed8; border-color:rgba(59,130,246,.35); background:rgba(59,130,246,.08); }
  .proto.z3950{ color:#92400e; border-color:rgba(245,158,11,.35); background:rgba(245,158,11,.10); }
  .proto.p2p{ color:#047857; border-color:rgba(16,185,129,.35); background:rgba(16,185,129,.08); }
  .status{ display:inline-flex; align-items:center; border-radius:999px; border:1px solid var(--nb-border); padding:2px 8px; font-size:11px; font-weight:700; }
  .status.imported{ color:#047857; border-color:rgba(16,185,129,.35); background:rgba(16,185,129,.08); }
  .status.failed{ color:#b91c1c; border-color:rgba(239,68,68,.35); background:rgba(239,68,68,.10); }
  table{ width:100%; border-collapse:collapse; table-layout:fixed; }
  th, td{ border-bottom:1px solid var(--nb-border); padding:9px 8px; font-size:13px; text-align:left; vertical-align:top; word-break:break-word; }
  th{ color:var(--nb-muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
  .actions{ text-align:right; }
  @media (max-width:1100px){
    .grid.search, .grid.source{ grid-template-columns:1fr; }
    .kpi{ grid-template-columns:repeat(2,minmax(0,1fr)); }
    table{ table-layout:auto; }
  }
</style>

<div class="page">
  <div class="card">
    <h1 class="title">Klien Copy Cataloging</h1>
    <div class="muted">Cari data bibliografi dari SRU, gateway Z39.50, atau P2P lalu impor cepat ke katalog lokal.</div>
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
    <h2 class="title" style="font-size:16px;">Cari Rekaman Eksternal</h2>
    <form method="GET" action="{{ route('copy_cataloging.index') }}" class="grid search" style="margin-top:12px;">
      <div class="field">
        <label>Sumber</label>
        <select name="source_id" required class="nb-field">
          <option value="">Pilih sumber...</option>
          @foreach($sources as $source)
            <option value="{{ $source->id }}" @selected((int)$sourceId === (int)$source->id)>{{ $source->name }} ({{ strtoupper($source->protocol) }})</option>
          @endforeach
        </select>
      </div>
      <div class="field" style="grid-column:span 2;">
        <label>Query</label>
        <input name="q" value="{{ $q }}" required placeholder="Judul / ISBN / pengarang" class="nb-field" />
      </div>
      <div class="field">
        <label>Limit</label>
        <input type="number" name="limit" min="1" max="30" value="{{ $limit }}" class="nb-field" />
      </div>
      <div style="display:flex; align-items:flex-end;">
        <button class="btn btn-primary" type="submit" style="width:100%;">Cari</button>
      </div>
    </form>

    <div class="kpi">
      <div class="kpi-item var-aktif"><div class="label">SUMBER AKTIF</div><div class="value">{{ number_format((int) $sources->where('is_active', true)->count()) }}</div></div>
      <div class="kpi-item var-total"><div class="label">TOTAL SUMBER</div><div class="value">{{ number_format((int) $sources->count()) }}</div></div>
      <div class="kpi-item var-hasil"><div class="label">HASIL QUERY</div><div class="value">{{ number_format((int) count($results)) }}</div></div>
      <div class="kpi-item var-import"><div class="label">RIWAYAT IMPOR</div><div class="value">{{ number_format((int) $imports->count()) }}</div></div>
    </div>
  </div>

  <div class="card">
    <h2 class="title" style="font-size:16px;">Tambah Sumber</h2>
    <form method="POST" action="{{ route('copy_cataloging.sources.store') }}" class="grid source" style="margin-top:12px;">
      @csrf
      <div class="field">
        <label>Nama sumber</label>
        <input name="name" required placeholder="Mis: Perpusnas SRU" class="nb-field" />
      </div>
      <div class="field">
        <label>Protocol</label>
        <select name="protocol" class="nb-field">
          <option value="sru">SRU</option>
          <option value="z3950">Gateway Z39.50</option>
          <option value="p2p">P2P JSON</option>
        </select>
      </div>
      <div class="field" style="grid-column:span 2;">
        <label>Endpoint URL</label>
        <input name="endpoint" required placeholder="https://..." class="nb-field" />
      </div>
      <div class="field" style="grid-column:span 2;">
        <label>URL Gateway (opsional untuk Z39.50)</label>
        <input name="gateway_url" placeholder="https://..." class="nb-field" />
      </div>
      <div class="field">
        <label>Username</label>
        <input name="username" placeholder="opsional" class="nb-field" />
      </div>
      <div class="field">
        <label>Password</label>
        <input name="password" placeholder="opsional" class="nb-field" />
      </div>
      <div style="display:flex; align-items:flex-end;">
        <button class="btn btn-dark" type="submit" style="width:100%;">Simpan Sumber</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2 class="title" style="font-size:16px;">Hasil Pencarian @if($activeSource) - {{ $activeSource->name }} @endif</h2>
    <div style="margin-top:10px;">
      <table>
        <thead>
          <tr>
            <th>Judul</th>
            <th>Pengarang</th>
            <th>Penerbit</th>
            <th>Tahun</th>
            <th>ISBN</th>
            <th class="actions">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($results as $row)
            <tr>
              <td>{{ $row['title'] ?? '-' }}</td>
              <td>{{ $row['author'] ?? '-' }}</td>
              <td>{{ $row['publisher'] ?? '-' }}</td>
              <td>{{ $row['publish_year'] ?? '-' }}</td>
              <td>{{ $row['isbn'] ?? '-' }}</td>
              <td class="actions">
                @if($activeSource)
                  <form method="POST" action="{{ route('copy_cataloging.import') }}">
                    @csrf
                    <input type="hidden" name="source_id" value="{{ $activeSource->id }}">
                    <input type="hidden" name="record_payload" value="{{ base64_encode(json_encode($row)) }}">
                    <button class="btn btn-primary btn-sm" type="submit">Impor</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="muted" style="padding:18px 8px;">Belum ada hasil. Jalankan pencarian terlebih dulu.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h2 class="title" style="font-size:16px;">Riwayat Impor</h2>
    <div style="margin-top:10px;">
      <table>
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Sumber</th>
            <th>Protokol</th>
            <th>Judul</th>
            <th>Status</th>
            <th>Operator</th>
          </tr>
        </thead>
        <tbody>
          @forelse($imports as $imp)
            @php
              $proto = strtolower((string) ($imp->source->protocol ?? ''));
            @endphp
            <tr>
              <td>{{ optional($imp->created_at)->format('d M Y H:i') }}</td>
              <td>{{ $imp->source->name ?? '-' }}</td>
              <td>
                <span class="proto {{ $proto }}">{{ strtoupper($proto !== '' ? $proto : '-') }}</span>
              </td>
              <td>{{ $imp->title ?? '-' }}</td>
              <td><span class="status {{ strtolower((string)$imp->status) }}">{{ strtoupper((string) $imp->status) }}</span></td>
              <td>{{ $imp->user->name ?? '-' }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="muted" style="padding:18px 8px;">Belum ada riwayat import.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
