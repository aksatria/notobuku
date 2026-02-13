{{-- resources/views/beranda.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Beranda • NOTOBUKU')

@section('content')
@php
  $user = auth()->user();
  $name = $user->name ?? 'Pengguna';
  $role = $user->role ?? 'member';
  $roleLabel = $role === 'super_admin' ? 'Super Admin' : ucfirst($role);

  $mode = $mode ?? ($role === 'member' ? 'member' : 'admin');
  $isMember = $mode === 'member';

  $activeBranchName = $activeBranchName ?? null;

  // Stats dari controller (fallback aman)
  $stats = $stats ?? [
    ['label'=>'Total Buku','value'=>'—','tone'=>'blue','icon'=>'#nb-icon-book','hint'=>'Koleksi terdaftar','dot'=>'blue'],
    ['label'=>'Sedang Dipinjam','value'=>'—','tone'=>'green','icon'=>'#nb-icon-rotate','hint'=>'Transaksi aktif','dot'=>'green'],
    ['label'=>'Anggota','value'=>'—','tone'=>'indigo','icon'=>'#nb-icon-users','hint'=>'Member terdaftar','dot'=>'indigo'],
    ['label'=>'Postingan','value'=>'—','tone'=>'teal','icon'=>'#nb-icon-chat','hint'=>'Aktivitas komunitas','dot'=>'teal'],
  ];

  // Quick links role-based (hindari route yang tidak diizinkan member)
  if ($isMember) {
    $links = [
      ['title'=>'Katalog', 'desc'=>'Cari buku & ketersediaan.', 'url'=>route('katalog.index'), 'icon'=>'#nb-icon-book'],
      ['title'=>'Pinjaman', 'desc'=>'Pinjaman aktif & riwayat.', 'url'=>route('member.pinjaman'), 'icon'=>'#nb-icon-rotate'],
      ['title'=>'Reservasi', 'desc'=>'Ajukan & pantau reservasi.', 'url'=>route('member.reservasi'), 'icon'=>'#nb-icon-clock'],
      ['title'=>'Notifikasi', 'desc'=>'Pengingat & informasi.', 'url'=>route('member.notifikasi'), 'icon'=>'#nb-icon-bell'],
      ['title'=>'Profil', 'desc'=>'Data akun & pengaturan.', 'url'=>route('member.profil'), 'icon'=>'#nb-icon-users'],
    ];
  } else {
    $links = [
      ['title'=>'Transaksi', 'desc'=>'Pinjam / kembali / perpanjang.', 'url'=>route('transaksi.index'), 'icon'=>'#nb-icon-rotate'],
      ['title'=>'Dashboard', 'desc'=>'Ringkasan transaksi & tren.', 'url'=>route('transaksi.dashboard'), 'icon'=>'#nb-icon-chart'],
      ['title'=>'Katalog', 'desc'=>'Kelola bibliografi & eksemplar.', 'url'=>route('katalog.index'), 'icon'=>'#nb-icon-book'],
      ['title'=>'Reservasi', 'desc'=>'Kelola antrian reservasi.', 'url'=>route('reservasi.index'), 'icon'=>'#nb-icon-clock'],
      ['title'=>'Anggota', 'desc'=>'Data member & aktivitas.', 'url'=>route('anggota.index'), 'icon'=>'#nb-icon-users'],
      ['title'=>'Notifikasi', 'desc'=>'Pusat notifikasi sistem.', 'url'=>route('notifikasi.index'), 'icon'=>'#nb-icon-bell'],
    ];
  }

  // Data tambahan (fallback)
  $dueSoon = $dueSoon ?? [];
  $favorite = $favorite ?? [];
  $popularTitles = $popularTitles ?? [];
  $latestTitles = $latestTitles ?? [];

  $fmtDate = function ($date) {
    if (!$date) return '-';
    try { return \Carbon\Carbon::parse($date)->format('d M Y'); } catch (\Throwable $e) { return '-'; }
  };
  $daysLeftText = function ($date) {
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

  $health = $health ?? [];
  $topTitles = $topTitles ?? [];
  $topOverdueMembers = $topOverdueMembers ?? [];
@endphp

<style>
  /* ====== (CSS kamu yang sudah ada) ====== */
  .nb-wrap{ max-width:1100px; margin:0 auto; }

  .nb-card{
    background: rgba(255,255,255,.90);
    border: 1px solid rgba(15,23,42,.08);
    border-radius: 18px;
    box-shadow: 0 12px 30px rgba(2,6,23,.06);
    overflow:hidden;
  }
  html.dark .nb-card{
    background: rgba(15,23,42,.58);
    border-color: rgba(148,163,184,.14);
    box-shadow: 0 14px 34px rgba(0,0,0,.32);
  }

  .nb-page-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
  }
  .nb-page-head-main{ min-width:0; flex:1; }

  .nb-title{
    font-size:15px;
    font-weight:700;
    letter-spacing:.2px;
    color: rgba(15,23,42,.92);
  }
  html.dark .nb-title{ color: rgba(226,232,240,.92); }

  .nb-sub{
    margin-top:4px;
    font-size:12px;
    color: rgba(100,116,139,.95);
  }
  html.dark .nb-sub{ color: rgba(148,163,184,.92); }

  .nb-badge{
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 10px;
    border-radius: 999px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.75);
    font-size:12px;
    color: rgba(15,23,42,.75);
  }
  html.dark .nb-badge{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.35);
    color: rgba(226,232,240,.78);
  }

  .nb-grid{ display:grid; gap:14px; }
  @media(min-width: 900px){ .nb-grid-2{ grid-template-columns: 1.1fr .9fr; } }
  @media(min-width: 900px){ .nb-grid-3{ grid-template-columns: 1fr 1fr 1fr; } }

  .nb-kpi{ display:grid; gap:12px; grid-template-columns: repeat(2, minmax(0,1fr)); }
  @media(min-width: 900px){ .nb-kpi{ grid-template-columns: repeat(4, minmax(0,1fr)); } }

  .nb-kpi-card{
    padding:14px;
    position:relative;
    color:white;
  }
  .nb-kpi-top{ display:flex; align-items:center; justify-content:space-between; gap:10px; }
  .nb-kpi-ico{
    width:34px; height:34px;
    display:grid; place-items:center;
    border-radius: 12px;
    background: rgba(255,255,255,.18);
  }
  .nb-kpi-ico svg{ width:18px; height:18px; fill: white; }

  .nb-kpi-label{ font-size:12px; font-weight:600; opacity:.92; }
  .nb-kpi-val{ font-size:22px; font-weight:800; margin-top:6px; letter-spacing:.2px; }
  .nb-kpi-hint{ font-size:12px; opacity:.9; margin-top:2px; }

  .tone-blue{ background: linear-gradient(135deg, rgba(59,130,246,1), rgba(37,99,235,1)); }
  .tone-green{ background: linear-gradient(135deg, rgba(34,197,94,1), rgba(22,163,74,1)); }
  .tone-indigo{ background: linear-gradient(135deg, rgba(99,102,241,1), rgba(79,70,229,1)); }
  .tone-teal{ background: linear-gradient(135deg, rgba(20,184,166,1), rgba(13,148,136,1)); }

  .nb-links{ display:grid; gap:12px; grid-template-columns: repeat(1, minmax(0,1fr)); }
  @media(min-width: 700px){ .nb-links{ grid-template-columns: repeat(2, minmax(0,1fr)); } }

  .nb-link a{
    display:flex; gap:12px; padding:14px;
    text-decoration:none;
    color: inherit;
  }
  .nb-link-ico{
    width:42px; height:42px; border-radius: 14px;
    display:grid; place-items:center;
    background: rgba(15,23,42,.04);
    border: 1px solid rgba(15,23,42,.06);
  }
  html.dark .nb-link-ico{
    background: rgba(148,163,184,.08);
    border-color: rgba(148,163,184,.12);
  }
  .nb-link-ico svg{ width:20px; height:20px; fill: rgba(15,23,42,.75); }
  html.dark .nb-link-ico svg{ fill: rgba(226,232,240,.78); }

  .nb-link-title{ font-size:13px; font-weight:800; color: rgba(15,23,42,.9); }
  html.dark .nb-link-title{ color: rgba(226,232,240,.92); }
  .nb-link-desc{ font-size:12px; color: rgba(100,116,139,.95); margin-top:2px; }
  html.dark .nb-link-desc{ color: rgba(148,163,184,.92); }

  .nb-section-title{
    font-size:13px; font-weight:800; color: rgba(15,23,42,.9);
    margin-bottom:10px;
  }
  html.dark .nb-section-title{ color: rgba(226,232,240,.92); }

  .nb-list{ padding: 12px 14px; }
  .nb-row{
    display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
    padding:10px 0;
    border-top: 1px solid rgba(15,23,42,.06);
  }
  .nb-row:first-child{ border-top:none; padding-top:0; }
  html.dark .nb-row{ border-top-color: rgba(148,163,184,.10); }

  .nb-row-title{ font-size:12px; font-weight:700; color: rgba(15,23,42,.88); }
  html.dark .nb-row-title{ color: rgba(226,232,240,.9); }
  .nb-row-sub{ font-size:12px; color: rgba(100,116,139,.95); margin-top:2px; }
  html.dark .nb-row-sub{ color: rgba(148,163,184,.9); }
  .nb-cover{ width:44px; height:64px; border-radius:8px; object-fit:cover; background: rgba(148,163,184,.18); border: 1px solid rgba(15,23,42,.08); }
  html.dark .nb-cover{ border-color: rgba(148,163,184,.18); background: rgba(15,23,42,.35); }
  .nb-row-media{ display:flex; gap:12px; align-items:center; }
  .nb-row-link{ color: inherit; text-decoration: none; }
  .nb-row-link:hover{ background: rgba(15,23,42,.03); border-radius:12px; padding-left:6px; padding-right:6px; margin-left:-6px; margin-right:-6px; }
  html.dark .nb-row-link:hover{ background: rgba(148,163,184,.08); }

  .nb-pill{
    font-size:12px;
    padding:6px 10px;
    border-radius: 999px;
    background: rgba(15,23,42,.05);
    border: 1px solid rgba(15,23,42,.06);
    color: rgba(15,23,42,.78);
    white-space:nowrap;
  }
  html.dark .nb-pill{
    background: rgba(148,163,184,.08);
    border-color: rgba(148,163,184,.12);
    color: rgba(226,232,240,.78);
  }
</style>

<div class="nb-wrap space-y-4">

  {{-- HEADER --}}
  <div class="nb-page-head nb-card" style="padding:14px;">
    <div class="nb-page-head-main">
      <div class="nb-title">Selamat datang, {{ $name }}</div>
      <div class="nb-sub">
        Role: <b>{{ $roleLabel }}</b>
        @if($activeBranchName)
          <span style="opacity:.6;">-</span> Cabang: <b>{{ $activeBranchName }}</b>
        @endif
        <span style="opacity:.6;">-</span> {{ now()->translatedFormat('l, d F Y') }}
      </div>
    </div>
    <div class="nb-badge">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width:16px;height:16px;fill:currentColor;">
        <use href="#nb-icon-home"></use>
      </svg>
      {{ $isMember ? 'Beranda Member' : 'Beranda Admin/Staff' }}
    </div>
  </div>

  {{-- KPI --}}
  <div class="nb-kpi">
    @foreach($stats as $s)
      <div class="nb-card nb-kpi-card tone-{{ $s['tone'] ?? 'blue' }}">
        <div class="nb-kpi-top">
          <div class="nb-kpi-label">{{ $s['label'] ?? '-' }}</div>
          <div class="nb-kpi-ico">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
              <use href="{{ $s['icon'] ?? '#nb-icon-chart' }}"></use>
            </svg>
          </div>
        </div>
        <div class="nb-kpi-val">{{ $s['value'] ?? '—' }}</div>
        <div class="nb-kpi-hint">{{ $s['hint'] ?? '' }}</div>
      </div>
    @endforeach
  </div>

  <div class="nb-grid nb-grid-2">

    {{-- QUICK LINKS --}}
    <div class="nb-card" style="padding:14px;">
      <div class="nb-section-title">Aksi cepat</div>
      <div class="nb-links">
        @foreach($links as $l)
          <div class="nb-card nb-link">
            <a href="{{ $l['url'] }}">
              <div class="nb-link-ico">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                  <use href="{{ $l['icon'] }}"></use>
                </svg>
              </div>
              <div style="min-width:0;">
                <div class="nb-link-title">{{ $l['title'] }}</div>
                <div class="nb-link-desc">{{ $l['desc'] }}</div>
              </div>
            </a>
          </div>
        @endforeach
      </div>
    </div>

    {{-- PANEL KANAN: role-based content --}}
    @if($isMember)
      {{-- MEMBER: Jatuh tempo dekat --}}
      <div class="nb-card">
        <div style="padding:14px;">
          <div class="nb-section-title">Jatuh tempo dekat</div>
          <div class="nb-sub">Pantau buku yang perlu segera dikembalikan.</div>
        </div>

        <div class="nb-list">
          @forelse($dueSoon as $row)
            @php
              $due = $row['due_date'] ?? $row['due_at'] ?? null;
              $dueText = $fmtDate($due);
              $daysLabel = $row['days_left'] ?? $row['days'] ?? $daysLeftText($due);
              if (is_numeric($daysLabel)) { $daysLabel = $daysLabel.' hari'; }
              if ($daysLabel === null || $daysLabel === '') { $daysLabel = '-'; }
            @endphp
            <div class="nb-row">
              <div style="min-width:0;">
                <div class="nb-row-title">{{ $row['title'] ?? ($row['biblio_title'] ?? 'Buku') }}</div>
                <div class="nb-row-sub">
                  Jatuh tempo:
                  <b>{{ $dueText }}</b>
                  @if(!empty($row['branch_name']))
                    <span style="opacity:.6;">-</span> {{ $row['branch_name'] }}
                  @endif
                </div>
              </div>
              <div class="nb-pill">{{ $daysLabel }}</div>
            </div>
          @empty
            <div style="padding:14px;" class="nb-sub">Tidak ada yang jatuh tempo dekat.</div>
          @endforelse
        </div>
      </div>
    @else
      {{-- ADMIN/STAFF: Health ringkas --}}
      <div class="nb-card">
        <div style="padding:14px;">
          <div class="nb-section-title">Kesehatan operasional (bulan ini)</div>
          <div class="nb-sub">Ringkasan cepat performa layanan.</div>
        </div>

        <div class="nb-list">
          <div class="nb-row">
            <div>
              <div class="nb-row-title">Return Rate</div>
              <div class="nb-row-sub">Perbandingan kembali vs pinjam</div>
            </div>
            <div class="nb-pill">{{ $health['return_rate'] ?? 0 }}%</div>
          </div>

          <div class="nb-row">
            <div>
              <div class="nb-row-title">On-time Return</div>
              <div class="nb-row-sub">Pengembalian tepat waktu</div>
            </div>
            <div class="nb-pill">{{ $health['on_time_rate'] ?? 0 }}%</div>
          </div>

          <div class="nb-row">
            <div>
              <div class="nb-row-title">Overdue Ratio</div>
              <div class="nb-row-sub">Proporsi item terlambat dari item terbuka</div>
            </div>
            <div class="nb-pill">{{ $health['overdue_ratio'] ?? 0 }}%</div>
          </div>

          <div class="nb-row">
            <div>
              <div class="nb-row-title">Open Items</div>
              <div class="nb-row-sub">Item belum kembali</div>
            </div>
            <div class="nb-pill">{{ $health['open_items'] ?? 0 }}</div>
          </div>
        </div>
      </div>
    @endif

  </div>

  {{-- SECTION BAWAH: role-based --}}
  @if($isMember)
    <div class="nb-grid nb-grid-2">
      <div class="nb-card">
        <div style="padding:14px;">
          <div class="nb-section-title">Buku populer</div>
          <div class="nb-sub">Judul yang paling sering dipinjam di perpustakaan.</div>
        </div>
        <div class="nb-list">
          @forelse($popularTitles as $p)
            @php
              $cover = $p['cover'] ?? null;
              $coverUrl = null;
              if (!empty($cover)) {
                $coverUrl = \Illuminate\Support\Str::startsWith($cover, ['http://','https://','/']) ? $cover : asset($cover);
              }
              $detailUrl = !empty($p['title_id']) ? route('katalog.show', ['id' => $p['title_id']]) : null;
            @endphp
            @if($detailUrl)
              <a class="nb-row nb-row-link" href="{{ $detailUrl }}">
            @else
              <div class="nb-row">
            @endif
              <div class="nb-row-media">
                @if($coverUrl)
                  <img class="nb-cover" src="{{ $coverUrl }}" alt="Cover">
                @else
                  <div class="nb-cover"></div>
                @endif
                <div style="min-width:0;">
                  <div class="nb-row-title">{{ $p['title'] ?? 'Judul' }}</div>
                  <div class="nb-row-sub">Dipinjam <b>{{ $p['borrow_count'] ?? $p['count'] ?? 0 }}</b> kali</div>
                </div>
              </div>
              <div class="nb-pill">Populer</div>
            @if($detailUrl)
              </a>
            @else
              </div>
            @endif
          @empty
            <div style="padding:14px;" class="nb-sub">Belum ada data buku populer.</div>
          @endforelse
        </div>
      </div>

      <div class="nb-card">
        <div style="padding:14px;">
          <div class="nb-section-title">Koleksi terbaru</div>
          <div class="nb-sub">Judul yang baru ditambahkan ke koleksi.</div>
        </div>
        <div class="nb-list">
          @forelse($latestTitles as $p)
            @php
              $cover = $p['cover'] ?? null;
              $coverUrl = null;
              if (!empty($cover)) {
                $coverUrl = \Illuminate\Support\Str::startsWith($cover, ['http://','https://','/']) ? $cover : asset($cover);
              }
              $detailUrl = !empty($p['title_id']) ? route('katalog.show', ['id' => $p['title_id']]) : null;
            @endphp
            @if($detailUrl)
              <a class="nb-row nb-row-link" href="{{ $detailUrl }}">
            @else
              <div class="nb-row">
            @endif
              <div class="nb-row-media">
                @if($coverUrl)
                  <img class="nb-cover" src="{{ $coverUrl }}" alt="Cover">
                @else
                  <div class="nb-cover"></div>
                @endif
                <div style="min-width:0;">
                  <div class="nb-row-title">{{ $p['title'] ?? 'Judul' }}</div>
                  @if(!empty($p['added_at']))
                    <div class="nb-row-sub">Ditambahkan {{ $fmtDate($p['added_at']) }}</div>
                  @else
                    <div class="nb-row-sub">Koleksi terbaru</div>
                  @endif
                </div>
              </div>
              <div class="nb-pill">Baru</div>
            @if($detailUrl)
              </a>
            @else
              </div>
            @endif
          @empty
            <div style="padding:14px;" class="nb-sub">Belum ada koleksi terbaru.</div>
          @endforelse
        </div>
      </div>
    </div>
  @else
    <div class="nb-grid nb-grid-2">

      <div class="nb-card">
        <div style="padding:14px;">
          <div class="nb-section-title">Top judul ({{ $range_days ?? 14 }} hari)</div>
          <div class="nb-sub">Judul paling sering dipinjam.</div>
        </div>
        <div class="nb-list">
          @forelse($topTitles as $t)
            <div class="nb-row">
              <div style="min-width:0;">
                <div class="nb-row-title">{{ $t['title'] ?? 'Judul' }}</div>
                <div class="nb-row-sub">Total pinjam: <b>{{ $t['loans'] ?? $t['count'] ?? 0 }}</b></div>
              </div>
              <div class="nb-pill">Top</div>
            </div>
          @empty
            <div style="padding:14px;" class="nb-sub">Belum ada data top judul.</div>
          @endforelse
        </div>
      </div>

      <div class="nb-card">
        <div style="padding:14px;">
          <div class="nb-section-title">Member paling sering telat</div>
          <div class="nb-sub">Prioritas tindak lanjut.</div>
        </div>
        <div class="nb-list">
          @forelse($topOverdueMembers as $m)
            <div class="nb-row">
              <div style="min-width:0;">
                <div class="nb-row-title">{{ $m['member_name'] ?? $m['name'] ?? 'Member' }}</div>
                <div class="nb-row-sub">Item terlambat: <b>{{ $m['overdue_items'] ?? $m['count'] ?? 0 }}</b></div>
              </div>
              <div class="nb-pill">Overdue</div>
            </div>
          @empty
            <div style="padding:14px;" class="nb-sub">Belum ada data overdue member.</div>
          @endforelse
        </div>
      </div>

    </div>
  @endif

</div>
@endsection