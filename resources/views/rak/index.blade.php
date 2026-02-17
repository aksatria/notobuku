{{-- resources/views/rak/index.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Rak • NOTOBUKU')

@section('content')
@php
  $role = auth()->user()->role ?? 'member';
  $canManage = in_array($role, ['super_admin','admin','staff'], true);

  // variabel aman (kalau controller belum set)
  $q        = (string)($q ?? request()->query('q',''));
  $branchId = (string)($branchId ?? request()->query('branch_id',''));
  $status   = (string)($status ?? request()->query('status',''));

  $branches = $branches ?? collect();
  $shelves  = $shelves ?? null;

  $totalTxt = ($shelves && method_exists($shelves,'total')) ? $shelves->total().' rak' : '—';
@endphp

<style>
/* =========================================================
   NOTOBUKU • Rak • Index (REDESIGN TOTAL - FIXED)
   - Konsisten dengan Katalog Create
   - Field rapi (label + input)
   - Aksi icon only (inline SVG, pasti muncul)
   - Hover terbaca
   ========================================================= */

  .rk-wrap{ max-width:none; width:100%; margin:0; }
  .rk-shell{ padding:12px; }

  .rk-head{
    display:flex; align-items:flex-start; justify-content:space-between;
    gap:12px; flex-wrap:wrap; margin-bottom:12px;
  }
  .rk-head .title{ font-weight:700; letter-spacing:.10px; font-size:18px; margin:0; }
  .rk-head .sub{ margin-top:4px; font-size:13px; }

  .rk-layout{
    display:grid;
    grid-template-columns:minmax(0,1fr) 320px;
    gap:14px;
    align-items:start;
  }
  .rk-side{ position:sticky; top:14px; }

  .rk-section{
    padding:14px;
    border:1px solid var(--nb-border);
    border-radius:16px;
    background:var(--nb-surface);
  }
  .rk-section + .rk-section{ margin-top:12px; }

  /* Aksen seperti Katalog Create */
  .rk-section.acc-blue  { background: linear-gradient(180deg, rgba(59,130,246,.11), rgba(59,130,246,.05)); border-color: rgba(59,130,246,.22); }
  .rk-section.acc-green { background: linear-gradient(180deg, rgba(16,185,129,.11), rgba(16,185,129,.05)); border-color: rgba(16,185,129,.22); }
  .rk-section.acc-slate { background: linear-gradient(180deg, rgba(30,41,59,.07), rgba(30,41,59,.03)); border-color: rgba(30,41,59,.14); }

  html.dark .rk-section.acc-blue  { background: rgba(30,136,229,.12); border-color: rgba(30,136,229,.18); }
  html.dark .rk-section.acc-green { background: rgba(39,174,96,.12);  border-color: rgba(39,174,96,.18); }
  html.dark .rk-section.acc-slate { background: rgba(148,163,184,.08); border-color: rgba(148,163,184,.14); }

  .rk-section-head{
    display:flex; align-items:flex-start; justify-content:space-between; gap:10px;
    padding-bottom:10px; margin-bottom:12px;
    border-bottom:1px solid var(--nb-border);
  }
  .rk-section-head .h{ font-weight:700; letter-spacing:.1px; font-size:13.5px; }
  .rk-section-head .hint{ font-size:12.5px; margin:0; }

  /* ---------- Filter field (FIX aneh) ---------- */
  .rk-field{ margin-bottom:12px; }
  .rk-field label{
    display:block;
    font-weight:600;
    font-size:12.5px;
    margin-bottom:6px;
  }
  .rk-field .nb-field{
    width:100% !important;
    box-sizing:border-box;
    font-size:13px;
    line-height:1.4;
    padding:9px 11px;
    border-radius:14px;
  }
  .rk-help{ margin-top:5px; font-size:12.3px; line-height:1.45; }
  .rk-actionsRow{
    display:flex; gap:10px; flex-wrap:wrap;
    margin-top:10px;
  }
  .rk-actionsRow .nb-btn,
  .rk-actionsRow .nb-btn-primary{ border-radius:14px; }

  /* ---------- List/table look (rapi & modern) ---------- */
  .rk-tableWrap{
    border:1px solid var(--nb-border);
    border-radius:16px;
    overflow:hidden;
    background: rgba(255,255,255,.65);
  }
  html.dark .rk-tableWrap{ background: rgba(255,255,255,.05); }

  .rk-table{ width:100%; border-collapse:collapse; }
  .rk-table thead th{
    text-align:left;
    font-weight:700;
    font-size:12.6px;
    padding:10px 12px;
    background: rgba(15,23,42,.06);
    border-bottom:1px solid var(--nb-border);
    white-space:nowrap;
  }
  html.dark .rk-table thead th{ background: rgba(255,255,255,.03); }

  .rk-table tbody td{
    padding:10px 12px;
    border-bottom:1px solid var(--nb-border);
    vertical-align:top;
  }
  .rk-table tbody tr:last-child td{ border-bottom:0; }

  .rk-name{ font-weight:600; font-size:13px; line-height:1.35; }
  .rk-meta{
    margin-top:3px;
    font-size:12px;
    color: rgba(11,37,69,.62);
    line-height:1.35;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    max-width:100%;
  }
  html.dark .rk-meta{ color: rgba(226,232,240,.62); }

  .rk-badge{
    display:inline-flex; align-items:center; gap:6px;
    padding:5px 10px;
    border-radius:999px;
    font-size:11.5px;
    font-weight:600;
    border:1px solid rgba(15,23,42,.14);
    background: rgba(15,23,42,.03);
    white-space:nowrap;
  }
  .rk-badge.on { background: rgba(16,185,129,.14); border-color: rgba(16,185,129,.28); color:#065f46; }
  .rk-badge.off{ background: rgba(239,68,68,.14); border-color: rgba(239,68,68,.28); color:#991b1b; }

  .rk-dot{
    width:8px; height:8px; border-radius:999px;
    background: rgba(15,23,42,.35);
  }
  .rk-badge.on .rk-dot { background: rgba(39,174,96,.85); }
  .rk-badge.off .rk-dot{ background: rgba(220,38,38,.85); }

  /* ---------- Icon actions (visible + hover readable) ---------- */
  .rk-acts{
    display:flex;
    justify-content:flex-end;
    gap:6px;
    white-space:nowrap;
  }

  .rk-iconBtn{
    width:30px; height:30px;
    border-radius:10px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:1px solid var(--nb-border);
    background: rgba(15,23,42,.04);
    cursor:pointer;
    transition: transform .08s ease, background .15s ease, border-color .15s ease;
  }
  html.dark .rk-iconBtn{ background: rgba(255,255,255,.04); }

  .rk-iconBtn:hover{
    background: rgba(30,136,229,.10);
    border-color: rgba(30,136,229,.28);
    transform: translateY(-1px);
  }
  .rk-iconBtn:active{ transform: translateY(0px); }

  .rk-iconBtn svg{
    width:15px; height:15px;
    stroke: rgba(11,37,69,.78);
  }
  html.dark .rk-iconBtn svg{
    stroke: rgba(226,232,240,.82);
  }

  /* tombol bahaya */
  .rk-iconBtn--danger:hover{
    background: rgba(220,38,38,.10);
    border-color: rgba(220,38,38,.26);
  }
  .rk-iconBtn--danger:hover svg{ stroke: rgba(220,38,38,.95); }

  /* toggle */
  .rk-iconBtn--toggle:hover{
    background: rgba(39,174,96,.10);
    border-color: rgba(39,174,96,.26);
  }
  .rk-iconBtn--toggle:hover svg{ stroke: rgba(39,174,96,.95); }

  .rk-empty{
    padding:14px;
    border:1px dashed rgba(15,23,42,.18);
    border-radius:16px;
    background: rgba(15,23,42,.02);
    font-size:12.8px;
    line-height:1.55;
  }

  /* ---------- Pagination ---------- */
  .rk-pagination{
    margin-top:12px;
    padding:8px;
    border:1px solid var(--nb-border);
    border-radius:14px;
    background: rgba(255,255,255,.7);
    overflow:hidden;
  }
  html.dark .rk-pagination{ background: rgba(255,255,255,.04); }
  .rk-pager{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:8px;
    flex-wrap:wrap;
    font-size:12px;
  }
  .rk-pager-meta{
    color: var(--nb-muted);
    font-weight:500;
    font-size:12px;
  }
  .rk-pager-track{
    display:flex;
    align-items:center;
    justify-content:flex-start;
    gap:6px;
    overflow-x:auto;
    max-width:100%;
    width:100%;
    padding:2px 0;
    scrollbar-width:thin;
  }
  .rk-page-btn{
    border-radius:10px !important;
    border:1px solid rgba(148,163,184,.35) !important;
    min-width:30px;
    height:28px;
    display:inline-flex !important;
    align-items:center;
    justify-content:center;
    padding:0 10px !important;
    font-size:11.5px !important;
    line-height:1;
    font-weight:500 !important;
    background:#fff;
    color:#1e293b;
    text-decoration:none !important;
    white-space:nowrap;
    flex:0 0 auto;
  }
  .rk-page-btn.is-disabled{ opacity:.5; pointer-events:none; }
  .rk-page-btn.is-ellipsis{ min-width:30px; }
  .rk-page-btn.is-active{
    background: linear-gradient(90deg, #3b82f6, #2563eb) !important;
    color:#fff !important;
    border-color: transparent !important;
    font-weight:600 !important;
  }
  .rk-page-btn:not(.is-disabled):not(.is-active):hover{
    background: rgba(59,130,246,.12);
    border-color: rgba(59,130,246,.35) !important;
    color:#1d4ed8;
  }
  html.dark .rk-page-btn{
    background: rgba(15,23,42,.65);
    color:#e2e8f0;
    border-color: rgba(148,163,184,.35) !important;
  }
  html.dark .rk-empty{
    border-color: rgba(148,163,184,.22);
    background: rgba(255,255,255,.03);
  }

  @media(max-width:1200px){
    .rk-layout{ grid-template-columns:minmax(0,1fr) 300px; }
  }

  @media(max-width:980px){
    .rk-layout{ grid-template-columns:1fr; }
    .rk-side{ position:static; order:-1; }
    .rk-actionsRow .nb-btn,.rk-actionsRow .nb-btn-primary{ width:100%; justify-content:center; }
    .rk-acts{ justify-content:flex-start; }
    .rk-head{ margin-bottom:8px; }
    .rk-shell{ padding:10px; }
    .rk-table thead th:nth-child(2),
    .rk-table tbody td:nth-child(2){
      display:none;
    }
    .rk-table thead th:nth-child(1){ width:56% !important; }
    .rk-table thead th:nth-child(3){ width:20% !important; }
    .rk-table thead th:nth-child(4){ width:24% !important; text-align:left !important; }
    .rk-pager{ flex-direction:column; }
  }
</style>

@if(!$canManage)
  <div class="nb-card rk-wrap" style="padding:16px;">
    <div style="font-weight:900;">Akses ditolak</div>
    <div class="nb-muted-2" style="margin-top:6px;">Hanya admin/staff yang dapat mengelola rak.</div>
  </div>
@else

<div class="rk-wrap">
  <div class="nb-card rk-shell">

    <div class="rk-head">
      <div>
        <h1 class="title">Rak</h1>
        <div class="nb-muted-2 sub">Kelola rak penyimpanan per cabang.</div>
      </div>

      <a href="{{ route('rak.create') }}" class="nb-btn nb-btn-primary" style="border-radius:14px;">
        Tambah Rak
      </a>
    </div>

    <div class="rk-layout">

      {{-- LEFT --}}
      <div class="rk-section acc-slate">
        <div class="rk-section-head">
          <div class="h">Daftar Rak</div>
          <p class="nb-muted-2 hint">{{ $totalTxt }}</p>
        </div>

        @if($shelves && $shelves->count())
          <div class="rk-tableWrap">
            <table class="rk-table">
              <thead>
                <tr>
                  <th style="width:44%;">Rak</th>
                  <th style="width:28%;">Cabang</th>
                  <th style="width:12%;">Status</th>
                  <th style="width:16%; text-align:right;">Aksi</th>
                </tr>
              </thead>
              <tbody>
                @foreach($shelves as $s)
                  @php $active = (int)($s->is_active ?? 0) === 1; @endphp
                  <tr>
                    <td>
                      <div class="rk-name">{{ $s->name }}</div>
                      <div class="rk-meta">
                        {{ !empty($s->code) ? 'Kode: '.$s->code : 'Kode: -' }}
                        
                      </div>
                    </td>

                    <td>
                      <div class="rk-name">{{ $s->branch_name ?? '—' }}</div>
                      <div class="rk-meta">
                        {{ !empty($s->location) ? $s->location : 'Lokasi belum diisi' }}
                      </div>
                    </td>

                    <td>
                      <span class="rk-badge {{ $active ? 'on' : 'off' }}">
                        <span class="rk-dot"></span>
                        {{ $active ? 'Aktif' : 'Nonaktif' }}
                      </span>
                    </td>

                    <td style="text-align:right;">
                      <div class="rk-acts">
                        {{-- EDIT --}}
                        <a href="{{ route('rak.edit', $s->id) }}"
                           class="rk-iconBtn"
                           title="Edit">
                          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
                          </svg>
                        </a>

                        {{-- TOGGLE --}}
                        <form method="POST" action="{{ route('rak.toggle', $s->id) }}" style="display:inline;">
                          @csrf
                          <button type="submit"
                                  class="rk-iconBtn rk-iconBtn--toggle"
                                  title="{{ $active ? 'Nonaktifkan' : 'Aktifkan' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/>
                              <circle cx="12" cy="12" r="3"/>
                            </svg>
                          </button>
                        </form>

                        {{-- DELETE --}}
                        <form method="POST"
                              action="{{ route('rak.destroy', $s->id) }}"
                              style="display:inline;"
                              onsubmit="return confirm('Hapus rak ini? Jika masih dipakai eksemplar, penghapusan akan ditolak.');">
                          @csrf
                          @method('DELETE')
                          <button type="submit"
                                  class="rk-iconBtn rk-iconBtn--danger"
                                  title="Hapus">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M3 6h18"/>
                              <path d="M8 6V4h8v2"/>
                              <path d="M19 6l-1 14H6L5 6"/>
                              <path d="M10 11v6"/>
                              <path d="M14 11v6"/>
                            </svg>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="rk-pagination">
            {{ $shelves->onEachSide(1)->links('vendor.pagination.rak-compact') }}
          </div>
        @else
          <div class="rk-empty">
            Belum ada rak.<br>
            <span class="nb-muted-2">Klik</span> <b>Tambah Rak</b> <span class="nb-muted-2">untuk mulai.</span>
          </div>
        @endif
      </div>

      {{-- RIGHT --}}
      <div class="rk-side">
        <div class="rk-section acc-blue">
          <div class="rk-section-head">
            <div class="h">Filter</div>
            <p class="nb-muted-2 hint">Cari cepat</p>
          </div>

          <form method="GET" action="{{ route('rak.index') }}">
            <div class="rk-field">
              <label>Cari</label>
              <input class="nb-field" name="q" value="{{ $q }}" placeholder="nama rak / kode / lokasi…">
            </div>

            <div class="rk-field">
              <label>Cabang</label>
              <select class="nb-field" name="branch_id">
                <option value="">Semua</option>
                @foreach($branches as $b)
                  <option value="{{ $b->id }}" @selected((string)$b->id === (string)$branchId)>
                    {{ $b->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="rk-field">
              <label>Status</label>
              <select class="nb-field" name="status">
                <option value="">Semua</option>
                <option value="1" @selected($status === '1')>Aktif</option>
                <option value="0" @selected($status === '0')>Nonaktif</option>
              </select>
              <div class="nb-muted-2 rk-help">Rak nonaktif tidak disarankan dipakai untuk eksemplar baru.</div>
            </div>

            <div class="rk-actionsRow">
              <button type="submit" class="nb-btn nb-btn-primary">Terapkan</button>
              <a class="nb-btn" href="{{ route('rak.index') }}">Reset</a>
            </div>
          </form>
        </div>

        <div class="rk-section acc-green" style="margin-top:12px;">
          <div class="rk-section-head">
            <div class="h">Automasi DDC</div>
            <p class="nb-muted-2 hint">Tanpa artisan</p>
          </div>

          <div class="nb-muted-2" style="line-height:1.55; font-size:12.5px; margin-bottom:10px;">
            • Generate rak DDC detail <b>000-990</b> per cabang aktif.<br>
            • Mapping item berdasarkan <b>DDC bibliografi</b> ke rak DDC cabang item.
          </div>

          <div class="rk-actionsRow">
            <form method="POST" action="{{ route('rak.generate_ddc') }}" style="display:inline;">
              @csrf
              <button type="submit"
                      class="nb-btn nb-btn-primary"
                      onclick="return confirm('Generate rak DDC detail untuk semua cabang aktif di institusi ini?');">
                Generate Rak DDC
              </button>
            </form>

            <form method="POST" action="{{ route('rak.map_items_ddc') }}" style="display:inline;">
              @csrf
              <button type="submit"
                      class="nb-btn"
                      onclick="return confirm('Jalankan mapping item ke rak DDC sekarang?');">
                Mapping Item by DDC
              </button>
            </form>
          </div>
        </div>

        <div class="rk-section acc-slate" style="margin-top:12px;">
          <div class="rk-section-head">
            <div class="h">Catatan</div>
            <p class="nb-muted-2 hint">Flow</p>
          </div>
          <div class="nb-muted-2" style="line-height:1.55; font-size:12.5px;">
            • Buat <b>Cabang</b> dulu di menu Master → Cabang.<br>
            • Setelah itu buat <b>Rak</b> sesuai cabang.<br>
            • Rak dipakai untuk dropdown lokasi eksemplar.
          </div>
        </div>
      </div>

    </div>

  </div>
</div>

@endif
@endsection

