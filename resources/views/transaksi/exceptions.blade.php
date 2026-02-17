@extends('layouts.notobuku')

@section('title', 'Operasi Pengecualian')

@section('content')
@php
  $rows = $rows ?? [];
  $dates = $dates ?? [];
  $selectedDate = $selectedDate ?? '';
  $filters = $filters ?? ['type' => '', 'severity' => '', 'status' => '', 'q' => ''];
  $ackEnabled = (bool) ($ackEnabled ?? false);
  $sla = $sla ?? ['total' => 0, 'open' => 0, 'ack' => 0, 'resolved' => 0, 'open_over_24h' => 0, 'open_over_72h' => 0, 'ack_over_24h' => 0, 'ack_over_72h' => 0];
  $owners = $owners ?? [];
  $currentUserId = (int) (auth()->id() ?? 0);
  $viewMode = in_array(($viewMode ?? 'full'), ['compact', 'full'], true) ? $viewMode : 'full';
  $isCompactMode = $viewMode === 'compact';
  $modeFilterParams = [
    'date' => $selectedDate,
    'type' => $filters['type'] ?? '',
    'severity' => $filters['severity'] ?? '',
    'status' => $filters['status'] ?? '',
    'q' => $filters['q'] ?? '',
  ];
@endphp

<style>
  .ex-wrap { width:100%; max-width:none; margin:0; padding:0; display:grid; gap:14px; }
  .ex-card { background:#fff; border:1px solid rgba(15,23,42,.10); border-radius:16px; padding:14px; }
  html.dark .ex-card { background:#1f2937; border-color:#374151; }
  .ex-title { margin:0; font-size:20px; font-weight:800; color:#111827; }
  html.dark .ex-title { color:#f9fafb; }
  .ex-sub { margin-top:4px; font-size:13px; color:#6b7280; }
  .ex-mode-hint { margin-top:6px; display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:#1e3a8a; background:#dbeafe; border:1px solid #93c5fd; border-radius:999px; padding:4px 10px; }
  html.dark .ex-sub { color:#9ca3af; }
  .ex-filter { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; align-items:end; margin-top:12px; }
  .ex-filter-actions { grid-column:1 / -1; display:flex; gap:8px; align-items:center; justify-content:flex-start; flex-wrap:wrap; }
  .ex-field label { display:block; font-size:12px; color:#6b7280; font-weight:700; margin-bottom:6px; }
  .ex-field input, .ex-field select, .ex-field textarea { width:100%; border:1px solid rgba(15,23,42,.12); border-radius:12px; padding:10px; font-size:13px; background:#fff; }
  html.dark .ex-field input, html.dark .ex-field select, html.dark .ex-field textarea { background:#111827; border-color:#374151; color:#f9fafb; }
  .ex-btn, .op-btn { display:inline-flex !important; align-items:center; justify-content:center; border:1px solid rgba(15,23,42,.14); border-radius:12px; padding:10px 12px; font-size:13px; font-weight:800; text-decoration:none; cursor:pointer; background:#fff; color:#111827; white-space:nowrap !important; line-height:1.1; min-height:40px; letter-spacing:.01em; }
  .ex-btn.primary { background:linear-gradient(180deg,#1e88e5,#1565c0); border-color:transparent; color:#fff; }
  .ex-btn.warn { background:#fff8e1; color:#7a4300; border-color:#f59e0b66; }
  .ex-btn.ok { background:#ecfdf5; color:#065f46; border-color:#10b98155; }
  .ex-table { width:100%; border-collapse:collapse; margin-top:8px; }
  .ex-table-wrap { overflow-x:auto; border-radius:12px; border:1px solid rgba(15,23,42,.08); }
  .ex-table th, .ex-table td { border-bottom:1px solid rgba(15,23,42,.08); text-align:left; padding:9px 8px; font-size:12px; vertical-align:top; }
  .ex-table th { color:#6b7280; font-weight:800; text-transform:uppercase; position:sticky; top:0; background:#f8fafc; z-index:2; }
  .ex-table td { background:#fff; }
  html.dark .ex-table th, html.dark .ex-table td { border-bottom-color:#374151; color:#e5e7eb; }
  html.dark .ex-table th { background:#111827; }
  html.dark .ex-table td { background:#1f2937; }
  .ex-pill { display:inline-flex; border-radius:999px; padding:2px 8px; font-size:11px; font-weight:800; border:1px solid; }
  .ex-pill.open { color:#1e3a8a; background:#dbeafe; border-color:#93c5fd; }
  .ex-pill.ack { color:#92400e; background:#fef3c7; border-color:#f59e0b66; }
  .ex-pill.resolved { color:#065f46; background:#d1fae5; border-color:#6ee7b7; }
  .ex-pill.sla-ok { color:#065f46; background:#dcfce7; border-color:#86efac; }
  .ex-pill.sla-warn { color:#92400e; background:#fef3c7; border-color:#f59e0b66; }
  .ex-pill.sla-critical { color:#991b1b; background:#fee2e2; border-color:#fca5a5; }
  .ex-pill.prio-0 { color:#475569; background:#f1f5f9; border-color:#cbd5e1; }
  .ex-pill.prio-1 { color:#991b1b; background:#fee2e2; border-color:#fca5a5; }
  .ex-pill.prio-2 { color:#92400e; background:#fef3c7; border-color:#f59e0b66; }
  .ex-pill.prio-3 { color:#065f46; background:#dcfce7; border-color:#86efac; }
  .ex-mini { font-size:11px; color:#6b7280; margin-top:3px; }
  .ex-sla { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:10px; }
  .ex-sla-card { border:1px solid rgba(15,23,42,.10); border-radius:12px; padding:10px; background:#f8fafc; }
  .ex-sla-title { font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase; }
  .ex-sla-val { font-size:20px; font-weight:900; color:#0f172a; margin-top:4px; }
  .ex-bulk { margin-top:10px; display:grid; gap:10px; align-items:end; }
  .ex-bulk-advanced { display:grid; grid-template-columns:220px minmax(0,1fr) auto; gap:10px; align-items:end; }
  .ex-bulk-main { min-width:0; }
  .ex-bulk-note { min-width:0; }
  .ex-bulk-apply { min-width:120px; }
  .ex-bulk-assign { grid-column:1 / span 2; min-width:0; }
  .ex-bulk-assign-btn { min-width:120px; }
  .ex-bulk-info { display:flex; gap:10px; align-items:center; flex-wrap:wrap; padding:8px 10px; border:1px dashed rgba(15,23,42,.14); border-radius:10px; background:#f8fafc; }
  .ex-advanced[hidden] { display:none !important; }
  .ex-bulk-meta { font-size:12px; color:#334155; font-weight:700; }
  .ex-btn[disabled] { opacity:.55; cursor:not-allowed; }
  .ex-row-critical { background: rgba(239,68,68,.06); }
  .ex-row-warn { background: rgba(245,158,11,.06); }
  .ex-empty { padding:18px 12px; text-align:center; color:#64748b; font-weight:700; }
  .ex-pic-actions { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
  .ex-compact-select { min-width:170px; }
  .ex-legend { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:10px; }
  .ex-legend-label { font-size:12px; color:#64748b; font-weight:700; margin-right:4px; }
  .ex-muted-box { border:1px dashed rgba(15,23,42,.18); border-radius:12px; padding:10px; background:#f8fafc; color:#64748b; font-size:12px; }
  .ex-adv { margin-top:10px; border:1px dashed rgba(15,23,42,.18); border-radius:12px; background:#f8fafc; padding:10px; }
  .ex-adv > summary { cursor:pointer; font-weight:800; font-size:12px; color:#334155; }
  .ex-adv-grid { margin-top:10px; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
  .is-compact .ex-sub { margin-top:2px; }
  .is-compact .ex-legend, .is-compact .ex-sla { display:none; }
  .ex-wrap.is-compact { gap:12px; }
  @media (max-width: 1240px) { .ex-filter { grid-template-columns:repeat(3,minmax(0,1fr)); } .ex-filter-actions { justify-content:flex-start; } }
  @media (max-width: 1100px) { .ex-filter, .ex-adv-grid { grid-template-columns:1fr; } .ex-bulk-advanced { grid-template-columns:1fr; } .ex-bulk-assign { grid-column:auto; } }
</style>

<div class="ex-wrap {{ $isCompactMode ? 'is-compact' : '' }}">
  <div class="ex-card">
    <h1 class="ex-title">Monitoring Operasional Sirkulasi</h1>
    <div class="ex-sub">Panel ringkas untuk memantau kasus bermasalah dan menindaklanjuti sampai selesai.</div>
    <div class="ex-mode-hint">Mode ringkas aktif</div>

    @if(session('success'))
      <div class="ex-sub" style="color:#166534; margin-top:8px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="ex-sub" style="color:#991b1b; margin-top:8px;">{{ session('error') }}</div>
    @endif

    <form method="GET" action="{{ route('transaksi.exceptions.index') }}" class="ex-filter">
      <input type="hidden" name="mode" value="{{ $viewMode }}">
      <div class="ex-field">
        <label>Tanggal Data</label>
        @if(!empty($dates))
          <select name="date">
            @foreach($dates as $d)
              <option value="{{ $d }}" @selected($selectedDate === $d)>{{ $d }}</option>
            @endforeach
          </select>
        @else
          <input type="text" value="Belum ada snapshot" disabled>
        @endif
      </div>
      <div class="ex-field">
        <label>Status Tindak Lanjut</label>
        <select name="status" @disabled(empty($dates))>
          <option value="">Semua</option>
          <option value="open" @selected(($filters['status'] ?? '') === 'open')>Belum ditangani</option>
          <option value="ack" @selected(($filters['status'] ?? '') === 'ack')>Sedang ditangani</option>
          <option value="resolved" @selected(($filters['status'] ?? '') === 'resolved')>Selesai</option>
        </select>
      </div>
      <div class="ex-field">
        <label>Cari Cepat</label>
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="kode pinjam / anggota / barcode / detail" @disabled(empty($dates))>
      </div>
      <details class="ex-adv" @if(!$isCompactMode && empty($dates)) open @endif>
        <summary>Filter lanjutan</summary>
        <div class="ex-adv-grid">
          <div class="ex-field">
            <label>Jenis Kasus</label>
            <select name="type" @disabled(empty($dates))>
              <option value="">Semua</option>
              <option value="overdue_extreme" @selected(($filters['type'] ?? '') === 'overdue_extreme')>Keterlambatan ekstrem</option>
              <option value="fine_void_activity" @selected(($filters['type'] ?? '') === 'fine_void_activity')>Aktivitas void denda</option>
              <option value="branch_mismatch_active_loan" @selected(($filters['type'] ?? '') === 'branch_mismatch_active_loan')>Mismatch cabang pinjaman aktif</option>
            </select>
          </div>
          <div class="ex-field">
            <label>Tingkat Risiko</label>
            <select name="severity" @disabled(empty($dates))>
              <option value="">Semua</option>
              <option value="warning" @selected(($filters['severity'] ?? '') === 'warning')>Peringatan</option>
              <option value="critical" @selected(($filters['severity'] ?? '') === 'critical')>Kritis</option>
            </select>
          </div>
        </div>
      </details>
      <div class="ex-filter-actions">
        <button class="ex-btn primary" type="submit" @disabled(empty($dates))>Terapkan</button>
        <a
          class="ex-btn"
          href="{{ route('transaksi.exceptions.export.csv', array_merge($modeFilterParams, ['mode' => $viewMode])) }}"
        >Export CSV</a>
        <a
          class="ex-btn"
          href="{{ route('transaksi.exceptions.export.xlsx', array_merge($modeFilterParams, ['mode' => $viewMode])) }}"
        >Export XLSX</a>
        <a class="ex-btn" href="{{ route('transaksi.exceptions.index', ['mode' => $viewMode]) }}">Bersihkan</a>
      </div>
    </form>

    <div class="ex-legend">
      <span class="ex-legend-label">Panduan SLA/Prioritas:</span>
      <span class="ex-pill sla-ok">&lt;24h</span>
      <span class="ex-pill sla-warn">24-72h</span>
      <span class="ex-pill sla-critical">&gt;72h</span>
      <span class="ex-pill prio-1">Mendesak</span>
      <span class="ex-pill prio-2">Perlu tindak lanjut</span>
      <span class="ex-pill prio-3">Normal</span>
      <span class="ex-pill prio-0">Selesai</span>
    </div>
  </div>

  <div class="ex-card">
    <div class="ex-sla">
      <div class="ex-sla-card">
        <div class="ex-sla-title">Belum ditangani >24h</div>
        <div class="ex-sla-val">{{ number_format((int) $sla['open_over_24h']) }}</div>
      </div>
      <div class="ex-sla-card">
        <div class="ex-sla-title">Belum ditangani >72h</div>
        <div class="ex-sla-val">{{ number_format((int) $sla['open_over_72h']) }}</div>
      </div>
      <div class="ex-sla-card">
        <div class="ex-sla-title">Sedang ditangani >24h</div>
        <div class="ex-sla-val">{{ number_format((int) $sla['ack_over_24h']) }}</div>
      </div>
      <div class="ex-sla-card">
        <div class="ex-sla-title">Sedang ditangani >72h</div>
        <div class="ex-sla-val">{{ number_format((int) $sla['ack_over_72h']) }}</div>
      </div>
    </div>

    <form method="POST" action="{{ route('transaksi.exceptions.bulk') }}" class="ex-bulk" id="bulk-form">
      @csrf
      <input type="hidden" name="snapshot_date" value="{{ $selectedDate }}">
      <input type="hidden" name="current_date" value="{{ $selectedDate }}">
      <input type="hidden" name="current_type" value="{{ $filters['type'] ?? '' }}">
      <input type="hidden" name="current_severity" value="{{ $filters['severity'] ?? '' }}">
      <input type="hidden" name="current_status" value="{{ $filters['status'] ?? '' }}">
      <input type="hidden" name="current_q" value="{{ $filters['q'] ?? '' }}">
      <input type="hidden" name="current_mode" value="{{ $viewMode }}">
      <div class="ex-bulk-advanced ex-advanced" id="bulk-advanced" hidden>
        <div class="ex-field ex-bulk-main">
          <label>Aksi Massal</label>
          <select name="bulk_action">
            <option value="ack">Tandai sedang ditangani</option>
            <option value="resolved">Tandai selesai</option>
          </select>
        </div>
        <div class="ex-field ex-bulk-note">
          <label>Catatan Aksi Massal</label>
          <input type="text" name="ack_note" placeholder="catatan bulk (opsional)">
        </div>
        <button type="submit" class="ex-btn primary ex-bulk-apply" id="bulk-submit" @disabled(!$ackEnabled)>Terapkan</button>
        <div class="ex-field ex-bulk-assign">
          <label>Tetapkan PIC Massal</label>
          <select name="bulk_owner_user_id" id="bulk-owner-user-id">
            <option value="">Pilih PIC untuk selected...</option>
            @foreach($owners as $owner)
              <option value="{{ $owner['id'] }}">{{ $owner['name'] }} ({{ strtoupper($owner['role']) }})</option>
            @endforeach
          </select>
        </div>
        <button type="button" class="ex-btn ex-bulk-assign-btn" id="bulk-assign-owner" @disabled(!$ackEnabled)>Set PIC</button>
      </div>
      <div class="ex-bulk-info">
        <span class="ex-bulk-meta">Terpilih: <span id="bulk-count">0</span> item</span>
        <span class="ex-mini" id="bulk-hint">Pilih row lewat checkbox untuk menampilkan aksi lanjutan.</span>
      </div>
    </form>

    @if(!$ackEnabled)
      <div class="ex-sub" style="color:#991b1b;">Fitur acknowledge belum aktif: tabel `circulation_exception_acknowledgements` belum tersedia.</div>
    @endif
    @if(empty($dates))
      <div class="ex-muted-box">
        Snapshot exception belum tersedia. Jalankan command `php artisan notobuku:circulation-exception-snapshot` atau tunggu scheduler harian.
      </div>
    @endif

    <div class="ex-table-wrap">
    <table class="ex-table">
      <thead>
        <tr>
          <th><input type="checkbox" id="toggle-all"></th>
          <th>Type</th>
          <th>Severity</th>
          <th>Loan/Item</th>
          <th>Member</th>
          <th>Detail</th>
          <th>Age</th>
          <th>Status</th>
          <th>PIC</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $row)
          @php
            $status = (string) ($row['status'] ?? 'open');
            $isResolved = $status === 'resolved';
          @endphp
          <tr class="{{ (int) ($row['age_hours'] ?? 0) >= 72 && $status !== 'resolved' ? 'ex-row-critical' : ((int) ($row['age_hours'] ?? 0) >= 24 && $status !== 'resolved' ? 'ex-row-warn' : '') }}">
            <td><input class="row-pick" type="checkbox" value="{{ $row['fingerprint'] }}" @disabled(!$ackEnabled || $isResolved)></td>
            <td>{{ $row['exception_type'] }}</td>
            <td>{{ $row['severity'] }}</td>
            <td>
              <div><b>{{ $row['loan_code'] !== '' ? $row['loan_code'] : '-' }}</b></div>
              <div class="ex-mini">Item: {{ $row['item_id'] }} / {{ $row['barcode'] !== '' ? $row['barcode'] : '-' }}</div>
              <div class="ex-mini">LoanItem: {{ $row['loan_item_id'] }}</div>
            </td>
            <td>
              <div>{{ $row['member_code'] !== '' ? $row['member_code'] : '-' }}</div>
              <div class="ex-mini">Member ID: {{ $row['member_id'] }}</div>
            </td>
            <td>
              <div>{{ $row['detail'] !== '' ? $row['detail'] : '-' }}</div>
              <div class="ex-mini">Detected: {{ $row['detected_at'] !== '' ? $row['detected_at'] : '-' }}</div>
            </td>
            @php
              $ageHours = (int) ($row['age_hours'] ?? 0);
              $slaClass = $ageHours >= 72 ? 'sla-critical' : ($ageHours >= 24 ? 'sla-warn' : 'sla-ok');
              $slaText = $ageHours >= 72 ? '>72h' : ($ageHours >= 24 ? '24-72h' : '<24h');
              $severityText = strtolower(trim((string) ($row['severity'] ?? '')));
              $priority = 'Normal';
              $priorityClass = 'prio-3';
              if ($status === 'resolved') {
                $priority = 'Selesai';
                $priorityClass = 'prio-0';
              } elseif ($ageHours >= 72 || $severityText === 'critical') {
                $priority = 'Mendesak';
                $priorityClass = 'prio-1';
              } elseif ($ageHours >= 24) {
                $priority = 'Perlu tindak lanjut';
                $priorityClass = 'prio-2';
              }
            @endphp
            <td>
              <div>{{ number_format($ageHours) }}h</div>
              <div class="ex-mini" style="display:flex; gap:4px; align-items:center; flex-wrap:wrap;">
                <span class="ex-pill {{ $slaClass }}">{{ $slaText }}</span>
                <span class="ex-pill {{ $priorityClass }}">{{ $priority }}</span>
              </div>
            </td>
            <td>
              @php
                $statusLabel = match($status){
                  'ack' => 'Sedang ditangani',
                  'resolved' => 'Selesai',
                  default => 'Belum ditangani',
                };
              @endphp
              <span class="ex-pill {{ $status }}">{{ $statusLabel }}</span>
              @if(($row['ack_by_name'] ?? '') !== '')
                <div class="ex-mini">Ack: {{ $row['ack_by_name'] }} {{ $row['ack_at'] !== '' ? '(' . $row['ack_at'] . ')' : '' }}</div>
              @endif
              @if(($row['resolved_by_name'] ?? '') !== '')
                <div class="ex-mini">Resolved: {{ $row['resolved_by_name'] }} {{ $row['resolved_at'] !== '' ? '(' . $row['resolved_at'] . ')' : '' }}</div>
              @endif
            </td>
            <td>
              <div>{{ ($row['owner_name'] ?? '') !== '' ? $row['owner_name'] : '-' }}</div>
              @if(($row['owner_assigned_at'] ?? '') !== '')
                <div class="ex-mini">Assigned: {{ $row['owner_assigned_at'] }}</div>
              @endif
              <form method="POST" action="{{ route('transaksi.exceptions.assign_owner') }}" style="display:grid; gap:6px; margin-top:6px;">
                @csrf
                <input type="hidden" name="snapshot_date" value="{{ $row['snapshot_date'] }}">
                <input type="hidden" name="current_date" value="{{ $selectedDate }}">
                <input type="hidden" name="current_type" value="{{ $filters['type'] ?? '' }}">
                <input type="hidden" name="current_severity" value="{{ $filters['severity'] ?? '' }}">
                <input type="hidden" name="current_status" value="{{ $filters['status'] ?? '' }}">
                <input type="hidden" name="current_q" value="{{ $filters['q'] ?? '' }}">
                <input type="hidden" name="current_mode" value="{{ $viewMode }}">
                <input type="hidden" name="fingerprint" value="{{ $row['fingerprint'] }}">
                <input type="hidden" name="exception_type" value="{{ $row['exception_type'] }}">
                <input type="hidden" name="severity" value="{{ $row['severity'] }}">
                <input type="hidden" name="loan_id" value="{{ $row['loan_id'] }}">
                <input type="hidden" name="loan_item_id" value="{{ $row['loan_item_id'] }}">
                <input type="hidden" name="item_id" value="{{ $row['item_id'] }}">
                <input type="hidden" name="barcode" value="{{ $row['barcode'] }}">
                <input type="hidden" name="member_id" value="{{ $row['member_id'] }}">
                <input type="hidden" name="detail" value="{{ $row['detail'] }}">
                <div class="ex-pic-actions">
                  <select class="ex-compact-select" name="owner_user_id" @disabled(!$ackEnabled || $isResolved)>
                    <option value="">Pilih PIC...</option>
                    @foreach($owners as $owner)
                      <option value="{{ $owner['id'] }}" @selected((int) ($row['owner_user_id'] ?? 0) === (int) $owner['id'])>
                        {{ $owner['name'] }} ({{ strtoupper($owner['role']) }})
                      </option>
                    @endforeach
                  </select>
                  <button class="ex-btn" type="submit" @disabled(!$ackEnabled || $isResolved)>Set PIC</button>
                  @if($currentUserId > 0)
                    <button
                      class="ex-btn"
                      type="submit"
                      name="owner_user_id"
                      value="{{ $currentUserId }}"
                      @disabled(!$ackEnabled || $isResolved || (int) ($row['owner_user_id'] ?? 0) === $currentUserId)
                    >Assign me</button>
                  @endif
                </div>
              </form>
            </td>
            <td>
              <form method="POST" action="{{ route('transaksi.exceptions.ack') }}" style="display:grid; gap:6px;">
                @csrf
                <input type="hidden" name="snapshot_date" value="{{ $row['snapshot_date'] }}">
                <input type="hidden" name="current_date" value="{{ $selectedDate }}">
                <input type="hidden" name="current_type" value="{{ $filters['type'] ?? '' }}">
                <input type="hidden" name="current_severity" value="{{ $filters['severity'] ?? '' }}">
                <input type="hidden" name="current_status" value="{{ $filters['status'] ?? '' }}">
                <input type="hidden" name="current_q" value="{{ $filters['q'] ?? '' }}">
                <input type="hidden" name="current_mode" value="{{ $viewMode }}">
                <input type="hidden" name="fingerprint" value="{{ $row['fingerprint'] }}">
                <input type="hidden" name="exception_type" value="{{ $row['exception_type'] }}">
                <input type="hidden" name="severity" value="{{ $row['severity'] }}">
                <input type="hidden" name="loan_id" value="{{ $row['loan_id'] }}">
                <input type="hidden" name="loan_item_id" value="{{ $row['loan_item_id'] }}">
                <input type="hidden" name="item_id" value="{{ $row['item_id'] }}">
                <input type="hidden" name="barcode" value="{{ $row['barcode'] }}">
                <input type="hidden" name="member_id" value="{{ $row['member_id'] }}">
                <input type="hidden" name="detail" value="{{ $row['detail'] }}">
                <textarea name="ack_note" rows="2" placeholder="catatan ack/resolved">{{ $row['ack_note'] ?? '' }}</textarea>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                  <button class="ex-btn warn" type="submit" @disabled(!$ackEnabled || $isResolved)>Acknowledge</button>
                  <button
                    class="ex-btn ok"
                    type="submit"
                    formaction="{{ route('transaksi.exceptions.resolve') }}"
                    @disabled(!$ackEnabled || $isResolved)
                  >Resolve</button>
                </div>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="10">
              <div class="ex-empty">
                Tidak ada data exception untuk filter ini.
                <div style="margin-top:8px;">
                  <a class="ex-btn" href="{{ route('transaksi.exceptions.index', ['mode' => $viewMode]) }}">Reset filter</a>
                </div>
              </div>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
    </div>
  </div>
</div>
<form method="POST" action="{{ route('transaksi.exceptions.bulk_assign_owner') }}" id="bulk-assign-owner-form" style="display:none;">
  @csrf
  <input type="hidden" name="snapshot_date" value="{{ $selectedDate }}">
  <input type="hidden" name="owner_user_id" id="bulk-assign-owner-user-id" value="">
  <input type="hidden" name="current_date" value="{{ $selectedDate }}">
  <input type="hidden" name="current_type" value="{{ $filters['type'] ?? '' }}">
  <input type="hidden" name="current_severity" value="{{ $filters['severity'] ?? '' }}">
  <input type="hidden" name="current_status" value="{{ $filters['status'] ?? '' }}">
  <input type="hidden" name="current_q" value="{{ $filters['q'] ?? '' }}">
  <input type="hidden" name="current_mode" value="{{ $viewMode }}">
</form>
<script>
(function(){
  const toggleAll = document.getElementById('toggle-all');
  const rowPicks = Array.from(document.querySelectorAll('.row-pick'));
  const bulkForm = document.getElementById('bulk-form');
  const bulkAdvanced = document.getElementById('bulk-advanced');
  const bulkSubmit = document.getElementById('bulk-submit');
  const bulkCount = document.getElementById('bulk-count');
  const bulkAssignButton = document.getElementById('bulk-assign-owner');
  const bulkOwnerSelect = document.getElementById('bulk-owner-user-id');
  const bulkAssignForm = document.getElementById('bulk-assign-owner-form');
  const bulkAssignOwnerUserId = document.getElementById('bulk-assign-owner-user-id');
  const bulkHint = document.getElementById('bulk-hint');
  if(!bulkForm || !bulkSubmit || !bulkAdvanced) return;

  function refreshBulkState(){
    const count = rowPicks.filter((cb) => cb.checked && !cb.disabled).length;
    if(bulkCount) bulkCount.textContent = String(count);
    bulkSubmit.disabled = count === 0 || !{{ $ackEnabled ? 'true' : 'false' }};
    const showAdvanced = count > 0;
    bulkAdvanced.hidden = !showAdvanced;
    if (bulkHint) {
      bulkHint.textContent = showAdvanced
        ? 'Aksi lanjutan aktif untuk item yang terpilih.'
        : 'Pilih row lewat checkbox untuk menampilkan aksi lanjutan.';
    }
    if (bulkAssignButton) {
      bulkAssignButton.disabled = count === 0 || !{{ $ackEnabled ? 'true' : 'false' }};
    }
  }

  if(toggleAll){
    toggleAll.addEventListener('change', function(){
      rowPicks.forEach((cb) => {
        if(!cb.disabled) cb.checked = !!toggleAll.checked;
      });
      refreshBulkState();
    });
  }

  rowPicks.forEach((cb) => cb.addEventListener('change', refreshBulkState));

  bulkForm.addEventListener('submit', function(e){
    const picked = rowPicks.filter((cb) => cb.checked && !cb.disabled).map((cb) => cb.value);
    bulkForm.querySelectorAll('input[name=\"fingerprints[]\"]').forEach((el) => el.remove());
    if(picked.length === 0){
      e.preventDefault();
      alert('Pilih minimal satu item exception.');
      return;
    }
    picked.forEach((fp) => {
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'fingerprints[]';
      hidden.value = fp;
      bulkForm.appendChild(hidden);
    });
  });

  if (bulkAssignButton && bulkAssignForm && bulkOwnerSelect && bulkAssignOwnerUserId) {
    bulkAssignButton.addEventListener('click', function(){
      const picked = rowPicks.filter((cb) => cb.checked && !cb.disabled).map((cb) => cb.value);
      const ownerId = String(bulkOwnerSelect.value || '').trim();
      bulkAssignForm.querySelectorAll('input[name=\"fingerprints[]\"]').forEach((el) => el.remove());
      if (picked.length === 0) {
        alert('Pilih minimal satu item exception.');
        return;
      }
      if (ownerId === '') {
        alert('Pilih PIC untuk bulk assign.');
        return;
      }
      bulkAssignOwnerUserId.value = ownerId;
      picked.forEach((fp) => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'fingerprints[]';
        hidden.value = fp;
        bulkAssignForm.appendChild(hidden);
      });
      bulkAssignForm.submit();
    });
  }

  refreshBulkState();
})();
</script>
@endsection
