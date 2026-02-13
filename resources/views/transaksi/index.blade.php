@extends('layouts.notobuku')

@section('title','Transaksi • NOTOBUKU')

@section('content')
@php
  $links = [
    ['title'=>'Dashboard Sirkulasi','desc'=>'Ringkasan pinjam/kembali, open, dan keterlambatan.','href'=>route('transaksi.dashboard'),'tone'=>'blue','hint'=>'Ringkasan'],
    ['title'=>'Pinjam','desc'=>'Peminjaman via pilih member + scan barcode eksemplar.','href'=>route('transaksi.index'),'tone'=>'green','hint'=>'Aksi'],
    ['title'=>'Kembali','desc'=>'Pengembalian cepat via scan barcode eksemplar.','href'=>route('transaksi.kembali.form'),'tone'=>'blue','hint'=>'Cepat'],
    ['title'=>'Perpanjang','desc'=>'Perpanjangan jatuh tempo dengan catatan opsional.','href'=>route('transaksi.perpanjang.form'),'tone'=>'green','hint'=>'Extend'],
    ['title'=>'Riwayat','desc'=>'Audit trail transaksi + pengelolaan denda keterlambatan.','href'=>route('transaksi.riwayat'),'tone'=>'blue','hint'=>'Audit'],
    ['title'=>'Denda','desc'=>'Endpoint denda (UI menyusul) — akses dari tab Riwayat > Denda.','href'=>route('transaksi.riwayat', ['tab'=>'denda']),'tone'=>'green','hint'=>'Step'],
  ];

  $nav = [
    ['label'=>'Pinjam','href'=>route('transaksi.index')],
    ['label'=>'Kembali','href'=>route('transaksi.kembali.form')],
    ['label'=>'Perpanjang','href'=>route('transaksi.perpanjang.form')],
    ['label'=>'Riwayat','href'=>route('transaksi.riwayat')],
    ['label'=>'Dashboard','href'=>route('transaksi.dashboard')],
  ];
@endphp

<style>
  .tx-wrap{ max-width:1100px; margin:0 auto; }
  .tx-hero{
    border:1px solid var(--nb-border);
    border-radius:24px;
    overflow:hidden;
    background:
      radial-gradient(900px 240px at 18% 0%, rgba(46,204,113,.18), rgba(46,204,113,0)),
      radial-gradient(900px 240px at 72% 10%, rgba(30,136,229,.18), rgba(30,136,229,0)),
      linear-gradient(135deg, rgba(17,24,39,.04), rgba(17,24,39,0));
    box-shadow: var(--nb-shadow-soft);
  }
  .tx-top{
    padding:18px 18px 12px 18px;
    border-bottom:1px solid var(--nb-border);
  }
  .tx-title{ font-weight:1000; letter-spacing:.2px; color:var(--nb-text); font-size:22px; }
  .tx-sub{ margin-top:6px; color:var(--nb-muted); font-size:14px; line-height:1.5; }
  .tx-actions{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; justify-content:flex-end; }
  .tx-nav{
    display:flex; gap:8px; flex-wrap:wrap; align-items:center;
    padding:12px 18px;
  }
  .tx-chip{
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 12px; border-radius:999px;
    border:1px solid var(--nb-border);
    background: var(--nb-surface);
    box-shadow: var(--nb-shadow-soft);
    font-weight:900; font-size:13px;
    text-decoration:none;
    color:var(--nb-text);
  }
  .tx-chip .dot{
    width:10px; height:10px; border-radius:999px;
    background: linear-gradient(180deg, var(--nb-blue), var(--nb-green));
  }

  .tx-grid{ display:grid; gap:12px; padding:16px 18px 18px; grid-template-columns: repeat(3, 1fr); }
  .tx-card{
    display:block; text-decoration:none;
    border:1px solid var(--nb-border);
    background: var(--nb-surface);
    border-radius:22px;
    overflow:hidden;
    box-shadow: var(--nb-shadow-soft);
    transition: transform .12s ease, opacity .12s ease;
  }
  .tx-card:hover{ transform: translateY(-1px); opacity:.98; }
  .tx-card .pad{ padding:14px; }
  .tx-badge{
    display:inline-flex; align-items:center; gap:8px;
    padding:7px 10px; border-radius:999px;
    border:1px solid var(--nb-border);
    font-size:12px; font-weight:1000;
    color: var(--nb-text);
    background: rgba(31,58,95,.06);
  }
  .tx-badge .b-dot{ width:9px; height:9px; border-radius:999px; background: var(--nb-blue); }
  .tx-badge.green{ background: rgba(46,204,113,.10); }
  .tx-badge.green .b-dot{ background: var(--nb-green); }

  .tx-name{ margin-top:10px; font-weight:1000; letter-spacing:.1px; color:var(--nb-text); font-size:16px; }
  .tx-desc{ margin-top:6px; color:var(--nb-muted); font-size:13px; line-height:1.45; }

  .tx-path{ margin-top:12px; display:flex; align-items:center; justify-content:space-between; gap:10px; }
  .tx-path .left{ color:var(--nb-muted); font-weight:900; font-size:12px; }
  .tx-path .right{ font-weight:1000; font-size:12px; }
  .tx-bar{ height:6px; border-top:1px solid rgba(0,0,0,.04); }
  .tx-bar.blue{ background: linear-gradient(90deg, rgba(30,136,229,.45), rgba(30,136,229,.10)); }
  .tx-bar.green{ background: linear-gradient(90deg, rgba(46,204,113,.55), rgba(46,204,113,.12)); }

  @media(max-width: 980px){ .tx-grid{ grid-template-columns: repeat(2,1fr);} }
  @media(max-width: 560px){ .tx-grid{ grid-template-columns: 1fr;} .tx-actions{ justify-content:flex-start; } }
</style>

<div class="tx-wrap">

  <div class="tx-hero">
    <div class="tx-top">
      <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <div style="min-width:260px;">
          <div class="text-xs font-black tracking-[.14em] text-[var(--nb-muted)]">SIRKULASI</div>
          <div class="tx-title">Transaksi</div>
          <div class="tx-sub">
            Pilih modul transaksi di bawah. Gunakan <b>Ctrl + K</b> untuk pencarian cepat (barcode / kode transaksi).
          </div>
        </div>

        <div class="tx-actions">
          <a class="nb-btn nb-btn-primary" href="{{ route('transaksi.index') }}">Mulai Pinjam</a>
          <button class="nb-btn" type="button" data-nb-open-search>
            Pencarian Cepat <span class="ml-1 opacity-70 text-xs font-black">Ctrl K</span>
          </button>
          <a class="nb-btn" href="{{ route('transaksi.dashboard') }}">Dashboard</a>
        </div>
      </div>
    </div>

    <div class="tx-nav">
      <span class="text-xs font-black text-[var(--nb-muted)] tracking-wider">AKSES CEPAT</span>
      @foreach($nav as $n)
        <a class="tx-chip" href="{{ $n['href'] }}"><span class="dot"></span>{{ $n['label'] }}</a>
      @endforeach
    </div>

    <div class="tx-grid">
      @foreach($links as $m)
        @php $isGreen = $m['tone']==='green'; @endphp
        <a class="tx-card" href="{{ $m['href'] }}">
          <div class="pad">
            <span class="tx-badge {{ $isGreen ? 'green' : '' }}">
              <span class="b-dot"></span>{{ $m['hint'] }}
            </span>

            <div class="tx-name">{{ $m['title'] }}</div>
            <div class="tx-desc">{{ $m['desc'] }}</div>

            <div class="tx-path">
              <div class="left">Buka modul →</div>
              <div class="right" style="color:{{ $isGreen ? 'var(--nb-green)' : 'var(--nb-blue)' }}">
                {{ parse_url($m['href'], PHP_URL_PATH) ?: '/' }}
              </div>
            </div>
          </div>
          <div class="tx-bar {{ $isGreen ? 'green' : 'blue' }}"></div>
        </a>
      @endforeach
    </div>
  </div>

</div>
@endsection
