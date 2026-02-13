{{-- resources/views/cabang/index.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Master Cabang • NOTOBUKU')

@section('content')
@php
  /** @var \Illuminate\Pagination\LengthAwarePaginator|\Illuminate\Pagination\Paginator $branches */
  $branches = $branches ?? null;

  $status = (string)($status ?? '');
  $total = $branches ? (int)($branches->total() ?? 0) : 0;

  // UI: label status filter
  $statusLabel = match($status){
    '1' => 'Aktif',
    '0' => 'Nonaktif',
    default => 'Semua',
  };

  // UI: fallback aman kalau variabel belum ada
  $countPage = $branches ? (int)$branches->count() : 0;
@endphp

<style>
  /* =========================================================
     NOTOBUKU • Master • Cabang (Index)
     - Konsisten dengan gaya kartu Katalog
     - Fokus keterbacaan + aksi jelas
     ========================================================= */

  .cb-wrap{ max-width:1120px; margin:0 auto; }
  .cb-shell{ padding:16px; }

  .cb-head{
    display:flex; align-items:flex-start; justify-content:space-between;
    gap:12px; flex-wrap:wrap; margin-bottom:12px;
  }
  .cb-title{ margin:0; font-weight:900; letter-spacing:.12px; font-size:15px; }
  .cb-sub{ margin-top:6px; font-size:12.8px; line-height:1.45; }

  .cb-actions{ display:flex; gap:10px; flex-wrap:wrap; }

  .cb-panel{
    padding:14px;
    border:1px solid var(--nb-border);
    border-radius:16px;
    background: var(--nb-surface);
  }

  .cb-panel.acc-slate { background: rgba(15,23,42,.035); border-color: rgba(15,23,42,.10); }
  html.dark .cb-panel.acc-slate { background: rgba(148,163,184,.08); border-color: rgba(148,163,184,.14); }

  .cb-panel-head{
    display:flex; align-items:flex-start; justify-content:space-between; gap:10px;
    padding-bottom:10px; margin-bottom:12px;
    border-bottom:1px solid var(--nb-border);
  }
  .cb-panel-head .h{ font-weight:900; letter-spacing:.1px; font-size:13.5px; }
  .cb-panel-head .hint{ font-size:12.5px; margin:0; }

  .cb-filter{
    display:flex; gap:10px; flex-wrap:wrap; align-items:end;
  }
  .cb-field label{
    display:block;
    font-weight:800;
    font-size:12.5px;
    margin-bottom:6px;
  }
  .cb-field .nb-field{
    width:100%!important;
    box-sizing:border-box;
    font-size:12.8px;
    line-height:1.4;
    padding:9px 11px;
    border-radius:14px;
  }

  .cb-kpi{
    display:flex; gap:10px; flex-wrap:wrap;
    margin-top:12px;
  }
  .cb-kpi .box{
    flex:1;
    min-width:220px;
    padding:10px 12px;
    border:1px solid var(--nb-border);
    border-radius:14px;
    background: rgba(31,58,95,.03);
    display:flex; align-items:center; justify-content:space-between; gap:10px;
    font-size:12.5px;
  }
  html.dark .cb-kpi .box{ background: rgba(255,255,255,.04); }
  .cb-kpi .v{ font-weight:900; font-size:13px; }

  .cb-tablewrap{ overflow:auto; }
  .cb-table{
    width:100%;
    border-collapse:separate;
    border-spacing:0 10px;
  }
  .cb-table thead th{
    text-align:left;
    font-size:12.5px;
    color: var(--nb-muted);
    font-weight:800;
    padding: 0 12px;
  }

  .cb-row{
    border:1px solid var(--nb-border);
    background: var(--nb-surface);
  }
  .cb-td{
    padding:12px;
    vertical-align:top;
  }

  .cb-name{
    font-weight:900;
    letter-spacing:.08px;
    line-height:1.25;
  }
  .cb-code{
    margin-top:6px;
    font-size:12.5px;
    color: var(--nb-muted);
  }

  .cb-meta{
    font-size:12.6px;
    line-height:1.5;
    color: var(--nb-muted);
  }

  .cb-actions-cell{
    display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap;
  }
  .cb-mini{ padding:8px 10px; border-radius:14px; }

  .cb-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid var(--nb-border);
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
  }
  .cb-dot{
    width:8px; height:8px; border-radius:999px;
    background: rgba(15,23,42,.28);
  }
  .cb-badge--on .cb-dot{ background: rgba(39,174,96,.85); }
  .cb-badge--off .cb-dot{ background: rgba(220,38,38,.85); }

  /* Hover tombol supaya teks kebaca (tanpa ubah global) */
  .cb-actions-cell .nb-btn:hover,
  .cb-actions-cell .nb-btn:focus{
    filter: brightness(.98) saturate(1.06);
  }

  .cb-empty{
    padding:14px;
    border:1px solid var(--nb-border);
    border-radius:16px;
    background: var(--nb-surface);
  }

  .cb-mobile-k{ display:none; font-size:12px; color: var(--nb-muted); font-weight:800; margin-bottom:4px; }

  @media(max-width:980px){
    .cb-actions .nb-btn, .cb-actions .nb-btn-primary{ width:100%; justify-content:center; }
    .cb-filter{ flex-direction:column; align-items:stretch; }
    .cb-kpi .box{ min-width:0; width:100%; }
  }

  @media(max-width:860px){
    .cb-table thead{ display:none; }
    .cb-table{ border-spacing:0 12px; }
    .cb-td{ display:block; }
    .cb-actions-cell{ justify-content:flex-start; }
    .cb-mobile-k{ display:block; }
  }
</style>

<div class="cb-wrap">
  <div class="nb-card cb-shell">

    <div class="cb-head">
      <div>
        <h1 class="cb-title">Master Cabang</h1>
        <div class="nb-muted-2 cb-sub">
          Data cabang digunakan untuk <b>lokasi eksemplar</b> (dropdown Cabang) dan penyaringan daftar eksemplar.
        </div>
      </div>

      <div class="cb-actions">
        <a class="nb-btn" href="{{ route('katalog.index') }}">Kembali</a>
        <a class="nb-btn nb-btn-primary" href="{{ route('cabang.create') }}">Tambah Cabang</a>
      </div>
    </div>

    <div class="cb-panel acc-slate">
      <div class="cb-panel-head">
        <div>
          <div class="h">Filter</div>
          <p class="nb-muted-2 hint">Tampilkan berdasarkan status cabang.</p>
        </div>
        <span class="nb-badge">{{ $statusLabel }}</span>
      </div>

      <form method="GET" action="{{ route('cabang.index') }}">
        <div class="cb-filter">
          <div class="cb-field" style="min-width:240px;">
            <label>Status</label>
            <select class="nb-field" name="status">
              <option value=""  {{ $status==='' ? 'selected' : '' }}>Semua</option>
              <option value="1" {{ $status==='1' ? 'selected' : '' }}>Aktif</option>
              <option value="0" {{ $status==='0' ? 'selected' : '' }}>Nonaktif</option>
            </select>
          </div>

          <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <button class="nb-btn nb-btn-primary" type="submit">Terapkan</button>
            <a class="nb-btn" href="{{ route('cabang.index') }}">Reset</a>
          </div>
        </div>
      </form>

      <div class="cb-kpi">
        <div class="box">
          <span>Total data</span>
          <span class="v">{{ $total }}</span>
        </div>
        <div class="box">
          <span>Menampilkan</span>
          <span class="v">{{ $countPage }}</span>
        </div>
        <div class="box">
          <span>Halaman</span>
          <span class="v">{{ $branches ? ($branches->currentPage() . ' / ' . $branches->lastPage()) : '1 / 1' }}</span>
        </div>
      </div>
    </div>

    <div style="height:12px;"></div>

    @if(!$branches || $branches->count() === 0)
      <div class="cb-empty">
        <div style="font-weight:900;">Belum ada cabang</div>
        <div class="nb-muted-2" style="margin-top:6px;">
          Tambahkan cabang agar dropdown Cabang di Eksemplar bisa digunakan.
        </div>
        <div style="height:12px;"></div>
        <a class="nb-btn nb-btn-primary" href="{{ route('cabang.create') }}">Tambah Cabang</a>
      </div>
    @else
      <div class="cb-tablewrap">
        <table class="cb-table">
          <thead>
            <tr>
              <th>Cabang</th>
              <th>Alamat</th>
              <th>Status</th>
              <th style="text-align:right;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @foreach($branches as $br)
              @php
                $isActive = (int)($br->is_active ?? 1) === 1;
                $badgeClass = $isActive ? 'cb-badge--on' : 'cb-badge--off';
              @endphp

              <tr class="cb-row">
                <td class="cb-td" style="border-top-left-radius:16px; border-bottom-left-radius:16px;">
                  <div class="cb-mobile-k">Cabang</div>
                  <div class="cb-name">{{ $br->name }}</div>
                  <div class="cb-code">
                    Kode: <b>{{ $br->code ?: '-' }}</b>
                    <span class="nb-muted-2" style="margin-left:8px;">• ID: {{ $br->id }}</span>
                  </div>

                  @if(!empty($br->notes))
                    <div class="cb-meta" style="margin-top:8px;">
                      <b class="nb-muted-2">Catatan:</b> {{ $br->notes }}
                    </div>
                  @endif
                </td>

                <td class="cb-td">
                  <div class="cb-mobile-k">Alamat</div>
                  <div class="cb-meta" style="color: var(--nb-text);">
                    {{ $br->address ?: '-' }}
                  </div>
                </td>

                <td class="cb-td">
                  <div class="cb-mobile-k">Status</div>
                  <span class="cb-badge {{ $badgeClass }}">
                    <span class="cb-dot"></span>
                    {{ $isActive ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </td>

                <td class="cb-td" style="border-top-right-radius:16px; border-bottom-right-radius:16px;">
                  <div class="cb-mobile-k">Aksi</div>
                  <div class="cb-actions-cell">

                    <form method="POST" action="{{ route('cabang.toggle', $br->id) }}">
                      @csrf
                      <button class="nb-btn cb-mini" type="submit"
                              onclick="return confirm('{{ $isActive ? 'Nonaktifkan cabang ini?' : 'Aktifkan cabang ini?' }}');">
                        {{ $isActive ? 'Nonaktifkan' : 'Aktifkan' }}
                      </button>
                    </form>

                    <a class="nb-btn cb-mini" href="{{ route('cabang.edit', $br->id) }}">Edit</a>

                    <form method="POST" action="{{ route('cabang.destroy', $br->id) }}"
                          onsubmit="return confirm('Hapus cabang ini? Jika cabang sedang digunakan eksemplar, sistem akan menolak.');">
                      @csrf
                      @method('DELETE')
                      <button class="nb-btn cb-mini" type="submit">Hapus</button>
                    </form>
                  </div>

                  <div class="nb-muted-2" style="margin-top:8px; font-size:12.2px; line-height:1.45;">
                    Tips: Disarankan <b>Nonaktifkan</b> bila cabang sudah tidak digunakan, agar histori tetap aman.
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div style="height:10px;"></div>

      <div style="overflow:auto;">
        {{ $branches->links() }}
      </div>
    @endif

  </div>
</div>
@endsection
