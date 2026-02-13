@extends('layouts.notobuku')

@section('title', 'Transaksi Pinjam • NOTOBUKU')

@section('content')
@php
  $title = $title ?? 'Transaksi Pinjam';
@endphp

<style>
  .nb-k-wrap{ max-width:1180px; margin:0 auto; }
  .nb-k-head{ padding:14px; }
  .nb-k-headTop{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .nb-k-title{ font-size:16px; font-weight:800; letter-spacing:.1px; color: rgba(11,37,69,.94); line-height:1.2; }
  html.dark .nb-k-title{ color: rgba(226,232,240,.92); }
  .nb-k-sub{ margin-top:6px; font-size:13px; font-weight:500; color: rgba(11,37,69,.70); line-height:1.35; }
  html.dark .nb-k-sub{ color: rgba(226,232,240,.70); }

  .nb-k-ibtn{ width:44px; height:44px; border-radius:16px; border:1px solid rgba(15,23,42,.12); background: rgba(255,255,255,.78); display:inline-flex; align-items:center; justify-content:center; cursor:pointer; user-select:none; text-decoration:none; transition: background .12s ease, border-color .12s ease, transform .06s ease, box-shadow .12s ease, color .12s ease; }
  .nb-k-ibtn:active{ transform: translateY(1px); }
  .nb-k-ibtn svg{ width:18px; height:18px; }
  .nb-k-ibtn.apply{ border-color: rgba(30,136,229,.22); background: linear-gradient(180deg, rgba(30,136,229,1), rgba(21,101,192,1)); color:#fff; box-shadow: 0 14px 26px rgba(30,136,229,.22); }
  .nb-k-ibtn.apply svg{ color:#fff; }
  .nb-k-ibtn.reset{ color: rgba(11,37,69,.92); }
  html.dark .nb-k-ibtn.reset{ color: rgba(226,232,240,.92); border-color: rgba(148,163,184,.16); background: rgba(15,23,42,.40); }

  .nb-k-filter{ display:grid; gap:12px; grid-template-columns: 1fr 260px; }
  @media (max-width: 900px){ .nb-k-filter{ grid-template-columns: 1fr; } }

  .nb-k-fieldBlock{ position:relative; }
  .nb-k-label{ display:block; font-size:12px; font-weight:700; color: rgba(11,37,69,.70); margin-bottom:6px; }
  .nb-field{ width:100%; border:1px solid rgba(15,23,42,.10); border-radius:14px; padding:12px 14px; font-size:14px; background: rgba(255,255,255,.85); }
  .nb-field:focus{ outline:none; border-color: rgba(30,136,229,.35); box-shadow: 0 0 0 3px rgba(30,136,229,.12); }

  .nb-k-inputWrap{ position:relative; border:1px solid rgba(15,23,42,.10); border-radius:14px; background: rgba(255,255,255,.85); }
  .nb-k-inputWrap:focus-within{ border-color: rgba(30,136,229,.35); box-shadow: 0 0 0 3px rgba(30,136,229,.12); }
  .nb-k-inputWrap .nb-field{ position:relative; z-index:2; border:0; background: transparent; box-shadow:none; }

  .nb-k-ghost{
    position:absolute;
    left:14px;
    top:50%;
    transform:translateY(-50%);
    pointer-events:none;
    z-index:1;
    font-size:14px;
    color: rgba(15,23,42,.28);
    letter-spacing:.2px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    max-width: calc(100% - 28px);
    transition: opacity .12s ease;
    opacity:.9;
  }
  html.dark .nb-k-ghost{ color: rgba(226,232,240,.30); }

  .tx-dd{ position:absolute; top: calc(44px + 30px); left:0; right:0; z-index:50; border-radius:16px; border:1px solid rgba(15,23,42,.10); background: rgba(255,255,255,.98); box-shadow: 0 18px 40px rgba(2,6,23,.10); overflow:hidden; }
  .tx-dd-item{ width:100%; text-align:left; border:0; background:transparent; padding:10px 12px; display:flex; flex-direction:column; gap:4px; cursor:pointer; }
  .tx-dd-item:hover{ background: rgba(15,23,42,.04); }
  .tx-dd-name{ font-weight:700; font-size:13.5px; color: rgba(11,37,69,.92); }
  .tx-dd-meta{ font-size:12px; color: rgba(11,37,69,.55); }

  .mcard{ margin-top:14px; border:1px solid rgba(15,23,42,.10); border-radius:16px; padding:12px; background: rgba(255,255,255,.92); display:none; }
  .mbadges{ display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
  .mbadge{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; border:1px solid rgba(15,23,42,.12); color: rgba(11,37,69,.85); background: rgba(255,255,255,.75); }
  .mbadge.info{ border-color: rgba(30,136,229,.18); color:#0B3B7A; background: rgba(30,136,229,.08); }
  .mbadge.warn{ border-color: rgba(245,158,11,.22); color:#8A4B08; background: rgba(245,158,11,.10); }
  .mbadge.bad{ border-color: rgba(231,76,60,.22); color:#8B1E16; background: rgba(231,76,60,.10); }
  .mbadge.btn{ cursor:pointer; }

  .tx-list{ margin-top:14px; border:1px solid rgba(15,23,42,.08); border-radius:16px; overflow:hidden; }
  .tx-list table{ width:100%; border-collapse:collapse; }
  .tx-list th, .tx-list td{ padding:12px 12px; border-bottom:1px solid rgba(15,23,42,.06); font-size:13px; }
  .tx-list th{ text-align:left; color: rgba(11,37,69,.70); font-weight:800; background: rgba(15,23,42,.02); }
  .tx-empty{ padding:16px; text-align:center; color: rgba(11,37,69,.60); }

  .tx-actions{ display:flex; gap:10px; justify-content:flex-end; margin-top:12px; }

  .nb-btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:14px; border:1px solid rgba(15,23,42,.12); background: rgba(255,255,255,.9); font-weight:700; font-size:13px; color: rgba(11,37,69,.92); text-decoration:none; cursor:pointer; }
  .nb-btn-primary{ background: linear-gradient(180deg, rgba(30,136,229,1), rgba(21,101,192,1)); color:#fff; border-color: rgba(30,136,229,.35); }
  .nb-btn-danger{ background: rgba(231,76,60,.08); color:#8B1E16; border-color: rgba(231,76,60,.2); }
</style>

<div class="nb-k-wrap">
  <div class="nb-card nb-k-head">
    <div class="nb-k-headTop">
      <div style="min-width:260px;">
        <div class="nb-k-title">{{ $title }}</div>
        <div class="nb-k-sub">Pilih member, scan barcode eksemplar, lalu simpan transaksi.</div>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <a href="{{ route('katalog.index') }}" class="nb-k-ibtn reset" title="Katalog" aria-label="Katalog">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M4 6h16v2H4V6Zm0 5h16v2H4v-2Zm0 5h16v2H4v-2Z"/></svg>
        </a>
        <a href="{{ route('transaksi.riwayat') }}" class="nb-k-ibtn reset" title="Riwayat" aria-label="Riwayat">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12 8v5l4 2-.8 1.6L10 13V8h2Zm0-6a10 10 0 1 1-9.95 11h2.02A8 8 0 1 0 12 4V1l4 3-4 3V4Z"/></svg>
        </a>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('transaksi.pinjam.store') }}" id="form-pinjam">
    @csrf
    <input type="hidden" name="member_id" id="member_id">

    <div class="nb-card" style="padding:14px; margin-top:12px;">
      <div class="nb-k-filter">
        <div class="nb-k-fieldBlock" id="member_block">
          <label class="nb-k-label">Cari Member</label>
          <div class="nb-k-inputWrap">
            <div class="nb-k-ghost" id="member_ghost"></div>
            <input type="text" id="member_search" class="nb-field" placeholder="Ketik minimal 2 huruf / kode member..." autocomplete="off">
          </div>
          <div id="member_results"></div>
        </div>
        <div class="nb-k-fieldBlock">
          <label class="nb-k-label">Jatuh Tempo</label>
          <input type="datetime-local" name="due_at" id="due_at" class="nb-field">
        </div>
      </div>

      <div class="mcard" id="member_card">
        <div><b id="m_name">-</b> <span id="m_meta" class="nb-muted"></span></div>
        <div class="mbadges">
          <span class="mbadge ok">Active</span>
          <span class="mbadge info" id="m_policy">Limit: — • Perpanjang: —</span>
          <span class="mbadge info" id="m_due">Jatuh tempo: otomatis</span>
          <span class="mbadge warn" id="m_warn" style="display:none;">Hampir penuh</span>
          <button type="button" class="mbadge btn" id="btn_change_member">Ganti Member</button>
        </div>
      </div>

      <div class="nb-k-fieldBlock" style="margin-top:12px;">
        <label class="nb-k-label">Scan Barcode</label>
        <input type="text" id="barcode_input" class="nb-field" placeholder="Scan barcode lalu Enter" autocomplete="off">
      </div>

      <div class="tx-list" style="margin-top:12px;">
        <table>
          <thead>
            <tr>
              <th>Barcode</th>
              <th>Judul</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody id="items_body">
            <tr id="items_empty">
              <td colspan="4" class="tx-empty">Belum ada item. Scan barcode untuk menambahkan.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="nb-k-fieldBlock" style="margin-top:12px;">
        <label class="nb-k-label">Catatan</label>
        <textarea name="notes" class="nb-field" rows="3" placeholder="Catatan opsional..."></textarea>
      </div>

      <div class="tx-actions">
        <button type="button" class="nb-btn nb-btn-danger" id="btn_reset">Reset</button>
        <button type="submit" class="nb-btn nb-btn-primary">Simpan Transaksi</button>
      </div>
    </div>
  </form>
</div>

<script>
  const memberSearch = document.getElementById('member_search');
  const memberResults = document.getElementById('member_results');
  const memberGhost = document.getElementById('member_ghost');
  const memberIdInput  = document.getElementById('member_id');

  const memberCard = document.getElementById('member_card');
  const mName = document.getElementById('m_name');
  const mMeta = document.getElementById('m_meta');
  const mDue  = document.getElementById('m_due');
  const mPolicy = document.getElementById('m_policy');
  const mWarn = document.getElementById('m_warn');
  const btnChangeMember = document.getElementById('btn_change_member');

  const barcodeInput = document.getElementById('barcode_input');
  const itemsBody = document.getElementById('items_body');
  const itemsEmpty = document.getElementById('items_empty');
  const btnReset = document.getElementById('btn_reset');

  const memberInfoBase = "{{ route('transaksi.pinjam.member_info', ['id' => '__ID__']) }}";

  const barcodes = new Set();
  let ghostTimer = null;
  let ghostSuggestion = '';

  function measureTextWidth(text, inputEl){
    const style = window.getComputedStyle(inputEl);
    const canvas = measureTextWidth.canvas || (measureTextWidth.canvas = document.createElement('canvas'));
    const ctx = canvas.getContext('2d');
    ctx.font = `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
    return ctx.measureText(text).width;
  }

  function setGhost(typed, suggestion){
    if (!memberGhost) return;
    ghostSuggestion = suggestion || '';
    if (!typed || !suggestion || suggestion.length <= typed.length) {
      memberGhost.textContent = '';
      memberGhost.style.transform = 'translateY(-50%)';
      return;
    }
    const remainder = suggestion.slice(typed.length);
    const offset = measureTextWidth(typed, memberSearch);
    memberGhost.textContent = remainder;
    memberGhost.style.transform = `translateY(-50%) translateX(${offset}px)`;
  }

  function scheduleGhost(typed, suggestion){
    if (ghostTimer) clearTimeout(ghostTimer);
    ghostTimer = setTimeout(() => setGhost(typed, suggestion), 120);
  }

  memberSearch.addEventListener('input', async function(){
    const q = this.value.trim();
    scheduleGhost('', '');
    if(q.length < 2){
      memberResults.innerHTML = '';
      return;
    }
    const url = "{{ route('transaksi.pinjam.cari_member') }}?q=" + encodeURIComponent(q);
    const res = await fetch(url);
    const json = await res.json();

    if(!json.ok || !json.data || json.data.length === 0){
      memberResults.innerHTML = `<div class="tx-dd"><div style="padding:10px 12px;" class="nb-muted">Tidak ditemukan</div></div>`;
      return;
    }

    const top = json.data[0];
    const typed = q.toLowerCase();
    const name = String(top.name || '').trim();
    const code = String(top.username || '').trim();
    if (name.toLowerCase().startsWith(typed)) {
      scheduleGhost(q, name);
    } else if (code.toLowerCase().startsWith(typed)) {
      scheduleGhost(q, code);
    } else {
      scheduleGhost('', '');
    }

    const wrap = document.createElement('div');
    wrap.className = 'tx-dd';

    json.data.forEach(m=>{
      const btn = document.createElement('button');
      btn.type='button';
      btn.className='tx-dd-item';
      btn.onclick = ()=> selectMember(m);

      const parts = String(m.label).split('•').map(s=>s.trim());
      const name = parts[0] || m.label;
      const meta = parts[1] ? ('Kode: ' + parts[1]) : '';
      const role = m.role ? ('Role: ' + String(m.role).toUpperCase()) : '';
      const limit = m.max_items ? ('Limit: ' + String(m.max_items)) : '';
      const metaLine = [meta, role, limit].filter(Boolean).join(' • ');

      btn.innerHTML = `
        <div class="tx-dd-name">${name}</div>
        <div class="tx-dd-meta">${metaLine}</div>
      `;
      wrap.appendChild(btn);
    });

    memberResults.innerHTML = '';
    memberResults.appendChild(wrap);
  });

  function selectMember(m){
    memberIdInput.value = m.id;
    memberResults.innerHTML = '';
    scheduleGhost('', '');
    memberSearch.value = `${m.name} (${m.username})`;

    if (mName) mName.textContent = m.name;
    if (mMeta) mMeta.textContent = m.username || '';

    memberCard.style.display = 'block';
    fetchMemberInfo(m.id);
  }

  async function fetchMemberInfo(memberId){
    if (!memberId) return;
    try {
      const url = memberInfoBase.replace('__ID__', String(memberId));
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      const json = await res.json();
      if (!json.ok || !json.data) return;

      const policy = json.data.policy || {};
      const maxItems = Number(policy.max_items || 0) || 0;
      const maxRenew = Number(policy.max_renewals || 0) || 0;
      const role = String(policy.role || json.data.member_type || 'member');
      const active = Number(json.data.active_items || 0) || 0;

      if (mPolicy) {
        const renewText = maxRenew ? `${maxRenew}x` : '-';
        mPolicy.textContent = `Limit: ${active}/${maxItems || '-'} • Perpanjang: ${renewText} • ${role.toUpperCase()}`;
      }

      if (mWarn) {
        if (maxItems > 0 && active >= maxItems) {
          mWarn.textContent = `Maksimal tercapai (${active}/${maxItems})`;
          mWarn.classList.remove('warn');
          mWarn.classList.add('bad');
          mWarn.style.display = 'inline-flex';
        } else if (maxItems > 0 && active >= Math.max(1, maxItems - 1)) {
          mWarn.textContent = `Hampir penuh (${active}/${maxItems})`;
          mWarn.classList.remove('bad');
          mWarn.classList.add('warn');
          mWarn.style.display = 'inline-flex';
        } else {
          mWarn.style.display = 'none';
        }
      }
    } catch (e) {
      // ignore
    }
  }

  document.addEventListener('click', (e)=>{
    const block = document.getElementById('member_block');
    if(!block.contains(e.target)) memberResults.innerHTML = '';
    if(!block.contains(e.target)) scheduleGhost('', '');
  });

  document.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape') {
      memberResults.innerHTML = '';
      scheduleGhost('', '');
    }
    if(e.key === 'Tab' && ghostSuggestion){
      const cur = memberSearch.value.trim();
      const sug = String(ghostSuggestion);
      if (cur && sug.toLowerCase().startsWith(cur.toLowerCase())) {
        e.preventDefault();
        memberSearch.value = sug;
        scheduleGhost('', '');
        memberSearch.dispatchEvent(new Event('input'));
      }
    }
  });

  btnChangeMember.addEventListener('click', ()=>{
    if (barcodes.size > 0) {
      const ok = confirm('Ganti member akan mengosongkan daftar item yang sudah discan. Lanjutkan?');
      if (!ok) return;
      clearItems();
    }
    memberIdInput.value = '';
    memberSearch.value = '';
    memberResults.innerHTML = '';
    scheduleGhost('', '');
    memberCard.style.display = 'none';
    memberSearch.focus();
  });

  btnReset.addEventListener('click', ()=>{
    if (barcodes.size > 0) {
      const ok = confirm('Reset akan mengosongkan daftar item. Lanjutkan?');
      if (!ok) return;
    }
    clearItems();
    memberIdInput.value = '';
    memberSearch.value = '';
    memberResults.innerHTML = '';
    scheduleGhost('', '');
    memberCard.style.display = 'none';
  });

  function clearItems(){
    barcodes.clear();
    itemsBody.innerHTML = '';
    itemsBody.appendChild(itemsEmpty);
  }

  barcodeInput.addEventListener('keydown', async function(e){
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const code = this.value.trim();
    if (!code) return;
    if (!memberIdInput.value) {
      alert('Pilih member terlebih dahulu.');
      memberSearch.focus();
      return;
    }
    if (barcodes.has(code)) {
      alert('Barcode sudah ada di daftar.');
      this.value = '';
      return;
    }

    const url = "{{ route('transaksi.pinjam.cek_barcode') }}?barcode=" + encodeURIComponent(code);
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    const json = await res.json();
    if (!json.ok) {
      alert(json.message || 'Barcode tidak valid.');
      return;
    }

    barcodes.add(code);
    const item = json.data || {};

    const tr = document.createElement('tr');
    tr.dataset.barcode = code;
    tr.innerHTML = `
      <td>${item.barcode || code}</td>
      <td>${item.title || '-'}</td>
      <td>${item.status || '-'}</td>
      <td><button type="button" class="nb-btn nb-btn-danger btn-remove">Hapus</button></td>
    `;
    tr.querySelector('.btn-remove').addEventListener('click', ()=>{
      barcodes.delete(code);
      tr.remove();
      if (barcodes.size === 0) itemsBody.appendChild(itemsEmpty);
    });

    if (itemsEmpty.parentNode) itemsEmpty.remove();
    itemsBody.appendChild(tr);

    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'barcodes[]';
    hidden.value = code;
    tr.appendChild(hidden);

    this.value = '';
  });

  document.getElementById('form-pinjam').addEventListener('submit', (e)=>{
    if (!memberIdInput.value) {
      e.preventDefault();
      alert('Pilih member terlebih dahulu.');
      memberSearch.focus();
      return;
    }
    if (barcodes.size === 0) {
      e.preventDefault();
      alert('Scan minimal 1 barcode.');
      barcodeInput.focus();
      return;
    }
  });
</script>
@endsection
