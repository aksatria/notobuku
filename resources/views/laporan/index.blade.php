@extends('layouts.notobuku')

@section('title', 'Laporan Operasional')

@section('content')
@php
  $filters = $filters ?? ['from' => now()->subDays(30)->toDateString(), 'to' => now()->toDateString(), 'branch_id' => 0, 'operator_id' => 0, 'loan_status' => ''];
  $kpi = $kpi ?? ['loans' => 0, 'returns' => 0, 'overdue' => 0, 'fines_assessed' => 0, 'fines_paid' => 0, 'purchase_orders' => 0, 'new_members' => 0, 'serial_open' => 0];
  $topTitles = $topTitles ?? [];
  $topOverdueMembers = $topOverdueMembers ?? [];
  $finesRows = $finesRows ?? [];
  $acquisitionRows = $acquisitionRows ?? [];
  $memberRows = $memberRows ?? [];
  $serialRows = $serialRows ?? [];
  $circulationAuditRows = $circulationAuditRows ?? [];
  $branches = $branches ?? [];
  $operators = $operators ?? [];
@endphp

<style>
  .page{ max-width:1180px; margin:0 auto; display:flex; flex-direction:column; gap:14px; }
  .card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; }
  .title{ margin:0; font-size:20px; font-weight:700; }
  .muted{ color:var(--nb-muted); font-size:13px; }
  .grid{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:12px; }
  .kpi{ border:1px solid var(--nb-border); border-radius:14px; padding:10px; }
  .kpi.var-loans{ background:rgba(59,130,246,.10); }
  .kpi.var-returns{ background:rgba(16,185,129,.10); }
  .kpi.var-overdue{ background:rgba(239,68,68,.10); }
  .kpi.var-po{ background:rgba(99,102,241,.10); }
  .kpi.var-fines-a{ background:rgba(245,158,11,.12); }
  .kpi.var-fines-p{ background:rgba(20,184,166,.12); }
  .kpi.var-members{ background:rgba(168,85,247,.12); }
  .kpi.var-serial{ background:rgba(148,163,184,.16); }
  .kpi .label{ font-size:12px; color:var(--nb-muted); }
  .kpi .value{ margin-top:4px; font-size:20px; font-weight:700; }
  .filter{ display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:10px; align-items:end; }
  .field label{ display:block; font-size:12px; font-weight:600; color:var(--nb-muted); margin-bottom:6px; }
  .nb-field{ width:100%; border:1px solid var(--nb-border); border-radius:12px; padding:10px 12px; background:var(--nb-surface); }
  .btn{ display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--nb-border); border-radius:12px; padding:10px 12px; font-weight:600; }
  .btn-primary{ background:linear-gradient(90deg,#1e88e5,#1565c0); color:#fff; border-color:transparent; }
  .tables{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
  table{ width:100%; border-collapse:collapse; }
  th, td{ border-bottom:1px solid var(--nb-border); padding:10px 8px; text-align:left; font-size:13px; }
  th{ color:var(--nb-muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
  .head-row{ display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
  @media (max-width:1100px){ .grid{ grid-template-columns:repeat(2,minmax(0,1fr)); } .tables{ grid-template-columns:1fr; } .filter{ grid-template-columns:1fr; } }
</style>

<div class="page">
  <div class="card">
    <h1 class="title">Laporan Operasional</h1>
    <div class="muted">Monitoring cepat sirkulasi, keterlambatan, denda, dan pengadaan.</div>

    <form method="GET" action="{{ route('laporan.index') }}" class="filter" style="margin-top:12px;">
      <div class="field">
        <label>Dari Tanggal</label>
        <input class="nb-field" type="date" name="from" value="{{ $filters['from'] }}">
      </div>
      <div class="field">
        <label>Sampai Tanggal</label>
        <input class="nb-field" type="date" name="to" value="{{ $filters['to'] }}">
      </div>
      <div class="field">
        <label>Cabang</label>
        <select class="nb-field" name="branch_id">
          <option value="0">Semua cabang</option>
          @foreach($branches as $b)
            <option value="{{ $b['id'] }}" @selected((int) $filters['branch_id'] === (int) $b['id'])>{{ $b['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>Operator</label>
        <select class="nb-field" name="operator_id">
          <option value="0">Semua operator</option>
          @foreach($operators as $op)
            <option value="{{ $op['id'] }}" @selected((int) ($filters['operator_id'] ?? 0) === (int) $op['id'])>{{ $op['name'] }} ({{ strtoupper($op['role']) }})</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label>Status Loan</label>
        <select class="nb-field" name="loan_status">
          <option value="">Semua status</option>
          <option value="open" @selected(($filters['loan_status'] ?? '') === 'open')>TERBUKA</option>
          <option value="overdue" @selected(($filters['loan_status'] ?? '') === 'overdue')>TERLAMBAT</option>
          <option value="closed" @selected(($filters['loan_status'] ?? '') === 'closed')>SELESAI</option>
        </select>
      </div>
      <button class="btn btn-primary" type="submit">Terapkan</button>
      <a class="btn" href="{{ route('laporan.index') }}">Reset</a>
    </form>

    <div class="grid">
      <div class="kpi var-loans"><div class="label">Total peminjaman</div><div class="value">{{ number_format((int) $kpi['loans']) }}</div></div>
      <div class="kpi var-returns"><div class="label">Total pengembalian</div><div class="value">{{ number_format((int) $kpi['returns']) }}</div></div>
      <div class="kpi var-overdue"><div class="label">Item terlambat aktif</div><div class="value">{{ number_format((int) $kpi['overdue']) }}</div></div>
      <div class="kpi var-po"><div class="label">PO dibuat</div><div class="value">{{ number_format((int) $kpi['purchase_orders']) }}</div></div>
      <div class="kpi var-fines-a"><div class="label">Denda tercatat</div><div class="value">Rp {{ number_format((float) $kpi['fines_assessed'], 0, ',', '.') }}</div></div>
      <div class="kpi var-fines-p"><div class="label">Denda terbayar</div><div class="value">Rp {{ number_format((float) $kpi['fines_paid'], 0, ',', '.') }}</div></div>
      <div class="kpi var-members"><div class="label">Anggota baru (periode)</div><div class="value">{{ number_format((int) $kpi['new_members']) }}</div></div>
      <div class="kpi var-serial"><div class="label">Serial terbuka (terjadwal/hilang/klaim)</div><div class="value">{{ number_format((int) $kpi['serial_open']) }}</div></div>
    </div>
  </div>

  <div class="tables">
    <div class="card" style="grid-column:1 / -1;">
      <div class="head-row">
        <h2 class="title" style="font-size:16px;">Audit Sirkulasi Detail</h2>
        <div style="display:flex; gap:8px;">
          <a class="btn" href="{{ route('laporan.export', ['type' => 'sirkulasi_audit', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id'], 'operator_id' => $filters['operator_id'] ?? 0, 'loan_status' => $filters['loan_status'] ?? '']) }}">CSV</a>
          <a class="btn" href="{{ route('laporan.export_xlsx', ['type' => 'sirkulasi_audit', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id'], 'operator_id' => $filters['operator_id'] ?? 0, 'loan_status' => $filters['loan_status'] ?? '']) }}">XLSX</a>
        </div>
      </div>
      <table>
        <thead>
          <tr>
            <th>Peminjaman</th>
            <th>Anggota</th>
            <th>Item</th>
            <th>Tanggal</th>
            <th>Terlambat</th>
            <th>Cabang</th>
            <th>Operator</th>
          </tr>
        </thead>
        <tbody>
          @forelse($circulationAuditRows as $row)
            <tr>
              @php
                $loanStatusLabel = match(strtolower((string) $row['loan_status'])){
                  'open' => 'TERBUKA',
                  'overdue' => 'TERLAMBAT',
                  'closed' => 'SELESAI',
                  default => strtoupper((string) $row['loan_status']),
                };
              @endphp
              <td>{{ $row['loan_code'] }}<br><span class="muted">{{ $loanStatusLabel }}</span></td>
              <td>{{ $row['member_name'] }}<br><span class="muted">{{ $row['member_code'] }}</span></td>
              <td>{{ $row['title'] }}<br><span class="muted">{{ $row['barcode'] }}</span></td>
              <td>
                <div class="muted">Pinjam: {{ $row['borrowed_at'] !== '' ? \Illuminate\Support\Carbon::parse($row['borrowed_at'])->format('d M Y H:i') : '-' }}</div>
                <div class="muted">Tempo: {{ $row['due_at'] !== '' ? \Illuminate\Support\Carbon::parse($row['due_at'])->format('d M Y H:i') : '-' }}</div>
                <div class="muted">Kembali: {{ $row['returned_at'] !== '' ? \Illuminate\Support\Carbon::parse($row['returned_at'])->format('d M Y H:i') : '-' }}</div>
              </td>
              <td>{{ number_format((int) $row['late_days']) }} hari</td>
              <td>{{ $row['branch_name'] }}</td>
              <td>{{ $row['operator_name'] }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="muted">Tidak ada data audit sirkulasi untuk filter ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="head-row">
        <h2 class="title" style="font-size:16px;">Top Judul Dipinjam</h2>
        <div style="display:flex; gap:8px;">
          <a class="btn" href="{{ route('laporan.export', ['type' => 'sirkulasi', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor CSV</a>
          <a class="btn" href="{{ route('laporan.export_xlsx', ['type' => 'sirkulasi', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor XLSX</a>
        </div>
      </div>
      <table>
        <thead><tr><th>Judul</th><th>Dipinjam</th></tr></thead>
        <tbody>
          @forelse($topTitles as $row)
            <tr><td>{{ $row['title'] }}</td><td>{{ number_format($row['borrowed']) }}</td></tr>
          @empty
            <tr><td colspan="2" class="muted">Tidak ada data</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="head-row">
        <h2 class="title" style="font-size:16px;">Top Member Overdue</h2>
        <div style="display:flex; gap:8px;">
          <a class="btn" href="{{ route('laporan.export', ['type' => 'overdue', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor CSV</a>
          <a class="btn" href="{{ route('laporan.export_xlsx', ['type' => 'overdue', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor XLSX</a>
        </div>
      </div>
      <table>
        <thead><tr><th>Kode</th><th>Nama</th><th>Overdue</th></tr></thead>
        <tbody>
          @forelse($topOverdueMembers as $row)
            <tr><td>{{ $row['member_code'] }}</td><td>{{ $row['full_name'] }}</td><td>{{ number_format($row['overdue_items']) }}</td></tr>
          @empty
            <tr><td colspan="3" class="muted">Tidak ada data</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="head-row">
        <h2 class="title" style="font-size:16px;">Ringkasan Denda</h2>
        <div style="display:flex; gap:8px;">
          <a class="btn" href="{{ route('laporan.export', ['type' => 'denda', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor CSV</a>
          <a class="btn" href="{{ route('laporan.export_xlsx', ['type' => 'denda', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor XLSX</a>
        </div>
      </div>
      <table>
        <thead><tr><th>Kode</th><th>Nama</th><th>Status</th><th>Jumlah</th></tr></thead>
        <tbody>
          @forelse($finesRows as $row)
            <tr>
              <td>{{ $row['member_code'] }}</td>
              <td>{{ $row['full_name'] }}</td>
              <td>{{ strtoupper($row['status']) }}</td>
              <td>Rp {{ number_format((float) $row['amount'], 0, ',', '.') }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="muted">Tidak ada data</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="head-row">
        <h2 class="title" style="font-size:16px;">Ringkasan Pengadaan</h2>
        <div style="display:flex; gap:8px;">
          <a class="btn" href="{{ route('laporan.export', ['type' => 'pengadaan', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor CSV</a>
          <a class="btn" href="{{ route('laporan.export_xlsx', ['type' => 'pengadaan', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor XLSX</a>
        </div>
      </div>
      <table>
        <thead><tr><th>PO</th><th>Vendor</th><th>Status</th><th>Total</th></tr></thead>
        <tbody>
          @forelse($acquisitionRows as $row)
            <tr>
              <td>{{ $row['po_number'] }}</td>
              <td>{{ $row['vendor_name'] }}</td>
              <td>{{ strtoupper($row['status']) }}</td>
              <td>Rp {{ number_format((float) $row['total_amount'], 0, ',', '.') }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="muted">Tidak ada data</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="head-row">
        <h2 class="title" style="font-size:16px;">Ringkasan Anggota</h2>
        <div style="display:flex; gap:8px;">
          <a class="btn" href="{{ route('laporan.export', ['type' => 'anggota', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor CSV</a>
          <a class="btn" href="{{ route('laporan.export_xlsx', ['type' => 'anggota', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor XLSX</a>
        </div>
      </div>
      <table>
        <thead><tr><th>Kode</th><th>Nama</th><th>Status</th><th>Pinjaman Aktif</th><th>Overdue</th><th>Denda Belum Lunas</th></tr></thead>
        <tbody>
          @forelse($memberRows as $row)
            <tr>
              <td>{{ $row['member_code'] }}</td>
              <td>{{ $row['full_name'] }}</td>
              <td>{{ strtoupper($row['status']) }}</td>
              <td>{{ number_format((int) $row['active_loans']) }}</td>
              <td>{{ number_format((int) $row['overdue_items']) }}</td>
              <td>Rp {{ number_format((float) $row['unpaid_fines'], 0, ',', '.') }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="muted">Tidak ada data</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="head-row">
        <h2 class="title" style="font-size:16px;">Ringkasan Serial</h2>
        <div style="display:flex; gap:8px;">
          <a class="btn" href="{{ route('laporan.export', ['type' => 'serial', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor CSV</a>
          <a class="btn" href="{{ route('laporan.export_xlsx', ['type' => 'serial', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor XLSX</a>
        </div>
      </div>
      <table>
        <thead><tr><th>Issue</th><th>Judul</th><th>Status</th><th>Expected</th><th>Received</th><th>Cabang</th></tr></thead>
        <tbody>
          @forelse($serialRows as $row)
            <tr>
              <td>{{ $row['issue_code'] }}</td>
              <td>{{ $row['title'] }}</td>
              <td>{{ strtoupper($row['status']) }}</td>
              <td>{{ $row['expected_on'] !== '' ? \Illuminate\Support\Carbon::parse($row['expected_on'])->format('d M Y') : '-' }}</td>
              <td>{{ $row['received_at'] !== '' ? \Illuminate\Support\Carbon::parse($row['received_at'])->format('d M Y') : '-' }}</td>
              <td>{{ $row['branch_name'] }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="muted">Tidak ada data</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
