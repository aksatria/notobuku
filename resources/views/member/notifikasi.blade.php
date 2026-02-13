{{-- resources/views/member/notifikasi.blade.php --}}
@extends('layouts.member')

@section('title','Notifikasi • NOTOBUKU')
@section('member_title','Notifikasi')
@section('member_subtitle','Peringatan jatuh tempo, reservasi, dan info penting lainnya.')

@section('member.content')
@php
  $notifications = $notifications ?? collect();

  $fmt = function($d){
    if(!$d) return '—';
    try { return \Carbon\Carbon::parse($d)->format('d M Y, H:i'); } catch (\Throwable $e) { return '—'; }
  };

  $isUnread = function($n){
    if (isset($n->read_at)) return empty($n->read_at);
    if (isset($n->is_read)) return !$n->is_read;
    return false;
  };

  $tone = function($n) use ($isUnread){
    // heuristik ringan dari type/konten
    $type = strtolower((string)($n->type ?? ''));
    $msg = strtolower((string)($n->message ?? $n->title ?? ''));

    if (str_contains($type,'overdue') || str_contains($msg,'telat') || str_contains($msg,'overdue')) return ['pill'=>'red','tint'=>'tint-orange','label'=>'Penting'];
    if (str_contains($type,'reservation') || str_contains($msg,'reservasi')) return ['pill'=>'green','tint'=>'tint-green','label'=>'Reservasi'];
    return ['pill'=>$isUnread($n)?'blue':'slate','tint'=>$isUnread($n)?'tint-blue':'','label'=>$isUnread($n)?'Baru':'Info'];
  };
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

    <div class="nb-section">
      <div class="nb-toolbar">
        <div style="min-width:0;">
          <div class="nb-h2">Daftar Notifikasi</div>
          <div class="nb-hint">
            {{ method_exists($notifications,'total') ? $notifications->total() : $notifications->count() }} notifikasi
          </div>
        </div>

        <form method="POST" action="{{ route('member.notifikasi.read_all') }}">
          @csrf
          <button type="submit" class="nb-btn primary">Tandai semua dibaca</button>
        </form>
      </div>
    </div>

    @if($notifications->isEmpty())
      <div class="nb-section">
        <div class="nb-h2">Belum ada notifikasi</div>
        <div class="nb-hint">Jika ada pinjaman jatuh tempo atau reservasi berubah status, akan muncul di sini.</div>
      </div>
    @else
      <div class="nb-section">
        <div class="nb-grid">
          @foreach($notifications as $n)
            @php
              $unread = $isUnread($n);
              $t = $tone($n);
              $title = $n->title ?? 'Notifikasi';
              $message = $n->message ?? ($n->body ?? '');
              $time = $fmt($n->created_at ?? null);
            @endphp

            <div class="nb-card {{ $t['tint'] }}">
              <div class="nb-card-top">
                <div style="min-width:0;">
                  <div class="nb-card-title">{{ $title }}</div>
                  <div class="nb-card-meta">{{ $time }}</div>
                </div>
                <span class="nb-pill {{ $t['pill'] }}">{{ $t['label'] }}</span>
              </div>

              @if(!empty($message))
                <div class="nb-hint" style="margin-top:10px; font-weight:600;">
                  {{ $message }}
                </div>
              @endif

              <div class="nb-actions-row">
                @if($unread)
                  <form method="POST" action="{{ route('member.notifikasi.read', $n->id) }}">
                    @csrf
                    <button type="submit" class="nb-btn">Tandai dibaca</button>
                  </form>
                @else
                  <span class="nb-pill">Sudah dibaca</span>
                @endif

                @if(!empty($n->action_url))
                  <a class="nb-btn primary" href="{{ $n->action_url }}">Buka</a>
                @endif
              </div>
            </div>
          @endforeach
        </div>

        @if(method_exists($notifications,'links'))
          <div style="margin-top:16px;">
            {{ $notifications->links() }}
          </div>
        @endif
      </div>
    @endif
  </div>
</div>
@endsection
