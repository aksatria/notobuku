@extends('layouts.notobuku')

@section('title', 'Serial Issue Control')

@section('content')
@php
  $issues = $issues ?? null;
  $tableReady = (bool) ($tableReady ?? true);
  $q = (string) ($q ?? '');
  $status = (string) ($status ?? '');
  $branchId = (int) ($branchId ?? 0);
  $from = (string) ($from ?? now()->subDays(30)->toDateString());
  $to = (string) ($to ?? now()->toDateString());
  $biblios = $biblios ?? collect();
  $branches = $branches ?? collect();
  $summary = $summary ?? ['total' => 0, 'expected' => 0, 'received' => 0, 'missing' => 0, 'claimed' => 0, 'late_expected' => 0];
@endphp

<style>
  .page{ max-width:1200px; margin:0 auto; display:flex; flex-direction:column; gap:14px; }
  .card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; }
  .title{ margin:0; font-size:20px; font-weight:700; }
  .muted{ color:var(--nb-muted); font-size:13px; }
  .grid{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:12px; }
  .field label{ display:block; font-size:12px; color:var(--nb-muted); font-weight:600; margin-bottom:6px; }
  .nb-field{ width:100%; border:1px solid var(--nb-border); border-radius:12px; background:var(--nb-surface); padding:10px 12px; }
  .btn{ display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--nb-border); border-radius:12px; padding:10px 12px; font-weight:600; }
  .btn-primary{ background:linear-gradient(90deg,#1e88e5,#1565c0); color:#fff; border-color:transparent; }
  .tag{ display:inline-flex; align-items:center; border:1px solid var(--nb-border); border-radius:999px; padding:3px 8px; font-size:11px; font-weight:700; }
  .tag.expected{ color:#0369a1; border-color:rgba(3,105,161,.35); background:rgba(56,189,248,.12); }
  .tag.received{ color:#0f766e; border-color:rgba(15,118,110,.35); background:rgba(20,184,166,.12); }
  .tag.missing{ color:#b91c1c; border-color:rgba(185,28,28,.35); background:rgba(239,68,68,.12); }
  .tag.claimed{ color:#92400e; border-color:rgba(146,64,14,.35); background:rgba(245,158,11,.12); }
  table{ width:100%; border-collapse:collapse; min-width:980px; }
  th, td{ border-bottom:1px solid var(--nb-border); padding:10px 8px; font-size:13px; text-align:left; vertical-align:middle; }
  th{ color:var(--nb-muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
  .actions{ display:flex; gap:8px; }
  .kpi-grid{ display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:10px; margin-top:12px; }
  .kpi{ border:1px solid var(--nb-border); border-radius:12px; padding:10px; }
  .kpi .label{ color:var(--nb-muted); font-size:12px; }
  .kpi .value{ margin-top:6px; font-size:20px; font-weight:700; }
  @media (max-width:1100px){ .grid{ grid-template-columns:1fr; } }
  @media (max-width:1100px){ .kpi-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
</style>

<div class="page">
  <div class="card">
    <h1 class="title">Serial Issue Control</h1>
    <div class="muted">Kelola issue serial: expected, received, dan missing.</div>

    <form method="POST" action="{{ route('serial_issues.store') }}">
      @csrf
      <div class="grid">
        <div class="field">
          <label>Judul Serial</label>
          <select class="nb-field" name="biblio_id" required>
            <option value="">Pilih judul</option>
            @foreach($biblios as $b)
              <option value="{{ $b->id }}">{{ $b->title }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label>Kode Issue</label>
          <input class="nb-field" name="issue_code" placeholder="2026-Vol.1-No.1" required>
        </div>
        <div class="field">
          <label>Volume</label>
          <input class="nb-field" name="volume" placeholder="Vol. 1">
        </div>
        <div class="field">
          <label>Nomor</label>
          <input class="nb-field" name="issue_no" placeholder="No. 1">
        </div>
        <div class="field">
          <label>Cabang</label>
          <select class="nb-field" name="branch_id">
            <option value="">Semua/Umum</option>
            @foreach($branches as $b)
              <option value="{{ $b->id }}">{{ $b->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="field">
          <label>Tgl Terbit</label>
          <input class="nb-field" type="date" name="published_on">
        </div>
        <div class="field">
          <label>Tgl Expected</label>
          <input class="nb-field" type="date" name="expected_on">
        </div>
        <div class="field">
          <label>Catatan</label>
          <input class="nb-field" name="notes" placeholder="Catatan penerimaan">
        </div>
      </div>
      <div style="margin-top:12px;">
        <button class="btn btn-primary" type="submit">Tambah Issue</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:10px;">
      <div class="muted">Ringkasan periode {{ \Illuminate\Support\Carbon::parse($from)->format('d M Y') }} - {{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}</div>
      <div style="display:flex; gap:8px;">
        <a class="btn" href="{{ route('serial_issues.export.csv', ['q' => $q, 'status' => $status, 'branch_id' => $branchId, 'from' => $from, 'to' => $to]) }}">Export CSV</a>
        <a class="btn" href="{{ route('serial_issues.export.xlsx', ['q' => $q, 'status' => $status, 'branch_id' => $branchId, 'from' => $from, 'to' => $to]) }}">Export XLSX</a>
      </div>
    </div>
    <div class="kpi-grid">
      <div class="kpi"><div class="label">Total Issue</div><div class="value">{{ number_format((int) $summary['total']) }}</div></div>
      <div class="kpi"><div class="label">Expected</div><div class="value">{{ number_format((int) $summary['expected']) }}</div></div>
      <div class="kpi"><div class="label">Received</div><div class="value">{{ number_format((int) $summary['received']) }}</div></div>
      <div class="kpi"><div class="label">Missing</div><div class="value">{{ number_format((int) $summary['missing']) }}</div></div>
      <div class="kpi"><div class="label">Claimed</div><div class="value">{{ number_format((int) $summary['claimed']) }}</div></div>
      <div class="kpi"><div class="label">Expected Terlambat</div><div class="value">{{ number_format((int) $summary['late_expected']) }}</div></div>
    </div>
  </div>

  <div class="card">
    <form method="GET" action="{{ route('serial_issues.index') }}" class="grid" style="align-items:end;">
      <div class="field">
        <label>Cari</label>
        <input class="nb-field" name="q" value="{{ $q }}" placeholder="Issue code / judul">
      </div>
      <div class="field">
        <label>Status</label>
        <select class="nb-field" name="status">
          <option value="">Semua status</option>
          <option value="expected" @selected($status === 'expected')>Expected</option>
          <option value="received" @selected($status === 'received')>Received</option>
          <option value="missing" @selected($status === 'missing')>Missing</option>
          <option value="claimed" @selected($status === 'claimed')>Claimed</option>
        </select>
      </div>
      <div class="field">
        <label>Cabang</label>
        <select class="nb-field" name="branch_id">
          <option value="0">Semua cabang</option>
          @foreach($branches as $b)
            <option value="{{ $b->id }}" @selected($branchId === (int) $b->id)>{{ $b->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>Dari Tanggal</label>
        <input class="nb-field" type="date" name="from" value="{{ $from }}">
      </div>
      <div class="field">
        <label>Sampai Tanggal</label>
        <input class="nb-field" type="date" name="to" value="{{ $to }}">
      </div>
      <div style="display:flex; gap:8px;">
        <button class="btn btn-primary" type="submit">Filter</button>
        <a class="btn" href="{{ route('serial_issues.index') }}">Reset</a>
      </div>
    </form>
  </div>

  <div class="card">
    @if(!$tableReady)
      <div class="muted" style="margin-bottom:10px;">
        Tabel <code>serial_issues</code> belum tersedia. Jalankan <code>php artisan migrate</code> lalu refresh halaman ini.
      </div>
    @endif

    @if(!$issues || $issues->count() === 0)
      <div class="muted">Belum ada data serial issue.</div>
    @else
      <div style="overflow:auto;">
        <table>
          <thead>
            <tr>
              <th>Issue</th>
              <th>Judul</th>
              <th>Volume/No</th>
              <th>Tgl Terbit</th>
              <th>Expected</th>
              <th>Status</th>
              <th>Klaim</th>
              <th>Cabang</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            @foreach($issues as $it)
              <tr>
                <td>{{ $it->issue_code }}</td>
                <td>{{ $it->biblio->title ?? '-' }}</td>
                <td>{{ trim(($it->volume ?: '-') . ' / ' . ($it->issue_no ?: '-')) }}</td>
                <td>{{ $it->published_on ? \Illuminate\Support\Carbon::parse($it->published_on)->format('d M Y') : '-' }}</td>
                <td>{{ $it->expected_on ? \Illuminate\Support\Carbon::parse($it->expected_on)->format('d M Y') : '-' }}</td>
                <td><span class="tag {{ $it->status }}">{{ strtoupper($it->status) }}</span></td>
                <td>
                  @if(!empty($it->claim_reference))
                    <div>{{ $it->claim_reference }}</div>
                  @endif
                  @if(!empty($it->claim_notes))
                    <div class="muted">{{ \Illuminate\Support\Str::limit($it->claim_notes, 80) }}</div>
                  @elseif($it->status === 'claimed')
                    <div class="muted">Claimed tanpa catatan</div>
                  @else
                    -
                  @endif
                </td>
                <td>{{ $it->branch->name ?? '-' }}</td>
                <td>
                  <div class="actions">
                    @if($it->status !== 'received')
                      <form method="POST" action="{{ route('serial_issues.receive', $it->id) }}">
                        @csrf
                        <button class="btn" type="submit">Terima</button>
                      </form>
                    @endif
                    @if($it->status !== 'missing')
                      <form method="POST" action="{{ route('serial_issues.missing', $it->id) }}">
                        @csrf
                        <button class="btn" type="submit">Missing</button>
                      </form>
                    @endif
                    @if($it->status !== 'received')
                      <form method="POST" action="{{ route('serial_issues.claim', $it->id) }}" style="display:flex; gap:6px; align-items:center;">
                        @csrf
                        <input class="nb-field" name="claim_reference" placeholder="Ref klaim" style="min-width:120px; padding:7px 8px;">
                        <input class="nb-field" name="claim_notes" placeholder="Catatan klaim" style="min-width:140px; padding:7px 8px;">
                        <button class="btn" type="submit">Claim</button>
                      </form>
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if(method_exists($issues, 'links'))
        <div style="margin-top:10px;">{{ $issues->links() }}</div>
      @endif
    @endif
  </div>
</div>
@endsection
