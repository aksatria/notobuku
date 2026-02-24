@extends('layouts.notobuku')

@section('title', 'Transaksi Kembali • NOTOBUKU')

@section('content')
@php
  $title = $title ?? 'Transaksi Kembali';
@endphp

<style>
  .nb-k-wrap{ max-width:1180px; margin:0 auto; }
  .nb-k-head{ padding:14px; }

  .nb-k-headTop{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .nb-k-title{ font-size:16px; font-weight:800; letter-spacing:.1px; color: rgba(11,37,69,.94); line-height:1.2; }
  html.dark .nb-k-title{ color: rgba(226,232,240,.92); }
  .nb-k-sub{ margin-top:6px; font-size:13px; font-weight:500; color: rgba(11,37,69,.70); line-height:1.35; }
  html.dark .nb-k-sub{ color: rgba(226,232,240,.70); }

  .nb-k-divider{ height:1px; border:0; margin:12px 0; background: linear-gradient(90deg, rgba(15,23,42,.10), rgba(15,23,42,.05), rgba(15,23,42,.10)); }
  html.dark .nb-k-divider{ background: linear-gradient(90deg, rgba(148,163,184,.20), rgba(148,163,184,.10), rgba(148,163,184,.20)); }

  .nb-k-ibtn{
    width:44px; height:44px;
    border-radius:16px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.78);
    display:inline-flex; align-items:center; justify-content:center;
    cursor:pointer; user-select:none; text-decoration:none;
    transition: background .12s ease, border-color .12s ease, transform .06s ease, box-shadow .12s ease, color .12s ease;
  }
  .nb-k-ibtn:active{ transform: translateY(1px); }
  .nb-k-ibtn svg{ width:18px; height:18px; }
  .nb-k-ibtn.apply{
    border-color: rgba(30,136,229,.22);
    background: linear-gradient(180deg, rgba(30,136,229,1), rgba(21,101,192,1));
    color:#fff;
    box-shadow: 0 14px 26px rgba(30,136,229,.22);
  }
  .nb-k-ibtn.apply:hover{ box-shadow: 0 16px 30px rgba(30,136,229,.26); }
  .nb-k-ibtn.apply svg{ color:#fff; }
  .nb-k-ibtn.reset{ color: rgba(11,37,69,.92); }
  html.dark .nb-k-ibtn.reset{
    color: rgba(226,232,240,.92);
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.40);
  }

  .tx-card{ padding:14px; border-radius:18px; overflow:hidden; position:relative; }
  .tx-card::before{ content:""; position:absolute; top:0; left:0; right:0; height:3px; background: rgba(148,163,184,.28); }
  .tx-card.is-ok::before{ background: linear-gradient(90deg, rgba(39,174,96,.95), rgba(30,136,229,.95)); }

  .tx-hd{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
  .tx-hd .t{ font-size:13.8px; font-weight:780; letter-spacing:.08px; color: rgba(11,37,69,.94); line-height:1.2; }
  html.dark .tx-hd .t{ color: rgba(226,232,240,.92); }
  .tx-hd .s{ margin-top:5px; font-size:12.8px; font-weight:500; color: rgba(11,37,69,.70); line-height:1.35; }
  html.dark .tx-hd .s{ color: rgba(226,232,240,.70); }
  .tx-mini{ font-size:12.5px; color: rgba(11,37,69,.60); font-weight:500; }
  html.dark .tx-mini{ color: rgba(226,232,240,.60); }

  .tx-grid{ display:grid; grid-template-columns: 1fr; gap:14px; }

  .nb-k-label{ display:block; margin-bottom:6px; font-size:12px; font-weight:650; letter-spacing:.12px; color: rgba(11,37,69,.76); }
  html.dark .nb-k-label{ color: rgba(226,232,240,.76); }
  .nb-k-label-ghost{ opacity:0; pointer-events:none; user-select:none; }
  .nb-field{ height:44px; border-radius:999px !important; padding-left:14px; padding-right:14px; }

  .tx-full{ margin-top:10px; margin-left:-14px; margin-right:-14px; margin-bottom:-14px; }
  .tx-panel{ padding: 10px 14px 14px; }
  .tx-panelInner{
    border:1px solid rgba(15,23,42,.10);
    border-radius:16px;
    overflow:visible;
    background: rgba(255,255,255,.70);
  }
  html.dark .tx-panelInner{
    border-color: rgba(148,163,184,.18);
    background: rgba(15,23,42,.35);
  }

  .tx-tableWrap{ overflow: visible; }
  .tx-tableWrap .nb-table{
    width:100%;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
  }
  .tx-tableWrap thead th{
    background: rgba(255,255,255,.98);
    font-size: 12.5px;
    letter-spacing: .10px;
    font-weight: 800;
    color: rgba(11,37,69,.92);
    padding: 14px 14px;
    line-height: 1.35;
    vertical-align: middle;
    border-bottom: 1px solid rgba(15,23,42,.12);
    text-align: left;
    white-space: nowrap;
  }
  html.dark .tx-tableWrap thead th{
    background: rgba(15,27,46,.96);
    color: rgba(226,232,240,.92);
    border-bottom-color: rgba(148,163,184,.22);
  }
  .tx-tableWrap tbody td{
    padding: 14px 14px;
    line-height: 1.4;
    vertical-align: top;
    border-bottom: 1px solid rgba(15,23,42,.08);
    font-size: 13.5px;
    font-weight: 650;
    color: rgba(11,37,69,.92);
    white-space: normal !important;
    overflow-wrap: anywhere;
    word-break: break-word;
    text-align: left;
  }
  html.dark .tx-tableWrap tbody td{
    border-bottom-color: rgba(148,163,184,.16);
    color: rgba(226,232,240,.92);
  }
  .tx-tableWrap tbody tr:nth-child(even){ background: rgba(2,6,23,.02); }
  html.dark .tx-tableWrap tbody tr:nth-child(even){ background: rgba(255,255,255,.03); }
  .tx-tableWrap tbody tr:hover{ background: rgba(30,136,229,.06); }
  html.dark .tx-tableWrap tbody tr:hover{ background: rgba(147,197,253,.10); }

  /* columns (mirip perpanjang) */
  .tx-col-barcode{ width: 30%; }
  .tx-col-loan{ width: 22%; }
  .tx-col-due{ width: 28%; }
  .tx-col-status{ width: 16%; }
  .tx-col-action{ width: 50px; text-align:right; }

  .sb{
    white-space: nowrap;
    color: rgba(11,37,69,.92);
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 10px;
    border-radius:999px;
    font-weight:650;
    font-size:12px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.70);
    max-width:100%;
  }
  html.dark .sb{
    color: rgba(226,232,240,.92);
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.45);
  }
  .sb.ok{ border-color: rgba(39,174,96,.22); background: rgba(39,174,96,.10); }
  .sb.warn{ border-color: rgba(30,136,229,.22); background: rgba(30,136,229,.10); }
  .sb.bad{ border-color: rgba(231,76,60,.22); background: rgba(231,76,60,.10); }
  .dot{ width:10px; height:10px; border-radius:999px; background: rgba(148,163,184,.70); }
  .sb.ok .dot{ background: rgba(39,174,96,.95); }
  .sb.warn .dot{ background: rgba(30,136,229,.95); }
  .sb.bad .dot{ background: rgba(231,76,60,.95); }

  .btn-del{ width:36px; height:36px; border-radius:12px; }

  .tx-footer{
    color: rgba(11,37,69,.78);
    padding:12px 14px;
    border-top: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.88);
  }
  html.dark .tx-footer{
    color: rgba(226,232,240,.78);
    border-top-color: rgba(148,163,184,.18);
    background: rgba(15,27,46,.74);
  }

  .tx-empty{
    padding:40px 20px;
    text-align:center;
    color: rgba(11,37,69,.65);
    font-size:13.5px;
  }
  html.dark .tx-empty{ color: rgba(226,232,240,.65); }
  .tx-empty .ico{ font-size:28px; margin-bottom:8px; opacity:.6; }

  .row-hi{ animation: rowPulse 1.0s ease-out 1; }
  @keyframes rowPulse{ 0%{ background: rgba(30,136,229,.12); } 100%{ background: transparent; } }

  .nb-toast{
    position: fixed;
    right: 16px;
    bottom: 16px;
    z-index: 9999;
    min-width: 260px;
    max-width: 360px;
    padding: 12px 12px;
    border-radius: 18px;
    border: 1px solid rgba(39,174,96,.22);
    background: rgba(39,174,96,.10);
    box-shadow: 0 18px 40px rgba(2,6,23,.14);
    color: rgba(11,37,69,.92);
  }
  html.dark .nb-toast{
    border-color: rgba(34,197,94,.26);
    background: rgba(34,197,94,.14);
    box-shadow: 0 18px 40px rgba(0,0,0,.35);
    color: rgba(226,232,240,.92);
  }
  .nb-toast .t{ font-weight:900; font-size:13px; }
  .nb-toast .s{ margin-top:6px; font-weight:650; font-size:12.5px; color: rgba(11,37,69,.70); }
  html.dark .nb-toast .s{ color: rgba(226,232,240,.70); }
  .nb-toast .x{
    position:absolute; top:10px; right:10px;
    width:34px; height:34px;
    border-radius:14px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.70);
    cursor:pointer;
    display:flex; align-items:center; justify-content:center;
  }
  html.dark .nb-toast .x{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.45);
  }
</style>

<div class="nb-k-wrap">
  <div class="nb-card nb-k-head">

    <div class="nb-k-headTop">
      <div style="min-width:260px;">
        <div class="nb-k-title">{{ $title }}</div>
        <div class="nb-k-sub">Scan barcode item untuk pengembalian, review daftar, lalu simpan.</div>
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <a href="{{ route('katalog.index') }}" class="nb-k-ibtn reset" title="Katalog" aria-label="Katalog">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M4 6h16v2H4V6Zm0 5h16v2H4v-2Zm0 5h16v2H4v-2Z"/></svg>
        </a>
        <a href="{{ route('transaksi.riwayat') }}" class="nb-k-ibtn reset" title="Riwayat" aria-label="Riwayat">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M13 3a9 9 0 1 0 8.95 10h-2.02A7 7 0 1 1 13 5v3l4-4-4-4v3Zm1 6h-2v6l5 3 1-1.73-4-2.27V9Z"/></svg>
        </a>
      </div>
    </div>

    <hr class="nb-k-divider">

    <div style="height:12px"></div>

    @if(session('success'))
      <div class="nb-alert nb-alert-success">{{ session('success') }}</div>
      <div style="height:10px"></div>
    @endif
    @if(session('error'))
      <div class="nb-alert nb-alert-danger">{{ session('error') }}</div>
      <div style="height:10px"></div>
    @endif

    <form method="POST" action="{{ route('transaksi.kembali.store') }}" id="form-kembali">
      @csrf

      <div class="tx-grid">

        <div class="nb-card tx-card is-ok">
          <div class="tx-hd">
            <div>
              <div class="t">Scan / Input Barcode</div>
              <div class="s">Scan barcode lalu tekan <b>Enter</b> untuk menambahkan item ke daftar pengembalian.</div>
            </div>
          </div>

          <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
            <div style="flex:1; min-width:260px;">
              <label class="nb-k-label">Barcode</label>
              <input type="text" id="barcode_input" class="nb-field" placeholder="Scan barcode item..." autocomplete="off">
              <div class="tx-mini" style="margin-top:8px;" id="scan_hint">Siap scan. Tekan Enter untuk menambah.</div>
            </div>

            <div style="flex:0; min-width:44px;">
              <label class="nb-k-label nb-k-label-ghost">Tambah</label>
              <button type="button" class="nb-k-ibtn apply" id="btn_add_barcode" title="Tambah item" aria-label="Tambah item">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M19 11H13V5h-2v6H5v2h6v6h2v-6h6v-2Z"/></svg>
              </button>
            </div>
          </div>
        </div>

        <div class="nb-card tx-card">
          <div class="tx-hd">
            <div>
              <div class="t">Daftar Item Dikembalikan</div>
              <div class="s">Cek loan & jatuh tempo. Hapus jika salah scan.</div>
            </div>
            <div class="tx-mini" id="count_info">0 item</div>
          </div>

          <div class="tx-full">
            <div class="tx-panel">
              <div class="tx-panelInner">

                <div class="tx-tableWrap">
                  <table class="nb-table">
                    <thead>
                      <tr>
                        <th class="tx-col-barcode">Barcode</th>
                        <th class="tx-col-loan">Loan</th>
                        <th class="tx-col-due">Jatuh Tempo</th>
                        <th class="tx-col-status">Status</th>
                        <th class="tx-col-action"></th>
                      </tr>
                    </thead>
                    <tbody id="items_table">
                      <tr id="items_empty">
                        <td colspan="5" class="nb-muted" style="padding:14px 14px;">
                          <div class="tx-empty">
                            <div class="ico">↩️</div>
                            <b>Belum ada item</b><br>
                            Scan barcode untuk menambahkan ke pengembalian.
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                {{-- hidden inputs appended here --}}
                <div id="return_inputs"></div>

                <div class="tx-footer">
                  <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:center;">
                    <div class="tx-mini">Tips: Tekan <b>Enter</b> untuk menambah item • Tekan <b>Esc</b> untuk fokus ke scan</div>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                      <a href="{{ route('transaksi.kembali.form') }}" class="nb-k-ibtn reset" title="Atur Ulang" aria-label="Atur Ulang">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12 5V2L8 6l4 4V7c3.31 0 6 2.69 6 6a6 6 0 0 1-10.39 4.16l-1.42 1.42A8 8 0 0 0 20 13c0-4.42-3.58-8-8-8Z"/></svg>
                      </a>

                      <button type="submit" class="nb-k-ibtn apply" title="Simpan pengembalian" aria-label="Simpan pengembalian">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M9 16.2 4.8 12l1.4-1.4L9 13.4l8.8-8.8L19.2 6 9 16.2Z"/></svg>
                      </button>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>

        </div>

      </div>
    </form>

  </div>
</div>

@if(session('success'))
  <div class="nb-toast" id="toast_ok">
    <button class="x" type="button" id="toast_close" aria-label="Tutup">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41Z"/></svg>
    </button>
    <div class="t">Sukses ✅</div>
    <div class="s">{{ session('success') }}</div>
  </div>
@endif

<script>
(function(){
  const barcodeInput = document.getElementById('barcode_input');
  const btnAddBarcode = document.getElementById('btn_add_barcode');

  const itemsTable = document.getElementById('items_table');
  const itemsEmpty = document.getElementById('items_empty');
  const inputsWrap = document.getElementById('return_inputs');
  const countInfo  = document.getElementById('count_info');

  const form = document.getElementById('form-kembali');

  // toast close
  const toast = document.getElementById('toast_ok');
  const toastClose = document.getElementById('toast_close');
  if(toast && toastClose){
    toastClose.addEventListener('click', ()=> toast.remove());
    setTimeout(()=>{ if(toast) toast.remove(); }, 6000);
  }

  // key: loan_item_id
  let added = new Map();

  function escapeHtml(str){
    return String(str)
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  function updateCount(){
    countInfo.textContent = `${added.size} item`;
  }

  function formatDateTime(dtStr){
    if(!dtStr) return '-';
    const iso = String(dtStr).includes('T') ? dtStr : String(dtStr).replace(' ', 'T');
    const d = new Date(iso);
    if(String(d) === 'Invalid Date') return String(dtStr);
    const dd = String(d.getDate()).padStart(2,'0');
    const mm = String(d.getMonth()+1).padStart(2,'0');
    const yy = d.getFullYear();
    const hh = String(d.getHours()).padStart(2,'0');
    const mi = String(d.getMinutes()).padStart(2,'0');
    return `${dd}/${mm}/${yy} ${hh}:${mi}`;
  }

  async function addBarcode(){
    const barcode = (barcodeInput.value || '').trim();
    if(!barcode) return;

    const url = "{{ route('transaksi.kembali.cek_barcode') }}?barcode=" + encodeURIComponent(barcode);
    const res = await fetch(url);
    let json = null;

    try { json = await res.json(); }
    catch(e){
      alert('Gagal membaca respon server.');
      barcodeInput.focus();
      return;
    }

    if(!json.ok){
      alert(json.message || 'Barcode tidak valid');
      barcodeInput.focus();
      return;
    }

    const data = json.data || {};

    const loanItemId = Number(data.loan_item_id || 0);
    if(!loanItemId){
      alert('Data pengembalian tidak valid (loan_item_id kosong).');
      barcodeInput.focus();
      return;
    }

    if(added.has(loanItemId)){
      alert('Item ini sudah ada di daftar pengembalian.');
      barcodeInput.value = '';
      barcodeInput.focus();
      return;
    }

    if(itemsEmpty) itemsEmpty.style.display = 'none';

    const loanCode = data.loan_code ? String(data.loan_code) : '-';
    const detailUrl = data.detail_url ? String(data.detail_url) : null;

    const dueAt = formatDateTime(data.due_at ?? null);

    // status badge: default "Dipinjam" (akan dikembalikan)
    let cls = 'sb ok';
    let label = 'Dipinjam';
    const statusRaw = String(data.loan_status ?? data.status ?? '').toLowerCase();

    // jika loan overdue atau due_at lewat -> tampilkan "Terlambat"
    try{
      if(statusRaw.includes('overdue')){ cls = 'sb bad'; label = 'Terlambat'; }
      else if(data.due_at){
        const t = Date.parse(String(data.due_at).includes('T') ? data.due_at : String(data.due_at).replace(' ', 'T'));
        if(!Number.isNaN(t) && t < Date.now()){
          cls = 'sb bad'; label = 'Terlambat';
        }
      }
    }catch(e){}

    const tr = document.createElement('tr');
    tr.classList.add('row-hi');
    tr.innerHTML = `
      <td style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">
        ${escapeHtml(data.barcode || barcode)}
      </td>
      <td>
        ${detailUrl ? `<a href="${escapeHtml(detailUrl)}" style="text-decoration:none; font-weight:900; color: inherit;">${escapeHtml(loanCode)}</a>`
                    : `<span style="font-weight:900;">${escapeHtml(loanCode)}</span>`}
      </td>
      <td>${escapeHtml(dueAt)}</td>
      <td><span class="${cls}"><span class="dot"></span>${escapeHtml(label)}</span></td>
      <td class="tx-col-action">
        <button type="button" class="nb-k-ibtn reset btn-del" title="Hapus" aria-label="Hapus">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41Z"/>
          </svg>
        </button>
      </td>
    `;

    const btnDel = tr.querySelector('button');
    btnDel.onclick = ()=> removeItem(loanItemId, tr);

    itemsTable.appendChild(tr);

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'loan_item_ids[]';
    input.value = String(loanItemId);
    input.id = 'li_' + loanItemId;
    inputsWrap.appendChild(input);

    added.set(loanItemId, tr);

    barcodeInput.value = '';
    barcodeInput.focus();

    updateCount();
  }

  function removeItem(loanItemId, row){
    added.delete(loanItemId);
    if(row) row.remove();

    const hid = document.getElementById('li_' + loanItemId);
    if(hid) hid.remove();

    if(added.size === 0 && itemsEmpty){
      itemsEmpty.style.display = '';
    }
    updateCount();
  }

  btnAddBarcode.addEventListener('click', addBarcode);
  barcodeInput.addEventListener('keydown', (e)=>{
    if(e.key === 'Enter'){ e.preventDefault(); addBarcode(); }
    if(e.key === 'Escape'){ e.preventDefault(); barcodeInput.select(); }
  });

  form.addEventListener('submit', (e)=>{
    if(added.size === 0){
      e.preventDefault();
      alert('Minimal 1 item harus ditambahkan.');
      barcodeInput.focus();
      return;
    }
  });

  updateCount();
  setTimeout(()=> barcodeInput.focus(), 120);
})();
</script>
@endsection
