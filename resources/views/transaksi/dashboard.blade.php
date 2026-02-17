{{-- resources/views/transaksi/dashboard.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Dashboard Sirkulasi • NOTOBUKU')

@section('content')
@php
  $kpi = $kpi ?? [];
  $health = $health ?? [];
  $observability = $observability ?? [];
  $trend = $trend ?? [];
  $top_titles = $top_titles ?? [];
  $top_overdue_members = $top_overdue_members ?? [];
  $aging_overdue = $aging_overdue ?? [];
  $range_days = (int)($range_days ?? 14);

  $allowedRanges = [7, 14, 30];
  if (!in_array($range_days, $allowedRanges, true)) {
    $range_days = 14;
  }

  $get = function(array $arr, array $keys, $default = 0){
    foreach($keys as $key){
      if(array_key_exists($key, $arr) && $arr[$key] !== null){
        return $arr[$key];
      }
    }
    return $default;
  };

  $fmt = fn($n) => number_format((int)($n ?? 0), 0, ',', '.');
  $fmt1 = fn($n) => number_format((float)($n ?? 0), 1, ',', '.');
  $fmt2 = fn($n) => number_format((float)($n ?? 0), 2, ',', '.');

  $loans_today   = (int) $get($kpi, ['loans_today','loansToday'], 0);
  $returns_today = (int) $get($kpi, ['returns_today','returnsToday'], 0);
  $loans_month   = (int) $get($kpi, ['loans_month','loansMonth'], 0);
  $returns_month = (int) $get($kpi, ['returns_month','returnsMonth'], 0);
  $open_loans    = (int) $get($kpi, ['open_loans','openLoans'], 0);
  $overdue_loans = (int) $get($kpi, ['overdue_loans','overdueLoans'], 0);
  $overdue_items = (int) $get($kpi, ['overdue_items','overdueItems'], 0);

  $return_rate   = (float) ($health['return_rate'] ?? 0);
  $overdue_ratio = (float) ($health['overdue_ratio'] ?? 0);
  $on_time_rate  = (float) ($health['on_time_rate'] ?? 0);
  $obsHealth = (array) ($observability['health'] ?? []);
  $obsTotals = (array) ($observability['totals'] ?? []);
  $obsTopReasons = (array) ($observability['top_failure_reasons'] ?? []);
  $obsLabel = (string) ($obsHealth['label'] ?? 'Sehat');
  $obsClass = (string) ($obsHealth['class'] ?? 'good');
  $obsP95 = (int) ($obsTotals['latency_p95_ms'] ?? 0);
  $obsFailureRate = (float) ($obsTotals['business_failure_rate_pct'] ?? 0);
  $obsTopReason = count($obsTopReasons) > 0 ? (string) (($obsTopReasons[0]['reason'] ?? 'n/a')) : 'n/a';
  $obsTopReasonCount = count($obsTopReasons) > 0 ? (int) (($obsTopReasons[0]['count'] ?? 0)) : 0;
  $obsMetricsUrl = route('transaksi.metrics');

  $trendLabels = [];
  $trendLoans = [];
  $trendReturns = [];

  if (is_array($trend)) {
    foreach ($trend as $row) {
      $d = (string)($row['date'] ?? '');
      $loanC = (int)($row['loans'] ?? 0);
      $retC  = (int)($row['returns'] ?? 0);

      try {
        $label = \Carbon\Carbon::parse($d)->locale('id')->isoFormat('D MMM');
      } catch (\Throwable $e) {
        $label = $d;
      }

      $trendLabels[] = $label;
      $trendLoans[] = $loanC;
      $trendReturns[] = $retC;
    }
  }

  $insightTone = 'ok';
  if ($overdue_ratio >= 10) { $insightTone = 'bad'; }
  else if ($overdue_ratio >= 5) { $insightTone = 'warn'; }

  $mkRangeUrl = function(int $d) {
    return request()->fullUrlWithQuery(['range' => $d]);
  };
@endphp

<style>
  .nb-db-wrap{ max-width:1280px; margin:0 auto; padding:0 16px; }
  .nb-db-header { margin-bottom: 18px; padding-top: 8px; }
  .nb-db-title { font-size: 22px; font-weight: 700; color: #111827; margin: 0 0 6px 0; line-height: 1.3; }
  html.dark .nb-db-title { color: #f9fafb; }
  .nb-db-subtitle { font-size: 14px; font-weight: 500; color: #6b7280; margin: 0; line-height: 1.5; }
  html.dark .nb-db-subtitle { color: #9ca3af; }

  .nb-db-period {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 12px; background: #f3f4f6; border-radius: 10px;
    font-size: 13px; color: #4b5563; font-weight: 500; margin-top: 12px;
  }
  html.dark .nb-db-period { background: #374151; color: #d1d5db; }

  .nb-db-range {
    margin-top: 12px; display:flex; align-items:center; gap: 8px; flex-wrap: wrap;
  }
  .nb-db-range a {
    text-decoration:none;
    display:inline-flex; align-items:center; justify-content:center;
    padding: 7px 12px; border-radius: 999px;
    font-size: 12px; font-weight: 800;
    border: 1px solid #e5e7eb;
    color:#374151; background:#ffffff;
  }
  .nb-db-range a:hover { border-color:#d1d5db; }
  .nb-db-range a.active { border-color:#3b82f6; color:#1d4ed8; background: rgba(59,130,246,0.08); }
  html.dark .nb-db-range a { border-color:#374151; color:#e5e7eb; background:#1f2937; }
  html.dark .nb-db-range a.active { border-color:#60a5fa; color:#bfdbfe; background: rgba(96,165,250,0.12); }

  .nb-db-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px; }
  @media (max-width: 1200px) { .nb-db-stats-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 640px) { .nb-db-stats-grid { grid-template-columns: 1fr; } }

  .nb-db-stat-card {
    background: #ffffff; border-radius: 16px; padding: 18px;
    border: 1px solid #e5e7eb; transition: all 0.15s ease;
    position: relative; overflow: hidden;
  }
  html.dark .nb-db-stat-card { background: #1f2937; border-color: #374151; }
  .nb-db-stat-card:hover { border-color: #d1d5db; box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04); }
  html.dark .nb-db-stat-card:hover { border-color: #4b5563; }

  .nb-db-stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
  .nb-db-stat-card.blue::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
  .nb-db-stat-card.green::before { background: linear-gradient(90deg, #10b981, #34d399); }
  .nb-db-stat-card.amber::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
  .nb-db-stat-card.purple::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }

  .nb-db-stat-value { font-size: 24px; font-weight: 800; color: #111827; margin: 6px 0 4px; line-height: 1.1; }
  html.dark .nb-db-stat-value { color: #f9fafb; }
  .nb-db-stat-label { font-size: 13px; font-weight: 600; color: #6b7280; margin: 0; }
  html.dark .nb-db-stat-label { color: #9ca3af; }
  .nb-db-stat-desc { font-size: 12px; color: #9ca3af; line-height: 1.4; margin-top: 8px; }
  html.dark .nb-db-stat-desc { color: #6b7280; }

  .nb-db-main-grid { display:grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px; }
  @media (max-width: 1024px) { .nb-db-main-grid { grid-template-columns: 1fr; } }

  .nb-db-card {
    background: #ffffff; border-radius: 16px; padding: 18px;
    border: 1px solid #e5e7eb;
  }
  html.dark .nb-db-card { background: #1f2937; border-color: #374151; }

  .nb-db-card-title { font-size: 14px; font-weight: 800; color: #111827; margin: 0 0 4px; }
  html.dark .nb-db-card-title { color: #f9fafb; }
  .nb-db-card-sub { font-size: 13px; color: #6b7280; margin: 0; }
  html.dark .nb-db-card-sub { color: #9ca3af; }

  .nb-db-chart-wrapper { height: 280px; width:100%; position:relative; margin-top: 12px; }

  .nb-db-pill {
    display:inline-flex; align-items:center; gap:8px;
    padding: 6px 10px; border-radius: 999px;
    font-size: 12px; font-weight: 700;
    border: 1px solid transparent;
  }
  .nb-db-pill.ok { background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
  .nb-db-pill.warn { background:#fffbeb; color:#92400e; border-color:#fde68a; }
  .nb-db-pill.bad { background:#fef2f2; color:#991b1b; border-color:#fecaca; }
  html.dark .nb-db-pill.ok { background:rgba(16,185,129,0.12); color:#a7f3d0; border-color:rgba(16,185,129,0.25); }
  html.dark .nb-db-pill.warn { background:rgba(245,158,11,0.12); color:#fde68a; border-color:rgba(245,158,11,0.25); }
  html.dark .nb-db-pill.bad { background:rgba(239,68,68,0.12); color:#fecaca; border-color:rgba(239,68,68,0.25); }

  .nb-db-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  .nb-db-table th {
    text-align:left; font-size:12px; color:#6b7280;
    padding: 10px 0; border-bottom: 1px solid #e5e7eb; font-weight: 800;
  }
  .nb-db-table td {
    padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 13px; color:#111827;
  }
  html.dark .nb-db-table th { color:#9ca3af; border-bottom-color:#374151; }
  html.dark .nb-db-table td { color:#f9fafb; border-bottom-color:#374151; }

  .nb-db-empty {
    padding: 14px; margin-top: 12px;
    background: #f9fafb; border: 1px dashed #e5e7eb; border-radius: 12px;
    color:#6b7280; font-size: 13px; font-weight: 600;
  }
  html.dark .nb-db-empty {
    background: rgba(55,65,81,0.25); border-color: rgba(75,85,99,0.45); color:#9ca3af;
  }

  .nb-db-bar { display:flex; align-items:center; gap:10px; margin-top: 10px; }
  .nb-db-bar-label { width: 58px; font-size: 12px; font-weight: 800; color:#6b7280; }
  html.dark .nb-db-bar-label { color:#9ca3af; }
  .nb-db-bar-track {
    flex:1; height: 10px; background:#f3f4f6; border-radius: 999px; overflow:hidden;
    border: 1px solid #e5e7eb;
  }
  html.dark .nb-db-bar-track { background: rgba(55,65,81,0.35); border-color:#374151; }
  .nb-db-bar-fill { height: 100%; background: linear-gradient(90deg, #f59e0b, #ef4444); }
  .nb-db-bar-val { width: 56px; text-align:right; font-size: 12px; font-weight: 900; color:#111827; }
  html.dark .nb-db-bar-val { color:#f9fafb; }
</style>

<div class="nb-db-wrap">
  <div class="nb-db-header">
    <h1 class="nb-db-title">Dashboard Sirkulasi</h1>
    <p class="nb-db-subtitle">Ringkasan transaksi sirkulasi & indikator operasional (scoped per cabang aktif).</p>

    <div class="nb-db-period">
      <span>📅</span>
      <span>{{ \Carbon\Carbon::now()->locale('id')->isoFormat('dddd, D MMMM YYYY') }}</span>
    </div>

    <div class="nb-db-range">
      <a href="{{ $mkRangeUrl(7) }}" class="{{ $range_days===7 ? 'active' : '' }}">7 hari</a>
      <a href="{{ $mkRangeUrl(14) }}" class="{{ $range_days===14 ? 'active' : '' }}">14 hari</a>
      <a href="{{ $mkRangeUrl(30) }}" class="{{ $range_days===30 ? 'active' : '' }}">30 hari</a>
      <a href="{{ route('transaksi.exceptions.index') }}">Exception Ops</a>
    </div>
  </div>

  {{-- KPI --}}
  <div class="nb-db-stats-grid">
    <div class="nb-db-stat-card blue">
      <div class="nb-db-stat-label">Peminjaman Hari Ini</div>
      <div class="nb-db-stat-value">{{ $fmt($loans_today) }}</div>
      <div class="nb-db-stat-desc">Jumlah transaksi pinjam hari ini.</div>
    </div>

    <div class="nb-db-stat-card green">
      <div class="nb-db-stat-label">Pengembalian Hari Ini</div>
      <div class="nb-db-stat-value">{{ $fmt($returns_today) }}</div>
      <div class="nb-db-stat-desc">Jumlah item kembali hari ini.</div>
    </div>

    <div class="nb-db-stat-card purple">
      <div class="nb-db-stat-label">Open Loans</div>
      <div class="nb-db-stat-value">{{ $fmt($open_loans) }}</div>
      <div class="nb-db-stat-desc">Transaksi yang masih berjalan.</div>
    </div>

    <div class="nb-db-stat-card amber">
      <div class="nb-db-stat-label">Overdue Items</div>
      <div class="nb-db-stat-value">{{ $fmt($overdue_items) }}</div>
      <div class="nb-db-stat-desc">Item lewat jatuh tempo (belum kembali).</div>
    </div>
  </div>

  {{-- Main: Trend + Health --}}
  <div class="nb-db-main-grid">
    <div class="nb-db-card">
      <div class="nb-db-card-title">Trend {{ $range_days }} Hari (Pinjam vs Kembali)</div>
      <div class="nb-db-card-sub">Membaca ritme harian untuk periode terpilih.</div>

      @if(empty($trendLabels))
        <div class="nb-db-empty">Belum ada data trend.</div>
      @else
        <div class="nb-db-chart-wrapper">
          <canvas id="nbTrendChart"></canvas>
        </div>
      @endif
    </div>

    <div class="nb-db-card">
      <div class="nb-db-card-title">Health & Operasional</div>
      <div class="nb-db-card-sub">Rate ringkas untuk monitoring cepat.</div>

      <div style="margin-top:12px; display:flex; flex-direction:column; gap:10px;">
        <span class="nb-db-pill {{ $insightTone }}">
          Overdue Ratio: {{ $fmt1($overdue_ratio) }}%
        </span>
        <span class="nb-db-pill {{ $return_rate < 70 ? 'warn' : 'ok' }}">
          Return Rate (bulan ini): {{ $fmt1($return_rate) }}%
        </span>
        <span class="nb-db-pill {{ $on_time_rate < 70 ? 'warn' : 'ok' }}">
          On-time Rate (bulan ini): {{ $fmt1($on_time_rate) }}%
        </span>
      </div>

      <table class="nb-db-table" style="margin-top: 14px;">
        <tbody>
          <tr>
            <td>Total pinjam (bulan ini)</td>
            <td style="text-align:right; font-weight:900;">{{ $fmt($loans_month) }}</td>
          </tr>
          <tr>
            <td>Total kembali (bulan ini)</td>
            <td style="text-align:right; font-weight:900;">{{ $fmt($returns_month) }}</td>
          </tr>
          <tr>
            <td>Transaksi overdue</td>
            <td style="text-align:right; font-weight:900;">{{ $fmt($overdue_loans) }}</td>
          </tr>
        </tbody>
      </table>

      <div style="margin-top:14px; border-top:1px dashed #e5e7eb; padding-top:12px;">
        <div class="nb-db-card-title" style="font-size:13px;">Observability Alert</div>
        <div class="nb-db-card-sub">p95 latency + failure reason top-N endpoint sirkulasi.</div>
        <div style="margin-top:10px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
          <span
            id="circ-obs-pill"
            class="nb-db-pill {{ $obsClass === 'critical' ? 'bad' : ($obsClass === 'warning' ? 'warn' : 'ok') }}"
            data-metrics-url="{{ $obsMetricsUrl }}"
          >{{ $obsLabel }}</span>
          <button id="circ-obs-refresh" type="button" class="nb-db-pill ok" style="cursor:pointer;">Refresh now</button>
        </div>
        <div id="circ-obs-sub" class="nb-db-card-sub" style="margin-top:8px;">
          p95 {{ $fmt($obsP95) }} ms, failure {{ $fmt2($obsFailureRate) }}%
        </div>
        <div id="circ-obs-reason" class="nb-db-card-sub" style="margin-top:4px;">
          Top failure: {{ $obsTopReason }} ({{ $fmt($obsTopReasonCount) }})
        </div>
        <div id="circ-obs-updated" class="nb-db-card-sub" style="margin-top:4px;">Last updated baru saja</div>
      </div>
    </div>
  </div>

  {{-- Extra sections --}}
  <div class="nb-db-main-grid" style="grid-template-columns: 1fr 1fr 1fr;">
    {{-- Aging Overdue --}}
    <div class="nb-db-card">
      <div class="nb-db-card-title">Aging Overdue</div>
      <div class="nb-db-card-sub">Sebaran keterlambatan untuk prioritas follow-up.</div>

      @php
        $b = is_array($aging_overdue) ? $aging_overdue : [];
        $b13 = (int)($b['1-3'] ?? 0);
        $b47 = (int)($b['4-7'] ?? 0);
        $b814 = (int)($b['8-14'] ?? 0);
        $b1530 = (int)($b['15-30'] ?? 0);
        $b30p = (int)($b['30+'] ?? 0);
        $maxBucket = max($b13, $b47, $b814, $b1530, $b30p, 1);
        $mk = fn($x) => max(4, (int) round(($x / $maxBucket) * 100));
      @endphp

      @if(($b13 + $b47 + $b814 + $b1530 + $b30p) === 0)
        <div class="nb-db-empty">Tidak ada overdue items saat ini.</div>
      @else
        <div class="nb-db-bar">
          <div class="nb-db-bar-label">1–3</div>
          <div class="nb-db-bar-track"><div class="nb-db-bar-fill" style="width: {{ $mk($b13) }}%;"></div></div>
          <div class="nb-db-bar-val">{{ $fmt($b13) }}</div>
        </div>
        <div class="nb-db-bar">
          <div class="nb-db-bar-label">4–7</div>
          <div class="nb-db-bar-track"><div class="nb-db-bar-fill" style="width: {{ $mk($b47) }}%;"></div></div>
          <div class="nb-db-bar-val">{{ $fmt($b47) }}</div>
        </div>
        <div class="nb-db-bar">
          <div class="nb-db-bar-label">8–14</div>
          <div class="nb-db-bar-track"><div class="nb-db-bar-fill" style="width: {{ $mk($b814) }}%;"></div></div>
          <div class="nb-db-bar-val">{{ $fmt($b814) }}</div>
        </div>
        <div class="nb-db-bar">
          <div class="nb-db-bar-label">15–30</div>
          <div class="nb-db-bar-track"><div class="nb-db-bar-fill" style="width: {{ $mk($b1530) }}%;"></div></div>
          <div class="nb-db-bar-val">{{ $fmt($b1530) }}</div>
        </div>
        <div class="nb-db-bar">
          <div class="nb-db-bar-label">30+</div>
          <div class="nb-db-bar-track"><div class="nb-db-bar-fill" style="width: {{ $mk($b30p) }}%;"></div></div>
          <div class="nb-db-bar-val">{{ $fmt($b30p) }}</div>
        </div>
      @endif
    </div>

    {{-- Top Titles --}}
    <div class="nb-db-card">
      <div class="nb-db-card-title">Top Titles</div>
      <div class="nb-db-card-sub">Judul paling sering dipinjam (window {{ $range_days }} hari).</div>

      @if(empty($top_titles))
        <div class="nb-db-empty">Belum ada data Top Titles.</div>
      @else
        <table class="nb-db-table">
          <thead>
            <tr>
              <th style="width:60%;">Judul</th>
              <th style="width:18%;">Pinjam</th>
              <th style="text-align:right;">Pressure</th>
            </tr>
          </thead>
          <tbody>
            @foreach($top_titles as $row)
              @php
                $title = is_array($row) ? ($row['title'] ?? '-') : ($row->title ?? '-');
                $total = is_array($row) ? ($row['total'] ?? 0) : ($row->total ?? 0);
                $pressure = is_array($row) ? ($row['stock_pressure'] ?? 0) : ($row->stock_pressure ?? 0);
              @endphp
              <tr>
                <td style="font-weight:800;">{{ $title }}</td>
                <td>{{ $fmt($total) }}</td>
                <td style="text-align:right; font-weight:900;">{{ $fmt2($pressure) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>

        <div style="margin-top:12px;">
          <span class="nb-db-pill ok">Pressure = total pinjam / total stok.</span>
        </div>
      @endif
    </div>

    {{-- Top Overdue Members --}}
    <div class="nb-db-card">
      <div class="nb-db-card-title">Top Overdue Members</div>
      <div class="nb-db-card-sub">Anggota dengan overdue terbanyak + keterlambatan terlama.</div>

      @if(empty($top_overdue_members))
        <div class="nb-db-empty">Tidak ada member dengan overdue saat ini.</div>
      @else
        <table class="nb-db-table">
          <thead>
            <tr>
              <th style="width:55%;">Member</th>
              <th style="width:18%;">Overdue</th>
              <th style="text-align:right;">Max hari</th>
            </tr>
          </thead>
          <tbody>
            @foreach($top_overdue_members as $row)
              @php
                $name  = is_array($row) ? ($row['name'] ?? '-') : ($row->name ?? '-');
                $total = is_array($row) ? ($row['overdue_items'] ?? 0) : ($row->overdue_items ?? 0);
                $maxd  = is_array($row) ? ($row['max_days_overdue'] ?? 0) : ($row->max_days_overdue ?? 0);
              @endphp
              <tr>
                <td style="font-weight:800;">{{ $name }}</td>
                <td>{{ $fmt($total) }}</td>
                <td style="text-align:right; font-weight:900;">{{ $fmt($maxd) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>

        <div style="margin-top:12px;">
          <span class="nb-db-pill warn">Saran: follow-up mulai dari bucket 15–30 & 30+ hari.</span>
        </div>
      @endif
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const labels  = @json($trendLabels);
  const loans   = @json($trendLoans);
  const returns = @json($trendReturns);

  const el = document.getElementById('nbTrendChart');
  if(!el) return;
  if(!labels || labels.length === 0) return;

  const isDark = document.documentElement.classList.contains('dark');
  const ctx = el.getContext('2d');

  if(window.__nbTrendChart && typeof window.__nbTrendChart.destroy === 'function'){
    window.__nbTrendChart.destroy();
  }

  const gridColor = isDark ? 'rgba(55, 65, 81, 0.20)' : 'rgba(229, 231, 235, 0.80)';
  const textColor = isDark ? 'rgba(156, 163, 175, 0.85)' : 'rgba(75, 85, 99, 0.75)';

  window.__nbTrendChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Peminjaman',
          data: loans,
          tension: 0.3,
          borderWidth: 2,
          borderColor: isDark ? '#60a5fa' : '#3b82f6',
          backgroundColor: isDark ? 'rgba(96, 165, 250, 0.10)' : 'rgba(59, 130, 246, 0.10)',
          pointRadius: 2,
          pointHoverRadius: 4,
          fill: true
        },
        {
          label: 'Pengembalian',
          data: returns,
          tension: 0.3,
          borderWidth: 2,
          borderColor: isDark ? '#34d399' : '#10b981',
          backgroundColor: isDark ? 'rgba(52, 211, 153, 0.10)' : 'rgba(16, 185, 129, 0.10)',
          pointRadius: 2,
          pointHoverRadius: 4,
          fill: true
        },
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: isDark ? '#1f2937' : '#ffffff',
          titleColor: isDark ? '#f3f4f6' : '#111827',
          bodyColor: isDark ? '#d1d5db' : '#374151',
          borderColor: isDark ? '#374151' : '#e5e7eb',
          borderWidth: 1,
          padding: 10,
          displayColors: false,
          callbacks: {
            label: function(ctx){
              const v = (ctx.parsed && typeof ctx.parsed.y === 'number') ? ctx.parsed.y : 0;
              return `${ctx.dataset.label}: ${v.toLocaleString('id-ID')}`;
            }
          }
        }
      },
      scales: {
        x: {
          grid: { color: 'transparent' },
          ticks: { color: textColor, font: { size: 11, weight: '600' }, maxRotation: 0 }
        },
        y: {
          beginAtZero: true,
          grid: { color: gridColor },
          ticks: {
            color: textColor,
            font: { size: 11, weight: '600' },
            callback: v => Number(v).toLocaleString('id-ID'),
            padding: 6
          }
        }
      }
    }
  });
})();

(function(){
  const pill = document.getElementById('circ-obs-pill');
  const sub = document.getElementById('circ-obs-sub');
  const reasonEl = document.getElementById('circ-obs-reason');
  const updated = document.getElementById('circ-obs-updated');
  const refreshBtn = document.getElementById('circ-obs-refresh');
  if(!pill || !sub || !reasonEl || !updated) return;

  const url = pill.dataset.metricsUrl || '';
  if(!url) return;

  let lastUpdatedAt = Date.now();
  let intervalId = null;

  function setPillClass(cls, label){
    pill.classList.remove('ok', 'warn', 'bad');
    if(cls === 'critical') pill.classList.add('bad');
    else if(cls === 'warning') pill.classList.add('warn');
    else pill.classList.add('ok');
    pill.textContent = label || 'Sehat';
  }

  function refreshRelativeTime(){
    const sec = Math.max(0, Math.floor((Date.now() - lastUpdatedAt) / 1000));
    updated.textContent = `Last updated ${sec} detik lalu`;
  }

  async function refreshObservability(){
    try {
      const resp = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store',
      });
      if(!resp.ok) return;
      const data = await resp.json();
      if(!data || data.ok !== true || !data.metrics) return;

      const metrics = data.metrics || {};
      const health = metrics.health || {};
      const totals = metrics.totals || {};
      const reasons = Array.isArray(metrics.top_failure_reasons) ? metrics.top_failure_reasons : [];

      const p95 = Number(totals.latency_p95_ms || 0);
      const failureRate = Number(totals.business_failure_rate_pct || 0);
      const topReason = reasons.length > 0 ? String((reasons[0] && reasons[0].reason) || 'n/a') : 'n/a';
      const topReasonCount = reasons.length > 0 ? Number((reasons[0] && reasons[0].count) || 0) : 0;

      setPillClass(String(health.class || 'good'), String(health.label || 'Sehat'));
      sub.textContent = `p95 ${p95.toLocaleString('id-ID')} ms, failure ${failureRate.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`;
      reasonEl.textContent = `Top failure: ${topReason} (${topReasonCount.toLocaleString('id-ID')})`;
      lastUpdatedAt = Date.now();
      refreshRelativeTime();
    } catch (e) {
      // no-op
    }
  }

  function startRefreshLoop(){
    if(intervalId) return;
    intervalId = setInterval(() => {
      if(document.hidden) return;
      refreshObservability();
      refreshRelativeTime();
    }, 30000);
  }

  function stopRefreshLoop(){
    if(!intervalId) return;
    clearInterval(intervalId);
    intervalId = null;
  }

  if(refreshBtn){
    refreshBtn.addEventListener('click', refreshObservability);
  }

  document.addEventListener('visibilitychange', () => {
    if(document.hidden){
      stopRefreshLoop();
      return;
    }
    refreshRelativeTime();
    refreshObservability();
    startRefreshLoop();
  });

  refreshRelativeTime();
  startRefreshLoop();
})();
</script>
@endsection
