@extends('layouts.notobuku')

@section('title', 'Riwayat Transaksi • NOTOBUKU')
@section('page_title', 'Riwayat Transaksi')

@section('content')
@php
  $tab = $tab ?? request('tab', 'transaksi');
  if(!in_array($tab, ['transaksi','denda'], true)) $tab = 'transaksi';

  $filters = $filters ?? [];
  $q = $filters['q'] ?? '';
  $status = $filters['status'] ?? '';
  $from = $filters['from'] ?? '';
  $to = $filters['to'] ?? '';
  $overdue = $filters['overdue'] ?? '';
  $perPage = $filters['per_page'] ?? '15';
  $range = $filters['range'] ?? '';
  $sort = $filters['sort'] ?? 'loaned_at';
  $dir = $filters['dir'] ?? 'desc';

  $fineRate = (int)($fine_rate ?? 1000);
  $fines = $fines ?? null;
  $fineFilters = $fine_filters ?? [];
  $fineQ = $fineFilters['fine_q'] ?? '';
  $fineStatus = $fineFilters['fine_status'] ?? 'unpaid';
  $finePerPage = $fineFilters['fine_per_page'] ?? '15';

  $fineSummary = $fine_summary ?? ['count_unpaid'=>0,'sum_unpaid'=>0,'count_paid'=>0,'sum_paid'=>0];

  $rolePolicies = (array)config('notobuku.loans.roles', []);
  $basePolicy = (array)config('notobuku.loans', []);
  $policyFor = function($role) use ($rolePolicies, $basePolicy){
    $key = strtolower(trim((string)$role ?: 'member'));
    $policy = $rolePolicies[$key] ?? ($rolePolicies['member'] ?? []);
    $fallback = [
      'default_days' => (int)($basePolicy['default_days'] ?? 7),
      'max_items' => (int)($basePolicy['max_items'] ?? 3),
      'max_renewals' => (int)($basePolicy['max_renewals'] ?? 2),
      'extend_days' => (int)($basePolicy['extend_days'] ?? 7),
    ];
    return array_merge($fallback, is_array($policy) ? $policy : []);
  };

  $badge = function($st, $isOverdue = false){
    $st = (string)$st;
    if($st === 'closed') return ['bg'=>'rgba(39,174,96,.12)','bd'=>'rgba(39,174,96,.22)','tx'=>'#1E7A44','lb'=>'Selesai'];
    if($st === 'overdue') return ['bg'=>'rgba(231,76,60,.12)','bd'=>'rgba(231,76,60,.22)','tx'=>'#B33A2B','lb'=>'Terlambat'];
    if($isOverdue) return ['bg'=>'rgba(231,76,60,.12)','bd'=>'rgba(231,76,60,.22)','tx'=>'#B33A2B','lb'=>'Jatuh tempo'];
    return ['bg'=>'rgba(31,58,95,.10)','bd'=>'rgba(31,58,95,.18)','tx'=>'#0B2545','lb'=>'Berjalan'];
  };

  $fineBadge = function($st){
    $st = (string)$st;
    if($st === 'paid') return ['bg'=>'rgba(39,174,96,.12)','bd'=>'rgba(39,174,96,.22)','tx'=>'#1E7A44','lb'=>'Lunas'];
    if($st === 'void') return ['bg'=>'rgba(127,140,141,.10)','bd'=>'rgba(127,140,141,.22)','tx'=>'#46525A','lb'=>'Dibatalkan'];
    return ['bg'=>'rgba(231,76,60,.12)','bd'=>'rgba(231,76,60,.22)','tx'=>'#B33A2B','lb'=>'Belum bayar'];
  };

  $idr = function($n){
    $n = (int)$n;
    return 'Rp ' . number_format($n, 0, ',', '.');
  };

  $tabUrl = function($name){
    $qs = request()->query();
    $qs['tab'] = $name;
    return route('transaksi.riwayat', $qs);
  };

  $isActiveTab = fn($name) => $tab === $name;
@endphp

<style>
  /* =========================================================
     RIWAYAT - match style Pinjam (clean bar + soft table)
     ========================================================= */

  .nb-rw-wrap{ max-width: 1100px; margin: 0 auto; }
  .nb-rw-card{ border-radius: 18px; border:1px solid rgba(15,23,42,.08); background: rgba(255,255,255,.92); box-shadow: 0 10px 30px rgba(15,23,42,.06); }
  .nb-rw-head{ padding: 14px 14px; }
  .nb-rw-title{ font-weight: 900; letter-spacing:.2px; font-size: 15px; }
  .nb-rw-sub{ margin-top:4px; color: rgba(15,23,42,.62); font-size: 12.5px; }

  /* Segmented control (tabs) */
  .nb-rw-tabs{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .nb-rw-pill{
    display:inline-flex; align-items:center; justify-content:center;
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.65);
    color: rgba(15,23,42,.78);
    text-decoration:none;
    font-weight:700;
    font-size: 12.5px;
    transition: transform .08s ease, background .15s ease, border-color .15s ease, box-shadow .15s ease;
  }
  .nb-rw-pill:hover{ transform: translateY(-1px); box-shadow: 0 10px 18px rgba(15,23,42,.08); }
  .nb-rw-pill.is-active{
    background: linear-gradient(90deg, rgba(30,136,229,.16), rgba(21,101,192,.12));
    border-color: rgba(30,136,229,.26);
    color: rgba(21,101,192,.95);
  }

  /* Quick action buttons to match pinjam */
  .nb-rw-actions{ display:flex; gap:8px; flex-wrap:wrap; }
  .nb-rw-btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding: 8px 12px;
    border-radius: 14px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.65);
    color: rgba(15,23,42,.78);
    text-decoration:none;
    font-weight:800;
    font-size: 12.5px;
    transition: transform .08s ease, background .15s ease, border-color .15s ease, box-shadow .15s ease;
  }
  .nb-rw-btn:hover{ transform: translateY(-1px); box-shadow: 0 10px 18px rgba(15,23,42,.08); }
  .nb-rw-btn.is-primary{
    background: linear-gradient(90deg, rgba(30,136,229,.18), rgba(21,101,192,.14));
    border-color: rgba(30,136,229,.28);
    color: rgba(21,101,192,.95);
  }

  /* Filter bar (clean) */
  .nb-rw-filter{ padding: 12px 12px; }
  .nb-rw-filterbar{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:flex-end;
    padding: 12px;
    border-radius: 16px;
    border: 1px solid rgba(15,23,42,.08);
    background: rgba(15,23,42,.02);
  }
  .nb-rw-field{ display:flex; flex-direction:column; gap:6px; min-width: 140px; flex: 1; }
  .nb-rw-field.w-220{ min-width: 220px; flex: 2; }
  .nb-rw-label{ font-size: 11.5px; color: rgba(15,23,42,.62); font-weight: 800; letter-spacing: .2px; }
  .nb-rw-input, .nb-rw-select{
    width: 100%;
    border-radius: 14px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.85);
    padding: 10px 11px;
    font-size: 13px;
    color: rgba(15,23,42,.88);
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease;
  }
  .nb-rw-input:focus, .nb-rw-select:focus{
    border-color: rgba(30,136,229,.32);
    box-shadow: 0 0 0 4px rgba(30,136,229,.10);
  }
  .nb-rw-chips{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  .nb-rw-chip{
    display:inline-flex; align-items:center; gap:8px;
    padding: 8px 10px;
    border-radius: 999px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.75);
    font-weight: 800;
    font-size: 12px;
    color: rgba(15,23,42,.76);
    text-decoration:none;
  }
  .nb-rw-chip input{ transform: translateY(1px); }

  .nb-rw-mini{
    display:inline-flex; align-items:center; gap:6px;
    padding:4px 8px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.75);
    font-size:11px;
    font-weight:800;
    color: rgba(15,23,42,.70);
  }
  .nb-rw-mini.warn{
    border-color: rgba(251,140,0,.35);
    background: rgba(251,140,0,.12);
    color:#ef6c00;
  }

  .nb-rw-filteractions{ display:flex; gap:8px; flex-wrap:wrap; }
  .nb-rw-apply{
    padding: 10px 14px;
    border-radius: 14px;
    border: 1px solid rgba(30,136,229,.28);
    background: linear-gradient(90deg, rgba(30,136,229,.18), rgba(21,101,192,.14));
    color: rgba(21,101,192,.95);
    font-weight: 900;
    cursor:pointer;
  }
  .nb-rw-reset{
    padding: 10px 14px;
    border-radius: 14px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.70);
    color: rgba(15,23,42,.72);
    font-weight: 900;
    text-decoration:none;
  }

  /* Table (soft, not admin-y) */
  .nb-rw-tablewrap{ padding: 0 12px 12px 12px; }
  .nb-rw-tablebox{
    border-radius: 16px;
    border: 1px solid rgba(15,23,42,.08);
    background: rgba(255,255,255,.88);
    overflow: hidden;
  }
  .nb-rw-table-scroll{ overflow-x:auto; -webkit-overflow-scrolling:touch; }
  .nb-rw-table{ width:100%; border-collapse:separate; border-spacing:0; min-width: 860px; }
  .nb-rw-table thead th{
    text-align:left;
    font-size: 11.5px;
    letter-spacing:.2px;
    text-transform: none;
    padding: 12px 12px;
    color: rgba(15,23,42,.64);
    background: rgba(15,23,42,.03);
    border-bottom: 1px solid rgba(15,23,42,.08);
    font-weight: 900;
    white-space: nowrap;
  }
  .nb-rw-table tbody td{
    padding: 12px 12px;
    border-bottom: 1px solid rgba(15,23,42,.06);
    vertical-align: middle;
    color: rgba(15,23,42,.86);
    font-size: 13px;
  }
  .nb-rw-table tbody tr:hover td{ background: rgba(30,136,229,.035); }
  .nb-rw-muted{ color: rgba(15,23,42,.62); font-size: 12px; }

  .nb-rw-pillbadge{
    display:inline-flex; align-items:center; gap:8px;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid transparent;
    font-weight: 900;
    font-size: 11.5px;
    white-space: nowrap;
  }

  .nb-rw-link{
    color: rgba(21,101,192,.96);
    font-weight: 900;
    text-decoration:none;
  }
  .nb-rw-link:hover{ text-decoration: underline; }

  /* Mobile: keep tabs + actions solid, reduce table min width */
  @media (max-width: 720px){
    .nb-rw-head{ padding: 12px 12px; }
    .nb-rw-filterbar{ padding: 10px; gap:10px; }
    .nb-rw-field{ min-width: 140px; }
    .nb-rw-field.w-220{ min-width: 100%; flex: 1 1 100%; }
    .nb-rw-table{ min-width: 760px; } /* still scrollable but softer */
  }

  /* =========================================================
     MOBILE CARD LIST (RIWAYAT) - TANPA SCROLL TABLE DI HP
     ========================================================= */
  .nb-rw-desktop{ display:block; }
  .nb-rw-mobile{ display:none; }

  .nb-rw-cards{ display:flex; flex-direction:column; gap:12px; }
  .nb-rw-card{
    border:1px solid rgba(17,24,39,.08);
    border-radius:18px;
    background:#fff;
    box-shadow: 0 10px 24px rgba(17,24,39,.05);
    overflow:hidden;
  }
  .nb-rw-card-hd{
    display:flex; align-items:flex-start; justify-content:space-between; gap:10px;
    padding:12px 12px 10px 12px;
    border-bottom:1px solid rgba(17,24,39,.06);
    background: linear-gradient(180deg, rgba(30,136,229,.06), rgba(30,136,229,0));
  }
  .nb-rw-card-title{
    font-weight:800; letter-spacing:.2px;
    font-size:13.5px; color:#0f172a;
    line-height:1.25;
  }
  .nb-rw-card-sub{
    margin-top:4px;
    font-size:12px; color:rgba(15,23,42,.70);
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    max-width: 100%;
  }
  .nb-rw-card-bd{
    padding:10px 12px 12px 12px;
    display:flex; flex-direction:column; gap:8px;
  }
  .nb-rw-kv{
    display:flex; justify-content:space-between; gap:10px;
    font-size:12px;
  }
  .nb-rw-kv .k{ color:rgba(15,23,42,.60); }
  .nb-rw-kv .v{ color:#0f172a; font-weight:650; text-align:right; max-width: 62%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .nb-rw-card-actions{
    margin-top:8px;
    display:flex; gap:10px; flex-wrap:wrap;
  }
  .nb-rw-card-actions a, .nb-rw-card-actions button{
    flex:1;
    min-width: 120px;
  }

  @media (max-width: 860px){
    .nb-rw-desktop{ display:none; }
    .nb-rw-mobile{ display:block; }
    .nb-rw-tablebox{ padding:0; background:transparent; border:none; box-shadow:none; }
    .nb-rw-card-actions a, .nb-rw-card-actions button{ flex: 1 1 calc(50% - 10px); }
  }

  @media (max-width: 420px){
    .nb-rw-card-actions a, .nb-rw-card-actions button{ flex: 1 1 100%; }
  }

</style>

<div class="nb-rw-wrap">
  {{-- Header --}}
  <div class="nb-rw-card nb-rw-head">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
      <div style="min-width: 200px;">
        <div class="nb-rw-title">Riwayat</div>
        <div class="nb-rw-sub">Transaksi sirkulasi + denda keterlambatan.</div>
      </div>

      <div class="nb-rw-actions" style="flex:1; justify-content:flex-end;">
        <a href="{{ route('transaksi.index') }}" class="nb-rw-btn">Pinjam</a>
        <a href="{{ route('transaksi.kembali.form') }}" class="nb-rw-btn">Kembali</a>
        <a href="{{ route('transaksi.perpanjang.form') }}" class="nb-rw-btn">Perpanjang</a>
        <a href="{{ route('transaksi.riwayat') }}" class="nb-rw-btn is-primary">Riwayat</a>
      </div>
    </div>

    <div style="height:12px;"></div>

    {{-- Tabs --}}
    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
      <div class="nb-rw-tabs">
        <a href="{{ $tabUrl('transaksi') }}" class="nb-rw-pill {{ $isActiveTab('transaksi') ? 'is-active' : '' }}">Transaksi</a>
        <a href="{{ $tabUrl('denda') }}" class="nb-rw-pill {{ $isActiveTab('denda') ? 'is-active' : '' }}">Denda</a>
      </div>

      @if($tab === 'denda')
        <div class="nb-rw-muted" style="font-weight:900;">
          Rate: {{ $idr($fineRate) }}/hari
        </div>
      @endif
    </div>
  </div>

  <div style="height:12px;"></div>

  {{-- FILTER BAR --}}
  <div class="nb-rw-card nb-rw-filter">
    @if($tab === 'transaksi')
      <form method="GET" action="{{ route('transaksi.riwayat') }}">
        <input type="hidden" name="tab" value="transaksi" />
        <div class="nb-rw-filterbar">
          <div class="nb-rw-chips" style="flex: 1 1 100%; margin-bottom:2px;">
            <a class="nb-rw-chip" href="{{ route('transaksi.riwayat', array_merge(request()->query(), ['status' => 'open', 'tab' => 'transaksi'])) }}">Aktif</a>
            <a class="nb-rw-chip" href="{{ route('transaksi.riwayat', array_merge(request()->query(), ['status' => 'overdue', 'tab' => 'transaksi'])) }}">Overdue</a>
            <a class="nb-rw-chip" href="{{ route('transaksi.riwayat', array_merge(request()->query(), ['status' => 'closed', 'tab' => 'transaksi'])) }}">Selesai</a>
            <a class="nb-rw-chip" href="{{ route('transaksi.riwayat', ['tab' => 'transaksi']) }}">Semua</a>
          </div>
          <div class="nb-rw-field w-220">
            <div class="nb-rw-label">Cari</div>
            <input class="nb-rw-input" type="text" name="q" value="{{ $q }}" placeholder="Kode transaksi / nama / kode anggota" />
          </div>

          <div class="nb-rw-field">
            <div class="nb-rw-label">Status</div>
            <select class="nb-rw-select" name="status">
              <option value="">Semua</option>
              <option value="open" @selected($status==='open')>Berjalan</option>
              <option value="overdue" @selected($status==='overdue')>Terlambat</option>
              <option value="closed" @selected($status==='closed')>Selesai</option>
            </select>
          </div>

          <div class="nb-rw-field">
            <div class="nb-rw-label">Rentang</div>
            <select class="nb-rw-select" name="range">
              <option value="">Custom</option>
              <option value="today" @selected($range==='today')>Hari ini</option>
              <option value="week" @selected($range==='week')>Minggu ini</option>
              <option value="month" @selected($range==='month')>Bulan ini</option>
            </select>
          </div>

          <div class="nb-rw-field">
            <div class="nb-rw-label">Dari</div>
            <input class="nb-rw-input" type="date" name="from" value="{{ $from }}" />
          </div>

          <div class="nb-rw-field">
            <div class="nb-rw-label">Sampai</div>
            <input class="nb-rw-input" type="date" name="to" value="{{ $to }}" />
          </div>

          <div class="nb-rw-field">
            <div class="nb-rw-label">Urut</div>
            <select class="nb-rw-select" name="sort">
              <option value="loaned_at" @selected($sort==='loaned_at')>Tanggal</option>
              <option value="loan_code" @selected($sort==='loan_code')>Kode</option>
              <option value="member" @selected($sort==='member')>Anggota</option>
              <option value="branch" @selected($sort==='branch')>Cabang</option>
              <option value="due_at" @selected($sort==='due_at')>Jatuh tempo</option>
              <option value="status" @selected($sort==='status')>Status</option>
            </select>
          </div>

          <div class="nb-rw-field" style="min-width:120px; flex:0;">
            <div class="nb-rw-label">Arah</div>
            <select class="nb-rw-select" name="dir">
              <option value="desc" @selected($dir==='desc')>Terbaru</option>
              <option value="asc" @selected($dir==='asc')>Terlama</option>
            </select>
          </div>

          <div class="nb-rw-field" style="min-width:120px; flex:0;">
            <div class="nb-rw-label">Per halaman</div>
            <select class="nb-rw-select" name="per_page">
              @foreach(['10','15','25','50'] as $pp)
                <option value="{{ $pp }}" @selected((string)$perPage===(string)$pp)>{{ $pp }}</option>
              @endforeach
            </select>
          </div>

          <div class="nb-rw-chips" style="flex: 1 1 100%;">
            <label class="nb-rw-chip" title="Tampilkan yang terlambat / jatuh tempo">
              <input type="checkbox" name="overdue" value="1" @checked($overdue==='1') />
              <span>Overdue saja</span>
            </label>
          </div>

          <div class="nb-rw-filteractions" style="margin-left:auto;">
            <button type="submit" class="nb-rw-apply">Terapkan</button>
            <a class="nb-rw-reset" href="{{ route('transaksi.riwayat', ['tab'=>'transaksi']) }}">Reset</a>
          </div>
        </div>
      </form>
    @else
      <form method="GET" action="{{ route('transaksi.riwayat') }}">
        <input type="hidden" name="tab" value="denda" />
        <div class="nb-rw-filterbar">
          <div class="nb-rw-field w-220">
            <div class="nb-rw-label">Cari</div>
            <input class="nb-rw-input" type="text" name="fine_q" value="{{ $fineQ }}" placeholder="Kode transaksi / nama / kode anggota / barcode" />
          </div>

          <div class="nb-rw-field">
            <div class="nb-rw-label">Status</div>
            <select class="nb-rw-select" name="fine_status">
              <option value="unpaid" @selected($fineStatus==='unpaid')>Belum bayar</option>
              <option value="paid" @selected($fineStatus==='paid')>Lunas</option>
              <option value="all" @selected($fineStatus==='all')>Semua</option>
            </select>
          </div>

          <div class="nb-rw-field" style="min-width:120px; flex:0;">
            <div class="nb-rw-label">Per halaman</div>
            <select class="nb-rw-select" name="fine_per_page">
              @foreach(['10','15','25','50'] as $pp)
                <option value="{{ $pp }}" @selected((string)$finePerPage===(string)$pp)>{{ $pp }}</option>
              @endforeach
            </select>
          </div>

          <div class="nb-rw-filteractions" style="margin-left:auto;">
            <button type="submit" class="nb-rw-apply">Terapkan</button>
            <a class="nb-rw-reset" href="{{ route('transaksi.riwayat', ['tab'=>'denda']) }}">Reset</a>
          </div>

          <div style="flex: 1 1 100%; display:flex; gap:10px; flex-wrap:wrap; margin-top:2px;">
            <div class="nb-rw-chip" title="Ringkasan denda belum bayar">
              Belum bayar: <strong>{{ (int)($fineSummary['count_unpaid'] ?? 0) }}</strong> • {{ $idr((int)($fineSummary['sum_unpaid'] ?? 0)) }}
            </div>
            <div class="nb-rw-chip" title="Ringkasan denda lunas">
              Lunas: <strong>{{ (int)($fineSummary['count_paid'] ?? 0) }}</strong> • {{ $idr((int)($fineSummary['sum_paid'] ?? 0)) }}
            </div>
          </div>
        </div>
      </form>
    @endif
  </div>

  <div style="height:12px;"></div>

  {{-- TABLE --}}
  <div class="nb-rw-card nb-rw-tablewrap">
    <div class="nb-rw-tablebox">
    <div class="nb-rw-desktop">
      <div class="nb-rw-table-scroll">
        @if($tab === 'transaksi')
          <table class="nb-rw-table">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Anggota</th>
                <th>Cabang</th>
                <th>Dipinjam</th>
                <th>Jatuh tempo</th>
                <th>Status</th>
                <th style="text-align:right;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              @forelse(($loans ?? []) as $l)
                @php
                  $isOverdue = false;
                  if(($l->status ?? '') === 'open' && !empty($l->due_at)){
                    $isOverdue = strtotime((string)$l->due_at) < time();
                  }
                  $b = $badge($l->status ?? 'open', $isOverdue);
                @endphp
                <tr>
                  <td style="white-space:nowrap;">
                    <a class="nb-rw-link" href="{{ route('transaksi.riwayat.detail', ['id' => $l->id]) }}">
                      {{ $l->loan_code }}
                    </a>
                    <div class="nb-rw-muted">
                      {{ (int)($l->items_open ?? 0) }}/{{ (int)($l->items_total ?? 0) }} item
                    </div>
                  </td>
                  <td>
                    <div style="font-weight:900; line-height:1.15;">{{ $l->member_name }}</div>
                    <div class="nb-rw-muted">{{ $l->member_code }}</div>
                    @php
                      $role = $l->member_type ?: 'member';
                      $policy = $policyFor($role);
                      $limit = (int)($policy['max_items'] ?? 0);
                      $nearFull = $limit > 0 && (int)($l->items_open ?? 0) >= max(1, $limit - 1);
                    @endphp
                    <div style="margin-top:4px; display:flex; gap:6px; flex-wrap:wrap;">
                      <span class="nb-rw-mini {{ $nearFull ? 'warn' : '' }}">
                        {{ strtoupper((string)$role) }} • Limit {{ $limit ?: '-' }}
                      </span>
                    </div>
                  </td>
                  <td>
                    <div style="font-weight:800;">{{ $l->branch_name ?? '-' }}</div>
                  </td>
                  <td style="white-space:nowrap;">
                    {{ $l->loaned_at ? \Carbon\Carbon::parse($l->loaned_at)->format('d M Y H:i') : '-' }}
                  </td>
                  <td style="white-space:nowrap;">
                    {{ $l->due_at ? \Carbon\Carbon::parse($l->due_at)->format('d M Y H:i') : '-' }}
                  </td>
                  <td style="white-space:nowrap;">
                    <span class="nb-rw-pillbadge" style="background:{{ $b['bg'] }}; border-color:{{ $b['bd'] }}; color:{{ $b['tx'] }}; border:1px solid {{ $b['bd'] }};">
                      {{ $b['lb'] }}
                    </span>
                  </td>
                  <td style="white-space:nowrap; text-align:right;">
                    <a class="nb-rw-btn" style="padding:8px 10px;" href="{{ route('transaksi.riwayat.detail', ['id' => $l->id]) }}">Detail</a>
                    {{-- Route print slip mengikuti routes: transaksi.riwayat.print (GET /transaksi/riwayat/{id}/print?size=80|58) --}}
                    <a class="nb-rw-btn" style="padding:8px 10px;" href="{{ route('transaksi.riwayat.print', ['id' => $l->id, 'size' => '80']) }}">Cetak</a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" style="padding:16px; color:rgba(15,23,42,.62);">
                    Tidak ada data transaksi.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        @else
          <table class="nb-rw-table">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Anggota</th>
                <th>Barcode</th>
                <th>Due</th>
                <th>Kembali</th>
                <th>Hari</th>
                <th>Nominal</th>
                <th>Status</th>
                <th style="text-align:right;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              @forelse(($fines ?? []) as $f)
                @php
                  $fb = $fineBadge($f->fine_status ?? 'unpaid');
                @endphp
                <tr>
                  <td style="white-space:nowrap;">
                    <a class="nb-rw-link" href="{{ route('transaksi.riwayat.detail', ['id' => $f->loan_id]) }}">
                      {{ $f->loan_code }}
                    </a>
                    <div class="nb-rw-muted">Loan Item #{{ (int)$f->loan_item_id }}</div>
                  </td>
                  <td>
                    <div style="font-weight:900; line-height:1.15;">{{ $f->member_name }}</div>
                    <div class="nb-rw-muted">{{ $f->member_code }}</div>
                  </td>
                  <td style="white-space:nowrap;">{{ $f->barcode }}</td>
                  <td style="white-space:nowrap;">{{ $f->due_at ? \Carbon\Carbon::parse($f->due_at)->format('d M Y') : '-' }}</td>
                  <td style="white-space:nowrap;">{{ $f->returned_at ? \Carbon\Carbon::parse($f->returned_at)->format('d M Y') : '-' }}</td>
                  <td style="white-space:nowrap;">{{ (int)($f->days_late ?? 0) }}</td>
                  <td style="white-space:nowrap; font-weight:900;">{{ $idr((int)($f->amount ?? 0)) }}</td>
                  <td style="white-space:nowrap;">
                    <span class="nb-rw-pillbadge" style="background:{{ $fb['bg'] }}; border-color:{{ $fb['bd'] }}; color:{{ $fb['tx'] }}; border:1px solid {{ $fb['bd'] }};">
                      {{ $fb['lb'] }}
                    </span>
                    @if(!empty($f->paid_amount))
                      <div class="nb-rw-muted">Terbayar: {{ $idr((int)$f->paid_amount) }}</div>
                    @endif
                  </td>
                  <td style="white-space:nowrap; text-align:right;">
                    <a class="nb-rw-btn" style="padding:8px 10px;" href="{{ route('transaksi.riwayat.detail', ['id' => $f->loan_id]) }}">Detail</a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="9" style="padding:16px; color:rgba(15,23,42,.62);">
                    Tidak ada data denda.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        @endif
      </div>
    </div>

    
    </div><!-- /.nb-rw-desktop -->

    <div class="nb-rw-mobile">
      @if($tab === 'transaksi')
        <div class="nb-rw-cards">
          @if(($loans ?? null) && count($loans))
            @foreach($loans as $l)
              @php
                $st = (string)($l->status ?? 'open');
                $loanedAt = $l->loaned_at ? \Carbon\Carbon::parse($l->loaned_at) : null;
                $dueAt = $l->due_at ? \Carbon\Carbon::parse($l->due_at) : null;
                $isOver = ($st === 'overdue') || ($st === 'open' && $dueAt && $dueAt->isPast());
                $statusLabel = $isOver ? 'Terlambat' : (($st === 'closed') ? 'Selesai' : 'Berjalan');
              @endphp

              <div class="nb-rw-card">
                <div class="nb-rw-card-hd">
                  <div style="min-width:0;">
                    <div class="nb-rw-card-title" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                      {{ $l->loan_code }}
                    </div>
                    <div class="nb-rw-card-sub">
                      {{ $l->member_name }} • {{ $l->branch_name }}
                    </div>
                  </div>

                  <div style="flex-shrink:0;">
              @php $b = $badge((string)$l->status, (string)$l->due_at); @endphp
              <span class="nb-rw-pillbadge" style="background:{{ $b['bg'] }}; border-color:{{ $b['bd'] }}; color:{{ $b['tx'] }}; border:1px solid {{ $b['bd'] }};">{{ $b['lb'] }}</span>
                  </div>
                </div>

                <div class="nb-rw-card-bd">
                  <div class="nb-rw-kv">
                    <div class="k">Tanggal</div>
                    <div class="v">{{ $loanedAt ? $loanedAt->format('d M Y') : '-' }}</div>
                  </div>
                  <div class="nb-rw-kv">
                    <div class="k">Jatuh tempo</div>
                    <div class="v">{{ $dueAt ? $dueAt->format('d M Y') : '-' }}</div>
                  </div>
                  <div class="nb-rw-kv">
                    <div class="k">Item</div>
                    <div class="v">{{ (int)($l->items_open ?? 0) }}/{{ (int)($l->items_total ?? 0) }}</div>
                  </div>
                  @php
                    $role = $l->member_type ?: 'member';
                    $policy = $policyFor($role);
                    $limit = (int)($policy['max_items'] ?? 0);
                    $nearFull = $limit > 0 && (int)($l->items_open ?? 0) >= max(1, $limit - 1);
                  @endphp
                  <div class="nb-rw-kv" style="align-items:center;">
                    <div class="k">Peran/Limit</div>
                    <div class="v" style="white-space:normal;">
                      <span class="nb-rw-mini {{ $nearFull ? 'warn' : '' }}">
                        {{ strtoupper((string)$role) }} • Limit {{ $limit ?: '-' }}
                      </span>
                    </div>
                  </div>

                  <div class="nb-rw-card-actions">
                    <a class="nb-btn nb-btn-primary" href="{{ route('transaksi.riwayat.detail', ['id' => $l->id]) }}" style="border-radius:14px; justify-content:center;">
                      Detail
                    </a>
                    @php
                      // print route optional
                      $printUrl = null;
                      if (\Illuminate\Support\Facades\Route::has('transaksi.print')) {
                        $printUrl = route('transaksi.print', ['id' => $l->id, 'size' => '80']);
                      } elseif (\Illuminate\Support\Facades\Route::has('transaksi.printSlip')) {
                        $printUrl = route('transaksi.printSlip', ['id' => $l->id, 'size' => '80']);
                      } elseif (\Illuminate\Support\Facades\Route::has('transaksi.print_slip')) {
                        $printUrl = route('transaksi.print_slip', ['id' => $l->id, 'size' => '80']);
                      }
                    @endphp
                    @if($printUrl)
                      <a class="nb-btn" href="{{ $printUrl }}" style="border-radius:14px; justify-content:center; border:1px solid rgba(17,24,39,.12); background:#fff;">
                        Cetak
                      </a>
                    @endif
                  </div>
                </div>
              </div>
            @endforeach
          @else
            <div class="nb-rw-card" style="padding:14px;">
              <div style="font-weight:700; color:#0f172a;">Tidak ada data</div>
              <div style="font-size:12px; color:rgba(15,23,42,.65); margin-top:4px;">
                Coba ubah filter atau rentang tanggal.
              </div>
            </div>
          @endif
        </div>
      @else
        <div class="nb-rw-cards">
          @if(($fines ?? null) && count($fines))
            @foreach($fines as $f)
              @php
                $due = $f->due_at ? \Carbon\Carbon::parse($f->due_at) : null;
                $ret = $f->returned_at ? \Carbon\Carbon::parse($f->returned_at) : null;
              @endphp

              <div class="nb-rw-card">
                <div class="nb-rw-card-hd">
                  <div style="min-width:0;">
                    <div class="nb-rw-card-title" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                      {{ $f->loan_code }} • {{ $f->barcode }}
                    </div>
                    <div class="nb-rw-card-sub">
                      {{ $f->member_name }} • {{ $f->member_code }}
                    </div>
                  </div>
                  <div style="flex-shrink:0;">
@php $fb = $fineBadge($f->fine_status ?? 'unpaid'); @endphp
                    <span class="nb-rw-pillbadge" style="background:{{ $fb['bg'] }}; border-color:{{ $fb['bd'] }}; color:{{ $fb['tx'] }}; border:1px solid {{ $fb['bd'] }};">
                      {{ $fb['lb'] }}
                    </span>
                  </div>
                </div>

                <div class="nb-rw-card-bd">
                  <div class="nb-rw-kv">
                    <div class="k">Jatuh tempo</div>
                    <div class="v">{{ $due ? $due->format('d M Y') : '-' }}</div>
                  </div>
                  <div class="nb-rw-kv">
                    <div class="k">Dikembalikan</div>
                    <div class="v">{{ $ret ? $ret->format('d M Y') : '-' }}</div>
                  </div>
                  <div class="nb-rw-kv">
                    <div class="k">Telat</div>
                    <div class="v">{{ (int)($f->days_late ?? 0) }} hari</div>
                  </div>
                  <div class="nb-rw-kv">
                    <div class="k">Nominal</div>
                    <div class="v">Rp {{ number_format((int)($f->amount ?? 0), 0, ',', '.') }}</div>
                  </div>

                  <div class="nb-rw-card-actions">
                    <a class="nb-btn nb-btn-primary" href="{{ route('transaksi.riwayat.detail', ['id' => $f->loan_id]) }}" style="border-radius:14px; justify-content:center;">
                      Detail
                    </a>
                  </div>
                </div>
              </div>
            @endforeach
          @else
            <div class="nb-rw-card" style="padding:14px;">
              <div style="font-weight:700; color:#0f172a;">Tidak ada data denda</div>
              <div style="font-size:12px; color:rgba(15,23,42,.65); margin-top:4px;">
                Jika ada keterlambatan, data akan muncul di sini.
              </div>
            </div>
          @endif
        </div>
      @endif
    </div><!-- /.nb-rw-mobile -->


    {{-- Pagination --}}
    <div style="padding: 12px 12px 2px 12px;">
      @if($tab === 'transaksi')
        @if(method_exists(($loans ?? null), 'links'))
          {{ $loans->links() }}
        @endif
      @else
        @if(method_exists(($fines ?? null), 'links'))
          {{ $fines->links() }}
        @endif
      @endif
    </div>
  </div>
</div>
@endsection






