@extends('layouts.notobuku')

@section('title', 'Analytics Pencarian - Admin NOTOBUKU')

@section('content')
@php
  $totalSearches = (int) ($searchAnalytics['total_searches'] ?? 0);
  $successRate = (float) ($searchAnalytics['success_rate_pct'] ?? 0);
  $zeroDistinct = (int) ($searchAnalytics['zero_result_distinct_queries'] ?? 0);
  $windowSearches = (int) ($searchAnalytics['window_searches'] ?? 0);
  $zeroRate = (float) ($searchAnalytics['zero_result_rate_pct'] ?? 0);
  $noClickRate = (float) ($searchAnalytics['no_click_rate_pct'] ?? 0);
  $borrowConvRate = (float) ($searchAnalytics['conversion_to_borrow_rate_pct'] ?? 0);
  $alertState = (string) ($searchAnalytics['alert_state'] ?? 'ok');
  $alertLastAt = trim((string) ($searchAnalytics['alert_last_triggered_at'] ?? ''));
  $alertCls = $alertState === 'critical' ? 'crit' : ($alertState === 'warning' ? 'warn' : 'ok');
  $topKeywords = collect((array) ($searchAnalytics['top_keywords'] ?? []))->take(10);
  $topZero = collect((array) ($searchAnalytics['top_zero_result_queries'] ?? []))->take(10);
@endphp

<style>
  .nb-sa-wrap{max-width:1100px;margin:0 auto;--c:#0b2545;--m:rgba(11,37,69,.62);--b:rgba(148,163,184,.25);}
  .nb-sa-card{background:rgba(255,255,255,.93);border:1px solid var(--b);border-radius:16px;padding:16px;margin-bottom:12px;}
  .nb-sa-head{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;}
  .nb-sa-title{font-size:18px;font-weight:800;color:var(--c);}
  .nb-sa-sub{font-size:12.5px;color:var(--m);}
  .nb-sa-kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-top:10px;}
  .nb-sa-k{border:1px solid var(--b);border-radius:12px;padding:12px;background:#fff}
  .nb-sa-k .l{font-size:12px;color:var(--m);font-weight:700}
  .nb-sa-k .v{font-size:24px;color:var(--c);font-weight:800}
  .nb-sa-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .nb-sa-table{width:100%;border-collapse:collapse;font-size:12.5px}
  .nb-sa-table th,.nb-sa-table td{border-top:1px solid var(--b);padding:9px;text-align:left}
  .nb-sa-table th{color:var(--m);font-weight:700}
  .nb-sa-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;border:1px solid var(--b);font-size:11px;font-weight:700;letter-spacing:.3px;text-transform:uppercase}
  .nb-sa-badge.ok{background:#ecfdf5;border-color:#86efac;color:#166534}
  .nb-sa-badge.warn{background:#fffbeb;border-color:#fcd34d;color:#92400e}
  .nb-sa-badge.crit{background:#fef2f2;border-color:#fca5a5;color:#991b1b}
  @media(max-width:900px){.nb-sa-grid{grid-template-columns:1fr}}
</style>

<div class="nb-sa-wrap">
  <div class="nb-sa-card">
    <div class="nb-sa-head">
      <div>
        <div class="nb-sa-title">Search Analytics</div>
        <div class="nb-sa-sub">Ringkasan penggunaan pencarian OPAC {{ $days }} hari terakhir.</div>
      </div>
      <div class="nb-sa-badge {{ $alertCls }}">Search Alert: {{ $alertState }}</div>
      <form method="GET">
        <select name="days" onchange="this.form.submit()" style="border:1px solid var(--b);border-radius:10px;padding:6px 10px;">
          <option value="7" @selected($days===7)>7 hari</option>
          <option value="30" @selected($days===30)>30 hari</option>
          <option value="60" @selected($days===60)>60 hari</option>
          <option value="90" @selected($days===90)>90 hari</option>
        </select>
      </form>
    </div>
    <div class="nb-sa-kpi">
      <div class="nb-sa-k"><div class="l">Total Search</div><div class="v">{{ number_format($totalSearches) }}</div></div>
      <div class="nb-sa-k"><div class="l">Success Rate</div><div class="v">{{ number_format($successRate, 2) }}%</div></div>
      <div class="nb-sa-k"><div class="l">Distinct Zero Result</div><div class="v">{{ number_format($zeroDistinct) }}</div></div>
      <div class="nb-sa-k"><div class="l">Queue Open</div><div class="v">{{ number_format((int) ($zeroQueue['open'] ?? 0)) }}</div></div>
      <div class="nb-sa-k"><div class="l">Zero-result Rate (window)</div><div class="v">{{ number_format($zeroRate, 2) }}%</div></div>
      <div class="nb-sa-k"><div class="l">No-click Rate (window)</div><div class="v">{{ number_format($noClickRate, 2) }}%</div></div>
      <div class="nb-sa-k"><div class="l">Borrow Conversion (window)</div><div class="v">{{ number_format($borrowConvRate, 2) }}%</div></div>
      <div class="nb-sa-k"><div class="l">Window Searches</div><div class="v">{{ number_format($windowSearches) }}</div></div>
    </div>
    <div class="nb-sa-sub" style="margin-top:8px;">
      Alert terakhir: {{ $alertLastAt !== '' ? $alertLastAt : '-' }}
    </div>
  </div>

  <div class="nb-sa-card">
    <div class="nb-sa-title" style="font-size:14px;margin-bottom:8px;">Tren Search Harian</div>
    <canvas id="nbSearchTrendChart" height="90"></canvas>
  </div>

  <div class="nb-sa-grid">
    <div class="nb-sa-card">
      <div class="nb-sa-title" style="font-size:14px;">Top Keyword</div>
      <table class="nb-sa-table">
        <thead><tr><th>Query</th><th>Total</th><th>Hits terakhir</th></tr></thead>
        <tbody>
          @forelse($topKeywords as $r)
            <tr><td>{{ $r['query'] ?? '' }}</td><td>{{ number_format((int) ($r['search_count'] ?? 0)) }}</td><td>{{ (int) ($r['last_hits'] ?? 0) }}</td></tr>
          @empty
            <tr><td colspan="3">Belum ada data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="nb-sa-card">
      <div class="nb-sa-title" style="font-size:14px;">Top Zero Result</div>
      <table class="nb-sa-table">
        <thead><tr><th>Query</th><th>Total</th></tr></thead>
        <tbody>
          @forelse($topZero as $r)
            <tr><td>{{ $r['query'] ?? '' }}</td><td>{{ number_format((int) ($r['search_count'] ?? 0)) }}</td></tr>
          @empty
            <tr><td colspan="2">Belum ada data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="nb-sa-grid">
    <div class="nb-sa-card">
      <div class="nb-sa-title" style="font-size:14px;">Status Zero-result Queue</div>
      <table class="nb-sa-table">
        <thead><tr><th>Status</th><th>Total</th></tr></thead>
        <tbody>
          @foreach(['open','resolved','resolved_auto','ignored'] as $st)
            <tr><td>{{ $st }}</td><td>{{ number_format((int) ($zeroQueue[$st] ?? 0)) }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="nb-sa-card">
      <div class="nb-sa-title" style="font-size:14px;">Workflow Sinonim</div>
      <table class="nb-sa-table">
        <thead><tr><th>Status</th><th>Total</th></tr></thead>
        <tbody>
          @foreach(['pending','approved','rejected'] as $st)
            <tr><td>{{ $st }}</td><td>{{ number_format((int) ($synonymStats[$st] ?? 0)) }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
  const el = document.getElementById('nbSearchTrendChart');
  if (!el || typeof Chart === 'undefined') return;
  const labels = @json($trendLabels->all());
  const total = @json($trendTotal->all());
  const zero = @json($trendZero->all());
  new Chart(el.getContext('2d'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'Total Search', data: total, borderColor: '#1f6feb', backgroundColor: 'rgba(31,111,235,.15)', fill: true, tension: 0.3 },
        { label: 'Zero Result', data: zero, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.1)', fill: true, tension: 0.3 },
      ],
    },
    options: { plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true } } },
  });
})();
</script>
@endsection
