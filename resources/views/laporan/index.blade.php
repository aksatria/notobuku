@extends('layouts.notobuku')

@section('title', 'Laporan Operasional')

@section('content')
@php
  $filters = $filters ?? ['from' => now()->subDays(30)->toDateString(), 'to' => now()->toDateString(), 'branch_id' => 0];
  $kpi = $kpi ?? ['loans' => 0, 'returns' => 0, 'overdue' => 0, 'fines_assessed' => 0, 'fines_paid' => 0, 'purchase_orders' => 0, 'new_members' => 0, 'serial_open' => 0];
  $topTitles = $topTitles ?? [];
  $topOverdueMembers = $topOverdueMembers ?? [];
  $finesRows = $finesRows ?? [];
  $acquisitionRows = $acquisitionRows ?? [];
  $memberRows = $memberRows ?? [];
  $serialRows = $serialRows ?? [];
  $branches = $branches ?? [];
@endphp

<style>
  .page{ max-width:1180px; margin:0 auto; display:flex; flex-direction:column; gap:14px; }
  .card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; }
  .title{ margin:0; font-size:20px; font-weight:700; }
  .muted{ color:var(--nb-muted); font-size:13px; }
  .grid{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:12px; }
  .kpi{ border:1px solid var(--nb-border); border-radius:14px; padding:10px; }
  .kpi .label{ font-size:12px; color:var(--nb-muted); }
  .kpi .value{ margin-top:4px; font-size:20px; font-weight:700; }
  .filter{ display:grid; grid-template-columns:1fr 1fr 1fr auto auto; gap:10px; align-items:end; }
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
      <button class="btn btn-primary" type="submit">Terapkan</button>
      <a class="btn" href="{{ route('laporan.index') }}">Reset</a>
    </form>

    <div class="grid">
      <div class="kpi"><div class="label">Total Peminjaman</div><div class="value">{{ number_format((int) $kpi['loans']) }}</div></div>
      <div class="kpi"><div class="label">Total Pengembalian</div><div class="value">{{ number_format((int) $kpi['returns']) }}</div></div>
      <div class="kpi"><div class="label">Item Overdue Aktif</div><div class="value">{{ number_format((int) $kpi['overdue']) }}</div></div>
      <div class="kpi"><div class="label">PO Dibuat</div><div class="value">{{ number_format((int) $kpi['purchase_orders']) }}</div></div>
      <div class="kpi"><div class="label">Denda Tercatat</div><div class="value">Rp {{ number_format((float) $kpi['fines_assessed'], 0, ',', '.') }}</div></div>
      <div class="kpi"><div class="label">Denda Terbayar</div><div class="value">Rp {{ number_format((float) $kpi['fines_paid'], 0, ',', '.') }}</div></div>
      <div class="kpi"><div class="label">Anggota Baru (periode)</div><div class="value">{{ number_format((int) $kpi['new_members']) }}</div></div>
      <div class="kpi"><div class="label">Serial Open (expected/missing/claimed)</div><div class="value">{{ number_format((int) $kpi['serial_open']) }}</div></div>
    </div>
  </div>

  <div class="tables">
    <div class="card">
      <div class="head-row">
        <h2 class="title" style="font-size:16px;">Top Judul Dipinjam</h2>
        <div style="display:flex; gap:8px;">
          <a class="btn" href="{{ route('laporan.export', ['type' => 'sirkulasi', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">CSV</a>
          <a class="btn" href="{{ route('laporan.export_xlsx', ['type' => 'sirkulasi', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">XLSX</a>
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
          <a class="btn" href="{{ route('laporan.export', ['type' => 'overdue', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">CSV</a>
          <a class="btn" href="{{ route('laporan.export_xlsx', ['type' => 'overdue', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">XLSX</a>
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
          <a class="btn" href="{{ route('laporan.export', ['type' => 'denda', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">CSV</a>
          <a class="btn" href="{{ route('laporan.export_xlsx', ['type' => 'denda', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">XLSX</a>
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
          <a class="btn" href="{{ route('laporan.export', ['type' => 'pengadaan', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">CSV</a>
          <a class="btn" href="{{ route('laporan.export_xlsx', ['type' => 'pengadaan', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">XLSX</a>
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
          <a class="btn" href="{{ route('laporan.export', ['type' => 'anggota', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">CSV</a>
          <a class="btn" href="{{ route('laporan.export_xlsx', ['type' => 'anggota', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">XLSX</a>
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
          <a class="btn" href="{{ route('laporan.export', ['type' => 'serial', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">CSV</a>
          <a class="btn" href="{{ route('laporan.export_xlsx', ['type' => 'serial', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">XLSX</a>
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
