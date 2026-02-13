{{-- resources/views/partials/sidebar.blade.php --}}
@php
  $user = auth()->user();
  $name = $user->name ?? 'Pengguna';
  $role = $user->role ?? 'member';
  $roleLabel = $role === 'super_admin' ? 'Super Admin' : ucfirst($role);

  $initials = collect(explode(' ', trim($name)))
    ->filter()
    ->take(2)
    ->map(fn($p) => mb_strtoupper(mb_substr($p,0,1)))
    ->implode('');

  $isStaff = in_array($role, ['super_admin','admin','staff'], true);
  $isAdmin = in_array($role, ['super_admin','admin'], true);
  $isMember = !$isStaff;

  // =========================
  // MENU UTAMA (ROLE-AWARE)
  // =========================
  $items = [
    ['route' => 'beranda',       'label' => 'Beranda',   'icon' => '#nb-icon-home',  'color' => '#1e88e5'],

    // highlight tetap aktif untuk semua katalog.*
    ['route' => 'katalog.index', 'match' => ['katalog.*'], 'label' => 'Katalog',   'icon' => '#nb-icon-book',  'color' => '#2ecc71'],
  ];

  // Menu khusus staff/admin
  if ($isStaff) {
    $items[] = ['route' => 'anggota.index', 'label' => 'Anggota', 'icon' => '#nb-icon-users', 'color' => '#8e24aa'];
    $items[] = ['route' => 'laporan.index', 'match' => ['laporan.*'], 'label' => 'Laporan', 'icon' => '#nb-icon-chart', 'color' => '#42a5f5'];
    $items[] = ['route' => 'serial_issues.index', 'match' => ['serial_issues.*'], 'label' => 'Serial Issue', 'icon' => '#nb-icon-clipboard', 'color' => '#26a69a'];
  }

  // Komunitas untuk semua role
  $items[] = ['route' => 'komunitas.feed', 'match' => ['komunitas.*'], 'label' => 'Komunitas', 'icon' => '#nb-icon-chat', 'color' => '#00acc1'];
  $items[] = ['route' => 'docs.index', 'match' => ['docs.*'], 'label' => 'Dokumentasi', 'icon' => '#nb-icon-book', 'color' => '#90caf9'];

  // =========================
  // MEMBER (SUBMENU) - MEMBER ONLY
  // =========================
  $memberActive = request()->routeIs('member.*') || request()->is('member*');
  $memberItems = [
    ['route' => 'member.dashboard',  'label' => 'Dashboard',      'icon' => '#nb-icon-home',     'color' => '#1e88e5'],
    ['route' => 'member.pustakawan.digital', 'match' => ['member.pustakawan.*'], 'label' => 'Pustakawan Digital', 'icon' => '#nb-icon-chat', 'color' => '#00acc1'],
    ['route' => 'member.pinjaman',   'label' => 'Pinjaman Saya',  'icon' => '#nb-icon-rotate',   'color' => '#fb8c00'],
    ['route' => 'member.reservasi',  'label' => 'Reservasi Saya', 'icon' => '#nb-icon-book',     'color' => '#2ecc71'],
    ['route' => 'member.notifikasi', 'label' => 'Notifikasi',     'icon' => '#nb-icon-bell',     'color' => '#42a5f5'],
    ['route' => 'member.profil',     'label' => 'Profil',         'icon' => '#nb-icon-users',    'color' => '#8e24aa'],
    ['route' => 'member.security',   'label' => 'Keamanan',       'icon' => '#nb-icon-alert',    'color' => '#90caf9'],
  ];

  // =========================
  // TRANSAKSI (SUBMENU) - STAFF ONLY
  // =========================
  $transaksiActive = request()->routeIs('transaksi.*') || request()->is('transaksi*');

  // ✅ Match dibuat "lebih spesifik" (explicit) biar jelas route mana saja yang dianggap aktif.
  // Tetap aman kalau nanti kamu nambah route baru: kamu tinggal tambah ke array match.
  $transaksiItems = [
    [
      'route' => 'transaksi.pinjam.form',
      'match' => [
        'transaksi.pinjam.form',
        'transaksi.pinjam.cari_member',
        'transaksi.pinjam.cek_barcode',
        'transaksi.pinjam.store',
        'transaksi.pinjam.success',
      ],
      'label' => 'Pinjam',
      'icon'  => '#nb-icon-rotate',
    ],
    [
      'route' => 'transaksi.kembali.form',
      'match' => [
        'transaksi.kembali.form',
        'transaksi.kembali.cek_barcode',
        'transaksi.kembali.store',
        'transaksi.kembali.success',
      ],
      'label' => 'Kembali',
      'icon'  => '#nb-icon-rotate',
    ],
    [
      'route' => 'transaksi.perpanjang.form',
      'match' => [
        'transaksi.perpanjang.form',
        'transaksi.perpanjang.cek_barcode',
        'transaksi.perpanjang.store',
      ],
      'label' => 'Perpanjang',
      'icon'  => '#nb-icon-rotate',
    ],
    [
      'route' => 'transaksi.riwayat',
      'match' => [
        'transaksi.riwayat',
        'transaksi.riwayat.detail',
        'transaksi.riwayat.print',
      ],
      'label' => 'Riwayat',
      'icon'  => '#nb-icon-rotate',
    ],
    [
      'route' => 'transaksi.denda.index',
      'match' => [
        'transaksi.denda.index',
        'transaksi.denda.recalc',
        'transaksi.denda.bayar',
        'transaksi.denda.void',
      ],
      'label' => 'Denda',
      'icon'  => '#nb-icon-rotate',
    ],
    [
      'route' => 'transaksi.dashboard',
      'match' => [
        'transaksi.dashboard',
      ],
      'label' => 'Dashboard',
      'icon'  => '#nb-icon-rotate',
    ],
  ];

  // =========================
  // PENGADAAN (SUBMENU) - STAFF ONLY
  // =========================
  $pengadaanActive = request()->routeIs('acquisitions.*') || request()->is('acquisitions*');
  $pengadaanItems = [
    [
      'route' => 'acquisitions.requests.index',
      'match' => [
        'acquisitions.requests.index',
        'acquisitions.requests.create',
        'acquisitions.requests.show',
      ],
      'label' => 'Permintaan',
      'icon'  => '#nb-icon-clipboard',
    ],
    [
      'route' => 'acquisitions.pos.index',
      'match' => [
        'acquisitions.pos.index',
        'acquisitions.pos.create',
        'acquisitions.pos.show',
      ],
      'label' => 'Purchase Order',
      'icon'  => '#nb-icon-book',
    ],
    [
      'route' => 'acquisitions.vendors.index',
      'match' => [
        'acquisitions.vendors.index',
        'acquisitions.vendors.create',
        'acquisitions.vendors.edit',
      ],
      'label' => 'Vendor',
      'icon'  => '#nb-icon-users',
    ],
    [
      'route' => 'acquisitions.budgets.index',
      'match' => [
        'acquisitions.budgets.index',
        'acquisitions.budgets.create',
        'acquisitions.budgets.edit',
      ],
      'label' => 'Budget',
      'icon'  => '#nb-icon-chart',
    ],
  ];

  // =========================
  // MASTER (STAFF ONLY)
  // =========================
  $masterItems = [
    ['route' => 'cabang.index', 'label' => 'Cabang', 'icon' => '#nb-icon-users', 'color' => '#42a5f5'],
    ['route' => 'rak.index',    'label' => 'Rak',    'icon' => '#nb-icon-book',  'color' => '#90caf9'],
  ];

  $systemItems = [
    ['route' => 'admin.search_synonyms', 'label' => 'Sinonim Pencarian', 'icon' => '#nb-icon-search', 'color' => '#42a5f5'],
    ['route' => 'admin.marc.settings', 'label' => 'MARC Settings', 'icon' => '#nb-icon-chart', 'color' => '#42a5f5'],
    ['route' => 'docs.marc-policy', 'label' => 'Dokumentasi MARC', 'icon' => '#nb-icon-book', 'color' => '#90caf9'],
  ];

  // =========================
  // SWITCH CABANG (SUPER ADMIN) - NO REDIRECT
  // =========================
  $canSwitchBranch = ($role === 'super_admin');
  $effectiveBranchId = (int) session('active_branch_id', (int) ($user->branch_id ?? 0));

  // Tooltip label (ambil name cabang aktif)
  $branchLabel = null;
  try {
    if ($effectiveBranchId > 0) {
      $branchLabel = \Illuminate\Support\Facades\DB::table('branches')->where('id', $effectiveBranchId)->value('name');
    }
  } catch (\Throwable $e) {
    $branchLabel = null;
  }
  $branchLabel = $branchLabel ?: 'Belum dipilih';

  // List cabang untuk dropdown (super_admin saja)
  $switchBranches = collect();
  if ($canSwitchBranch) {
    try {
      $switchBranches = \Illuminate\Support\Facades\DB::table('branches')
        ->select('id','name')
        ->where('is_active', 1)
        ->orderBy('name')
        ->get();
    } catch (\Throwable $e) {
      $switchBranches = collect();
    }
  }
@endphp

<style>
  /* =========================================================
     SIDEBAR SCROLL (SMOOTH + SCROLLBAR BIRU)
     ========================================================= */
  .nb-sb-scroll{
    scroll-behavior:smooth;
    -webkit-overflow-scrolling:touch;
    overflow-x:hidden;

    /* Firefox */
    scrollbar-width:thin;
    scrollbar-color:#1e88e5 transparent;
  }

  /* Chrome / Edge / Safari */
  .nb-sb-scroll::-webkit-scrollbar{ width:10px; height:10px; }
  .nb-sb-scroll::-webkit-scrollbar-track{
    background: rgba(0,0,0,0.06);
    border-radius:999px;
    margin: 10px 0;
  }
  .nb-sb-scroll::-webkit-scrollbar-thumb{
    background: linear-gradient(180deg, #1e88e5, #1565c0);
    border-radius:999px;
    border: 3px solid transparent;
    background-clip: content-box;
  }
  .nb-sb-scroll::-webkit-scrollbar-thumb:hover{
    background: linear-gradient(180deg, #42a5f5, #1e88e5);
    background-clip: content-box;
  }

  @keyframes nbGradientShift {
    0%   { background-position: 0% 50%; }
    50%  { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
  }

  /* Hover CTA */
  .nb-sb-cta{
    transition: filter .15s ease, transform .08s ease, box-shadow .15s ease;
  }
  .nb-sb-cta:hover{
    filter: brightness(.95) saturate(1.08);
    box-shadow: 0 16px 26px rgba(46,204,113,.18);
    transform: translateY(-1px);
  }
  .nb-sb-cta:active{ transform: translateY(0px); }
  .nb-sb-cta:hover .nb-sb-cta-text,
  .nb-sb-cta:hover .nb-sb-cta-right,
  .nb-sb-cta:hover svg{
    color:#fff !important;
    fill: currentColor !important;
  }

  .nb-sb-fw-500{ font-weight:500 !important; }
  .nb-sb-fw-600{ font-weight:600 !important; }
  .nb-sb-fw-700{ font-weight:700 !important; }

  @media (prefers-reduced-motion: reduce){
    .nb-sb-active-anim{ animation: none !important; }
    .nb-sb-cta, .nb-sb-item-link{ transition: none !important; }
  }

  /* =========================================================
     TRANSAKSI SUBMENU (RAPI)
     ========================================================= */
  .nb-sb-group{ border:1px solid rgba(255,255,255,.10); border-radius:16px; overflow:hidden; }
  .nb-sb-group summary{ list-style:none; cursor:pointer; }
  .nb-sb-group summary::-webkit-details-marker{ display:none; }
  .nb-sb-group-head{
    display:flex; align-items:center; gap:12px;
    padding:12px 12px;
    border-radius:16px;
    transition: background .15s ease, transform .08s ease, box-shadow .15s ease, border-color .15s ease;
  }
  .nb-sb-group-active .nb-sb-group-head{
    background: linear-gradient(90deg, #fb8c00, #ef6c00, #fb8c00);
    background-size:200% 200%;
    animation: nbGradientShift 2.8s ease-in-out infinite;
    border-color: rgba(251,140,0,.45);
    box-shadow: 0 6px 12px rgba(251,140,0,.14);
    transform: translateY(-1px);
  }

  .nb-sb-sub{ display:flex; flex-direction:column; gap:8px; padding:8px 10px 12px; }
  .nb-sb-sub a{
    display:flex; align-items:center; gap:10px;
    padding:10px 10px;
    border-radius:14px;
    border:1px solid rgba(255,255,255,.10);
    text-decoration:none;
    transition: background .15s ease, transform .08s ease, box-shadow .15s ease, border-color .15s ease;
  }
  .nb-sb-sub a:hover{ background: rgba(255,255,255,.06); }
  .nb-sb-sub a.nb-sb-sub-active{
    background: rgba(251,140,0,.20);
    border-color: rgba(251,140,0,.35);
  }

  /* Switch cabang inline (dropdown) */
  .nb-sb-branchctl{
    padding:6px 12px 12px 12px;
    display:flex;
    gap:8px;
    align-items:center;
  }
  .nb-sb-branchctl select{
    margin-top:4px;
    width:100%;
    max-width:100%;
    background: rgba(255,255,255,.06);
    color: rgba(255,255,255,.92);
    border: 1px solid rgba(255,255,255,.14);
    border-radius: 12px;
    padding: 9px 10px;
    font-size: 12px;
    outline: none;
  }
  .nb-sb-branchctl select:focus{
    border-color: rgba(34,197,94,.45);
    box-shadow: 0 0 0 4px rgba(34,197,94,.10);
  }
  .nb-sb-branchctl option{ color:#111827; }

  .nb-sb-reset{
    font-size:11px;
    color: rgba(255,255,255,.70);
    background:none;
    border:none;
    padding:0;
    cursor:pointer;
    text-decoration: underline;
  }
  .nb-sb-reset:hover{ color: rgba(255,255,255,.92); }
</style>

<div
  class="nb-sb-scroll"
  style="
    flex:1;
    display:flex;
    flex-direction:column;
    gap:12px;
    overflow-y:auto;
    overflow-x:hidden;
    padding-right:14px;
    margin-right:-8px;
  "
>
  {{-- Brand --}}
  <div style="display:flex; align-items:center; gap:12px; min-width:0; flex-shrink:0;">
    <div style="width:46px;height:46px;border-radius:16px;background:rgba(255,255,255,.10);
                border:1px solid rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <span class="nb-sb-fw-700" style="color:#fff; letter-spacing:.6px; font-size:13px;">NB</span>
    </div>
    <div style="min-width:0;">
      <div class="nb-sb-label nb-sb-fw-700" style="color:#fff; font-size:14px; line-height:1.1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
        NOTOBUKU
      </div>
      <div class="nb-sb-sub nb-sb-fw-500" style="color:rgba(229,231,235,.70); font-size:12px; margin-top:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
        Perpustakaan + Komunitas
      </div>
    </div>
  </div>

  <div style="height:1px; background:rgba(255,255,255,.10);"></div>

  {{-- Menu --}}
  <nav style="display:flex; flex-direction:column; gap:10px;">
    @foreach($items as $it)
      @php
        $active = false;
        if (!empty($it['match']) && is_array($it['match'])) {
          foreach ($it['match'] as $p) {
            if (request()->routeIs($p)) { $active = true; break; }
          }
        } else {
          $active = request()->routeIs($it['route']) || request()->routeIs($it['route'].'.*');
        }

        $ic = $active ? '#fff' : ($it['color'] ?? 'rgba(255,255,255,.72)');
        $bg = $active ? 'linear-gradient(90deg, #1e88e5, #1565c0, #1e88e5)' : 'transparent';
        $border = $active ? 'rgba(30,136,229,.45)' : 'rgba(255,255,255,.10)';
        $shadow = $active ? '0 6px 12px rgba(30,136,229,.14)' : 'none';
        $transform = $active ? 'translateY(-1px)' : 'none';
        $bgSize = $active ? '200% 200%' : 'auto';
        $anim = $active ? 'nbGradientShift 2.8s ease-in-out infinite' : 'none';
      @endphp

      <a href="{{ route($it['route']) }}"
         class="nb-sb-item nb-sb-item-link {{ $active ? 'nb-sb-active-anim' : '' }}"
         style="
           display:flex;
           align-items:center;
           gap:12px;
           padding:12px 12px;
           border-radius:16px;
           border:1px solid {{ $border }};
           background:{{ $bg }};
           background-size:{{ $bgSize }};
           background-position:0% 50%;
           animation:{{ $anim }};
           box-shadow:{{ $shadow }};
           transform:{{ $transform }};
           transition: background .15s ease, transform .08s ease, box-shadow .15s ease, border-color .15s ease;
         ">
        <span class="nb-sb-ico" style="width:20px;height:20px; display:inline-flex; color:{{ $ic }}; flex-shrink:0;">
          <svg style="width:20px;height:20px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <use href="{{ $it['icon'] }}"></use>
          </svg>
        </span>

        <span class="nb-sb-label nb-sb-fw-600"
              style="color:{{ $active ? '#fff' : 'rgba(255,255,255,.86)' }};
                     font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
          {{ $it['label'] }}
        </span>
      </a>
    @endforeach

    {{-- MEMBER (MEMBER ONLY) --}}
    @if($isMember)
      <div style="margin-top:10px;"></div>

      <div style="padding: 0 8px; margin: 4px 0 6px; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.55);">
        Member Area
      </div>

      <div style="display:flex; flex-direction:column; gap:10px;">
        @foreach($memberItems as $it)
          @php
            $active = false;
            if (!empty($it['match']) && is_array($it['match'])) {
              foreach ($it['match'] as $p) {
                if (request()->routeIs($p)) { $active = true; break; }
              }
            } else {
              $active = request()->routeIs($it['route']) || request()->routeIs($it['route'].'.*');
            }
            $ic = $active ? '#fff' : ($it['color'] ?? 'rgba(255,255,255,.72)');
            $bg = $active ? 'linear-gradient(90deg, #1e88e5, #1565c0, #1e88e5)' : 'transparent';
            $border = $active ? 'rgba(30,136,229,.45)' : 'rgba(255,255,255,.10)';
            $shadow = $active ? '0 6px 12px rgba(30,136,229,.14)' : 'none';
            $transform = $active ? 'translateY(-1px)' : 'none';
            $bgSize = $active ? '200% 200%' : 'auto';
            $anim = $active ? 'nbGradientShift 2.8s ease-in-out infinite' : 'none';
          @endphp

          <a href="{{ route($it['route']) }}"
             class="nb-sb-item nb-sb-item-link {{ $active ? 'nb-sb-active-anim' : '' }}"
             style="
               display:flex;
               align-items:center;
               gap:12px;
               padding:12px 12px;
               border-radius:16px;
               border:1px solid {{ $border }};
               background:{{ $bg }};
               background-size:{{ $bgSize }};
               background-position:0% 50%;
               animation:{{ $anim }};
               box-shadow:{{ $shadow }};
               transform:{{ $transform }};
               transition: background .15s ease, transform .08s ease, box-shadow .15s ease, border-color .15s ease;
             ">

            <span class="nb-sb-ico" style="width:20px;height:20px; display:inline-flex; color:{{ $ic }}; flex-shrink:0;">
              <svg style="width:20px;height:20px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <use href="{{ $it['icon'] }}"></use>
              </svg>
            </span>

            <span class="nb-sb-label nb-sb-fw-600"
                  style="color:{{ $active ? '#fff' : 'rgba(255,255,255,.86)' }};
                         font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1;">
              {{ $it['label'] }}
            </span>

            @if($it['route'] === 'member.notifikasi')
              <span id="nbMemberNotifBadge"
                    style="display:none; min-width:28px; height:22px; padding:0 8px;
                           border-radius:999px; font-size:12px; font-weight:700;
                           align-items:center; justify-content:center;
                           background:rgba(255,255,255,.16); color:#fff;">
                0
              </span>
            @endif
          </a>
        @endforeach
      </div>

      <script>
        (function () {
          var el = document.getElementById('nbMemberNotifBadge');
          if (!el) return;

          fetch('/notifikasi/count', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
              var n = 0;
              if (typeof d === 'number') n = d;
              if (d && typeof d.count === 'number') n = d.count;

              if (n > 0) {
                el.style.display = 'inline-flex';
                el.textContent = n > 99 ? '99+' : String(n);
              }
            })
            .catch(function(){});
        })();
      </script>
    @endif

    {{-- TRANSAKSI (STAFF ONLY) --}}
    @if($isStaff)
      <div style="margin-top:6px;">
        <details class="nb-sb-group {{ $transaksiActive ? 'nb-sb-group-active' : '' }}" {{ $transaksiActive ? 'open' : '' }}>
          <summary class="nb-sb-group-head">
            @php $ic = $transaksiActive ? '#fff' : '#fb8c00'; @endphp
            <span class="nb-sb-ico" style="width:20px;height:20px; display:inline-flex; color:{{ $ic }}; flex-shrink:0;">
              <svg style="width:20px;height:20px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <use href="#nb-icon-rotate"></use>
              </svg>
            </span>

            <span class="nb-sb-label nb-sb-fw-600"
                  style="color:{{ $transaksiActive ? '#fff' : 'rgba(255,255,255,.86)' }}; font-size:13px;">
              Transaksi
            </span>
          </summary>

          {{-- Switch cabang inline (super_admin) --}}
          @if($canSwitchBranch && $switchBranches->count())
            <form class="nb-sb-branchctl" method="POST" action="{{ route('preferences.active_branch.set') }}">
              @csrf
              <select name="branch_id" data-nb-branch-select data-set-url="{{ route('preferences.active_branch.set') }}" data-branch-name="{{ $branchLabel }}" data-current="{{ $effectiveBranchId }}" aria-label="Pilih cabang aktif" title="Cabang aktif: {{ $branchLabel }}">
                @foreach($switchBranches as $b)
                  <option value="{{ $b->id }}" @selected((int)$effectiveBranchId === (int)$b->id)>{{ (int)$effectiveBranchId === (int)$b->id ? "🟢" : "⚪" }} {{ $b->name }}</option>
                @endforeach
              </select>
            </form>

            <div class="nb-sb-branchctl" style="padding-top:0; justify-content:flex-end;">
              <form method="POST" action="{{ route('preferences.active_branch.reset') }}">
                @csrf
                <button type="button" data-nb-branch-reset data-reset-url="{{ route('preferences.active_branch.reset') }}" class="nb-sb-reset" title="Kembali ke cabang default akun">
                  Reset
                </button>
              </form>
            </div>
          @endif

          <div class="nb-sb-sub">
            @foreach($transaksiItems as $ti)
              @php
                $subActive = false;
                if (!empty($ti['match']) && is_array($ti['match'])) {
                  foreach ($ti['match'] as $p) {
                    if (request()->routeIs($p)) { $subActive = true; break; }
                  }
                } else {
                  $subActive = request()->routeIs($ti['route']);
                }
              @endphp

              <a href="{{ route($ti['route']) }}" class="{{ $subActive ? 'nb-sb-sub-active' : '' }}">
                <span style="width:18px;height:18px; display:inline-flex; color:{{ $subActive ? '#fff' : 'rgba(255,255,255,.72)' }}; flex-shrink:0;">
                  <svg style="width:18px;height:18px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <use href="{{ $ti['icon'] }}"></use>
                  </svg>
                </span>
                <span style="color:{{ $subActive ? '#fff' : 'rgba(255,255,255,.86)' }}; font-size:12.5px;">
                  {{ $ti['label'] }}
                </span>
              </a>
            @endforeach
          </div>
        </details>
      </div>

      {{-- PENGADAAN (STAFF ONLY) --}}
      <div style="margin-top:10px;">
        <details class="nb-sb-group {{ $pengadaanActive ? 'nb-sb-group-active' : '' }}" {{ $pengadaanActive ? 'open' : '' }}>
          <summary class="nb-sb-group-head">
            @php $ic = $pengadaanActive ? '#fff' : '#2ecc71'; @endphp
            <span class="nb-sb-ico" style="width:20px;height:20px; display:inline-flex; color:{{ $ic }}; flex-shrink:0;">
              <svg style="width:20px;height:20px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <use href="#nb-icon-clipboard"></use>
              </svg>
            </span>

            <span class="nb-sb-label nb-sb-fw-600"
                  style="color:{{ $pengadaanActive ? '#fff' : 'rgba(255,255,255,.86)' }}; font-size:13px;">
              Pengadaan
            </span>
          </summary>

          <div class="nb-sb-sub">
            @foreach($pengadaanItems as $ti)
              @php
                $subActive = false;
                if (!empty($ti['match']) && is_array($ti['match'])) {
                  foreach ($ti['match'] as $p) {
                    if (request()->routeIs($p)) { $subActive = true; break; }
                  }
                } else {
                  $subActive = request()->routeIs($ti['route']);
                }
              @endphp

              <a href="{{ route($ti['route']) }}" class="{{ $subActive ? 'nb-sb-sub-active' : '' }}">
                <span style="width:18px;height:18px; display:inline-flex; color:{{ $subActive ? '#fff' : 'rgba(255,255,255,.72)' }}; flex-shrink:0;">
                  <svg style="width:18px;height:18px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <use href="{{ $ti['icon'] }}"></use>
                  </svg>
                </span>
                <span style="color:{{ $subActive ? '#fff' : 'rgba(255,255,255,.86)' }}; font-size:12.5px;">
                  {{ $ti['label'] }}
                </span>
              </a>
            @endforeach
          </div>
        </details>
      </div>

      {{-- MASTER (STAFF ONLY) --}}
      <div style="margin-top:10px;">
        <div class="nb-sb-fw-600"
             style="font-size:11.5px; color:rgba(255,255,255,.62); padding:0 6px; margin:8px 0 6px;">
          Master
        </div>

        <div style="display:flex; flex-direction:column; gap:10px;">
          @foreach($masterItems as $it)
            @php
              $active = request()->routeIs($it['route']) || request()->routeIs($it['route'].'.*');
              $ic = $active ? '#fff' : ($it['color'] ?? 'rgba(255,255,255,.72)');
              $bg = $active ? 'linear-gradient(90deg, #1e88e5, #1565c0, #1e88e5)' : 'transparent';
              $border = $active ? 'rgba(30,136,229,.45)' : 'rgba(255,255,255,.10)';
              $shadow = $active ? '0 6px 12px rgba(30,136,229,.14)' : 'none';
              $transform = $active ? 'translateY(-1px)' : 'none';
              $bgSize = $active ? '200% 200%' : 'auto';
              $anim = $active ? 'nbGradientShift 2.8s ease-in-out infinite' : 'none';
            @endphp

            <a href="{{ route($it['route']) }}"
               class="nb-sb-item nb-sb-item-link {{ $active ? 'nb-sb-active-anim' : '' }}"
               style="
                 display:flex;
                 align-items:center;
                 gap:12px;
                 padding:12px 12px;
                 border-radius:16px;
                 border:1px solid {{ $border }};
                 background:{{ $bg }};
                 background-size:{{ $bgSize }};
                 background-position:0% 50%;
                 animation:{{ $anim }};
                 box-shadow:{{ $shadow }};
                 transform:{{ $transform }};
                 transition: background .15s ease, transform .08s ease, box-shadow .15s ease, border-color .15s ease;
               ">
              <span class="nb-sb-ico" style="width:20px;height:20px; display:inline-flex; color:{{ $ic }}; flex-shrink:0;">
                <svg style="width:20px;height:20px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                  <use href="{{ $it['icon'] }}"></use>
                </svg>
              </span>

              <span class="nb-sb-label nb-sb-fw-600"
                    style="color:{{ $active ? '#fff' : 'rgba(255,255,255,.86)' }};
                           font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                {{ $it['label'] }}
              </span>
            </a>
          @endforeach
        </div>
      </div>
    @endif

    {{-- SYSTEM (ADMIN ONLY) --}}
    @if($isAdmin)
      <div style="margin-top:10px;">
        <div class="nb-sb-fw-600"
             style="font-size:11.5px; color:rgba(255,255,255,.62); padding:0 6px; margin:8px 0 6px;">
          System
        </div>

        <div style="display:flex; flex-direction:column; gap:10px;">
          @foreach($systemItems as $it)
            @php
              $active = request()->routeIs($it['route']) || request()->routeIs($it['route'].'.*');
              $ic = $active ? '#fff' : ($it['color'] ?? 'rgba(255,255,255,.72)');
              $bg = $active ? 'linear-gradient(90deg, #1e88e5, #1565c0, #1e88e5)' : 'transparent';
              $border = $active ? 'rgba(30,136,229,.45)' : 'rgba(255,255,255,.10)';
              $shadow = $active ? '0 6px 12px rgba(30,136,229,.14)' : 'none';
              $transform = $active ? 'translateY(-1px)' : 'none';
              $bgSize = $active ? '200% 200%' : 'auto';
              $anim = $active ? 'nbGradientShift 2.8s ease-in-out infinite' : 'none';
            @endphp

            <a href="{{ route($it['route']) }}"
               class="nb-sb-item nb-sb-item-link {{ $active ? 'nb-sb-active-anim' : '' }}"
               style="
                 display:flex;
                 align-items:center;
                 gap:12px;
                 padding:12px 12px;
                 border-radius:16px;
                 border:1px solid {{ $border }};
                 background:{{ $bg }};
                 background-size:{{ $bgSize }};
                 background-position:0% 50%;
                 animation:{{ $anim }};
                 box-shadow:{{ $shadow }};
                 transform:{{ $transform }};
                 transition: background .15s ease, transform .08s ease, box-shadow .15s ease, border-color .15s ease;
               ">
              <span class="nb-sb-ico" style="width:20px;height:20px; display:inline-flex; color:{{ $ic }}; flex-shrink:0;">
                <svg style="width:20px;height:20px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                  <use href="{{ $it['icon'] }}"></use>
                </svg>
              </span>

              <span class="nb-sb-label nb-sb-fw-600"
                    style="color:{{ $active ? '#fff' : 'rgba(255,255,255,.86)' }};
                           font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                {{ $it['label'] }}
              </span>
            </a>
          @endforeach
        </div>
      </div>
    @endif

    {{-- CTA: Buat Postingan --}}
    <a href="{{ route('komunitas.buat') }}"
       class="nb-btn nb-btn-success nb-sb-cta"
       style="justify-content:flex-start; width:100%; border-radius:16px; margin-top:6px; font-weight:600;">
      <span style="width:18px;height:18px; display:inline-flex; flex-shrink:0; color:#0b2d18;">
        <svg style="width:18px;height:18px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <use href="#nb-icon-plus"></use>
        </svg>
      </span>
      <span class="nb-sb-label nb-sb-cta-text nb-sb-fw-600" style="font-size:13px; color:#0b2d18;">
        Buat Postingan
      </span>
      <span class="nb-sb-label nb-sb-cta-right nb-sb-fw-600" style="margin-left:auto; font-size:11px; color:#0b2d18;">
        Komunitas
      </span>
    </a>
  </nav>

  {{-- Spacer --}}
  <div style="flex-grow:1;"></div>

  <div style="height:1px; background:rgba(255,255,255,.10);"></div>

  {{-- Quick: Search + Theme --}}
  <div style="display:flex; gap:10px;">
    <button type="button"
            data-nb-open-search
            style="flex:1; display:flex; align-items:center; justify-content:center; gap:10px;
                   padding:12px 12px; border-radius:16px;
                   background:rgba(255,255,255,.06);
                   border:1px solid rgba(255,255,255,.10);
                   color:#fff; font-weight:600; cursor:pointer;">
      <span style="width:18px;height:18px; display:inline-flex; color:rgba(255,255,255,.85); flex-shrink:0;">
        <svg style="width:18px;height:18px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <use href="#nb-icon-search"></use>
        </svg>
      </span>
      <span class="nb-sb-fw-600" style="font-size:13px; color:#fff;">Cari</span>
      <span class="nb-sb-fw-600" style="margin-left:auto; font-size:12px; color:rgba(255,255,255,.65);">Ctrl K</span>
    </button>

    <button type="button"
            data-nb-toggle-theme
            aria-label="Ganti tema"
            style="width:54px; display:flex; align-items:center; justify-content:center;
                   padding:12px 0; border-radius:16px;
                   background:rgba(255,255,255,.06);
                   border:1px solid rgba(255,255,255,.10);
                   cursor:pointer;">
      <span data-nb-theme-icon style="display:inline-flex; width:18px; height:18px; color:rgba(255,255,255,.85);">
        <svg style="width:18px;height:18px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <use href="#nb-icon-sun"></use>
        </svg>
      </span>
    </button>
  </div>

  {{-- User card --}}
  <div style="border-radius:18px; border:1px solid rgba(255,255,255,.10); background:rgba(255,255,255,.06);
              padding:12px 12px; display:flex; align-items:center; gap:12px; min-width:0;">
    <div style="width:40px;height:40px;border-radius:16px;background:linear-gradient(90deg,#1e88e5,#1565c0);
                display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:12px;flex-shrink:0;">
      {{ $initials }}
    </div>
    <div style="min-width:0; flex:1;">
      <div class="nb-sb-fw-600" style="color:#fff; font-size:13px; line-height:1.15; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
        {{ $name }}
      </div>
      <div class="nb-sb-fw-500" style="color:rgba(229,231,235,.70); font-size:12px; margin-top:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
        {{ $roleLabel }}
      </div>
    </div>
  </div>

  {{-- Logout --}}
  <form method="POST" action="{{ route('keluar') }}">
    @csrf
    <button type="submit"
            style="width:100%; display:flex; align-items:center; gap:12px;
                   padding:12px 12px; border-radius:16px;
                   background:transparent; border:1px solid rgba(255,255,255,.10);
                   color:#fff; font-weight:600; cursor:pointer;">
      <span style="width:20px;height:20px; display:inline-flex; color:rgba(255,255,255,.80); flex-shrink:0;">
        <svg style="width:20px;height:20px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <use href="#nb-icon-logout"></use>
        </svg>
      </span>
      <span class="nb-sb-fw-600" style="color:#fff; font-size:13px;">Keluar</span>
    </button>
  </form>

  <div class="nb-sb-fw-500" style="font-size:11px; color:rgba(255,255,255,.62); line-height:1.5; padding:0 2px;">
    Tips: <span class="nb-sb-fw-600" style="padding:2px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.14); background:rgba(255,255,255,.08);">Ctrl K</span> cari cepat.
  </div>
</div>

<script>
(function(){
  const role = @json(auth()->user()->role ?? 'member');
  if(role !== 'super_admin') return;

  const select = document.querySelector('[data-nb-branch-select]');
  const resetBtn = document.querySelector('[data-nb-branch-reset]');
  if(!select) return;

  const csrf = @json(csrf_token());
  const setUrl = select.getAttribute('data-set-url');
  const resetUrl = resetBtn ? resetBtn.getAttribute('data-reset-url') : null;

  function setTooltip(name){
    const label = name || 'Belum dipilih';
    select.setAttribute('title', 'Cabang aktif: ' + label);
  }

  async function postJSON(url, payload){
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload || {})
    });
    const data = await res.json().catch(() => ({}));
    if(!res.ok || data.ok === false){
      const msg = data.message || (data.errors && (data.errors.branch_id?.[0] || data.errors.branch_id)) || 'Gagal memproses.';
      throw new Error(msg);
    }
    return data;
  }

  function isTransaksiScanInProgress(){
    const path = window.location.pathname || '';
    const isTransaksiPage = /\/transaksi\/(pinjam|kembali|perpanjang)/.test(path);
    if(!isTransaksiPage) return false;

    const tables = Array.from(document.querySelectorAll('table'));
    for(const t of tables){
      const tb = t.tBodies && t.tBodies.length ? t.tBodies[0] : null;
      if(tb && tb.querySelectorAll('tr').length > 0){
        return true;
      }
    }

    const listCandidates = document.querySelectorAll('[data-scan-items], .scan-items, .barcode-items');
    for(const el of listCandidates){
      if(el.children && el.children.length > 0) return true;
    }

    const hiddenIds = document.querySelectorAll('input[type="hidden"][name*="item"], input[type="hidden"][name*="loan_item"]');
    if(hiddenIds.length > 0) return true;

    return false;
  }

  function applyBranchLockIfNeeded(){
    const locked = isTransaksiScanInProgress();
    if(locked){
      select.disabled = true;
      select.setAttribute('title', 'Tidak bisa ganti cabang saat transaksi scan berjalan. Selesaikan atau reset transaksi terlebih dahulu.');
      if(resetBtn) resetBtn.disabled = true;
      toast('Switch cabang dikunci: transaksi scan sedang berjalan.', true);
    }
    return locked;
  }

  function toast(msg, isError){
    let el = document.getElementById('nb-branch-toast');
    if(!el){
      el = document.createElement('div');
      el.id = 'nb-branch-toast';
      el.style.cssText = 'position:sticky;bottom:0;z-index:50;margin-top:10px;padding:10px 12px;border-radius:14px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.25);backdrop-filter:blur(8px);color:#fff;font-size:12px;line-height:1.3;';
      (select.closest('details') || select.parentElement)?.appendChild(el);
    }
    el.textContent = msg;
    el.style.borderColor = isError ? 'rgba(239,68,68,.45)' : 'rgba(34,197,94,.45)';
    el.style.background = isError ? 'rgba(239,68,68,.12)' : 'rgba(34,197,94,.12)';
    el.style.display = 'block';
    clearTimeout(window.__nbToastT);
    window.__nbToastT = setTimeout(()=>{ el.style.display='none'; }, 2400);
  }

  select.addEventListener('change', async function(){
    if(applyBranchLockIfNeeded()){ return; }

    const branchId = parseInt(select.value || '0', 10);
    if(!branchId) return;

    select.disabled = true;
    try{
      const data = await postJSON(setUrl, { branch_id: branchId });
      setTooltip(data.active_branch_name);
      toast('Cabang aktif: ' + data.active_branch_name);
      [...select.options].forEach(o => {
        const raw = o.textContent.replace(/^(?:\u{1F7E2}|\u{26AA})\s+/u, '');
        o.textContent = ((o.value === String(branchId)) ? '\u{1F7E2} ' : '\u{26AA} ') + raw;
      });
    }catch(err){
      toast(err.message || 'Gagal mengganti cabang.', true);
      const prev = select.getAttribute('data-current');
      if(prev) select.value = prev;
    }finally{
      select.setAttribute('data-current', select.value);
      select.disabled = false;
    }
  });

  if(resetBtn && resetUrl){
    resetBtn.addEventListener('click', async function(ev){
      if(applyBranchLockIfNeeded()){ ev.preventDefault(); return; }

      ev.preventDefault();
      resetBtn.disabled = true;
      try{
        const data = await postJSON(resetUrl, {});
        setTooltip(data.active_branch_name || 'Belum dipilih');
        toast('Cabang direset');
        const cur = String(data.active_branch_id || select.value || '');
        [...select.options].forEach(o => {
          const raw = o.textContent.replace(/^(?:\u{1F7E2}|\u{26AA})\s+/u, '');
          o.textContent = ((o.value === cur) ? '\u{1F7E2} ' : '\u{26AA} ') + raw;
        });
        if(data.active_branch_id){
          select.value = String(data.active_branch_id);
        }
        select.setAttribute('data-current', select.value);
      }catch(err){
        toast(err.message || 'Gagal reset cabang.', true);
      }finally{
        resetBtn.disabled = false;
      }
    });
  }

  setTooltip(select.getAttribute('data-branch-name'));
  applyBranchLockIfNeeded();
})();
</script>
