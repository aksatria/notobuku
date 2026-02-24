@extends('layouts.notobuku')

@section('title', 'Analitik Pencarian - Admin NOTOBUKU')

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
  $ctaTotals = (array) ($ctaTotals ?? []);
  $ctaDetail = (int) ($ctaTotals['cta_detail'] ?? 0);
  $ctaReserve = (int) ($ctaTotals['cta_reserve'] ?? 0);
  $ctaDetailSearch = (int) ($ctaTotals['cta_detail_search'] ?? 0);
  $ctaDetailDetail = (int) ($ctaTotals['cta_detail_detail'] ?? 0);
  $ctaReserveSearch = (int) ($ctaTotals['cta_reserve_search'] ?? 0);
  $ctaReserveDetail = (int) ($ctaTotals['cta_reserve_detail'] ?? 0);
  $eventClicks = (int) ($ctaTotals['clicks'] ?? 0);
  $eventBorrows = (int) ($ctaTotals['borrows'] ?? 0);
  $eventReserves = (int) ($ctaTotals['reserves'] ?? 0);
  $ctaDetailRate = $windowSearches > 0 ? round(($ctaDetail / $windowSearches) * 100, 2) : 0.0;
  $ctaReserveRate = $windowSearches > 0 ? round(($ctaReserve / $windowSearches) * 100, 2) : 0.0;
  $borrowFromCtaDetailRate = $ctaDetail > 0 ? round(($eventBorrows / $ctaDetail) * 100, 2) : 0.0;
  $reserveFromCtaReserveRate = $ctaReserve > 0 ? round(($eventReserves / $ctaReserve) * 100, 2) : 0.0;
  $ctaSourceSplit = (array) ($ctaSourceSplit ?? []);
  $ctaDetailSplit = (array) ($ctaSourceSplit['detail'] ?? []);
  $ctaReserveSplit = (array) ($ctaSourceSplit['reserve'] ?? []);
@endphp

<style>
  .nb-sa-wrap{max-width:1100px;margin:0 auto;--c:#0b2545;--m:rgba(11,37,69,.62);--b:rgba(148,163,184,.25);}
  .nb-sa-card{background:rgba(255,255,255,.93);border:1px solid var(--b);border-radius:16px;padding:16px;margin-bottom:12px;}
  .nb-sa-head{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;}
  .nb-sa-title{font-size:18px;font-weight:600;color:var(--c);}
  .nb-sa-sub{font-size:12.5px;color:var(--m);}
  .nb-sa-kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-top:10px;}
  .nb-sa-k{border:1px solid var(--b);border-radius:12px;padding:12px;background:#fff}
  .nb-sa-k .l{font-size:12px;color:var(--m);font-weight:500}
  .nb-sa-k .v{font-size:24px;color:var(--c);font-weight:600}
  .nb-sa-k:nth-child(4n+1){background:#dbeafe;border-color:#60a5fa;box-shadow:inset 0 0 0 1px rgba(37,99,235,.18);}
  .nb-sa-k:nth-child(4n+2){background:#ccfbf1;border-color:#2dd4bf;box-shadow:inset 0 0 0 1px rgba(13,148,136,.18);}
  .nb-sa-k:nth-child(4n+3){background:#fef3c7;border-color:#f59e0b;box-shadow:inset 0 0 0 1px rgba(180,83,9,.16);}
  .nb-sa-k:nth-child(4n+4){background:#ede9fe;border-color:#8b5cf6;box-shadow:inset 0 0 0 1px rgba(109,40,217,.16);}
  .nb-sa-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .nb-sa-table{width:100%;border-collapse:collapse;font-size:12.5px}
  .nb-sa-table th,.nb-sa-table td{border-top:1px solid var(--b);padding:9px;text-align:left;color:#243b53}
  .nb-sa-table th{color:#486581;font-weight:600}
  .nb-sa-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;border:1px solid var(--b);font-size:11px;font-weight:600;letter-spacing:.3px;text-transform:uppercase}
  .nb-sa-badge.ok{background:#ecfdf5;border-color:#86efac;color:#166534}
  .nb-sa-badge.warn{background:#fffbeb;border-color:#fcd34d;color:#92400e}
  .nb-sa-badge.crit{background:#fef2f2;border-color:#fca5a5;color:#991b1b}
  @media(max-width:900px){.nb-sa-grid{grid-template-columns:1fr}}
</style>

<div class="nb-sa-wrap">
  <div class="nb-sa-card">
    <div class="nb-sa-head">
      <div>
        <div class="nb-sa-title">Analitik Pencarian</div>
        <div class="nb-sa-sub">Ringkasan penggunaan pencarian OPAC {{ $days }} hari terakhir.</div>
      </div>
      <div class="nb-sa-badge {{ $alertCls }}">Peringatan Pencarian: {{ $alertState }}</div>
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
      <div class="nb-sa-k"><div class="l">Total Pencarian</div><div class="v">{{ number_format($totalSearches) }}</div></div>
      <div class="nb-sa-k"><div class="l">Tingkat Berhasil</div><div class="v">{{ number_format($successRate, 2) }}%</div></div>
      <div class="nb-sa-k"><div class="l">Kueri Nihil Unik</div><div class="v">{{ number_format($zeroDistinct) }}</div></div>
      <div class="nb-sa-k"><div class="l">Antrian Terbuka</div><div class="v">{{ number_format((int) ($zeroQueue['open'] ?? 0)) }}</div></div>
      <div class="nb-sa-k"><div class="l">Rasio Hasil Nihil (periode)</div><div class="v">{{ number_format($zeroRate, 2) }}%</div></div>
      <div class="nb-sa-k"><div class="l">Rasio Tanpa Klik (periode)</div><div class="v">{{ number_format($noClickRate, 2) }}%</div></div>
      <div class="nb-sa-k"><div class="l">Konversi ke Pinjam (periode)</div><div class="v">{{ number_format($borrowConvRate, 2) }}%</div></div>
      <div class="nb-sa-k"><div class="l">Total Pencarian (periode)</div><div class="v">{{ number_format($windowSearches) }}</div></div>
      <div class="nb-sa-k"><div class="l">CTA Detail</div><div class="v">{{ number_format($ctaDetail) }}</div></div>
      <div class="nb-sa-k"><div class="l">CTA Reservasi</div><div class="v">{{ number_format($ctaReserve) }}</div></div>
      <div class="nb-sa-k"><div class="l">CTA Detail (Hasil Pencarian)</div><div class="v">{{ number_format($ctaDetailSearch) }}</div></div>
      <div class="nb-sa-k"><div class="l">CTA Detail (Halaman Detail)</div><div class="v">{{ number_format($ctaDetailDetail) }}</div></div>
      <div class="nb-sa-k"><div class="l">CTA Reservasi (Hasil Pencarian)</div><div class="v">{{ number_format($ctaReserveSearch) }}</div></div>
      <div class="nb-sa-k"><div class="l">CTA Reservasi (Halaman Detail)</div><div class="v">{{ number_format($ctaReserveDetail) }}</div></div>
      <div class="nb-sa-k"><div class="l">Rasio CTA Detail</div><div class="v">{{ number_format($ctaDetailRate, 2) }}%</div></div>
      <div class="nb-sa-k"><div class="l">Rasio CTA Reservasi</div><div class="v">{{ number_format($ctaReserveRate, 2) }}%</div></div>
    </div>
    <div class="nb-sa-sub" style="margin-top:8px;">
      Peringatan terakhir: {{ $alertLastAt !== '' ? $alertLastAt : '-' }}
    </div>
  </div>

  <div class="nb-sa-card">
    <div class="nb-sa-title" style="font-size:14px;margin-bottom:8px;">Tren Pencarian Harian</div>
    <canvas id="nbSearchTrendChart" height="90"></canvas>
  </div>

  <div class="nb-sa-grid">
    <div class="nb-sa-card">
      <div class="nb-sa-title" style="font-size:14px;">Kata Kunci Teratas</div>
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
      <div class="nb-sa-title" style="font-size:14px;">Kueri Hasil Nihil Teratas</div>
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
      <div class="nb-sa-title" style="font-size:14px;">Corong CTA</div>
      <table class="nb-sa-table">
        <thead><tr><th>Metrik</th><th>Nilai</th></tr></thead>
        <tbody>
          <tr><td>Klik Event</td><td>{{ number_format($eventClicks) }}</td></tr>
          <tr><td>CTA detail</td><td>{{ number_format($ctaDetail) }}</td></tr>
          <tr><td>CTA detail (dari hasil pencarian)</td><td>{{ number_format($ctaDetailSearch) }}</td></tr>
          <tr><td>CTA detail (dari halaman detail)</td><td>{{ number_format($ctaDetailDetail) }}</td></tr>
          <tr><td>Peminjaman</td><td>{{ number_format($eventBorrows) }}</td></tr>
          <tr><td>Peminjaman per CTA detail</td><td>{{ number_format($borrowFromCtaDetailRate, 2) }}%</td></tr>
          <tr><td>CTA reservasi</td><td>{{ number_format($ctaReserve) }}</td></tr>
          <tr><td>CTA reservasi (dari hasil pencarian)</td><td>{{ number_format($ctaReserveSearch) }}</td></tr>
          <tr><td>CTA reservasi (dari halaman detail)</td><td>{{ number_format($ctaReserveDetail) }}</td></tr>
          <tr><td>Reservasi</td><td>{{ number_format($eventReserves) }}</td></tr>
          <tr><td>Reservasi per CTA reservasi</td><td>{{ number_format($reserveFromCtaReserveRate, 2) }}%</td></tr>
        </tbody>
      </table>
    </div>
    <div class="nb-sa-card">
      <div class="nb-sa-title" style="font-size:14px;">Status Antrian Hasil Nihil</div>
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
      <div class="nb-sa-title" style="font-size:14px;">Alur Sinonim</div>
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

  <div class="nb-sa-card">
    <div class="nb-sa-title" style="font-size:14px;">Corong CTA Per Cabang</div>
    <table class="nb-sa-table">
      <thead>
        <tr>
          <th>Cabang</th>
          <th>Klik</th>
          <th>CTA Detail</th>
          <th>CTA Reservasi</th>
          <th>Peminjaman</th>
          <th>Reservasi</th>
          <th>Rasio CTA Detail</th>
          <th>Peminjaman/CTA Detail</th>
          <th>Reservasi/CTA Reservasi</th>
        </tr>
      </thead>
      <tbody>
        @forelse(collect($branchFunnel ?? []) as $row)
          <tr>
            <td>{{ $row['branch_name'] ?? 'Tanpa Cabang' }}</td>
            <td>{{ number_format((int) ($row['clicks'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($row['cta_detail'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($row['cta_reserve'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($row['borrows'] ?? 0)) }}</td>
            <td>{{ number_format((int) ($row['reserves'] ?? 0)) }}</td>
            <td>{{ number_format((float) ($row['cta_detail_rate_pct'] ?? 0), 2) }}%</td>
            <td>{{ number_format((float) ($row['borrow_per_cta_detail_pct'] ?? 0), 2) }}%</td>
            <td>{{ number_format((float) ($row['reserve_per_cta_reserve_pct'] ?? 0), 2) }}%</td>
          </tr>
        @empty
          <tr><td colspan="9">Belum ada data CTA per cabang.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="nb-sa-card">
    <div class="nb-sa-title" style="font-size:14px;">Pemisahan Sumber CTA</div>
    <table class="nb-sa-table">
      <thead>
        <tr>
          <th>Corong</th>
          <th>Total</th>
          <th>Hasil Pencarian</th>
          <th>Porsi Hasil Pencarian</th>
          <th>Halaman Detail</th>
          <th>Porsi Halaman Detail</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>CTA Detail</td>
          <td>{{ number_format((int) ($ctaDetailSplit['total'] ?? 0)) }}</td>
          <td>{{ number_format((int) ($ctaDetailSplit['search'] ?? 0)) }}</td>
          <td>{{ number_format((float) ($ctaDetailSplit['search_share_pct'] ?? 0), 2) }}%</td>
          <td>{{ number_format((int) ($ctaDetailSplit['detail'] ?? 0)) }}</td>
          <td>{{ number_format((float) ($ctaDetailSplit['detail_share_pct'] ?? 0), 2) }}%</td>
        </tr>
        <tr>
          <td>CTA Reservasi</td>
          <td>{{ number_format((int) ($ctaReserveSplit['total'] ?? 0)) }}</td>
          <td>{{ number_format((int) ($ctaReserveSplit['search'] ?? 0)) }}</td>
          <td>{{ number_format((float) ($ctaReserveSplit['search_share_pct'] ?? 0), 2) }}%</td>
          <td>{{ number_format((int) ($ctaReserveSplit['detail'] ?? 0)) }}</td>
          <td>{{ number_format((float) ($ctaReserveSplit['detail_share_pct'] ?? 0), 2) }}%</td>
        </tr>
      </tbody>
    </table>
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
  const eventLabels = @json($eventTrendLabels->all());
  const ctaDetailSeries = @json($eventTrendCtaDetail->all());
  const ctaReserveSeries = @json($eventTrendCtaReserve->all());
  const ctaDetailSearchSeries = @json($eventTrendCtaDetailSearch->all());
  const ctaDetailDetailSeries = @json($eventTrendCtaDetailDetail->all());
  const ctaReserveSearchSeries = @json($eventTrendCtaReserveSearch->all());
  const ctaReserveDetailSeries = @json($eventTrendCtaReserveDetail->all());
  const borrowSeries = @json($eventTrendBorrows->all());
  const reserveSeries = @json($eventTrendReserves->all());

  const useEventSeries = Array.isArray(eventLabels) && eventLabels.length === labels.length && eventLabels.join('|') === labels.join('|');
  new Chart(el.getContext('2d'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'Total Pencarian', data: total, borderColor: '#1f6feb', backgroundColor: 'rgba(31,111,235,.15)', fill: true, tension: 0.3 },
        { label: 'Hasil Nihil', data: zero, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.1)', fill: true, tension: 0.3 },
        ...(useEventSeries ? [
          { label: 'CTA Detail', data: ctaDetailSeries, borderColor: '#0ea5e9', borderDash: [6, 4], backgroundColor: 'transparent', fill: false, tension: 0.3 },
          { label: 'CTA Reservasi', data: ctaReserveSeries, borderColor: '#f59e0b', borderDash: [6, 4], backgroundColor: 'transparent', fill: false, tension: 0.3 },
          { label: 'CTA Detail (Hasil Pencarian)', data: ctaDetailSearchSeries, borderColor: '#2563eb', borderDash: [2, 3], backgroundColor: 'transparent', fill: false, tension: 0.3 },
          { label: 'CTA Detail (Detail)', data: ctaDetailDetailSeries, borderColor: '#1d4ed8', borderDash: [2, 3], backgroundColor: 'transparent', fill: false, tension: 0.3 },
          { label: 'CTA Reservasi (Hasil Pencarian)', data: ctaReserveSearchSeries, borderColor: '#d97706', borderDash: [2, 3], backgroundColor: 'transparent', fill: false, tension: 0.3 },
          { label: 'CTA Reservasi (Halaman Detail)', data: ctaReserveDetailSeries, borderColor: '#b45309', borderDash: [2, 3], backgroundColor: 'transparent', fill: false, tension: 0.3 },
          { label: 'Event Peminjaman', data: borrowSeries, borderColor: '#16a34a', backgroundColor: 'transparent', fill: false, tension: 0.3 },
          { label: 'Event Reservasi', data: reserveSeries, borderColor: '#7c3aed', backgroundColor: 'transparent', fill: false, tension: 0.3 },
        ] : []),
      ],
    },
    options: { plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true } } },
  });
})();
</script>
@endsection
