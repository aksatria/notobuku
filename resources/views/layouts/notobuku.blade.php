{{-- resources/views/layouts/notobuku.blade.php --}}
<!doctype html>
<html lang="id" class="nb-root">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#1e88e5">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', config('app.name','NOTOBUKU'))</title>
  @stack('head')

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  @php
    $nbViteReady = file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json'));
  @endphp
  @if($nbViteReady)
    @vite(['resources/css/app.css','resources/js/app.js'])
  @endif

  <style>
    :root{
      --nb-bg:#f4f6fb;
      --nb-surface:#ffffff;
      --nb-text:#111827;
      --nb-muted:#6b7280;
      --nb-border:rgba(17,24,39,.10);

      --nb-blue:#1e88e5;
      --nb-blue-2:#1565c0;
      --nb-green:#2ecc71;
      --nb-green-2:#1fa85a;

      --nb-sidebar:#111827;
      --nb-sidebar-2:#0b1220;
      --nb-sidebar-text:#e5e7eb;
      --nb-sidebar-muted:rgba(229,231,235,.68);

      --nb-radius:18px;
      --nb-radius-sm:14px;

      --nb-shadow: 0 10px 30px rgba(17,24,39,.12);
      --nb-shadow-soft: 0 2px 10px rgba(17,24,39,.08);

      --nb-topbar-h: 70px;
      --nb-sidebar-w: 300px;
      --nb-sidebar-w-collapsed: 92px;
    }

    html.dark{
      --nb-bg:#0b1220;
      --nb-surface:#0f1b2e;
      --nb-text:#e5e7eb;
      --nb-muted:#a8b3c7;
      --nb-border:rgba(226,232,240,.12);

      --nb-sidebar:#0a0f1c;
      --nb-sidebar-2:#060a14;
      --nb-sidebar-text:#eef2ff;
      --nb-sidebar-muted:rgba(238,242,255,.62);

      --nb-shadow: 0 16px 40px rgba(0,0,0,.40);
      --nb-shadow-soft: 0 6px 18px rgba(0,0,0,.30);
    }

    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family:Poppins, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background:var(--nb-bg);
      color:var(--nb-text);
      font-weight:400;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      text-rendering: optimizeLegibility;
      pointer-events:auto !important;
    }

    a{ color:inherit; text-decoration:none; }
    .nb-skip-link{
      position:absolute;
      left:16px;
      top:-42px;
      z-index:2000;
      background:#0f172a;
      color:#fff;
      border-radius:10px;
      padding:8px 12px;
      font-weight:600;
      border:1px solid rgba(255,255,255,.25);
    }
    .nb-skip-link:focus{
      top:12px;
      outline:2px solid #38bdf8;
      outline-offset:2px;
    }
    .nb-unlock-btn{
      position:fixed;
      right:14px;
      bottom:14px;
      z-index:2147483647;
      border:1px solid rgba(239,68,68,.45);
      background:rgba(127,29,29,.94);
      color:#fff;
      border-radius:999px;
      padding:8px 12px;
      font-size:12px;
      font-weight:600;
      cursor:pointer;
      display:none;
      pointer-events:auto;
    }
    .nb-container{ max-width:1300px; margin:0 auto; padding:0 22px; }

    /* =====================================================
       GLOBAL TEXT NORMALIZER (biar halaman lain tidak tebal)
       ===================================================== */
    h1,h2,h3,h4,h5,h6{ font-weight:600; margin:0; }
    p{ margin:0; }
    b,strong{ font-weight:600; } /* jangan 700/800 */
    .nb-muted{ color:var(--nb-muted); font-weight:400; }

    /* Layout shell */
    .nb-shell{ min-height:100vh; display:flex; }

    /* ===== SIDEBAR (PCM-style) ===== */
    .nb-sidebar{
      width:var(--nb-sidebar-w);
      padding:14px;
      height:100vh;
      position:sticky;
      top:0;
      display:none;
      flex-shrink:0;
    }
    html.dark .nb-sidebar{ filter: drop-shadow(0 0 12px rgba(30,136,229,.20)); }

    .nb-sidebar-wrap{
      height:calc(100vh - 28px);
      border-radius:22px;
      background:linear-gradient(180deg, var(--nb-sidebar), var(--nb-sidebar-2));
      box-shadow: var(--nb-shadow);
      border:1px solid rgba(255,255,255,.08);
      overflow:hidden; /* radius aman */
      display:flex;
      flex-direction:column;
      gap:10px;
      padding:14px;
    }

    /* Desktop show */
    @media (min-width: 1024px){
      .nb-sidebar{ display:block; }
      .nb-main{ flex:1; }
    }

    /* Desktop collapse */
    body.nb-sidebar-collapsed .nb-sidebar{ width:var(--nb-sidebar-w-collapsed); }
    body.nb-sidebar-collapsed .nb-sb-label,
    body.nb-sidebar-collapsed .nb-sb-sub,
    body.nb-sidebar-collapsed .nb-sb-hint,
    body.nb-sidebar-collapsed .nb-sb-role,
    body.nb-sidebar-collapsed .nb-sb-quick-kbd{
      display:none !important;
    }
    body.nb-sidebar-collapsed .nb-sb-item{
      justify-content:center;
      padding:12px 10px !important;
    }
    body.nb-sidebar-collapsed .nb-sb-item .nb-sb-ico{ margin:0 !important; }
    body.nb-sidebar-collapsed .nb-sb-cta{
      justify-content:center;
      padding:12px 10px !important;
    }
    body.nb-sidebar-collapsed .nb-sb-cta .nb-sb-cta-right{ display:none !important; }
    body.nb-sidebar-collapsed .nb-sb-user{ justify-content:center; }
    body.nb-sidebar-collapsed .nb-sb-user .nb-sb-user-meta{ display:none !important; }

    /* Mobile offcanvas sidebar */
    .nb-sidebar-overlay{
      position:fixed;
      inset:0;
      background:rgba(2,6,23,.60);
      z-index:80;
      display:none;
      pointer-events:none;
    }
    .nb-sidebar-mobile{
      position:fixed;
      left:0; top:0; bottom:0;
      width:min(var(--nb-sidebar-w), 92vw);
      padding:14px;
      z-index:90;
      transform: translateX(-110%);
      transition: transform .18s ease;
      display:block;
    }
    .nb-sidebar-mobile .nb-sidebar-wrap{ height:calc(100vh - 28px); }

    body.nb-sidebar-open .nb-sidebar-overlay{
      display:block;
      pointer-events:auto;
    }
    body.nb-sidebar-open .nb-sidebar-mobile{ transform: translateX(0); }
    body.nb-sidebar-open{ overflow:hidden; }

    @media (min-width: 1024px){
      .nb-sidebar-overlay, .nb-sidebar-mobile{ display:none !important; }
      body.nb-sidebar-open{ overflow:auto; }
    }

    /* ===== MAIN ===== */
    .nb-main{ flex:1; min-width:0; display:flex; flex-direction:column; }

    .nb-topbar{
      height:var(--nb-topbar-h);
      position:sticky;
      top:0;
      z-index:30;
      padding:14px 0;
      background:linear-gradient(180deg, rgba(244,246,251,.92), rgba(244,246,251,.70));
      backdrop-filter:saturate(140%) blur(10px);
      border-bottom:1px solid var(--nb-border);
    }
    html.dark .nb-topbar{
      background:linear-gradient(180deg, rgba(11,18,32,.92), rgba(11,18,32,.70));
    }

    .nb-content{ padding:18px 0 90px; }
    @media (min-width:1024px){ .nb-content{ padding-bottom:26px; } }

    /* Helpers */
    .nb-stack{ display:flex; flex-direction:column; gap:14px; }
    .nb-row{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .nb-row-left{ display:flex; align-items:center; gap:12px; min-width:0; }
    .nb-row-right{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .nb-clip{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    /* =====================================================
       BUTTONS — lighter (default) + no animations
       ===================================================== */
    .nb-btn{
      border:1px solid var(--nb-border);
      background:var(--nb-surface);
      color:var(--nb-text);
      border-radius:var(--nb-radius-sm);
      padding:10px 14px;
      font-weight:520; /* lebih ringan */
      font-size:14px;
      cursor:pointer;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      user-select:none;
      white-space:nowrap;
      line-height:1.2;
      transition:none !important;
    }
    a:focus-visible,
    button:focus-visible,
    input:focus-visible,
    select:focus-visible,
    textarea:focus-visible{
      outline:2px solid #38bdf8;
      outline-offset:2px;
    }

    .nb-btn-primary{
      background:linear-gradient(90deg,#1e88e5,#1565c0);
      border-color:transparent;
      color:#fff;
      box-shadow: 0 10px 18px rgba(30,136,229,.22);
      font-weight:560;
    }

    .nb-btn-success{
      background:linear-gradient(90deg,#2ecc71,#1fa85a);
      border-color:transparent;
      color:#0b2d18;
      box-shadow: 0 10px 18px rgba(46,204,113,.20);
      font-weight:560;
    }

    .nb-iconbtn{
      width:44px; height:44px;
      border-radius:var(--nb-radius-sm);
      border:1px solid var(--nb-border);
      background:var(--nb-surface);
      display:inline-flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
      transition:none !important;
    }

    /* Bottomnav */
    .nb-bottomnav{
      position:fixed; left:0; right:0; bottom:0;
      z-index:40;
      background:rgba(255,255,255,.92);
      backdrop-filter:saturate(140%) blur(10px);
      border-top:1px solid var(--nb-border);
      padding:10px 12px 12px;
    }
    html.dark .nb-bottomnav{ background:rgba(11,18,32,.88); }
    @media (min-width:1024px){ .nb-bottomnav{ display:none; } }

    /* Modal (search overlay) */
    .nb-modal-overlay{
      position:fixed; inset:0;
      background:rgba(2,6,23,.60);
      z-index:60;
      display:none;
      align-items:flex-start;
      justify-content:center;
      padding:84px 12px 12px;
      pointer-events:none;
    }
    .nb-modal-overlay.show{
      display:flex;
      pointer-events:auto;
    }
    .nb-modal{
      width:min(860px, 100%);
      border-radius:20px;
      overflow:hidden;
      border:1px solid var(--nb-border);
      background:var(--nb-surface);
      box-shadow:var(--nb-shadow);
    }

    /* FAB Switch Cabang */
    .nb-fab-switch{
      position: fixed;
      right: 16px;
      bottom: 86px;
      z-index: 60;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 12px 14px;
      border-radius: 999px;
      background: #2563eb;
      color: #fff;
      text-decoration: none;
      font-weight:560;
      box-shadow: 0 10px 24px rgba(37,99,235,.25);
      transition:none !important;
    }
    html.dark .nb-fab-switch{ background:#3b82f6; }
    @media (min-width:1024px){
      .nb-fab-switch{ bottom: 18px; }
    }

    /* =====================================================
       HARD OVERRIDE: REMOVE ALL HOVER / FOCUS EFFECTS ✅✅✅
       (biar menang dari CSS halaman & partials)
       ===================================================== */
    @media (hover:hover){
      a:hover, a:focus, a:focus-visible, a:active{
        color:inherit !important;
        text-decoration:none !important;
        outline:none !important;
        filter:none !important;
        transform:none !important;
        box-shadow:none !important;
        opacity:1 !important;
      }

      button:hover, button:focus, button:focus-visible, button:active,
      [role="button"]:hover, [role="button"]:focus, [role="button"]:focus-visible, [role="button"]:active{
        outline:none !important;
        filter:none !important;
        transform:none !important;
        box-shadow:none !important;
        opacity:1 !important;
      }

      /* NB buttons / icons / links */
      .nb-btn:hover, .nb-btn:focus, .nb-btn:focus-visible, .nb-btn:active{
        background:var(--nb-surface) !important;
        color:var(--nb-text) !important;
        border-color:var(--nb-border) !important;
        box-shadow:none !important;
        transform:none !important;
        filter:none !important;
        opacity:1 !important;
        outline:none !important;
      }

      .nb-btn-primary:hover, .nb-btn-primary:focus, .nb-btn-primary:focus-visible, .nb-btn-primary:active{
        background:linear-gradient(90deg,#1e88e5,#1565c0) !important;
        color:#fff !important;
        border-color:transparent !important;
        box-shadow: 0 10px 18px rgba(30,136,229,.22) !important;
      }

      .nb-btn-success:hover, .nb-btn-success:focus, .nb-btn-success:focus-visible, .nb-btn-success:active{
        background:linear-gradient(90deg,#2ecc71,#1fa85a) !important;
        border-color:transparent !important;
        color:#0b2d18 !important;
        box-shadow: 0 10px 18px rgba(46,204,113,.20) !important;
      }

      .nb-iconbtn:hover, .nb-iconbtn:focus, .nb-iconbtn:focus-visible, .nb-iconbtn:active{
        background:var(--nb-surface) !important;
        border-color:var(--nb-border) !important;
        box-shadow:none !important;
        transform:none !important;
        filter:none !important;
        opacity:1 !important;
        outline:none !important;
      }

      /* Kalau ada icon button versi halaman (mis. .nb-icon-btn di page) */
      .nb-icon-btn:hover, .nb-icon-btn:focus, .nb-icon-btn:focus-visible, .nb-icon-btn:active{
        background:inherit !important;
        box-shadow:none !important;
        transform:none !important;
        filter:none !important;
        opacity:1 !important;
        outline:none !important;
      }

      /* Sidebar hover overrides (partials/sidebar) */
      .nb-sb-cta:hover,
      .nb-sb-group-head:hover,
      .nb-sb-sub a:hover,
      .nb-sb-item-link:hover,
      .nb-sb-reset:hover{
        background:inherit !important;
        box-shadow:none !important;
        transform:none !important;
        filter:none !important;
        opacity:1 !important;
      }

      .nb-sb-cta:hover .nb-sb-cta-text,
      .nb-sb-cta:hover .nb-sb-cta-right,
      .nb-sb-cta:hover svg{
        color:inherit !important;
        fill: currentColor !important;
      }

      /* scrollbar thumb hover (sidebar) — kunci biar tidak berubah */
      .nb-sb-scroll::-webkit-scrollbar-thumb:hover{
        background: linear-gradient(180deg, #1e88e5, #1565c0) !important;
        background-clip: content-box !important;
      }

      /* Bottomnav hover overrides */
      .nb-bottomnav a:hover,
      .nb-bottomnav button:hover{
        background:inherit !important;
        box-shadow:none !important;
        transform:none !important;
        filter:none !important;
        opacity:1 !important;
      }

      /* Search modal hover overrides (kalau ada style hover di partial) */
      [data-nb-search-modal] a:hover,
      [data-nb-search-modal] button:hover{
        background:inherit !important;
        box-shadow:none !important;
        transform:none !important;
        filter:none !important;
        opacity:1 !important;
      }

      /* FAB hover overrides */
      .nb-fab-switch:hover, .nb-fab-switch:focus, .nb-fab-switch:focus-visible, .nb-fab-switch:active{
        box-shadow: 0 10px 24px rgba(37,99,235,.25) !important;
        transform:none !important;
        filter:none !important;
        opacity:1 !important;
        outline:none !important;
      }
      html.dark .nb-fab-switch:hover, html.dark .nb-fab-switch:focus, html.dark .nb-fab-switch:focus-visible, html.dark .nb-fab-switch:active{
        background:#3b82f6 !important;
      }
    }

    /* =====================================================
       SIDEBAR utility font weights (biar tidak bold)
       ===================================================== */
    .nb-sb-fw-700{ font-weight:600 !important; }
    .nb-sb-fw-600{ font-weight:560 !important; }
    .nb-sb-fw-500{ font-weight:500 !important; }
  </style>
</head>

<body>
  <script>
    (function () {
      const cleanup = function () {
        document.body.classList.remove('nb-search-open', 'nb-sidebar-open', 'modal-open', 'overflow-hidden');
        document.documentElement.classList.remove('overflow-hidden');
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
        document.body.style.pointerEvents = '';

        // Hard reset known blocking layers that can get stuck after logout/login or bfcache restore.
        document.querySelectorAll(
          '.nb-search-overlay, .nb-sidebar-overlay, .nb-modal-overlay, .nb-modal-backdrop, .dt-backdrop, .nb-k-shortcuts-backdrop'
        ).forEach(function (el) {
          el.classList.remove('show', 'open', 'is-open');
          el.style.display = 'none';
          el.style.pointerEvents = 'none';
          el.setAttribute('aria-hidden', 'true');
        });
      };
      try {
        cleanup();
        document.addEventListener('DOMContentLoaded', cleanup, { once: true });
        window.addEventListener('pageshow', cleanup);
        window.addEventListener('focus', function () { setTimeout(cleanup, 0); });
      } catch (_) {}
    })();
  </script>
  <a class="nb-skip-link" href="#nb-main-content">Lewati ke konten utama</a>
  <button type="button" class="nb-unlock-btn" data-nb-unlock-ui>Unlock UI</button>
  @include('partials.flash')

  {{-- Desktop sidebar --}}
  <div class="nb-shell">
    <aside class="nb-sidebar" aria-label="Sidebar Desktop">
      <div class="nb-sidebar-wrap">
        @include('partials.sidebar')
      </div>
    </aside>

    <main class="nb-main">
      <header class="nb-topbar">
        @include('partials.topbar')
      </header>

      <div class="nb-content" id="nb-main-content">
        <div class="nb-container">
          @yield('content')
        </div>
      </div>
    </main>
  </div>

  {{-- Mobile sidebar overlay + panel --}}
  <div class="nb-sidebar-overlay" data-nb-sidebar-overlay></div>
  <aside class="nb-sidebar-mobile" aria-label="Sidebar Mobile">
    <div class="nb-sidebar-wrap">
      @include('partials.sidebar')
    </div>
  </aside>

  @include('partials.bottomnav')
  @include('partials.search-modal')
  @include('partials.icons')

  <script>
    window.nbApp = (function(){
      const state = {
        theme: 'light',
        modalOpen: false,
        sidebarOpen: false,
        sidebarCollapsed: false,
      };

      function clearGlobalUiLocks(){
        // Clear body/html lock state
        document.body.classList.remove('nb-sidebar-open', 'modal-open', 'overflow-hidden');
        document.body.classList.remove('nb-search-open');
        document.documentElement.classList.remove('overflow-hidden');
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
        document.body.style.pointerEvents = '';
        state.modalOpen = false;
        state.sidebarOpen = false;

        // Close common overlays/modals if they got stuck
        document.querySelectorAll(
          '[data-nb-search-overlay], [data-nb-sidebar-overlay], .nb-modal-overlay, .nb-search-overlay, .nb-sidebar-overlay'
        ).forEach((el) => {
          el.classList.remove('show', 'open', 'is-open');
          if (el.hasAttribute('data-open')) el.setAttribute('data-open', '0');
          if (el.hasAttribute('aria-hidden')) el.setAttribute('aria-hidden', 'true');
          el.style.removeProperty('display');
          el.style.removeProperty('pointer-events');
        });
      }

      function applyTheme(theme){
        state.theme = theme === 'dark' ? 'dark' : 'light';
        const root = document.documentElement;
        if(state.theme === 'dark') root.classList.add('dark');
        else root.classList.remove('dark');
        localStorage.setItem('nb-theme', state.theme);

        const iconId = state.theme === 'dark' ? '#nb-icon-moon' : '#nb-icon-sun';
        document.querySelectorAll('[data-nb-theme-icon]').forEach((wrap) => {
          wrap.innerHTML = `
            <svg style="width:18px;height:18px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
              <use href="${iconId}"></use>
            </svg>
          `;
        });
      }

      function toggleTheme(){ applyTheme(state.theme === 'dark' ? 'light' : 'dark'); }

      function openSearch(){
        const overlay = document.querySelector('[data-nb-search-overlay]');
        if(!overlay){
          const fallbackInput =
            document.querySelector('#nbSearchInput') ||
            document.querySelector('input[name="q"]') ||
            document.querySelector('input[type="search"]');
          if (fallbackInput) {
            fallbackInput.focus();
            if (typeof fallbackInput.select === 'function') fallbackInput.select();
          }
          return;
        }
        document.body.classList.add('nb-search-open');
        overlay.classList.add('show');
        state.modalOpen = true;
        const input = overlay.querySelector('input[data-nb-search-input]');
        setTimeout(() => input && input.focus(), 10);
      }

      function closeSearch(){
        const overlay = document.querySelector('[data-nb-search-overlay]');
        if(!overlay) return;
        overlay.classList.remove('show');
        document.body.classList.remove('nb-search-open');
        state.modalOpen = false;
      }

      /* ===== Sidebar (PCM-style) ===== */
      function setCollapsed(v){
        state.sidebarCollapsed = !!v;
        document.body.classList.toggle('nb-sidebar-collapsed', state.sidebarCollapsed);
        localStorage.setItem('nb-sidebar-collapsed', state.sidebarCollapsed ? '1' : '0');
      }

      function toggleCollapsed(){
        if(window.innerWidth < 1024) return;
        setCollapsed(!state.sidebarCollapsed);
      }

      function openSidebar(){
        if(window.innerWidth >= 1024) return;
        state.sidebarOpen = true;
        document.body.classList.add('nb-sidebar-open');
      }

      function closeSidebar(){
        state.sidebarOpen = false;
        document.body.classList.remove('nb-sidebar-open');
      }

      function toggleSidebar(){
        if(window.innerWidth >= 1024) toggleCollapsed();
        else{
          if(document.body.classList.contains('nb-sidebar-open')) closeSidebar();
          else openSidebar();
        }
      }

      function bind(){
        // Failsafe: pastikan overlay tidak nyangkut setelah navigasi/error JS sebelumnya.
        clearGlobalUiLocks();
        setTimeout(clearGlobalUiLocks, 120);
        setTimeout(clearGlobalUiLocks, 450);
        setTimeout(clearGlobalUiLocks, 1200);

        // init theme
        applyTheme(localStorage.getItem('nb-theme') === 'dark' ? 'dark' : 'light');

        // init collapsed state
        state.sidebarCollapsed = (localStorage.getItem('nb-sidebar-collapsed') === '1');
        document.body.classList.toggle('nb-sidebar-collapsed', state.sidebarCollapsed);

        document.addEventListener('click', (e) => {
          if(e.target.closest('[data-nb-toggle-theme]')){ e.preventDefault(); toggleTheme(); }
          if(e.target.closest('[data-nb-open-search]')){ e.preventDefault(); openSearch(); }

          if(e.target.closest('[data-nb-sidebar-toggle]')){ e.preventDefault(); toggleSidebar(); }
          if(e.target.closest('[data-nb-sidebar-open]')){ e.preventDefault(); openSidebar(); }
          if(e.target.closest('[data-nb-sidebar-close]')){ e.preventDefault(); closeSidebar(); }

          if(e.target.closest('[data-nb-sidebar-overlay]')){ e.preventDefault(); closeSidebar(); }

          // Emergency close if user clicks dimmed backdrop from unknown stuck overlay.
          if (e.target.classList && (e.target.classList.contains('nb-search-overlay') || e.target.classList.contains('nb-sidebar-overlay') || e.target.classList.contains('nb-modal-overlay'))) {
            e.preventDefault();
            closeSearch();
            closeSidebar();
            clearGlobalUiLocks();
          }
        });

        // Hardening: klik menu sidebar/bottomnav harus selalu pindah halaman.
        // Ini mencegah kasus intermiten ketika handler lain menahan default anchor.
        document.addEventListener('click', (e) => {
          const link = e.target.closest('.nb-sb-item-link, .nb-sb-sub a, .nb-bottomnav a');
          if (!link) return;
          if (e.defaultPrevented) return;
          if (e.button !== 0) return; // only left click
          if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
          const href = link.getAttribute('href') || '';
          if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
          if (link.getAttribute('target') === '_blank') return;

          e.preventDefault();
          clearGlobalUiLocks();
          window.location.assign(link.href);
        }, true);

        // click outside search modal closes it
        document.addEventListener('click', (e) => {
          const overlay = e.target.closest('[data-nb-search-overlay]');
          if(!overlay) return;
          if(!e.target.closest('[data-nb-search-modal]')) closeSearch();
        });

        document.addEventListener('keydown', (e) => {
          const isMac = navigator.platform.toUpperCase().includes('MAC');
          const k = (e.key || '').toLowerCase();

          // Ctrl+K search
          if((isMac ? e.metaKey : e.ctrlKey) && k === 'k'){ e.preventDefault(); openSearch(); }

          // Ctrl+/ toggle sidebar
          if((isMac ? e.metaKey : e.ctrlKey) && k === '/'){ e.preventDefault(); toggleSidebar(); }

          // Escape close modal / sidebar mobile
          if(e.key === 'Escape'){
            if(state.modalOpen){ e.preventDefault(); closeSearch(); }
            if(document.body.classList.contains('nb-sidebar-open')){ e.preventDefault(); closeSidebar(); }
          }
        });

        // responsive safety
        window.addEventListener('resize', () => {
          if(window.innerWidth >= 1024){
            closeSidebar();
            document.body.style.overflow = '';
          }
        });

        // Guard: jika ada overlay tidak valid, otomatis nonaktifkan agar klik halaman tetap hidup.
        const guardOverlayInteraction = () => {
          document.querySelectorAll('.nb-search-overlay, .nb-sidebar-overlay, .nb-modal-overlay').forEach((el) => {
            const isSidebar = el.classList.contains('nb-sidebar-overlay');
            const shouldBeOpen = isSidebar
              ? document.body.classList.contains('nb-sidebar-open')
              : el.classList.contains('show');
            el.style.pointerEvents = shouldBeOpen ? 'auto' : 'none';
          });
        };
        const isAllowedBlockingLayer = (el) => {
          if (!el || !(el instanceof HTMLElement)) return false;
          if (el.matches('.nb-search-overlay.show')) return true;
          if (el.matches('.nb-modal-overlay.show')) return true;
          if (el.matches('.nb-modal[data-open="1"]')) return true;
          if (el.matches('.nb-modal.open')) return true;
          if (el.matches('.nb-sidebar-overlay') && document.body.classList.contains('nb-sidebar-open')) return true;
          return false;
        };
        const findFullscreenBlockerAt = (el) => {
          if (!el || !(el instanceof HTMLElement)) return null;
          const blocker = el.closest(
            '.nb-search-overlay, .nb-sidebar-overlay, .nb-modal-overlay, .nb-modal-backdrop, .dt-backdrop, .nb-k-shortcuts-backdrop'
          );
          if (!blocker || !(blocker instanceof HTMLElement)) return null;
          const rect = blocker.getBoundingClientRect();
          const vw = window.innerWidth || document.documentElement.clientWidth || 0;
          const vh = window.innerHeight || document.documentElement.clientHeight || 0;
          const covers = rect.width >= (vw * 0.85) && rect.height >= (vh * 0.85);
          return covers ? blocker : null;
        };
        const neutralizeStuckBlockers = () => {
          const vw = window.innerWidth || document.documentElement.clientWidth || 0;
          const vh = window.innerHeight || document.documentElement.clientHeight || 0;
          let foundBlocker = false;
          document.querySelectorAll('body *').forEach((el) => {
            if (!(el instanceof HTMLElement)) return;
            const cs = window.getComputedStyle(el);
            if (cs.display === 'none' || cs.visibility === 'hidden') return;
            if (cs.pointerEvents === 'none') return;
            const pos = cs.position;
            if (pos !== 'fixed' && pos !== 'absolute') return;
            const rect = el.getBoundingClientRect();
            const covers = rect.width >= (vw * 0.9) && rect.height >= (vh * 0.9);
            if (!covers) return;
            if (isAllowedBlockingLayer(el)) return;
            foundBlocker = true;
            // Matikan layer fullscreen yang tidak dikenal agar halaman bisa diklik lagi.
            el.style.pointerEvents = 'none';
            el.style.display = 'none';
          });
          const btn = document.querySelector('[data-nb-unlock-ui]');
          if (btn) btn.style.display = foundBlocker ? 'inline-flex' : 'none';
        };
        guardOverlayInteraction();
        setTimeout(guardOverlayInteraction, 300);
        setTimeout(guardOverlayInteraction, 1000);
        setTimeout(neutralizeStuckBlockers, 250);
        setTimeout(neutralizeStuckBlockers, 800);
        setTimeout(neutralizeStuckBlockers, 1600);
        window.setInterval(() => {
          guardOverlayInteraction();
          neutralizeStuckBlockers();
        }, 4000);

        // Jika klik jatuh ke blocker fullscreen yang nyangkut, netralisasi lalu teruskan ke link di bawahnya.
        document.addEventListener('click', (e) => {
          const blocker = findFullscreenBlockerAt(e.target);
          if (!blocker) return;
          if (isAllowedBlockingLayer(blocker)) return;

          e.preventDefault();
          e.stopPropagation();
          blocker.style.pointerEvents = 'none';
          blocker.style.display = 'none';
          clearGlobalUiLocks();

          const below = document.elementFromPoint(e.clientX, e.clientY);
          const belowLink = below && below.closest ? below.closest('a[href]') : null;
          if (belowLink && belowLink.href && !belowLink.href.startsWith('javascript:')) {
            window.location.assign(belowLink.href);
          }
        }, true);

        document.addEventListener('click', (e) => {
          if (!e.target.closest('[data-nb-unlock-ui]')) return;
          e.preventDefault();
          clearGlobalUiLocks();
          neutralizeStuckBlockers();
        });
      }

      return { bind, closeSearch, openSidebar, closeSidebar, toggleCollapsed, clearGlobalUiLocks };
    })();

    document.addEventListener('DOMContentLoaded', () => window.nbApp?.bind?.());
    window.addEventListener('pageshow', () => {
      window.nbApp?.clearGlobalUiLocks?.();
    });
  </script>
  @stack('scripts')
</body>
</html>
