@extends('layouts.notobuku')

@section('title', 'Sirkulasi Terpadu - NOTOBUKU')

@section('content')
@php
  $title = $title ?? 'Sirkulasi Terpadu';
@endphp

<div class="nb-container" style="max-width:none; padding-left:0; padding-right:0;">
  <div class="nb-card uc-card" id="txu_app"
       data-api-member-search='@json($apiUrls["member_search"] ?? "")'
       data-api-member-info='@json($apiUrls["member_info"] ?? "")'
       data-api-cek-pinjam='@json($apiUrls["cek_pinjam"] ?? "")'
       data-api-cek-kembali='@json($apiUrls["cek_kembali"] ?? "")'
       data-api-cek-perpanjang='@json($apiUrls["cek_perpanjang"] ?? "")'
       data-api-commit='@json($apiUrls["commit"] ?? "")'
       data-api-sync='@json($apiUrls["sync"] ?? "")'
       data-flag-offline-queue-enabled="{{ !empty($featureFlags['offline_queue_enabled']) ? '1' : '0' }}"
       data-flag-shortcuts-enabled="{{ !empty($featureFlags['shortcuts_enabled']) ? '1' : '0' }}">

    <div class="uc-head">
      <div>
        <div class="uc-title">{{ $title }}</div>
        <div class="uc-sub">Pinjam, kembali, dan perpanjang dalam satu halaman. Optimasi scan cepat pustakawan.</div>
      </div>
      <div class="uc-statuses">
        <span class="uc-chip" id="txu_conn">Online</span>
        <span class="uc-chip" id="txu_queue">Queue: 0</span>
        <span class="uc-chip" id="txu_sync">Sinkron: idle</span>
      </div>
    </div>

    <div class="uc-toolbar" id="txu_mode">
      <button type="button" class="op-btn mode active" data-mode="checkout">Pinjam</button>
      <button type="button" class="op-btn mode" data-mode="return">Kembali</button>
      <button type="button" class="op-btn mode" data-mode="renew">Perpanjang</button>
      <button type="button" class="op-btn" id="txu_auto_commit">Auto-commit: ON</button>
      <a class="op-btn" href="{{ $legacyUrls['pinjam'] ?? '#' }}">Pinjam lama</a>
      <a class="op-btn" href="{{ $legacyUrls['kembali'] ?? '#' }}">Kembali lama</a>
      <a class="op-btn" href="{{ $legacyUrls['perpanjang'] ?? '#' }}">Perpanjang lama</a>
    </div>
    <div class="uc-quick">
      <div class="uc-steps" id="txu_mode_steps">Langkah cepat: Pilih member → Scan barcode item → Simpan pinjam.</div>
      <div class="uc-shortcuts" id="txu_shortcuts_hint">Shortcut: <kbd>F2</kbd> ganti mode, <kbd>F8</kbd> simpan, <kbd>Esc</kbd> bersihkan.</div>
    </div>

    <div class="uc-body">
      <div class="uc-main">
        <div class="uc-field" id="txu_member_block">
          <label id="txu_member_label">Cari Member</label>
          <input class="uc-input" type="text" id="txu_member_search" placeholder="Nama/kode member...">
          <div id="txu_member_results"></div>
          <input type="hidden" id="txu_member_id">
          <div class="uc-hint" id="txu_member_meta"></div>
        </div>

        <div class="uc-field uc-scan-sticky" id="txu_scan_field">
          <label id="txu_barcode_label">Scan Barcode</label>
          <input class="uc-input" type="text" id="txu_barcode" placeholder="Scan lalu Enter" autocomplete="off">
          <div class="uc-tip" id="txu_scan_tip_wrap">
            <button type="button" class="uc-tip-btn" id="txu_scan_tip_btn" aria-describedby="txu_scan_tip_text" title="Contoh scan valid">?</button>
            <span id="txu_scan_tip_text">Contoh: BK-REF-000123 (barcode item).</span>
          </div>
          <div class="uc-hint" id="txu_mode_help">Scan item yang akan dipinjam.</div>
        </div>

        <div class="uc-table-wrap">
          <table class="uc-table">
            <thead>
              <tr>
                <th style="width:30%;">Barcode</th>
                <th style="width:30%;">Referensi</th>
                <th style="width:25%;">Status</th>
                <th style="width:15%; text-align:right;">Aksi</th>
              </tr>
            </thead>
            <tbody id="txu_items"></tbody>
          </table>
          <div id="txu_empty" class="uc-empty">Belum ada item dipilih.</div>
        </div>
      </div>

      <div class="uc-side">
        <div class="uc-field">
          <label id="txu_notes_label">Catatan</label>
          <textarea class="uc-text" id="txu_notes" placeholder="Catatan opsional..."></textarea>
        </div>

        <div class="uc-actions">
          <button type="button" class="op-btn" id="txu_undo">Batalkan item terakhir</button>
          <button type="button" class="op-btn" id="txu_clear">Bersihkan</button>
          <button type="button" class="op-btn" id="txu_flush">Sync Queue</button>
          <button type="button" class="op-btn primary" id="txu_commit">Terapkan</button>
        </div>
        <div class="uc-error-panel" id="txu_error_panel" hidden>
          <div class="uc-error-title" id="txu_error_title">Error</div>
          <div class="uc-error-msg" id="txu_error_msg">-</div>
          <div class="uc-error-hint" id="txu_error_hint">-</div>
        </div>
        <div class="uc-batch-summary" id="txu_batch_summary">Ringkasan batch: berhasil 0 • gagal 0 • queued 0</div>

        <div class="uc-log" id="txu_log"></div>
      </div>
    </div>
  </div>
</div>

<style>
  .uc-card{padding:14px 0;}
  .uc-head{padding:0 14px 10px;display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;}
  .uc-title{font-size:16px;font-weight:800;color:rgba(11,37,69,.92);}
  .uc-sub{margin-top:3px;font-size:12px;color:rgba(15,23,42,.62);} 

  .uc-statuses{display:flex;gap:6px;flex-wrap:wrap;align-items:center;}
  .uc-chip{display:inline-flex;align-items:center;padding:7px 10px;border-radius:999px;border:1px solid rgba(15,23,42,.14);background:#fff;font-size:12px;font-weight:800;color:#111827;}
  .uc-chip.ok{background:#ecfdf5;border-color:#10b98155;color:#065f46;}
  .uc-chip.warn{background:#fff8e1;border-color:#f59e0b66;color:#7a4300;}

  .uc-toolbar{padding:10px 14px;border-top:1px solid rgba(15,23,42,.08);border-bottom:1px solid rgba(15,23,42,.08);display:flex;gap:8px;flex-wrap:wrap;}
  .uc-quick{padding:8px 14px;border-bottom:1px solid rgba(15,23,42,.08);display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;background:#fafcff;}
  .uc-steps{font-size:12px;font-weight:700;color:#334155;}
  .uc-shortcuts{font-size:11px;font-weight:700;color:#64748b;}
  .uc-shortcuts kbd{padding:2px 6px;border:1px solid rgba(15,23,42,.16);border-radius:6px;background:#fff;color:#0f172a;font-size:11px;font-weight:800;}
  .op-btn{display:inline-flex !important;align-items:center;justify-content:center;border:1px solid rgba(15,23,42,.14);border-radius:12px;padding:9px 12px;font-size:13px;font-weight:800;text-decoration:none;cursor:pointer;background:#fff;color:#111827;white-space:nowrap;line-height:1.1;min-height:38px;}
  .op-btn.primary{background:linear-gradient(180deg,#1e88e5,#1565c0);border-color:transparent;color:#fff;}
  .op-btn.mode.active{background:linear-gradient(180deg,#1e88e5,#1565c0);border-color:transparent;color:#fff;}

  .uc-body{padding:12px 14px;display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:12px;}
  .uc-main,.uc-side{display:grid;gap:10px;align-content:start;min-width:0;}
  .uc-field label{display:block;font-size:12px;color:#6b7280;font-weight:700;margin-bottom:6px;}
  .uc-input,.uc-text{width:100%;border:1px solid rgba(15,23,42,.12);border-radius:12px;padding:10px;font-size:13px;background:#fff;}
  .uc-text{min-height:92px;resize:vertical;}
  .uc-hint{margin-top:5px;font-size:12px;color:#64748b;}
  .uc-tip{margin-top:6px;display:inline-flex;align-items:center;gap:7px;padding:5px 8px;border:1px dashed rgba(15,23,42,.22);border-radius:10px;background:#f8fafc;}
  .uc-tip-btn{width:20px;height:20px;min-height:20px;border-radius:999px;border:1px solid rgba(15,23,42,.2);background:#fff;color:#334155;font-size:11px;font-weight:900;line-height:1;display:inline-flex;align-items:center;justify-content:center;cursor:help;padding:0;}
  #txu_scan_tip_text{font-size:11px;font-weight:700;color:#475569;}

  .uc-table-wrap{border:1px solid rgba(15,23,42,.08);border-radius:12px;overflow:hidden;}
  .uc-table{width:100%;border-collapse:collapse;table-layout:fixed;}
  .uc-table th,.uc-table td{padding:9px 8px;border-bottom:1px solid rgba(15,23,42,.08);text-align:left;vertical-align:top;font-size:12px;word-break:break-word;}
  .uc-table th{background:#f8fafc;color:#6b7280;font-weight:800;text-transform:uppercase;}
  .uc-table td:last-child,.uc-table th:last-child{text-align:right;}
  .uc-table tr:last-child td{border-bottom:0;}
  .uc-empty{padding:14px 10px;text-align:center;font-size:12px;color:#64748b;font-weight:700;}
  .uc-badge{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;border:1px solid;font-size:11px;font-weight:800;line-height:1.15;}
  .uc-badge.ok{color:#065f46;background:#ecfdf5;border-color:#10b98155;}
  .uc-badge.warn{color:#92400e;background:#fffbeb;border-color:#f59e0b66;}
  .uc-badge.bad{color:#991b1b;background:#fef2f2;border-color:#fca5a5;}
  .uc-badge.info{color:#1e3a8a;background:#eff6ff;border-color:#93c5fd;}

  .uc-actions{display:flex;gap:8px;flex-wrap:wrap;}
  .uc-error-panel{border:1px solid #fca5a5;background:#fef2f2;border-radius:10px;padding:8px 10px;}
  .uc-error-title{font-size:12px;font-weight:800;color:#991b1b;}
  .uc-error-msg{margin-top:2px;font-size:12px;font-weight:700;color:#7f1d1d;}
  .uc-error-hint{margin-top:2px;font-size:11px;font-weight:700;color:#b45309;}
  .uc-batch-summary{border:1px solid rgba(15,23,42,.12);border-radius:10px;padding:8px 10px;background:#fff;font-size:12px;font-weight:700;color:#334155;}
  .uc-log{border:1px dashed rgba(15,23,42,.20);border-radius:12px;padding:10px;font-size:12px;color:#475569;background:#f8fafc;max-height:180px;overflow:auto;white-space:pre-wrap;}

  .uc-dd{border:1px solid rgba(15,23,42,.12);border-radius:12px;background:#fff;overflow:hidden;}
  .uc-dd button{display:block;width:100%;text-align:left;border:0;background:#fff;padding:9px 10px;font-size:12px;font-weight:700;color:#0f172a;}
  .uc-dd button:hover{background:#f1f5f9;}

  @media (max-width:980px){
    .uc-body{grid-template-columns:1fr;}
    .uc-actions{
      justify-content:flex-start;
      position:sticky;
      bottom:0;
      z-index:5;
      background:#fff;
      border-top:1px solid rgba(15,23,42,.08);
      padding-top:8px;
      padding-bottom:6px;
    }
    .uc-quick{align-items:flex-start;}
    .uc-scan-sticky{
      position:sticky;
      top:0;
      z-index:6;
      background:#fff;
      border-bottom:1px solid rgba(15,23,42,.08);
      padding-bottom:8px;
      margin-bottom:2px;
    }
  }
</style>

<script>
(() => {
  const app = document.getElementById('txu_app');
  if (!app) return;

  const API = {
    memberSearch: JSON.parse(app.dataset.apiMemberSearch || '""'),
    memberInfo: JSON.parse(app.dataset.apiMemberInfo || '""'),
    cekPinjam: JSON.parse(app.dataset.apiCekPinjam || '""'),
    cekKembali: JSON.parse(app.dataset.apiCekKembali || '""'),
    cekPerpanjang: JSON.parse(app.dataset.apiCekPerpanjang || '""'),
    commit: JSON.parse(app.dataset.apiCommit || '""'),
    sync: JSON.parse(app.dataset.apiSync || '""'),
  };
  const FLAGS = {
    offlineQueueEnabled: (app.dataset.flagOfflineQueueEnabled || '1') === '1',
    shortcutsEnabled: (app.dataset.flagShortcutsEnabled || '1') === '1',
  };

  const el = {
    modeWrap: document.getElementById('txu_mode'),
    memberBlock: document.getElementById('txu_member_block'),
    memberLabel: document.getElementById('txu_member_label'),
    memberSearch: document.getElementById('txu_member_search'),
    memberResults: document.getElementById('txu_member_results'),
    memberId: document.getElementById('txu_member_id'),
    memberMeta: document.getElementById('txu_member_meta'),
    barcodeLabel: document.getElementById('txu_barcode_label'),
    barcode: document.getElementById('txu_barcode'),
    scanTip: document.getElementById('txu_scan_tip_text'),
    shortcutsHint: document.getElementById('txu_shortcuts_hint'),
    modeSteps: document.getElementById('txu_mode_steps'),
    modeHelp: document.getElementById('txu_mode_help'),
    items: document.getElementById('txu_items'),
    empty: document.getElementById('txu_empty'),
    notesLabel: document.getElementById('txu_notes_label'),
    notes: document.getElementById('txu_notes'),
    autoCommit: document.getElementById('txu_auto_commit'),
    commit: document.getElementById('txu_commit'),
    undo: document.getElementById('txu_undo'),
    clear: document.getElementById('txu_clear'),
    flush: document.getElementById('txu_flush'),
    errorPanel: document.getElementById('txu_error_panel'),
    errorTitle: document.getElementById('txu_error_title'),
    errorMsg: document.getElementById('txu_error_msg'),
    errorHint: document.getElementById('txu_error_hint'),
    batchSummary: document.getElementById('txu_batch_summary'),
    log: document.getElementById('txu_log'),
    conn: document.getElementById('txu_conn'),
    queue: document.getElementById('txu_queue'),
    sync: document.getElementById('txu_sync'),
  };

  const STATE = {
    mode: 'checkout',
    autoCommit: true,
    rows: [],
    scanLock: false,
    syncLock: false,
    lastScanValue: '',
    lastScanAt: 0,
    counters: {
      success: 0,
      failed: 0,
      queued: 0,
      dead: 0,
    },
  };

  const QUEUE_KEY = 'nb_circulation_offline_queue_v1';
  const DEAD_QUEUE_KEY = 'nb_circulation_offline_dead_queue_v1';
  const PREFS_KEY = 'nb_circulation_unified_prefs_v1';
  const QUEUE_MAX_SIZE = 500;
  const DEAD_QUEUE_MAX_SIZE = 200;
  const SYNC_CHUNK_SIZE = 20;
  const SYNC_MAX_RETRY = 5;
  const SYNC_BASE_DELAY_MS = 5000;
  const SYNC_MAX_DELAY_MS = 300000;
  const MODE_COPY = {
    checkout: {
      memberLabel: 'Cari Member',
      memberPlaceholder: 'Nama/kode member yang meminjam...',
      barcodeLabel: 'Scan Barcode Pinjam',
      barcodePlaceholder: 'Scan barcode item untuk dipinjam',
      modeHelp: 'Scan satu per satu item yang akan dipinjam oleh member terpilih.',
      scanTip: 'Contoh scan valid: BK-REF-000123 (barcode item tersedia).',
      steps: 'Langkah cepat: Pilih member → Scan barcode item → Simpan pinjam.',
      empty: 'Belum ada item pinjam.',
      notesLabel: 'Catatan Pinjam',
      notesPlaceholder: 'Contoh: diproses oleh meja layanan 1',
      commit: 'Simpan Pinjam',
      logMode: 'Pinjam',
    },
    return: {
      memberLabel: 'Member (otomatis dari pinjaman)',
      memberPlaceholder: 'Tidak perlu isi member pada mode kembali',
      barcodeLabel: 'Scan Barcode Kembali',
      barcodePlaceholder: 'Scan barcode item yang dikembalikan',
      modeHelp: 'Sistem akan mencari pinjaman aktif dari barcode lalu menyiapkan pengembalian.',
      scanTip: 'Contoh scan valid: BK-REF-000123 (barcode item yang sedang dipinjam).',
      steps: 'Langkah cepat: Scan barcode item → Cek status pinjaman aktif → Simpan pengembalian.',
      empty: 'Belum ada item kembali.',
      notesLabel: 'Catatan Pengembalian',
      notesPlaceholder: 'Contoh: ada kerusakan sampul ringan',
      commit: 'Simpan Kembali',
      logMode: 'Kembali',
    },
    renew: {
      memberLabel: 'Member (otomatis dari pinjaman)',
      memberPlaceholder: 'Tidak perlu isi member pada mode perpanjang',
      barcodeLabel: 'Scan Barcode Perpanjang',
      barcodePlaceholder: 'Scan barcode item yang akan diperpanjang',
      modeHelp: 'Sistem akan cek hak perpanjangan, batas renew, dan jatuh tempo baru.',
      scanTip: 'Contoh scan valid: BK-REF-000123 (barcode item dengan pinjaman aktif).',
      steps: 'Langkah cepat: Scan barcode item → Review batas perpanjang → Simpan perpanjangan.',
      empty: 'Belum ada item perpanjang.',
      notesLabel: 'Catatan Perpanjang',
      notesPlaceholder: 'Contoh: disetujui karena antrian kosong',
      commit: 'Simpan Perpanjang',
      logMode: 'Perpanjang',
    },
  };

  function tone(ok){
    try {
      const AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return;
      const c = new AC();
      const o = c.createOscillator();
      const g = c.createGain();
      o.frequency.value = ok ? 780 : 240;
      o.connect(g); g.connect(c.destination); g.gain.value = 0.03; o.start();
      setTimeout(() => { o.stop(); c.close(); }, ok ? 75 : 130);
    } catch (_) {}
  }

  function csrf(){
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function log(msg){
    const line = `[${new Date().toLocaleTimeString('id-ID')}] ${msg}`;
    el.log.textContent = `${line}\n${el.log.textContent}`.slice(0, 5000);
  }

  function setErrorPanel(title, message, hint) {
    if (!el.errorPanel) return;
    if (!title && !message) {
      el.errorPanel.hidden = true;
      return;
    }
    el.errorPanel.hidden = false;
    if (el.errorTitle) el.errorTitle.textContent = title || 'Error';
    if (el.errorMsg) el.errorMsg.textContent = message || '-';
    if (el.errorHint) el.errorHint.textContent = hint || '';
  }

  function classifyError(message) {
    const msg = String(message || '').toLowerCase();
    if (msg.includes('failed to fetch') || msg.includes('network') || msg.includes('offline')) {
      return { title: 'Offline/Jaringan', hint: 'Periksa koneksi. Data akan masuk queue dan disinkronkan otomatis.' };
    }
    if (msg.includes('duplikat')) {
      return { title: 'Duplikat Request', hint: 'Tunggu sebentar lalu scan ulang sekali saja.' };
    }
    if (msg.includes('tidak valid') || msg.includes('wajib') || msg.includes('tidak ditemukan')) {
      return { title: 'Validasi Data', hint: 'Cek barcode/member, lalu ulangi scan sesuai mode aktif.' };
    }
    if (msg.includes('akses') || msg.includes('ditolak') || msg.includes('forbidden')) {
      return { title: 'Hak Akses', hint: 'Gunakan akun staff/admin pada cabang yang sesuai.' };
    }
    return { title: 'Operasional', hint: 'Coba lagi. Jika berulang, laporkan ke admin sistem.' };
  }

  function prefsLoad() {
    try { return JSON.parse(localStorage.getItem(PREFS_KEY) || '{}') || {}; }
    catch (_) { return {}; }
  }

  function prefsSave() {
    const payload = { mode: STATE.mode, autoCommit: STATE.autoCommit === true };
    localStorage.setItem(PREFS_KEY, JSON.stringify(payload));
  }

  function queueLoad(){
    if (!FLAGS.offlineQueueEnabled) return [];
    try {
      const rows = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]') || [];
      return Array.isArray(rows) ? rows.map((r) => ({
        ...r,
        queued_at: Number(r.queued_at || Date.now()),
        retry_count: Number(r.retry_count || 0),
        next_retry_at: Number(r.next_retry_at || 0),
        last_error: String(r.last_error || ''),
      })) : [];
    }
    catch(_) { return []; }
  }

  function deadQueueLoad(){
    if (!FLAGS.offlineQueueEnabled) return [];
    try {
      const rows = JSON.parse(localStorage.getItem(DEAD_QUEUE_KEY) || '[]') || [];
      return Array.isArray(rows) ? rows : [];
    } catch (_) { return []; }
  }

  function deadQueueSave(rows){
    if (!FLAGS.offlineQueueEnabled) return;
    const clipped = rows.slice(-DEAD_QUEUE_MAX_SIZE);
    localStorage.setItem(DEAD_QUEUE_KEY, JSON.stringify(clipped));
    STATE.counters.dead = clipped.length;
    renderBatchSummary();
  }

  function deadQueuePush(event, reason){
    if (!FLAGS.offlineQueueEnabled) return;
    const rows = deadQueueLoad();
    rows.push({
      ...event,
      dead_at: Date.now(),
      dead_reason: String(reason || 'Maks retry tercapai'),
    });
    deadQueueSave(rows);
  }

  function queueSave(rows){
    if (!FLAGS.offlineQueueEnabled) {
      el.queue.textContent = 'Queue: OFF';
      return;
    }
    const clipped = rows.slice(-QUEUE_MAX_SIZE);
    localStorage.setItem(QUEUE_KEY, JSON.stringify(clipped));
    const oldest = clipped.length ? Math.min(...clipped.map((r) => Number(r.queued_at || Date.now()))) : 0;
    if (clipped.length && oldest > 0) {
      const ageSec = Math.max(0, Math.floor((Date.now() - oldest) / 1000));
      const ageText = ageSec >= 60 ? `${Math.floor(ageSec / 60)}m` : `${ageSec}s`;
      el.queue.textContent = `Queue: ${clipped.length} • oldest ${ageText}`;
    } else {
      el.queue.textContent = `Queue: ${clipped.length}`;
    }
    STATE.counters.queued = clipped.length;
    renderBatchSummary();
  }

  function queuePush(ev){
    if (!FLAGS.offlineQueueEnabled) return;
    const rows = queueLoad();
    rows.push({
      ...ev,
      queued_at: Date.now(),
      retry_count: 0,
      next_retry_at: 0,
      last_error: '',
    });
    if (rows.length > QUEUE_MAX_SIZE) {
      const dropped = rows.splice(0, rows.length - QUEUE_MAX_SIZE);
      dropped.forEach((d) => deadQueuePush(d, 'Queue penuh, event lama dipindah ke dead-letter lokal.'));
      log(`Queue mencapai batas ${QUEUE_MAX_SIZE}; event lama dipindah ke dead-letter.`);
    }
    queueSave(rows);
  }

  function setOnlineState(){
    const online = navigator.onLine;
    el.conn.textContent = online ? 'Online' : 'Offline';
    el.conn.className = `uc-chip ${online ? 'ok' : 'warn'}`;
  }

  function renderBatchSummary() {
    if (!el.batchSummary) return;
    el.batchSummary.textContent = `Ringkasan batch: berhasil ${STATE.counters.success} • gagal ${STATE.counters.failed} • queued ${STATE.counters.queued} • dead ${STATE.counters.dead || 0}`;
  }

  async function postJson(url, payload){
    const res = await fetch(url, {
      method:'POST',
      headers:{
        'Accept':'application/json',
        'Content-Type':'application/json',
        'X-CSRF-TOKEN': csrf(),
      },
      body: JSON.stringify(payload),
      credentials:'same-origin',
    });
    const json = await res.json().catch(() => ({ ok:false, message:'Respons tidak valid.' }));
    if (!res.ok) throw new Error(json.message || 'Request gagal.');
    return json;
  }

  function renderRows(){
    el.items.innerHTML = '';
    if (!STATE.rows.length) {
      el.empty.style.display = '';
      return;
    }
    el.empty.style.display = 'none';
    STATE.rows.forEach((r, idx) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${r.barcode || '-'}</td>
        <td>${r.ref || '-'}</td>
        <td><span class="uc-badge ${r.statusClass || 'info'}">${r.statusLabel || r.status || '-'}</span></td>
        <td><button type="button" class="op-btn" data-del="${idx}" style="padding:6px 8px;font-size:11px;min-height:30px;">Hapus</button></td>
      `;
      el.items.appendChild(tr);
    });

    el.items.querySelectorAll('button[data-del]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const i = Number(btn.getAttribute('data-del'));
        if (!Number.isNaN(i)) {
          STATE.rows.splice(i, 1);
          renderRows();
        }
      });
    });
  }

  function resetSelection(){
    STATE.rows = [];
    renderRows();
    el.barcode.value = '';
    el.barcode.focus();
  }

  function setMode(mode){
    STATE.mode = mode;
    const copy = MODE_COPY[mode] || MODE_COPY.checkout;
    el.modeWrap.querySelectorAll('button[data-mode]').forEach((b) => {
      b.classList.toggle('active', b.getAttribute('data-mode') === mode);
    });
    el.memberBlock.style.display = mode === 'checkout' ? '' : 'none';
    if (mode !== 'checkout') {
      if (el.memberId) el.memberId.value = '';
      if (el.memberSearch) el.memberSearch.value = '';
      if (el.memberMeta) el.memberMeta.textContent = '';
      if (el.memberResults) el.memberResults.innerHTML = '';
    }
    if (el.memberLabel) el.memberLabel.textContent = copy.memberLabel;
    if (el.memberSearch) el.memberSearch.placeholder = copy.memberPlaceholder;
    if (el.barcodeLabel) el.barcodeLabel.textContent = copy.barcodeLabel;
    if (el.barcode) el.barcode.placeholder = copy.barcodePlaceholder;
    if (el.modeHelp) el.modeHelp.textContent = copy.modeHelp;
    if (el.scanTip) el.scanTip.textContent = copy.scanTip;
    if (el.modeSteps) el.modeSteps.textContent = copy.steps;
    if (el.empty) el.empty.textContent = copy.empty;
    if (el.notesLabel) el.notesLabel.textContent = copy.notesLabel;
    if (el.notes) el.notes.placeholder = copy.notesPlaceholder;
    if (el.commit) el.commit.textContent = copy.commit;
    resetSelection();
    setErrorPanel('', '', '');
    log(`Mode ${copy.logMode} aktif`);
    prefsSave();
  }

  function classifyScanStatus(mode, data){
    if (mode === 'checkout') {
      const raw = String(data.status || '').toLowerCase();
      if (raw === 'available') return { statusClass: 'ok', statusLabel: 'Siap dipinjam' };
      if (raw === 'reserved') return { statusClass: 'warn', statusLabel: 'Reservasi aktif' };
      return { statusClass: 'bad', statusLabel: `Tidak siap (${raw || 'unknown'})` };
    }
    if (mode === 'return') {
      const raw = String(data.item_status || data.status || '').toLowerCase();
      if (raw === 'borrowed') return { statusClass: 'ok', statusLabel: 'Siap dikembalikan' };
      return { statusClass: 'warn', statusLabel: `Perlu cek (${raw || 'unknown'})` };
    }
    const renew = Number(data.renew_count || 0);
    if (renew >= 2) return { statusClass: 'warn', statusLabel: `Perlu review (${renew}x)` };
    return { statusClass: 'ok', statusLabel: `Bisa diperpanjang (${renew}x)` };
  }

  function buildCommitPayload(singleIndex = null){
    const rows = singleIndex === null ? STATE.rows : [STATE.rows[singleIndex]].filter(Boolean);
    if (STATE.mode === 'checkout') {
      return {
        action:'checkout',
        payload:{
          member_id: Number(el.memberId.value || 0),
          barcodes: rows.map(r => r.barcode).filter(Boolean),
          notes: el.notes.value || null,
        },
      };
    }
    if (STATE.mode === 'return') {
      return {
        action:'return',
        payload:{
          loan_item_ids: rows.map(r => Number(r.loan_item_id || 0)).filter(v => v > 0),
        },
      };
    }
    return {
      action:'renew',
      payload:{
        loan_item_ids: rows.map(r => Number(r.loan_item_id || 0)).filter(v => v > 0),
        notes: el.notes.value || null,
      },
    };
  }

  function randomEventId(){
    return `${Date.now()}-${Math.random().toString(36).slice(2,9)}`;
  }

  async function commit(singleIndex = null){
    const event = buildCommitPayload(singleIndex);
    const isBatch = singleIndex === null;

    if (event.action === 'checkout' && Number(event.payload.member_id || 0) <= 0) {
      log('Pilih member dulu untuk transaksi pinjam.');
      const cls = classifyError('validasi member');
      setErrorPanel(cls.title, 'Member belum dipilih untuk mode pinjam.', cls.hint);
      tone(false);
      return;
    }
    if ((event.payload.barcodes && !event.payload.barcodes.length) || (event.payload.loan_item_ids && !event.payload.loan_item_ids.length)) {
      log('Belum ada item valid.');
      const cls = classifyError('validasi item');
      setErrorPanel(cls.title, 'Belum ada item valid untuk diproses.', cls.hint);
      tone(false);
      return;
    }

    const candidateCount = event.payload.barcodes ? event.payload.barcodes.length : event.payload.loan_item_ids.length;
    if (isBatch && candidateCount > 1) {
      const modeLabel = event.action === 'checkout' ? 'pinjam' : (event.action === 'return' ? 'kembali' : 'perpanjang');
      const ok = window.confirm(`Terapkan ${candidateCount} item untuk mode ${modeLabel}?`);
      if (!ok) {
        log('Aksi dibatalkan operator.');
        return;
      }
    }

    const wrapped = { ...event, client_event_id: randomEventId() };
    try {
      const res = await postJson(API.commit, wrapped);
      log(res.message || 'Berhasil diproses.');
      tone(true);
      setErrorPanel('', '', '');
      const successDelta = Math.max(1, candidateCount);
      STATE.counters.success += successDelta;
      if (singleIndex === null) STATE.rows = [];
      else STATE.rows.splice(singleIndex, 1);
      renderRows();
      renderBatchSummary();
    } catch (e) {
      const offline = !navigator.onLine || String(e.message || '').toLowerCase().includes('failed to fetch');
      if (offline) {
        if (FLAGS.offlineQueueEnabled) {
          queuePush(wrapped);
          log('Offline: masuk queue untuk auto-sync.');
          const cls = classifyError('offline');
          setErrorPanel(cls.title, 'Koneksi terputus. Transaksi dimasukkan ke queue lokal.', cls.hint);
        } else {
          log('Offline: queue dimatikan, transaksi tidak disimpan lokal.');
          const cls = classifyError('offline');
          setErrorPanel(cls.title, 'Koneksi terputus. Queue offline dinonaktifkan oleh admin.', cls.hint);
          STATE.counters.failed += Math.max(1, candidateCount);
          renderBatchSummary();
        }
      } else {
        log(`Gagal: ${e.message || 'unknown error'}`);
        const cls = classifyError(e.message || '');
        setErrorPanel(cls.title, String(e.message || 'Gagal memproses transaksi.'), cls.hint);
        STATE.counters.failed += Math.max(1, candidateCount);
        renderBatchSummary();
      }
      tone(false);
    }
  }

  async function flushQueue(){
    if (!FLAGS.offlineQueueEnabled) return;
    const rows = queueLoad();
    if (!rows.length || !navigator.onLine) return;
    if (STATE.syncLock) return;
    STATE.syncLock = true;

    el.sync.textContent = 'Sinkron: berjalan';
    el.sync.className = 'uc-chip warn';

    const now = Date.now();
    const dueRows = rows.filter((r) => Number(r.next_retry_at || 0) <= now);
    if (!dueRows.length) {
      el.sync.textContent = 'Sinkron: menunggu retry';
      el.sync.className = 'uc-chip warn';
      STATE.syncLock = false;
      return;
    }

    const chunk = dueRows.slice(0, SYNC_CHUNK_SIZE);
    try {
      const res = await postJson(API.sync, { events: chunk });
      const failedIds = new Set((res.results || []).filter(r => !r.ok).map(r => (chunk[r.index] || {}).client_event_id));
      const syncSuccess = Math.max(0, Number(res.summary?.success || 0));
      const syncFailed = Math.max(0, Number(res.summary?.failed || 0));
      STATE.counters.success += syncSuccess;
      STATE.counters.failed += syncFailed;
      const remaining = [];
      rows.forEach((r) => {
        const processed = chunk.find((c) => c.client_event_id === r.client_event_id);
        if (!processed) {
          remaining.push(r);
          return;
        }

        if (!failedIds.has(r.client_event_id)) {
          return;
        }

        const retryCount = Number(r.retry_count || 0) + 1;
        if (retryCount > SYNC_MAX_RETRY) {
          deadQueuePush(r, 'Maks retry tercapai saat auto-sync.');
          return;
        }

        const backoff = Math.min(SYNC_MAX_DELAY_MS, SYNC_BASE_DELAY_MS * Math.pow(2, retryCount - 1));
        remaining.push({
          ...r,
          retry_count: retryCount,
          next_retry_at: Date.now() + backoff,
          last_error: 'Sync partial failed',
        });
      });
      queueSave(remaining);
      log(res.message || 'Sync selesai.');
      setErrorPanel('', '', '');
      renderBatchSummary();
    } catch (e) {
      log(`Sync gagal: ${e.message || 'network error'}`);
      const cls = classifyError(e.message || '');
      setErrorPanel(cls.title, String(e.message || 'Sync queue gagal.'), cls.hint);
      const retryRows = rows.map((r) => {
        const processed = chunk.find((c) => c.client_event_id === r.client_event_id);
        if (!processed) return r;
        const retryCount = Number(r.retry_count || 0) + 1;
        if (retryCount > SYNC_MAX_RETRY) {
          deadQueuePush(r, e.message || 'Sync queue gagal');
          return null;
        }
        const backoff = Math.min(SYNC_MAX_DELAY_MS, SYNC_BASE_DELAY_MS * Math.pow(2, retryCount - 1));
        return {
          ...r,
          retry_count: retryCount,
          next_retry_at: Date.now() + backoff,
          last_error: String(e.message || 'Sync queue gagal'),
        };
      }).filter(Boolean);
      queueSave(retryRows);
    }

    el.sync.textContent = 'Sinkron: idle';
    el.sync.className = 'uc-chip';
    STATE.syncLock = false;
  }

  async function scanLookup(barcode){
    const endpoint = STATE.mode === 'checkout' ? API.cekPinjam : (STATE.mode === 'return' ? API.cekKembali : API.cekPerpanjang);
    const res = await fetch(`${endpoint}?barcode=${encodeURIComponent(barcode)}`, {
      headers:{ 'Accept':'application/json' }, credentials:'same-origin'
    });
    const json = await res.json();
    if (!res.ok || !json.ok) throw new Error(json.message || 'Gagal cek barcode.');
    return json.data || {};
  }

  async function onScan(barcode){
    const now = Date.now();
    if (STATE.lastScanValue === barcode && (now - STATE.lastScanAt) < 1200) {
      log(`Duplikat scan diabaikan: ${barcode}`);
      return;
    }
    STATE.lastScanValue = barcode;
    STATE.lastScanAt = now;

    if (STATE.scanLock) return;
    STATE.scanLock = true;
    try {
      const data = await scanLookup(barcode);
      const row = {
        barcode: data.barcode || barcode,
        loan_item_id: data.loan_item_id || null,
        ref: data.loan_code || data.title || '-',
        status: data.status || data.item_status || data.loan_status || 'ok',
      };
      const classified = classifyScanStatus(STATE.mode, data);
      row.statusClass = classified.statusClass;
      row.statusLabel = classified.statusLabel;

      const duplicate = STATE.rows.find((r) => r.barcode === row.barcode && String(r.loan_item_id || '') === String(row.loan_item_id || ''));
      if (!duplicate) {
        STATE.rows.push(row);
        renderRows();
      }
      tone(true);
      setErrorPanel('', '', '');

      if (STATE.autoCommit) {
        await commit(STATE.rows.length - 1);
      }
    } catch (e) {
      log(`Scan gagal: ${e.message || 'unknown error'}`);
      const cls = classifyError(e.message || '');
      setErrorPanel(cls.title, String(e.message || 'Scan gagal.'), cls.hint);
      tone(false);
    } finally {
      STATE.scanLock = false;
      el.barcode.value = '';
      el.barcode.focus();
    }
  }

  async function searchMember(q){
    const res = await fetch(`${API.memberSearch}?q=${encodeURIComponent(q)}`, {
      headers:{ 'Accept':'application/json' }, credentials:'same-origin'
    });
    const json = await res.json();
    if (!json.ok) return [];
    return json.data || [];
  }

  async function pickMember(row){
    el.memberId.value = row.id;
    el.memberSearch.value = row.label || row.name || '';
    el.memberResults.innerHTML = '';

    const infoUrl = API.memberInfo.replace('__ID__', String(row.id));
    try {
      const res = await fetch(infoUrl, { headers:{ 'Accept':'application/json' }, credentials:'same-origin' });
      const json = await res.json();
      if (json.ok) {
        const p = json.data.policy || {};
        el.memberMeta.textContent = `${json.data.member_name || ''} • Aktif ${json.data.active_items || 0} • Limit ${p.max_items || '-'} • Extend ${p.extend_days || '-'} hari`;
      }
    } catch (_) {}
  }

  let memberTimer = null;
  el.memberSearch.addEventListener('input', () => {
    const q = el.memberSearch.value.trim();
    clearTimeout(memberTimer);
    if (q.length < 2) {
      el.memberResults.innerHTML = '';
      return;
    }
    memberTimer = setTimeout(async () => {
      const rows = await searchMember(q);
      if (!rows.length) {
        el.memberResults.innerHTML = '<div class="uc-dd"><button type="button" disabled>Tidak ditemukan</button></div>';
        return;
      }
      const div = document.createElement('div');
      div.className = 'uc-dd';
      rows.slice(0,6).forEach((r) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = r.label || `${r.name || ''}`;
        b.addEventListener('click', () => pickMember(r));
        div.appendChild(b);
      });
      el.memberResults.innerHTML = '';
      el.memberResults.appendChild(div);
    }, 220);
  });

  el.modeWrap.querySelectorAll('button[data-mode]').forEach((btn) => {
    btn.addEventListener('click', () => setMode(btn.getAttribute('data-mode')));
  });

  el.autoCommit.addEventListener('click', () => {
    STATE.autoCommit = !STATE.autoCommit;
    el.autoCommit.textContent = `Auto-commit: ${STATE.autoCommit ? 'ON' : 'OFF'}`;
    el.autoCommit.classList.toggle('active', STATE.autoCommit);
    prefsSave();
  });

  el.barcode.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const code = el.barcode.value.trim();
    if (!code) return;
    onScan(code);
  });

  el.commit.addEventListener('click', () => commit(null));
  el.undo.addEventListener('click', () => {
    if (!STATE.rows.length) {
      log('Tidak ada item untuk dibatalkan.');
      return;
    }
    const removed = STATE.rows.pop();
    renderRows();
    setErrorPanel('', '', '');
    log(`Item dibatalkan: ${removed?.barcode || '-'}`);
    el.barcode.focus();
  });
  el.clear.addEventListener('click', () => {
    resetSelection();
    setErrorPanel('', '', '');
  });
  el.flush.addEventListener('click', flushQueue);

  if (FLAGS.shortcutsEnabled) {
    document.addEventListener('keydown', (e) => {
      const key = String(e.key || '');
      if (key === 'F2') {
        e.preventDefault();
        const order = ['checkout', 'return', 'renew'];
        const idx = order.indexOf(STATE.mode);
        const next = order[(idx + 1) % order.length];
        setMode(next);
        return;
      }
      if (key === 'F8') {
        e.preventDefault();
        commit(null);
        return;
      }
      if (key === 'Escape') {
        e.preventDefault();
        resetSelection();
        setErrorPanel('', '', '');
        log('Form dibersihkan (Esc).');
      }
    });
  } else if (el.shortcutsHint) {
    el.shortcutsHint.textContent = 'Shortcut keyboard dinonaktifkan oleh admin.';
  }

  window.addEventListener('online', () => {
    setOnlineState();
    if (FLAGS.offlineQueueEnabled) flushQueue();
  });
  window.addEventListener('offline', setOnlineState);

  if (!FLAGS.offlineQueueEnabled) {
    el.queue.textContent = 'Queue: OFF';
    el.sync.textContent = 'Sinkron: OFF';
    el.sync.className = 'uc-chip warn';
    el.flush.textContent = 'Sync Queue OFF';
    el.flush.disabled = true;
  }

  setOnlineState();
  if (FLAGS.offlineQueueEnabled) {
    queueSave(queueLoad());
    deadQueueSave(deadQueueLoad());
  }
  renderBatchSummary();
  const prefs = prefsLoad();
  STATE.autoCommit = prefs.autoCommit !== false;
  el.autoCommit.textContent = `Auto-commit: ${STATE.autoCommit ? 'ON' : 'OFF'}`;
  el.autoCommit.classList.toggle('active', STATE.autoCommit);
  setMode(['checkout','return','renew'].includes(prefs.mode) ? prefs.mode : 'checkout');
  if (FLAGS.offlineQueueEnabled) {
    setInterval(flushQueue, 30000);
  }
  log('Sirkulasi terpadu siap.');
})();
</script>
@endsection

