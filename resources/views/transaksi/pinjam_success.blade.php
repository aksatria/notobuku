@extends('layouts.notobuku')

@section('title', 'Transaksi Berhasil • NOTOBUKU')

@section('content')
@php
  $title = $title ?? 'Transaksi Berhasil';

  // stdClass-safe fields
  $loanId      = (int)($loan->id ?? 0);
  $loanCode    = (string)($loan->loan_code ?? '-');
  $loanStatus  = (string)($loan->status ?? 'open');

  $anggotaName  = (string)($loan->anggota_name ?? '-');
  $anggotaCode  = (string)($loan->anggota_code ?? '-');
  $anggotaPhone = (string)($loan->anggota_phone ?? '-');

  $branchName  = (string)($loan->branch_name ?? '-');
  $createdBy   = (string)($loan->created_by_name ?? '-');

  $loanedAtText = !empty($loan->loaned_at)
    ? \Illuminate\Support\Carbon::parse($loan->loaned_at)->format('d/m/Y H:i')
    : '-';

  $dueAtText = !empty($loan->due_at)
    ? \Illuminate\Support\Carbon::parse($loan->due_at)->format('d/m/Y H:i')
    : '-';

  $notes = trim((string)($loan->notes ?? ''));

  $itemsCount = is_countable($items ?? []) ? count($items) : 0;

  // status badge class
  $st = strtolower($loanStatus);
  $statusCls = 'sb warn';
  if ($st === 'open') $statusCls = 'sb ok';
  elseif ($st === 'closed') $statusCls = 'sb ok';
  elseif ($st === 'overdue') $statusCls = 'sb bad';

  // anggota initials
  $init = 'MB';
  if ($anggotaName && $anggotaName !== '-') {
    $parts = preg_split('/\s+/', trim($anggotaName));
    $parts = array_values(array_filter($parts));
    $init = strtoupper(substr($parts[0] ?? 'M', 0, 1) . substr($parts[1] ?? 'B', 0, 1));
  }

  // QR data
  $qrData = $loanCode !== '-' ? $loanCode : ('LOAN-' . $loanId);
  $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($qrData);

  // Print mode handling (single file handles normal + print templates)
  // ?print=slip|nota
  // ?paper=58|80 (slip only)
  // ?mode=direct|preview (direct: auto print; preview: show template only)
  $printType = (string)request()->query('print', '');
  $paper = (string)request()->query('paper', '58');
  if (!in_array($paper, ['58','80'], true)) $paper = '58';

  $mode = (string)request()->query('mode', '');
  if (!in_array($mode, ['direct','preview'], true)) $mode = '';
@endphp

<style>
  .nb-k-wrap{ max-width:1180px; margin:0 auto; }
  .nb-k-head{ padding:14px; }

  .nb-k-headTop{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .nb-k-title{ font-size:16px; font-weight:800; letter-spacing:.1px; color: rgba(11,37,69,.94); line-height:1.2; }
  html.dark .nb-k-title{ color: rgba(226,232,240,.92); }
  .nb-k-sub{ margin-top:6px; font-size:13px; font-weight:500; color: rgba(11,37,69,.70); line-height:1.35; }
  html.dark .nb-k-sub{ color: rgba(226,232,240,.70); }

  .nb-k-divider{ height:1px; border:0; margin:12px 0; background: linear-gradient(90deg, rgba(15,23,42,.10), rgba(15,23,42,.05), rgba(15,23,42,.10)); }
  html.dark .nb-k-divider{ background: linear-gradient(90deg, rgba(148,163,184,.20), rgba(148,163,184,.10), rgba(148,163,184,.20)); }

  /* Wide buttons */
  .btn-wide{
    height:44px;
    padding:0 14px;
    border-radius:16px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.78);
    display:inline-flex; align-items:center; justify-content:center;
    gap:10px;
    cursor:pointer;
    font-weight:800;
    font-size:13px;
    color: rgba(11,37,69,.92);
    text-decoration:none;
    transition: box-shadow .12s ease, transform .06s ease, border-color .12s ease, background .12s ease;
    white-space: nowrap;
  }
  .btn-wide:active{ transform: translateY(1px); }
  .btn-wide svg{ width:18px; height:18px; }
  .btn-wide.primary{
    border-color: rgba(30,136,229,.22);
    background: linear-gradient(180deg, rgba(30,136,229,1), rgba(21,101,192,1));
    color:#fff;
    box-shadow: 0 14px 26px rgba(30,136,229,.22);
  }
  .btn-wide.primary:hover{ box-shadow: 0 16px 30px rgba(30,136,229,.26); }
  .btn-wide.primary svg{ color:#fff; }

  .btn-wide.ghost{
    background: rgba(255,255,255,.30);
    border-color: rgba(15,23,42,.12);
  }

  html.dark .btn-wide{
    color: rgba(226,232,240,.92);
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.40);
  }
  html.dark .btn-wide.primary{
    border-color: rgba(59,130,246,.26);
    background: linear-gradient(180deg, rgba(59,130,246,1), rgba(37,99,235,1));
    color:#fff;
  }

  /* Cards */
  .tx-card{ padding:14px; border-radius:18px; overflow:hidden; position:relative; }
  .tx-card::before{ content:""; position:absolute; top:0; left:0; right:0; height:3px; background: rgba(148,163,184,.28); }
  .tx-card.is-ok::before{ background: linear-gradient(90deg, rgba(39,174,96,.95), rgba(30,136,229,.95)); }
  .tx-hd{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
  .tx-hd .t{ font-size:13.8px; font-weight:780; letter-spacing:.08px; color: rgba(11,37,69,.94); line-height:1.2; }
  html.dark .tx-hd .t{ color: rgba(226,232,240,.92); }
  .tx-hd .s{ margin-top:5px; font-size:12.8px; font-weight:500; color: rgba(11,37,69,.70); line-height:1.35; }
  html.dark .tx-hd .s{ color: rgba(226,232,240,.70); }
  .tx-mini{ font-size:12.5px; color: rgba(11,37,69,.60); font-weight:500; }
  html.dark .tx-mini{ color: rgba(226,232,240,.60); }

  /* Anggota summary */
  .mcard{
    margin-top:12px;
    padding:12px;
    border-radius:18px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.70);
  }
  html.dark .mcard{ border-color: rgba(148,163,184,.16); background: rgba(15,23,42,.35); }
  .mrow{ display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
  .mava{
    width:42px; height:42px; border-radius:16px;
    display:flex; align-items:center; justify-content:center;
    font-weight:850; letter-spacing:.08px;
    color:#fff;
    background: linear-gradient(180deg, rgba(30,136,229,1), rgba(39,174,96,1));
  }
  .mname{ font-weight:750; color: rgba(11,37,69,.92); }
  html.dark .mname{ color: rgba(226,232,240,.92); }
  .mmeta{ font-size:12.5px; font-weight:500; color: rgba(11,37,69,.60); }
  html.dark .mmeta{ color: rgba(226,232,240,.60); }

  .mbadges{ display:flex; gap:8px; flex-wrap:wrap; margin-left:auto; }
  .mbadge{
    padding:8px 10px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.75);
    font-size:12px;
    font-weight:650;
    color: rgba(11,37,69,.76);
    max-width:100%;
  }
  html.dark .mbadge{ border-color: rgba(148,163,184,.16); background: rgba(15,23,42,.45); color: rgba(226,232,240,.74); }
  .mbadge.ok{ border-color: rgba(39,174,96,.22); background: rgba(39,174,96,.10); }
  .mbadge.info{ border-color: rgba(30,136,229,.22); background: rgba(30,136,229,.10); }

  /* Status badge */
  .sb{
    color: rgba(11,37,69,.92);
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 10px;
    border-radius:999px;
    font-weight:650;
    font-size:12px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.70);
    white-space: nowrap;
  }
  html.dark .sb{
    color: rgba(226,232,240,.92);
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.45);
  }
  .sb.ok{ border-color: rgba(39,174,96,.22); background: rgba(39,174,96,.10); }
  .sb.warn{ border-color: rgba(30,136,229,.22); background: rgba(30,136,229,.10); }
  .sb.bad{ border-color: rgba(231,76,60,.22); background: rgba(231,76,60,.10); }
  .dot{ width:10px; height:10px; border-radius:999px; background: rgba(148,163,184,.70); }
  .sb.ok .dot{ background: rgba(39,174,96,.95); }
  .sb.warn .dot{ background: rgba(30,136,229,.95); }
  .sb.bad .dot{ background: rgba(231,76,60,.95); }

  /* Table panel */
  .tx-panelInner{
    border:1px solid rgba(15,23,42,.10);
    border-radius:16px;
    overflow:hidden;
    background: rgba(255,255,255,.70);
  }
  html.dark .tx-panelInner{
    border-color: rgba(148,163,184,.18);
    background: rgba(15,23,42,.35);
  }

  .tx-tableWrap{ overflow:hidden; }
  .tx-tableWrap .nb-table{
    width:100%;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
  }
  .tx-tableWrap thead th{
    background: rgba(255,255,255,.98);
    font-size: 12.5px;
    letter-spacing: .10px;
    font-weight: 800;
    color: rgba(11,37,69,.92);
    padding: 14px 14px;
    line-height: 1.35;
    vertical-align: middle;
    border-bottom: 1px solid rgba(15,23,42,.12);
    text-align: left;
    white-space: nowrap;
  }
  html.dark .tx-tableWrap thead th{
    background: rgba(15,27,46,.96);
    color: rgba(226,232,240,.92);
    border-bottom-color: rgba(148,163,184,.22);
  }
  .tx-tableWrap tbody td{
    padding: 14px 14px;
    line-height: 1.4;
    vertical-align: top;
    border-bottom: 1px solid rgba(15,23,42,.08);
    font-size: 13.5px;
    font-weight: 650;
    color: rgba(11,37,69,.92);
    white-space: normal !important;
    word-break: break-word;
    text-align: left;
  }
  html.dark .tx-tableWrap tbody td{
    border-bottom-color: rgba(148,163,184,.16);
    color: rgba(226,232,240,.92);
  }

  /* Hover (bukan abu2) */
  .tx-tableWrap tbody tr:hover{ background: rgba(30,136,229,.06); }
  html.dark .tx-tableWrap tbody tr:hover{ background: rgba(147,197,253,.10); }

  .tx-col-barcode{ width: 210px; }
  .tx-col-status{ width: 150px; }
  .tx-col-due{ width: 170px; }
  @media (max-width: 560px){
    .tx-col-barcode{ width: 150px; }
    .tx-col-status{ width: 120px; }
    .tx-col-due{ width: 150px; }
  }

  .noteBox{
    margin-top:12px;
    padding:12px;
    border-radius:16px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.70);
    color: rgba(11,37,69,.86);
    font-size:13px;
    font-weight:600;
    white-space: pre-wrap;
  }
  html.dark .noteBox{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.35);
    color: rgba(226,232,240,.86);
  }

  /* QR block */
  .qrBox{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    align-items:center;
    justify-content:space-between;
    margin-top:12px;
    padding:12px;
    border-radius:18px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.70);
  }
  html.dark .qrBox{ border-color: rgba(148,163,184,.16); background: rgba(15,23,42,.35); }
  .qrMeta .h{ font-weight:900; color: rgba(11,37,69,.92); }
  html.dark .qrMeta .h{ color: rgba(226,232,240,.92); }
  .qrMeta .p{ margin-top:4px; font-weight:650; color: rgba(11,37,69,.62); font-size:12.5px; }
  html.dark .qrMeta .p{ color: rgba(226,232,240,.62); }
  .qrImg{
    width:120px; height:120px;
    border-radius:18px;
    border:1px solid rgba(15,23,42,.10);
    background:#fff;
    overflow:hidden;
    display:flex; align-items:center; justify-content:center;
  }
  html.dark .qrImg{ border-color: rgba(148,163,184,.16); background: rgba(255,255,255,.92); }

  /* Toast */
  .nb-toast{
    position: fixed;
    right: 16px;
    bottom: 16px;
    z-index: 9999;
    min-width: 280px;
    max-width: 420px;
    padding: 12px 12px;
    border-radius: 18px;
    border: 1px solid rgba(39,174,96,.22);
    background: rgba(39,174,96,.10);
    box-shadow: 0 18px 40px rgba(2,6,23,.14);
    color: rgba(11,37,69,.92);
  }
  html.dark .nb-toast{
    border-color: rgba(34,197,94,.26);
    background: rgba(34,197,94,.14);
    box-shadow: 0 18px 40px rgba(0,0,0,.35);
    color: rgba(226,232,240,.92);
  }
  .nb-toast.info{
    border-color: rgba(30,136,229,.22);
    background: rgba(30,136,229,.10);
  }
  html.dark .nb-toast.info{
    border-color: rgba(59,130,246,.26);
    background: rgba(59,130,246,.14);
  }
  .nb-toast .t{ font-weight:900; font-size:13px; }
  .nb-toast .s{ margin-top:6px; font-weight:650; font-size:12.5px; color: rgba(11,37,69,.72); line-height:1.35; }
  html.dark .nb-toast .s{ color: rgba(226,232,240,.72); }
  .nb-toast .x{
    position:absolute; top:10px; right:10px;
    width:34px; height:34px;
    border-radius:14px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.70);
    cursor:pointer;
    display:flex; align-items:center; justify-content:center;
  }
  html.dark .nb-toast .x{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.45);
  }

  /* Modal preview */
  .nb-modal{
    position: fixed;
    inset: 0;
    z-index: 9998;
    display:none;
    align-items:center;
    justify-content:center;
    padding: 18px;
    background: rgba(2,6,23,.55);
    backdrop-filter: blur(6px);
  }
  .nb-modal.open{ display:flex; }
  .nb-modalCard{
    width: min(980px, 96vw);
    height: min(84vh, 820px);
    border-radius: 22px;
    border: 1px solid rgba(148,163,184,.18);
    background: rgba(255,255,255,.92);
    overflow:hidden;
    box-shadow: 0 30px 90px rgba(2,6,23,.35);
    display:flex;
    flex-direction:column;
  }
  html.dark .nb-modalCard{
    background: rgba(15,23,42,.92);
    border-color: rgba(148,163,184,.16);
  }
  .nb-modalTop{
    padding: 12px 12px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 10px;
    border-bottom: 1px solid rgba(148,163,184,.18);
  }
  html.dark .nb-modalTop{
    border-bottom-color: rgba(148,163,184,.16);
  }
  .nb-modalTitle{
    font-weight: 900;
    font-size: 13.5px;
    color: rgba(11,37,69,.92);
  }
  html.dark .nb-modalTitle{ color: rgba(226,232,240,.92); }
  .nb-modalBody{
    flex:1;
    display:flex;
    gap:0;
    overflow:hidden;
  }
  .nb-modalSide{
    width: 280px;
    border-right: 1px solid rgba(148,163,184,.18);
    padding: 12px;
    overflow:auto;
  }
  html.dark .nb-modalSide{
    border-right-color: rgba(148,163,184,.16);
  }
  .nb-modalMain{
    flex:1;
    overflow:hidden;
    background: rgba(148,163,184,.10);
  }
  html.dark .nb-modalMain{
    background: rgba(148,163,184,.08);
  }
  .nb-ctrlBlock{
    padding: 12px;
    border-radius: 18px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.78);
    margin-bottom: 10px;
  }
  html.dark .nb-ctrlBlock{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.45);
  }
  .nb-ctrlLabel{
    font-size: 12px;
    font-weight: 900;
    color: rgba(11,37,69,.82);
  }
  html.dark .nb-ctrlLabel{ color: rgba(226,232,240,.82); }
  .nb-ctrlHint{
    margin-top: 6px;
    font-size: 12px;
    font-weight: 650;
    color: rgba(11,37,69,.62);
    line-height:1.35;
  }
  html.dark .nb-ctrlHint{ color: rgba(226,232,240,.62); }

  .nb-select{
    margin-top: 8px;
    width:100%;
    height: 42px;
    border-radius: 14px;
    border: 1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.92);
    padding: 0 12px;
    font-weight: 800;
    color: rgba(11,37,69,.92);
  }
  html.dark .nb-select{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.55);
    color: rgba(226,232,240,.92);
  }

  .nb-iframe{
    width:100%;
    height:100%;
    border:0;
    background: #fff;
  }

  /* Print: hide layout chrome (topbar/bottomnav/etc.) */
  @media print{
    body{ background:#fff !important; }
    /* hide common layout blocks */
    header, footer, nav{ display:none !important; }
    .topbar, .bottomnav, .sidebar, .app-header, .app-footer{ display:none !important; }
    .nb-topbar, .nb-bottomnav, .nb-sidebar, .nb-navbar{ display:none !important; }

    /* hide anything explicitly marked */
    .no-print{ display:none !important; }

    /* show only print scope */
    .print-scope{ display:block !important; }

    /* avoid shadows */
    .nb-card{ box-shadow:none !important; border:0 !important; }
  }

  /* Print Templates: hidden in normal view */
  .print-scope{ display:none; }

  /* Slip base */
  .slip{
    padding: 8mm 6mm;
    font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Helvetica Neue", sans-serif;
    color:#111827;
  }
  .slip.w58{ width: 58mm; max-width: 58mm; }
  .slip.w80{ width: 80mm; max-width: 80mm; }

  .slip .brand{ font-weight:900; font-size:14px; letter-spacing:.2px; }
  .slip .muted{ color:#6b7280; font-weight:700; font-size:11px; margin-top:2px; }
  .slip .hr{ height:1px; background:#e5e7eb; margin:8px 0; }
  .slip .row{ display:flex; justify-content:space-between; gap:10px; font-size:11px; font-weight:800; }
  .slip .row span{ font-weight:900; }
  .slip .big{ font-size:12px; font-weight:900; }
  .slip table{ width:100%; border-collapse:collapse; margin-top:6px; }
  .slip th, .slip td{ font-size:10.5px; padding:4px 0; border-bottom:1px dashed #e5e7eb; text-align:left; vertical-align:top; }
  .slip th{ font-weight:900; }
  .slip .qr{ margin-top:10px; display:flex; gap:8px; align-items:center; }
  .slip .qr img{ width:26mm; height:26mm; }
  .slip .foot{ margin-top:8px; font-size:10px; color:#6b7280; font-weight:700; }

  /* Nota (A4 / normal printer) */
  .nota{
    width: 210mm;
    max-width: 210mm;
    margin: 0 auto;
    padding: 12mm 12mm;
    font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Helvetica Neue", sans-serif;
    color:#111827;
  }
  .nota .top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap: 12px;
  }
  .nota .brand{ font-weight: 1000; font-size: 18px; letter-spacing:.2px; }
  .nota .meta{ margin-top:4px; font-size: 11.5px; font-weight: 700; color:#374151; line-height:1.45; }
  .nota .hr{ height:1px; background:#e5e7eb; margin:10px 0; }
  .nota .kpi{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    font-size: 12px;
    font-weight: 800;
    color:#111827;
  }
  .nota .kpi .box{
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 8px 10px;
  }
  .nota .kpi .lbl{ font-size: 11px; font-weight: 900; color:#6b7280; }
  .nota .kpi .val{ margin-top:4px; font-weight: 1000; }

  .nota table{
    width:100%;
    border-collapse:collapse;
    margin-top: 8px;
  }
  .nota th{
    text-align:left;
    font-size: 11px;
    font-weight: 1000;
    padding: 8px 8px;
    border-bottom: 1px solid #e5e7eb;
    color:#111827;
  }
  .nota td{
    font-size: 11.5px;
    font-weight: 750;
    padding: 8px 8px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align:top;
  }
  .nota .sign{
    margin-top: 14px;
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
  }
  .nota .sign .box{
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    padding: 10px;
    height: 42mm;
  }
  .nota .sign .lbl{
    font-size: 11px;
    font-weight: 1000;
    color:#6b7280;
  }
  .nota .sign .line{
    margin-top: 28mm;
    border-top: 1px solid #cbd5e1;
  }
  .nota .qrWrap{
    display:flex;
    gap: 10px;
    align-items:center;
    justify-content:flex-end;
  }
  .nota .qrWrap img{
    width: 28mm;
    height: 28mm;
  }
  .nota .foot{
    margin-top: 10px;
    font-size: 10.5px;
    font-weight: 700;
    color:#6b7280;
  }
</style>

<div class="nb-k-wrap">

  {{-- ===========================================================
      PRINT SCOPE
      - rendered when ?print=slip|nota
      =========================================================== --}}
  @if(in_array($printType, ['slip','nota'], true))
    <div class="print-scope">
      @if($printType === 'slip')
        <div class="slip {{ $paper === '80' ? 'w80' : 'w58' }}">
          <div class="brand">NOTOBUKU</div>
          <div class="muted">Slip Peminjaman</div>

          <div class="hr"></div>

          <div class="row"><div>Pinjaman</div><div class="big">{{ $loanCode }}</div></div>
          <div class="row"><div>Anggota</div><div>{{ $anggotaCode }}</div></div>
          <div class="row" style="justify-content:flex-start; margin-top:4px;">
            <div style="font-weight:900;">{{ $anggotaName }}</div>
          </div>

          <div class="hr"></div>

          <div class="row"><div>Pinjam</div><div>{{ $loanedAtText }}</div></div>
          <div class="row"><div>Jatuh Tempo</div><div>{{ $dueAtText }}</div></div>
          <div class="row"><div>Cabang</div><div>{{ $branchName }}</div></div>
          <div class="row"><div>Petugas</div><div>{{ $createdBy }}</div></div>

          <div class="hr"></div>

          <div class="big">Daftar Item ({{ $itemsCount }})</div>

          <table>
            <thead>
              <tr>
                <th style="width:{{ $paper==='80' ? '34mm' : '24mm' }};">Barcode</th>
                <th>Judul</th>
              </tr>
            </thead>
            <tbody>
              @if(empty($items) || $itemsCount === 0)
                <tr><td colspan="2">-</td></tr>
              @else
                @foreach($items as $it)
                  @php
                    $itBarcode = (string)($it->barcode ?? '-');
                    $itTitle = (string)($it->title ?? '-');
                  @endphp
                  <tr>
                    <td>{{ $itBarcode }}</td>
                    <td>{{ $itTitle }}</td>
                  </tr>
                @endforeach
              @endif
            </tbody>
          </table>

          <div class="qr">
            <img src="{{ $qrUrl }}" alt="QR">
            <div>
              <div style="font-size:10.5px; font-weight:900;">Pindai untuk cepat cari transaksi</div>
              <div style="font-size:10px; color:#6b7280; font-weight:800;">{{ $qrData }}</div>
            </div>
          </div>

          @if($notes !== '')
            <div class="hr"></div>
            <div class="big">Catatan</div>
            <div style="font-size:10.5px; font-weight:700; color:#111827; white-space:pre-wrap;">{{ $notes }}</div>
          @endif

          <div class="foot">
            Simpan slip ini. Tunjukkan saat pengembalian.
          </div>
        </div>
      @else
        <div class="nota">
          <div class="top">
            <div>
              <div class="brand">NOTOBUKU</div>
              <div class="meta">
                <div><b>Nota Peminjaman</b></div>
                <div>Pinjaman: <b>{{ $loanCode }}</b> • Status: <b>{{ strtoupper($loanStatus) }}</b></div>
                <div>Dicetak: {{ now()->format('d/m/Y H:i') }} • Petugas: {{ $createdBy }}</div>
              </div>
            </div>

            <div class="qrWrap">
              <div style="text-align:right;">
                <div style="font-size:11px; font-weight:1000;">QR Pinjaman</div>
                <div style="font-size:10.5px; font-weight:800; color:#6b7280;">{{ $qrData }}</div>
              </div>
              <img src="{{ $qrUrl }}" alt="QR">
            </div>
          </div>

          <div class="hr"></div>

          <div class="kpi">
            <div class="box">
              <div class="lbl">Anggota</div>
              <div class="val">{{ $anggotaName }}</div>
              <div style="margin-top:4px; font-size:11px; font-weight:800; color:#374151;">
                Kode: {{ $anggotaCode }} • HP: {{ $anggotaPhone }}
              </div>
            </div>
            <div class="box">
              <div class="lbl">Transaksi</div>
              <div class="val">{{ $itemsCount }} item</div>
              <div style="margin-top:4px; font-size:11px; font-weight:800; color:#374151;">
                Pinjam: {{ $loanedAtText }}<br>
                Jatuh Tempo: {{ $dueAtText }}<br>
                Cabang: {{ $branchName }}
              </div>
            </div>
          </div>

          <div class="hr"></div>

          <div style="font-weight:1000; font-size:12px;">Daftar Item</div>
          <table>
            <thead>
              <tr>
                <th style="width:34mm;">Barcode</th>
                <th>Judul</th>
                <th style="width:30mm;">Call No</th>
                <th style="width:26mm;">Status</th>
                <th style="width:34mm;">Jatuh Tempo</th>
              </tr>
            </thead>
            <tbody>
              @if(empty($items) || $itemsCount === 0)
                <tr><td colspan="5">-</td></tr>
              @else
                @foreach($items as $it)
                  @php
                    $itBarcode = (string)($it->barcode ?? '-');
                    $itTitle = (string)($it->title ?? '-');
                    $itCall = (string)($it->call_number ?? '-');
                    $itStatus = (string)($it->item_status ?? $it->loan_item_status ?? '-');
                    $itDueText = !empty($it->item_due_at)
                      ? \Illuminate\Support\Carbon::parse($it->item_due_at)->format('d/m/Y H:i')
                      : $dueAtText;
                  @endphp
                  <tr>
                    <td>{{ $itBarcode }}</td>
                    <td>{{ $itTitle }}</td>
                    <td>{{ $itCall }}</td>
                    <td>{{ $itStatus }}</td>
                    <td>{{ $itDueText }}</td>
                  </tr>
                @endforeach
              @endif
            </tbody>
          </table>

          @if($notes !== '')
            <div class="hr"></div>
            <div style="font-weight:1000; font-size:12px;">Catatan</div>
            <div style="margin-top:6px; font-size:11.5px; font-weight:750; color:#111827; white-space:pre-wrap;">
              {{ $notes }}
            </div>
          @endif

          <div class="sign">
            <div class="box">
              <div class="lbl">Tanda Tangan Petugas</div>
              <div class="line"></div>
            </div>
            <div class="box">
              <div class="lbl">Tanda Tangan Peminjam</div>
              <div class="line"></div>
            </div>
          </div>

          <div class="foot">
            * Jika muncul tulisan URL/tanggal/nomor halaman saat print, matikan opsi <b>Headers and footers</b> di pengaturan print browser.
          </div>
        </div>
      @endif
    </div>

    {{-- Auto print/close for direct mode --}}
    <script>
      (function(){
        const mode = @json($mode);
        if(mode !== 'direct') return;

        // Delay for render
        setTimeout(()=>{
          window.print();

          // Attempt close only if opened by script
          setTimeout(()=>{
            try{
              if (window.opener) window.close();
            }catch(e){}
          }, 350);
        }, 250);
      })();
    </script>

  @else
  {{-- ===========================================================
      NORMAL VIEW
      =========================================================== --}}

  <div class="nb-card nb-k-head no-print">

    <div class="nb-k-headTop">
      <div style="min-width:260px;">
        <div class="nb-k-title">{{ $title }}</div>
        <div class="nb-k-sub">
          Transaksi peminjaman berhasil dibuat. Kamu bisa pratinjau/cetak slip (termal) atau nota (A4).
        </div>
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        {{-- Slip --}}
        <button type="button" class="btn-wide" id="btn_preview_slip">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12 5c5 0 9 5.5 9 7s-4 7-9 7-9-5.5-9-7 4-7 9-7Zm0 2c-3.57 0-6.84 3.77-6.98 5 .14 1.23 3.41 5 6.98 5s6.84-3.77 6.98-5c-.14-1.23-3.41-5-6.98-5Z"/></svg>
          Pratinjau Slip
        </button>
        <button type="button" class="btn-wide" id="btn_print_slip">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M19 8H5a3 3 0 0 0-3 3v4h4v4h12v-4h4v-4a3 3 0 0 0-3-3ZM8 19v-5h8v5H8Zm10-7H6v-1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1Z"/></svg>
          Cetak Slip
        </button>

        {{-- Nota --}}
        <button type="button" class="btn-wide ghost" id="btn_preview_nota">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M6 2h9l5 5v15a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm8 1.5V8h4.5L14 3.5ZM7 12h10v2H7v-2Zm0 4h10v2H7v-2Zm0-8h6v2H7V8Z"/></svg>
          Pratinjau Nota
        </button>
        <button type="button" class="btn-wide ghost" id="btn_print_nota">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M19 8H5a3 3 0 0 0-3 3v4h4v4h12v-4h4v-4a3 3 0 0 0-3-3ZM8 19v-5h8v5H8Zm10-7H6v-1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1Z"/></svg>
          Cetak Nota
        </button>

        <a href="{{ route('transaksi.index') }}" class="btn-wide">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12 5V2L8 6l4 4V7c3.31 0 6 2.69 6 6a6 6 0 0 1-10.39 4.16l-1.42 1.42A8 8 0 0 0 20 13c0-4.42-3.58-8-8-8Z"/></svg>
          Transaksi Baru
        </a>

        <a href="{{ route('transaksi.riwayat.detail', ['id' => $loanId]) }}" class="btn-wide primary">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12 5c5 0 9 5.5 9 7s-4 7-9 7-9-5.5-9-7 4-7 9-7Zm0 2c-3.57 0-6.84 3.77-6.98 5 .14 1.23 3.41 5 6.98 5s6.84-3.77 6.98-5c-.14 1.23-3.41 5-6.98 5Zm0 2.5A2.5 2.5 0 1 1 9.5 12 2.5 2.5 0 0 1 12 9.5Z"/></svg>
          Lihat Detail
        </a>
      </div>
    </div>

    <hr class="nb-k-divider">

    <div class="nb-card tx-card is-ok" style="margin-top:0;">
      <div class="tx-hd">
        <div>
          <div class="t">Ringkasan Transaksi</div>
          <div class="s">Kode transaksi, anggota, jatuh tempo, dan petugas.</div>
        </div>
        <div class="tx-mini">{{ $itemsCount }} item</div>
      </div>

      <div class="mcard" style="margin-top:0;">
        <div class="mrow">
          <div class="mava">{{ $init }}</div>

          <div style="min-width:240px;">
            <div class="mname">{{ $anggotaName }}</div>
            <div class="mmeta">Kode: {{ $anggotaCode }} • HP: {{ $anggotaPhone }}</div>
          </div>

          <div class="mbadges">
            <span class="mbadge info">Pinjaman: {{ $loanCode }}</span>
            <span class="mbadge ok">Pinjam: {{ $loanedAtText }}</span>
            <span class="mbadge info">Jatuh tempo: {{ $dueAtText }}</span>
            <span class="mbadge info">Cabang: {{ $branchName }}</span>
            <span class="mbadge info">Petugas: {{ $createdBy }}</span>
            <span class="{{ $statusCls }}"><span class="dot"></span>{{ strtoupper($loanStatus) }}</span>
          </div>
        </div>
      </div>

      <div class="qrBox">
        <div class="qrMeta">
          <div class="h">QR Kode Pinjaman</div>
          <div class="p">Pindai QR ini untuk cari transaksi dengan cepat.</div>
          <div class="p" style="font-weight:900;">Data: {{ $qrData }}</div>

          <div style="margin-top:10px;">
            <label style="display:flex; align-items:center; gap:10px; font-weight:800; font-size:12.5px; color: rgba(11,37,69,.78);">
              <input type="checkbox" id="auto_redirect_toggle" style="width:18px; height:18px;">
              Alihkan otomatis ke “Transaksi Baru” setelah <span id="redir_sec">0</span> detik
            </label>
            <div class="tx-mini" id="redir_hint" style="margin-top:6px;">(Opsional) Cocok untuk alur kerja cepat petugas.</div>
          </div>
        </div>

        <div class="qrImg">
          <img src="{{ $qrUrl }}" alt="QR Kode Pinjaman" style="width:100%; height:100%; object-fit:cover;">
        </div>
      </div>

      @if($notes !== '')
        <div class="noteBox">
          Catatan:
          {{ $notes }}
        </div>
      @endif
    </div>

    <div class="nb-card tx-card" style="margin-top:12px;">
      <div class="tx-hd">
        <div>
          <div class="t">Daftar Item Dipinjam</div>
          <div class="s">Pastikan item yang dipinjam sudah sesuai.</div>
        </div>
        <div class="tx-mini">{{ $itemsCount }} baris</div>
      </div>

      <div class="tx-panelInner">
        <div class="tx-tableWrap">
          <table class="nb-table">
            <thead>
              <tr>
                <th class="tx-col-barcode">Barcode</th>
                <th>Judul</th>
                <th style="width:150px;">Call No</th>
                <th class="tx-col-status">Status</th>
                <th class="tx-col-due">Jatuh Tempo</th>
              </tr>
            </thead>
            <tbody>
              @if(empty($items) || $itemsCount === 0)
                <tr>
                  <td colspan="5" class="nb-muted" style="padding:14px 14px;">
                    Tidak ada item untuk transaksi ini.
                  </td>
                </tr>
              @else
                @foreach($items as $it)
                  @php
                    $itBarcode = (string)($it->barcode ?? '-');
                    $itTitle = (string)($it->title ?? '-');
                    $itCall = (string)($it->call_number ?? '-');
                    $itStatus = (string)($it->item_status ?? $it->loan_item_status ?? '-');

                    $itDueText = !empty($it->item_due_at)
                      ? \Illuminate\Support\Carbon::parse($it->item_due_at)->format('d/m/Y H:i')
                      : $dueAtText;

                    $st2 = strtolower($itStatus);
                    $cls = 'sb warn';
                    if (str_contains($st2, 'borrow')) $cls = 'sb ok';
                    elseif (str_contains($st2, 'available')) $cls = 'sb ok';
                    elseif (str_contains($st2, 'overdue')) $cls = 'sb bad';
                  @endphp
                  <tr>
                    <td>{{ $itBarcode }}</td>
                    <td>{{ $itTitle }}</td>
                    <td>{{ $itCall }}</td>
                    <td><span class="{{ $cls }}"><span class="dot"></span>{{ $itStatus }}</span></td>
                    <td>{{ $itDueText }}</td>
                  </tr>
                @endforeach
              @endif
            </tbody>
          </table>
        </div>
      </div>

      <div class="tx-mini" style="margin-top:10px;">
        Tips: Untuk hilangkan tulisan URL/tanggal saat print, matikan <b>Headers and footers</b> di pengaturan print browser.
      </div>
    </div>

  </div>

  {{-- Modal Preview --}}
  <div class="nb-modal no-print" id="modal_preview" aria-hidden="true">
    <div class="nb-modalCard" role="dialog" aria-modal="true">
      <div class="nb-modalTop">
        <div class="nb-modalTitle" id="modal_title">Pratinjau</div>
        <button class="btn-wide" type="button" id="modal_close" style="height:38px;">Tutup</button>
      </div>

      <div class="nb-modalBody">
        <div class="nb-modalSide">
          <div class="nb-ctrlBlock">
            <div class="nb-ctrlLabel">Jenis Dokumen</div>
            <div class="nb-ctrlHint">Slip = printer termal (58/80mm). Nota = A4/normal.</div>
            <select class="nb-select" id="preview_type">
              <option value="slip">Slip (Termal)</option>
              <option value="nota">Nota (A4)</option>
            </select>
          </div>

          <div class="nb-ctrlBlock" id="block_paper">
            <div class="nb-ctrlLabel">Ukuran Thermal (per PC)</div>
            <div class="nb-ctrlHint">
              Disimpan permanen di PC ini. Default: <b>58mm</b>.
              <br>
              (Jika mau ganti, klik “Ubah ukuran”.)
            </div>
            <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
              <button type="button" class="btn-wide" id="btn_change_paper" style="height:38px;">Ubah ukuran</button>
              <button type="button" class="btn-wide ghost" id="btn_reset_paper" style="height:38px;">Atur Ulang</button>
            </div>
          </div>

          <div class="nb-ctrlBlock" id="block_paper_picker" style="display:none;">
            <div class="nb-ctrlLabel">Pilih ukuran</div>
            <select class="nb-select" id="paper_picker">
              <option value="58">58mm</option>
              <option value="80">80mm</option>
            </select>
            <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
              <button type="button" class="btn-wide primary" id="btn_save_paper" style="height:38px;">Simpan</button>
              <button type="button" class="btn-wide" id="btn_cancel_paper" style="height:38px;">Batal</button>
            </div>
          </div>

          <div class="nb-ctrlBlock">
            <div class="nb-ctrlLabel">Aksi</div>
            <div class="nb-ctrlHint">
              Jika masih muncul URL/tanggal/halaman saat print: matikan <b>Headers and footers</b> di dialog print.
            </div>
            <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
              <button type="button" class="btn-wide primary" id="btn_modal_print" style="height:38px;">Cetak dari Pratinjau</button>
              <button type="button" class="btn-wide" id="btn_open_new" style="height:38px;">Buka Tab Baru</button>
            </div>
          </div>
        </div>

        <div class="nb-modalMain">
          <iframe class="nb-iframe" id="preview_iframe" title="Pratinjau"></iframe>
        </div>
      </div>
    </div>
  </div>

  {{-- TOAST: sukses --}}
  <div class="nb-toast no-print" id="toast_ok" style="display:none;">
    <button class="x" type="button" id="toast_close" aria-label="Tutup">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41Z"/></svg>
    </button>
    <div class="t">Sukses ✅</div>
    <div class="s">Transaksi pinjam berhasil dibuat. Pinjaman: <b>{{ $loanCode }}</b> • {{ $itemsCount }} item</div>
  </div>

  {{-- TOAST: edukasi print --}}
  <div class="nb-toast info no-print" id="toast_print_tip" style="display:none;">
    <button class="x" type="button" id="toast_tip_close" aria-label="Tutup">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41Z"/></svg>
    </button>
    <div class="t">Tips Cetak 🖨️</div>
    <div class="s">
      Kalau muncul teks URL/tanggal/nomor halaman di hasil print: buka <b>More settings</b> lalu matikan <b>Headers and footers</b>.
    </div>
  </div>

  <script>
  (function(){
    // ===========================
    // Toasts
    // ===========================
    const toast = document.getElementById('toast_ok');
    const toastClose = document.getElementById('toast_close');

    function showToast(el, ms){
      if(!el) return;
      el.style.display = 'block';
      setTimeout(()=>{ try{ el.style.display = 'none'; }catch(e){} }, ms || 6000);
    }
    if(toastClose){
      toastClose.addEventListener('click', ()=>{ toast.style.display = 'none'; });
    }
    showToast(toast, 5500);

    const tip = document.getElementById('toast_print_tip');
    const tipClose = document.getElementById('toast_tip_close');
    const TIP_KEY = 'nb_print_tip_seen';
    if(tipClose) tipClose.addEventListener('click', ()=>{ tip.style.display = 'none'; });

    // show once per PC
    if(localStorage.getItem(TIP_KEY) !== '1'){
      setTimeout(()=>{
        showToast(tip, 9000);
        localStorage.setItem(TIP_KEY, '1');
      }, 900);
    }

    // ===========================
    // Auto redirect toggle (saved in localStorage)
    // ===========================
    const toggle = document.getElementById('auto_redirect_toggle');
    const secEl  = document.getElementById('redir_sec');
    const hintEl = document.getElementById('redir_hint');

    const REDIR_KEY = 'nb_auto_redirect_pinjam';
    const defaultEnabled = (localStorage.getItem(REDIR_KEY) === '1');

    if(toggle){
      toggle.checked = defaultEnabled;
      toggle.addEventListener('change', ()=>{
        localStorage.setItem(REDIR_KEY, toggle.checked ? '1' : '0');
        if(!toggle.checked){
          stopRedirect();
          if(hintEl) hintEl.textContent = '(Opsional) Cocok untuk alur kerja cepat petugas.';
        } else {
          startRedirect();
        }
      });
    }

    let timer = null;
    let left = 0;

    function stopRedirect(){
      if(timer){ clearInterval(timer); timer = null; }
      left = 0;
      if(secEl) secEl.textContent = '0';
    }

    function startRedirect(){
      stopRedirect();
      left = 7; // 7 detik
      if(secEl) secEl.textContent = String(left);
      if(hintEl) hintEl.textContent = 'Akan kembali ke halaman Transaksi Baru otomatis...';

      timer = setInterval(()=>{
        left--;
        if(secEl) secEl.textContent = String(Math.max(0,left));
        if(left <= 0){
          clearInterval(timer);
          timer = null;
          window.location.href = @json(route('transaksi.index'));
        }
      }, 1000);
    }

    if(defaultEnabled){ startRedirect(); } else { if(secEl) secEl.textContent = '0'; }

    // ===========================
    // Printer Paper Preference (per PC, permanent)
    // ===========================
    const PAPER_KEY = 'nb_printer_paper_width';
    function getPaper(){
      const v = localStorage.getItem(PAPER_KEY);
      return (v === '80') ? '80' : '58'; // default 58
    }
    function setPaper(v){
      localStorage.setItem(PAPER_KEY, (v === '80') ? '80' : '58');
    }

    // ===========================
    // Print URLs
    // ===========================
    const baseUrl = @json(route('transaksi.pinjam.success', ['id' => $loanId]));
    function buildUrl(type, mode){
      // type: slip|nota
      // mode: preview|direct
      const u = new URL(baseUrl, window.location.origin);
      u.searchParams.set('print', type);
      u.searchParams.set('mode', mode);
      if(type === 'slip'){
        u.searchParams.set('paper', getPaper());
      }
      return u.toString();
    }

    function openDirect(type){
      const url = buildUrl(type, 'direct');
      // open a small popup (allowed by click)
      window.open(url, '_blank', 'width=520,height=720');
    }

    // ===========================
    // Modal Preview
    // ===========================
    const modal = document.getElementById('modal_preview');
    const modalClose = document.getElementById('modal_close');
    const modalTitle = document.getElementById('modal_title');

    const previewType = document.getElementById('preview_type');
    const blockPaper = document.getElementById('block_paper');
    const blockPicker = document.getElementById('block_paper_picker');
    const paperPicker = document.getElementById('paper_picker');

    const iframe = document.getElementById('preview_iframe');
    const btnModalPrint = document.getElementById('btn_modal_print');
    const btnOpenNew = document.getElementById('btn_open_new');

    const btnChangePaper = document.getElementById('btn_change_paper');
    const btnResetPaper = document.getElementById('btn_reset_paper');
    const btnSavePaper = document.getElementById('btn_save_paper');
    const btnCancelPaper = document.getElementById('btn_cancel_paper');

    function setModalOpen(open){
      if(!modal) return;
      modal.classList.toggle('open', !!open);
      modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    function refreshIframe(){
      const t = previewType ? previewType.value : 'slip';
      const url = buildUrl(t, 'preview');
      if(iframe) iframe.src = url;

      if(modalTitle){
        modalTitle.textContent = (t === 'nota') ? 'Pratinjau Nota (A4)' : ('Pratinjau Slip (' + getPaper() + 'mm)');
      }

      if(blockPaper){
        blockPaper.style.display = (t === 'slip') ? 'block' : 'none';
      }
    }

    function openPreview(type){
      if(previewType) previewType.value = type;
      if(paperPicker) paperPicker.value = getPaper();
      if(blockPicker) blockPicker.style.display = 'none';
      if(blockPaper) blockPaper.style.display = (type === 'slip') ? 'block' : 'none';

      refreshIframe();
      setModalOpen(true);
    }

    if(modalClose){
      modalClose.addEventListener('click', ()=> setModalOpen(false));
    }
    if(modal){
      modal.addEventListener('click', (e)=>{
        if(e.target === modal) setModalOpen(false);
      });
    }

    if(previewType){
      previewType.addEventListener('change', ()=>{
        if(paperPicker) paperPicker.value = getPaper();
        if(blockPicker) blockPicker.style.display = 'none';
        refreshIframe();
      });
    }

    // Change paper (still per PC, but hidden behind "Ubah ukuran")
    if(btnChangePaper){
      btnChangePaper.addEventListener('click', ()=>{
        if(!blockPicker || !paperPicker) return;
        paperPicker.value = getPaper();
        blockPicker.style.display = 'block';
      });
    }
    if(btnCancelPaper){
      btnCancelPaper.addEventListener('click', ()=>{
        if(blockPicker) blockPicker.style.display = 'none';
      });
    }
    if(btnSavePaper){
      btnSavePaper.addEventListener('click', ()=>{
        if(!paperPicker) return;
        setPaper(paperPicker.value);
        if(blockPicker) blockPicker.style.display = 'none';
        refreshIframe();
        // show tip toast again after change
        showToast(tip, 9000);
      });
    }
    if(btnResetPaper){
      btnResetPaper.addEventListener('click', ()=>{
        // reset to default 58
        setPaper('58');
        if(paperPicker) paperPicker.value = '58';
        if(blockPicker) blockPicker.style.display = 'none';
        refreshIframe();
        showToast(tip, 9000);
      });
    }

    // Print from preview (trigger print inside iframe)
    if(btnModalPrint){
      btnModalPrint.addEventListener('click', ()=>{
        try{
          iframe.contentWindow.focus();
          iframe.contentWindow.print();
        }catch(e){
          // fallback open direct
          openDirect(previewType ? previewType.value : 'slip');
        }
      });
    }
    if(btnOpenNew){
      btnOpenNew.addEventListener('click', ()=>{
        openDirect(previewType ? previewType.value : 'slip');
      });
    }

    // ===========================
    // Buttons
    // ===========================
    const btnPrevSlip = document.getElementById('btn_preview_slip');
    const btnPrintSlip = document.getElementById('btn_print_slip');
    const btnPrevNota = document.getElementById('btn_preview_nota');
    const btnPrintNota = document.getElementById('btn_print_nota');

    if(btnPrevSlip) btnPrevSlip.addEventListener('click', ()=> openPreview('slip'));
    if(btnPrevNota) btnPrevNota.addEventListener('click', ()=> openPreview('nota'));

    if(btnPrintSlip){
      btnPrintSlip.addEventListener('click', ()=>{
        // Direct print slip based on saved per-PC paper width
        openDirect('slip');
        showToast(tip, 9000);
      });
    }
    if(btnPrintNota){
      btnPrintNota.addEventListener('click', ()=>{
        openDirect('nota');
        showToast(tip, 9000);
      });
    }

  })();
  </script>

  @endif
</div>
@endsection



