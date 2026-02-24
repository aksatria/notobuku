@extends('layouts.notobuku')

@section('title', ($title ?? 'Laporan Denda') . ' - NOTOBUKU')
@section('page_title', 'Laporan Denda')

@section('content')
@php
  $rows      = $rows ?? [];
  $q         = $q ?? request('q','');
  $status    = $status ?? request('status','');
  $recap     = $recap ?? ['outstanding'=>0,'paid_today'=>0,'paid_month'=>0,'tx_today'=>0,'tx_month'=>0];
  $branches  = $branches ?? [];
  $branch_id = $branch_id ?? request('branch_id');
  $date_from = $date_from ?? request('date_from');
  $date_to   = $date_to ?? request('date_to');
  $viewMode = in_array(($viewMode ?? 'full'), ['compact', 'full'], true) ? $viewMode : 'full';
  $isCompactMode = $viewMode === 'compact';

  $fmtDate = function($dt){
    if (empty($dt)) return '-';
    try { return \Carbon\Carbon::parse($dt)->format('d M Y'); } catch (\Throwable $e) { return (string)$dt; }
  };

  $idr = function($n){
    $n = (int)($n ?? 0);
    return number_format($n, 0, ',', '.');
  };

  $badge = function($s){
    $s = $s ?: 'unpaid';
    return match ($s) {
      'paid' => ['Lunas', 'ok'],
      'void' => ['Void', 'info'],
      default => ['Belum Dibayar', 'bad'],
    };
  };

  $sum_total = 0;
  $sum_paid  = 0;
  $sum_left  = 0;
  $count_rows = 0;

  foreach ($rows as $r){
    $st = $r['fine_status'] ?? 'unpaid';
    if($st === 'void') continue;

    $amount = (int)($r['amount'] ?? 0);
    $paid   = (int)($r['paid_amount'] ?? 0);
    $paidPart = min(max($paid,0), max($amount,0));
    $left = max($amount - $paidPart, 0);

    $sum_total += $amount;
    $sum_paid  += $paidPart;
    $sum_left  += $left;
    $count_rows++;
  }
@endphp

<div class="nb-container {{ $isCompactMode ? 'is-compact' : '' }}" style="max-width:none; padding-left:0; padding-right:0;">
  <div class="nb-card rp-card">
    <div class="rp-head">
      <div>
        <div class="rp-title">Monitoring Operasional Denda</div>
        <div class="rp-sub">Pantau status denda harian. Gunakan pencarian cepat dulu, lalu buka filter lanjutan jika perlu.</div>
        <div class="rp-mode-hint">Mode ringkas aktif</div>
      </div>
    </div>

    <div class="rp-kpi">
      <div class="rp-kpi-item bad">
        <div class="rp-kpi-lb">Outstanding</div>
        <div class="rp-kpi-val">Rp {{ $idr($recap['outstanding'] ?? 0) }}</div>
      </div>
      <div class="rp-kpi-item ok">
        <div class="rp-kpi-lb">Paid Hari Ini</div>
        <div class="rp-kpi-val">Rp {{ $idr($recap['paid_today'] ?? 0) }}</div>
        <div class="rp-kpi-sub">{{ (int)($recap['tx_today'] ?? 0) }} transaksi</div>
      </div>
      <div class="rp-kpi-item ok">
        <div class="rp-kpi-lb">Paid Bulan Ini</div>
        <div class="rp-kpi-val">Rp {{ $idr($recap['paid_month'] ?? 0) }}</div>
        <div class="rp-kpi-sub">{{ (int)($recap['tx_month'] ?? 0) }} transaksi</div>
      </div>
      <div class="rp-kpi-item">
        <div class="rp-kpi-lb">Total (hasil filter)</div>
        <div class="rp-kpi-val">Rp {{ $idr($sum_total) }}</div>
        <div class="rp-kpi-sub">{{ $count_rows }} baris</div>
      </div>
    </div>

    <form method="GET" action="{{ route('transaksi.denda.index') }}" class="rp-filter">
      <input type="hidden" name="mode" value="{{ $viewMode }}">
      <div class="rp-filter-grid">
        <div class="rp-field">
          <label>Cari Cepat</label>
          <input class="rp-input" type="text" name="q" value="{{ $q }}" placeholder="Kode Pinjaman / Nama / Kode Anggota / Judul">
        </div>

        <div class="rp-field">
          <label>Status Denda</label>
          <select class="rp-input" name="status">
            <option value="" {{ $status==='' ? 'selected' : '' }}>Semua</option>
            <option value="unpaid" {{ $status==='unpaid' ? 'selected' : '' }}>Belum Dibayar</option>
            <option value="paid" {{ $status==='paid' ? 'selected' : '' }}>Lunas</option>
            <option value="void" {{ $status==='void' ? 'selected' : '' }}>Void</option>
          </select>
        </div>

        <div class="rp-field">
          <label>&nbsp;</label>
          <button class="rp-btn op-btn primary" type="submit">Terapkan</button>
        </div>
      </div>
      <div class="rp-filter-actions">
        <a class="rp-btn op-btn" style="width:auto;" href="{{ route('transaksi.denda.index', ['mode' => $viewMode]) }}">Bersihkan</a>
      </div>
      <details class="rp-adv" @if(!$isCompactMode && (!empty($branch_id) || !empty($date_from) || !empty($date_to))) open @endif>
        <summary>Penyaring lanjutan</summary>
        <div class="rp-row" style="margin-top:10px;">
          @if(!empty($branches))
            <div class="rp-field w200">
              <label>Cabang</label>
              <select class="rp-input" name="branch_id">
                <option value="" {{ empty($branch_id) ? 'selected' : '' }}>Semua Cabang</option>
                @foreach($branches as $b)
                  <option value="{{ $b['id'] }}" {{ (string)$branch_id === (string)$b['id'] ? 'selected' : '' }}>
                    {{ $b['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
          @endif

          <div class="rp-field w140">
            <label>Dari Tanggal</label>
            <input class="rp-input" type="date" name="date_from" value="{{ $date_from }}">
          </div>
          <div class="rp-field w140">
            <label>Sampai Tanggal</label>
            <input class="rp-input" type="date" name="date_to" value="{{ $date_to }}">
          </div>
        </div>
      </details>

      <div class="rp-totline">
        <span class="rp-chip">Total: <strong>Rp {{ $idr($sum_total) }}</strong></span>
        <span class="rp-chip ok">Dibayar: <strong>Rp {{ $idr($sum_paid) }}</strong></span>
        <span class="rp-chip bad">Sisa: <strong>Rp {{ $idr($sum_left) }}</strong></span>
      </div>
    </form>

    <div class="rp-table">
      <table>
        <thead>
          <tr>
            <th style="width:36px; text-align:center;">No</th>
            <th style="width:148px;">Transaksi</th>
            <th style="width:160px;">Tanggal<span class="th-sub">Due / Returned</span></th>
            <th style="width:76px; text-align:center;">Telat</th>
            <th style="width:100px; text-align:right;">Total</th>
            <th style="width:100px; text-align:right;">Dibayar</th>
            <th style="width:100px; text-align:right;">Sisa</th>
            <th style="width:126px;">Status</th>
            <th style="width:74px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $r)
            @php
              [$label,$tone] = $badge($r['fine_status'] ?? 'unpaid');
              $amount = (int)($r['amount'] ?? 0);
              $paid   = (int)($r['paid_amount'] ?? 0);
              $paidPart = min(max($paid,0), max($amount,0));
              $left = max($amount - $paidPart, 0);

              $dueText = $fmtDate($r['due_at'] ?? null);
              $retText = $fmtDate($r['returned_at'] ?? null);
            @endphp
            <tr>
              <td style="text-align:center;">{{ $loop->iteration }}</td>
              <td>
                <div class="rp-mono rp-truncate" title="{{ $r['loan_code'] ?? '-' }}">{{ $r['loan_code'] ?? '-' }}</div>
              </td>
              <td>
                <div class="rp-sub" style="margin:0;">
                  <span class="rp-mono" style="font-weight:600;">Jatuh:</span> {{ $dueText }}
                </div>
                <div class="rp-sub" style="margin:0; margin-top:2px;">
                  <span class="rp-mono" style="font-weight:600;">Kembali:</span> {{ $retText }}
                </div>
              </td>
              <td style="text-align:center;">
                <span class="rp-chip {{ ((int)($r['days_late'] ?? 0))>0 ? 'bad' : '' }}">{{ (int)($r['days_late'] ?? 0) }} hari</span>
              </td>
              <td style="text-align:right; white-space:nowrap;">Rp {{ $idr($amount) }}</td>
              <td style="text-align:right; white-space:nowrap;">Rp {{ $idr($paidPart) }}</td>
              <td style="text-align:right; white-space:nowrap;">Rp {{ $idr($left) }}</td>
              <td>
                <span class="rp-chip {{ $tone }}">{{ $label }}</span>
              </td>
              <td>
                @if(!empty($r['loan_id']))
                  <a class="rp-mini op-btn" href="{{ route('transaksi.riwayat.detail', ['id' => $r['loan_id']]) }}">Detail</a>
                @else
                  <span class="rp-sub">-</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="nb-muted" style="padding:10px; font-weight:600;">Tidak ada data.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

  </div>
</div>

<style>
  .rp-card{ padding:14px 0; }
  .rp-head{ display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; }
  .rp-title{ font-size:14px; font-weight:700; color: rgba(11,37,69,.92); }
  .rp-sub{ margin-top:3px; font-size:12px; color: rgba(15,23,42,.62); }
  .rp-mode-hint{ margin-top:6px; display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:#1e3a8a; background:#dbeafe; border:1px solid #93c5fd; border-radius:999px; padding:4px 10px; }
  .op-btn{ display:inline-flex !important; align-items:center; justify-content:center; border:1px solid rgba(15,23,42,.14); border-radius:12px; padding:10px 12px; font-size:13px; font-weight:800; text-decoration:none; cursor:pointer; background:#fff; color:#111827; white-space:nowrap !important; line-height:1.1; min-height:40px; letter-spacing:.01em; }
  .op-btn.primary{ background:linear-gradient(180deg,#1e88e5,#1565c0); border-color:transparent; color:#fff; }
  .is-compact .rp-kpi, .is-compact .rp-totline{ display:none; }

  .rp-kpi{ display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:10px; margin-top:12px; }
  @media (max-width: 980px){ .rp-kpi{ grid-template-columns: repeat(2, minmax(0,1fr)); } }
  @media (max-width: 560px){ .rp-kpi{ grid-template-columns: 1fr; } }
  .rp-kpi-item{
    border:1px solid rgba(148,163,184,.28);
    background: rgba(255,255,255,.7);
    border-radius:14px;
    padding:10px 12px;
  }
  .rp-kpi-item.bad{ background: rgba(239,68,68,.10); border-color: rgba(239,68,68,.18); }
  .rp-kpi-item.info{ background: rgba(59,130,246,.10); border-color: rgba(59,130,246,.18); }
  .rp-kpi-item.ok{ background: rgba(16,185,129,.10); border-color: rgba(16,185,129,.18); }
  .rp-kpi-lb{ font-size:12px; font-weight:800; color: rgba(15,23,42,.70); }
  .rp-kpi-val{ margin-top:4px; font-size:16px; font-weight:700; color: rgba(15,23,42,.92); }
  .rp-kpi-sub{ margin-top:2px; font-size:12px; color: rgba(15,23,42,.62); font-weight:700; }

  .rp-filter{ margin-top:12px; }
  .rp-adv{
    margin-top:10px;
    border:1px dashed rgba(148,163,184,.45);
    border-radius:12px;
    padding:10px;
    background: rgba(248,250,252,.9);
  }
  .rp-adv > summary{
    cursor:pointer;
    font-size:12px;
    font-weight:800;
    color: rgba(15,23,42,.78);
  }
  .rp-row{ display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; }
  .rp-filter-grid{ display:grid; grid-template-columns:minmax(0,1fr) 170px 130px; gap:10px; align-items:end; }
  .rp-filter-actions{ margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .rp-field label{ display:block; font-size:12px; font-weight:850; color: rgba(15,23,42,.78); margin-bottom:6px; }
  .rp-field.grow{ flex:1; min-width:220px; }
  .rp-field.w200{ width:200px; }
  .rp-field.w160{ width:160px; }
  .rp-field.w140{ width:140px; }
  .rp-field.w130{ width:130px; }
  @media (max-width: 980px){ .rp-filter-grid{ grid-template-columns:1fr; } }

  .rp-input{
    width:100%;
    border-radius:12px;
    border:1px solid rgba(148,163,184,.35);
    padding:9px 10px;
    background: rgba(255,255,255,.92);
    font-size:13px;
  }
  .rp-btn{ width:100%; }

  .rp-totline{ display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }

  .rp-chip{
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid rgba(148,163,184,.35);
    background: rgba(255,255,255,.65);
    color: rgba(15,23,42,.78);
    font-size:12px; font-weight:750;
    white-space:nowrap;
  }
  .rp-chip.ok{ background: rgba(16,185,129,.12); border-color: rgba(16,185,129,.25); color: rgba(6,95,70,.95); }
  .rp-chip.bad{ background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.25); color: rgba(153,27,27,.95); }
  .rp-chip.info{ background: rgba(59,130,246,.12); border-color: rgba(59,130,246,.25); color: rgba(30,64,175,.95); }

  /* Table - dibuat rapat & no horizontal scroll */
  .rp-table{ margin-top:10px; }
  .rp-table table{ width:100%; border-collapse:separate; border-spacing:0; font-size:12.5px; table-layout:fixed; }
  .rp-table thead th{
    text-align:left;
    padding:8px 8px;
    border-bottom:1px solid rgba(148,163,184,.35);
    background: rgba(248,250,252,.96);
    color: rgba(15,23,42,.82);
    font-weight:650;
    white-space:normal;
    line-height:1.25;
  }
  .rp-table thead th .th-sub{
    display:block;
    margin-top:2px;
    font-size:11px;
    font-weight:500;
    color: rgba(15,23,42,.55);
  }
  .rp-table tbody td{
    padding:8px 8px;
    border-bottom:1px solid rgba(148,163,184,.18);
    vertical-align:top;
    color: rgba(15,23,42,.78);
    font-weight:450;
    line-height:1.25;
  }
  @media (max-width: 560px){
    .rp-table thead th, .rp-table tbody td{ padding:7px 6px; }
  }

  .rp-mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-weight:850; }
  .rp-truncate{
    display:block;
    max-width:100%;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }
  .rp-strong{ font-weight:900; color: rgba(15,23,42,.92); }
  .rp-sub{ font-size:12px; color: rgba(15,23,42,.62); font-weight:700; margin-top:2px; overflow:hidden; text-overflow:ellipsis; }

  .rp-mini{ min-height:30px; padding:5px 8px; border-radius:10px; font-size:11px; white-space:nowrap; }
</style>
@endsection

