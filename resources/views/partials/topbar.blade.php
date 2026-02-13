{{-- resources/views/partials/topbar.blade.php --}}
@php
  $user = auth()->user();
  $name = $user->name ?? 'Pengguna';
  $role = $user->role ?? 'member';
  $isStaff = in_array($role, ['super_admin','admin','staff'], true);
  $isMember = !$isStaff;

    // Notifikasi (role-based, tanpa ubah tampilan)
  $notifCountUrl = route('notifikasi.count');
  $notifIndexUrl = $isMember ? route('member.notifikasi') : route('notifikasi.index');


  $routeName = optional(request()->route())->getName();

  $title = match($routeName){
    // dashboard
    'admin.dashboard' => 'Dashboard Admin',
    'staff.dashboard' => 'Dashboard Staff',
    'member.dashboard' => 'Dashboard Member',

    // member pinjaman
    'member.pinjaman' => 'Pinjaman Saya',
    'member.pinjaman.detail' => 'Detail Pinjaman',

    // member alias (redirect ke modul existing)
    'member.reservasi' => 'Reservasi Saya',
    'member.notifikasi' => 'Notifikasi',

    // public/app
    'beranda' => 'Beranda',
    'katalog.index' => 'Katalog',
    'katalog.show' => 'Detail Katalog',
    'komunitas.feed' => 'Komunitas',
    'komunitas.buat' => 'Buat Postingan',

    // staff transaksi
    'transaksi.index' => 'Transaksi',
    'transaksi.pinjam.form' => 'Transaksi • Pinjam',
    'transaksi.kembali.form' => 'Transaksi • Kembali',
    'transaksi.perpanjang.form' => 'Transaksi • Perpanjang',
    'transaksi.riwayat' => 'Riwayat Transaksi',
    'transaksi.riwayat.detail' => 'Detail Transaksi',
    'transaksi.dashboard' => 'Dashboard Sirkulasi',

    // notifikasi modul existing
    'notifikasi.index' => 'Notifikasi',

    // admin/staff master
    'anggota.index' => 'Anggota',
    'cabang.index' => 'Master Cabang',
    'rak.index' => 'Master Rak',

    default => 'NOTOBUKU',
  };

  $sub = match($routeName){
    'admin.dashboard' => 'Kelola sistem NOTOBUKU',
    'staff.dashboard' => 'Kelola layanan perpustakaan',
    'member.dashboard' => 'Ringkasan pinjaman & pengingat jatuh tempo',

    'member.pinjaman' => 'Daftar pinjaman yang sedang / pernah kamu ambil',
    'member.pinjaman.detail' => 'Detail item, jatuh tempo, dan status',

    'member.reservasi' => 'Kelola reservasi buku kamu',
    'member.notifikasi' => 'Pengingat jatuh tempo & keterlambatan',

    'notifikasi.index' => 'Pengingat jatuh tempo & keterlambatan',

    default => $isMember
      ? 'Akses cepat untuk member'
      : 'Operasional perpustakaan',
  };

  $initials = collect(explode(' ', trim($name)))->filter()->take(2)
    ->map(fn($p)=>mb_strtoupper(mb_substr($p,0,1)))->implode('') ?: 'NB';

  $roleLabel = $role === 'super_admin' ? 'Super Admin' : ucfirst($role);
@endphp

<style>
  /* =========================================================
     TOPBAR (compact mobile)
     ========================================================= */

  .nb-tb{
    height:100%;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
  }

  .nb-tb-left{
    display:flex;
    align-items:center;
    gap:10px;
    min-width:0;
    flex:1;
  }

  .nb-tb-title{
    min-width:0;
    display:flex;
    flex-direction:column;
    gap:2px;
  }
  .nb-tb-title .t{
    font-size:14px;
    font-weight:800;
    line-height:1.15;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .nb-tb-title .s{
    font-size:12px;
    font-weight:500;
    color:var(--nb-muted);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }

  .nb-tb-right{
    display:flex;
    align-items:center;
    gap:10px;
    flex-shrink:0;
  }

  .nb-tb-btn{
    height:42px;
    border-radius:14px;
    border:1px solid var(--nb-border);
    background:var(--nb-surface);
    box-shadow: var(--nb-shadow-soft);
    display:inline-flex;
    align-items:center;
    gap:10px;
    padding:0 12px;
    cursor:pointer;
  }
  .nb-tb-btn:hover{ transform: translateY(-1px); }
  .nb-tb-btn:active{ transform: translateY(0px); }

  .nb-tb-ico{
    width:20px; height:20px;
    color:var(--nb-muted);
  }

  .nb-tb-search-pill{
    display:inline-flex;
    align-items:center;
    gap:10px;
    height:42px;
    border-radius:999px;
    padding:0 14px;
    border:1px solid var(--nb-border);
    background:rgba(255,255,255,.75);
    box-shadow: var(--nb-shadow-soft);
    min-width: 320px;
    cursor:pointer;
  }
  html.dark .nb-tb-search-pill{ background:rgba(15,27,46,.65); }
  .nb-tb-kbd{
    font-size:12px;
    color:var(--nb-muted);
    border:1px solid var(--nb-border);
    padding:2px 8px;
    border-radius:10px;
    background:rgba(255,255,255,.6);
  }
  html.dark .nb-tb-kbd{ background:rgba(15,27,46,.6); }

  .nb-tb-user{
    display:flex; align-items:center; gap:10px;
    height:42px;
    border-radius:999px;
    padding:0 8px 0 8px;
    border:1px solid var(--nb-border);
    background:rgba(255,255,255,.75);
    box-shadow: var(--nb-shadow-soft);
  }
  html.dark .nb-tb-user{ background:rgba(15,27,46,.65); }

  .nb-tb-avatar{
    width:32px; height:32px;
    border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    font-size:12px; font-weight:800;
    color:#fff;
    background: linear-gradient(135deg, var(--nb-blue), var(--nb-green));
  }
  .nb-tb-user-meta{ display:flex; flex-direction:column; gap:1px; min-width:0; }
  .nb-tb-user-meta .n{ font-size:12px; font-weight:800; line-height:1.1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width: 160px;}
  .nb-tb-user-meta .r{ font-size:11px; color:var(--nb-muted); }

  /* Mobile: search pill jadi icon saja */
  @media (max-width: 767px){
    .nb-tb-search-pill{ min-width:auto; padding:0 12px; }
    .nb-tb-search-pill .txt,
    .nb-tb-search-pill .nb-tb-kbd{ display:none; }
    .nb-tb-user-meta{ display:none; }
  }
</style>

<div class="nb-container">
  <div class="nb-tb">
    <div class="nb-tb-left">
      <button class="nb-tb-btn" type="button" data-nb-sidebar-open title="Menu">
        <svg class="nb-tb-ico" viewBox="0 0 24 24"><path fill="currentColor" d="M4 6h16v2H4V6Zm0 5h16v2H4v-2Zm0 5h16v2H4v-2Z"/></svg>
      </button>

      <div class="nb-tb-title">
        <div class="t">{{ $title }}</div>
        <div class="s">{{ $sub }}</div>
      </div>
    </div>

    <div class="nb-tb-right">
      <button class="nb-tb-search-pill" type="button" data-nb-open-search title="Cari menu (Ctrl+K)">
        <svg class="nb-tb-ico" viewBox="0 0 24 24"><use href="#nb-icon-search"/></svg>
        <span class="txt" style="font-size:12px;color:var(--nb-muted);">Cari menu, buku, atau halaman...</span>
        <span class="nb-tb-kbd">Ctrl K</span>
      </button>

      <a class="nb-tb-btn" href="{{ $notifIndexUrl }}" title="Notifikasi">
        <svg class="nb-tb-ico" viewBox="0 0 24 24"><use href="#nb-icon-bell"/></svg>
        <span data-nb-notif-badge
              data-nb-notif-url="{{ $notifCountUrl }}"
              style="
                display:none;
                min-width:20px;height:20px;
                border-radius:999px;
                padding:0 6px;
                font-size:12px;
                font-weight:800;
                background:rgba(239,68,68,.95);
                color:#fff;
                align-items:center;
                justify-content:center;
              ">0</span>
      </a>

      <div class="nb-tb-user">
        <div class="nb-tb-avatar">{{ $initials }}</div>
        <div class="nb-tb-user-meta">
          <div class="n">{{ $name }}</div>
          <div class="r">{{ $roleLabel }}</div>
        </div>

        <form method="POST" action="{{ route('keluar') }}" style="margin:0;">
          @csrf
          <button class="nb-tb-btn" type="submit" title="Keluar" style="height:34px;padding:0 10px;border-radius:12px;box-shadow:none;">
            <svg class="nb-tb-ico" viewBox="0 0 24 24"><path fill="currentColor" d="M10 17v-2h7v-6h-7V7l-5 5 5 5Zm10-15a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2v-3h2v3h12V4H8v3H6V4a2 2 0 0 1 2-2h12Z"/></svg>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
