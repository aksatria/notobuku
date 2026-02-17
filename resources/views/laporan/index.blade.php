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
  .rp-page{ display:flex; flex-direction:column; gap:14px; }
  .rp-card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:14px; }
  .rp-card.tone-audit{ border-top:4px solid #1e88e5; }
  .rp-card.tone-sirkulasi{ border-top:4px solid #3b82f6; }
  .rp-card.tone-overdue{ border-top:4px solid #ef4444; }
  .rp-card.tone-denda{ border-top:4px solid #f59e0b; }
  .rp-card.tone-pengadaan{ border-top:4px solid #6366f1; }
  .rp-card.tone-anggota{ border-top:4px solid #a855f7; }
  .rp-card.tone-serial{ border-top:4px solid #0ea5a4; }
  .rp-title{ margin:0; font-size:20px; font-weight:800; color:#0b2545; }
  .rp-muted{ color:var(--nb-muted); font-size:13px; }
  .rp-top{ display:flex; flex-direction:column; gap:12px; }
  .rp-tools{ display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
  .rp-legend{ display:flex; gap:8px; flex-wrap:wrap; }
  .rp-legend-chip{
    display:inline-flex;
    align-items:center;
    gap:6px;
    border:1px solid var(--nb-border);
    border-radius:999px;
    padding:6px 10px;
    font-size:11px;
    font-weight:600;
    background:#fff;
    color:#334155;
  }
  .rp-dot{ width:8px; height:8px; border-radius:999px; display:inline-block; }
  .rp-dot.blue{ background:#2563eb; }
  .rp-dot.green{ background:#16a34a; }
  .rp-dot.red{ background:#dc2626; }
  .rp-dot.indigo{ background:#4f46e5; }
  .rp-dot.amber{ background:#d97706; }
  .rp-dot.teal{ background:#0d9488; }
  .rp-dot.purple{ background:#9333ea; }
  .rp-dot.slate{ background:#475569; }
  .rp-density{ display:flex; align-items:center; gap:6px; }
  .rp-density-btn{
    border:1px solid var(--nb-border);
    border-radius:999px;
    background:#fff;
    color:#334155;
    font-size:11px;
    font-weight:700;
    padding:6px 10px;
    line-height:1;
    cursor:pointer;
  }
  .rp-density-btn.is-active{ background:#eff6ff; border-color:#93c5fd; color:#1e3a8a; }
  .rp-filter{
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr)) auto auto;
    gap:10px;
    align-items:end;
    padding:12px;
    border:1px solid var(--nb-border);
    border-radius:14px;
    background:#f8fbff;
  }
  .rp-field label{ display:block; font-size:12px; font-weight:700; color:var(--nb-muted); margin-bottom:6px; }
  .rp-input{ width:100%; border:1px solid var(--nb-border); border-radius:12px; padding:10px 12px; background:var(--nb-surface); min-height:42px; }
  .rp-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:1px solid var(--nb-border);
    border-radius:12px;
    padding:0 14px;
    font-size:13px;
    font-weight:700;
    line-height:1;
    height:42px;
    min-height:42px;
    white-space:nowrap;
  }
  .rp-btn.primary{ background:linear-gradient(90deg,#1e88e5,#1565c0); color:#fff; border-color:transparent; }
  .rp-kpi-grid{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
  .rp-kpi{
    border:0;
    border-radius:16px;
    padding:14px;
    color:#fff;
    box-shadow:0 10px 22px rgba(15,23,42,.16);
  }
  .rp-kpi .label{ font-size:12px; color:rgba(255,255,255,.94); font-weight:600; }
  .rp-kpi .value{ margin-top:6px; font-size:24px; font-weight:700; color:#fff; letter-spacing:.01em; }
  .rp-kpi.var-loans{ background:linear-gradient(135deg,rgba(59,130,246,1),rgba(37,99,235,1)); }
  .rp-kpi.var-returns{ background:linear-gradient(135deg,rgba(34,197,94,1),rgba(22,163,74,1)); }
  .rp-kpi.var-overdue{ background:linear-gradient(135deg,rgba(239,68,68,1),rgba(220,38,38,1)); }
  .rp-kpi.var-po{ background:linear-gradient(135deg,rgba(99,102,241,1),rgba(79,70,229,1)); }
  .rp-kpi.var-fines-a{ background:linear-gradient(135deg,rgba(245,158,11,1),rgba(217,119,6,1)); }
  .rp-kpi.var-fines-p{ background:linear-gradient(135deg,rgba(20,184,166,1),rgba(13,148,136,1)); }
  .rp-kpi.var-members{ background:linear-gradient(135deg,rgba(168,85,247,1),rgba(147,51,234,1)); }
  .rp-kpi.var-serial{ background:linear-gradient(135deg,rgba(100,116,139,1),rgba(71,85,105,1)); }
  .rp-tables{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
  .rp-head-row{ display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
  .rp-subtitle{ margin:0; font-size:16px; font-weight:800; color:#0b2545; }
  .rp-actions{ display:flex; gap:8px; flex-wrap:wrap; }
  .rp-table-wrap{ border:1px solid var(--nb-border); border-radius:12px; overflow:hidden; background:#fff; }
  .rp-table{ width:100%; border-collapse:collapse; }
  .rp-table th, .rp-table td{ border-bottom:1px solid var(--nb-border); padding:10px 8px; text-align:left; font-size:13px; vertical-align:top; }
  .rp-table th{ color:var(--nb-muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; font-weight:800; background:#f8fafc; }
  .rp-table tr:last-child td{ border-bottom:0; }
  .rp-table tbody tr:nth-child(even){ background:#fcfdff; }
  .rp-page.compact .rp-table th,
  .rp-page.compact .rp-table td{ padding:7px 8px; font-size:12px; }
  .rp-page.compact .rp-muted{ font-size:12px; }
  .rp-empty{ color:var(--nb-muted); font-size:13px; text-align:center; padding:16px 8px; }
  .rp-badge{
    display:inline-flex;
    align-items:center;
    padding:4px 10px;
    border-radius:999px;
    border:1px solid;
    font-size:11px;
    font-weight:800;
    line-height:1;
  }
  .rp-badge.blue{ color:#1e3a8a; border-color:#93c5fd; background:#eff6ff; }
  .rp-badge.green{ color:#065f46; border-color:#86efac; background:#ecfdf5; }
  .rp-badge.red{ color:#991b1b; border-color:#fca5a5; background:#fef2f2; }
  .rp-badge.amber{ color:#92400e; border-color:#fcd34d; background:#fffbeb; }
  .rp-badge.purple{ color:#6b21a8; border-color:#d8b4fe; background:#faf5ff; }
  .rp-badge.gray{ color:#334155; border-color:#cbd5e1; background:#f8fafc; }
  @media (max-width:1280px){ .rp-filter{ grid-template-columns:repeat(3,minmax(0,1fr)); } }
  @media (max-width:1100px){ .rp-kpi-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); } .rp-tables{ grid-template-columns:1fr; } }
  @media (max-width:720px){ .rp-filter{ grid-template-columns:1fr; } }
</style>

<div class="rp-page">
  <div class="rp-card rp-top">
    <div>
      <h1 class="rp-title">Laporan Operasional</h1>
      <div class="rp-muted">Monitoring sirkulasi, keterlambatan, denda, pengadaan, anggota, dan serial dalam satu halaman.</div>
    </div>

    <form method="GET" action="{{ route('laporan.index') }}" class="rp-filter">
      <div class="rp-field">
        <label>Dari Tanggal</label>
        <input class="rp-input" type="date" name="from" value="{{ $filters['from'] }}">
      </div>
      <div class="rp-field">
        <label>Sampai Tanggal</label>
        <input class="rp-input" type="date" name="to" value="{{ $filters['to'] }}">
      </div>
      <div class="rp-field">
        <label>Cabang</label>
        <select class="rp-input" name="branch_id">
          <option value="0">Semua cabang</option>
          @foreach($branches as $b)
            <option value="{{ $b['id'] }}" @selected((int) $filters['branch_id'] === (int) $b['id'])>{{ $b['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="rp-field">
        <label>Operator</label>
        <select class="rp-input" name="operator_id">
          <option value="0">Semua operator</option>
          @foreach($operators as $op)
            <option value="{{ $op['id'] }}" @selected((int) ($filters['operator_id'] ?? 0) === (int) $op['id'])>{{ $op['name'] }} ({{ strtoupper($op['role']) }})</option>
          @endforeach
        </select>
      </div>
      <div class="rp-field">
        <label>Status Loan</label>
        <select class="rp-input" name="loan_status">
          <option value="">Semua status</option>
          <option value="open" @selected(($filters['loan_status'] ?? '') === 'open')>TERBUKA</option>
          <option value="overdue" @selected(($filters['loan_status'] ?? '') === 'overdue')>TERLAMBAT</option>
          <option value="closed" @selected(($filters['loan_status'] ?? '') === 'closed')>SELESAI</option>
        </select>
      </div>
      <button class="rp-btn primary" type="submit">Terapkan</button>
      <a class="rp-btn" href="{{ route('laporan.index') }}">Reset</a>
    </form>

    <div class="rp-tools">
      <div class="rp-legend">
        <span class="rp-legend-chip"><span class="rp-dot blue"></span>Peminjaman</span>
        <span class="rp-legend-chip"><span class="rp-dot green"></span>Pengembalian</span>
        <span class="rp-legend-chip"><span class="rp-dot red"></span>Terlambat</span>
        <span class="rp-legend-chip"><span class="rp-dot indigo"></span>Pengadaan</span>
        <span class="rp-legend-chip"><span class="rp-dot amber"></span>Denda</span>
        <span class="rp-legend-chip"><span class="rp-dot purple"></span>Anggota</span>
        <span class="rp-legend-chip"><span class="rp-dot slate"></span>Serial</span>
      </div>
      <div class="rp-density">
        <button type="button" class="rp-density-btn is-active" data-density="normal">Normal</button>
        <button type="button" class="rp-density-btn" data-density="compact">Compact</button>
      </div>
    </div>

    <div class="rp-kpi-grid">
      <div class="rp-kpi var-loans"><div class="label">Total peminjaman</div><div class="value">{{ number_format((int) $kpi['loans']) }}</div></div>
      <div class="rp-kpi var-returns"><div class="label">Total pengembalian</div><div class="value">{{ number_format((int) $kpi['returns']) }}</div></div>
      <div class="rp-kpi var-overdue"><div class="label">Item terlambat aktif</div><div class="value">{{ number_format((int) $kpi['overdue']) }}</div></div>
      <div class="rp-kpi var-po"><div class="label">PO dibuat</div><div class="value">{{ number_format((int) $kpi['purchase_orders']) }}</div></div>
      <div class="rp-kpi var-fines-a"><div class="label">Denda tercatat</div><div class="value">Rp {{ number_format((float) $kpi['fines_assessed'], 0, ',', '.') }}</div></div>
      <div class="rp-kpi var-fines-p"><div class="label">Denda terbayar</div><div class="value">Rp {{ number_format((float) $kpi['fines_paid'], 0, ',', '.') }}</div></div>
      <div class="rp-kpi var-members"><div class="label">Anggota baru (periode)</div><div class="value">{{ number_format((int) $kpi['new_members']) }}</div></div>
      <div class="rp-kpi var-serial"><div class="label">Serial terbuka (terjadwal/hilang/klaim)</div><div class="value">{{ number_format((int) $kpi['serial_open']) }}</div></div>
    </div>
  </div>

  <div class="rp-tables">
    <div class="rp-card tone-audit" style="grid-column:1 / -1;">
      <div class="rp-head-row">
        <h2 class="rp-subtitle">Audit Sirkulasi Detail</h2>
        <div class="rp-actions">
          <a class="rp-btn" href="{{ route('laporan.export', ['type' => 'sirkulasi_audit', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id'], 'operator_id' => $filters['operator_id'] ?? 0, 'loan_status' => $filters['loan_status'] ?? '']) }}">CSV</a>
          <a class="rp-btn" href="{{ route('laporan.export_xlsx', ['type' => 'sirkulasi_audit', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id'], 'operator_id' => $filters['operator_id'] ?? 0, 'loan_status' => $filters['loan_status'] ?? '']) }}">XLSX</a>
        </div>
      </div>
      <div class="rp-table-wrap">
      <table class="rp-table">
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
              @php
                $loanStatusTone = match(strtolower((string) $row['loan_status'])) {
                  'open' => 'blue',
                  'overdue' => 'red',
                  'closed' => 'green',
                  default => 'gray',
                };
              @endphp
              <td>{{ $row['loan_code'] }}<br><span class="rp-badge {{ $loanStatusTone }}">{{ $loanStatusLabel }}</span></td>
              <td>{{ $row['member_name'] }}<br><span class="rp-muted">{{ $row['member_code'] }}</span></td>
              <td>{{ $row['title'] }}<br><span class="rp-muted">{{ $row['barcode'] }}</span></td>
              <td>
                <div class="rp-muted">Pinjam: {{ $row['borrowed_at'] !== '' ? \Illuminate\Support\Carbon::parse($row['borrowed_at'])->format('d M Y H:i') : '-' }}</div>
                <div class="rp-muted">Tempo: {{ $row['due_at'] !== '' ? \Illuminate\Support\Carbon::parse($row['due_at'])->format('d M Y H:i') : '-' }}</div>
                <div class="rp-muted">Kembali: {{ $row['returned_at'] !== '' ? \Illuminate\Support\Carbon::parse($row['returned_at'])->format('d M Y H:i') : '-' }}</div>
              </td>
              <td>
                @php $lateDays = (int) $row['late_days']; @endphp
                <span class="rp-badge {{ $lateDays > 0 ? 'red' : 'green' }}">{{ number_format($lateDays) }} hari</span>
              </td>
              <td>{{ $row['branch_name'] }}</td>
              <td>{{ $row['operator_name'] }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="rp-empty">Tidak ada data audit sirkulasi untuk filter ini.</td></tr>
          @endforelse
        </tbody>
      </table>
      </div>
    </div>

    <div class="rp-card tone-sirkulasi">
      <div class="rp-head-row">
        <h2 class="rp-subtitle">Top Judul Dipinjam</h2>
        <div class="rp-actions">
          <a class="rp-btn" href="{{ route('laporan.export', ['type' => 'sirkulasi', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor CSV</a>
          <a class="rp-btn" href="{{ route('laporan.export_xlsx', ['type' => 'sirkulasi', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor XLSX</a>
        </div>
      </div>
      <div class="rp-table-wrap">
      <table class="rp-table">
        <thead><tr><th>Judul</th><th>Dipinjam</th></tr></thead>
        <tbody>
          @forelse($topTitles as $row)
            <tr><td>{{ $row['title'] }}</td><td>{{ number_format($row['borrowed']) }}</td></tr>
          @empty
            <tr><td colspan="2" class="rp-empty">Tidak ada data</td></tr>
          @endforelse
        </tbody>
      </table>
      </div>
    </div>

    <div class="rp-card tone-overdue">
      <div class="rp-head-row">
        <h2 class="rp-subtitle">Top Member Overdue</h2>
        <div class="rp-actions">
          <a class="rp-btn" href="{{ route('laporan.export', ['type' => 'overdue', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor CSV</a>
          <a class="rp-btn" href="{{ route('laporan.export_xlsx', ['type' => 'overdue', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor XLSX</a>
        </div>
      </div>
      <div class="rp-table-wrap">
      <table class="rp-table">
        <thead><tr><th>Kode</th><th>Nama</th><th>Overdue</th></tr></thead>
        <tbody>
          @forelse($topOverdueMembers as $row)
            <tr><td>{{ $row['member_code'] }}</td><td>{{ $row['full_name'] }}</td><td><span class="rp-badge red">{{ number_format($row['overdue_items']) }}</span></td></tr>
          @empty
            <tr><td colspan="3" class="rp-empty">Tidak ada data</td></tr>
          @endforelse
        </tbody>
      </table>
      </div>
    </div>

    <div class="rp-card tone-denda">
      <div class="rp-head-row">
        <h2 class="rp-subtitle">Ringkasan Denda</h2>
        <div class="rp-actions">
          <a class="rp-btn" href="{{ route('laporan.export', ['type' => 'denda', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor CSV</a>
          <a class="rp-btn" href="{{ route('laporan.export_xlsx', ['type' => 'denda', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor XLSX</a>
        </div>
      </div>
      <div class="rp-table-wrap">
      <table class="rp-table">
        <thead><tr><th>Kode</th><th>Nama</th><th>Status</th><th>Jumlah</th></tr></thead>
        <tbody>
          @forelse($finesRows as $row)
            <tr>
              <td>{{ $row['member_code'] }}</td>
              <td>{{ $row['full_name'] }}</td>
              @php
                $fStatus = strtolower((string) $row['status']);
                $fTone = match($fStatus) {
                  'paid', 'lunas' => 'green',
                  'waived', 'void' => 'gray',
                  'partial' => 'amber',
                  default => 'red',
                };
              @endphp
              <td><span class="rp-badge {{ $fTone }}">{{ strtoupper((string) $row['status']) }}</span></td>
              <td>Rp {{ number_format((float) $row['amount'], 0, ',', '.') }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="rp-empty">Tidak ada data</td></tr>
          @endforelse
        </tbody>
      </table>
      </div>
    </div>

    <div class="rp-card tone-pengadaan">
      <div class="rp-head-row">
        <h2 class="rp-subtitle">Ringkasan Pengadaan</h2>
        <div class="rp-actions">
          <a class="rp-btn" href="{{ route('laporan.export', ['type' => 'pengadaan', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor CSV</a>
          <a class="rp-btn" href="{{ route('laporan.export_xlsx', ['type' => 'pengadaan', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor XLSX</a>
        </div>
      </div>
      <div class="rp-table-wrap">
      <table class="rp-table">
        <thead><tr><th>PO</th><th>Vendor</th><th>Status</th><th>Total</th></tr></thead>
        <tbody>
          @forelse($acquisitionRows as $row)
            <tr>
              <td>{{ $row['po_number'] }}</td>
              <td>{{ $row['vendor_name'] }}</td>
              @php
                $poStatus = strtolower((string) $row['status']);
                $poTone = match($poStatus) {
                  'approved', 'received', 'closed' => 'green',
                  'draft', 'pending' => 'amber',
                  'cancelled', 'rejected' => 'red',
                  default => 'gray',
                };
              @endphp
              <td><span class="rp-badge {{ $poTone }}">{{ strtoupper((string) $row['status']) }}</span></td>
              <td>Rp {{ number_format((float) $row['total_amount'], 0, ',', '.') }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="rp-empty">Tidak ada data</td></tr>
          @endforelse
        </tbody>
      </table>
      </div>
    </div>

    <div class="rp-card tone-anggota">
      <div class="rp-head-row">
        <h2 class="rp-subtitle">Ringkasan Anggota</h2>
        <div class="rp-actions">
          <a class="rp-btn" href="{{ route('laporan.export', ['type' => 'anggota', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor CSV</a>
          <a class="rp-btn" href="{{ route('laporan.export_xlsx', ['type' => 'anggota', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor XLSX</a>
        </div>
      </div>
      <div class="rp-table-wrap">
      <table class="rp-table">
        <thead><tr><th>Kode</th><th>Nama</th><th>Status</th><th>Pinjaman Aktif</th><th>Overdue</th><th>Denda Belum Lunas</th></tr></thead>
        <tbody>
          @forelse($memberRows as $row)
            <tr>
              <td>{{ $row['member_code'] }}</td>
              <td>{{ $row['full_name'] }}</td>
              @php
                $mStatus = strtolower((string) $row['status']);
                $mTone = match($mStatus) {
                  'active' => 'green',
                  'inactive' => 'gray',
                  'blocked', 'suspended' => 'red',
                  default => 'amber',
                };
              @endphp
              <td><span class="rp-badge {{ $mTone }}">{{ strtoupper((string) $row['status']) }}</span></td>
              <td>{{ number_format((int) $row['active_loans']) }}</td>
              <td><span class="rp-badge red">{{ number_format((int) $row['overdue_items']) }}</span></td>
              <td>Rp {{ number_format((float) $row['unpaid_fines'], 0, ',', '.') }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="rp-empty">Tidak ada data</td></tr>
          @endforelse
        </tbody>
      </table>
      </div>
    </div>

    <div class="rp-card tone-serial">
      <div class="rp-head-row">
        <h2 class="rp-subtitle">Ringkasan Serial</h2>
        <div class="rp-actions">
          <a class="rp-btn" href="{{ route('laporan.export', ['type' => 'serial', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor CSV</a>
          <a class="rp-btn" href="{{ route('laporan.export_xlsx', ['type' => 'serial', 'from' => $filters['from'], 'to' => $filters['to'], 'branch_id' => $filters['branch_id']]) }}">Ekspor XLSX</a>
        </div>
      </div>
      <div class="rp-table-wrap">
      <table class="rp-table">
        <thead><tr><th>Issue</th><th>Judul</th><th>Status</th><th>Expected</th><th>Received</th><th>Cabang</th></tr></thead>
        <tbody>
          @forelse($serialRows as $row)
            <tr>
              <td>{{ $row['issue_code'] }}</td>
              <td>{{ $row['title'] }}</td>
              @php
                $sStatus = strtolower((string) $row['status']);
                $sTone = match($sStatus) {
                  'received', 'closed' => 'green',
                  'claimed', 'missing' => 'red',
                  'scheduled', 'open' => 'blue',
                  default => 'gray',
                };
              @endphp
              <td><span class="rp-badge {{ $sTone }}">{{ strtoupper((string) $row['status']) }}</span></td>
              <td>{{ $row['expected_on'] !== '' ? \Illuminate\Support\Carbon::parse($row['expected_on'])->format('d M Y') : '-' }}</td>
              <td>{{ $row['received_at'] !== '' ? \Illuminate\Support\Carbon::parse($row['received_at'])->format('d M Y') : '-' }}</td>
              <td>{{ $row['branch_name'] }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="rp-empty">Tidak ada data</td></tr>
          @endforelse
        </tbody>
      </table>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const page = document.querySelector('.rp-page');
  if (!page) return;
  const key = 'nb_laporan_table_density_v1';
  const buttons = Array.from(document.querySelectorAll('.rp-density-btn'));
  if (!buttons.length) return;

  function apply(mode) {
    const compact = mode === 'compact';
    page.classList.toggle('compact', compact);
    buttons.forEach((btn) => {
      btn.classList.toggle('is-active', btn.dataset.density === mode);
    });
  }

  buttons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const mode = btn.dataset.density === 'compact' ? 'compact' : 'normal';
      localStorage.setItem(key, mode);
      apply(mode);
    });
  });

  const saved = localStorage.getItem(key);
  apply(saved === 'compact' ? 'compact' : 'normal');
})();
</script>
@endsection
