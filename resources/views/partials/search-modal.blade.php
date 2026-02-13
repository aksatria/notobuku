{{-- resources/views/partials/search-modal.blade.php --}}
@php
  $user = auth()->user();
  $role = $user->role ?? 'member';
  $isStaff = in_array($role, ['super_admin','admin','staff'], true);
  $isMember = !$isStaff;

  $menu = [];

  // ===== Umum (semua role)
  $menu[] = ['label'=>'Beranda', 'hint'=>'Ringkasan & akses cepat', 'route'=>'beranda', 'group'=>'Umum', 'icon'=>'#nb-icon-home'];
  $menu[] = ['label'=>'Katalog', 'hint'=>'Cari & jelajahi koleksi buku', 'route'=>'katalog.index', 'group'=>'Perpustakaan', 'icon'=>'#nb-icon-book'];
  $menu[] = ['label'=>'Komunitas', 'hint'=>'Feed komunitas literasi', 'route'=>'komunitas.feed', 'group'=>'Komunitas', 'icon'=>'#nb-icon-chat'];
  $menu[] = ['label'=>'Buat Postingan', 'hint'=>'Tulis cerita / unggah gambar', 'route'=>'komunitas.buat', 'group'=>'Komunitas', 'icon'=>'#nb-icon-plus'];

  // ===== Member shortcuts
  if ($isMember) {
    $menu[] = ['label'=>'Dashboard Member', 'hint'=>'Ringkasan pinjaman & pengingat', 'route'=>'member.dashboard', 'group'=>'Member Area', 'icon'=>'#nb-icon-home'];
    $menu[] = ['label'=>'Pustakawan Digital', 'hint'=>'Minta rekomendasi & ajukan buku', 'route'=>'member.pustakawan.digital', 'group'=>'Member Area', 'icon'=>'#nb-icon-chat'];
    $menu[] = ['label'=>'Pinjaman Saya', 'hint'=>'Status pinjaman & jatuh tempo', 'route'=>'member.pinjaman', 'group'=>'Member Area', 'icon'=>'#nb-icon-rotate'];
    $menu[] = ['label'=>'Reservasi Saya', 'hint'=>'Kelola reservasi buku', 'route'=>'member.reservasi', 'group'=>'Member Area', 'icon'=>'#nb-icon-book'];
    $menu[] = ['label'=>'Notifikasi', 'hint'=>'Pengingat jatuh tempo & keterlambatan', 'route'=>'member.notifikasi', 'group'=>'Member Area', 'icon'=>'#nb-icon-bell'];
  }

  // ===== Staff/Admin shortcuts
  if ($isStaff) {
    $menu[] = ['label'=>'Transaksi', 'hint'=>'Pinjam / kembali / perpanjang', 'route'=>'transaksi.index', 'group'=>'Operasional', 'icon'=>'#nb-icon-rotate'];
    $menu[] = ['label'=>'Dashboard Sirkulasi', 'hint'=>'Ringkasan aktivitas sirkulasi', 'route'=>'transaksi.dashboard', 'group'=>'Operasional', 'icon'=>'#nb-icon-rotate'];
    $menu[] = ['label'=>'Anggota', 'hint'=>'Data member & aktivitas', 'route'=>'anggota.index', 'group'=>'Admin', 'icon'=>'#nb-icon-users'];
    $menu[] = ['label'=>'Permintaan Pengadaan', 'hint'=>'Review & approve permintaan', 'route'=>'acquisitions.requests.index', 'group'=>'Pengadaan', 'icon'=>'#nb-icon-clipboard'];
    $menu[] = ['label'=>'Purchase Order', 'hint'=>'Buat & kelola PO', 'route'=>'acquisitions.pos.index', 'group'=>'Pengadaan', 'icon'=>'#nb-icon-book'];
    $menu[] = ['label'=>'Vendor', 'hint'=>'Manajemen vendor', 'route'=>'acquisitions.vendors.index', 'group'=>'Pengadaan', 'icon'=>'#nb-icon-users'];
    $menu[] = ['label'=>'Budget', 'hint'=>'Anggaran pengadaan', 'route'=>'acquisitions.budgets.index', 'group'=>'Pengadaan', 'icon'=>'#nb-icon-chart'];
  }

  $payload = collect($menu)->map(function($m){
    return [
      'label' => $m['label'],
      'hint'  => $m['hint'],
      'group' => $m['group'],
      'icon'  => $m['icon'],
      'href'  => route($m['route']),
    ];
  })->values();
@endphp

<style>
  /* Overlay */
  .nb-search-overlay{
    position:fixed;
    inset:0;
    z-index:100;
    display:none;
    background:rgba(2,6,23,.55);
    backdrop-filter: blur(6px);
  }
  .nb-search-overlay.show{ display:block; }

  .nb-search{
    width:min(860px, calc(100% - 24px));
    margin: 90px auto 0;
    border-radius:22px;
    overflow:hidden;
    border:1px solid var(--nb-border);
    background: var(--nb-surface);
    box-shadow: var(--nb-shadow);
  }
  html.dark .nb-search{ background: rgba(15,27,46,.95); }

  .nb-search-head{
    padding:14px;
    border-bottom:1px solid var(--nb-border);
    display:flex;
    gap:10px;
    align-items:center;
  }
  .nb-search-input{
    flex:1;
    border:none;
    outline:none;
    background:transparent;
    font-size:14px;
    color:var(--nb-text);
    font-weight:600;
  }
  .nb-search-kbd{
    font-size:12px;
    color:var(--nb-muted);
    border:1px solid var(--nb-border);
    padding:2px 8px;
    border-radius:10px;
    background:rgba(255,255,255,.6);
  }
  html.dark .nb-search-kbd{ background:rgba(15,27,46,.6); }

  .nb-search-body{
    max-height: 62vh;
    overflow:auto;
    padding:10px;
  }

  .nb-search-item{
    display:flex;
    align-items:flex-start;
    gap:12px;
    padding:10px 12px;
    border-radius:16px;
    cursor:pointer;
  }
  .nb-search-item:hover{ background: rgba(30,136,229,.08); }
  .nb-search-item.active{ background: rgba(30,136,229,.12); }

  .nb-search-ico{
    width:36px;height:36px;
    border-radius:14px;
    display:flex;align-items:center;justify-content:center;
    background: rgba(30,136,229,.12);
    color: var(--nb-blue);
    flex-shrink:0;
  }
  .nb-search-meta{ min-width:0; flex:1; }
  .nb-search-meta .l{ font-weight:800; font-size:13px; }
  .nb-search-meta .h{ font-size:12px; color:var(--nb-muted); margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

  .nb-search-group{
    padding:10px 12px 6px;
    font-size:11px;
    font-weight:800;
    letter-spacing:.08em;
    text-transform:uppercase;
    color:var(--nb-muted);
  }
</style>

<div class="nb-search-overlay" data-nb-search-overlay>
  <div class="nb-search" role="dialog" aria-modal="true" aria-label="Pencarian">
    <div class="nb-search-head">
      <svg style="width:18px;height:18px;color:var(--nb-muted)"><use href="#nb-icon-search"/></svg>
      <input class="nb-search-input" data-nb-search-input placeholder="Cari menu / halaman…" autocomplete="off">
      <span class="nb-search-kbd">ESC</span>
    </div>
    <div class="nb-search-body" data-nb-search-list></div>
  </div>
</div>

<script>
(() => {
  const overlay = document.querySelector('[data-nb-search-overlay]');
  const input = document.querySelector('[data-nb-search-input]');
  const list  = document.querySelector('[data-nb-search-list]');
  if(!overlay || !input || !list) return;

  const DATA = @json($payload);

  const state = {
    items: [],
    activeIndex: 0,
  };

  const open = () => {
    overlay.classList.add('show');
    setTimeout(() => input.focus(), 10);
    render('');
  };

  const close = () => {
    overlay.classList.remove('show');
    input.value = '';
  };

  const groupBy = (arr) => {
    const map = new Map();
    arr.forEach(it => {
      const g = it.group || 'Lainnya';
      if(!map.has(g)) map.set(g, []);
      map.get(g).push(it);
    });
    return [...map.entries()];
  };

  const render = (q) => {
    const qq = (q || '').toLowerCase().trim();
    const filtered = DATA.filter(it => {
      if(!qq) return true;
      return (it.label || '').toLowerCase().includes(qq) || (it.hint || '').toLowerCase().includes(qq) || (it.group || '').toLowerCase().includes(qq);
    });

    state.items = filtered;
    state.activeIndex = 0;

    const grouped = groupBy(filtered);
    list.innerHTML = grouped.map(([g, items]) => {
      const rows = items.map((it, idx) => {
        // index global
        const globalIndex = filtered.indexOf(it);
        return `
          <a class="nb-search-item ${globalIndex === 0 ? 'active' : ''}" data-idx="${globalIndex}" href="${it.href}">
            <div class="nb-search-ico"><svg style="width:18px;height:18px"><use href="${it.icon}"/></svg></div>
            <div class="nb-search-meta">
              <div class="l">${it.label}</div>
              <div class="h">${it.hint}</div>
            </div>
          </a>
        `;
      }).join('');
      return `<div class="nb-search-group">${g}</div>${rows}`;
    }).join('');

    syncActive();
  };

  const syncActive = () => {
    const els = [...list.querySelectorAll('.nb-search-item')];
    els.forEach(el => el.classList.remove('active'));
    const active = els.find(el => Number(el.dataset.idx) === state.activeIndex);
    if(active){
      active.classList.add('active');
      active.scrollIntoView({ block: 'nearest' });
    }
  };

  // hooks
  document.querySelectorAll('[data-nb-open-search]').forEach(btn => btn.addEventListener('click', open));
  overlay.addEventListener('click', (e) => { if(e.target === overlay) close(); });

  input.addEventListener('input', () => render(input.value));

  document.addEventListener('keydown', (e) => {
    const isOpen = overlay.classList.contains('show');
    const isK = (e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'k');

    if(isK){
      e.preventDefault();
      if(!isOpen) open();
      return;
    }

    if(!isOpen) return;

    if(e.key === 'ArrowDown'){
      e.preventDefault();
      if(state.items.length){
        state.activeIndex = Math.min(state.activeIndex + 1, state.items.length - 1);
        syncActive();
      }
    }

    if(e.key === 'ArrowUp'){
      e.preventDefault();
      if(state.items.length){
        state.activeIndex = Math.max(state.activeIndex - 1, 0);
        syncActive();
      }
    }

    if(e.key === 'Enter'){
      if(!state.items.length) return;
      e.preventDefault();
      const it = state.items[state.activeIndex];
      if(it?.href) window.location.href = it.href;
    }

    if(e.key === 'Escape'){
      e.preventDefault();
      close();
    }
  });

})();
</script>
