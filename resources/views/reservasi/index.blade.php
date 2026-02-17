@extends('layouts.notobuku')

@section('title', 'Reservasi Buku • NOTOBUKU')

@section('content')
@php
  $mode = $mode ?? 'member'; // member | staff
  $scopeLabel = $scopeLabel ?? 'Reservasi';
  $items = $items ?? collect();
  $filter = $filter ?? request('filter', 'all'); // all|queued|ready|done
  $q = $q ?? request('q', '');
  $canCreate = $canCreate ?? false;
  $canManage = $canManage ?? false;
  $memberLinked = $memberLinked ?? true;

  // paginator safety
  $allItems = $items;
  if (is_object($items) && method_exists($items, 'getCollection')) $allItems = $items->getCollection();

  $countAll = 0; $countQueued = 0; $countReady = 0; $countDone = 0;
  foreach ($allItems as $x) {
    $countAll++;
    $st = (string)($x->status ?? 'queued');
    if ($st === 'queued') $countQueued++;
    if ($st === 'ready') $countReady++;
    if (in_array($st, ['fulfilled','cancelled','expired'], true)) $countDone++;
  }

  $filterLabel = match($filter) {
    'all' => 'Semua',
    'queued' => 'Menunggu',
    'ready' => 'Tersedia',
    'done' => 'Riwayat',
    default => 'Semua',
  };

  $badge = function(string $status){
    return match($status){
      'queued' => ['bg'=>'rgba(245,158,11,.20)','tx'=>'rgba(124,82,0,.96)','lb'=>'Menunggu'],
      'ready' => ['bg'=>'rgba(39,174,96,.18)','tx'=>'rgba(12,72,39,.96)','lb'=>'Tersedia'],
      'fulfilled' => ['bg'=>'rgba(99,102,241,.18)','tx'=>'rgba(30,27,75,.94)','lb'=>'Dipenuhi'],
      'cancelled' => ['bg'=>'rgba(229,57,53,.16)','tx'=>'rgba(127,29,29,.94)','lb'=>'Dibatalkan'],
      'expired' => ['bg'=>'rgba(107,114,128,.20)','tx'=>'rgba(55,65,81,.94)','lb'=>'Kedaluwarsa'],
      default => ['bg'=>'rgba(107,114,128,.20)','tx'=>'rgba(55,65,81,.94)','lb'=>$status],
    };
  };

  $katalogShowExists = \Illuminate\Support\Facades\Route::has('katalog.show');
@endphp

@include('partials.flash')

<style>
  .nb-wrap{ max-width:1100px; margin:0 auto; }

  .nb-card{
    background: rgba(255,255,255,.92);
    border: 1px solid rgba(15,23,42,.08);
    border-radius: 18px;
    box-shadow: 0 12px 30px rgba(2,6,23,.06);
    overflow:hidden;
  }
  html.dark .nb-card{
    background: rgba(15,23,42,.58);
    border-color: rgba(148,163,184,.14);
    box-shadow: 0 14px 34px rgba(0,0,0,.32);
  }

  /* ===========================
     NO HOVER EFFECTS ✅
     (button/icon/table row)
     =========================== */
  .nb-btn,
  .nb-icon-btn{
    transition:none !important;
  }
  .nb-btn:hover,
  .nb-btn:active,
  .nb-btn:focus{
    filter:none !important;
    transform:none !important;
    box-shadow:none !important;
    opacity:1 !important;
  }
  .nb-btn:hover{ background: inherit !important; }

  .nb-icon-btn:hover,
  .nb-icon-btn:active,
  .nb-icon-btn:focus{
    background: inherit !important;
    filter:none !important;
    transform:none !important;
    box-shadow:none !important;
    opacity:1 !important;
  }

  .nb-table tbody tr:hover{ background: inherit !important; }
  html.dark .nb-table tbody tr:hover{ background: inherit !important; }

  /* Page head */
  .nb-page-head{
    display:flex; align-items:flex-start; justify-content:space-between;
    gap:12px; flex-wrap:wrap;
  }
  .nb-page-head-main{ min-width:0; flex:1; }
  .nb-actions{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:flex-end; }

  /* ======== TEXT (lebih ringan) ======== */
  .nb-title{
    font-size:15px;
    font-weight:550;
    letter-spacing:.10px;
    color: var(--nb-navy, #0B2545);
    line-height:1.15;
  }
  .nb-sub{
    font-size:13px;
    font-weight:450;
    color: rgba(11,37,69,.64);
    line-height:1.35;
  }
  html.dark .nb-title{ color: rgba(226,232,240,.92); }
  html.dark .nb-sub{ color: rgba(226,232,240,.64); }

  /* KPI */
  .nb-grid{ display:grid; gap:14px; }
  .nb-grid.kpi{ grid-template-columns: repeat(12, 1fr); }

  .col-3{ grid-column: span 3; }
  @media (max-width: 980px){ .col-3{ grid-column: span 6; } }
  @media (max-width: 560px){ .col-3{ grid-column: span 6; } } /* 2x2 on mobile */
  @media (max-width: 360px){ .col-3{ grid-column: span 12; } }

  .nb-kpi{
    padding:14px;
    border-radius:18px;
    overflow:hidden;
    border: 1px solid rgba(255,255,255,.32);
    box-shadow: inset 0 -1px 0 rgba(0,0,0,.06);
  }
  html.dark .nb-kpi{ border-color: rgba(255,255,255,.08); }

  .nb-kpi.blue   { background:#E8F2FF; border-color: rgba(30,136,229,.25); box-shadow: inset 0 -1px 0 rgba(30,136,229,.30); }
  .nb-kpi.amber  { background:#FFF4DF; border-color: rgba(245,158,11,.28); box-shadow: inset 0 -1px 0 rgba(245,158,11,.32); }
  .nb-kpi.green  { background:#E9FBF1; border-color: rgba(39,174,96,.25); box-shadow: inset 0 -1px 0 rgba(39,174,96,.30); }
  .nb-kpi.indigo { background:#EEF0FF; border-color: rgba(99,102,241,.25); box-shadow: inset 0 -1px 0 rgba(99,102,241,.30); }

  html.dark .nb-kpi.blue   { background: rgba(30,136,229,.16); border-color: rgba(30,136,229,.22); box-shadow: inset 0 -1px 0 rgba(30,136,229,.45); }
  html.dark .nb-kpi.amber  { background: rgba(245,158,11,.14); border-color: rgba(245,158,11,.22); box-shadow: inset 0 -1px 0 rgba(245,158,11,.45); }
  html.dark .nb-kpi.green  { background: rgba(39,174,96,.16); border-color: rgba(39,174,96,.22); box-shadow: inset 0 -1px 0 rgba(39,174,96,.45); }
  html.dark .nb-kpi.indigo { background: rgba(99,102,241,.16); border-color: rgba(99,102,241,.22); box-shadow: inset 0 -1px 0 rgba(99,102,241,.45); }

  .nb-kpi-label{ font-size:13px; font-weight:500; color: rgba(11,37,69,.78); }
  html.dark .nb-kpi-label{ color: rgba(226,232,240,.78); }
  .nb-kpi-value{ margin-top:8px; font-size:22px; font-weight:650; line-height:1.05; color: rgba(11,37,69,.98); }
  html.dark .nb-kpi-value{ color: rgba(226,232,240,.92); }

  /* Inputs */
  .nb-field-label{
    font-size:12px;
    font-weight:500;
    letter-spacing:.01em;
    color: rgba(11,37,69,.70);
    margin-bottom:8px;
  }
  html.dark .nb-field-label{ color: rgba(226,232,240,.72); }

  .nb-input-strong{
    padding:12px 14px !important;
    border-radius:14px !important;
    border:1px solid rgba(30,136,229,.22) !important; /* lebih tipis */
    background:rgba(232,242,255,.45) !important;
  }
  html.dark .nb-input-strong{
    border-color: rgba(30,136,229,.30) !important;
    background: rgba(30,136,229,.14) !important;
  }

  .resv-bar{ display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
  .search-block{ flex: 1; min-width: 320px; }

  .resv-search{
    display:flex;
    align-items:center;
    gap:10px;
    height:52px;
    color: rgba(11,37,69,.92);
  }
  html.dark .resv-search{ color: rgba(226,232,240,.88); }

  .resv-search input{
    border:0; outline:none; background:transparent; width:100%;
    font-weight:450;
    color: inherit;
  }
  .resv-search input::placeholder{
    font-weight:400;
    color: rgba(11,37,69,.55);
  }
  html.dark .resv-search input::placeholder{ color: rgba(226,232,240,.55); }

  .resv-search .kbd{
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-weight:550;
    font-size:11px;
    padding:2px 10px;
    border-radius:999px;
    border:1px solid rgba(30,136,229,.18);
    background: rgba(255,255,255,.60);
    color: rgba(11,37,69,.70);
    white-space:nowrap;
  }
  html.dark .resv-search .kbd{
    background: rgba(255,255,255,.06);
    border-color: rgba(148,163,184,.18);
    color: rgba(226,232,240,.72);
  }

  /* Table */
  .nb-table{ width:100%; border-collapse:separate; border-spacing:0; }
  .nb-table thead th{
    text-align:left;
    font-size:12px;
    letter-spacing:.06em;
    text-transform:uppercase;
    padding:12px 16px;
    border-bottom:1px solid rgba(15,23,42,.08);
    color: rgba(11,37,69,.62);
    background: rgba(2,6,23,.02);
    white-space:nowrap;
    font-weight:500;
  }
  html.dark .nb-table thead th{
    color: rgba(226,232,240,.62);
    border-bottom-color: rgba(148,163,184,.14);
    background: rgba(255,255,255,.04);
  }
  .nb-table tbody td{
    padding:14px 16px;
    border-bottom:1px solid rgba(15,23,42,.08);
    vertical-align:top;
    font-weight:420;
  }
  html.dark .nb-table tbody td{ border-bottom-color: rgba(148,163,184,.14); }

  .nb-table tbody tr:nth-child(even){ background: rgba(2,6,23,.015); }
  html.dark .nb-table tbody tr:nth-child(even){ background: rgba(255,255,255,.03); }

  .nb-badge{
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 12px;
    border-radius:999px;
    font-weight:550;
    font-size:12px;
    border:1px solid transparent;
    white-space:nowrap;
  }

  .nb-help{ font-size:13px; color: rgba(11,37,69,.68); font-weight:420; }
  html.dark .nb-help{ color: rgba(226,232,240,.68); }

  /* ========= ACTION ICONS ========= */
  .nb-icon-btn{
    width:42px;
    height:42px;
    border-radius:14px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.70);
    color: rgba(11,37,69,.92);
    cursor:pointer;
    text-decoration:none;
    font-weight:550;
  }
  html.dark .nb-icon-btn{
    border-color: rgba(148,163,184,.16);
    background: rgba(255,255,255,.06);
    color: rgba(226,232,240,.92);
  }

  /* ========= MODAL ========= */
  .nb-modal{
    position:fixed;
    inset:0;
    z-index:9999;
    display:none;
  }
  .nb-modal[data-open="1"]{ display:block; }

  .nb-modal-backdrop{
    position:absolute;
    inset:0;
    background: rgba(2,6,23,.55);
  }

  .nb-modal-panel{
    position:absolute;
    left:50%;
    top:50%;
    transform: translate(-50%,-50%);
    width:min(560px, calc(100% - 28px));
    background: rgba(255,255,255,.98);
    border:1px solid rgba(15,23,42,.10);
    border-radius:18px;
    box-shadow: 0 22px 60px rgba(0,0,0,.30);
    overflow:hidden;
  }
  html.dark .nb-modal-panel{
    background: rgba(15,23,42,.92);
    border-color: rgba(148,163,184,.16);
    box-shadow: 0 26px 70px rgba(0,0,0,.55);
  }

  .nb-modal-head{
    padding:14px 16px;
    border-bottom:1px solid rgba(15,23,42,.08);
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
  }
  html.dark .nb-modal-head{ border-bottom-color: rgba(148,163,184,.14); }

  .nb-modal-title{
    font-size:14px;
    font-weight:550;
    color:#0B2545;
    line-height:1.2;
  }
  html.dark .nb-modal-title{ color: rgba(226,232,240,.92); }

  .nb-modal-sub{
    margin-top:4px;
    font-size:12px;
    font-weight:450;
    color: rgba(11,37,69,.64);
  }
  html.dark .nb-modal-sub{ color: rgba(226,232,240,.64); }

  .nb-modal-body{ padding:14px 16px; }

  .nb-detail-grid{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:12px;
  }
  @media(max-width:520px){
    .nb-detail-grid{ grid-template-columns: 1fr; }
  }

  .nb-detail-card{
    border:1px solid rgba(15,23,42,.08);
    background: rgba(2,6,23,.02);
    border-radius:16px;
    padding:12px 12px;
  }
  html.dark .nb-detail-card{
    border-color: rgba(148,163,184,.14);
    background: rgba(255,255,255,.04);
  }

  .nb-detail-k{
    font-size:12px;
    font-weight:500;
    color: rgba(11,37,69,.62);
  }
  html.dark .nb-detail-k{ color: rgba(226,232,240,.62); }

  .nb-detail-v{
    margin-top:6px;
    font-size:13px;
    font-weight:450;
    color: rgba(11,37,69,.92);
    line-height:1.35;
    word-break: break-word;
  }
  html.dark .nb-detail-v{ color: rgba(226,232,240,.90); }

  .nb-modal-foot{
    padding:14px 16px;
    border-top:1px solid rgba(15,23,42,.08);
    display:flex;
    gap:10px;
    justify-content:flex-end;
    flex-wrap:wrap;
  }
  html.dark .nb-modal-foot{ border-top-color: rgba(148,163,184,.14); }

  @media(max-width:620px){
    .nb-modal-panel{
      left:50%;
      top:auto;
      bottom:14px;
      transform: translate(-50%, 0);
      width: calc(100% - 22px);
      border-radius:20px;
    }
  }

  /* ================================
     MOBILE: TABLE -> CARD LIST
     ================================ */
  .resv-table-wrap{ display:block; }
  .resv-cards{ display:none; }

  @media(max-width:860px){
    .resv-table-wrap{ display:none; }
    .resv-cards{ display:block; }
  }

  .resv-card{
    border-top:1px solid rgba(15,23,42,.08);
    padding:14px 14px;
    display:flex;
    gap:12px;
    align-items:flex-start;
  }
  html.dark .resv-card{ border-top-color: rgba(148,163,184,.14); }
  .resv-card:first-child{ border-top:0; }

  .resv-card-main{ flex:1; min-width:0; }

  .resv-card-title{
    font-size:14px;
    font-weight:550;
    color: rgba(11,37,69,.98);
    line-height:1.25;
  }
  html.dark .resv-card-title{ color: rgba(226,232,240,.92); }

  .resv-card-meta{
    margin-top:10px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    font-size:13px;
    color: rgba(11,37,69,.68);
    font-weight:420;
    line-height:1.6;
  }
  html.dark .resv-card-meta{ color: rgba(226,232,240,.66); }

  .resv-card-actions{
    display:flex;
    gap:8px;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
  }

  .resv-card-badge{
    display:flex;
    align-items:center;
    justify-content:flex-start;
    margin-top:10px;
  }

  .resv-card-notes{
    margin-top:8px;
    font-size:13px;
    color: rgba(11,37,69,.70);
    font-weight:420;
    line-height:1.45;
  }
  html.dark .resv-card-notes{ color: rgba(226,232,240,.70); }
</style>

<div class="nb-wrap">

  {{-- Page head --}}
  <div class="nb-page-head" style="margin-bottom:14px;">
    <div class="nb-page-head-main">
      <div class="nb-title">Reservasi Buku</div>
      <div class="nb-sub">
        {{ $scopeLabel }} • <span style="font-weight:500;">{{ $filterLabel }}</span>
        @if($mode==='staff')
          <span class="nb-chip" style="margin-left:8px;font-weight:500;background:rgba(39,174,96,.10);color:#1E7A44;border-color:transparent;">
            Mode Staff
          </span>
        @else
          <span class="nb-chip" style="margin-left:8px;font-weight:500;background:rgba(47,128,237,.10);color:#1F3A5F;border-color:transparent;">
            Mode Member
          </span>
        @endif
      </div>

      @if($mode==='member' && !$memberLinked)
        <div class="nb-card" style="margin-top:12px;padding:12px 14px;border-radius:16px;">
          <div class="nb-help">
            Akun Anda belum terhubung ke data <b>member</b>. Hubungi petugas untuk sinkronisasi akun.
          </div>
        </div>
      @endif
    </div>

    <div class="nb-actions">
      @if($canCreate)
        <a href="#buat-reservasi" class="nb-btn nb-btn-primary">Buat Reservasi</a>
      @endif
    </div>
  </div>

  {{-- KPI --}}
  <div class="nb-grid kpi" style="margin-bottom:14px;">
    <div class="col-3">
      <div class="nb-kpi blue">
        <div class="nb-kpi-label">Total (halaman ini)</div>
        <div class="nb-kpi-value">{{ $countAll }}</div>
      </div>
    </div>
    <div class="col-3">
      <div class="nb-kpi amber">
        <div class="nb-kpi-label">Menunggu</div>
        <div class="nb-kpi-value">{{ $countQueued }}</div>
      </div>
    </div>
    <div class="col-3">
      <div class="nb-kpi green">
        <div class="nb-kpi-label">Tersedia</div>
        <div class="nb-kpi-value">{{ $countReady }}</div>
      </div>
    </div>
    <div class="col-3">
      <div class="nb-kpi indigo">
        <div class="nb-kpi-label">Riwayat</div>
        <div class="nb-kpi-value">{{ $countDone }}</div>
      </div>
    </div>
  </div>

  {{-- Search + Filter --}}
  <div class="nb-card" style="padding:14px;margin-bottom:14px;">
    <form method="GET" action="{{ route('reservasi.index') }}" class="resv-bar">
      <input type="hidden" name="filter" value="{{ $filter }}">

      <div class="search-block">
        <div class="nb-field-label">Cari Reservasi</div>
        <div class="nb-input nb-input-strong resv-search">
          <span style="opacity:.78;font-size:15px;">🔎</span>
          <input
            name="q"
            value="{{ $q }}"
            placeholder="{{ $mode==='staff' ? 'Ketik judul / nama member / kode member…' : 'Ketik judul buku / catatan…' }}"
          />
          <span class="kbd">Enter</span>
        </div>
      </div>

      <div style="min-width:220px;">
        <div class="nb-field-label">Filter</div>
        <div class="nb-input nb-input-strong" style="height:52px;display:flex;align-items:center;">
          <select
            onchange="location=this.value"
            style="border:0;outline:none;background:transparent;color:inherit;font-weight:450;cursor:pointer;width:100%;"
            aria-label="Filter reservasi"
          >
            <option value="{{ route('reservasi.index',['filter'=>'all','q'=>$q]) }}" {{ $filter==='all'?'selected':'' }}>Semua</option>
            <option value="{{ route('reservasi.index',['filter'=>'queued','q'=>$q]) }}" {{ $filter==='queued'?'selected':'' }}>Menunggu</option>
            <option value="{{ route('reservasi.index',['filter'=>'ready','q'=>$q]) }}" {{ $filter==='ready'?'selected':'' }}>Tersedia</option>
            <option value="{{ route('reservasi.index',['filter'=>'done','q'=>$q]) }}" {{ $filter==='done'?'selected':'' }}>Riwayat</option>
          </select>
        </div>
      </div>

      <div style="display:flex;gap:10px;align-items:flex-end;">
        <div style="height:20px;"></div>
        <button class="nb-btn nb-btn-soft" type="submit" style="height:52px;">Cari</button>
        @if(trim((string)$q) !== '')
          <a class="nb-btn nb-btn-soft" href="{{ route('reservasi.index',['filter'=>$filter]) }}" style="height:52px;display:inline-flex;align-items:center;">Reset</a>
        @endif
      </div>
    </form>
  </div>

  {{-- Create (Barcode) --}}
  @if($canCreate)
    <div id="buat-reservasi" class="nb-card" style="padding:14px;margin-bottom:14px;">
      <div class="nb-page-head" style="margin-bottom:10px;">
        <div class="nb-page-head-main">
          <div class="nb-title">Buat Reservasi</div>
          <div class="nb-sub">Scan barcode pada buku, atau masukkan kode barcode untuk membuat reservasi.</div>
        </div>
      </div>

      <form method="POST" action="{{ route('reservasi.store') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        @csrf

        @if($mode==='staff')
          <div style="min-width:220px;flex:1;">
            <div class="nb-field-label">Member ID</div>
            <div class="nb-input nb-input-strong" style="height:52px;display:flex;align-items:center;">
              <input
                name="member_id"
                value="{{ old('member_id') }}"
                placeholder="contoh: 12"
                style="border:0;outline:none;width:100%;background:transparent;color:inherit;font-weight:450;"
              >
            </div>
          </div>
        @endif

        <div style="min-width:240px;flex:1.2;">
          <div class="nb-field-label">Barcode Buku</div>
          <div class="nb-input nb-input-strong" style="height:52px;display:flex;align-items:center;">
            <input
              name="barcode"
              value="{{ old('barcode', old('biblio_id')) }}"
              placeholder="scan / ketik barcode…"
              required
              inputmode="numeric"
              style="border:0;outline:none;width:100%;background:transparent;color:inherit;font-weight:450;"
            >
          </div>
        </div>

        <div style="min-width:260px;flex:2;">
          <div class="nb-field-label">Catatan (opsional)</div>
          <div class="nb-input nb-input-strong" style="height:52px;display:flex;align-items:center;">
            <input
              name="notes"
              value="{{ old('notes') }}"
              placeholder="misal: ambil di cabang tertentu"
              style="border:0;outline:none;width:100%;background:transparent;color:inherit;font-weight:420;"
            >
          </div>
        </div>

        <button class="nb-btn nb-btn-primary" type="submit" style="height:52px;">Buat Reservasi</button>
      </form>

      @error('barcode') <div class="nb-muted" style="color:#b91c1c;margin-top:8px;">{{ $message }}</div> @enderror
      @error('notes') <div class="nb-muted" style="color:#b91c1c;margin-top:6px;">{{ $message }}</div> @enderror
      @error('member_id') <div class="nb-muted" style="color:#b91c1c;margin-top:6px;">{{ $message }}</div> @enderror
    </div>
  @endif

  {{-- List --}}
  <div class="nb-card" style="padding:0;">
    <div style="padding:14px 16px;border-bottom:1px solid rgba(15,23,42,.08);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
      <div style="font-weight:500;">Daftar Reservasi</div>
      <div class="nb-help">
        Total: <span style="font-weight:500;">{{ $countAll }}</span>
        @if(trim((string)$q) !== '')
          • Pencarian: <span style="font-weight:500;">{{ $q }}</span>
        @endif
      </div>
    </div>

    @if(method_exists($items,'isEmpty') ? $items->isEmpty() : (count($items)===0))
      <div style="padding:26px;text-align:center;" class="nb-muted">
        Belum ada data reservasi. Scan barcode buku atau cari di <span style="font-weight:500;">Katalog</span> untuk mulai reservasi.
      </div>
    @else

      {{-- MOBILE: CARD LIST --}}
      <div class="resv-cards">
        @foreach($items as $i => $r)
          @php
            $status = (string)($r->status ?? 'queued');
            $b = $badge($status);

            $readyAt = !empty($r->ready_at) ? \Carbon\Carbon::parse($r->ready_at)->format('d M Y H:i') : null;
            $expiresAt = !empty($r->expires_at) ? \Carbon\Carbon::parse($r->expires_at)->format('d M Y H:i') : null;
            $createdAt = !empty($r->created_at) ? \Carbon\Carbon::parse($r->created_at)->format('d M Y') : null;

            $title = trim((string)($r->biblio_title ?? ''));
            if ($title === '') $title = 'Tanpa judul';

            $bookUrl = (!empty($r->biblio_id) && $katalogShowExists) ? route('katalog.show', $r->biblio_id) : '#';
            $notes = (string)($r->notes ?? '');
          @endphp

          <div class="resv-card">
            <div class="resv-card-main">
              <div class="resv-card-title">{{ $title }}</div>

              <div class="resv-card-badge">
                <span class="nb-badge" style="background:{{ $b['bg'] }};color:{{ $b['tx'] }};">
                  {{ $b['lb'] }}
                </span>
              </div>

              <div class="resv-card-meta">
                @if($createdAt) <span><span style="opacity:.8;">Dibuat:</span> <span style="font-weight:500;">{{ $createdAt }}</span></span>@endif
                @if($readyAt) <span><span style="opacity:.8;">Siap:</span> <span style="font-weight:500;">{{ $readyAt }}</span></span>@endif
                @if($status==='ready' && $expiresAt) <span><span style="opacity:.8;">Batas:</span> <span style="font-weight:500;">{{ $expiresAt }}</span></span>@endif
              </div>

              @if($notes !== '')
                <div class="resv-card-notes">Catatan: {{ $notes }}</div>
              @endif

              @if($mode==='staff')
                <div class="resv-card-meta" style="margin-top:10px;">
                  <span>Kode: <span style="font-weight:500;">{{ $r->member_code ?? '-' }}</span></span>
                  <span>Nama: <span style="font-weight:500;">{{ $r->member_name ?? '-' }}</span></span>
                </div>
              @endif
            </div>

            <div class="resv-card-actions">
              @if($canManage && in_array($status, ['queued','ready'], true))
                @if($mode==='staff')
                  <form method="POST" action="{{ route('reservasi.fulfill', $r->id) }}"
                        onsubmit="return confirm('Tandai reservasi ini sebagai terpenuhi?')">
                    @csrf
                    <button class="nb-btn nb-btn-primary" type="submit" style="height:42px;padding:10px 12px;">Penuhi</button>
                  </form>
                @endif

                <form method="POST" action="{{ route('reservasi.cancel', $r->id) }}"
                      onsubmit="return confirm('Batalkan reservasi ini?')">
                  @csrf
                  <button class="nb-btn nb-btn-soft" type="submit" style="height:42px;padding:10px 12px;">Batal</button>
                </form>
              @endif

              @if($mode==='member' && in_array($status, ['cancelled','expired'], true))
                <form method="POST" action="{{ route('member.reservasi.requeue', $r->id) }}">
                  @csrf
                  <button class="nb-btn nb-btn-soft" type="submit" style="height:42px;padding:10px 12px;">Antre Ulang</button>
                </form>
              @endif

              <button
                type="button"
                class="nb-icon-btn"
                title="Detail"
                aria-label="Lihat detail reservasi"
                data-resv-open
                data-id="{{ $r->id ?? '' }}"
                data-title="{{ e($title) }}"
                data-status="{{ e($b['lb']) }}"
                data-created="{{ e((string)($createdAt ?? '-')) }}"
                data-ready="{{ e((string)($readyAt ?? '-')) }}"
                data-exp="{{ e((string)($expiresAt ?? '-')) }}"
                data-notes="{{ e($notes !== '' ? $notes : '-') }}"
                @if($mode==='staff')
                  data-member-name="{{ e((string)($r->member_name ?? '-')) }}"
                  data-member-code="{{ e((string)($r->member_code ?? '-')) }}"
                @endif
              >👁</button>

              <a class="nb-icon-btn" href="{{ $bookUrl }}" title="Lihat Buku" aria-label="Lihat buku">📘</a>
            </div>
          </div>
        @endforeach
      </div>

      {{-- DESKTOP/TABLET: TABLE --}}
      <div class="resv-table-wrap">
        <div style="overflow:auto;">
          <table class="nb-table">
            <thead>
              <tr>
                <th style="width:60px;">No</th>
                <th>Judul Buku</th>
                @if($mode==='staff')
                  <th style="min-width:150px;">Kode Member</th>
                  <th style="min-width:180px;">Nama Member</th>
                @endif
                <th style="min-width:150px;">Status</th>
                <th style="min-width:190px;">Info</th>
                <th style="min-width:160px;text-align:right;">Aksi</th>
              </tr>
            </thead>

            <tbody>
              @foreach($items as $i => $r)
                @php
                  $status = (string)($r->status ?? 'queued');
                  $b = $badge($status);

                  $readyAt = !empty($r->ready_at) ? \Carbon\Carbon::parse($r->ready_at)->format('d M Y H:i') : null;
                  $expiresAt = !empty($r->expires_at) ? \Carbon\Carbon::parse($r->expires_at)->format('d M Y H:i') : null;
                  $createdAt = !empty($r->created_at) ? \Carbon\Carbon::parse($r->created_at)->format('d M Y') : null;

                  $title = trim((string)($r->biblio_title ?? ''));
                  if ($title === '') $title = 'Tanpa judul';

                  $bookUrl = (!empty($r->biblio_id) && $katalogShowExists) ? route('katalog.show', $r->biblio_id) : '#';
                  $notes = (string)($r->notes ?? '');
                @endphp

                <tr>
                  <td><div style="font-weight:450;">{{ is_numeric($i) ? $i+1 : '-' }}</div></td>

                  <td>
                    <div style="font-weight:550;color:rgba(11,37,69,.98);">{{ $title }}</div>
                    @if($notes !== '')
                      <div class="nb-help" style="margin-top:6px;">Catatan: {{ $notes }}</div>
                    @endif
                  </td>

                  @if($mode==='staff')
                    <td><div style="font-weight:450;">{{ $r->member_code ?? '-' }}</div></td>
                    <td><div style="font-weight:450;">{{ $r->member_name ?? '-' }}</div></td>
                  @endif

                  <td>
                    <span class="nb-badge" style="background:{{ $b['bg'] }};color:{{ $b['tx'] }};">
                      {{ $b['lb'] }}
                    </span>
                  </td>

                  <td>
                    <div class="nb-help" style="line-height:1.55;">
                      @if($createdAt) <span style="opacity:.8;">Dibuat:</span> <span style="font-weight:500;">{{ $createdAt }}</span><br>@endif
                      @if($readyAt) <span style="opacity:.8;">Siap:</span> <span style="font-weight:500;">{{ $readyAt }}</span><br>@endif
                      @if($status==='ready' && $expiresAt) <span style="opacity:.8;">Batas ambil:</span> <span style="font-weight:500;">{{ $expiresAt }}</span>@endif
                    </div>
                  </td>

                  <td style="text-align:right;">
                    <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;flex-wrap:wrap;">

                      @if($canManage && in_array($status, ['queued','ready'], true))
                        @if($mode==='staff')
                          <form method="POST" action="{{ route('reservasi.fulfill', $r->id) }}"
                                onsubmit="return confirm('Tandai reservasi ini sebagai terpenuhi?')">
                            @csrf
                            <button class="nb-btn nb-btn-primary" type="submit">Penuhi</button>
                          </form>
                        @endif

                        <form method="POST" action="{{ route('reservasi.cancel', $r->id) }}"
                              onsubmit="return confirm('Batalkan reservasi ini?')">
                          @csrf
                          <button class="nb-btn nb-btn-soft" type="submit">Batal</button>
                        </form>
                      @endif

                      @if($mode==='member' && in_array($status, ['cancelled','expired'], true))
                        <form method="POST" action="{{ route('member.reservasi.requeue', $r->id) }}">
                          @csrf
                          <button class="nb-btn nb-btn-soft" type="submit">Antre Ulang</button>
                        </form>
                      @endif

                      <button
                        type="button"
                        class="nb-icon-btn"
                        title="Detail"
                        aria-label="Lihat detail reservasi"
                        data-resv-open
                        data-id="{{ $r->id ?? '' }}"
                        data-title="{{ e($title) }}"
                        data-status="{{ e($b['lb']) }}"
                        data-created="{{ e((string)($createdAt ?? '-')) }}"
                        data-ready="{{ e((string)($readyAt ?? '-')) }}"
                        data-exp="{{ e((string)($expiresAt ?? '-')) }}"
                        data-notes="{{ e($notes !== '' ? $notes : '-') }}"
                        @if($mode==='staff')
                          data-member-name="{{ e((string)($r->member_name ?? '-')) }}"
                          data-member-code="{{ e((string)($r->member_code ?? '-')) }}"
                        @endif
                      >👁</button>

                      <a class="nb-icon-btn" href="{{ $bookUrl }}" title="Lihat Buku" aria-label="Lihat buku">📘</a>

                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      @if(method_exists($items,'links'))
        <div style="padding:12px 16px;">
          {{ $items->links() }}
        </div>
      @endif

    @endif
  </div>

</div>

{{-- Modal: Detail Reservasi --}}
<div class="nb-modal" id="nbResvModal" aria-hidden="true">
  <div class="nb-modal-backdrop" data-modal-close></div>

  <div class="nb-modal-panel" role="dialog" aria-modal="true" aria-labelledby="nbResvModalTitle">
    <div class="nb-modal-head">
      <div>
        <div class="nb-modal-title" id="nbResvModalTitle">Detail Reservasi</div>
        <div class="nb-modal-sub" id="nbResvModalSub">—</div>
      </div>
      <button type="button" class="nb-icon-btn" data-modal-close aria-label="Tutup" title="Tutup">✕</button>
    </div>

    <div class="nb-modal-body">
      <div class="nb-detail-grid">
        <div class="nb-detail-card">
          <div class="nb-detail-k">Judul</div>
          <div class="nb-detail-v" id="mTitle">—</div>
        </div>

        <div class="nb-detail-card">
          <div class="nb-detail-k">Status</div>
          <div class="nb-detail-v" id="mStatus">—</div>
        </div>

        <div class="nb-detail-card">
          <div class="nb-detail-k">Dibuat</div>
          <div class="nb-detail-v" id="mCreated">—</div>
        </div>

        <div class="nb-detail-card">
          <div class="nb-detail-k">Siap</div>
          <div class="nb-detail-v" id="mReady">—</div>
        </div>

        <div class="nb-detail-card">
          <div class="nb-detail-k">Batas ambil</div>
          <div class="nb-detail-v" id="mExp">—</div>
        </div>

        @if($mode==='staff')
          <div class="nb-detail-card">
            <div class="nb-detail-k">Member</div>
            <div class="nb-detail-v" id="mMember">—</div>
          </div>
        @endif

        <div class="nb-detail-card" style="grid-column: 1 / -1;">
          <div class="nb-detail-k">Catatan</div>
          <div class="nb-detail-v" id="mNotes">—</div>
        </div>
      </div>
    </div>

    <div class="nb-modal-foot">
      <button type="button" class="nb-btn nb-btn-soft" data-modal-close>Tutup</button>
    </div>
  </div>
</div>

<script>
(function(){
  const modal = document.getElementById('nbResvModal');
  if(!modal) return;

  const el = (id) => document.getElementById(id);

  const mTitle   = el('mTitle');
  const mStatus  = el('mStatus');
  const mCreated = el('mCreated');
  const mReady   = el('mReady');
  const mExp     = el('mExp');
  const mNotes   = el('mNotes');
  const mMember  = el('mMember');
  const sub      = el('nbResvModalSub');

  let lastFocus = null;

  function openModal(fromBtn){
    lastFocus = fromBtn || null;
    modal.dataset.open = "1";
    modal.setAttribute('aria-hidden', 'false');

    const closeBtn = modal.querySelector('[data-modal-close].nb-icon-btn');
    closeBtn && closeBtn.focus({preventScroll:true});

    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
  }

  function closeModal(){
    modal.dataset.open = "0";
    modal.setAttribute('aria-hidden', 'true');

    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';

    if(lastFocus) lastFocus.focus({preventScroll:true});
    lastFocus = null;
  }

  document.addEventListener('click', function(e){
    const btn = e.target.closest('[data-resv-open]');
    if(btn){
      const id = btn.getAttribute('data-id') || '-';
      const title = btn.getAttribute('data-title') || '-';
      const status = btn.getAttribute('data-status') || '-';
      const created = btn.getAttribute('data-created') || '-';
      const ready = btn.getAttribute('data-ready') || '-';
      const exp = btn.getAttribute('data-exp') || '-';
      const notes = btn.getAttribute('data-notes') || '-';

      sub.textContent = `ID #${id}`;

      mTitle.textContent = title;
      mStatus.textContent = status;
      mCreated.textContent = created;
      mReady.textContent = ready;
      mExp.textContent = exp;
      mNotes.textContent = notes;

      if(mMember){
        const mn = btn.getAttribute('data-member-name') || '-';
        const mc = btn.getAttribute('data-member-code') || '-';
        mMember.textContent = `${mn} • ${mc}`;
      }

      openModal(btn);
      return;
    }

    if(e.target.closest('[data-modal-close]')){
      closeModal();
      return;
    }
  });

  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && modal.dataset.open === "1"){
      closeModal();
    }
  });
})();
</script>
@endsection
