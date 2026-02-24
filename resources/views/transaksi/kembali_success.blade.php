@extends('layouts.notobuku')

@section('title', 'Pengembalian Berhasil • NOTOBUKU')

@section('content')
@php
  $title = $title ?? 'Pengembalian Berhasil';

  // stdClass-safe fields
  $loanId      = (int)($loan->id ?? 0);
  $loanCode    = (string)($loan->loan_code ?? '-');
  $loanStatus  = (string)($loan->status ?? 'open');

  $anggotaName  = (string)($loan->anggota_name ?? '-');
  $anggotaCode  = (string)($loan->anggota_code ?? '-');
  $anggotaPhone = (string)($loan->anggota_phone ?? '-');

  $branchName  = (string)($loan->branch_name ?? '-');
  $createdBy   = (string)($loan->created_by_name ?? '-');

  $loanedAtText = !empty($loan->loaned_at)
    ? \Illuminate\Support\Carbon::parse($loan->loaned_at)->format('d/m/Y H:i')
    : '-';

  $dueAtText = !empty($loan->due_at)
    ? \Illuminate\Support\Carbon::parse($loan->due_at)->format('d/m/Y H:i')
    : '-';

  $closedAtText = !empty($loan->closed_at)
    ? \Illuminate\Support\Carbon::parse($loan->closed_at)->format('d/m/Y H:i')
    : '-';

  $itemsCount = is_countable($items ?? []) ? count($items) : 0;

  $returnedLoanItemIds = collect($returned_loan_item_ids ?? [])
    ->map(fn($x)=>(int)$x)->unique()->values()->all();

  $newCount = count($returnedLoanItemIds);

  // status badge class (loan)
  $st = strtolower($loanStatus);
  $statusCls = 'sb warn';
  if ($st === 'open') $statusCls = 'sb ok';
  elseif ($st === 'closed') $statusCls = 'sb ok';
  elseif ($st === 'overdue') $statusCls = 'sb bad';

  // anggota initials
  $init = 'MB';
  if ($anggotaName && $anggotaName !== '-') {
    $parts = preg_split('/\s+/', trim($anggotaName));
    $parts = array_values(array_filter($parts));
    $init = strtoupper(substr($parts[0] ?? 'M', 0, 1) . substr($parts[1] ?? 'B', 0, 1));
  }
@endphp

<style>
  .nb-k-wrap{ max-width:1180px; margin:0 auto; }
  .nb-k-head{ padding:14px; }

  .nb-k-headTop{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .nb-k-title{ font-size:16px; font-weight:650; letter-spacing:.08px; color: rgba(11,37,69,.94); line-height:1.2; }
  html.dark .nb-k-title{ color: rgba(226,232,240,.92); }
  .nb-k-sub{ margin-top:6px; font-size:13px; font-weight:450; color: rgba(11,37,69,.70); line-height:1.35; }
  html.dark .nb-k-sub{ color: rgba(226,232,240,.70); }

  .nb-k-divider{ height:1px; border:0; margin:12px 0; background: linear-gradient(90deg, rgba(15,23,42,.10), rgba(15,23,42,.05), rgba(15,23,42,.10)); }
  html.dark .nb-k-divider{ background: linear-gradient(90deg, rgba(148,163,184,.20), rgba(148,163,184,.10), rgba(148,163,184,.20)); }

  /* Wide buttons */
  .btn-wide{
    height:44px;
    padding:0 14px;
    border-radius:16px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.78);
    display:inline-flex; align-items:center; justify-content:center;
    gap:10px;
    cursor:pointer;
    font-weight:600; /* was 800 */
    font-size:13px;
    color: rgba(11,37,69,.92);
    text-decoration:none;
    transition: box-shadow .12s ease, transform .06s ease, border-color .12s ease, background .12s ease;
    white-space: nowrap;
  }
  .btn-wide:active{ transform: translateY(1px); }
  .btn-wide svg{ width:18px; height:18px; }
  .btn-wide.primary{
    border-color: rgba(30,136,229,.22);
    background: linear-gradient(180deg, rgba(30,136,229,1), rgba(21,101,192,1));
    color:#fff;
    box-shadow: 0 14px 26px rgba(30,136,229,.22);
  }
  .btn-wide.primary:hover{ box-shadow: 0 16px 30px rgba(30,136,229,.26); }
  .btn-wide.primary svg{ color:#fff; }

  .btn-wide.ghost{
    background: rgba(255,255,255,.30);
    border-color: rgba(15,23,42,.12);
  }

  html.dark .btn-wide{
    color: rgba(226,232,240,.92);
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.40);
  }
  html.dark .btn-wide.primary{
    border-color: rgba(59,130,246,.26);
    background: linear-gradient(180deg, rgba(59,130,246,1), rgba(37,99,235,1));
    color:#fff;
  }

  /* Cards */
  .tx-card{ padding:14px; border-radius:18px; overflow:hidden; position:relative; }
  .tx-card::before{ content:""; position:absolute; top:0; left:0; right:0; height:3px; background: rgba(148,163,184,.28); }
  .tx-card.is-ok::before{ background: linear-gradient(90deg, rgba(39,174,96,.95), rgba(30,136,229,.95)); }

  .tx-hd{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
  .tx-hd .t{ font-size:13.8px; font-weight:650; letter-spacing:.06px; color: rgba(11,37,69,.94); line-height:1.2; }
  html.dark .tx-hd .t{ color: rgba(226,232,240,.92); }
  .tx-hd .s{ margin-top:5px; font-size:12.8px; font-weight:450; color: rgba(11,37,69,.70); line-height:1.35; }
  html.dark .tx-hd .s{ color: rgba(226,232,240,.70); }
  .tx-mini{ font-size:12.5px; color: rgba(11,37,69,.60); font-weight:450; }
  html.dark .tx-mini{ color: rgba(226,232,240,.60); }

  /* Anggota summary */
  .mcard{
    margin-top:12px;
    padding:12px;
    border-radius:18px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.70);
  }
  html.dark .mcard{ border-color: rgba(148,163,184,.16); background: rgba(15,23,42,.35); }
  .mrow{ display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
  .mava{
    width:42px; height:42px; border-radius:16px;
    display:flex; align-items:center; justify-content:center;
    font-weight:700; letter-spacing:.06px; /* was 850 */
    color:#fff;
    background: linear-gradient(180deg, rgba(30,136,229,1), rgba(39,174,96,1));
  }
  .mname{ font-weight:600; color: rgba(11,37,69,.92); }
  html.dark .mname{ color: rgba(226,232,240,.92); }
  .mmeta{ font-size:12.5px; font-weight:450; color: rgba(11,37,69,.60); }
  html.dark .mmeta{ color: rgba(226,232,240,.60); }

  .mbadges{ display:flex; gap:8px; flex-wrap:wrap; margin-left:auto; }
  .mbadge{
    padding:8px 10px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.75);
    font-size:12px;
    font-weight:550; /* was 650 */
    color: rgba(11,37,69,.76);
    max-width:100%;
  }
  html.dark .mbadge{ border-color: rgba(148,163,184,.16); background: rgba(15,23,42,.45); color: rgba(226,232,240,.74); }
  .mbadge.ok{ border-color: rgba(39,174,96,.22); background: rgba(39,174,96,.10); }
  .mbadge.info{ border-color: rgba(30,136,229,.22); background: rgba(30,136,229,.10); }
  .mbadge.new{ border-color: rgba(39,174,96,.22); background: rgba(39,174,96,.10); }

  /* Status badge */
  .sb{
    color: rgba(11,37,69,.92);
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 10px;
    border-radius:999px;
    font-weight:550; /* was 650 */
    font-size:12px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.70);
    white-space: nowrap;
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

  /* Table panel */
  .tx-panelInner{
    border:1px solid rgba(15,23,42,.10);
    border-radius:16px;
    overflow:hidden;
    background: rgba(255,255,255,.70);
  }
  html.dark .tx-panelInner{
    border-color: rgba(148,163,184,.18);
    background: rgba(15,23,42,.35);
  }

  .tx-tableWrap{ overflow:hidden; }
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
    font-weight: 650; /* was 800 */
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
    font-weight: 450; /* was 650 */
    color: rgba(11,37,69,.92);
    white-space: normal !important;
    word-break: break-word;
    text-align: left;
  }
  html.dark .tx-tableWrap tbody td{
    border-bottom-color: rgba(148,163,184,.16);
    color: rgba(226,232,240,.92);
  }

  /* highlight returned rows */
  .tx-tableWrap tbody tr.is-new{ background: rgba(39,174,96,.10); }
  html.dark .tx-tableWrap tbody tr.is-new{ background: rgba(34,197,94,.14); }

  /* Hover */
  .tx-tableWrap tbody tr:hover{ background: rgba(30,136,229,.06); }
  html.dark .tx-tableWrap tbody tr:hover{ background: rgba(147,197,253,.10); }

  .tx-col-barcode{ width: 210px; }
  .tx-col-status{ width: 160px; }
  .tx-col-due{ width: 170px; }
  @media (max-width: 560px){
    .tx-col-barcode{ width: 150px; }
    .tx-col-status{ width: 130px; }
    .tx-col-due{ width: 150px; }
  }

  /* Toast */
  .nb-toast{
    position: fixed;
    right: 16px;
    bottom: 16px;
    z-index: 9999;
    min-width: 280px;
    max-width: 420px;
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
  .nb-toast .t{ font-weight:650; font-size:13px; }
  .nb-toast .s{ margin-top:6px; font-weight:450; font-size:12.5px; color: rgba(11,37,69,.72); line-height:1.35; }
  html.dark .nb-toast .s{ color: rgba(226,232,240,.72); }
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

  /* Modal preview */
  .nb-modal{
    position: fixed;
    inset: 0;
    z-index: 9998;
    display:none;
    align-items:center;
    justify-content:center;
    padding: 18px;
    background: rgba(2,6,23,.55);
    backdrop-filter: blur(6px);
  }
  .nb-modal.open{ display:flex; }
  .nb-modalCard{
    width: min(980px, 96vw);
    height: min(84vh, 820px);
    border-radius: 22px;
    border: 1px solid rgba(148,163,184,.18);
    background: rgba(255,255,255,.92);
    overflow:hidden;
    box-shadow: 0 30px 90px rgba(2,6,23,.35);
    display:flex;
    flex-direction:column;
  }
  html.dark .nb-modalCard{
    background: rgba(15,23,42,.92);
    border-color: rgba(148,163,184,.16);
  }
  .nb-modalTop{
    padding: 12px 12px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 10px;
    border-bottom: 1px solid rgba(148,163,184,.18);
  }
  html.dark .nb-modalTop{
    border-bottom-color: rgba(148,163,184,.16);
  }
  .nb-modalTitle{
    font-weight:650;
    font-size: 13.5px;
    color: rgba(11,37,69,.92);
  }
  html.dark .nb-modalTitle{ color: rgba(226,232,240,.92); }
  .nb-modalBody{
    flex:1;
    display:flex;
    gap:0;
    overflow:hidden;
  }
  .nb-modalSide{
    width: 280px;
    border-right: 1px solid rgba(148,163,184,.18);
    padding: 12px;
    overflow:auto;
  }
  html.dark .nb-modalSide{
    border-right-color: rgba(148,163,184,.16);
  }
  .nb-modalMain{
    flex:1;
    overflow:hidden;
    background: rgba(148,163,184,.10);
  }
  html.dark .nb-modalMain{
    background: rgba(148,163,184,.08);
  }
  .nb-ctrlBlock{
    padding: 12px;
    border-radius: 18px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.78);
    margin-bottom: 10px;
  }
  html.dark .nb-ctrlBlock{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.45);
  }
  .nb-ctrlLabel{
    font-size: 12px;
    font-weight:650;
    color: rgba(11,37,69,.82);
  }
  html.dark .nb-ctrlLabel{ color: rgba(226,232,240,.82); }
  .nb-ctrlHint{
    margin-top: 6px;
    font-size: 12px;
    font-weight:450;
    color: rgba(11,37,69,.62);
    line-height:1.35;
  }
  html.dark .nb-ctrlHint{ color: rgba(226,232,240,.62); }

  .nb-select{
    margin-top: 8px;
    width:100%;
    height: 42px;
    border-radius: 14px;
    border: 1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.92);
    padding: 0 12px;
    font-weight:600;
    color: rgba(11,37,69,.92);
  }
  html.dark .nb-select{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.55);
    color: rgba(226,232,240,.92);
  }

  .nb-iframe{
    width:100%;
    height:100%;
    border:0;
    background: #fff;
  }
</style>

<div class="nb-k-wrap">

  <div class="nb-card nb-k-head no-print">

    <div class="nb-k-headTop">
      <div style="min-width:260px;">
        <div class="nb-k-title">{{ $title }}</div>
        <div class="nb-k-sub">
          Pengembalian berhasil disimpan. Kamu bisa pratinjau/cetak slip (termal) atau lihat detail transaksi.
        </div>
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        {{-- Slip --}}
        <button type="button" class="btn-wide" id="btn_preview_slip">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12 5c5 0 9 5.5 9 7s-4 7-9 7-9-5.5-9-7 4-7 9-7Zm0 2c-3.57 0-6.84 3.77-6.98 5 .14 1.23 3.41 5 6.98 5s6.84-3.77 6.98-5c-.14-1.23-3.41-5-6.98-5Z"/></svg>
          Pratinjau Slip
        </button>
        <button type="button" class="btn-wide" id="btn_print_slip">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M19 8H5a3 3 0 0 0-3 3v4h4v4h12v-4h4v-4a3 3 0 0 0-3-3ZM8 19v-5h8v5H8Zm10-7H6v-1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1Z"/></svg>
          Cetak Slip
        </button>

        <a href="{{ route('transaksi.kembali.form') }}" class="btn-wide">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12 5V2L8 6l4 4V7c3.31 0 6 2.69 6 6a6 6 0 0 1-10.39 4.16l-1.42 1.42A8 8 0 0 0 20 13c0-4.42-3.58-8-8-8Z"/></svg>
          Pengembalian Baru
        </a>

        <a href="{{ route('transaksi.riwayat.detail', ['id' => $loanId]) }}" class="btn-wide primary">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12 5c5 0 9 5.5 9 7s-4 7-9 7-9-5.5-9-7 4-7 9-7Zm0 2c-3.57 0-6.84 3.77-6.98 5 .14 1.23 3.41 5 6.98 5s6.84-3.77 6.98-5c-.14 1.23-3.41 5-6.98 5Zm0 2.5A2.5 2.5 0 1 1 9.5 12 2.5 2.5 0 0 1 12 9.5Z"/></svg>
          Lihat Detail
        </a>
      </div>
    </div>

    <hr class="nb-k-divider">

    <div class="nb-card tx-card is-ok" style="margin-top:0;">
      <div class="tx-hd">
        <div>
          <div class="t">Ringkasan Pengembalian</div>
          <div class="s">Kode transaksi, anggota, status, dan waktu.</div>
        </div>
        <div class="tx-mini">{{ $itemsCount }} item</div>
      </div>

      <div class="mcard" style="margin-top:0;">
        <div class="mrow">
          <div class="mava">{{ $init }}</div>

          <div style="min-width:240px;">
            <div class="mname">{{ $anggotaName }}</div>
            <div class="mmeta">Kode: {{ $anggotaCode }} • HP: {{ $anggotaPhone }}</div>
          </div>

          <div class="mbadges">
            <span class="mbadge info">Loan: {{ $loanCode }}</span>
            <span class="mbadge ok">Pinjam: {{ $loanedAtText }}</span>
            <span class="mbadge info">Jatuh tempo: {{ $dueAtText }}</span>
            <span class="mbadge info">Cabang: {{ $branchName }}</span>
            <span class="mbadge info">Petugas: {{ $createdBy }}</span>
            @if($closedAtText !== '-')
              <span class="mbadge ok">Selesai: {{ $closedAtText }}</span>
            @endif
            @if($newCount > 0)
              <span class="mbadge new">Baru dikembalikan: {{ $newCount }}</span>
            @endif
            <span class="{{ $statusCls }}"><span class="dot"></span>{{ strtoupper($loanStatus) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="nb-card tx-card" style="margin-top:12px;">
      <div class="tx-hd">
        <div>
          <div class="t">Daftar Item dalam transaksi</div>
          <div class="s">Baris yang hijau menandakan item yang baru dikembalikan.</div>
        </div>
        <div class="tx-mini">{{ $itemsCount }} baris</div>
      </div>

      <div class="tx-panelInner">
        <div class="tx-tableWrap">
          <table class="nb-table">
            <thead>
              <tr>
                <th class="tx-col-barcode">Barcode</th>
                <th>Judul</th>
                <th style="width:150px;">Call No</th>
                <th class="tx-col-status">Status</th>
                <th class="tx-col-due">Jatuh Tempo</th>
              </tr>
            </thead>
            <tbody>
              @if(empty($items) || $itemsCount === 0)
                <tr>
                  <td colspan="5" class="nb-muted" style="padding:14px 14px;">
                    Tidak ada item untuk transaksi ini.
                  </td>
                </tr>
              @else
                @foreach($items as $it)
                  @php
                    $itLoanItemId = (int)($it->loan_item_id ?? 0);
                    $isNew = in_array($itLoanItemId, $returnedLoanItemIds, true);

                    $itBarcode = (string)($it->barcode ?? '-');
                    $itTitle = (string)($it->title ?? '-');
                    $itCall = (string)($it->call_number ?? '-');

                    $itStatus = (string)($it->loan_item_status ?? $it->item_status ?? 'returned');

                    $itDueText = !empty($it->item_due_at)
                      ? \Illuminate\Support\Carbon::parse($it->item_due_at)->format('d/m/Y H:i')
                      : $dueAtText;

                    $st2 = strtolower($itStatus);
                    $cls = 'sb warn';
                    if (str_contains($st2, 'return')) $cls = 'sb ok';
                    elseif (str_contains($st2, 'available')) $cls = 'sb ok';
                    elseif (str_contains($st2, 'overdue')) $cls = 'sb bad';
                  @endphp
                  <tr class="{{ $isNew ? 'is-new' : '' }}">
                    <td style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">{{ $itBarcode }}</td>
                    <td>{{ $itTitle }}</td>
                    <td>{{ $itCall }}</td>
                    <td><span class="{{ $cls }}"><span class="dot"></span>{{ $itStatus }}</span></td>
                    <td>{{ $itDueText }}</td>
                  </tr>
                @endforeach
              @endif
            </tbody>
          </table>
        </div>
      </div>

      <div class="tx-mini" style="margin-top:10px;">
        Tips: Untuk hilangkan tulisan URL/tanggal saat print, matikan <b>Headers and footers</b> di pengaturan print browser.
      </div>
    </div>

  </div>

  {{-- Modal Preview (Slip) --}}
  <div class="nb-modal no-print" id="modal_preview" aria-hidden="true">
    <div class="nb-modalCard" role="dialog" aria-modal="true">
      <div class="nb-modalTop">
        <div class="nb-modalTitle" id="modal_title">Pratinjau Slip</div>
        <button class="btn-wide" type="button" id="modal_close" style="height:38px;">Tutup</button>
      </div>

      <div class="nb-modalBody">
        <div class="nb-modalSide">
          <div class="nb-ctrlBlock">
            <div class="nb-ctrlLabel">Ukuran Thermal (per PC)</div>
            <div class="nb-ctrlHint">
              Disimpan permanen di PC ini. Default: <b>58mm</b>.
              <br>
              (Jika mau ganti, klik “Ubah ukuran”.)
            </div>
            <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
              <button type="button" class="btn-wide" id="btn_change_paper" style="height:38px;">Ubah ukuran</button>
              <button type="button" class="btn-wide ghost" id="btn_reset_paper" style="height:38px;">Reset</button>
            </div>
          </div>

          <div class="nb-ctrlBlock" id="block_paper_picker" style="display:none;">
            <div class="nb-ctrlLabel">Pilih ukuran</div>
            <select class="nb-select" id="paper_picker">
              <option value="58">58mm</option>
              <option value="80">80mm</option>
            </select>
            <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
              <button type="button" class="btn-wide primary" id="btn_save_paper" style="height:38px;">Simpan</button>
              <button type="button" class="btn-wide" id="btn_cancel_paper" style="height:38px;">Batal</button>
            </div>
          </div>

          <div class="nb-ctrlBlock">
            <div class="nb-ctrlLabel">Aksi</div>
            <div class="nb-ctrlHint">
              Jika masih muncul URL/tanggal/halaman saat print: matikan <b>Headers and footers</b> di dialog print.
            </div>
            <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
              <button type="button" class="btn-wide primary" id="btn_modal_print" style="height:38px;">Cetak dari Pratinjau</button>
              <button type="button" class="btn-wide" id="btn_open_new" style="height:38px;">Buka Tab Baru</button>
            </div>
          </div>
        </div>

        <div class="nb-modalMain">
          <iframe class="nb-iframe" id="preview_iframe" title="Pratinjau Slip"></iframe>
        </div>
      </div>
    </div>
  </div>

  {{-- TOAST: sukses --}}
  <div class="nb-toast no-print" id="toast_ok" style="display:none;">
    <button class="x" type="button" id="toast_close" aria-label="Tutup">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41Z"/></svg>
    </button>
    <div class="t">Sukses ✅</div>
    <div class="s">Pengembalian berhasil disimpan. Loan: <b>{{ $loanCode }}</b> • {{ $newCount }} item baru dikembalikan</div>
  </div>

  <script>
  (function(){
    // ===========================
    // Toast
    // ===========================
    const toast = document.getElementById('toast_ok');
    const toastClose = document.getElementById('toast_close');

    function showToast(el, ms){
      if(!el) return;
      el.style.display = 'block';
      setTimeout(()=>{ try{ el.style.display = 'none'; }catch(e){} }, ms || 6000);
    }
    if(toastClose){
      toastClose.addEventListener('click', ()=>{ toast.style.display = 'none'; });
    }
    showToast(toast, 5500);

    // ===========================
    // Printer Paper Preference (per PC)
    // ===========================
    const PAPER_KEY = 'nb_printer_paper_width';
    function getPaper(){
      const v = localStorage.getItem(PAPER_KEY);
      return (v === '80') ? '80' : '58';
    }
    function setPaper(v){
      localStorage.setItem(PAPER_KEY, (v === '80') ? '80' : '58');
    }

    // ===========================
    // Print URL (reuse existing route transaksi.riwayat.print)
    // ===========================
    const loanId = @json($loanId);
    function buildPrintUrl(paper){
      const url = new URL(@json(route('transaksi.riwayat.print', ['id' => $loanId])), window.location.origin);
      url.searchParams.set('size', paper);
      return url.toString();
    }

    function openDirect(){
      const url = buildPrintUrl(getPaper());
      window.open(url, '_blank', 'width=520,height=720');
    }

    // ===========================
    // Modal Preview
    // ===========================
    const modal = document.getElementById('modal_preview');
    const modalClose = document.getElementById('modal_close');
    const modalTitle = document.getElementById('modal_title');

    const blockPicker = document.getElementById('block_paper_picker');
    const paperPicker = document.getElementById('paper_picker');

    const iframe = document.getElementById('preview_iframe');
    const btnModalPrint = document.getElementById('btn_modal_print');
    const btnOpenNew = document.getElementById('btn_open_new');

    const btnChangePaper = document.getElementById('btn_change_paper');
    const btnResetPaper = document.getElementById('btn_reset_paper');
    const btnSavePaper = document.getElementById('btn_save_paper');
    const btnCancelPaper = document.getElementById('btn_cancel_paper');

    function setModalOpen(open){
      if(!modal) return;
      modal.classList.toggle('open', !!open);
      modal.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    function refreshIframe(){
      const paper = getPaper();
      if(modalTitle) modalTitle.textContent = 'Pratinjau Slip (' + paper + 'mm)';
      if(iframe) iframe.src = buildPrintUrl(paper);
    }

    function openPreview(){
      if(paperPicker) paperPicker.value = getPaper();
      if(blockPicker) blockPicker.style.display = 'none';
      refreshIframe();
      setModalOpen(true);
    }

    if(modalClose){
      modalClose.addEventListener('click', ()=> setModalOpen(false));
    }
    if(modal){
      modal.addEventListener('click', (e)=>{
        if(e.target === modal) setModalOpen(false);
      });
    }

    if(btnChangePaper){
      btnChangePaper.addEventListener('click', ()=>{
        if(!blockPicker || !paperPicker) return;
        paperPicker.value = getPaper();
        blockPicker.style.display = 'block';
      });
    }
    if(btnCancelPaper){
      btnCancelPaper.addEventListener('click', ()=>{
        if(blockPicker) blockPicker.style.display = 'none';
      });
    }
    if(btnSavePaper){
      btnSavePaper.addEventListener('click', ()=>{
        if(!paperPicker) return;
        setPaper(paperPicker.value);
        if(blockPicker) blockPicker.style.display = 'none';
        refreshIframe();
      });
    }
    if(btnResetPaper){
      btnResetPaper.addEventListener('click', ()=>{
        setPaper('58');
        if(paperPicker) paperPicker.value = '58';
        if(blockPicker) blockPicker.style.display = 'none';
        refreshIframe();
      });
    }

    if(btnModalPrint){
      btnModalPrint.addEventListener('click', ()=>{
        try{
          iframe.contentWindow.focus();
          iframe.contentWindow.print();
        }catch(e){
          openDirect();
        }
      });
    }
    if(btnOpenNew){
      btnOpenNew.addEventListener('click', ()=> openDirect());
    }

    // ===========================
    // Buttons
    // ===========================
    const btnPrevSlip = document.getElementById('btn_preview_slip');
    const btnPrintSlip = document.getElementById('btn_print_slip');

    if(btnPrevSlip) btnPrevSlip.addEventListener('click', ()=> openPreview());
    if(btnPrintSlip) btnPrintSlip.addEventListener('click', ()=> openDirect());

  })();
  </script>

</div>
@endsection



