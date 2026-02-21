@extends('layouts.notobuku')

@section('title', 'Visitor Counter - NOTOBUKU')

@section('content')
@php
  $rows = $rows ?? null;
  $branches = $branches ?? collect();
  $date = (string) ($date ?? now()->toDateString());
  $branchId = (string) ($branchId ?? '');
  $keyword = trim((string) ($keyword ?? ''));
  $activeOnly = (bool) ($activeOnly ?? false);
  $datePreset = (string) ($datePreset ?? 'custom');
  $perPage = (int) ($perPage ?? 20);
  $stats = $stats ?? ['total' => 0, 'member' => 0, 'non_member' => 0, 'active_inside' => 0];
  $auditRows = $auditRows ?? collect();
  $auditStats = $auditStats ?? ['checkin' => 0, 'checkout' => 0, 'undo' => 0];
  $auditAction = (string) ($auditAction ?? '');
  $auditRole = (string) ($auditRole ?? '');
  $auditKeyword = trim((string) ($auditKeyword ?? ''));
  $auditSort = (string) ($auditSort ?? 'latest');
  $auditPerPage = (int) ($auditPerPage ?? 15);
  $allowedAuditActions = $allowedAuditActions ?? [];
  $allowedAuditRoles = $allowedAuditRoles ?? [];
  $allowedAuditSorts = $allowedAuditSorts ?? ['latest', 'oldest'];
  $todayLabel = \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, d F Y');
  $oldVisitorType = old('visitor_type', 'member');
@endphp

<style>
  .v2-wrap{
    max-width: 1240px;
    margin: 0 auto;
    display: grid;
    gap: 14px;
  }

  .v2-surface{
    background: rgba(255,255,255,.94);
    border: 1px solid rgba(15,23,42,.08);
    border-radius: 20px;
    box-shadow: 0 14px 34px rgba(2,6,23,.06);
    padding: 16px;
  }
  html.dark .v2-surface{
    background: rgba(15,23,42,.58);
    border-color: rgba(148,163,184,.14);
    box-shadow: 0 14px 34px rgba(0,0,0,.34);
  }

  .v2-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    flex-wrap:wrap;
  }
  .v2-title{ font-size:15px; line-height:1.2; font-weight:700; color: rgba(15,23,42,.95); letter-spacing:.15px; }
  .v2-sub{ margin-top:4px; font-size:12px; color: rgba(100,116,139,.95); }
  html.dark .v2-title{ color: rgba(226,232,240,.95); }
  html.dark .v2-sub{ color: rgba(148,163,184,.92); }

  .v2-role{
    display:inline-flex;
    align-items:center;
    gap:8px;
    border:1px solid rgba(15,23,42,.12);
    border-radius:999px;
    padding:9px 13px;
    background: rgba(255,255,255,.82);
    font-size:12px;
    font-weight:600;
    color: rgba(15,23,42,.75);
  }
  html.dark .v2-role{
    border-color: rgba(148,163,184,.22);
    background: rgba(15,23,42,.38);
    color: rgba(226,232,240,.85);
  }

  .v2-kpi{
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(230px,1fr));
    gap:10px;
  }
  .v2-kpi-card{
    border-radius:16px;
    padding:14px;
    min-height:112px;
    color:#fff;
  }
  .v2-kpi-label{ font-size:12px; font-weight:600; opacity:.95; }
  .v2-kpi-value{ margin-top:8px; font-size:22px; line-height:1; font-weight:800; }
  .v2-kpi-note{ margin-top:8px; font-size:12px; opacity:.95; }

  .k-blue{ background: linear-gradient(135deg,#377cf1,#205ed6); }
  .k-indigo{ background: linear-gradient(135deg,#5d5cf0,#4338ca); }
  .k-teal{ background: linear-gradient(135deg,#0ea5a8,#0f8a8e); }
  .k-green{ background: linear-gradient(135deg,#22b864,#16984a); }

  .v2-layout{
    display:grid;
    grid-template-columns: minmax(0,1fr) 360px;
    gap:12px;
    align-items:start;
  }

  .v2-block-title{ font-size:13px; line-height:1.2; font-weight:800; color: rgba(15,23,42,.95); }
  .v2-block-sub{ margin-top:4px; font-size:12px; color: rgba(100,116,139,.95); }
  html.dark .v2-block-title{ color: rgba(226,232,240,.95); }
  html.dark .v2-block-sub{ color: rgba(148,163,184,.9); }

  .v2-list{ margin-top:12px; display:flex; flex-direction:column; gap:10px; }
  .v2-list-tools{
    margin-top:10px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }
  .v2-list-tools .nb-btn{
    border-radius:12px;
    min-height:38px;
    font-weight:600;
  }
  .v2-select{
    display:inline-flex;
    align-items:center;
    gap:8px;
    border:1px solid rgba(15,23,42,.12);
    border-radius:12px;
    padding:8px 10px;
    font-size:12px;
    color: rgba(71,85,105,.96);
    background: rgba(255,255,255,.75);
  }
  .v2-autorefresh{
    display:inline-flex;
    align-items:center;
    gap:8px;
    border:1px solid rgba(15,23,42,.12);
    border-radius:12px;
    padding:8px 10px;
    font-size:12px;
    color: rgba(71,85,105,.96);
    background: rgba(255,255,255,.75);
  }
  .v2-autorefresh input{ width:15px; height:15px; accent-color:#2563eb; }
  .v2-refresh-status{
    display:inline-flex;
    align-items:center;
    border:1px solid rgba(15,23,42,.12);
    border-radius:12px;
    padding:8px 10px;
    font-size:12px;
    color: rgba(71,85,105,.96);
    background: rgba(255,255,255,.75);
  }
  .v2-select input{ width:15px; height:15px; accent-color:#2563eb; }
  .v2-presets{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    margin-top:10px;
  }
  .v2-presets .nb-btn{
    border-radius:12px;
    min-height:34px;
    font-size:12px;
    font-weight:600;
    padding:7px 10px;
  }
  .v2-presets .is-active{
    background: linear-gradient(90deg,#1e88e5,#1565c0);
    border-color: transparent;
    color:#fff;
  }
  .v2-row-pick{
    display:flex;
    align-items:center;
    gap:8px;
    margin-bottom:6px;
    font-size:12px;
    color: rgba(71,85,105,.96);
  }
  .v2-row-pick input{ width:15px; height:15px; accent-color:#2563eb; }
  .v2-item{
    display:grid;
    grid-template-columns: 120px minmax(0,1.2fr) minmax(0,1.5fr) auto;
    gap:10px;
    align-items:start;
    border:1px solid rgba(15,23,42,.1);
    border-radius:14px;
    padding:12px;
    background: rgba(255,255,255,.82);
  }
  html.dark .v2-item{
    border-color: rgba(148,163,184,.15);
    background: rgba(2,6,23,.33);
  }

  .v2-time{ font-size:18px; line-height:1.1; font-weight:800; color: rgba(15,23,42,.94); }
  .v2-time-sub{ margin-top:6px; font-size:12px; color: rgba(100,116,139,.95); }
  html.dark .v2-time{ color: rgba(226,232,240,.94); }
  html.dark .v2-time-sub{ color: rgba(148,163,184,.9); }

  .v2-name{ font-size:13px; font-weight:700; line-height:1.3; color: rgba(15,23,42,.94); white-space:normal; word-break:break-word; }
  .v2-code{ margin-top:6px; font-size:13px; color: rgba(100,116,139,.95); white-space:normal; word-break:break-word; }
  .v2-type{
    margin-top:8px;
    display:inline-flex;
    border:1px solid rgba(15,23,42,.14);
    border-radius:999px;
    padding:4px 10px;
    font-size:11px;
    font-weight:700;
    letter-spacing:.02em;
  }
  html.dark .v2-name{ color: rgba(226,232,240,.94); }
  html.dark .v2-code{ color: rgba(148,163,184,.9); }
  html.dark .v2-type{ border-color: rgba(148,163,184,.22); }

  .v2-purpose{ font-size:13px; line-height:1.35; font-weight:600; color: rgba(15,23,42,.94); white-space:normal; word-break:break-word; }
  .v2-loc{ margin-top:6px; font-size:13px; color: rgba(100,116,139,.95); white-space:normal; word-break:break-word; }
  html.dark .v2-purpose{ color: rgba(226,232,240,.94); }
  html.dark .v2-loc{ color: rgba(148,163,184,.9); }

  .v2-action{ min-width:126px; display:flex; flex-direction:column; align-items:flex-end; gap:8px; }
  .v2-action .nb-btn{ border-radius:12px; min-height:40px; font-weight:600; }

  .v2-done{
    display:inline-flex;
    align-items:center;
    border:1px solid rgba(15,23,42,.14);
    border-radius:999px;
    padding:6px 10px;
    font-size:12px;
    font-weight:600;
    color: rgba(71,85,105,.95);
  }
  html.dark .v2-done{ border-color: rgba(148,163,184,.22); color: rgba(148,163,184,.95); }
  .v2-undo-hint{
    font-size:11px;
    color: rgba(100,116,139,.95);
    text-align:right;
  }
  .v2-undo-hint.is-expired{ color:#b45309; }
  html.dark .v2-undo-hint{ color: rgba(148,163,184,.95); }
  html.dark .v2-undo-hint.is-expired{ color:#fbbf24; }

  .v2-empty{
    margin-top:12px;
    border:1px dashed rgba(15,23,42,.22);
    border-radius:14px;
    padding:14px;
    font-size:12.8px;
    color: rgba(100,116,139,.95);
  }
  .v2-audit-list{
    margin-top: 12px;
    border: 1px solid rgba(15,23,42,.1);
    border-radius: 14px;
    overflow: hidden;
    max-height: 420px;
    overflow-y: auto;
  }
  .v2-audit-head{
    position: sticky;
    top: 0;
    z-index: 2;
    display: grid;
    grid-template-columns: 130px 170px minmax(0,1fr);
    gap: 10px;
    padding: 8px 12px;
    background: rgba(241,245,249,.95);
    border-bottom: 1px solid rgba(15,23,42,.1);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .02em;
    color: rgba(51,65,85,.95);
    text-transform: uppercase;
  }
  .v2-audit-kpis{
    margin-top: 10px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }
  .v2-audit-kpi{
    border: 1px solid rgba(15,23,42,.12);
    border-radius: 12px;
    padding: 8px 10px;
    background: rgba(255,255,255,.8);
    font-size: 12px;
    color: rgba(71,85,105,.96);
  }
  .v2-audit-kpi strong{
    font-size: 14px;
    color: rgba(15,23,42,.95);
    margin-left: 4px;
  }
  .v2-audit-item{
    display: grid;
    grid-template-columns: 130px 170px minmax(0,1fr);
    gap: 10px;
    padding: 10px 12px;
    border-bottom: 1px solid rgba(15,23,42,.08);
    background: rgba(255,255,255,.72);
  }
  .v2-audit-item:last-child{ border-bottom: 0; }
  .v2-audit-time{ font-size: 12px; font-weight: 600; color: rgba(71,85,105,.95); }
  .v2-audit-action{ font-size: 12px; font-weight: 700; color: rgba(30,64,175,.95); word-break: break-word; }
  .v2-audit-badge{
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 11px;
    font-weight: 700;
    border: 1px solid transparent;
    white-space: nowrap;
  }
  .v2-audit-badge.is-checkin{
    background: rgba(34,197,94,.12);
    color: #166534;
    border-color: rgba(34,197,94,.28);
  }
  .v2-audit-badge.is-checkout{
    background: rgba(37,99,235,.12);
    color: #1e40af;
    border-color: rgba(37,99,235,.25);
  }
  .v2-audit-badge.is-undo{
    background: rgba(245,158,11,.14);
    color: #92400e;
    border-color: rgba(245,158,11,.3);
  }
  .v2-audit-badge.is-muted{
    background: rgba(100,116,139,.12);
    color: #334155;
    border-color: rgba(100,116,139,.28);
  }
  .v2-audit-meta{ font-size: 12px; color: rgba(71,85,105,.95); word-break: break-word; }
  .v2-audit-hl{
    background: rgba(250,204,21,.35);
    color: inherit;
    border-radius: 4px;
    padding: 0 2px;
  }
  .v2-audit-extra{
    margin-top: 4px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }
  .v2-audit-chip{
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 1px solid rgba(100,116,139,.26);
    background: rgba(241,245,249,.9);
    color: rgba(51,65,85,.95);
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    white-space: nowrap;
  }
  .v2-audit-copy{
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 1px solid rgba(100,116,139,.3);
    background: rgba(255,255,255,.85);
    color: rgba(51,65,85,.95);
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    cursor: pointer;
  }
  .v2-audit-view{
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 1px solid rgba(59,130,246,.35);
    background: rgba(239,246,255,.95);
    color: #1d4ed8;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    cursor: pointer;
  }
  .v2-audit-copy.is-done{
    border-color: rgba(34,197,94,.4);
    color: #166534;
    background: rgba(220,252,231,.92);
  }
  html.dark .v2-audit-item{
    border-bottom-color: rgba(148,163,184,.14);
    background: rgba(2,6,23,.28);
  }
  html.dark .v2-audit-head{
    background: rgba(15,23,42,.95);
    border-bottom-color: rgba(148,163,184,.2);
    color: rgba(148,163,184,.9);
  }
  html.dark .v2-audit-time,
  html.dark .v2-audit-meta{ color: rgba(148,163,184,.92); }
  html.dark .v2-audit-hl{
    background: rgba(250,204,21,.28);
  }
  html.dark .v2-audit-chip{
    border-color: rgba(148,163,184,.3);
    background: rgba(30,41,59,.7);
    color: rgba(203,213,225,.95);
  }
  html.dark .v2-audit-copy{
    border-color: rgba(148,163,184,.35);
    background: rgba(30,41,59,.85);
    color: rgba(203,213,225,.95);
  }
  html.dark .v2-audit-view{
    border-color: rgba(59,130,246,.45);
    background: rgba(30,58,138,.45);
    color: #bfdbfe;
  }
  html.dark .v2-audit-copy.is-done{
    border-color: rgba(34,197,94,.45);
    color: #86efac;
    background: rgba(20,83,45,.6);
  }
  .v2-modal{
    position: fixed;
    inset: 0;
    z-index: 70;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15,23,42,.45);
  }
  .v2-modal.is-open{ display: flex; }
  .v2-modal-card{
    width: min(880px, 100%);
    max-height: calc(100vh - 40px);
    display: flex;
    flex-direction: column;
    border-radius: 16px;
    border: 1px solid rgba(15,23,42,.12);
    background: #fff;
    box-shadow: 0 20px 48px rgba(2,6,23,.25);
    overflow: hidden;
  }
  .v2-modal-head{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(15,23,42,.08);
  }
  .v2-modal-title{
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    color: rgba(15,23,42,.95);
  }
  .v2-modal-close{
    border: 1px solid rgba(15,23,42,.18);
    border-radius: 10px;
    background: rgba(255,255,255,.92);
    color: rgba(51,65,85,.95);
    font-size: 12px;
    font-weight: 600;
    padding: 6px 10px;
    cursor: pointer;
  }
  .v2-modal-body{
    padding: 12px 14px 14px;
    overflow: auto;
  }
  .v2-modal-json{
    margin: 0;
    padding: 12px;
    border-radius: 12px;
    border: 1px solid rgba(15,23,42,.12);
    background: rgba(248,250,252,.96);
    color: rgba(15,23,42,.96);
    font-size: 12px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-word;
  }
  .v2-modal-actions{
    margin-top: 10px;
    display: flex;
    justify-content: flex-end;
  }
  html.dark .v2-modal{
    background: rgba(2,6,23,.7);
  }
  html.dark .v2-modal-card{
    border-color: rgba(148,163,184,.24);
    background: rgba(15,23,42,.98);
  }
  html.dark .v2-modal-head{
    border-bottom-color: rgba(148,163,184,.2);
  }
  html.dark .v2-modal-title{
    color: rgba(226,232,240,.96);
  }
  html.dark .v2-modal-close{
    border-color: rgba(148,163,184,.3);
    background: rgba(30,41,59,.95);
    color: rgba(226,232,240,.95);
  }
  html.dark .v2-modal-json{
    border-color: rgba(148,163,184,.24);
    background: rgba(2,6,23,.82);
    color: rgba(226,232,240,.96);
  }
  html.dark .v2-audit-action{ color: rgba(147,197,253,.95); }
  html.dark .v2-audit-badge.is-checkin{
    background: rgba(34,197,94,.2);
    color: #86efac;
    border-color: rgba(34,197,94,.35);
  }
  html.dark .v2-audit-badge.is-checkout{
    background: rgba(37,99,235,.2);
    color: #93c5fd;
    border-color: rgba(37,99,235,.35);
  }
  html.dark .v2-audit-badge.is-undo{
    background: rgba(245,158,11,.2);
    color: #fcd34d;
    border-color: rgba(245,158,11,.35);
  }
  html.dark .v2-audit-badge.is-muted{
    background: rgba(100,116,139,.22);
    color: #cbd5e1;
    border-color: rgba(100,116,139,.35);
  }
  html.dark .v2-audit-kpi{
    border-color: rgba(148,163,184,.24);
    background: rgba(15,23,42,.52);
    color: rgba(148,163,184,.92);
  }
  html.dark .v2-audit-kpi strong{ color: rgba(226,232,240,.95); }

  .v2-side{ display:grid; gap:12px; }

  .v2-field{ margin-top:12px; }
  .v2-field label{ display:block; margin-bottom:6px; font-size:12px; font-weight:600; color: rgba(71,85,105,.95); }
  .v2-field .nb-field{
    width:100%;
    border-radius:14px;
    border:1px solid rgba(15,23,42,.22);
    background: rgba(255,255,255,.97);
    min-height:44px;
    padding:10px 12px;
    font-size:13px;
    white-space:normal;
  }
  .v2-field .nb-field.v2-err-input{
    border-color: #ef4444;
    box-shadow: inset 0 0 0 1px rgba(239,68,68,.15);
  }
  .v2-field textarea.nb-field{ min-height:104px; resize:vertical; }
  .v2-err{
    margin-top:6px;
    color:#dc2626;
    font-size:12px;
    font-weight:600;
  }
  html.dark .v2-err{ color:#fda4af; }

  .v2-check{
    margin-top:10px;
    display:flex;
    align-items:center;
    gap:8px;
    font-size:12px;
    color: rgba(71,85,105,.95);
  }
  .v2-check input{
    width:15px;
    height:15px;
    accent-color:#2563eb;
  }

  html.dark .v2-field label{ color: rgba(148,163,184,.95); }
  html.dark .v2-autorefresh{
    border-color: rgba(148,163,184,.24);
    background: rgba(15,23,42,.52);
    color: rgba(148,163,184,.95);
  }
  html.dark .v2-refresh-status{
    border-color: rgba(148,163,184,.24);
    background: rgba(15,23,42,.52);
    color: rgba(148,163,184,.95);
  }
  html.dark .v2-field .nb-field{
    background: rgba(15,23,42,.74);
    border-color: rgba(148,163,184,.24);
    color: rgba(226,232,240,.95);
  }

  .v2-btns{ display:flex; gap:8px; margin-top:12px; }
  .v2-btns .nb-btn{ flex:1; border-radius:12px; min-height:42px; font-weight:600; }

  .v2-pager{ margin-top:10px; }

  @media (max-width: 1140px){ .v2-layout{ grid-template-columns: 1fr; } }

  @media (max-width: 920px){
    .v2-title{ font-size:15px; }
    .v2-item{ grid-template-columns: 1fr; }
    .v2-audit-head{ grid-template-columns: 1fr; }
    .v2-audit-item{ grid-template-columns: 1fr; }
    .v2-action{ align-items:flex-start; min-width:0; }
  }
</style>

<div class="v2-wrap">
  <section class="v2-surface v2-head">
    <div>
      <div class="v2-title">Visitor Counter</div>
      <div class="v2-sub">Log kunjungan onsite - {{ $todayLabel }}</div>
    </div>
    <div class="v2-role">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;">
        <use href="#nb-icon-users"></use>
      </svg>
      Admin/Staff
    </div>
  </section>

  <section class="v2-kpi">
    <div class="v2-kpi-card k-blue">
      <div class="v2-kpi-label">Total Kunjungan</div>
      <div class="v2-kpi-value">{{ number_format((int) $stats['total']) }}</div>
      <div class="v2-kpi-note">Semua kunjungan hari ini</div>
    </div>
    <div class="v2-kpi-card k-indigo">
      <div class="v2-kpi-label">Member</div>
      <div class="v2-kpi-value">{{ number_format((int) $stats['member']) }}</div>
      <div class="v2-kpi-note">Kunjungan anggota</div>
    </div>
    <div class="v2-kpi-card k-teal">
      <div class="v2-kpi-label">Non-Member</div>
      <div class="v2-kpi-value">{{ number_format((int) $stats['non_member']) }}</div>
      <div class="v2-kpi-note">Visitor umum</div>
    </div>
    <div class="v2-kpi-card k-green">
      <div class="v2-kpi-label">Masih di Tempat</div>
      <div class="v2-kpi-value">{{ number_format((int) $stats['active_inside']) }}</div>
      <div class="v2-kpi-note">Belum checkout</div>
    </div>
  </section>

  <section class="v2-layout">
    <div class="v2-surface">
      <div class="v2-block-title">Daftar Kunjungan</div>
      <div class="v2-block-sub">Semua data tampil penuh tanpa teks terpotong dan tanpa scroll horizontal.</div>
      <div class="v2-list-tools">
        <label class="v2-select">
          <input type="checkbox" id="vcSelectAll">
          Pilih semua baris
        </label>
        <label class="v2-autorefresh">
          <input type="checkbox" id="vcAutoRefreshToggle" checked>
          Auto-refresh 30 dtk
        </label>
        <div class="v2-refresh-status" id="vcRefreshStatus">Last update: --:--:--</div>
        <a
          class="nb-btn"
          href="{{ route('visitor_counter.export_csv', ['date' => $date, 'preset' => $datePreset !== 'custom' ? $datePreset : null, 'branch_id' => $branchId !== '' ? $branchId : null, 'q' => $keyword !== '' ? $keyword : null, 'active_only' => $activeOnly ? 1 : null]) }}"
        >Export CSV</a>
        <form method="POST" action="{{ route('visitor_counter.checkout_selected') }}" id="vcCheckoutSelectedForm" onsubmit="return confirm('Checkout baris yang dipilih?');">
          @csrf
          <input type="hidden" name="date" value="{{ $date }}">
          <input type="hidden" name="preset" value="{{ $datePreset }}">
          <input type="hidden" name="branch_id" value="{{ $branchId }}">
          <input type="hidden" name="q" value="{{ $keyword }}">
          <input type="hidden" name="per_page" value="{{ $perPage }}">
          @if($activeOnly)
            <input type="hidden" name="active_only" value="1">
          @endif
          <button class="nb-btn" type="submit">Checkout Terpilih</button>
        </form>
        <form method="POST" action="{{ route('visitor_counter.checkout_bulk') }}" onsubmit="return confirm('Checkout semua visitor aktif sesuai filter saat ini?');">
          @csrf
          <input type="hidden" name="date" value="{{ $date }}">
          <input type="hidden" name="preset" value="{{ $datePreset }}">
          <input type="hidden" name="branch_id" value="{{ $branchId }}">
          <input type="hidden" name="q" value="{{ $keyword }}">
          <input type="hidden" name="per_page" value="{{ $perPage }}">
          <button class="nb-btn" type="submit">Checkout Semua Aktif</button>
        </form>
      </div>

      @if(!$rows || $rows->count() === 0)
        <div class="v2-empty">Belum ada data kunjungan untuk filter saat ini.</div>
      @else
        <div class="v2-list">
          @foreach($rows as $r)
            @php
              $canUndo = false;
              $undoHint = '';
              if ($r->checkout_at) {
                $checkoutAt = \Illuminate\Support\Carbon::parse($r->checkout_at);
                $remainingSeconds = max(0, (5 * 60) - $checkoutAt->diffInSeconds(now()));
                $canUndo = $remainingSeconds > 0;
                if ($canUndo) {
                  $undoHint = 'Undo tersisa ' . max(1, (int) ceil($remainingSeconds / 60)) . ' menit';
                } else {
                  $undoHint = 'Batas undo lewat';
                }
              }
            @endphp
            <article class="v2-item">
              <div>
                <label class="v2-row-pick">
                  <input type="checkbox" class="vc-row-check" value="{{ $r->id }}">
                  Pilih
                </label>
                <div class="v2-time">{{ $r->checkin_at ? \Illuminate\Support\Carbon::parse($r->checkin_at)->format('H:i') : '-' }}</div>
                <div class="v2-time-sub">Checkout: {{ $r->checkout_at ? \Illuminate\Support\Carbon::parse($r->checkout_at)->format('H:i') : '-' }}</div>
              </div>

              <div>
                <div class="v2-name">{{ $r->visitor_name ?: ($r->member_name ?: '-') }}</div>
                <div class="v2-code">{{ $r->member_code_snapshot ?: ($r->member_code ?: '-') }}</div>
                <span class="v2-type">{{ strtoupper((string) $r->visitor_type) }}</span>
              </div>

            lan  <div>
                <div class="v2-purpose">{{ $r->purpose ?: '-' }}</div>
                <div class="v2-loc">{{ $r->branch_name ?: '-' }}</div>
              </div>

              <div class="v2-action">
                @if(!$r->checkout_at)
                  <form method="POST" action="{{ route('visitor_counter.checkout', $r->id) }}">
                    @csrf
                    <button class="nb-btn" type="submit">Checkout</button>
                  </form>
                @else
                  <span class="v2-done">Selesai</span>
                  <div class="v2-undo-hint {{ $canUndo ? '' : 'is-expired' }}">{{ $undoHint }}</div>
                  @if($canUndo)
                    <form method="POST" action="{{ route('visitor_counter.undo_checkout', $r->id) }}" onsubmit="return confirm('Undo checkout visitor ini? Batas waktu 5 menit setelah checkout.');">
                      @csrf
                      <button class="nb-btn" type="submit">Undo</button>
                    </form>
                  @endif
                @endif
              </div>
            </article>
          @endforeach
        </div>

        <div class="v2-pager">{{ $rows->links() }}</div>

        <div class="v2-surface" style="margin-top:12px; padding:12px;">
          <div class="v2-block-title">Riwayat Aksi</div>
          <div class="v2-block-sub">Audit check-in / checkout terbaru (read-only).</div>
          <div class="v2-audit-kpis">
            <div class="v2-audit-kpi">Check-in<strong>{{ number_format((int) ($auditStats['checkin'] ?? 0)) }}</strong></div>
            <div class="v2-audit-kpi">Checkout<strong>{{ number_format((int) ($auditStats['checkout'] ?? 0)) }}</strong></div>
            <div class="v2-audit-kpi">Undo<strong>{{ number_format((int) ($auditStats['undo'] ?? 0)) }}</strong></div>
          </div>

          <form id="vcAuditFilterForm" method="GET" action="{{ route('visitor_counter.index') }}" style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
            <input type="hidden" name="date" value="{{ $date }}">
            <input type="hidden" name="preset" value="{{ $datePreset }}">
            <input type="hidden" name="branch_id" value="{{ $branchId }}">
            <input type="hidden" name="q" value="{{ $keyword }}">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            @if($activeOnly)
              <input type="hidden" name="active_only" value="1">
            @endif
            <select class="nb-field js-audit-auto-submit" name="audit_action" style="max-width:280px;">
              <option value="">Semua aksi</option>
              @foreach($allowedAuditActions as $actionKey)
                <option value="{{ $actionKey }}" {{ $auditAction === $actionKey ? 'selected' : '' }}>{{ $actionKey }}</option>
              @endforeach
            </select>
            <select class="nb-field js-audit-auto-submit" name="audit_role" style="max-width:220px;">
              <option value="">Semua role</option>
              @foreach($allowedAuditRoles as $roleKey)
                <option value="{{ $roleKey }}" {{ $auditRole === $roleKey ? 'selected' : '' }}>{{ $roleKey }}</option>
              @endforeach
            </select>
            <select class="nb-field js-audit-auto-submit" name="audit_sort" style="max-width:190px;">
              @foreach($allowedAuditSorts as $sortKey)
                <option value="{{ $sortKey }}" {{ $auditSort === $sortKey ? 'selected' : '' }}>
                  {{ $sortKey === 'oldest' ? 'Terlama dulu' : 'Terbaru dulu' }}
                </option>
              @endforeach
            </select>
            <select class="nb-field js-audit-auto-submit" name="audit_per_page" style="max-width:160px;">
              @foreach([10,15,25,50] as $auditSize)
                <option value="{{ $auditSize }}" {{ $auditPerPage === $auditSize ? 'selected' : '' }}>
                  {{ $auditSize }} / halaman
                </option>
              @endforeach
            </select>
            <input class="nb-field js-audit-auto-input" type="text" name="audit_q" value="{{ $auditKeyword }}" placeholder="Cari actor / action / row id / metadata" style="max-width:340px;">
            <button class="nb-btn" type="submit">Filter Audit</button>
            <a
              id="vcAuditClearBtn"
              class="nb-btn"
              href="{{ route('visitor_counter.index', ['date' => $date, 'preset' => $datePreset !== 'custom' ? $datePreset : null, 'branch_id' => $branchId !== '' ? $branchId : null, 'q' => $keyword !== '' ? $keyword : null, 'per_page' => $perPage, 'active_only' => $activeOnly ? 1 : null]) }}"
            >Clear Filter Audit</a>
            <a
              class="nb-btn"
              href="{{ route('visitor_counter.export_audit_csv', ['date' => $date, 'preset' => $datePreset !== 'custom' ? $datePreset : null, 'branch_id' => $branchId !== '' ? $branchId : null, 'audit_action' => $auditAction !== '' ? $auditAction : null, 'audit_role' => $auditRole !== '' ? $auditRole : null, 'audit_q' => $auditKeyword !== '' ? $auditKeyword : null, 'audit_sort' => $auditSort]) }}"
            >Export Audit CSV</a>
            <a
              class="nb-btn"
              href="{{ route('visitor_counter.export_audit_json', ['date' => $date, 'preset' => $datePreset !== 'custom' ? $datePreset : null, 'branch_id' => $branchId !== '' ? $branchId : null, 'audit_action' => $auditAction !== '' ? $auditAction : null, 'audit_role' => $auditRole !== '' ? $auditRole : null, 'audit_q' => $auditKeyword !== '' ? $auditKeyword : null, 'audit_sort' => $auditSort]) }}"
            >Export Audit JSON</a>
          </form>

          @if($auditRows->count() === 0)
            <div class="v2-empty">Belum ada audit log visitor counter.</div>
          @else
            <div class="v2-audit-list">
              <div class="v2-audit-head">
                <div>Waktu</div>
                <div>Aksi</div>
                <div>Detail</div>
              </div>
              @foreach($auditRows as $a)
                @php
                  $meta = $a->metadata_array ?? [];
                  $actorLabel = trim((string) (($a->actor_name ?? '') !== '' ? $a->actor_name : ($a->actor_role ?? '-')));
                  $branchLabel = isset($meta['branch_id']) && (int) $meta['branch_id'] > 0 ? 'Cabang #' . (int) $meta['branch_id'] : '-';
                  $action = (string) ($a->action ?? '');
                  $extraMeta = [];
                  foreach (['reason', 'updated_count', 'selected_count', 'visitor_type', 'member_id'] as $metaKey) {
                    if (array_key_exists($metaKey, $meta) && $meta[$metaKey] !== null && $meta[$metaKey] !== '') {
                      $extraMeta[$metaKey] = (string) $meta[$metaKey];
                    }
                  }
                  $actionClass = 'is-muted';
                  if (str_contains($action, 'checkin')) {
                    $actionClass = 'is-checkin';
                  } elseif (str_contains($action, 'checkout')) {
                    $actionClass = str_contains($action, 'undo') ? 'is-undo' : 'is-checkout';
                  } elseif (str_contains($action, 'undo')) {
                    $actionClass = 'is-undo';
                  }
                @endphp
                <article class="v2-audit-item">
                  <div class="v2-audit-time">{{ $a->created_at ? \Illuminate\Support\Carbon::parse($a->created_at)->format('d/m H:i:s') : '-' }}</div>
                  <div class="v2-audit-action"><span class="v2-audit-badge {{ $actionClass }}">{{ $a->action }}</span></div>
                  <div class="v2-audit-meta">
                    Actor: {{ $actorLabel }} | Row: {{ $a->auditable_id ?: '-' }} | {{ $branchLabel }}
                    @if(!empty($extraMeta))
                      <div class="v2-audit-extra">
                        @foreach($extraMeta as $key => $value)
                          <span class="v2-audit-chip">{{ $key }}: {{ $value }}</span>
                        @endforeach
                      </div>
                    @endif
                    @if(!empty($meta))
                      <div class="v2-audit-extra">
                        <button class="v2-audit-view js-audit-view" type="button" data-meta='@json($meta, JSON_UNESCAPED_UNICODE)'>View JSON</button>
                        <button class="v2-audit-copy js-audit-copy" type="button" data-meta='@json($meta, JSON_UNESCAPED_UNICODE)'>Copy JSON</button>
                      </div>
                    @endif
                  </div>
                </article>
              @endforeach
            </div>
            @if(method_exists($auditRows, 'links'))
              <div class="v2-pager" style="margin-top:10px;">{{ $auditRows->links() }}</div>
            @endif
          @endif
        </div>
      @endif
    </div>

    <aside class="v2-side">
      <div class="v2-surface">
        <div class="v2-block-title">Filter Tanggal</div>
        <div class="v2-block-sub">Lihat log per hari dan cabang.</div>

        <form method="GET" action="{{ route('visitor_counter.index') }}">
          <div class="v2-presets">
            <a class="nb-btn {{ $datePreset === 'today' ? 'is-active' : '' }}" href="{{ route('visitor_counter.index', ['preset' => 'today', 'branch_id' => $branchId !== '' ? $branchId : null, 'q' => $keyword !== '' ? $keyword : null, 'active_only' => $activeOnly ? 1 : null, 'per_page' => $perPage]) }}">Hari Ini</a>
            <a class="nb-btn {{ $datePreset === 'yesterday' ? 'is-active' : '' }}" href="{{ route('visitor_counter.index', ['preset' => 'yesterday', 'branch_id' => $branchId !== '' ? $branchId : null, 'q' => $keyword !== '' ? $keyword : null, 'active_only' => $activeOnly ? 1 : null, 'per_page' => $perPage]) }}">Kemarin</a>
            <a class="nb-btn {{ $datePreset === 'last7' ? 'is-active' : '' }}" href="{{ route('visitor_counter.index', ['preset' => 'last7', 'branch_id' => $branchId !== '' ? $branchId : null, 'q' => $keyword !== '' ? $keyword : null, 'active_only' => $activeOnly ? 1 : null, 'per_page' => $perPage]) }}">7 Hari</a>
          </div>
          <input type="hidden" name="preset" value="custom">

          <div class="v2-field">
            <label>Tanggal</label>
            <input class="nb-field" type="date" name="date" value="{{ $date }}">
          </div>

          <div class="v2-field">
            <label>Cari Nama / Kode / Tujuan</label>
            <input class="nb-field" type="text" name="q" value="{{ $keyword }}" placeholder="contoh: andi, MBR-0001, referensi">
          </div>

          <div class="v2-field">
            <label>Cabang</label>
            <select class="nb-field" name="branch_id">
              <option value="">Semua cabang</option>
              @foreach($branches as $b)
                <option value="{{ $b->id }}" {{ $branchId === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="v2-field">
            <label>Baris per Halaman</label>
            <select class="nb-field" name="per_page">
              @foreach([20,50,100] as $size)
                <option value="{{ $size }}" {{ $perPage === $size ? 'selected' : '' }}>{{ $size }}</option>
              @endforeach
            </select>
          </div>

          <label class="v2-check">
            <input type="checkbox" name="active_only" value="1" {{ $activeOnly ? 'checked' : '' }}>
            Belum checkout saja
          </label>

          <div class="v2-btns">
            <button class="nb-btn nb-btn-primary" type="submit">Terapkan</button>
            <a class="nb-btn" style="text-align:center;" href="{{ route('visitor_counter.index') }}">Reset</a>
          </div>
        </form>
      </div>

      <div class="v2-surface">
        <div class="v2-block-title">Check-in Visitor</div>
        <div class="v2-block-sub">Gunakan kode member atau input manual.</div>

        <form method="POST" action="{{ route('visitor_counter.store') }}" id="vcCheckinForm">
          @csrf

          <div class="v2-field">
            <label>Tipe Visitor</label>
            <select class="nb-field {{ $errors->has('visitor_type') ? 'v2-err-input' : '' }}" name="visitor_type" id="vcVisitorType">
              <option value="member" {{ $oldVisitorType === 'member' ? 'selected' : '' }}>Member</option>
              <option value="non_member" {{ $oldVisitorType === 'non_member' ? 'selected' : '' }}>Non-Member</option>
            </select>
            @error('visitor_type')
              <div class="v2-err">{{ $message }}</div>
            @enderror
          </div>

          <div class="v2-field" id="vcMemberCodeWrap">
            <label>Kode Member</label>
            <input class="nb-field {{ $errors->has('member_code') ? 'v2-err-input' : '' }}" id="vcMemberCode" name="member_code" placeholder="contoh: MBR-0001" value="{{ old('member_code') }}">
            @error('member_code')
              <div class="v2-err">{{ $message }}</div>
            @enderror
          </div>

          <div class="v2-field" id="vcNameWrap" style="display:none;">
            <label>Nama Visitor</label>
            <input class="nb-field {{ $errors->has('visitor_name') ? 'v2-err-input' : '' }}" id="vcVisitorName" name="visitor_name" placeholder="Nama non-member" value="{{ old('visitor_name') }}">
            @error('visitor_name')
              <div class="v2-err">{{ $message }}</div>
            @enderror
          </div>

          <div class="v2-field">
            <label>Cabang</label>
            <select class="nb-field {{ $errors->has('branch_id') ? 'v2-err-input' : '' }}" name="branch_id">
              <option value="">-</option>
              @foreach($branches as $b)
                <option value="{{ $b->id }}" {{ old('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
              @endforeach
            </select>
            @error('branch_id')
              <div class="v2-err">{{ $message }}</div>
            @enderror
          </div>

          <div class="v2-field">
            <label>Tujuan</label>
            <input class="nb-field {{ $errors->has('purpose') ? 'v2-err-input' : '' }}" name="purpose" placeholder="Baca, pinjam, referensi, dll" value="{{ old('purpose') }}">
            @error('purpose')
              <div class="v2-err">{{ $message }}</div>
            @enderror
          </div>

          <div class="v2-field">
            <label>Catatan</label>
            <textarea class="nb-field {{ $errors->has('notes') ? 'v2-err-input' : '' }}" name="notes" rows="3" placeholder="Opsional">{{ old('notes') }}</textarea>
            @error('notes')
              <div class="v2-err">{{ $message }}</div>
            @enderror
          </div>

          <div class="v2-btns" style="margin-top:14px;">
            <button class="nb-btn nb-btn-primary" type="submit" id="vcSubmitBtn" style="width:100%;">Simpan Check-in</button>
          </div>
        </form>
      </div>
    </aside>
  </section>
</div>

<div class="v2-modal" id="vcAuditModal" aria-hidden="true">
  <div class="v2-modal-card" role="dialog" aria-modal="true" aria-labelledby="vcAuditModalTitle">
    <div class="v2-modal-head">
      <h3 class="v2-modal-title" id="vcAuditModalTitle">Audit JSON</h3>
      <button type="button" class="v2-modal-close" id="vcAuditModalClose">Tutup</button>
    </div>
    <div class="v2-modal-body">
      <pre class="v2-modal-json" id="vcAuditJsonBody">{}</pre>
      <div class="v2-modal-actions">
        <button type="button" class="v2-audit-copy" id="vcAuditModalCopy">Copy JSON</button>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    const type = document.getElementById('vcVisitorType');
    const memberWrap = document.getElementById('vcMemberCodeWrap');
    const nameWrap = document.getElementById('vcNameWrap');
    const memberCode = document.getElementById('vcMemberCode');
    const visitorName = document.getElementById('vcVisitorName');
    const form = document.getElementById('vcCheckinForm');
    const submitBtn = document.getElementById('vcSubmitBtn');
    const selectAll = document.getElementById('vcSelectAll');
    const autoRefreshToggle = document.getElementById('vcAutoRefreshToggle');
    const refreshStatus = document.getElementById('vcRefreshStatus');
    const selectedForm = document.getElementById('vcCheckoutSelectedForm');
    const auditClearBtn = document.getElementById('vcAuditClearBtn');
    const auditFilterForm = document.getElementById('vcAuditFilterForm');
    const auditModal = document.getElementById('vcAuditModal');
    const auditModalClose = document.getElementById('vcAuditModalClose');
    const auditModalCopy = document.getElementById('vcAuditModalCopy');
    const auditJsonBody = document.getElementById('vcAuditJsonBody');
    const rowChecks = Array.from(document.querySelectorAll('.vc-row-check'));
    const autoRefreshKey = 'visitorCounterAutoRefreshEnabled';
    const refreshIntervalMs = 30000;
    let isSubmitting = false;
    let autoRefreshEnabled = true;
    const loadedAt = new Date();
    let nextRefreshAt = Date.now() + refreshIntervalMs;
    if (!type || !memberWrap || !nameWrap || !memberCode || !visitorName) return;

    const sync = function () {
      const isMember = type.value === 'member';
      memberWrap.style.display = isMember ? '' : 'none';
      nameWrap.style.display = isMember ? 'none' : '';
      memberCode.required = isMember;
      visitorName.required = !isMember;
    };

    type.addEventListener('change', sync);
    sync();

    if (form && submitBtn) {
      form.addEventListener('submit', function () {
        isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Menyimpan...';
      });
    }

    if (autoRefreshToggle) {
      const saved = localStorage.getItem(autoRefreshKey);
      if (saved === '0') autoRefreshEnabled = false;
      autoRefreshToggle.checked = autoRefreshEnabled;
      autoRefreshToggle.addEventListener('change', function () {
        autoRefreshEnabled = !!autoRefreshToggle.checked;
        localStorage.setItem(autoRefreshKey, autoRefreshEnabled ? '1' : '0');
        nextRefreshAt = Date.now() + refreshIntervalMs;
      });
    }

    if (selectAll && rowChecks.length > 0) {
      selectAll.addEventListener('change', function () {
        rowChecks.forEach(function (cb) { cb.checked = selectAll.checked; });
      });
      rowChecks.forEach(function (cb) {
        cb.addEventListener('change', function () {
          if (!cb.checked) {
            selectAll.checked = false;
            return;
          }
          selectAll.checked = rowChecks.every(function (x) { return x.checked; });
        });
      });
    }

    if (selectedForm) {
      selectedForm.addEventListener('submit', function (e) {
        selectedForm.querySelectorAll('input[name=\"ids[]\"]').forEach(function (el) { el.remove(); });
        const picked = rowChecks.filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
        if (picked.length === 0) {
          e.preventDefault();
          alert('Pilih minimal satu baris.');
          return;
        }
        picked.forEach(function (id) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'ids[]';
          input.value = id;
          selectedForm.appendChild(input);
        });
      });
    }

    if (auditFilterForm) {
      const autoSelects = Array.from(auditFilterForm.querySelectorAll('.js-audit-auto-submit'));
      const autoInput = auditFilterForm.querySelector('.js-audit-auto-input');
      const auditActionField = auditFilterForm.querySelector('[name="audit_action"]');
      const auditRoleField = auditFilterForm.querySelector('[name="audit_role"]');
      const auditSortField = auditFilterForm.querySelector('[name="audit_sort"]');
      const auditPerPageField = auditFilterForm.querySelector('[name="audit_per_page"]');
      const auditStorageKey = 'vcAuditFilterState';
      const auditRestoreFlag = 'vcAuditFilterRestored:' + window.location.pathname;
      let auditInputTimer = null;
      const getAuditPayload = function () {
        return {
          audit_action: auditActionField ? (auditActionField.value || '') : '',
          audit_role: auditRoleField ? (auditRoleField.value || '') : '',
          audit_sort: auditSortField ? (auditSortField.value || 'latest') : 'latest',
          audit_per_page: auditPerPageField ? (auditPerPageField.value || '15') : '15',
          audit_q: autoInput ? (autoInput.value || '') : '',
        };
      };
      const saveAuditPayload = function () {
        try {
          window.localStorage.setItem(auditStorageKey, JSON.stringify(getAuditPayload()));
        } catch (err) {}
      };
      const hasAuditQueryInUrl = function () {
        const p = new URLSearchParams(window.location.search);
        return p.has('audit_action') || p.has('audit_role') || p.has('audit_q') || p.has('audit_sort') || p.has('audit_per_page');
      };

      autoSelects.forEach(function (el) {
        el.addEventListener('change', function () {
          saveAuditPayload();
          auditFilterForm.submit();
        });
      });

      if (autoInput) {
        autoInput.addEventListener('input', function () {
          if (auditInputTimer) window.clearTimeout(auditInputTimer);
          auditInputTimer = window.setTimeout(function () {
            saveAuditPayload();
            auditFilterForm.submit();
          }, 450);
        });
      }

      if (hasAuditQueryInUrl()) {
        saveAuditPayload();
      } else {
        try {
          const restoredOnce = window.sessionStorage.getItem(auditRestoreFlag) === '1';
          if (!restoredOnce) {
            const raw = window.localStorage.getItem(auditStorageKey);
            if (raw) {
              const parsed = JSON.parse(raw);
              const hasSavedAuditFilter =
                !!(parsed.audit_action || parsed.audit_role || parsed.audit_q || (parsed.audit_sort && parsed.audit_sort !== 'latest') || (parsed.audit_per_page && parsed.audit_per_page !== '15'));
              if (hasSavedAuditFilter) {
                if (auditActionField) auditActionField.value = parsed.audit_action || '';
                if (auditRoleField) auditRoleField.value = parsed.audit_role || '';
                if (auditSortField) auditSortField.value = parsed.audit_sort || 'latest';
                if (auditPerPageField) auditPerPageField.value = parsed.audit_per_page || '15';
                if (autoInput) autoInput.value = parsed.audit_q || '';
                window.sessionStorage.setItem(auditRestoreFlag, '1');
                auditFilterForm.submit();
              }
            }
          }
        } catch (err) {}
      }
    }

    if (auditClearBtn) {
      auditClearBtn.addEventListener('click', function () {
        try {
          window.localStorage.removeItem('vcAuditFilterState');
          window.sessionStorage.removeItem('vcAuditFilterRestored:' + window.location.pathname);
        } catch (err) {}
      });
    }

    const highlightAuditKeyword = function () {
      if (!auditFilterForm) return;
      const input = auditFilterForm.querySelector('.js-audit-auto-input');
      if (!input) return;
      const keyword = (input.value || '').trim();
      if (!keyword) return;
      const escaped = keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      if (!escaped) return;
      const re = new RegExp('(' + escaped + ')', 'ig');
      const nodes = document.querySelectorAll('.v2-audit-action, .v2-audit-meta, .v2-audit-chip');
      nodes.forEach(function (el) {
        const text = el.textContent || '';
        if (!text) return;
        if (!re.test(text)) return;
        re.lastIndex = 0;
        el.innerHTML = text.replace(re, '<span class="v2-audit-hl">$1</span>');
      });
    };
    highlightAuditKeyword();

    const shouldSkipAutoRefresh = function () {
      if (isSubmitting || document.hidden) return true;
      const activeEl = document.activeElement;
      if (activeEl) {
        const tag = (activeEl.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select' || activeEl.isContentEditable) {
          return true;
        }
      }
      if (rowChecks.some(function (cb) { return cb.checked; })) {
        return true;
      }
      return false;
    };

    const formatTime = function (d) {
      return d.toLocaleTimeString('id-ID', { hour12: false });
    };

    const updateRefreshStatus = function () {
      if (!refreshStatus) return;
      const now = Date.now();
      const secLeft = Math.max(0, Math.ceil((nextRefreshAt - now) / 1000));
      const lastText = formatTime(loadedAt);
      if (!autoRefreshEnabled) {
        refreshStatus.textContent = 'Last update: ' + lastText + ' | Auto-refresh OFF';
        return;
      }
      if (shouldSkipAutoRefresh()) {
        refreshStatus.textContent = 'Last update: ' + lastText + ' | Menunggu idle';
        return;
      }
      refreshStatus.textContent = 'Last update: ' + lastText + ' | Refresh ' + secLeft + ' dtk';
    };
    window.setInterval(function () {
      updateRefreshStatus();
      if (!autoRefreshEnabled) return;
      if (shouldSkipAutoRefresh()) return;
      if (Date.now() < nextRefreshAt) return;
      nextRefreshAt = Date.now() + refreshIntervalMs;
      window.location.reload();
    }, 1000);

    const copyText = function (raw, done) {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(raw).then(done).catch(function () {});
        return;
      }
      const ta = document.createElement('textarea');
      ta.value = raw;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy');
        done();
      } catch (err) {}
      document.body.removeChild(ta);
    };

    const parseMeta = function (raw) {
      try {
        return JSON.parse(raw || '{}');
      } catch (err) {
        return { raw: raw || '{}' };
      }
    };

    const openAuditModal = function (raw) {
      if (!auditModal || !auditJsonBody) return;
      const parsed = parseMeta(raw);
      auditJsonBody.textContent = JSON.stringify(parsed, null, 2);
      auditModal.classList.add('is-open');
      auditModal.setAttribute('aria-hidden', 'false');
    };

    const closeAuditModal = function () {
      if (!auditModal) return;
      auditModal.classList.remove('is-open');
      auditModal.setAttribute('aria-hidden', 'true');
    };

    if (auditModalClose) {
      auditModalClose.addEventListener('click', closeAuditModal);
    }
    if (auditModal) {
      auditModal.addEventListener('click', function (e) {
        if (e.target === auditModal) closeAuditModal();
      });
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeAuditModal();
    });

    if (auditModalCopy && auditJsonBody) {
      auditModalCopy.addEventListener('click', function () {
        const raw = auditJsonBody.textContent || '{}';
        const oldText = auditModalCopy.textContent;
        copyText(raw, function () {
          auditModalCopy.textContent = 'Copied';
          auditModalCopy.classList.add('is-done');
          window.setTimeout(function () {
            auditModalCopy.textContent = oldText || 'Copy JSON';
            auditModalCopy.classList.remove('is-done');
          }, 1200);
        });
      });
    }

    document.addEventListener('click', function (e) {
      const viewBtn = e.target.closest('.js-audit-view');
      if (viewBtn) {
        openAuditModal(viewBtn.getAttribute('data-meta') || '{}');
        return;
      }

      const copyBtn = e.target.closest('.js-audit-copy');
      if (!copyBtn) return;
      const raw = copyBtn.getAttribute('data-meta') || '{}';
      const oldText = copyBtn.textContent;
      copyText(raw, function () {
        copyBtn.textContent = 'Copied';
        copyBtn.classList.add('is-done');
        window.setTimeout(function () {
          copyBtn.textContent = oldText || 'Copy JSON';
          copyBtn.classList.remove('is-done');
        }, 1200);
      });
    });
  })();
</script>
@endsection
