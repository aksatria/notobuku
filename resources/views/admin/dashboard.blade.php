{{-- resources/views/admin/dashboard.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Beranda Admin - NOTOBUKU')

@section('content')
@php
  $user = auth()->user();
  $userName = $user?->name ?? 'Admin';
  $userRole = $user?->role ?? 'admin';
  $userEmail = $user?->email ?? '-';
  $initials = collect(preg_split('/\s+/', $userName))
    ->filter()
    ->take(2)
    ->map(fn($part) => strtoupper(substr($part, 0, 1)))
    ->implode('');
  if ($initials === '') $initials = 'NB';

  $clicksTotal = (int) ($totals->clicks ?? 0);
  $borrowsTotal = (int) ($totals->borrows ?? 0);
  $conversionRate = $clicksTotal > 0 ? round(($borrowsTotal / $clicksTotal) * 100, 1) : 0;
  $recentClicksCount = isset($recentClicks) ? $recentClicks->count() : 0;
  $recentBorrowsCount = isset($recentBorrows) ? $recentBorrows->count() : 0;
  $topClickedCount = isset($topClicked) ? $topClicked->count() : 0;
  $topBorrowedCount = isset($topBorrowed) ? $topBorrowed->count() : 0;
  $branchCount = isset($branches) ? $branches->count() : 0;
  $interopP95 = (int) ($interopP95 ?? 0);
  $interopInvalid = (int) ($interopInvalid ?? 0);
  $interopLimited = (int) ($interopLimited ?? 0);
  $interopHealth = (string) ($interopHealth ?? 'Sehat');
  $opacP95 = (int) ($opacP95 ?? 0);
  $opacP50 = (int) ($opacP50 ?? 0);
  $opacRequests = (int) ($opacRequests ?? 0);
  $opacErrorRate = (float) ($opacErrorRate ?? 0);
  $opacMetrics = (array) ($opacMetrics ?? []);
  $opacP95Series24h = array_values((array) ($opacP95Series24h ?? []));
  $opacP95Min24h = count($opacP95Series24h) > 0 ? min($opacP95Series24h) : 0;
  $opacP95Max24h = count($opacP95Series24h) > 0 ? max($opacP95Series24h) : 0;
  $opacP95Now24h = count($opacP95Series24h) > 0 ? (int) end($opacP95Series24h) : 0;
  $opacSlo = (array) ($opacSlo ?? []);
  $opacSearchAnalytics = (array) data_get($opacMetrics ?? [], 'search_analytics', []);
  $opacTopKeywords = collect((array) ($opacSearchAnalytics['top_keywords'] ?? []))->take(6)->values();
  $opacTopZero = collect((array) ($opacSearchAnalytics['top_zero_result_queries'] ?? []))->take(6)->values();
  $opacSearchTotal = (int) ($opacSearchAnalytics['total_searches'] ?? 0);
  $opacSearchSuccessRate = (float) ($opacSearchAnalytics['success_rate_pct'] ?? 0);
  $opacZeroDistinct = (int) ($opacSearchAnalytics['zero_result_distinct_queries'] ?? 0);
  $opacSloState = (string) ($opacSlo['state'] ?? 'ok');
  $opacBurn5 = (float) ($opacSlo['burn_rate_5m'] ?? 0);
  $opacBurn60 = (float) ($opacSlo['burn_rate_60m'] ?? 0);
  $opacSloTarget = (float) ($opacSlo['target_pct'] ?? 99.5);
  $opacSloClass = match ($opacSloState) {
    'critical' => 'critical',
    'warning' => 'warning',
    default => 'good',
  };
  $opacSloLabel = match ($opacSloState) {
    'critical' => 'Kritis',
    'warning' => 'Waspada',
    default => 'Sehat',
  };
  $opacAlertClass = match ($opacSloState) {
    'critical' => 'critical',
    'warning' => 'warn',
    default => 'ok',
  };
  $interopMetrics = (array) ($interopMetrics ?? []);
  $interopOaiP95 = (int) data_get($interopMetrics, 'latency.oai.p95_ms', 0);
  $interopSruP95 = (int) data_get($interopMetrics, 'latency.sru.p95_ms', 0);
  $interopOaiLimited = (int) data_get($interopMetrics, 'counters.oai_rate_limited', 0);
  $interopSruLimited = (int) data_get($interopMetrics, 'counters.sru_rate_limited', 0);
  $interopHistory24h = (array) data_get($interopMetrics, 'history.last_24h', []);
  $interopP95Series24h = array_values(array_map(fn($r) => (int) ($r['p95_ms'] ?? 0), $interopHistory24h));
  $interopP95Min24h = count($interopP95Series24h) > 0 ? min($interopP95Series24h) : 0;
  $interopP95Max24h = count($interopP95Series24h) > 0 ? max($interopP95Series24h) : 0;
  $interopP95Latest24h = count($interopP95Series24h) > 0 ? (int) end($interopP95Series24h) : 0;
  $interopCriticalAlert = (array) data_get($interopMetrics, 'alerts.critical_streak', []);
  $interopCriticalActive = (bool) ($interopCriticalAlert['active'] ?? false);
  $interopCriticalStreak = (int) ($interopCriticalAlert['streak_minutes'] ?? 0);
  $interopCriticalThreshold = (int) ($interopCriticalAlert['threshold_minutes'] ?? 0);
  $interopHealthClass = match ($interopHealth) {
    'Kritis' => 'critical',
    'Waspada' => 'warning',
    default => 'good',
  };
  $uatSummary = (array) ($uatSummary ?? ['pass' => 0, 'fail' => 0, 'pending' => 0]);
  $uatRecent = $uatRecent ?? collect();
  $uatPass = (int) ($uatSummary['pass'] ?? 0);
  $uatFail = (int) ($uatSummary['fail'] ?? 0);
  $uatPending = (int) ($uatSummary['pending'] ?? 0);

  $quickActions = collect([
    ['label' => 'Tambah Bibliografi', 'desc' => 'Input koleksi baru', 'route' => 'katalog.create', 'icon' => '#nb-icon-plus'],
    ['label' => 'Kelola Katalog', 'desc' => 'Cari dan edit koleksi', 'route' => 'katalog.index', 'icon' => '#nb-icon-book'],
    ['label' => 'Anggota', 'desc' => 'Data member dan aktivitas', 'route' => 'anggota.index', 'icon' => '#nb-icon-users'],
    ['label' => 'Sirkulasi', 'desc' => 'Pinjam, kembali, perpanjang', 'route' => 'transaksi.index', 'icon' => '#nb-icon-rotate'],
    ['label' => 'Laporan', 'desc' => 'Ringkasan transaksi', 'route' => 'transaksi.dashboard', 'icon' => '#nb-icon-chart'],
    ['label' => 'MARC Settings', 'desc' => 'Atur mapping MARC', 'route' => 'admin.marc.settings', 'icon' => '#nb-icon-clipboard'],
    ['label' => 'Komunitas', 'desc' => 'Moderasi dan konten', 'route' => 'komunitas.feed', 'icon' => '#nb-icon-chat'],
    ['label' => 'Notifikasi', 'desc' => 'Pusat notifikasi sistem', 'route' => 'notifikasi.index', 'icon' => '#nb-icon-bell'],
  ])->filter(fn($item) => \Illuminate\Support\Facades\Route::has($item['route']))->values();

  $ctaPrimaryRoute = collect(['transaksi.dashboard', 'transaksi.index', 'katalog.index'])
    ->first(fn($route) => \Illuminate\Support\Facades\Route::has($route));
  $ctaPrimaryUrl = $ctaPrimaryRoute ? route($ctaPrimaryRoute) : '#';
  $ctaPrimaryLabel = match ($ctaPrimaryRoute) {
    'transaksi.dashboard' => 'Buka Laporan',
    'transaksi.index' => 'Lihat Transaksi',
    'katalog.index' => 'Kelola Katalog',
    default => 'Buka',
  };

  $ctaSecondaryRoute = collect(['katalog.create', 'katalog.index'])
    ->first(fn($route) => \Illuminate\Support\Facades\Route::has($route));
  $ctaSecondaryUrl = $ctaSecondaryRoute ? route($ctaSecondaryRoute) : '#';
@endphp

<style>
  .nb-admin-wrap{
    max-width:1180px;
    margin:0 auto;
    --nb-card: rgba(255,255,255,.92);
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
  }
  html.dark .nb-admin-wrap{
    --nb-card: rgba(15,23,42,.62);
    --admin-ink: rgba(226,232,240,.95);
    --admin-muted: rgba(226,232,240,.68);
    --admin-border: rgba(148,163,184,.2);
  }

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
  html.dark .nb-admin-hero{
    background: linear-gradient(135deg, rgba(31,111,235,.22), rgba(15,23,42,.68));
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
  html.dark .nb-admin-badge{
    background: rgba(15,23,42,.6);
    color: rgba(226,232,240,.85);
  }

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
    transition: background .12s ease, border-color .12s ease, box-shadow .12s ease, transform .06s ease;
  }
  .nb-admin-btn svg{ width:16px; height:16px; }
  .nb-admin-btn:hover{ box-shadow: 0 10px 20px rgba(15,23,42,.08); }
  .nb-admin-btn:active{ transform: translateY(1px); }
  .nb-admin-btn.primary{
    background: linear-gradient(180deg, var(--admin-primary), var(--admin-primary-2));
    color:#fff;
    border-color: rgba(31,111,235,.45);
    box-shadow: 0 14px 26px rgba(31,111,235,.24);
  }
  .nb-admin-btn.primary:hover{ box-shadow: 0 16px 30px rgba(31,111,235,.28); }
  .nb-admin-btn.ghost{ background: rgba(255,255,255,.7); }
  html.dark .nb-admin-btn.ghost{ background: rgba(15,23,42,.5); }

  .nb-admin-usercard{
    display:flex;
    align-items:center;
    gap:12px;
    padding:12px;
    border-radius:18px;
    border:1px solid var(--admin-border);
    background: var(--nb-card);
  }

  .nb-admin-avatar{
    width:44px;
    height:44px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    background: var(--admin-primary-soft);
    color: var(--admin-primary);
    font-weight:700;
    letter-spacing:.5px;
  }
  .nb-admin-user-name{ font-size:13px; font-weight:700; color: var(--admin-ink); }
  .nb-admin-user-mail{ font-size:12px; color: var(--admin-muted); }
  .nb-admin-role{
    display:inline-flex;
    align-items:center;
    margin-top:6px;
    font-size:11px;
    font-weight:700;
    letter-spacing:.08px;
    text-transform:uppercase;
    color: var(--admin-primary);
    background: var(--admin-primary-soft);
    border:1px solid rgba(31,111,235,.25);
    padding:2px 8px;
    border-radius:999px;
  }

  .nb-admin-mini{
    padding:12px;
    border-radius:18px;
    border:1px dashed var(--admin-border);
    background: rgba(248,250,252,.75);
  }
  .nb-admin-mini-label{ font-size:11px; letter-spacing:.08px; font-weight:700; color: var(--admin-muted); text-transform:uppercase; }
  .nb-admin-mini-value{ margin-top:4px; font-size:16px; font-weight:700; color: var(--admin-ink); }
  .nb-admin-mini-sub{ margin-top:2px; font-size:12px; color: var(--admin-muted); }
  html.dark .nb-admin-mini{ background: rgba(15,23,42,.55); }

  .nb-admin-alert{
    margin-top:12px;
    border-radius:14px;
    border:1px solid rgba(220,38,38,.3);
    background: rgba(220,38,38,.08);
    padding:10px 12px;
    font-size:12.5px;
    font-weight:600;
    color: rgba(153,27,27,.95);
  }
  html.dark .nb-admin-alert{
    border-color: rgba(248,113,113,.35);
    background: rgba(127,29,29,.35);
    color: rgba(254,226,226,.95);
  }

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
  html.dark .nb-admin-card{ box-shadow: 0 14px 26px rgba(0,0,0,.22); }

  .nb-admin-grid-3{
    margin-top:16px;
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap:12px;
  }
  .nb-admin-grid-2 .nb-admin-panel,
  .nb-admin-grid-3 .nb-admin-panel{ margin-top:0; }

  .nb-admin-quick-grid{
    margin-top:12px;
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap:10px;
  }
  .nb-admin-quick-card{
    display:flex;
    align-items:flex-start;
    gap:10px;
    padding:12px;
    border-radius:14px;
    border:1px solid var(--admin-border);
    background: rgba(255,255,255,.75);
    transition: border-color .12s ease, box-shadow .12s ease, transform .06s ease;
  }
  html.dark .nb-admin-quick-card{ background: rgba(15,23,42,.55); }
  .nb-admin-quick-card:hover{
    border-color: rgba(31,111,235,.3);
    box-shadow: 0 10px 20px rgba(2,6,23,.08);
    transform: translateY(-1px);
  }
  .nb-admin-quick-ico{
    width:34px;
    height:34px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    background: var(--admin-primary-soft);
    color: var(--admin-primary);
    flex: 0 0 auto;
  }
  .nb-admin-quick-ico svg{ width:16px; height:16px; }
  .nb-admin-quick-title{ font-size:12.6px; font-weight:700; color: var(--admin-ink); }
  .nb-admin-quick-sub{ font-size:11.5px; color: var(--admin-muted); margin-top:2px; }
  .nb-admin-quick-go{
    margin-left:auto;
    width:22px;
    height:22px;
    border-radius:999px;
    border:1px solid var(--admin-border);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:12px;
    color: var(--admin-muted);
  }

  .nb-admin-insights{
    margin-top:12px;
    display:grid;
    gap:10px;
  }
  .nb-admin-insight{
    padding:12px;
    border-radius:14px;
    border:1px dashed var(--admin-border);
    background: rgba(248,250,252,.75);
  }
  html.dark .nb-admin-insight{ background: rgba(15,23,42,.55); }
  .nb-admin-insight-label{ font-size:11px; font-weight:700; letter-spacing:.08px; text-transform:uppercase; color: var(--admin-muted); }
  .nb-admin-insight-value{ margin-top:4px; font-size:20px; font-weight:700; color: var(--admin-ink); }
  .nb-admin-insight-sub{ margin-top:2px; font-size:12px; color: var(--admin-muted); }
  .nb-admin-health-breakdown{
    margin-top:6px;
    display:grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap:6px;
  }
  .nb-admin-health-breakdown .row{
    border:1px dashed var(--admin-border);
    background: rgba(248,250,252,.75);
    border-radius:10px;
    padding:6px 8px;
    font-size:11.5px;
    color: var(--admin-muted);
  }
  .nb-admin-health-breakdown .row b{ color: var(--admin-ink); font-weight:700; }
  html.dark .nb-admin-health-breakdown .row{ background: rgba(15,23,42,.55); }
  .nb-admin-health-actions{
    margin-top:8px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:8px;
  }
  .nb-admin-health-sync{
    font-size:11px;
    font-weight:700;
    color: var(--admin-muted);
    display:inline-flex;
    align-items:center;
    gap:6px;
  }
  .nb-admin-health-sync::before{
    content:"";
    width:7px;
    height:7px;
    border-radius:999px;
    background: currentColor;
    display:inline-block;
  }
  .nb-admin-health-sync.live{ color: rgba(6,95,70,.95); }
  .nb-admin-health-sync.offline{ color: rgba(153,27,27,.95); }
  html.dark .nb-admin-health-sync.live{ color: rgba(167,243,208,.95); }
  html.dark .nb-admin-health-sync.offline{ color: rgba(254,202,202,.95); }
  .nb-admin-health-sync.refreshing{ color: var(--admin-primary); }
  html.dark .nb-admin-health-sync.refreshing{ color: rgba(147,197,253,.95); }
  .nb-admin-health-refresh{
    min-width:96px;
    text-align:center;
  }
  .nb-admin-health-refresh{
    border:1px solid var(--admin-border);
    border-radius:999px;
    background: var(--nb-card);
    color: var(--admin-ink);
    font-size:11px;
    font-weight:700;
    padding:4px 10px;
    line-height:1.2;
    cursor:pointer;
  }
  .nb-admin-health-refresh:hover{
    border-color: rgba(31,111,235,.3);
    color: var(--admin-primary);
  }
  .nb-admin-health-refresh:disabled{
    opacity:.55;
    cursor:wait;
  }
  .nb-admin-health-export{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:1px solid var(--admin-border);
    border-radius:999px;
    background: var(--nb-card);
    color: var(--admin-ink);
    font-size:11px;
    font-weight:700;
    padding:4px 10px;
    line-height:1.2;
    text-decoration:none;
    min-width:96px;
  }
  .nb-admin-health-export:hover{
    border-color: rgba(31,111,235,.3);
    color: var(--admin-primary);
  }
  .nb-admin-health-mini{
    margin-top:8px;
    display:grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap:6px;
  }
  .nb-admin-health-mini .row{
    border:1px dashed var(--admin-border);
    background: rgba(248,250,252,.75);
    border-radius:10px;
    padding:6px 8px;
    font-size:11px;
    color: var(--admin-muted);
  }
  .nb-admin-health-mini .row b{ color: var(--admin-ink); }
  html.dark .nb-admin-health-mini .row{ background: rgba(15,23,42,.55); }
  .nb-admin-health-alert{
    margin-top:6px;
    font-size:11.5px;
    font-weight:700;
  }
  .nb-admin-health-alert.ok{ color: rgba(6,95,70,.95); }
  .nb-admin-health-alert.warn{ color: rgba(146,64,14,.95); }
  .nb-admin-health-alert.critical{ color: rgba(153,27,27,.95); }
  .nb-admin-health-pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    border-radius:999px;
    padding:4px 10px;
    font-size:13px;
    font-weight:700;
    border:1px solid transparent;
  }
  .nb-admin-health-pill::before{
    content:"";
    width:8px;
    height:8px;
    border-radius:999px;
    background: currentColor;
    display:inline-block;
  }
  .nb-admin-health-pill.good{
    color: rgba(6,95,70,.95);
    background: rgba(16,185,129,.12);
    border-color: rgba(16,185,129,.35);
  }
  .nb-admin-health-pill.warning{
    color: rgba(146,64,14,.95);
    background: rgba(245,158,11,.14);
    border-color: rgba(245,158,11,.35);
  }
  .nb-admin-health-pill.critical{
    color: rgba(153,27,27,.95);
    background: rgba(239,68,68,.12);
    border-color: rgba(239,68,68,.35);
  }
  html.dark .nb-admin-health-pill.good{
    color: rgba(167,243,208,.96);
    background: rgba(16,185,129,.2);
    border-color: rgba(16,185,129,.45);
  }
  html.dark .nb-admin-health-pill.warning{
    color: rgba(253,230,138,.96);
    background: rgba(245,158,11,.22);
    border-color: rgba(245,158,11,.45);
  }
  html.dark .nb-admin-health-pill.critical{
    color: rgba(254,202,202,.96);
    background: rgba(239,68,68,.22);
    border-color: rgba(239,68,68,.45);
  }
  .nb-admin-opac-spark{
    margin-top:8px;
    padding:8px;
    border-radius:12px;
    border:1px solid var(--admin-border);
    background: rgba(248,250,252,.7);
  }
  html.dark .nb-admin-opac-spark{
    background: rgba(15,23,42,.55);
  }
  .nb-admin-opac-spark svg{
    width:100%;
    height:52px;
    display:block;
  }
  .nb-admin-opac-spark .line{
    fill:none;
    stroke:#1f6feb;
    stroke-width:2;
    stroke-linecap:round;
    stroke-linejoin:round;
  }
  .nb-admin-opac-spark .area{
    fill: rgba(31,111,235,.12);
  }
  .nb-admin-opac-spark-meta{
    margin-top:6px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    font-size:11px;
    color: var(--admin-muted);
  }
  .nb-admin-opac-analytics{
    margin-top:10px;
    padding-top:10px;
    border-top:1px dashed var(--admin-border);
  }

  .nb-admin-auto-grid{
    margin-top:12px;
    display:grid;
    gap:12px;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  }
  .nb-admin-auto-card{
    padding:14px;
    border-radius:16px;
    border:1px solid var(--admin-border);
    background: var(--nb-card);
  }
  .nb-admin-auto-title{
    font-size:12.5px;
    font-weight:700;
    color: var(--admin-ink);
  }
  .nb-admin-auto-total{
    margin-top:6px;
    font-size:20px;
    font-weight:700;
    color: var(--admin-ink);
  }
  .nb-admin-auto-sub{
    margin-top:2px;
    font-size:12px;
    color: var(--admin-muted);
  }
  .nb-admin-auto-list{
    margin-top:10px;
    display:grid;
    gap:6px;
  }
  .nb-admin-auto-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    font-size:12px;
    color: var(--admin-muted);
  }
  .nb-admin-auto-row b{ color: var(--admin-ink); font-weight:700; }
  .nb-admin-auto-tip{
    font-size:12px;
    color: var(--admin-muted);
    line-height:1.4;
  }
  .nb-admin-auto-empty{
    font-size:12px;
    color: var(--admin-muted);
  }
  .nb-admin-auto-chart{
    margin-top:10px;
    display:grid;
    gap:6px;
  }
  .nb-admin-auto-bar{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:11px;
    color: var(--admin-muted);
  }
  .nb-admin-auto-bar span{
    height:8px;
    border-radius:999px;
    background: linear-gradient(90deg, rgba(59,130,246,.25), rgba(59,130,246,.7));
    flex: 1 1 auto;
    position: relative;
    overflow: hidden;
  }
  .nb-admin-auto-bar span::after{
    content:"";
    position:absolute;
    inset:0;
    transform: scaleX(var(--pct, 0));
    transform-origin:left;
    background: linear-gradient(90deg, rgba(59,130,246,.55), rgba(59,130,246,1));
  }
  .nb-admin-auto-bar em{
    font-style:normal;
    min-width:50px;
    text-align:right;
  }
  .nb-admin-auto-kpi{
    margin-top:10px;
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap:8px;
  }
  .nb-admin-auto-kpi-item{
    padding:8px;
    border-radius:12px;
    border:1px dashed var(--admin-border);
    background: rgba(248,250,252,.75);
  }
  html.dark .nb-admin-auto-kpi-item{ background: rgba(15,23,42,.55); }
  .nb-admin-auto-kpi-item .label{ font-size:10.5px; color: var(--admin-muted); text-transform:uppercase; letter-spacing:.08px; font-weight:700; }
  .nb-admin-auto-kpi-item .value{ margin-top:4px; font-size:14px; font-weight:700; color: var(--admin-ink); }
  .nb-admin-auto-split{
    margin-top:10px;
    display:grid;
    gap:10px;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  }
  .nb-admin-auto-block-title{
    font-size:10.5px;
    text-transform:uppercase;
    letter-spacing:.08px;
    font-weight:700;
    color: var(--admin-muted);
    margin-bottom:6px;
  }

  .nb-admin-cta{
    position:relative;
    overflow:hidden;
    border-color: rgba(31,111,235,.25);
    background: linear-gradient(135deg, rgba(31,111,235,.12), rgba(14,116,144,.10));
  }
  html.dark .nb-admin-cta{
    background: linear-gradient(135deg, rgba(31,111,235,.22), rgba(15,23,42,.7));
  }
  .nb-admin-cta::before{
    content:"";
    position:absolute;
    width:220px;
    height:220px;
    right:-80px;
    top:-80px;
    background: radial-gradient(circle at center, rgba(31,111,235,.3), rgba(31,111,235,0) 70%);
    pointer-events:none;
  }
  .nb-admin-cta-inner{ position:relative; z-index:1; }
  .nb-admin-cta-title{ font-size:16px; font-weight:700; color: var(--admin-ink); }
  .nb-admin-cta-sub{ margin-top:6px; font-size:12.5px; color: var(--admin-muted); line-height:1.4; }
  .nb-admin-cta-actions{ margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; }
  .nb-admin-btn.is-disabled{ opacity:.6; pointer-events:none; }

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

  .nb-admin-status{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 10px;
    border-radius:999px;
    border:1px solid rgba(16,185,129,.35);
    background: rgba(16,185,129,.12);
    color: rgba(6,95,70,.95);
    font-size:12px;
    font-weight:700;
  }
  html.dark .nb-admin-status{
    border-color: rgba(16,185,129,.45);
    background: rgba(16,185,129,.2);
    color: rgba(167,243,208,.95);
  }

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
  .nb-admin-uat-head{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    align-items:center;
  }
  .nb-admin-uat-pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    border-radius:999px;
    padding:4px 10px;
    font-size:11px;
    font-weight:700;
    border:1px solid var(--admin-border);
    background: rgba(248,250,252,.8);
    color: var(--admin-muted);
  }
  .nb-admin-uat-pill.pass{
    color: rgba(6,95,70,.95);
    border-color: rgba(16,185,129,.35);
    background: rgba(16,185,129,.12);
  }
  .nb-admin-uat-pill.fail{
    color: rgba(153,27,27,.95);
    border-color: rgba(239,68,68,.35);
    background: rgba(239,68,68,.12);
  }
  .nb-admin-uat-pill.pending{
    color: rgba(146,64,14,.95);
    border-color: rgba(245,158,11,.35);
    background: rgba(245,158,11,.15);
  }
  .nb-admin-uat-table{
    margin-top:10px;
    border:1px solid var(--admin-border);
    border-radius:12px;
    overflow:hidden;
  }
  .nb-admin-uat-row{
    display:grid;
    grid-template-columns: 110px 92px 1fr 145px 1fr;
    gap:8px;
    padding:8px 10px;
    border-top:1px solid var(--admin-border);
    font-size:12px;
    align-items:center;
  }
  .nb-admin-uat-row:first-child{ border-top:none; }
  .nb-admin-uat-row.header{
    background: rgba(248,250,252,.85);
    font-size:11px;
    font-weight:700;
    color: var(--admin-muted);
    text-transform:uppercase;
    letter-spacing:.04em;
  }
  .nb-admin-uat-status{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border-radius:999px;
    border:1px solid var(--admin-border);
    padding:2px 8px;
    font-size:11px;
    font-weight:700;
    text-transform:uppercase;
  }
  .nb-admin-uat-status.pass{
    color: rgba(6,95,70,.95);
    border-color: rgba(16,185,129,.35);
    background: rgba(16,185,129,.12);
  }
  .nb-admin-uat-status.fail{
    color: rgba(153,27,27,.95);
    border-color: rgba(239,68,68,.35);
    background: rgba(239,68,68,.12);
  }
  .nb-admin-uat-status.pending{
    color: rgba(146,64,14,.95);
    border-color: rgba(245,158,11,.35);
    background: rgba(245,158,11,.15);
  }
  .nb-admin-uat-empty{
    padding:14px;
    text-align:center;
    color: var(--admin-muted);
    font-size:12px;
  }
  @media (max-width: 900px){
    .nb-admin-uat-row{
      grid-template-columns: 1fr;
      gap:4px;
    }
    .nb-admin-uat-row.header{ display:none; }
  }

  .nb-admin-field{
    height:34px;
    border-radius:12px;
    border:1px solid var(--admin-border);
    background: var(--nb-card);
    padding:0 10px;
    font-size:12px;
    color: var(--admin-ink);
  }

  .nb-admin-chart{ width:100%; height:160px; }

  .nb-admin-legend{
    margin-top:8px;
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    align-items:center;
    font-size:12px;
    color: var(--admin-muted);
  }

  .nb-admin-grid-2{
    margin-top:16px;
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap:12px;
  }

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

  .nb-admin-cover{
    width:42px;
    height:54px;
    border-radius:12px;
    overflow:hidden;
    background: rgba(148,163,184,.2);
    display:flex;
    align-items:center;
    justify-content:center;
    flex: 0 0 auto;
    color: var(--admin-muted);
    font-size:12px;
    font-weight:700;
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
  }

  .nb-admin-empty{
    font-size:12px;
    color: var(--admin-muted);
    padding:10px 12px;
    border-radius:12px;
    border:1px dashed var(--admin-border);
    background: rgba(148,163,184,.08);
  }
  html.dark .nb-admin-empty{ background: rgba(15,23,42,.5); }

  @media (max-width: 720px){
    .nb-admin-hero{ padding:18px; }
    .nb-admin-title{ font-size:20px; }
    .nb-admin-actions{ width:100%; }
    .nb-admin-btn{ width:100%; justify-content:center; }
  }
</style>

<div class="nb-admin-wrap">
  <section class="nb-admin-hero">
    <div class="nb-admin-hero-inner">
      <div class="nb-admin-hero-left">
        <div class="nb-admin-badge">
          <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-clipboard"></use></svg>
          Admin Console
        </div>
        <h1 class="nb-admin-title">Dashboard Admin</h1>
        <p class="nb-admin-sub">Kelola sistem NOTOBUKU, pantau interaksi, dan cek tren aktivitas dalam satu tampilan.</p>

        <div class="nb-admin-actions">
          <a class="nb-admin-btn primary" href="{{ route('app') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-home"></use></svg>
            Buka Portal Saya
          </a>
          <button type="button" class="nb-admin-btn" @click="openSearch()">
            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-search"></use></svg>
            Ctrl+K Cari Cepat
          </button>
          <form method="POST" action="{{ route('keluar') }}" class="m-0">
            @csrf
            <button class="nb-admin-btn ghost" type="submit">
              <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-logout"></use></svg>
              Keluar
            </button>
          </form>
        </div>
      </div>

      <div class="nb-admin-hero-right">
        <div class="nb-admin-usercard">
          <div class="nb-admin-avatar">{{ $initials }}</div>
          <div>
            <div class="nb-admin-user-name">{{ $userName }}</div>
            <div class="nb-admin-user-mail">{{ $userEmail }}</div>
            <div class="nb-admin-role">{{ strtoupper((string) $userRole) }}</div>
          </div>
        </div>
        <div class="nb-admin-mini">
          <div class="nb-admin-mini-label">Status Sistem</div>
          <div class="nb-admin-mini-value">Stabil</div>
          <div class="nb-admin-mini-sub">Semua layanan berjalan normal</div>
        </div>
      </div>
    </div>
  </section>

  @if(session('error'))
    <div class="nb-admin-alert">
      {{ session('error') }}
    </div>
  @endif

  <div class="nb-admin-kpis">
    <div class="nb-admin-card">
      <div class="nb-admin-kpi">
        <div class="nb-admin-kpi-ico">
          <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-chart"></use></svg>
        </div>
        <div>
          <div class="nb-admin-kpi-label">Klik Judul ({{ $range }} hari)</div>
          <div class="nb-admin-kpi-value">{{ number_format((int)($totals->clicks ?? 0)) }}</div>
          <div class="nb-admin-kpi-sub">Total interaksi klik di katalog</div>
        </div>
      </div>
    </div>

    <div class="nb-admin-card">
      <div class="nb-admin-kpi">
        <div class="nb-admin-kpi-ico green">
          <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-rotate"></use></svg>
        </div>
        <div>
          <div class="nb-admin-kpi-label">Pinjam ({{ $range }} hari)</div>
          <div class="nb-admin-kpi-value">{{ number_format((int)($totals->borrows ?? 0)) }}</div>
          <div class="nb-admin-kpi-sub">Total transaksi pinjam tercatat</div>
        </div>
      </div>
    </div>

    <div class="nb-admin-card">
      <div class="nb-admin-kpi">
        <div class="nb-admin-kpi-ico orange">
          <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#nb-icon-clock"></use></svg>
        </div>
        <div>
          <div class="nb-admin-kpi-label">Decay Ranking</div>
          <div class="nb-admin-kpi-value"><span class="nb-admin-status">Aktif</span></div>
          <div class="nb-admin-kpi-sub">Metrik lama turun bobot otomatis</div>
        </div>
      </div>
    </div>
  </div>

  <div class="nb-admin-grid-3">
    <section class="nb-admin-panel">
      <div class="nb-admin-panel-head">
        <div>
          <div class="nb-admin-panel-title">Aksi Cepat</div>
          <div class="nb-admin-panel-sub">Akses tugas harian yang sering dipakai</div>
        </div>
      </div>
      @if($quickActions->isNotEmpty())
        <div class="nb-admin-quick-grid">
          @foreach($quickActions as $action)
            <a class="nb-admin-quick-card" href="{{ route($action['route']) }}">
              <span class="nb-admin-quick-ico">
                <svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $action['icon'] }}"></use></svg>
              </span>
              <div class="min-w-0">
                <div class="nb-admin-quick-title">{{ $action['label'] }}</div>
                <div class="nb-admin-quick-sub">{{ $action['desc'] }}</div>
              </div>
              <span class="nb-admin-quick-go" aria-hidden="true">&gt;</span>
            </a>
          @endforeach
        </div>
      @else
        <div class="nb-admin-empty">Aksi cepat belum tersedia.</div>
      @endif
    </section>

    <section class="nb-admin-panel">
      <div class="nb-admin-panel-head">
        <div>
          <div class="nb-admin-panel-title">Insight Cepat</div>
          <div class="nb-admin-panel-sub">Ringkasan kinerja utama hari ini</div>
        </div>
      </div>
      <div class="nb-admin-insights">
        <div class="nb-admin-insight">
          <div class="nb-admin-insight-label">Konversi Klik ke Pinjam</div>
          <div class="nb-admin-insight-value">{{ $conversionRate }}%</div>
          <div class="nb-admin-insight-sub">{{ number_format($borrowsTotal) }} pinjam dari {{ number_format($clicksTotal) }} klik</div>
        </div>
        <div class="nb-admin-insight">
          <div class="nb-admin-insight-label">Aktivitas Terbaru</div>
          <div class="nb-admin-insight-value">{{ $recentClicksCount + $recentBorrowsCount }}</div>
          <div class="nb-admin-insight-sub">{{ $recentClicksCount }} klik, {{ $recentBorrowsCount }} pinjam</div>
        </div>
        <div class="nb-admin-insight">
          <div class="nb-admin-insight-label">Top Judul</div>
          <div class="nb-admin-insight-value">{{ $topClickedCount }}</div>
          <div class="nb-admin-insight-sub">{{ $branchCount }} cabang aktif</div>
        </div>
        <div class="nb-admin-insight">
          <div class="nb-admin-insight-label">Interop Health (OAI + SRU)</div>
          <div class="nb-admin-insight-value">
            <span
              id="interop-health-pill"
              class="nb-admin-health-pill {{ $interopHealthClass }}"
              data-metrics-url="{{ route('interop.metrics') }}"
            >{{ $interopHealth }}</span>
          </div>
          <div id="interop-health-sub" class="nb-admin-insight-sub">p95 {{ $interopP95 }} ms, invalid {{ number_format($interopInvalid) }}, limited {{ number_format($interopLimited) }}</div>
          <div class="nb-admin-health-breakdown">
            <div id="interop-health-oai" class="row"><b>OAI</b> p95 {{ $interopOaiP95 }} ms, limited {{ number_format($interopOaiLimited) }}</div>
            <div id="interop-health-sru" class="row"><b>SRU</b> p95 {{ $interopSruP95 }} ms, limited {{ number_format($interopSruLimited) }}</div>
          </div>
          <div class="nb-admin-health-mini">
            <div id="interop-health-p95-min" class="row"><b>24h Min</b> {{ $interopP95Min24h }} ms</div>
            <div id="interop-health-p95-max" class="row"><b>24h Max</b> {{ $interopP95Max24h }} ms</div>
            <div id="interop-health-p95-now" class="row"><b>Now</b> {{ $interopP95Latest24h }} ms</div>
          </div>
          <div
            id="interop-health-alert"
            class="nb-admin-health-alert {{ $interopCriticalActive ? 'critical' : 'ok' }}"
            data-critical-threshold="{{ $interopCriticalThreshold }}"
          >
            @if($interopCriticalActive)
              Alert: status Kritis {{ $interopCriticalStreak }} menit berturut-turut.
            @else
              Alert rule aktif: Kritis berturut-turut {{ $interopCriticalThreshold }} menit.
            @endif
          </div>
          <div class="nb-admin-health-actions">
            <span id="interop-health-sync" class="nb-admin-health-sync live">Live</span>
            <button id="interop-health-refresh" type="button" class="nb-admin-health-refresh">Refresh now</button>
            <a class="nb-admin-health-export" href="{{ route('interop.metrics.export.csv', ['days' => 30]) }}">Export CSV</a>
          </div>
          <div id="interop-health-next" class="nb-admin-insight-sub">Refresh berikutnya dalam 30 detik</div>
          <div id="interop-health-updated" class="nb-admin-insight-sub">Last updated baru saja</div>
        </div>
        <div class="nb-admin-insight">
          <div class="nb-admin-insight-label">OPAC Performance</div>
          <div class="nb-admin-insight-value">
            <span
              id="opac-health-pill"
              class="nb-admin-health-pill {{ $opacP95 >= 1500 ? ($opacP95 >= 3000 ? 'critical' : 'warning') : 'good' }}"
              data-metrics-url="{{ route('opac.metrics') }}"
            >p95 {{ $opacP95 }} ms</span>
            <span id="opac-slo-pill" class="nb-admin-health-pill {{ $opacSloClass }}">SLO {{ $opacSloLabel }}</span>
          </div>
          <div id="opac-health-sub" class="nb-admin-insight-sub">
            p50 {{ $opacP50 }} ms, error {{ number_format($opacErrorRate, 2) }}%, req {{ number_format($opacRequests) }}
          </div>
          <div id="opac-slo-sub" class="nb-admin-insight-sub">
            target {{ number_format($opacSloTarget, 2) }}% | burn 5m {{ number_format($opacBurn5, 2) }}x | burn 60m {{ number_format($opacBurn60, 2) }}x
          </div>
          <div
            id="opac-health-alert"
            class="nb-admin-health-alert {{ $opacAlertClass }}"
            data-warning-threshold="{{ (float) ($opacSlo['warning_threshold'] ?? 2) }}"
            data-critical-threshold="{{ (float) ($opacSlo['critical_threshold'] ?? 5) }}"
          >
            @if($opacSloState === 'critical')
              Alert: burn-rate OPAC melewati ambang kritis.
            @elseif($opacSloState === 'warning')
              Peringatan: burn-rate OPAC melewati ambang warning.
            @else
              Burn-rate OPAC dalam batas aman.
            @endif
          </div>
          <div class="nb-admin-opac-spark" aria-label="Trend p95 OPAC 24 jam">
            <svg viewBox="0 0 320 52" preserveAspectRatio="none" role="img">
              <path id="opac-spark-area" class="area" d=""></path>
              <path id="opac-spark-line" class="line" d=""></path>
            </svg>
            <div class="nb-admin-opac-spark-meta">
              <span id="opac-spark-min">24h min {{ $opacP95Min24h }} ms</span>
              <span id="opac-spark-max">24h max {{ $opacP95Max24h }} ms</span>
              <span id="opac-spark-now">now {{ $opacP95Now24h }} ms</span>
            </div>
          </div>
          <div class="nb-admin-health-actions">
            <span id="opac-health-sync" class="nb-admin-health-sync live">Live</span>
            <button id="opac-health-refresh" type="button" class="nb-admin-health-refresh">Refresh now</button>
          </div>
          <div id="opac-health-next" class="nb-admin-insight-sub">Refresh berikutnya dalam 30 detik</div>
          <div id="opac-health-trace" class="nb-admin-insight-sub">Trace ID: -</div>
          <div id="opac-health-updated" class="nb-admin-insight-sub">Last updated baru saja</div>
          <div class="nb-admin-opac-analytics">
            <div class="nb-admin-auto-kpi">
              <div class="nb-admin-auto-kpi-item">
                <div class="label">Total Search</div>
                <div class="value" id="opac-ana-total">{{ number_format($opacSearchTotal, 0, ',', '.') }}</div>
              </div>
              <div class="nb-admin-auto-kpi-item">
                <div class="label">Success Rate</div>
                <div class="value" id="opac-ana-success">{{ number_format($opacSearchSuccessRate, 2) }}%</div>
              </div>
              <div class="nb-admin-auto-kpi-item">
                <div class="label">Zero Distinct</div>
                <div class="value" id="opac-ana-zero">{{ number_format($opacZeroDistinct, 0, ',', '.') }}</div>
              </div>
            </div>
            <div class="nb-admin-auto-split">
              <div>
                <div class="nb-admin-auto-block-title">Top Keyword</div>
                <div class="nb-admin-auto-list" id="opac-ana-top">
                  @forelse($opacTopKeywords as $row)
                    <div class="nb-admin-auto-row">
                      <span>{{ $row['query'] ?? '-' }}</span>
                      <b>{{ number_format((int) ($row['search_count'] ?? 0), 0, ',', '.') }}</b>
                    </div>
                  @empty
                    <div class="nb-admin-auto-empty">Belum ada data keyword.</div>
                  @endforelse
                </div>
              </div>
              <div>
                <div class="nb-admin-auto-block-title">Zero-result Top</div>
                <div class="nb-admin-auto-list" id="opac-ana-zero-list">
                  @forelse($opacTopZero as $row)
                    <div class="nb-admin-auto-row">
                      <a href="{{ route('admin.search_synonyms', ['term' => (string) ($row['query'] ?? '')]) }}">{{ $row['query'] ?? '-' }}</a>
                      <b>{{ number_format((int) ($row['search_count'] ?? 0), 0, ',', '.') }}</b>
                    </div>
                  @empty
                    <div class="nb-admin-auto-empty">Belum ada zero-result dominan.</div>
                  @endforelse
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="nb-admin-panel">
      <div class="nb-admin-panel-head">
        <div>
          <div class="nb-admin-panel-title">Autocomplete UX</div>
          <div class="nb-admin-panel-sub">Pemakaian autocomplete {{ $range }} hari terakhir</div>
        </div>
      </div>
      @php
        $autoUsage = $autoUsage ?? collect();
        $autoTotal = (int) ($autoTotal ?? 0);
        $autoDaily = $autoDaily ?? collect();
        $autoDailyMax = (int) ($autoDailyMax ?? 0);
        $autoPerRecord = $autoPerRecord ?? null;
        $autoRecordCount = (int) ($autoRecordCount ?? 0);
        $autoFieldCoverage = (int) ($autoFieldCoverage ?? 0);
        $autoPeakDay = $autoPeakDay ?? null;
        $autoTopUsers = $autoTopUsers ?? collect();
        $autoTopPaths = $autoTopPaths ?? collect();
        $autoSuggestions = $autoSuggestions ?? [];
        $autoLabels = [
          'authors' => 'Pengarang',
          'subjects' => 'Subjek',
          'publisher' => 'Penerbit',
          'title' => 'Judul',
          'isbn' => 'ISBN',
        ];
        $peakLabel = $autoPeakDay ? \Illuminate\Support\Carbon::parse($autoPeakDay->day)->format('d M') : '-';
        $peakTotal = $autoPeakDay ? (int) ($autoPeakDay->total ?? 0) : 0;
      @endphp
      <div class="nb-admin-auto-grid">
        <div class="nb-admin-auto-card">
          <div class="nb-admin-auto-title">Pemakaian Autocomplete</div>
          <div class="nb-admin-auto-total">{{ number_format($autoTotal) }}</div>
          <div class="nb-admin-auto-sub">Total pemilihan di form katalog</div>
          <div class="nb-admin-auto-list">
            @forelse($autoUsage as $row)
              <div class="nb-admin-auto-row">
                <span>{{ $autoLabels[$row->field] ?? ucfirst((string) $row->field) }}</span>
                <b>
                  {{ number_format((int) $row->total, 0, ',', '.') }}
                  @if($autoTotal > 0)
                    <span style="font-weight:600;color:var(--admin-muted);">({{ round(((int) $row->total / $autoTotal) * 100) }}%)</span>
                  @endif
                </b>
              </div>
            @empty
              <div class="nb-admin-auto-empty">Belum ada data penggunaan.</div>
            @endforelse
          </div>
        </div>
        <div class="nb-admin-auto-card">
          <div class="nb-admin-auto-title">Trend Harian</div>
          <div class="nb-admin-auto-sub">Jumlah pemakaian per hari</div>
          <div class="nb-admin-auto-chart">
            @forelse($autoDaily as $day)
              @php
                $label = \Illuminate\Support\Carbon::parse($day->day)->format('d M');
                $val = (int) $day->total;
                $pct = $autoDailyMax > 0 ? (int) round(($val / $autoDailyMax) * 100) : 0;
              @endphp
              <div class="nb-admin-auto-bar" title="{{ $label }}: {{ $val }}">
                <span style="--pct: {{ $pct / 100 }};"></span>
                <em>{{ $label }}</em>
              </div>
            @empty
              <div class="nb-admin-auto-empty">Belum ada data harian.</div>
            @endforelse
          </div>
          <div class="nb-admin-auto-kpi">
            <div class="nb-admin-auto-kpi-item">
              <div class="label">Per Record</div>
              <div class="value">{{ $autoPerRecord !== null ? $autoPerRecord : '-' }}</div>
            </div>
            <div class="nb-admin-auto-kpi-item">
              <div class="label">Record Baru</div>
              <div class="value">{{ number_format($autoRecordCount, 0, ',', '.') }}</div>
            </div>
            <div class="nb-admin-auto-kpi-item">
              <div class="label">Coverage Field</div>
              <div class="value">{{ $autoFieldCoverage }}%</div>
            </div>
            <div class="nb-admin-auto-kpi-item">
              <div class="label">Puncak Harian</div>
              <div class="value">{{ $peakLabel }} ({{ $peakTotal }})</div>
            </div>
            <div class="nb-admin-auto-kpi-item">
              <div class="label">Konsentrasi</div>
              <div class="value">{{ $autoTopUserShare !== null ? $autoTopUserShare . '%' : '-' }}</div>
            </div>
          </div>
        </div>
        <div class="nb-admin-auto-card">
          <div class="nb-admin-auto-title">Saran Optimasi</div>
          <div class="nb-admin-auto-sub">Ringkas dan dapat ditindak</div>
          <div class="nb-admin-auto-list">
            @forelse($autoSuggestions as $tip)
              <div class="nb-admin-auto-tip">&bull; {{ $tip }}</div>
            @empty
              <div class="nb-admin-auto-empty">Pemakaian stabil, tidak ada saran khusus.</div>
            @endforelse
          </div>
        </div>
        <div class="nb-admin-auto-card">
          <div class="nb-admin-auto-title">Pengguna & Halaman</div>
          <div class="nb-admin-auto-sub">Siapa & di mana autocomplete paling sering dipakai</div>
          <div class="nb-admin-auto-split">
            <div>
              <div class="nb-admin-auto-block-title">Pengguna</div>
              <div class="nb-admin-auto-list">
                @forelse($autoTopUsers as $row)
                  <div class="nb-admin-auto-row">
                    <span>{{ $row->name ?? '(Anon)' }}</span>
                    <b>{{ number_format((int) $row->total, 0, ',', '.') }}</b>
                  </div>
                @empty
                  <div class="nb-admin-auto-empty">Belum ada data pengguna.</div>
                @endforelse
              </div>
            </div>
            <div>
              <div class="nb-admin-auto-block-title">Halaman</div>
              <div class="nb-admin-auto-list">
                @forelse($autoTopPaths as $row)
                  <div class="nb-admin-auto-row">
                    <span>{{ $row->path }}</span>
                    <b>{{ number_format((int) $row->total, 0, ',', '.') }}</b>
                  </div>
                @empty
                  <div class="nb-admin-auto-empty">Belum ada data halaman.</div>
                @endforelse
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="nb-admin-panel nb-admin-cta">
      <div class="nb-admin-cta-inner">
        <div class="nb-admin-panel-title">Butuh laporan detail?</div>
        <div class="nb-admin-cta-sub">Gabungkan filter cabang, periode, dan event untuk melihat tren mendalam.</div>
        <div class="nb-admin-cta-actions">
          @if($ctaPrimaryUrl !== '#')
            <a class="nb-admin-btn primary" href="{{ $ctaPrimaryUrl }}">{{ $ctaPrimaryLabel }}</a>
          @else
            <span class="nb-admin-btn primary is-disabled">{{ $ctaPrimaryLabel }}</span>
          @endif
          @if($ctaSecondaryUrl !== '#')
            <a class="nb-admin-btn ghost" href="{{ $ctaSecondaryUrl }}">Tambah Koleksi</a>
          @else
            <span class="nb-admin-btn ghost is-disabled">Tambah Koleksi</span>
          @endif
        </div>
      </div>
    </section>
  </div>

  <section class="nb-admin-panel">
    <div class="nb-admin-panel-head">
      <div>
        <div class="nb-admin-panel-title">Audit UAT Sign-off</div>
        <div class="nb-admin-panel-sub">Riwayat verifikasi operasional terbaru untuk audit cepat.</div>
      </div>
      <div class="nb-admin-uat-head">
        <span class="nb-admin-uat-pill pass">Pass {{ number_format($uatPass, 0, ',', '.') }}</span>
        <span class="nb-admin-uat-pill fail">Fail {{ number_format($uatFail, 0, ',', '.') }}</span>
        <span class="nb-admin-uat-pill pending">Pending {{ number_format($uatPending, 0, ',', '.') }}</span>
        <a class="nb-admin-btn ghost" href="{{ route('docs.uat-checklist') }}">Checklist UAT</a>
      </div>
    </div>
    <div class="nb-admin-uat-table">
      <div class="nb-admin-uat-row header">
        <div>Tanggal</div>
        <div>Status</div>
        <div>Operator</div>
        <div>Signed At</div>
        <div>Catatan</div>
      </div>
      @forelse($uatRecent as $row)
        @php
          $status = strtolower((string) ($row->status ?? 'pending'));
          if (!in_array($status, ['pass', 'fail', 'pending'], true)) {
            $status = 'pending';
          }
          $statusLabel = $status === 'pass' ? 'Lulus' : ($status === 'fail' ? 'Gagal' : 'Pending');
          $signedAt = $row->signed_at ? \Illuminate\Support\Carbon::parse($row->signed_at)->format('d M Y H:i') : '-';
          $notes = trim((string) ($row->notes ?? ''));
          $notes = $notes !== '' ? $notes : '-';
          $operator = trim((string) ($row->operator_name ?? ''));
          $operator = $operator !== '' ? $operator : '-';
        @endphp
        <div class="nb-admin-uat-row">
          <div>{{ \Illuminate\Support\Carbon::parse($row->check_date)->format('d M Y') }}</div>
          <div><span class="nb-admin-uat-status {{ $status }}">{{ $statusLabel }}</span></div>
          <div>{{ $operator }}</div>
          <div>{{ $signedAt }}</div>
          <div title="{{ $notes }}">{{ \Illuminate\Support\Str::limit($notes, 80) }}</div>
        </div>
      @empty
        <div class="nb-admin-uat-empty">Belum ada riwayat sign-off UAT untuk institusi ini.</div>
      @endforelse
    </div>
  </section>

  <section class="nb-admin-panel">
    <div class="nb-admin-panel-head">
      <div>
        <div class="nb-admin-panel-title">Tren Interaksi</div>
        <div class="nb-admin-panel-sub">Klik dan pinjam dalam {{ $range }} hari terakhir</div>
      </div>
      <form method="GET" class="flex flex-wrap gap-2 items-center">
        <select name="branch_id" class="nb-admin-field">
          <option value="">Semua Cabang</option>
          @foreach($branches as $b)
            <option value="{{ $b->id }}" {{ (string)$branchId === (string)$b->id ? 'selected' : '' }}>{{ $b->name }}</option>
          @endforeach
        </select>
        <select name="range" class="nb-admin-field">
          <option value="7" {{ (int)$range === 7 ? 'selected' : '' }}>7 hari</option>
          <option value="30" {{ (int)$range === 30 ? 'selected' : '' }}>30 hari</option>
        </select>
        <select name="event" class="nb-admin-field">
          <option value="all" {{ $eventType === 'all' ? 'selected' : '' }}>Semua Event</option>
          <option value="click" {{ $eventType === 'click' ? 'selected' : '' }}>Klik</option>
          <option value="borrow" {{ $eventType === 'borrow' ? 'selected' : '' }}>Pinjam</option>
        </select>
        <button class="nb-admin-btn" type="submit">Terapkan</button>
      </form>
    </div>

    @php
      $clickSeries = $clickSeries ?? [];
      $borrowSeries = $borrowSeries ?? [];
      $labels = array_keys($clickSeries);
      $count = max(count($labels), 1);
      $width = 520;
      $height = 140;
      $pad = 16;
      $maxVal = max(array_merge([1], array_values($clickSeries), array_values($borrowSeries)));
      $pointsFor = function ($series) use ($labels, $count, $width, $height, $pad, $maxVal) {
          $pts = [];
          $span = max(1, $count - 1);
          foreach ($labels as $i => $day) {
              $x = $pad + (($width - ($pad * 2)) * ($i / $span));
              $val = (int) ($series[$day] ?? 0);
              $y = $height - $pad - (($height - ($pad * 2)) * ($val / $maxVal));
              $pts[] = round($x, 1) . ',' . round($y, 1);
          }
          return implode(' ', $pts);
      };
      $clickPoints = $pointsFor($clickSeries);
      $borrowPoints = $pointsFor($borrowSeries);
    @endphp

    <div class="mt-4">
      <svg viewBox="0 0 520 140" class="nb-admin-chart" aria-label="Grafik tren interaksi">
        <rect x="0" y="0" width="520" height="140" fill="transparent" />
        <line x1="16" y1="124" x2="504" y2="124" stroke="rgba(15,23,42,.08)" stroke-width="1" />
        <line x1="16" y1="70" x2="504" y2="70" stroke="rgba(15,23,42,.06)" stroke-width="1" />
        <line x1="16" y1="16" x2="504" y2="16" stroke="rgba(15,23,42,.06)" stroke-width="1" />

        @if($eventType === 'all' || $eventType === 'click')
          <polyline points="{{ $clickPoints }}" fill="none" stroke="rgba(30,136,229,.95)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        @endif
        @if($eventType === 'all' || $eventType === 'borrow')
          <polyline points="{{ $borrowPoints }}" fill="none" stroke="rgba(39,174,96,.95)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        @endif
      </svg>

      <div class="nb-admin-legend">
        @if($eventType === 'all' || $eventType === 'click')
          <span class="inline-flex items-center gap-2"><span style="width:12px;height:2px;background:rgba(30,136,229,.95);display:inline-block;"></span>Klik</span>
        @endif
        @if($eventType === 'all' || $eventType === 'borrow')
          <span class="inline-flex items-center gap-2"><span style="width:12px;height:2px;background:rgba(39,174,96,.95);display:inline-block;"></span>Pinjam</span>
        @endif
        <span>{{ array_key_first($clickSeries) }} - {{ array_key_last($clickSeries) }}</span>
      </div>
    </div>
  </section>

  <div class="nb-admin-grid-2">
    <section class="nb-admin-panel">
      <div class="nb-admin-panel-head">
        <div>
          <div class="nb-admin-panel-title">Top Klik</div>
          <div class="nb-admin-panel-sub">Judul paling sering diklik</div>
        </div>
      </div>
      <div class="nb-admin-list mt-3">
        @forelse($topClicked as $row)
          <div class="nb-admin-item">
            <div class="nb-admin-cover">
              @if(!empty($row->cover_path))
                <img src="{{ asset('storage/'.$row->cover_path) }}" alt="Cover" class="h-full w-full object-cover">
              @else
                NB
              @endif
            </div>
            <div class="min-w-0">
              <div class="nb-admin-item-title truncate">{{ $row->title }}</div>
              <div class="nb-admin-item-sub">Klik: {{ (int)$row->click_count }}</div>
            </div>
            <span class="nb-admin-chip">#{{ $loop->iteration }}</span>
          </div>
        @empty
          <div class="nb-admin-empty">Belum ada data klik.</div>
        @endforelse
      </div>
    </section>

    <section class="nb-admin-panel">
      <div class="nb-admin-panel-head">
        <div>
          <div class="nb-admin-panel-title">Top Pinjam</div>
          <div class="nb-admin-panel-sub">Judul paling sering dipinjam</div>
        </div>
      </div>
      <div class="nb-admin-list mt-3">
        @forelse($topBorrowed as $row)
          <div class="nb-admin-item">
            <div class="nb-admin-cover">
              @if(!empty($row->cover_path))
                <img src="{{ asset('storage/'.$row->cover_path) }}" alt="Cover" class="h-full w-full object-cover">
              @else
                NB
              @endif
            </div>
            <div class="min-w-0">
              <div class="nb-admin-item-title truncate">{{ $row->title }}</div>
              <div class="nb-admin-item-sub">Pinjam: {{ (int)$row->borrow_count }}</div>
            </div>
            <span class="nb-admin-chip">#{{ $loop->iteration }}</span>
          </div>
        @empty
          <div class="nb-admin-empty">Belum ada data pinjam.</div>
        @endforelse
      </div>
    </section>
  </div>

  <div class="nb-admin-grid-2">
    <section class="nb-admin-panel">
      <div class="nb-admin-panel-head">
        <div>
          <div class="nb-admin-panel-title">Klik Terbaru</div>
          <div class="nb-admin-panel-sub">Aktivitas terbaru di katalog</div>
        </div>
      </div>
      <div class="nb-admin-list mt-3">
        @forelse($recentClicks as $row)
          <div class="nb-admin-item">
            <div class="min-w-0">
              <div class="nb-admin-item-title truncate">{{ $row->title }}</div>
              <div class="nb-admin-item-sub">Terakhir: {{ \Carbon\Carbon::parse($row->last_clicked_at)->diffForHumans() }}</div>
            </div>
          </div>
        @empty
          <div class="nb-admin-empty">Belum ada aktivitas klik.</div>
        @endforelse
      </div>
    </section>

    <section class="nb-admin-panel">
      <div class="nb-admin-panel-head">
        <div>
          <div class="nb-admin-panel-title">Pinjam Terbaru</div>
          <div class="nb-admin-panel-sub">Aktivitas peminjaman terbaru</div>
        </div>
      </div>
      <div class="nb-admin-list mt-3">
        @forelse($recentBorrows as $row)
          <div class="nb-admin-item">
            <div class="min-w-0">
              <div class="nb-admin-item-title truncate">{{ $row->title }}</div>
              <div class="nb-admin-item-sub">Terakhir: {{ \Carbon\Carbon::parse($row->last_borrowed_at)->diffForHumans() }}</div>
            </div>
          </div>
        @empty
          <div class="nb-admin-empty">Belum ada aktivitas pinjam.</div>
        @endforelse
      </div>
    </section>
  </div>
</div>

<script>
  (function () {
    const pill = document.getElementById('interop-health-pill');
    const sub = document.getElementById('interop-health-sub');
    const updated = document.getElementById('interop-health-updated');
    const oaiEl = document.getElementById('interop-health-oai');
    const sruEl = document.getElementById('interop-health-sru');
    const refreshBtn = document.getElementById('interop-health-refresh');
    const syncEl = document.getElementById('interop-health-sync');
    const nextEl = document.getElementById('interop-health-next');
    const minEl = document.getElementById('interop-health-p95-min');
    const maxEl = document.getElementById('interop-health-p95-max');
    const nowEl = document.getElementById('interop-health-p95-now');
    const alertEl = document.getElementById('interop-health-alert');
    if (!pill || !sub || !updated || !oaiEl || !sruEl || !refreshBtn || !syncEl || !nextEl || !minEl || !maxEl || !nowEl || !alertEl) return;

    const metricsUrl = pill.dataset.metricsUrl || '';
    if (!metricsUrl) return;
    let lastUpdatedAt = Date.now();

    function updateBadge(health, p95, invalid, limited, oaiP95, sruP95, oaiLimited, sruLimited) {
      pill.textContent = health.label;
      pill.classList.remove('good', 'warning', 'critical');
      pill.classList.add(health.klass);
      sub.textContent = `p95 ${p95} ms, invalid ${invalid.toLocaleString('id-ID')}, limited ${limited.toLocaleString('id-ID')}`;
      oaiEl.innerHTML = `<b>OAI</b> p95 ${oaiP95} ms, limited ${oaiLimited.toLocaleString('id-ID')}`;
      sruEl.innerHTML = `<b>SRU</b> p95 ${sruP95} ms, limited ${sruLimited.toLocaleString('id-ID')}`;
      lastUpdatedAt = Date.now();
      updated.textContent = 'Last updated baru saja';
    }

    function tickLastUpdated() {
      const diffSec = Math.max(0, Math.floor((Date.now() - lastUpdatedAt) / 1000));
      if (diffSec <= 1) {
        updated.textContent = 'Last updated baru saja';
        return;
      }
      if (diffSec < 60) {
        updated.textContent = `Last updated ${diffSec} detik lalu`;
        return;
      }
      const diffMin = Math.floor(diffSec / 60);
      updated.textContent = `Last updated ${diffMin} menit lalu`;
    }

    async function refreshInteropHealth() {
      refreshBtn.disabled = true;
      refreshBtn.textContent = 'Refreshing...';
      syncEl.textContent = 'Refreshing';
      syncEl.classList.remove('live', 'offline');
      syncEl.classList.add('refreshing');
      try {
        const resp = await fetch(metricsUrl, {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
        });
        if (!resp.ok) {
          syncEl.textContent = 'Offline';
          syncEl.classList.remove('refreshing', 'live');
          syncEl.classList.add('offline');
          nextDelayMs = Math.min(MAX_BACKOFF_MS, Math.max(REFRESH_INTERVAL_MS, nextDelayMs * 2));
          restartRefreshTimer();
          return;
        }
        const data = await resp.json();
        if (!data || data.ok !== true || !data.metrics) {
          syncEl.textContent = 'Offline';
          syncEl.classList.remove('refreshing', 'live');
          syncEl.classList.add('offline');
          nextDelayMs = Math.min(MAX_BACKOFF_MS, Math.max(REFRESH_INTERVAL_MS, nextDelayMs * 2));
          restartRefreshTimer();
          return;
        }

        const oai = (data.metrics.latency && data.metrics.latency.oai) || {};
        const sru = (data.metrics.latency && data.metrics.latency.sru) || {};
        const counters = data.metrics.counters || {};
        const oaiP95 = Number(oai.p95_ms || 0);
        const sruP95 = Number(sru.p95_ms || 0);
        const p95 = Math.max(oaiP95, sruP95);
        const invalid = Number(counters.oai_invalid_token || 0) + Number(counters.sru_invalid_token || 0);
        const oaiLimited = Number(counters.oai_rate_limited || 0);
        const sruLimited = Number(counters.sru_rate_limited || 0);
        const limited = oaiLimited + sruLimited;
        const health = (data.metrics && data.metrics.health) || {};
        const healthLabel = String(health.label || 'Sehat');
        const healthClass = String(health.class || 'good');
        const history24h = (data.metrics && data.metrics.history && data.metrics.history.last_24h) || [];
        p95History = Array.isArray(history24h)
          ? history24h.map((r) => Number((r && r.p95_ms) || 0)).filter((v) => !Number.isNaN(v))
          : [];
        refreshP95Mini();
        refreshAlert((data.metrics && data.metrics.alerts) || {});

        updateBadge({ label: healthLabel, klass: healthClass }, p95, invalid, limited, oaiP95, sruP95, oaiLimited, sruLimited);
        syncEl.textContent = 'Live';
        syncEl.classList.remove('refreshing', 'offline');
        syncEl.classList.add('live');
        nextDelayMs = REFRESH_INTERVAL_MS;
        restartRefreshTimer();
      } catch (e) {
        // No-op: keep last rendered value when refresh fails.
        syncEl.textContent = 'Offline';
        syncEl.classList.remove('refreshing', 'live');
        syncEl.classList.add('offline');
        nextDelayMs = Math.min(MAX_BACKOFF_MS, Math.max(REFRESH_INTERVAL_MS, nextDelayMs * 2));
        restartRefreshTimer();
      } finally {
        refreshBtn.disabled = false;
        refreshBtn.textContent = 'Refresh now';
      }
    }

    refreshBtn.addEventListener('click', function () {
      refreshInteropHealth();
    });

    let refreshTimer = null;
    let nextDelayMs = 30000;
    const REFRESH_INTERVAL_MS = 30000;
    const MAX_BACKOFF_MS = 300000;
    let nextRefreshAt = Date.now() + nextDelayMs;
    let p95History = [];

    function startRefreshTimer() {
      if (refreshTimer) return;
      nextRefreshAt = Date.now() + nextDelayMs;
      refreshTimer = setInterval(function () {
        if (document.visibilityState === 'visible') {
          refreshInteropHealth();
          nextRefreshAt = Date.now() + nextDelayMs;
        }
      }, nextDelayMs);
    }

    function stopRefreshTimer() {
      if (!refreshTimer) return;
      clearInterval(refreshTimer);
      refreshTimer = null;
    }

    function restartRefreshTimer() {
      if (document.visibilityState !== 'visible') return;
      stopRefreshTimer();
      startRefreshTimer();
    }

    function refreshP95Mini() {
      if (!Array.isArray(p95History) || p95History.length === 0) return;
      const min = Math.min(...p95History);
      const max = Math.max(...p95History);
      const now = Number(p95History[p95History.length - 1] || 0);
      minEl.innerHTML = `<b>24h Min</b> ${min} ms`;
      maxEl.innerHTML = `<b>24h Max</b> ${max} ms`;
      nowEl.innerHTML = `<b>Now</b> ${now} ms`;
    }

    function refreshAlert(alerts) {
      const critical = (alerts && alerts.critical_streak) || {};
      const active = Boolean(critical.active);
      const streak = Number(critical.streak_minutes || 0);
      const threshold = Number(critical.threshold_minutes || Number(alertEl.dataset.criticalThreshold || 0));
      alertEl.classList.remove('ok', 'warn', 'critical');
      if (active) {
        alertEl.classList.add('critical');
        alertEl.textContent = `Alert: status Kritis ${streak} menit berturut-turut.`;
        return;
      }
      if (streak > 0) {
        alertEl.classList.add('warn');
        alertEl.textContent = `Peringatan: streak Kritis sempat ${streak} menit (threshold ${threshold}).`;
        return;
      }
      alertEl.classList.add('ok');
      alertEl.textContent = `Alert rule aktif: Kritis berturut-turut ${threshold} menit.`;
    }

    function tickNextRefresh() {
      if (document.visibilityState !== 'visible') {
        nextEl.textContent = 'Refresh dijeda (tab tidak aktif)';
        return;
      }
      if (!refreshTimer) {
        nextEl.textContent = 'Refresh dijeda';
        return;
      }
      const sec = Math.max(1, Math.ceil((nextRefreshAt - Date.now()) / 1000));
      nextEl.textContent = `Refresh berikutnya dalam ${sec} detik`;
    }

    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') {
        startRefreshTimer();
        refreshInteropHealth();
      } else {
        stopRefreshTimer();
      }
    });

    setInterval(tickLastUpdated, 1000);
    setInterval(tickNextRefresh, 1000);
    if (document.visibilityState === 'visible') {
      startRefreshTimer();
      tickNextRefresh();
    }
  })();
</script>
<script>
  (function () {
    const pill = document.getElementById('opac-health-pill');
    const sloPill = document.getElementById('opac-slo-pill');
    const sub = document.getElementById('opac-health-sub');
    const sloSub = document.getElementById('opac-slo-sub');
    const alertEl = document.getElementById('opac-health-alert');
    const updated = document.getElementById('opac-health-updated');
    const trace = document.getElementById('opac-health-trace');
    const sync = document.getElementById('opac-health-sync');
    const refreshBtn = document.getElementById('opac-health-refresh');
    const nextEl = document.getElementById('opac-health-next');
    const sparkLine = document.getElementById('opac-spark-line');
    const sparkArea = document.getElementById('opac-spark-area');
    const sparkMin = document.getElementById('opac-spark-min');
    const sparkMax = document.getElementById('opac-spark-max');
    const sparkNow = document.getElementById('opac-spark-now');
    const anaTotal = document.getElementById('opac-ana-total');
    const anaSuccess = document.getElementById('opac-ana-success');
    const anaZero = document.getElementById('opac-ana-zero');
    const anaTop = document.getElementById('opac-ana-top');
    const anaZeroList = document.getElementById('opac-ana-zero-list');
    if (!pill || !sub || !updated || !nextEl) return;
    const url = pill.dataset.metricsUrl || '';
    if (!url) return;

    let timer = null;
    let tick = null;
    let lastUpdatedAt = Date.now();
    let nextRefreshAt = Date.now() + 30000;
    let p95Series = @json($opacP95Series24h ?? []);

    function applyBadge(p95) {
      pill.classList.remove('good', 'warning', 'critical');
      if (p95 >= 3000) {
        pill.classList.add('critical');
      } else if (p95 >= 1500) {
        pill.classList.add('warning');
      } else {
        pill.classList.add('good');
      }
    }

    function applySloBadge(state) {
      if (!sloPill) return;
      sloPill.classList.remove('good', 'warning', 'critical');
      const normalized = String(state || 'ok').toLowerCase();
      const cls = normalized === 'critical' ? 'critical' : (normalized === 'warning' ? 'warning' : 'good');
      const label = normalized === 'critical' ? 'Kritis' : (normalized === 'warning' ? 'Waspada' : 'Sehat');
      sloPill.classList.add(cls);
      sloPill.textContent = `SLO ${label}`;
    }

    function renderSloAlert(state, burn5, burn60) {
      if (!alertEl) return;
      const normalized = String(state || 'ok').toLowerCase();
      const warn = Number(alertEl.dataset.warningThreshold || 2);
      const crit = Number(alertEl.dataset.criticalThreshold || 5);
      alertEl.classList.remove('ok', 'warn', 'critical');
      if (normalized === 'critical') {
        alertEl.classList.add('critical');
        alertEl.textContent = `Alert: burn-rate OPAC kritis (5m ${burn5.toFixed(2)}x, 60m ${burn60.toFixed(2)}x, threshold ${crit}x).`;
        return;
      }
      if (normalized === 'warning') {
        alertEl.classList.add('warn');
        alertEl.textContent = `Peringatan: burn-rate OPAC naik (5m ${burn5.toFixed(2)}x, 60m ${burn60.toFixed(2)}x, warning ${warn}x).`;
        return;
      }
      alertEl.classList.add('ok');
      alertEl.textContent = 'Burn-rate OPAC dalam batas aman.';
    }

    function renderUpdated() {
      const sec = Math.max(0, Math.floor((Date.now() - lastUpdatedAt) / 1000));
      updated.textContent = `Last updated ${sec} detik lalu`;
    }

    function renderNextRefresh() {
      if (document.visibilityState !== 'visible') {
        nextEl.textContent = 'Refresh dijeda (tab tidak aktif)';
        if (sync) sync.classList.remove('live');
        return;
      }
      if (!timer) {
        nextEl.textContent = 'Refresh dijeda';
        if (sync) sync.classList.remove('live');
        return;
      }
      if (sync) sync.classList.add('live');
      const sec = Math.max(1, Math.ceil((nextRefreshAt - Date.now()) / 1000));
      nextEl.textContent = `Refresh berikutnya dalam ${sec} detik`;
    }

    function renderSparkline(series) {
      if (!sparkLine || !sparkArea) return;
      if (!Array.isArray(series) || series.length === 0) {
        sparkLine.setAttribute('d', '');
        sparkArea.setAttribute('d', '');
        return;
      }

      const values = series.map((v) => Number(v || 0)).filter((v) => !Number.isNaN(v));
      if (values.length === 0) return;
      const w = 320;
      const h = 52;
      const max = Math.max(...values, 1);
      const min = Math.min(...values);
      const span = Math.max(1, max - min);
      const step = values.length > 1 ? (w / (values.length - 1)) : w;
      const points = values.map((v, i) => {
        const x = i * step;
        const y = h - (((v - min) / span) * (h - 4)) - 2;
        return [x, y];
      });
      const line = points.map((p, i) => (i === 0 ? `M ${p[0]} ${p[1]}` : `L ${p[0]} ${p[1]}`)).join(' ');
      const area = `${line} L ${w} ${h} L 0 ${h} Z`;
      sparkLine.setAttribute('d', line);
      sparkArea.setAttribute('d', area);

      if (sparkMin) sparkMin.textContent = `24h min ${min} ms`;
      if (sparkMax) sparkMax.textContent = `24h max ${max} ms`;
      if (sparkNow) sparkNow.textContent = `now ${values[values.length - 1] || 0} ms`;
    }

    function renderOpacSearchAnalytics(searchAnalytics) {
      const analytics = searchAnalytics || {};
      const totalSearches = Number(analytics.total_searches || 0);
      const successRate = Number(analytics.success_rate_pct || 0);
      const zeroDistinct = Number(analytics.zero_result_distinct_queries || 0);
      if (anaTotal) anaTotal.textContent = totalSearches.toLocaleString('id-ID');
      if (anaSuccess) anaSuccess.textContent = `${successRate.toFixed(2)}%`;
      if (anaZero) anaZero.textContent = zeroDistinct.toLocaleString('id-ID');

      const renderRows = function (el, rows, emptyLabel) {
        if (!el) return;
        const list = Array.isArray(rows) ? rows : [];
        if (list.length === 0) {
          el.innerHTML = `<div class="nb-admin-auto-empty">${emptyLabel}</div>`;
          return;
        }
          const html = list.slice(0, 6).map(function (row) {
            const q = String((row && row.query) || '-');
            const cnt = Number((row && row.search_count) || 0).toLocaleString('id-ID');
            if (el.id === 'opac-ana-zero-list') {
              const synBase = @json(route('admin.search_synonyms'));
              const link = `${synBase}?term=${encodeURIComponent(q)}`;
              return `<div class="nb-admin-auto-row"><a href="${link}">${q}</a><b>${cnt}</b></div>`;
            }
            return `<div class="nb-admin-auto-row"><span>${q}</span><b>${cnt}</b></div>`;
          }).join('');
        el.innerHTML = html;
      };

      renderRows(anaTop, analytics.top_keywords, 'Belum ada data keyword.');
      renderRows(anaZeroList, analytics.top_zero_result_queries, 'Belum ada zero-result dominan.');
    }

    async function refreshOpac() {
      try {
        const resp = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
        if (!resp.ok) return;
        const data = await resp.json();
        const m = (data && data.metrics) || {};
        const lat = m.latency || {};
        const slo = m.slo || {};
        const p95 = Number(lat.p95_ms || 0);
        const p50 = Number(lat.p50_ms || 0);
        const req = Number(m.requests || 0);
        const err = Number(m.error_rate_pct || 0);
        const hist = (m.history && Array.isArray(m.history.last_24h)) ? m.history.last_24h : [];
        const burn5 = Number(slo.burn_rate_5m || 0);
        const burn60 = Number(slo.burn_rate_60m || 0);
        const targetPct = Number(slo.target_pct || 99.5);
        const sloState = String(slo.state || 'ok').toLowerCase();
        p95Series = hist.map((r) => Number((r && r.p95_ms) || 0)).filter((v) => !Number.isNaN(v));
        pill.textContent = `p95 ${p95} ms`;
        sub.textContent = `p50 ${p50} ms, error ${err.toFixed(2)}%, req ${req.toLocaleString('id-ID')}`;
        if (sloSub) {
          sloSub.textContent = `target ${targetPct.toFixed(2)}% | burn 5m ${burn5.toFixed(2)}x | burn 60m ${burn60.toFixed(2)}x`;
        }
        applySloBadge(sloState);
        renderSloAlert(sloState, burn5, burn60);
        applyBadge(p95);
        renderSparkline(p95Series);
        renderOpacSearchAnalytics(m.search_analytics || {});
        if (trace) {
          const traceId = String((data && data.trace_id) || resp.headers.get('X-Trace-Id') || '-');
          trace.textContent = `Trace ID: ${traceId}`;
        }
        lastUpdatedAt = Date.now();
        nextRefreshAt = Date.now() + 30000;
        renderUpdated();
        renderNextRefresh();
      } catch (_) {
        // keep last state
      }
    }

    function start() {
      if (timer) return;
      timer = setInterval(function () {
        if (document.visibilityState === 'visible') {
          refreshOpac();
        }
      }, 30000);
      nextRefreshAt = Date.now() + 30000;
    }

    function stop() {
      if (!timer) return;
      clearInterval(timer);
      timer = null;
    }

    if (refreshBtn) {
      refreshBtn.addEventListener('click', function () {
        refreshOpac();
      });
    }

    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') {
        start();
        refreshOpac();
      } else {
        stop();
      }
    });

    tick = setInterval(function () {
      renderUpdated();
      renderNextRefresh();
    }, 1000);
    renderSparkline(p95Series);
    renderOpacSearchAnalytics(@json($opacSearchAnalytics ?? []));
    applySloBadge(@json($opacSloState ?? 'ok'));
    renderSloAlert(@json($opacSloState ?? 'ok'), Number(@json($opacBurn5 ?? 0)), Number(@json($opacBurn60 ?? 0)));
    renderUpdated();
    renderNextRefresh();
    if (document.visibilityState === 'visible') {
      start();
    }
  })();
</script>
@endsection


