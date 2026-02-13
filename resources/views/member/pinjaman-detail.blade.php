{{-- resources/views/member/pinjaman-detail.blade.php --}}
@extends('layouts.member')

@section('title','Detail Pinjaman • NOTOBUKU')
@section('member_title','Detail Pinjaman')
@section('member_subtitle','Informasi transaksi dan daftar koleksi yang dipinjam.')

@section('member.content')
@php
  $loan = $loan ?? null;
  $items = $items ?? collect();

  $fmt = function($d){
    if(!$d) return '—';
    try { return \Carbon\Carbon::parse($d)->format('d M Y'); } catch (\Throwable $e) { return '—'; }
  };

  $statusInfo = function($loan){
    $overdueDays = (int)($loan->overdue_days ?? 0);
    $isReturned = !empty($loan->return_date ?? null);
    $isOverdue = !$isReturned && $overdueDays > 0;

    if ($isReturned) return ['label'=>'Selesai','pill'=>'green','tint'=>'tint-green'];
    if ($isOverdue) return ['label'=>'Overdue','pill'=>'red','tint'=>'tint-orange'];
    return ['label'=>'Aktif','pill'=>'blue','tint'=>'tint-blue'];
  };

  $titleList = [];
  if ($items && count($items)) {
    foreach ($items as $it) {
      $titleList[] = is_array($it) ? ($it['title'] ?? null) : ($it->title ?? null);
    }
    $titleList = array_values(array_filter($titleList));
  } elseif ($loan) {
    $t = $loan->titles ?? null;
    if ($t) $titleList = is_array($t) ? array_values(array_filter($t)) : array_values(array_filter((array)$t));
  }

  $totalItems = count($titleList);
  $loanDate = $loan ? $fmt($loan->loan_date ?? null) : '—';
  $dueDate  = $loan ? $fmt($loan->due_date ?? null) : '—';
  $retDate  = $loan ? $fmt($loan->return_date ?? null) : '—';

  $info = $loan ? $statusInfo($loan) : ['label'=>'—','pill'=>'slate','tint'=>''];
@endphp

<style>
  /* =========================================================
     MEMBER — konsisten dengan BERANDA/DASHBOARD (solid, no gradient)
     - mobile-first, no horizontal scroll
     - warna lembut (card background), bukan border
     ========================================================= */

  html, body{ max-width:100%; overflow-x:hidden; }
  .nb-wrap{ max-width:1100px; margin:0 auto; }

  .nb-panel{
    background: rgba(255,255,255,.92);
    border: 1px solid rgba(15,23,42,.08);
    border-radius: 22px;
    box-shadow: 0 12px 30px rgba(2,6,23,.06);
    overflow:hidden;
  }
  html.dark .nb-panel{
    background: rgba(15,23,42,.62);
    border-color: rgba(148,163,184,.14);
    box-shadow: 0 14px 34px rgba(0,0,0,.32);
  }

  .nb-panel-topbar{ height:2px; background: rgba(30,136,229,.55); }
  html.dark .nb-panel-topbar{ background: rgba(56,189,248,.35); }

  .nb-section{ padding: 14px 16px; border-top: 1px solid rgba(15,23,42,.08); }
  html.dark .nb-section{ border-top-color: rgba(148,163,184,.14); }

  .nb-h2{ font-size:14px; font-weight:650; letter-spacing:.1px; color: rgba(2,6,23,.88); }
  html.dark .nb-h2{ color: rgba(226,232,240,.92); }

  .nb-hint{ margin-top:6px; font-size:12.5px; font-weight:500; color: rgba(2,6,23,.58); line-height:1.35; }
  html.dark .nb-hint{ color: rgba(226,232,240,.68); }

  .nb-pill{
    display:inline-flex; align-items:center; gap:6px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 650;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(2,6,23,.03);
    color: rgba(2,6,23,.78);
    white-space:nowrap;
  }
  html.dark .nb-pill{
    border-color: rgba(148,163,184,.18);
    background: rgba(226,232,240,.06);
    color: rgba(226,232,240,.82);
  }

  .nb-pill.blue{ background: rgba(30,136,229,.10); border-color: rgba(30,136,229,.18); color: rgba(21,101,192,.95); }
  .nb-pill.green{ background: rgba(46,125,50,.10); border-color: rgba(46,125,50,.18); color: rgba(30,110,40,.95); }
  .nb-pill.orange{ background: rgba(251,140,0,.12); border-color: rgba(251,140,0,.20); color: rgba(180,83,9,.95); }
  .nb-pill.red{ background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.20); color: rgba(153,27,27,.95); }

  html.dark .nb-pill.blue{ background: rgba(56,189,248,.14); border-color: rgba(56,189,248,.24); color: rgba(186,230,253,.92); }
  html.dark .nb-pill.green{ background: rgba(34,197,94,.14); border-color: rgba(34,197,94,.24); color: rgba(187,247,208,.92); }
  html.dark .nb-pill.orange{ background: rgba(251,146,60,.16); border-color: rgba(251,146,60,.26); color: rgba(254,215,170,.92); }
  html.dark .nb-pill.red{ background: rgba(248,113,113,.16); border-color: rgba(248,113,113,.26); color: rgba(254,202,202,.92); }

  .nb-btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding: 10px 12px;
    border-radius: 14px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(2,6,23,.02);
    font-size: 13px;
    font-weight: 650;
    color: rgba(2,6,23,.82);
    text-decoration:none;
    white-space:nowrap;
    max-width:100%;
  }
  .nb-btn:hover{ background: rgba(2,6,23,.035); }
  html.dark .nb-btn{
    border-color: rgba(148,163,184,.18);
    background: rgba(226,232,240,.06);
    color: rgba(226,232,240,.86);
  }
  html.dark .nb-btn:hover{ background: rgba(226,232,240,.09); }

  .nb-btn.primary{
    background: rgba(30,136,229,.12);
    border-color: rgba(30,136,229,.22);
    color: rgba(21,101,192,.95);
  }
  html.dark .nb-btn.primary{
    background: rgba(56,189,248,.14);
    border-color: rgba(56,189,248,.24);
    color: rgba(186,230,253,.95);
  }

  .nb-btn.danger{
    background: rgba(239,68,68,.10);
    border-color: rgba(239,68,68,.20);
    color: rgba(153,27,27,.95);
  }
  html.dark .nb-btn.danger{
    background: rgba(248,113,113,.14);
    border-color: rgba(248,113,113,.26);
    color: rgba(254,202,202,.92);
  }

  .nb-toolbar{ display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
  .nb-tabs{ display:flex; gap:8px; flex-wrap:wrap; }
  .nb-tab{
    padding: 8px 10px;
    border-radius: 999px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(2,6,23,.02);
    font-size: 12.5px;
    font-weight: 650;
    color: rgba(2,6,23,.72);
    text-decoration:none;
    white-space:nowrap;
  }
  .nb-tab.active{
    background: rgba(30,136,229,.10);
    border-color: rgba(30,136,229,.18);
    color: rgba(21,101,192,.95);
  }
  html.dark .nb-tab{
    border-color: rgba(148,163,184,.18);
    background: rgba(226,232,240,.06);
    color: rgba(226,232,240,.78);
  }
  html.dark .nb-tab.active{
    background: rgba(56,189,248,.14);
    border-color: rgba(56,189,248,.24);
    color: rgba(186,230,253,.92);
  }

  /* Cards grid */
  .nb-grid{
    display:grid;
    grid-template-columns: 1fr;
    gap: 12px;
  }
  @media(min-width: 900px){
    .nb-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }

  .nb-card{
    border: 1px solid rgba(15,23,42,.08);
    border-radius: 18px;
    background: rgba(255,255,255,.86);
    box-shadow: 0 10px 22px rgba(2,6,23,.05);
    padding: 14px;
    min-width:0;
  }
  html.dark .nb-card{
    background: rgba(15,23,42,.52);
    border-color: rgba(148,163,184,.14);
    box-shadow: 0 14px 26px rgba(0,0,0,.26);
  }
  .nb-card.tint-blue{ background: rgba(30,136,229,.06); }
  .nb-card.tint-green{ background: rgba(46,125,50,.06); }
  .nb-card.tint-orange{ background: rgba(251,140,0,.07); }
  html.dark .nb-card.tint-blue{ background: rgba(56,189,248,.10); }
  html.dark .nb-card.tint-green{ background: rgba(34,197,94,.10); }
  html.dark .nb-card.tint-orange{ background: rgba(251,146,60,.12); }

  .nb-card-top{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
  .nb-card-title{
    font-size:13px;
    font-weight:700;
    color: rgba(2,6,23,.86);
    line-height:1.35;
    min-width:0;
  }
  html.dark .nb-card-title{ color: rgba(226,232,240,.92); }

  .nb-card-meta{
    margin-top:6px;
    font-size:12.5px;
    font-weight:600;
    color: rgba(2,6,23,.62);
    line-height:1.35;
  }
  html.dark .nb-card-meta{ color: rgba(226,232,240,.70); }

  .nb-list{ margin-top:10px; padding-left: 18px; }
  .nb-list li{
    font-size:12.8px;
    font-weight:600;
    color: rgba(2,6,23,.78);
    line-height:1.35;
    overflow-wrap:anywhere;
  }
  html.dark .nb-list li{ color: rgba(226,232,240,.82); }

  .nb-actions-row{
    margin-top: 12px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
  }

  /* kecilkan kiri/kanan di HP seperti beranda */
  @media(max-width: 520px){
    .nb-section{ padding: 12px 14px; }
  }
</style>

<div class="nb-wrap">
  <div class="nb-panel">
    <div class="nb-panel-topbar"></div>

    @if(!$loan)
      <div class="nb-section">
        <div class="nb-h2">Data tidak ditemukan</div>
        <div class="nb-hint">Pinjaman ini tidak tersedia atau sudah dihapus.</div>
        <div class="nb-actions-row" style="margin-top:12px;">
          <a class="nb-btn primary" href="{{ route('member.pinjaman') }}">Kembali</a>
        </div>
      </div>
    @else
      <div class="nb-section">
        <div class="nb-toolbar">
          <div style="min-width:0;">
            <div class="nb-h2">Ringkasan</div>
            <div class="nb-hint">Transaksi #{{ $loan->id }}</div>
          </div>
          <span class="nb-pill {{ $info['pill'] }}">{{ $info['label'] }}</span>
        </div>

        <div class="nb-grid" style="margin-top:12px;">
          <div class="nb-card {{ $info['tint'] }}">
            <div class="nb-card-title">{{ $totalItems }} koleksi</div>
            <div class="nb-card-meta" style="margin-top:8px;">
              Tanggal: {{ $loanDate }}<br>
              Jatuh tempo: {{ $dueDate }}<br>
              Kembali: {{ $retDate }}
            </div>

            <div class="nb-actions-row" style="margin-top:12px;">
              <a class="nb-btn" href="{{ route('member.pinjaman') }}">Kembali</a>

              @if(empty($loan->return_date))
                <form method="POST" action="{{ route('member.pinjaman.extend', $loan->id) }}">
                  @csrf
                  <button type="submit" class="nb-btn primary">Perpanjang</button>
                </form>
              @endif
            </div>
          </div>

          <div class="nb-card">
            <div class="nb-card-title">Daftar Koleksi</div>
            @if($totalItems)
              <ul class="nb-list">
                @foreach($titleList as $t)
                  <li>{{ $t }}</li>
                @endforeach
              </ul>
            @else
              <div class="nb-hint" style="margin-top:10px;">Judul koleksi tidak tersedia.</div>
            @endif
          </div>
        </div>
      </div>
    @endif
  </div>
</div>
@endsection
