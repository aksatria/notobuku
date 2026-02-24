@extends('layouts.notobuku')

@section('title', 'Data Anggota')

@section('content')
@php
  $members = $members ?? null;
  $summary = $summary ?? ['total' => 0, 'active' => 0, 'overdue' => 0, 'unpaid' => 0];
  $q = (string) ($q ?? '');
  $status = (string) ($status ?? '');
  $importPreview = $importPreview ?? null;
  $canUndoImport = (bool) ($canUndoImport ?? false);
  $importMetrics = $importMetrics ?? [
    'last_7d_import_runs' => 0,
    'last_7d_undo_runs' => 0,
    'last_7d_inserted' => 0,
    'last_7d_updated' => 0,
    'last_7d_skipped' => 0,
    'last_30d_import_runs' => 0,
    'last_30d_undo_runs' => 0,
    'last_30d_inserted' => 0,
    'last_30d_updated' => 0,
    'last_30d_skipped' => 0,
    'last_30d_override_email_dup' => 0,
    'daily_7d' => [],
    'daily_30d' => [],
    'recent' => [],
  ];
  $historyFrom = (string) request()->query('from', now()->subDays(30)->toDateString());
  $historyTo = (string) request()->query('to', now()->toDateString());
  $historyAction = (string) request()->query('action', '');
  $historyLimit = (int) request()->query('limit', 500);
  if ($historyLimit < 1) $historyLimit = 1;
  if ($historyLimit > 2000) $historyLimit = 2000;
  $daily7 = (array) ($importMetrics['daily_7d'] ?? []);
  $daily30 = (array) ($importMetrics['daily_30d'] ?? []);
  $chartMetricsUrl = route('anggota.import.metrics.chart');
  $kpiMetricsUrl = route('anggota.metrics.kpi');
  $kpiSpark = (array) ($summary['sparklines'] ?? []);
  $sparkLabels = (array) ($kpiSpark['labels'] ?? []);
  $sparkTotal = array_map('intval', (array) ($kpiSpark['total'] ?? array_fill(0, 7, 0)));
  $sparkActive = array_map('intval', (array) ($kpiSpark['active'] ?? array_fill(0, 7, 0)));
  $sparkOverdue = array_map('intval', (array) ($kpiSpark['overdue'] ?? array_fill(0, 7, 0)));
  $sparkUnpaid = array_map('intval', (array) ($kpiSpark['unpaid'] ?? array_fill(0, 7, 0)));
  $sparkHint = function (array $vals, array $labels): string {
    $parts = [];
    foreach ($vals as $i => $v) {
      $d = (string) ($labels[$i] ?? ('D-' . (6 - $i)));
      $parts[] = $d . ': ' . (int) $v;
    }
    return implode(' | ', $parts);
  };
@endphp

<style>
  .page{ max-width:1180px; margin:0 auto; display:flex; flex-direction:column; gap:14px; }
  .card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; }
  .title{ margin:0; font-size:20px; font-weight:700; }
  .muted{ color:var(--nb-muted); font-size:13px; }
  .kpi{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:12px; }
  .kpi-item{ border:1px solid var(--nb-border); border-radius:14px; padding:10px; background:var(--nb-surface); }
  .kpi-label{ font-size:12px; color:var(--nb-muted); }
  .kpi-value{ margin-top:4px; font-size:20px; font-weight:700; }
  .kpi-spark{ margin-top:8px; height:26px; display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); align-items:end; gap:4px; }
  .kpi-spark-bar{ border-radius:999px; min-height:2px; background:linear-gradient(180deg,#4aa3ff,#1e88e5); opacity:.9; }
  .kpi-spark-bar.muted{ background:rgba(30,136,229,.2); }
  .kpi-foot{ margin-top:4px; font-size:11px; color:var(--nb-muted); }
  .grid{ display:grid; grid-template-columns:2fr 1fr auto auto; gap:10px; align-items:end; }
  .field label{ display:block; font-size:12px; color:var(--nb-muted); margin-bottom:6px; font-weight:600; }
  .nb-field{ width:100%; border:1px solid var(--nb-border); background:var(--nb-surface); border-radius:12px; padding:10px 12px; }
  .btn{ display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--nb-border); border-radius:12px; padding:10px 12px; font-weight:600; }
  .btn-primary{ background:linear-gradient(90deg,#1e88e5,#1565c0); color:#fff; border-color:transparent; }
  .table-wrap{ overflow:auto; border:1px solid var(--nb-border); border-radius:14px; }
  table{ width:100%; border-collapse:collapse; min-width:900px; }
  th, td{ border-bottom:1px solid var(--nb-border); padding:11px 12px; text-align:left; font-size:13px; vertical-align:middle; }
  th{ background:rgba(30,136,229,.08); font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:var(--nb-muted); }
  .tag{ display:inline-flex; align-items:center; border:1px solid var(--nb-border); border-radius:999px; padding:3px 8px; font-size:11px; font-weight:700; }
  .tag.active{ color:#0f766e; border-color:rgba(15,118,110,.35); background:rgba(20,184,166,.12); }
  .tag.inactive{ color:#9a3412; border-color:rgba(154,52,18,.35); background:rgba(249,115,22,.12); }
  .tag.suspended{ color:#b91c1c; border-color:rgba(185,28,28,.35); background:rgba(239,68,68,.12); }
  .actions{ display:flex; gap:8px; }
  .err{ margin-top:4px; font-size:12px; color:#b91c1c; }
  .warn{ margin-top:4px; font-size:12px; color:#9a3412; }
  .mini-chart{ margin-top:10px; border:1px solid var(--nb-border); border-radius:14px; padding:12px; background:var(--nb-surface); }
  .mini-chart-head{ display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:8px; flex-wrap:wrap; }
  .mini-chart-right{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .mini-chart-window{ display:flex; border:1px solid var(--nb-border); border-radius:999px; padding:2px; background:#fff; }
  .mini-chart-window button{ border:0; background:transparent; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:700; color:var(--nb-muted); cursor:pointer; }
  .mini-chart-window button.active{ background:rgba(30,136,229,.14); color:#1565c0; }
  .mini-chart-legend{ display:flex; gap:6px; flex-wrap:wrap; }
  .mini-chart-legend button{ border:1px solid var(--nb-border); border-radius:999px; background:#fff; font-size:12px; font-weight:700; padding:4px 9px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
  .mini-chart-legend button.off{ opacity:.45; }
  .mini-chart-dot{ width:10px; height:10px; border-radius:999px; display:inline-block; }
  .mini-chart-canvas{ height:110px; display:grid; align-items:end; gap:4px; }
  .mini-chart-canvas.day7{ grid-template-columns:repeat(7,minmax(0,1fr)); }
  .mini-chart-canvas.day30{ grid-template-columns:repeat(30,minmax(0,1fr)); }
  .mini-chart-col{ position:relative; height:110px; border-radius:8px; }
  .mini-chart-segment{ position:absolute; left:0; right:0; bottom:0; border-radius:6px 6px 0 0; min-height:2px; opacity:.92; }
  .export-grid{ display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:8px; align-items:end; }
  @media (max-width:980px){ .export-grid{ grid-template-columns:1fr 1fr; } }
  @media (max-width:980px){
    .kpi{ grid-template-columns:repeat(2,minmax(0,1fr)); }
    .grid{ grid-template-columns:1fr; }
  }
</style>

<div class="page">
  <div class="card"
    id="member-kpi-panel"
    data-kpi-url="{{ $kpiMetricsUrl }}"
    data-spark-labels='@json($sparkLabels)'
    data-spark-total='@json($sparkTotal)'
    data-spark-active='@json($sparkActive)'
    data-spark-overdue='@json($sparkOverdue)'
    data-spark-unpaid='@json($sparkUnpaid)'>
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
      <div>
        <h1 class="title">Data Anggota</h1>
        <div class="muted">Manajemen anggota aktif, overdue, dan tunggakan denda.</div>
      </div>
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a class="btn" href="{{ route('anggota.template.csv') }}">Template CSV</a>
        <a class="btn btn-primary" href="{{ route('anggota.create') }}">Tambah Anggota</a>
      </div>
    </div>

    <div class="kpi">
      @php $sparkMaxTotal = max(1, ...$sparkTotal); @endphp
      <div class="kpi-item">
        <div class="kpi-label">Total Anggota</div>
        <div class="kpi-value" data-kpi-value="total">{{ number_format((int) $summary['total']) }}</div>
        <div class="kpi-spark" data-kpi-spark="total" title="{{ $sparkHint($sparkTotal, $sparkLabels) }}">
          @foreach($sparkTotal as $v)
            @php $h = max(2, (int) round(($v / $sparkMaxTotal) * 26)); @endphp
            <div class="kpi-spark-bar {{ $v <= 0 ? 'muted' : '' }}" style="height:{{ $h }}px;"></div>
          @endforeach
        </div>
        <div class="kpi-foot">Tren anggota baru 7 hari</div>
      </div>
      @php $sparkMaxActive = max(1, ...$sparkActive); @endphp
      <div class="kpi-item">
        <div class="kpi-label">Anggota Aktif</div>
        <div class="kpi-value" data-kpi-value="active">{{ number_format((int) $summary['active']) }}</div>
        <div class="kpi-spark" data-kpi-spark="active" title="{{ $sparkHint($sparkActive, $sparkLabels) }}">
          @foreach($sparkActive as $v)
            @php $h = max(2, (int) round(($v / $sparkMaxActive) * 26)); @endphp
            <div class="kpi-spark-bar {{ $v <= 0 ? 'muted' : '' }}" style="height:{{ $h }}px;"></div>
          @endforeach
        </div>
        <div class="kpi-foot">Tren anggota aktif baru 7 hari</div>
      </div>
      @php $sparkMaxOverdue = max(1, ...$sparkOverdue); @endphp
      <div class="kpi-item">
        <div class="kpi-label">Punya Overdue</div>
        <div class="kpi-value" data-kpi-value="overdue">{{ number_format((int) $summary['overdue']) }}</div>
        <div class="kpi-spark" data-kpi-spark="overdue" title="{{ $sparkHint($sparkOverdue, $sparkLabels) }}">
          @foreach($sparkOverdue as $v)
            @php $h = max(2, (int) round(($v / $sparkMaxOverdue) * 26)); @endphp
            <div class="kpi-spark-bar {{ $v <= 0 ? 'muted' : '' }}" style="height:{{ $h }}px;"></div>
          @endforeach
        </div>
        <div class="kpi-foot">Tren jatuh tempo item 7 hari</div>
      </div>
      @php $sparkMaxUnpaid = max(1, ...$sparkUnpaid); @endphp
      <div class="kpi-item">
        <div class="kpi-label">Punya Denda Unpaid</div>
        <div class="kpi-value" data-kpi-value="unpaid">{{ number_format((int) $summary['unpaid']) }}</div>
        <div class="kpi-spark" data-kpi-spark="unpaid" title="{{ $sparkHint($sparkUnpaid, $sparkLabels) }}">
          @foreach($sparkUnpaid as $v)
            @php $h = max(2, (int) round(($v / $sparkMaxUnpaid) * 26)); @endphp
            <div class="kpi-spark-bar {{ $v <= 0 ? 'muted' : '' }}" style="height:{{ $h }}px;"></div>
          @endforeach
        </div>
        <div class="kpi-foot">Tren denda unpaid ter-assess 7 hari</div>
      </div>
    </div>
  </div>

  <div class="card">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
      <div>
        <div style="font-weight:700;">Metrik Impor (30 hari)</div>
        <div class="muted">Ringkasan operasional impor anggota dari audit log.</div>
      </div>
    </div>
    <div class="kpi">
      <div class="kpi-item"><div class="kpi-label">Run Impor (7d)</div><div class="kpi-value">{{ number_format((int) $importMetrics['last_7d_import_runs']) }}</div></div>
      <div class="kpi-item"><div class="kpi-label">Run Batalkan (7d)</div><div class="kpi-value">{{ number_format((int) $importMetrics['last_7d_undo_runs']) }}</div></div>
      <div class="kpi-item"><div class="kpi-label">Run Impor</div><div class="kpi-value">{{ number_format((int) $importMetrics['last_30d_import_runs']) }}</div></div>
      <div class="kpi-item"><div class="kpi-label">Run Batalkan</div><div class="kpi-value">{{ number_format((int) $importMetrics['last_30d_undo_runs']) }}</div></div>
      <div class="kpi-item"><div class="kpi-label">Ditambahkan</div><div class="kpi-value">{{ number_format((int) $importMetrics['last_30d_inserted']) }}</div></div>
      <div class="kpi-item"><div class="kpi-label">Diperbarui</div><div class="kpi-value">{{ number_format((int) $importMetrics['last_30d_updated']) }}</div></div>
      <div class="kpi-item"><div class="kpi-label">Dilewati</div><div class="kpi-value">{{ number_format((int) $importMetrics['last_30d_skipped']) }}</div></div>
      <div class="kpi-item"><div class="kpi-label">Override Email Duplikat</div><div class="kpi-value">{{ number_format((int) $importMetrics['last_30d_override_email_dup']) }}</div></div>
    </div>
    <div class="mini-chart" id="member-import-mini-chart"
      data-chart-url="{{ $chartMetricsUrl }}"
      data-daily7='@json($daily7)'
      data-daily30='@json($daily30)'>
      <div class="mini-chart-head">
        <div>
          <div style="font-weight:700;">Mini Chart Aktivitas Impor</div>
          <div class="muted">Auto-refresh tiap 30 detik</div>
        </div>
        <div class="mini-chart-right">
          <div class="mini-chart-window" role="group" aria-label="Rentang chart">
            <button type="button" data-window="7">7d</button>
            <button type="button" data-window="30" class="active">30d</button>
          </div>
          <div class="mini-chart-legend">
            <button type="button" data-series="import_runs"><span class="mini-chart-dot" style="background:#1e88e5;"></span>Impor</button>
            <button type="button" data-series="undo_runs"><span class="mini-chart-dot" style="background:#fb8c00;"></span>Batalkan</button>
            <button type="button" data-series="inserted"><span class="mini-chart-dot" style="background:#2e7d32;"></span>Ditambahkan</button>
          </div>
        </div>
      </div>
      <div class="mini-chart-canvas day30" data-chart-canvas></div>
      <div class="muted" data-chart-status>Menampilkan 30 hari terakhir.</div>
    </div>

    <form method="GET" action="{{ route('anggota.import.history') }}" class="export-grid" style="margin-top:10px;">
      <div class="field">
        <label>From</label>
        <input type="date" class="nb-field" name="from" value="{{ $historyFrom }}">
      </div>
      <div class="field">
        <label>To</label>
        <input type="date" class="nb-field" name="to" value="{{ $historyTo }}">
      </div>
      <div class="field">
        <label>Action</label>
        <select class="nb-field" name="action">
          <option value="" @selected($historyAction === '')>Semua</option>
          <option value="member_import" @selected($historyAction === 'member_import')>Impor</option>
          <option value="member_import_undo" @selected($historyAction === 'member_import_undo')>Batalkan</option>
        </select>
      </div>
      <div class="field">
        <label>Limit</label>
        <input type="number" class="nb-field" name="limit" min="1" max="2000" value="{{ $historyLimit }}">
      </div>
      <button class="btn" type="submit">Ekspor Riwayat CSV</button>
      <button class="btn" type="submit" formaction="{{ route('anggota.import.history.xlsx') }}">Ekspor Riwayat XLSX</button>
    </form>

    @if(!empty($importMetrics['recent']))
      <div class="table-wrap" style="margin-top:10px;">
        <table>
          <thead>
            <tr>
              <th>Waktu</th>
              <th>Aksi</th>
              <th>User</th>
              <th>Status</th>
              <th>Ditambahkan</th>
              <th>Diperbarui</th>
              <th>Dilewati</th>
              <th>Override</th>
            </tr>
          </thead>
          <tbody>
            @foreach(array_slice((array) $importMetrics['recent'], 0, 10) as $r)
              <tr>
                <td>{{ !empty($r['created_at']) ? \Illuminate\Support\Carbon::parse($r['created_at'])->format('d M Y H:i') : '-' }}</td>
                <td>{{ strtoupper((string) ($r['action'] ?? '')) }}</td>
                <td>{{ $r['user_name'] ?? '-' }}</td>
                <td>{{ strtoupper((string) ($r['status'] ?? '-')) }}</td>
                <td>{{ number_format((int) ($r['inserted'] ?? 0)) }}</td>
                <td>{{ number_format((int) ($r['updated'] ?? 0)) }}</td>
                <td>{{ number_format((int) ($r['skipped'] ?? 0)) }}</td>
                <td>{{ !empty($r['force_email_duplicate']) ? 'YES' : '-' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <div class="card">
    <form method="POST" action="{{ route('anggota.import.preview') }}" enctype="multipart/form-data" class="grid" style="grid-template-columns:2fr auto;">
      @csrf
      <div class="field">
        <label>Pratinjau Impor Anggota via CSV</label>
        <input class="nb-field" type="file" name="csv_file" accept=".csv,text/csv">
        @error('csv_file')<div class="err">{{ $message }}</div>@enderror
      </div>
      <button class="btn btn-primary" type="submit">Pratinjau CSV</button>
    </form>
    <div class="warn">Alur baru: Pratinjau -> Konfirmasi impor. Bisa undo batch terakhir.</div>
    <div class="muted">Batas pratinjau impor: 3000 baris per file.</div>
  </div>

  @if(!empty($importPreview))
    @php
      $sum = $importPreview['summary'] ?? ['total' => 0, 'valid' => 0, 'errors' => 0, 'will_insert' => 0, 'will_update' => 0, 'duplicate_email_rows' => 0, 'duplicate_phone_rows' => 0];
      $rows = (array) ($importPreview['rows'] ?? []);
      $confirmToken = (string) ($importPreview['confirm_token'] ?? '');
      $role = (string) (auth()->user()->role ?? 'member');
      $canForceEmailDup = in_array($role, ['super_admin', 'admin', 'staff'], true);
    @endphp
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
        <div>
          <div style="font-weight:700;">Pratinjau Impor CSV</div>
          <div class="muted">
            Total: {{ $sum['total'] }} | Valid: {{ $sum['valid'] }} | Error: {{ $sum['errors'] }} | Insert: {{ $sum['will_insert'] }} | Update: {{ $sum['will_update'] }}
          </div>
          <div class="muted">
            Duplicate email rows: {{ $sum['duplicate_email_rows'] ?? 0 }} | Duplicate phone rows: {{ $sum['duplicate_phone_rows'] ?? 0 }}
          </div>
          <div class="warn">
            Aturan hybrid: email duplikat = blokir keras, telepon duplikat = peringatan saja.
          </div>
          @if(($sum['duplicate_email_rows'] ?? 0) > 0 && !$canForceEmailDup)
            <div class="err">Konfirmasi akan ditolak sampai email duplikat diperbaiki di CSV.</div>
          @endif
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
          <form method="POST" action="{{ route('anggota.import.confirm') }}">
            @csrf
            <input type="hidden" name="confirm_token" value="{{ $confirmToken }}">
            @if(($sum['duplicate_email_rows'] ?? 0) > 0 && $canForceEmailDup)
              <label style="display:flex; align-items:center; gap:6px; margin-bottom:6px; font-size:12px;">
                <input type="checkbox" name="force_email_duplicate" value="1">
                Override email duplikat (petugas/admin)
              </label>
            @endif
            <button class="btn btn-primary" type="submit" @disabled(($sum['valid'] ?? 0) <= 0)>Konfirmasi Impor</button>
          </form>
          <a class="btn" href="{{ route('anggota.import.errors') }}">CSV Error</a>
          <a class="btn" href="{{ route('anggota.import.summary') }}">Ringkasan Pratinjau CSV</a>
          <form method="POST" action="{{ route('anggota.import.cancel') }}">
            @csrf
            <button class="btn" type="submit">Batalkan Pratinjau</button>
          </form>
        </div>
      </div>

      <div class="table-wrap" style="margin-top:10px;">
        <table>
          <thead>
            <tr>
              <th>Line</th>
              <th>Kode</th>
              <th>Nama</th>
              <th>Status</th>
              <th>Aksi DB</th>
              <th>Validasi</th>
              <th>Duplicate Check</th>
            </tr>
          </thead>
          <tbody>
            @foreach(array_slice($rows, 0, 50) as $r)
              <tr>
                <td>{{ $r['line'] ?? '-' }}</td>
                <td>{{ $r['member_code'] ?? '-' }}</td>
                <td>{{ $r['full_name'] ?? '-' }}</td>
                <td>{{ strtoupper((string) ($r['status'] ?? '-')) }}</td>
                <td>
                  @if(!empty($r['is_error']))
                    SKIP
                  @else
                    {{ !empty($r['exists_in_db']) ? 'UPDATE' : 'INSERT' }}
                  @endif
                </td>
                <td>
                  @if(!empty($r['is_error']))
                    <span class="err">{{ $r['error_reason'] ?? 'Invalid row' }}</span>
                  @else
                    OK
                  @endif
                </td>
                <td>
                  @if(!empty($r['has_duplicate_contact']))
                    <span class="warn">{{ $r['duplicate_reason'] ?? 'Duplicate contact detected' }}</span>
                  @else
                    -
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if(count($rows) > 50)
        <div class="muted" style="margin-top:6px;">Menampilkan 50 baris pertama dari {{ count($rows) }} baris pratinjau.</div>
      @endif
    </div>
  @endif

  @if($canUndoImport)
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
        <div>
          <div style="font-weight:700;">Batalkan Batch Impor Terakhir</div>
          <div class="muted">Kembalikan perubahan impor anggota terakhir yang Anda lakukan.</div>
        </div>
        <form method="POST" action="{{ route('anggota.import.undo') }}" onsubmit="return confirm('Batalkan batch impor anggota terakhir?');">
          @csrf
          <button class="btn" type="submit">Batalkan Batch</button>
        </form>
      </div>
    </div>
  @endif

  <div class="card">
    <form method="GET" action="{{ route('anggota.index') }}" class="grid">
      <div class="field">
        <label>Cari</label>
        <input class="nb-field" name="q" value="{{ $q }}" placeholder="Kode anggota / nama / telepon">
      </div>
      <div class="field">
        <label>Status</label>
        <select class="nb-field" name="status">
          <option value="">Semua status</option>
          <option value="active" @selected($status === 'active')>Active</option>
          <option value="inactive" @selected($status === 'inactive')>Inactive</option>
          <option value="suspended" @selected($status === 'suspended')>Suspended</option>
        </select>
      </div>
      <button class="btn btn-primary" type="submit">Filter</button>
      <a class="btn" href="{{ route('anggota.index') }}">Reset</a>
    </form>
  </div>

  <div class="card">
    @if(!$members || $members->count() === 0)
      <div class="muted">Belum ada data anggota.</div>
    @else
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Kode</th>
              <th>Nama</th>
              <th>Kontak</th>
              <th>Status</th>
              <th>Bergabung</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            @foreach($members as $m)
              <tr>
                <td>{{ $m->member_code }}</td>
                <td>
                  <div style="font-weight:600;">{{ $m->full_name }}</div>
                  @if(isset($m->member_type) && (string) $m->member_type !== '')
                    <div class="muted">{{ $m->member_type }}</div>
                  @endif
                </td>
                <td>
                  <div>{{ $m->phone ?: '-' }}</div>
                  @if(isset($m->email))
                    <div class="muted">{{ $m->email ?: '-' }}</div>
                  @endif
                </td>
                <td>
                  <span class="tag {{ $m->status }}">{{ strtoupper($m->status) }}</span>
                </td>
                <td>{{ $m->joined_at ? \Illuminate\Support\Carbon::parse($m->joined_at)->format('d M Y') : '-' }}</td>
                <td>
                  <div class="actions">
                    <a class="btn" href="{{ route('anggota.show', $m->id) }}">Detail</a>
                    <a class="btn" href="{{ route('anggota.edit', $m->id) }}">Edit</a>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div style="margin-top:10px;">{{ $members->links() }}</div>
    @endif
  </div>
</div>
<script>
(() => {
  const root = document.getElementById('member-import-mini-chart');
  const kpiRoot = document.getElementById('member-kpi-panel');
  if (!root) return;

  const chartUrl = root.dataset.chartUrl || '';
  const kpiUrl = kpiRoot ? (kpiRoot.dataset.kpiUrl || '') : '';
  const canvas = root.querySelector('[data-chart-canvas]');
  const statusEl = root.querySelector('[data-chart-status]');
  const windowButtons = Array.from(root.querySelectorAll('[data-window]'));
  const legendButtons = Array.from(root.querySelectorAll('[data-series]'));
  if (!canvas) return;

  const palette = { import_runs: '#1e88e5', undo_runs: '#fb8c00', inserted: '#2e7d32' };
  const labels = { import_runs: 'Impor', undo_runs: 'Batalkan', inserted: 'Ditambahkan' };
  const activeSeries = { import_runs: true, undo_runs: true, inserted: true };
  let activeWindow = 30;
  let payload = {
    7: JSON.parse(root.dataset.daily7 || '[]'),
    30: JSON.parse(root.dataset.daily30 || '[]'),
  };
  let kpiState = {
    labels: kpiRoot ? JSON.parse(kpiRoot.dataset.sparkLabels || '[]') : [],
    total: kpiRoot ? JSON.parse(kpiRoot.dataset.sparkTotal || '[]') : [],
    active: kpiRoot ? JSON.parse(kpiRoot.dataset.sparkActive || '[]') : [],
    overdue: kpiRoot ? JSON.parse(kpiRoot.dataset.sparkOverdue || '[]') : [],
    unpaid: kpiRoot ? JSON.parse(kpiRoot.dataset.sparkUnpaid || '[]') : [],
  };

  function normalize(rows) {
    return (Array.isArray(rows) ? rows : []).map((row) => ({
      date: row.date || '',
      import_runs: Number(row.import_runs || 0),
      undo_runs: Number(row.undo_runs || 0),
      inserted: Number(row.inserted || 0),
    }));
  }

  function render() {
    const rows = normalize(payload[activeWindow] || []);
    const seriesKeys = Object.keys(activeSeries).filter((key) => activeSeries[key]);
    const maxValue = Math.max(1, ...rows.map((r) => Math.max(...seriesKeys.map((k) => Number(r[k] || 0)))));
    canvas.className = 'mini-chart-canvas ' + (activeWindow === 7 ? 'day7' : 'day30');
    canvas.innerHTML = '';

    rows.forEach((row) => {
      const col = document.createElement('div');
      col.className = 'mini-chart-col';
      let offset = 0;
      seriesKeys.forEach((key) => {
        const val = Number(row[key] || 0);
        if (val <= 0) return;
        const h = Math.max(2, Math.round((val / maxValue) * 100));
        const bar = document.createElement('div');
        bar.className = 'mini-chart-segment';
        bar.style.height = `${h}px`;
        bar.style.bottom = `${offset}px`;
        bar.style.background = palette[key];
        bar.title = `${row.date} ${labels[key]}: ${val}`;
        col.appendChild(bar);
        offset += h + 1;
      });
      canvas.appendChild(col);
    });

    if (statusEl) {
      statusEl.textContent = `Menampilkan ${activeWindow} hari terakhir.`;
    }
  }

  function sparkHint(values, labelsArr) {
    return (Array.isArray(values) ? values : []).map((v, i) => `${labelsArr[i] || `D-${6 - i}`}: ${Number(v || 0)}`).join(' | ');
  }

  function renderKpiSpark(key, values, labelsArr) {
    if (!kpiRoot) return;
    const el = kpiRoot.querySelector(`[data-kpi-spark="${key}"]`);
    if (!el) return;
    const list = Array.isArray(values) ? values.map((v) => Number(v || 0)) : [];
    const maxValue = Math.max(1, ...list);
    el.title = sparkHint(list, labelsArr);
    el.innerHTML = '';
    list.forEach((v) => {
      const h = Math.max(2, Math.round((v / maxValue) * 26));
      const bar = document.createElement('div');
      bar.className = `kpi-spark-bar ${v <= 0 ? 'muted' : ''}`;
      bar.style.height = `${h}px`;
      el.appendChild(bar);
    });
  }

  function renderKpi() {
    if (!kpiRoot) return;
    const labelsArr = Array.isArray(kpiState.labels) ? kpiState.labels : [];
    ['total', 'active', 'overdue', 'unpaid'].forEach((key) => {
      renderKpiSpark(key, kpiState[key], labelsArr);
    });
  }

  function updateKpiValue(key, value) {
    if (!kpiRoot) return;
    const el = kpiRoot.querySelector(`[data-kpi-value="${key}"]`);
    if (!el) return;
    const num = Number(value || 0);
    el.textContent = num.toLocaleString('id-ID');
  }

  async function refreshFromServer() {
    if (!chartUrl && !kpiUrl) return;
    try {
      const requests = [];
      if (chartUrl) {
        requests.push(fetch(`${chartUrl}?window=7`, { headers: { 'Accept': 'application/json' } }));
        requests.push(fetch(`${chartUrl}?window=30`, { headers: { 'Accept': 'application/json' } }));
      }
      if (kpiUrl) {
        requests.push(fetch(kpiUrl, { headers: { 'Accept': 'application/json' } }));
      }
      const responses = await Promise.all(requests);
      let offset = 0;

      if (chartUrl) {
        const r7 = responses[offset++];
        const r30 = responses[offset++];
        if (r7 && r7.ok && r30 && r30.ok) {
          const [j7, j30] = await Promise.all([r7.json(), r30.json()]);
          payload[7] = (j7.series && Array.isArray(j7.series.import_runs))
            ? j7.series.import_runs.map((p, i) => ({
                date: p.date || '',
                import_runs: Number(p.value || 0),
                undo_runs: Number((j7.series.undo_runs?.[i]?.value) || 0),
                inserted: Number((j7.series.inserted?.[i]?.value) || 0),
              }))
            : [];
          payload[30] = (j30.series && Array.isArray(j30.series.import_runs))
            ? j30.series.import_runs.map((p, i) => ({
                date: p.date || '',
                import_runs: Number(p.value || 0),
                undo_runs: Number((j30.series.undo_runs?.[i]?.value) || 0),
                inserted: Number((j30.series.inserted?.[i]?.value) || 0),
              }))
            : [];
        }
      }

      if (kpiUrl) {
        const rk = responses[offset++];
        if (rk && rk.ok) {
          const jk = await rk.json();
          const s = jk.summary || {};
          const sp = s.sparklines || {};
          kpiState = {
            labels: Array.isArray(sp.labels) ? sp.labels : kpiState.labels,
            total: Array.isArray(sp.total) ? sp.total : kpiState.total,
            active: Array.isArray(sp.active) ? sp.active : kpiState.active,
            overdue: Array.isArray(sp.overdue) ? sp.overdue : kpiState.overdue,
            unpaid: Array.isArray(sp.unpaid) ? sp.unpaid : kpiState.unpaid,
          };
          updateKpiValue('total', s.total);
          updateKpiValue('active', s.active);
          updateKpiValue('overdue', s.overdue);
          updateKpiValue('unpaid', s.unpaid);
          renderKpi();
        }
      }

      render();
    } catch (_e) {
      // silent fallback to existing payload
    }
  }

  windowButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      activeWindow = Number(btn.dataset.window || 30);
      windowButtons.forEach((b) => b.classList.toggle('active', b === btn));
      render();
    });
  });

  legendButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const key = String(btn.dataset.series || '');
      if (!Object.prototype.hasOwnProperty.call(activeSeries, key)) return;
      activeSeries[key] = !activeSeries[key];
      btn.classList.toggle('off', !activeSeries[key]);
      render();
    });
  });

  renderKpi();
  render();
  setInterval(refreshFromServer, 30000);
})();
</script>
@endsection

