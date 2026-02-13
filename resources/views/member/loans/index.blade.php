{{-- resources/views/member/loans/index.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Pinjaman Saya • NOTOBUKU')

@section('content')
@php
  $tabs = [
    'aktif'   => ['label' => 'Aktif',   'tone' => 'active'],
    'overdue' => ['label' => 'Overdue', 'tone' => 'overdue'],
    'selesai' => ['label' => 'Selesai', 'tone' => 'done'],
    'semua'   => ['label' => 'Semua',   'tone' => 'any'],
  ];

  $active = $filter ?? request('filter', 'aktif');
  $rows = $rows ?? collect();
  $maxItems = (int)config('notobuku.loans.max_items', 3);
  if ($maxItems <= 0) $maxItems = 3;
  $maxRenewals = (int)config('notobuku.loans.max_renewals', 2);
  if ($maxRenewals <= 0) $maxRenewals = 2;

  $fmtDate = function ($date) {
    if (!$date) return '—';
    try { return \Carbon\Carbon::parse($date)->format('d M Y'); } catch (\Throwable $e) { return '—'; }
  };

  $statusMeta = function ($r) {
    $itemsActive  = (int)($r->items_active ?? 0);
    $itemsOverdue = (int)($r->items_overdue ?? 0);

    $status = 'Selesai';
    $toneKey = 'done';

    if ($itemsActive > 0) {
      if ($itemsOverdue > 0) { $status = 'Overdue'; $toneKey = 'overdue'; }
      else { $status = 'Aktif'; $toneKey = 'active'; }
    }

    return [$status, $toneKey, $itemsActive, $itemsOverdue];
  };

  /**
   * titles -> array judul bersih
   * Aman dari kasus separator aneh & item kosong.
   */
  $parseTitles = function ($titles) {
    $t = trim((string)$titles);
    if ($t === '') return [];

    // ubah separator umum jadi newline
    $normalized = $t;
    $normalized = str_replace(" • ", "\n", $normalized);
    $normalized = str_replace(["\r\n", "\n"], "\n", $normalized);
    $normalized = str_replace([" | ", "|", ";"], "\n", $normalized);
    // HATI-HATI: koma bisa ada di judul. Tapi tetap kita dukung, dengan syarat " , " (ada spasi) saja.
    $normalized = str_replace(" , ", "\n", $normalized);

    // rapikan newline ganda
    $normalized = preg_replace("/\n+/", "\n", $normalized);

    $parts = array_values(array_filter(array_map('trim', explode("\n", $normalized))));

    // buang item yang cuma bullet / tanda baca
    $parts = array_values(array_filter($parts, function ($x) {
      $x = trim($x);
      if ($x === '' || $x === '•' || $x === '-' || $x === '—') return false;
      return true;
    }));

    // remove consecutive duplicates
    $dedup = [];
    foreach ($parts as $p) {
      if (empty($dedup) || end($dedup) !== $p) $dedup[] = $p;
    }
    return $dedup;
  };
@endphp

<style>
  :root{
    --nb-bg:        #F6F7FB;
    --nb-card:      #FFFFFF;         /* NETRAL */
    --nb-surface:   #F9FAFB;
    --nb-border:    rgba(17,24,39,.10);
    --nb-shadow:    0 10px 22px rgba(17,24,39,.06);

    --nb-text:      rgba(17,24,39,.92);
    --nb-subtext:   rgba(55,65,81,.74);
    --nb-muted:     rgba(55,65,81,.62);

    --nb-primary:   #1E88E5;
    --nb-primary-2: #1565C0;

    /* Status pill only (card tetap netral) */
    --st-active-fg:  #1E88E5;
    --st-active-bg:  rgba(30,136,229,.12);
    --st-active-bd:  rgba(30,136,229,.35);

    --st-overdue-fg: #E65100;
    --st-overdue-bg: rgba(251,140,0,.16);
    --st-overdue-bd: rgba(251,140,0,.40);

    --st-done-fg:    #2E7D32;
    --st-done-bg:    rgba(46,125,50,.14);
    --st-done-bd:    rgba(46,125,50,.35);

    --st-any-fg:     rgba(55,65,81,.90);
    --st-any-bg:     rgba(107,114,128,.10);
    --st-any-bd:     rgba(107,114,128,.24);

    --nb-bullet:     rgba(156,163,175,.95);
  }

  html.dark, body.dark, .dark{
    --nb-bg:        #0B1220;
    --nb-card:      #0F172A;         /* NETRAL dark */
    --nb-surface:   rgba(255,255,255,.04);
    --nb-border:    rgba(255,255,255,.12);
    --nb-shadow:    0 10px 22px rgba(0,0,0,.28);

    --nb-text:      rgba(229,231,235,.92);
    --nb-subtext:   rgba(229,231,235,.76);
    --nb-muted:     rgba(229,231,235,.62);

    --st-active-bg:  rgba(30,136,229,.18);
    --st-overdue-bg: rgba(251,140,0,.18);
    --st-done-bg:    rgba(46,125,50,.18);

    --nb-bullet:     rgba(156,163,175,.85);
  }

  .nb-page{ overflow-x:hidden; background: var(--nb-bg); }

  .nb-wrap{
    max-width: 980px;
    margin: 0 auto;
    padding: 12px 14px 22px;
  }

  .nb-panel{
    border: 1px solid var(--nb-border);
    background: var(--nb-card);
    border-radius: 22px;
    overflow: hidden;
    box-shadow: var(--nb-shadow);
  }
  .nb-panel-topbar{ height:6px; background: linear-gradient(90deg, rgba(30,136,229,.9), rgba(46,125,50,.85)); }

  .nb-title{ font-size: 18px; font-weight: 600; color: var(--nb-text); }
  .nb-subtitle{ font-size: 13px; font-weight: 400; color: var(--nb-subtext); margin-top: 4px; }
  .nb-section-title{ font-size: 14px; font-weight: 600; color: var(--nb-text); }
  .nb-muted{ font-size: 12.5px; font-weight: 400; color: var(--nb-muted); }

  .nb-actions{ display:flex; gap:10px; flex-wrap:wrap; }
  .nb-btn{
    display:inline-flex; align-items:center; justify-content:center;
    padding: 10px 12px;
    border-radius: 14px;
    border: 1px solid var(--nb-border);
    background: var(--nb-surface);
    font-size: 13px;
    font-weight: 500;
    color: var(--nb-text);
    text-decoration:none;
  }
  .nb-btn-primary{
    background: var(--nb-primary);
    border-color: rgba(30,136,229,.45);
    color:#fff;
  }
  .nb-btn-primary:hover{ background: var(--nb-primary-2); }

  .nb-tabs{
    padding:12px 16px;
    display:flex; gap:10px; flex-wrap:wrap;
    border-bottom: 1px solid var(--nb-border);
    background: linear-gradient(180deg, rgba(0,0,0,0), rgba(0,0,0,.01));
  }
  html.dark .nb-tabs, body.dark .nb-tabs, .dark .nb-tabs{
    background: linear-gradient(180deg, rgba(255,255,255,0), rgba(255,255,255,.02));
  }
  .nb-tab{
    display:inline-flex; align-items:center;
    padding: 9px 12px;
    border-radius: 14px;
    border: 1px solid var(--nb-border);
    background: var(--nb-surface);
    font-size: 13px;
    font-weight: 500;
    color: var(--nb-text);
    text-decoration:none;
  }

  /* Grid list: 2 columns desktop */
  .nb-list{
    padding: 14px;
    display:grid;
    grid-template-columns: 1fr;
    gap: 12px;
  }
  @media (min-width: 900px){
    .nb-list{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }

  .nb-card{
    border: 1px solid var(--nb-border);
    background: var(--nb-card);  /* NETRAL (bukan warna lain) */
    border-radius: 20px;
    padding: 14px;
    box-shadow: 0 8px 18px rgba(17,24,39,.06);
    display:flex;
    flex-direction:column;
    min-width:0;
  }
  html.dark .nb-card, body.dark .nb-card, .dark .nb-card{
    box-shadow: 0 8px 18px rgba(0,0,0,.30);
  }

  .nb-card-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    min-width:0;
  }

  .nb-card-title{
    font-size: 14.5px;
    font-weight: 600;
    color: var(--nb-text);
    line-height: 1.2;
  }

  .nb-card-sub{
    margin-top: 4px;
    font-size: 12.5px;
    font-weight: 400;
    color: var(--nb-subtext);
    line-height: 1.25;
  }

  /* Status pill ONLY */
  .nb-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
    flex-shrink:0;
    border: 1px solid var(--st-any-bd);
    background: var(--st-any-bg);
    color: var(--st-any-fg);
  }
  .nb-pill.is-active{  border-color: var(--st-active-bd);  background: var(--st-active-bg);  color: var(--st-active-fg); }
  .nb-pill.is-overdue{ border-color: var(--st-overdue-bd); background: var(--st-overdue-bg); color: var(--st-overdue-fg); }
  .nb-pill.is-done{    border-color: var(--st-done-bd);    background: var(--st-done-bg);    color: var(--st-done-fg); }

  /* Collection list */
  .nb-collection{
    margin-top: 12px;
    border: 1px solid var(--nb-border);
    border-radius: 16px;
    padding: 10px 12px;
    background: var(--nb-surface);
  }

  .nb-bullets{
    display:flex;
    flex-direction:column;
    gap:6px;
  }

  .nb-bullet-row{
    display:flex;
    gap:10px;
    min-width:0;
  }
  .nb-bullet-dot{
    color: var(--nb-bullet);
    line-height: 1.35;
    margin-top: 1px;
    flex-shrink:0;
  }
  .nb-bullet-text{
    font-size: 13px;
    font-weight: 400;
    color: var(--nb-text);
    line-height: 1.35;

    display:-webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow:hidden;
  }

  .nb-card-actions{
    margin-top: auto;
    padding-top: 12px;
    display:flex;
    justify-content:flex-end;
    gap:10px;
    flex-wrap:wrap;
  }
  .nb-btn-sm{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding: 10px 12px;
    border-radius: 14px;
    border: 1px solid var(--nb-border);
    background: var(--nb-surface);
    font-size: 13px;
    font-weight: 500;
    color: var(--nb-text);
    text-decoration:none;
    cursor:pointer;
  }
  .nb-btn-sm.primary{
    background: var(--nb-primary);
    border-color: rgba(30,136,229,.45);
    color:#fff;
  }
  .nb-btn-sm.primary:hover{ background: var(--nb-primary-2); }

  .nb-warn{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 8px;
    border-radius:999px;
    font-size:11.5px;
    font-weight:600;
    color:#ef6c00;
    background: rgba(251,140,0,.14);
    border:1px solid rgba(251,140,0,.35);
  }
</style>

<div class="nb-page">
  <div class="nb-wrap">
    <div class="nb-panel">
      <div class="nb-panel-topbar"></div>

      {{-- Header --}}
      <div style="padding:16px 16px 12px; border-bottom:1px solid var(--nb-border); display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <div style="min-width:0;">
          <div class="nb-title">Pinjaman Saya</div>
          <div class="nb-subtitle">Daftar transaksi peminjaman.</div>
        </div>

        <div class="nb-actions">
          <a class="nb-btn" href="{{ route('member.dashboard') }}">Dashboard</a>
          <a class="nb-btn nb-btn-primary" href="{{ route('katalog.index') }}">Cari Buku</a>
        </div>
      </div>

      {{-- Tabs --}}
      <div class="nb-tabs">
        @foreach($tabs as $key => $t)
          @php
            $isOn = ($active === $key);

            // tab highlight ringan, tidak mengubah card
            $bg = 'var(--nb-surface)';
            $bd = 'var(--nb-border)';
            $fg = 'var(--nb-text)';

            if ($isOn) {
              if ($t['tone'] === 'active')  { $bg='var(--st-active-bg)';  $bd='var(--st-active-bd)';  $fg='var(--st-active-fg)'; }
              if ($t['tone'] === 'overdue') { $bg='var(--st-overdue-bg)'; $bd='var(--st-overdue-bd)'; $fg='var(--st-overdue-fg)'; }
              if ($t['tone'] === 'done')    { $bg='var(--st-done-bg)';    $bd='var(--st-done-bd)';    $fg='var(--st-done-fg)'; }
              if ($t['tone'] === 'any')     { $bg='var(--st-any-bg)';     $bd='var(--st-any-bd)';     $fg='var(--st-any-fg)'; }
            }
          @endphp

          <a class="nb-tab"
             href="{{ route('member.pinjaman', ['filter' => $key]) }}"
             style="background:{{ $bg }}; border-color:{{ $bd }}; color:{{ $fg }};">
            {{ $t['label'] }}
          </a>
        @endforeach
      </div>

      {{-- Section header --}}
      <div style="padding:14px 16px; border-bottom:1px solid var(--nb-border); display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <div class="nb-section-title">Daftar Transaksi</div>
        @if(method_exists($rows, 'total'))
          <div class="nb-muted">{{ $rows->total() }} transaksi</div>
        @endif
      </div>

      {{-- Empty --}}
      @if($rows->count() === 0)
        <div style="padding:18px 16px;">
          <div class="nb-muted">Belum ada transaksi pada tab ini.</div>
        </div>
      @else
        <div class="nb-list">
          @foreach($rows as $r)
            @php
              [$status, $toneKey, $itemsActive, $itemsOverdue] = $statusMeta($r);
              $nearest = $r->nearest_due ? \Carbon\Carbon::parse($r->nearest_due) : null;
              $nearLimit = $itemsActive > 0 && $itemsActive >= max(1, $maxItems - 1);
              $renewMax = (int)($r->max_renew_count ?? 0);
              $renewRemain = max(0, $maxRenewals - $renewMax);
              $nearRenew = $itemsActive > 0 && $renewMax >= max(1, $maxRenewals - 1);

              $titlesArr = $parseTitles($r->titles ?? '');

              // kalau data titles kosong, minimal 1 item placeholder (tanpa bullet berdiri sendiri)
              $listTitles = count($titlesArr) ? $titlesArr : ['—'];

              $countBooks = max(1, count($titlesArr));

              $maxShow = 4;
              $shown = array_slice($listTitles, 0, $maxShow);
              $more = max(0, count($listTitles) - $maxShow);

              $pillClass = 'nb-pill';
              if ($toneKey === 'active')  $pillClass .= ' is-active';
              if ($toneKey === 'overdue') $pillClass .= ' is-overdue';
              if ($toneKey === 'done')    $pillClass .= ' is-done';
            @endphp

            <div class="nb-card">
              <div class="nb-card-head">
                <div style="min-width:0;">
                  <div class="nb-card-title">{{ $countBooks }} koleksi</div>

                  {{-- Tanggal + jatuh tempo tampil SEKALI di sini --}}
                  <div class="nb-card-sub">
                    {{ $fmtDate($r->created_at) }} • Jatuh tempo {{ $nearest ? $nearest->format('d M Y') : '—' }}
                  </div>
                </div>

                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px;">
                  <span class="{{ $pillClass }}">{{ $status }}</span>
                  @if($nearLimit && $itemsOverdue === 0)
                    <span class="nb-warn">Hampir penuh: {{ $itemsActive }}/{{ $maxItems }}</span>
                  @endif
                  @if($itemsActive > 0)
                    <span class="nb-warn"
                          title="Maks {{ $maxRenewals }}x per item"
                          style="background: rgba(30,136,229,.12); border-color: rgba(30,136,229,.35); color: rgba(21,101,192,1);">
                      Perpanjang: {{ $renewMax }}/{{ $maxRenewals }} • Sisa {{ $renewRemain }}
                    </span>
                  @endif
                </div>
              </div>

              <div class="nb-collection">
                <div class="nb-bullets">
                  @foreach($shown as $t)
                    <div class="nb-bullet-row">
                      <div class="nb-bullet-dot">•</div>
                      <div class="nb-bullet-text" title="{{ $t }}">{{ $t }}</div>
                    </div>
                  @endforeach

                  @if($more > 0)
                    <div class="nb-bullet-row">
                      <div class="nb-bullet-dot">•</div>
                      <div class="nb-bullet-text">+{{ $more }} koleksi lainnya</div>
                    </div>
                  @endif
                </div>
              </div>

              {{-- Tidak ada chip tanggal/jatuh tempo lagi (hapus duplikasi) --}}
              <div class="nb-card-actions">
                <a class="nb-btn-sm" href="{{ route('member.pinjaman.detail', ['id' => (int)$r->id]) }}">Detail</a>

                @if($itemsActive > 0 && $itemsOverdue === 0)
                  <form method="POST" action="{{ route('member.pinjaman.extend', ['id' => (int)$r->id]) }}">
                    @csrf
                    <button type="submit" class="nb-btn-sm primary">Perpanjang</button>
                  </form>
                @endif
              </div>
            </div>
          @endforeach
        </div>

        @if(method_exists($rows, 'links'))
          <div style="padding:12px 16px 16px;">
            {{ $rows->links() }}
          </div>
        @endif
      @endif
    </div>
  </div>
</div>
@endsection



