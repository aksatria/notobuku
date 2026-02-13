{{-- resources/views/member/dashboard.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Dashboard Member • NOTOBUKU')

@section('content')
@php
  $kpi = $kpi ?? [];
  $stats = $stats ?? [];
  $dueSoon = $dueSoon ?? [];
  $trend = $trend ?? ['days'=>[], 'max'=>0];
  $favorite = $favorite ?? [];
  $fines = $fines ?? ['outstanding' => 0, 'has_fines' => false];
  $notifUnread = (int) ($notifUnread ?? 0);
  $recentLoans = $recentLoans ?? [];
  $recentNotifications = $recentNotifications ?? [];

  $activeLoans = (int)($kpi['active_loans'] ?? 0);
  $activeItems = (int)($kpi['active_items'] ?? 0);
  $overdueItems = (int)($kpi['overdue_items'] ?? 0);
  $maxOverdueDays = (int)($kpi['max_overdue_days'] ?? 0);
  $maxRenewals = (int)($kpi['max_renewals'] ?? config('notobuku.loans.max_renewals', 2));
  $maxRenewCount = (int)($kpi['max_renew_count'] ?? 0);
  $renewRemaining = (int)($kpi['renew_remaining'] ?? max(0, $maxRenewals - $maxRenewCount));

  $totalLoansMonth = (int)($stats['total_loans_month'] ?? 0);
  $returnRateMonth = (float)($stats['return_rate_month'] ?? 0);

  $avgDurationDays = $stats['avg_duration_days'] ?? null;
  $avgDurationSample = (int)($stats['avg_duration_sample'] ?? 0);
  $fineOutstanding = (int) ($fines['outstanding'] ?? 0);
  $hasFines = (bool) ($fines['has_fines'] ?? false);

  $fmtDate = function ($date) {
    if (!$date) return '—';
    try { return \Carbon\Carbon::parse($date)->format('d M Y'); } catch (\Throwable $e) { return '—'; }
  };
  $daysLeft = function ($date) {
    if (!$date) return null;
    try {
      $d = \Carbon\Carbon::parse($date)->startOfDay();
      $today = \Carbon\Carbon::today();
      $diff = $today->diffInDays($d, false);
      if ($diff === 0) return 'Hari ini';
      if ($diff > 0) return $diff.' hari lagi';
      return 'Telat '.abs($diff).' hari';
    } catch (\Throwable $e) { return null; }
  };
  $idr = function ($val) {
    return 'Rp ' . number_format((int) $val, 0, ',', '.');
  };

  // Range waktu (UI)
  $startDate = request()->get('start_date', \Carbon\Carbon::now()->startOfMonth()->toDateString());
  $endDate = request()->get('end_date', \Carbon\Carbon::now()->toDateString());

  $pill = function(string $text, string $kind) {
    $map = [
      'ok'   => 'background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.30);color:rgba(21,128,61,.95);',
      'warn' => 'background:rgba(251,140,0,.12);border-color:rgba(251,140,0,.35);color:rgba(180,83,9,.95);',
      'info' => 'background:rgba(30,136,229,.10);border-color:rgba(30,136,229,.28);color:rgba(21,101,192,.95);',
      'neu'  => 'background:rgba(0,0,0,.04);border-color:rgba(0,0,0,.08);color:rgba(31,41,55,.85);',
    ];
    $style = $map[$kind] ?? $map['neu'];
    return '<span class="nb-pill" style="'.$style.'">'.$text.'</span>';
  };

  // Status pills (ringkas)
  $loanStatusPill = $activeLoans > 0 ? $pill('Aktif', 'info') : $pill('Tidak ada', 'neu');
  $itemsStatusPill = $activeItems > 0 ? $pill('Dipinjam', 'info') : $pill('Kosong', 'neu');
  $overduePill = $overdueItems > 0 ? $pill('Perlu perhatian', 'warn') : $pill('Aman', 'ok');
  $latePill = $maxOverdueDays > 0 ? $pill('Telat', 'warn') : $pill('On time', 'ok');
  $finePill = $hasFines ? $pill('Ada denda', 'warn') : $pill('Aman', 'ok');

  // Ringkas avg
  $avgText = '—';
  $avgHint = 'Belum cukup data pengembalian.';
  if ($avgDurationDays !== null) {
    $d = (float)$avgDurationDays;
    if ($d > 0 && $d < 1) { $avgText = '< 1 hari'; $avgHint = 'Rata-rata kurang dari 1 hari.'; }
    elseif ($d >= 1) { $avgText = number_format($d, 1).' hari'; $avgHint = 'Berdasarkan pengembalian terbaru.'; }
  }

  // DueSoon ambil top 3 (ringkas)
  $dueArr = is_array($dueSoon) ? $dueSoon : (is_iterable($dueSoon) ? iterator_to_array($dueSoon) : []);
  $dueTop = array_slice($dueArr, 0, 3);

  // helper extract (support array/object)
  $get = function($x, string $key, $default=null){
    if (is_array($x)) return $x[$key] ?? $default;
    if (is_object($x)) return $x->{$key} ?? $default;
    return $default;
  };

  $fmtDateTime = function ($date) {
    if (!$date) return '—';
    try { return \Carbon\Carbon::parse($date)->format('d M Y H:i'); } catch (\Throwable $e) { return '—'; }
  };
@endphp

<style>
  :root{
    --nb-card: rgba(255,255,255,.92);
    --nb-surface: rgba(248,250,252,.92);
    --nb-border: rgba(148,163,184,.25);
    --nb-text: rgba(11,37,69,.94);
    --nb-sub: rgba(11,37,69,.72);
    --nb-muted: rgba(11,37,69,.60);
    --nb-shadow: 0 12px 30px rgba(2,6,23,.06);

    --admin-primary:#1f6feb;
    --admin-primary-2:#0b5cd6;
    --admin-primary-soft: rgba(31,111,235,.12);
    --admin-accent:#22c55e;
    --admin-accent-soft: rgba(34,197,94,.12);
    --admin-amber:#f59e0b;
    --admin-amber-soft: rgba(245,158,11,.12);
    --admin-ink: rgba(11,37,69,.94);
    --admin-muted: rgba(11,37,69,.62);
    --admin-border: rgba(148,163,184,.25);

    --nb-primary: var(--admin-primary);
    --nb-primary-2: var(--admin-primary-2);
    --nb-primary-soft: var(--admin-primary-soft);
    --nb-accent: var(--admin-accent);
    --nb-accent-soft: var(--admin-accent-soft);
    --nb-amber: var(--admin-amber);
    --nb-amber-soft: var(--admin-amber-soft);

    --tint-blue: var(--admin-primary-soft);
    --tint-green: var(--admin-accent-soft);
    --tint-orange: var(--admin-amber-soft);
    --tint-slate: rgba(100,116,139,.10);
  }
  html.dark, body.dark, .dark{
    --nb-card: rgba(15,23,42,.62);
    --nb-surface: rgba(255,255,255,.04);
    --nb-border: rgba(148,163,184,.2);
    --nb-text: rgba(226,232,240,.95);
    --nb-sub: rgba(226,232,240,.70);
    --nb-muted: rgba(226,232,240,.68);
    --nb-shadow: 0 14px 34px rgba(0,0,0,.32);

    --admin-ink: rgba(226,232,240,.95);
    --admin-muted: rgba(226,232,240,.68);
    --admin-border: rgba(148,163,184,.2);
  }

  /* selaras Beranda: wrapper yang sama */
  .nb-admin-wrap{ max-width:1100px; margin:0 auto; }

  /* anti overflow (no horizontal scroll) */
  .nb-panel, .nb-section, .nb-grid, .nb-filter, .nb-head{ min-width:0; }
  .nb-actions, .nb-btn, .nb-pill{ min-width:0; max-width:100%; }

  .nb-admin-hero{
    position:relative;
    border-radius:26px;
    border:1px solid var(--admin-border);
    background: linear-gradient(135deg, rgba(31,111,235,.14), rgba(34,197,94,.10));
    padding:22px;
    overflow:hidden;
  }
  .nb-admin-hero::before{
    content:"";
    position:absolute;
    width:420px;
    height:420px;
    right:-180px;
    top:-220px;
    background: radial-gradient(circle at center, rgba(31,111,235,.28), rgba(31,111,235,0) 70%);
    opacity:.9;
    pointer-events:none;
  }
  .nb-admin-hero::after{
    content:"";
    position:absolute;
    width:360px;
    height:360px;
    left:-160px;
    bottom:-220px;
    background: radial-gradient(circle at center, rgba(34,197,94,.24), rgba(34,197,94,0) 70%);
    opacity:.9;
    pointer-events:none;
  }

  .nb-admin-hero-inner{
    position:relative;
    z-index:1;
    display:flex;
    gap:18px;
    align-items:flex-start;
    justify-content:space-between;
    flex-wrap:wrap;
  }
  .nb-admin-hero-left{ min-width:260px; flex:1 1 420px; }
  .nb-admin-hero-right{ min-width:240px; display:grid; gap:12px; }

  .nb-admin-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    border:1px solid var(--admin-border);
    border-radius:999px;
    padding:4px 10px;
    font-size:11px;
    font-weight:700;
    letter-spacing:.08px;
    text-transform:uppercase;
    background: var(--nb-card);
    color: var(--admin-muted);
  }
  .nb-admin-badge svg{ width:14px; height:14px; }

  .nb-admin-title{
    margin:10px 0 4px;
    font-size:22px;
    font-weight:700;
    letter-spacing:.2px;
    color: var(--admin-ink);
  }
  .nb-admin-sub{
    margin:0;
    font-size:13px;
    color: var(--admin-muted);
    max-width:560px;
    line-height:1.4;
  }

  .nb-admin-actions{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:14px;
  }
  .nb-admin-btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:9px 14px;
    border-radius:12px;
    border:1px solid var(--admin-border);
    background: var(--nb-card);
    color: var(--admin-ink);
    font-size:12.5px;
    font-weight:600;
    text-decoration:none;
  }
  .nb-admin-btn.is-disabled{ opacity:.6; pointer-events:none; }
  .nb-admin-btn.primary{
    background: linear-gradient(180deg, var(--nb-primary), var(--nb-primary-2));
    color:#fff;
    border-color: rgba(31,111,235,.45);
    box-shadow: 0 14px 26px rgba(31,111,235,.24);
  }
  .nb-admin-btn.ghost{ background: rgba(255,255,255,.7); }

  .nb-admin-kpis{
    margin-top:14px;
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap:12px;
  }
  .nb-admin-card{
    border:1px solid var(--admin-border);
    background: var(--nb-card);
    border-radius:18px;
    padding:14px;
    box-shadow: 0 14px 26px rgba(2,6,23,.04);
  }
  .nb-admin-kpi{ display:flex; gap:12px; align-items:center; }
  .nb-admin-kpi-ico{
    width:42px;
    height:42px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    background: var(--admin-primary-soft);
    color: var(--admin-primary);
  }
  .nb-admin-kpi-ico.green{ background: var(--admin-accent-soft); color: var(--admin-accent); }
  .nb-admin-kpi-ico.orange{ background: var(--admin-amber-soft); color: var(--admin-amber); }
  .nb-admin-kpi-ico svg{ width:18px; height:18px; }

  .nb-admin-kpi-label{ font-size:11px; font-weight:700; letter-spacing:.08px; text-transform:uppercase; color: var(--admin-muted); }
  .nb-admin-kpi-value{ margin-top:2px; font-size:22px; font-weight:700; color: var(--admin-ink); }
  .nb-admin-kpi-sub{ font-size:12px; color: var(--admin-muted); margin-top:2px; }

  .nb-admin-panel{
    margin-top:16px;
    border:1px solid var(--admin-border);
    background: var(--nb-card);
    border-radius:20px;
    padding:16px;
  }
  .nb-admin-panel-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
  }
  .nb-admin-panel-title{ font-size:14px; font-weight:700; color: var(--admin-ink); }
  .nb-admin-panel-sub{ font-size:12px; color: var(--admin-muted); margin-top:2px; }

  .nb-admin-list{ display:flex; flex-direction:column; gap:10px; }
  .nb-admin-item{
    display:flex;
    align-items:center;
    gap:12px;
    padding:10px;
    border-radius:14px;
    border:1px solid var(--admin-border);
    background: var(--nb-card);
  }
  .nb-admin-item-title{ font-size:12.8px; font-weight:600; color: var(--admin-ink); }
  .nb-admin-item-sub{ font-size:11.5px; color: var(--admin-muted); }
  .nb-admin-chip{
    margin-left:auto;
    font-size:11px;
    font-weight:700;
    color: var(--admin-primary);
    background: var(--admin-primary-soft);
    border:1px solid rgba(31,111,235,.2);
    padding:4px 8px;
    border-radius:999px;
    white-space:nowrap;
  }
  .nb-admin-empty{
    font-size:12px;
    color: var(--admin-muted);
    padding:10px 12px;
    border-radius:12px;
    border:1px dashed var(--admin-border);
    background: rgba(148,163,184,.08);
  }

  .nb-section{ padding: 14px 16px; border-top: 1px solid var(--nb-border); }
  .nb-h2{
    font-size:14px;
    font-weight:700;
    color:var(--nb-text);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
  }
  .nb-hint{ margin-top:6px; font-size:12.5px; color:var(--nb-sub); line-height:1.35; }

  .nb-filter{
    margin-top: 10px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:flex-end;
  }
  .nb-field{
    display:flex;
    flex-direction:column;
    gap:6px;
    min-width: 0;
    flex: 1 1 160px;
  }
  .nb-label{ font-size:12px; color: var(--nb-muted); font-weight:600; }
  .nb-input{
    width:100%;
    height:40px;
    padding: 8px 10px;
    border-radius: 12px;
    border: 1px solid var(--nb-border);
    background: var(--nb-card);
    color: var(--nb-text);
    font-size: 13px;
    outline: none;
    -webkit-appearance:none;
    appearance:none;
  }
  html.dark .nb-input{ background: rgba(255,255,255,.03); }

  .nb-pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid var(--nb-border);
    white-space:nowrap;
  }


  .nb-grid{
    padding: 14px;
    display:grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
  @media(min-width: 900px){
    .nb-grid{ grid-template-columns: repeat(4, minmax(0, 1fr)); }
  }

  .nb-card{
    border:1px solid var(--nb-border);
    border-radius:18px;
    background: var(--nb-card);
    padding: 12px;
    min-width:0;
  }
  .nb-card.blue{ background: color-mix(in srgb, var(--nb-card) 88%, var(--tint-blue)); }
  .nb-card.green{ background: color-mix(in srgb, var(--nb-card) 88%, var(--tint-green)); }
  .nb-card.orange{ background: color-mix(in srgb, var(--nb-card) 88%, var(--tint-orange)); }
  .nb-card.slate{ background: color-mix(in srgb, var(--nb-card) 88%, var(--tint-slate)); }

  @supports not (background: color-mix(in srgb, #000 50%, #fff)){
    .nb-card.blue{ background: var(--tint-blue); }
    .nb-card.green{ background: var(--tint-green); }
    .nb-card.orange{ background: var(--tint-orange); }
    .nb-card.slate{ background: var(--tint-slate); }
  }

  .nb-card .k{ font-size:12px; color:var(--nb-muted); font-weight:500; }
  .nb-card .v{ margin-top:4px; font-size:18px; font-weight:700; color:var(--nb-text); line-height:1.1; }
  .nb-card .s{ margin-top:6px; font-size:12.5px; color:var(--nb-sub); line-height:1.35; }

  .nb-due{ margin-top: 12px; display:flex; flex-direction:column; gap:10px; }
  .nb-due-item{
    border:1px solid var(--nb-border);
    border-radius: 16px;
    background: var(--nb-surface);
    padding: 12px;
    min-width:0;
  }
  .nb-due-date{ font-size:12px; color:var(--nb-muted); font-weight:600; }
  .nb-due-title{
    margin-top: 4px;
    font-size:13px;
    color:var(--nb-text);
    font-weight:600;
    line-height:1.35;
    display:-webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow:hidden;
  }
  .nb-due-sub{ margin-top:6px; font-size:12px; color:var(--nb-sub); }

  details.nb-details{
    margin-top: 12px;
    border:1px solid var(--nb-border);
    border-radius:16px;
    background: var(--nb-surface);
    overflow:hidden;
  }
  details.nb-details summary{
    list-style:none;
    cursor:pointer;
    padding: 12px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    color:var(--nb-text);
    font-size:13px;
    font-weight:700;
  }
  details.nb-details summary::-webkit-details-marker{ display:none; }
  .nb-details-body{ padding: 12px; border-top:1px solid var(--nb-border); }

  /* Mobile */
  @media (max-width: 560px){
    .nb-head{ padding: 14px; }
    .nb-title{ font-size:15px; }
    .nb-subtitle{ font-size:12.5px; }

    .nb-actions{ width:100%; }
    .nb-actions .nb-btn{ flex: 1 1 0; width: 100%; }

    .nb-h2 .nb-pill{ white-space: normal; line-height:1.25; }

    .nb-field{ flex: 1 1 100%; }
    .nb-filter > div[style*="display:flex"]{ width:100%; }
    .nb-filter > div[style*="display:flex"] .nb-btn{ flex: 1 1 0; width:100%; }

    .nb-grid{ grid-template-columns: 1fr; gap: 10px; padding: 12px 14px; }
    .nb-section{ padding: 12px 14px; }
  }
</style>


<div class="nb-admin-wrap">
    <section class="nb-admin-hero">
      <div class="nb-admin-hero-inner">
        <div class="nb-admin-hero-left">
          <div class="nb-admin-badge">
            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-home"></use></svg>
            Member Area
          </div>
          <h1 class="nb-admin-title">Dashboard Member</h1>
          <p class="nb-admin-sub">Ringkasan pinjaman, jatuh tempo, dan statistik personal Anda.</p>

          <div class="nb-admin-actions">
            <a class="nb-admin-btn primary" href="{{ route('member.pinjaman') }}">Pinjaman</a>
            <a class="nb-admin-btn ghost" href="{{ route('katalog.index') }}">Cari Buku</a>
          </div>
        </div>
      </div>
    </section>

      {{-- RANGE FILTER --}}
      <section class="nb-admin-panel">
        <div class="nb-admin-panel-head">
          <div>
            <div class="nb-admin-panel-title">Rentang Waktu</div>
            <div class="nb-admin-panel-sub">Gunakan untuk memfilter statistik (jika backend sudah mendukung).</div>
          </div>
          <span class="nb-pill" style="background:rgba(0,0,0,.04);border-color:rgba(0,0,0,.08);">
            {{ $fmtDate($startDate) }} – {{ $fmtDate($endDate) }}
          </span>
        </div>

        <form method="GET" action="{{ route('member.dashboard') }}">
          <div class="nb-filter">
            <div class="nb-field">
              <div class="nb-label">Dari</div>
              <input type="date" name="start_date" value="{{ $startDate }}" class="nb-input">
            </div>

            <div class="nb-field">
              <div class="nb-label">Sampai</div>
              <input type="date" name="end_date" value="{{ $endDate }}" class="nb-input">
            </div>

            <div style="display:flex; gap:10px; flex-wrap:wrap;">
              <button class="nb-admin-btn primary" type="submit">Terapkan</button>
              <a class="nb-admin-btn ghost" href="{{ route('member.dashboard') }}">Reset</a>
            </div>
          </div>
        </form>
      </section>

      {{-- QUICK STATS --}}
      <div class="nb-admin-kpis">
        <div class="nb-admin-card">
          <div class="nb-admin-kpi">
            <div class="nb-admin-kpi-ico">
              <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-rotate"></use></svg>
            </div>
            <div>
              <div class="nb-admin-kpi-label">Pinjaman Aktif</div>
              <div class="nb-admin-kpi-value">{{ $activeLoans }}</div>
              <div class="nb-admin-kpi-sub">{!! $loanStatusPill !!}</div>
            </div>
          </div>
        </div>

        <div class="nb-admin-card">
          <div class="nb-admin-kpi">
            <div class="nb-admin-kpi-ico green">
              <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-book"></use></svg>
            </div>
            <div>
              <div class="nb-admin-kpi-label">Item Dipinjam</div>
              <div class="nb-admin-kpi-value">{{ $activeItems }}</div>
              <div class="nb-admin-kpi-sub">{!! $itemsStatusPill !!}</div>
              <div class="nb-admin-kpi-sub" style="margin-top:6px;">
                <span class="nb-pill" title="Maks {{ $maxRenewals }}x perpanjang" style="background:rgba(14,116,144,.10);border-color:rgba(14,116,144,.25);color:rgba(14,116,144,.95);">
                  Perpanjang {{ $maxRenewCount }}/{{ $maxRenewals }} • Sisa {{ $renewRemaining }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="nb-admin-card">
          <div class="nb-admin-kpi">
            <div class="nb-admin-kpi-ico orange">
              <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-clock"></use></svg>
            </div>
            <div>
              <div class="nb-admin-kpi-label">Item Overdue</div>
              <div class="nb-admin-kpi-value">{{ $overdueItems }}</div>
              <div class="nb-admin-kpi-sub">{!! $overduePill !!}</div>
            </div>
          </div>
        </div>

        <div class="nb-admin-card">
          <div class="nb-admin-kpi">
            <div class="nb-admin-kpi-ico">
              <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-alert"></use></svg>
            </div>
            <div>
              <div class="nb-admin-kpi-label">Maks. Telat</div>
              <div class="nb-admin-kpi-value">{{ $maxOverdueDays }} <span style="font-size:14px;font-weight:600;">hari</span></div>
              <div class="nb-admin-kpi-sub">{!! $latePill !!}</div>
            </div>
          </div>
        </div>

        <div class="nb-admin-card">
          <div class="nb-admin-kpi">
            <div class="nb-admin-kpi-ico orange">
              <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-clipboard"></use></svg>
            </div>
            <div>
              <div class="nb-admin-kpi-label">Denda Aktif</div>
              <div class="nb-admin-kpi-value">{{ $idr($fineOutstanding) }}</div>
              <div class="nb-admin-kpi-sub">{!! $finePill !!}</div>
            </div>
          </div>
        </div>
      </div>

      {{-- CTA ringkas --}}
      <section class="nb-admin-panel" style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="nb-admin-btn primary" href="{{ route('member.pinjaman') }}">Lihat Pinjaman Saya</a>
        <a class="nb-admin-btn" href="{{ route('member.reservasi') }}">Reservasi</a>
        <a class="nb-admin-btn" href="{{ route('member.notifikasi') }}">
          Notifikasi
          @if($notifUnread > 0)
            <span class="nb-pill" style="background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.35);color:rgba(185,28,28,.95);">
              {{ $notifUnread }}
            </span>
          @endif
        </a>
      </section>

      {{-- QUICK SEARCH --}}
      <section class="nb-admin-panel">
        <div class="nb-admin-panel-head">
          <div>
            <div class="nb-admin-panel-title">Pencarian Cepat</div>
            <div class="nb-admin-panel-sub">Cari koleksi langsung tanpa membuka halaman katalog.</div>
          </div>
        </div>
        <form method="GET" action="{{ route('katalog.index') }}" class="nb-filter" style="margin-top:10px;">
          <div class="nb-field" style="flex:2 1 240px;">
            <div class="nb-label">Kata kunci</div>
            <input type="text" name="q" class="nb-input" placeholder="Judul, penulis, atau kata kunci...">
          </div>
          <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <button class="nb-admin-btn primary" type="submit">Cari</button>
            <a class="nb-admin-btn ghost" href="{{ route('katalog.index') }}">Buka Katalog</a>
          </div>
        </form>
      </section>

      {{-- DUE SOON (Top 3) --}}
      <section class="nb-admin-panel">
        <div class="nb-admin-panel-head">
          <div>
            <div class="nb-admin-panel-title">Jatuh Tempo Terdekat</div>
            <div class="nb-admin-panel-sub">Cek yang paling dekat agar tidak telat.</div>
          </div>
          <span style="font-size:12.5px; color:var(--nb-muted); font-weight:500;">Top 3</span>
        </div>

        @if(empty($dueTop))
          <div class="nb-hint" style="margin-top:12px;">Tidak ada jadwal jatuh tempo terdekat.</div>
        @else
          <div class="nb-due">
            @foreach($dueTop as $x)
              @php
                $due = $get($x, 'due_date');
                $title = $get($x, 'title', '—');
                $meta = $get($x, 'meta');
                $loanId = (int) $get($x, 'loan_id', 0);
                $barcode = $get($x, 'barcode', null);
                $daysText = $daysLeft($due);
              @endphp

              <div class="nb-due-item">
                <div class="nb-due-date">Due {{ $fmtDate($due) }}@if($daysText) · {{ $daysText }}@endif</div>
                <div class="nb-due-title">{{ $title }}</div>
                @if($meta || $barcode)
                  <div class="nb-due-sub">{{ $meta }}</div>
                  @if($barcode)
                    <div class="nb-due-sub">Barcode: {{ $barcode }}</div>
                  @endif
                @endif
                <div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
                  @if($loanId > 0)
                    <form method="POST" action="{{ route('member.pinjaman.extend', ['id' => $loanId]) }}">
                      @csrf
                      <button type="submit" class="nb-admin-btn">Perpanjang</button>
                    </form>
                  @endif
                  <a class="nb-admin-btn ghost" href="{{ route('member.pinjaman') }}">Detail</a>
                </div>
              </div>
            @endforeach
          </div>
        @endif
      </section>

      {{-- REKOMENDASI BUKU --}}
      <section class="nb-admin-panel">
        <div class="nb-admin-panel-head">
          <div>
            <div class="nb-admin-panel-title">Rekomendasi Buku</div>
            <div class="nb-admin-panel-sub">Diambil dari histori pinjaman favorit Anda.</div>
          </div>
        </div>
        <div class="nb-admin-list mt-3">
          @forelse(array_slice($favorite, 0, 6) as $row)
            <div class="nb-admin-item">
              <div class="min-w-0">
                <div class="nb-admin-item-title">{{ $row['title'] ?? '-' }}</div>
                <div class="nb-admin-item-sub">Dipinjam {{ (int) ($row['borrow_count'] ?? 0) }} kali</div>
              </div>
              <span class="nb-admin-chip">Rekomendasi</span>
            </div>
          @empty
            <div class="nb-admin-empty">Belum ada data rekomendasi.</div>
          @endforelse
        </div>
        <div class="mt-3">
          <a class="nb-admin-btn ghost" href="{{ route('katalog.index') }}">Lihat Katalog</a>
        </div>
      </section>

      {{-- RIWAYAT SINGKAT --}}
      <section class="nb-admin-panel">
        <div class="nb-admin-panel-head">
          <div>
            <div class="nb-admin-panel-title">Riwayat Singkat</div>
            <div class="nb-admin-panel-sub">Pinjaman terbaru Anda.</div>
          </div>
        </div>
        <div class="nb-admin-list mt-3">
          @forelse($recentLoans as $row)
            @php
              $status = (string) ($row['status'] ?? '');
              $label = $status === 'closed' ? 'Selesai' : 'Aktif';
            @endphp
            <div class="nb-admin-item">
              <div class="min-w-0">
                <div class="nb-admin-item-title">Loan #{{ (int) ($row['loan_id'] ?? 0) }}</div>
                <div class="nb-admin-item-sub">{{ $fmtDateTime($row['created_at'] ?? null) }}</div>
              </div>
              <span class="nb-admin-chip">{{ $label }}</span>
            </div>
          @empty
            <div class="nb-admin-empty">Belum ada riwayat pinjaman.</div>
          @endforelse
        </div>
        <div class="mt-3">
          <a class="nb-admin-btn ghost" href="{{ route('member.pinjaman') }}">Lihat Semua</a>
        </div>
      </section>

      {{-- DENDA & AKSI --}}
      <section class="nb-admin-panel">
        <div class="nb-admin-panel-head">
          <div>
            <div class="nb-admin-panel-title">Denda & Aksi</div>
            <div class="nb-admin-panel-sub">Ringkasan denda Anda saat ini.</div>
          </div>
        </div>
        <div class="nb-admin-list mt-3">
          <div class="nb-admin-item">
            <div class="min-w-0">
              <div class="nb-admin-item-title">Total Denda Aktif</div>
              <div class="nb-admin-item-sub">{{ $idr($fineOutstanding) }}</div>
            </div>
            <span class="nb-admin-chip">{{ $hasFines ? 'Perlu tindakan' : 'Aman' }}</span>
          </div>
        </div>
        <div class="mt-3" style="display:flex; gap:8px; flex-wrap:wrap;">
          <a class="nb-admin-btn primary" href="{{ route('member.notifikasi') }}">Lihat Denda</a>
          <span class="nb-admin-btn ghost is-disabled">Bayar</span>
        </div>
        <div class="nb-hint" style="margin-top:8px;">Pembayaran denda dilakukan melalui petugas.</div>
      </section>

      {{-- AKTIVITAS TERAKHIR --}}
      <section class="nb-admin-panel">
        <div class="nb-admin-panel-head">
          <div>
            <div class="nb-admin-panel-title">Aktivitas Terakhir</div>
            <div class="nb-admin-panel-sub">Notifikasi terbaru Anda.</div>
          </div>
        </div>
        <div class="nb-admin-list mt-3">
          @forelse($recentNotifications as $row)
            <div class="nb-admin-item">
              <div class="min-w-0">
                <div class="nb-admin-item-title">{{ $row['text'] ?? 'Notifikasi' }}</div>
                <div class="nb-admin-item-sub">{{ $fmtDateTime($row['created_at'] ?? null) }}</div>
              </div>
              <span class="nb-admin-chip">{{ !empty($row['read']) ? 'Dibaca' : 'Baru' }}</span>
            </div>
          @empty
            <div class="nb-admin-empty">Belum ada notifikasi terbaru.</div>
          @endforelse
        </div>
        <div class="mt-3">
          <a class="nb-admin-btn ghost" href="{{ route('member.notifikasi') }}">Buka Notifikasi</a>
        </div>
      </section>

      {{-- DETAIL (opsional) --}}
      <section class="nb-admin-panel">
        <details class="nb-details">
          <summary>
            <span>Detail Statistik</span>
            <span style="font-size:12px; color:var(--nb-muted); font-weight:500;">(opsional)</span>
          </summary>

          <div class="nb-details-body">
            <div class="nb-h2">Bulan Ini</div>
            <div class="nb-hint">Ringkasan bulan berjalan.</div>

            <div class="nb-admin-kpis" style="margin-top:12px;">
              <div class="nb-admin-card">
                <div class="nb-admin-kpi">
                  <div class="nb-admin-kpi-ico">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-rotate"></use></svg>
                  </div>
                  <div>
                    <div class="nb-admin-kpi-label">Pinjaman Bulan Ini</div>
                    <div class="nb-admin-kpi-value">{{ $totalLoansMonth }}</div>
                    <div class="nb-admin-kpi-sub">Total transaksi bulan berjalan</div>
                  </div>
                </div>
              </div>

              <div class="nb-admin-card">
                <div class="nb-admin-kpi">
                  <div class="nb-admin-kpi-ico">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-chart"></use></svg>
                  </div>
                  <div>
                    <div class="nb-admin-kpi-label">Return Rate Bulan Ini</div>
                    <div class="nb-admin-kpi-value">{{ number_format($returnRateMonth, 1) }}%</div>
                    <div class="nb-admin-kpi-sub">Rasio kembali vs pinjam</div>
                  </div>
                </div>
              </div>

              <div class="nb-admin-card">
                <div class="nb-admin-kpi">
                  <div class="nb-admin-kpi-ico green">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-clock"></use></svg>
                  </div>
                  <div>
                    <div class="nb-admin-kpi-label">Rata-rata Durasi</div>
                    <div class="nb-admin-kpi-value">{!! $avgText !!}</div>
                    <div class="nb-admin-kpi-sub">{{ $avgHint }}</div>
                  </div>
                </div>
              </div>

              <div class="nb-admin-card">
                <div class="nb-admin-kpi">
                  <div class="nb-admin-kpi-ico">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-clipboard"></use></svg>
                  </div>
                  <div>
                    <div class="nb-admin-kpi-label">Sample</div>
                    <div class="nb-admin-kpi-value">{{ $avgDurationSample }}</div>
                    <div class="nb-admin-kpi-sub">Jumlah data pengembalian</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </details>
      </section>

  </div>
@endsection



