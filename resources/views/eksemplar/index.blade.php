@extends('layouts.notobuku')

@section('title', 'Kelola Eksemplar • NOTOBUKU')

@section('content')
@php
  $biblio   = $biblio ?? null;
  $items    = $items ?? null;

  $q        = (string)($q ?? request()->query('q',''));
  $branchId = (string)($branchId ?? request()->query('branch_id',''));
  $shelfId  = (string)($shelfId ?? request()->query('shelf_id',''));
  $status   = (string)($status ?? request()->query('status',''));

  $branches = $branches ?? collect();
  $shelves  = $shelves ?? collect();

  // ✅ OPTIONAL: kalau controller kamu nanti kirim semua rak (untuk dependent tanpa reload)
  // aman kalau tidak ada (fallback ke reload)
  $shelvesAll = $shelvesAll ?? null; // collection atau null

  $statusLabels = [
    'available'   => 'Tersedia',
    'borrowed'    => 'Dipinjam',
    'reserved'    => 'Direservasi',
    'maintenance' => 'Perawatan',
    'damaged'     => 'Rusak',
    'lost'        => 'Hilang',
  ];

  $statusBadge = function($st){
    return match($st){
      'available' => 'nb-badge-green',
      'borrowed','reserved' => 'nb-badge-blue',
      'lost','damaged' => 'nb-badge-red',
      default => 'nb-badge',
    };
  };

  $totalTxt = ($items && method_exists($items,'total')) ? $items->total().' item' : '—';

  // ✅ siapkan array untuk JS dependent (kalau shelvesAll tersedia)
  $shelvesJson = [];
  if($shelvesAll instanceof \Illuminate\Support\Collection){
    $shelvesJson = $shelvesAll->map(function($r){
      return [
        'id' => (int)$r->id,
        'name' => (string)$r->name,
        'branch_id' => (int)$r->branch_id,
      ];
    })->values()->all();
  }
@endphp

<style>
/* =========================================================
   NOTOBUKU • Eksemplar • Index (KONSISTEN DENGAN KATALOG/RAK)
   - Layout 2 kolom: list + panel filter (sticky)
   - Table modern (border + header lembut)
   - Aksi icon only
   - FONT LEBIH RINGAN (tidak terlalu tebal)
   - BUTTON TANPA HOVER
   ========================================================= */

.ex-wrap{ max-width:1120px; margin:0 auto; }
.ex-shell{ padding:16px; }

.ex-head{
  display:flex; align-items:flex-start; justify-content:space-between;
  gap:12px; flex-wrap:wrap; margin-bottom:12px;
}
.ex-head .title{ font-weight:600; letter-spacing:.12px; font-size:15px; margin:0; }
.ex-head .sub{ margin-top:6px; font-size:12.8px; font-weight:400; }

.ex-layout{
  display:grid;
  grid-template-columns:minmax(0,1fr) 360px;
  gap:14px;
  align-items:start;
}
.ex-side{ position:sticky; top:14px; }

.ex-section{
  padding:14px;
  border:1px solid var(--nb-border);
  border-radius:16px;
  background:var(--nb-surface);
}
.ex-section + .ex-section{ margin-top:12px; }

/* Aksen seperti Katalog Create / Rak */
.ex-section.acc-blue  { background: rgba(30,136,229,.06); border-color: rgba(30,136,229,.14); }
.ex-section.acc-green { background: rgba(39,174,96,.06);  border-color: rgba(39,174,96,.14); }
.ex-section.acc-slate { background: rgba(15,23,42,.035);  border-color: rgba(15,23,42,.10); }

html.dark .ex-section.acc-blue  { background: rgba(30,136,229,.12); border-color: rgba(30,136,229,.18); }
html.dark .ex-section.acc-green { background: rgba(39,174,96,.12);  border-color: rgba(39,174,96,.18); }
html.dark .ex-section.acc-slate { background: rgba(148,163,184,.08); border-color: rgba(148,163,184,.14); }

.ex-section-head{
  display:flex; align-items:flex-start; justify-content:space-between; gap:10px;
  padding-bottom:10px; margin-bottom:12px;
  border-bottom:1px solid var(--nb-border);
}
.ex-section-head .h{ font-weight:600; letter-spacing:.1px; font-size:13.5px; }
.ex-section-head .hint{ font-size:12.5px; margin:0; font-weight:400; }

.ex-field{ margin-bottom:12px; }
.ex-field label{
  display:block;
  font-weight:500;
  font-size:12.5px;
  margin-bottom:6px;
}
.ex-field .nb-field{
  width:100% !important;
  box-sizing:border-box;
  font-size:12.8px;
  line-height:1.4;
  padding:9px 11px;
  border-radius:14px;
}
.ex-help{ margin-top:6px; font-size:12.3px; line-height:1.45; font-weight:400; }

.ex-actionsRow{
  display:flex; gap:10px; flex-wrap:wrap;
  margin-top:10px;
}
.ex-actionsRow .nb-btn,
.ex-actionsRow .nb-btn-primary{ border-radius:14px; }

/* List/table look */
.ex-tableWrap{
  border:1px solid var(--nb-border);
  border-radius:16px;
  overflow:hidden;
  background: rgba(255,255,255,.65);
}
html.dark .ex-tableWrap{ background: rgba(255,255,255,.05); }

.ex-table{ width:100%; border-collapse:collapse; }
.ex-table thead th{
  text-align:left;
  font-weight:600;
  font-size:12.2px;
  padding:10px 12px;
  background: rgba(15,23,42,.03);
  border-bottom:1px solid var(--nb-border);
  white-space:nowrap;
}
html.dark .ex-table thead th{ background: rgba(255,255,255,.03); }

.ex-table tbody td{
  padding:12px 12px;
  border-bottom:1px solid var(--nb-border);
  vertical-align:top;
  font-weight:400;
}
.ex-table tbody tr:last-child td{ border-bottom:0; }

.ex-code{ font-weight:600; letter-spacing:.06px; font-size:13px; }
.ex-muted{ margin-top:4px; font-size:12.3px; color: rgba(11,37,69,.62); line-height:1.45; font-weight:400; }
html.dark .ex-muted{ color: rgba(226,232,240,.62); }

.ex-loc{
  display:inline-flex; align-items:center; gap:8px;
  font-size:12.3px; font-weight:500;
  color: rgba(11,37,69,.62);
}
html.dark .ex-loc{ color: rgba(226,232,240,.62); }
.ex-loc .dot{
  width:7px; height:7px; border-radius:999px;
  background: rgba(15,23,42,.28);
}
html.dark .ex-loc .dot{ background: rgba(148,163,184,.55); }

/* Aksi icon (TANPA HOVER) */
.ex-acts{
  display:flex;
  justify-content:flex-end;
  gap:8px;
  white-space:nowrap;
}
.ex-iconBtn{
  width:36px; height:36px;
  border-radius:12px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border:1px solid var(--nb-border);
  background: rgba(15,23,42,.04);
  cursor:pointer;
  transition:none;
  text-decoration:none;
}
html.dark .ex-iconBtn{ background: rgba(255,255,255,.04); }

.ex-iconBtn:hover{
  background: rgba(15,23,42,.04);
  border-color: var(--nb-border);
  transform:none;
}
.ex-iconBtn:active{ transform:none; }

.ex-iconBtn svg{
  width:18px; height:18px;
  stroke: rgba(11,37,69,.78);
}
html.dark .ex-iconBtn svg{ stroke: rgba(226,232,240,.82); }

.ex-badgeRow{
  display:flex; align-items:center; gap:10px; flex-wrap:wrap;
}

/* Empty */
.ex-empty{
  padding:14px;
  border:1px dashed rgba(15,23,42,.18);
  border-radius:16px;
  background: rgba(15,23,42,.02);
  font-size:12.8px;
  line-height:1.55;
  font-weight:400;
}
html.dark .ex-empty{
  border-color: rgba(148,163,184,.22);
  background: rgba(255,255,255,.03);
}

/* BUTTON TANPA HOVER (override layout) */
.nb-btn:hover,
.nb-btn:focus,
.nb-btn:active,
.nb-btn.nb-btn-primary:hover,
.nb-btn.nb-btn-primary:focus,
.nb-btn.nb-btn-primary:active,
.nb-btn-primary:hover,
.nb-btn-primary:focus,
.nb-btn-primary:active{
  transform:none !important;
  box-shadow:none !important;
}

/* Responsive */
@media(max-width:980px){
  .ex-layout{ grid-template-columns:1fr; }
  .ex-side{ position:static; }
  .ex-actionsRow .nb-btn,.ex-actionsRow .nb-btn-primary{ width:100%; justify-content:center; }
  .ex-acts{ justify-content:flex-start; }
}
</style>

<div class="ex-wrap">
  <div class="nb-card ex-shell">

    <div class="ex-head">
      <div>
        <h1 class="title">Kelola Eksemplar</h1>
        <div class="nb-muted-2 sub">
          <b>{{ $biblio->display_title ?? $biblio->title ?? '-' }}</b>
          · {{ (int)($biblio->available_items_count ?? 0) }} tersedia
          · {{ (int)($biblio->items_count ?? 0) }} total
        </div>
      </div>

      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="nb-btn" href="{{ route('katalog.show', $biblio->id) }}" style="border-radius:14px;">Kembali</a>
        <a class="nb-btn nb-btn-primary" href="{{ route('eksemplar.create', $biblio->id) }}" style="border-radius:14px;">Tambah Eksemplar</a>
      </div>
    </div>

    <div class="ex-layout">

      {{-- MAIN: LIST --}}
      <div>
        <div class="ex-section acc-slate">
          <div class="ex-section-head">
            <div>
              <div class="h">Daftar Eksemplar</div>
              <p class="nb-muted-2 hint" style="margin-top:6px;">
                Gunakan filter di samping untuk mempersempit hasil.
              </p>
            </div>
            <div class="ex-badgeRow">
              <span class="nb-badge">{{ $totalTxt }}</span>
            </div>
          </div>

          @if(!$items || $items->count() === 0)
            <div class="ex-empty">
              Tidak ada data eksemplar untuk kriteria yang dipilih.
            </div>
          @else
            <div class="ex-tableWrap">
              <table class="ex-table">
                <thead>
                  <tr>
                    <th style="width:180px;">Barcode</th>
                    <th style="width:180px;">Accession</th>
                    <th style="width:160px;">Status</th>
                    <th>Lokasi</th>
                    <th style="width:92px; text-align:right;">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                @foreach($items as $it)
                  @php
                    // Lokasi dari join (controller index yang di-upgrade)
                    $branchName = trim((string)($it->branch_name ?? ''));
                    $shelfName  = trim((string)($it->shelf_name ?? ''));
                    $loc = trim($branchName.' • '.$shelfName,' •');
                  @endphp
                  <tr>
                    <td>
                      <div class="ex-code">{{ $it->barcode }}</div>
                      <div class="ex-muted">ID: {{ $it->id }}</div>
                    </td>

                    <td>
                      <div style="font-weight:600; font-size:13px;">
                        {{ $it->accession_number ?? '-' }}
                      </div>
                      @if(!empty($it->inventory_code))
                        <div class="ex-muted">Inventaris: {{ $it->inventory_code }}</div>
                      @else
                        <div class="ex-muted">Inventaris: -</div>
                      @endif
                    </td>

                    <td>
                      <span class="nb-badge {{ $statusBadge($it->status) }}">
                        {{ $statusLabels[$it->status] ?? $it->status }}
                      </span>
                    </td>

                    <td>
                      @if($loc !== '')
                        <span class="ex-loc"><span class="dot"></span>{{ $loc }}</span>
                      @else
                        <span class="ex-loc"><span class="dot"></span>-</span>
                      @endif
                    </td>

                    <td style="text-align:right;">
                      <div class="ex-acts">
                        <a class="ex-iconBtn"
                           href="{{ route('eksemplar.edit', [$biblio->id, $it->id]) }}"
                           title="Edit eksemplar"
                           aria-label="Edit eksemplar {{ $it->barcode }}">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l9.932-9.931z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125L16.875 4.5"/>
                          </svg>
                        </a>
                      </div>
                    </td>
                  </tr>
                @endforeach
                </tbody>
              </table>
            </div>

            <div style="margin-top:12px;">
              {{ $items->links() }}
            </div>
          @endif
        </div>
      </div>

      {{-- SIDE: FILTER --}}
      <div class="ex-side">
        <div class="ex-section acc-blue">
          <div class="ex-section-head">
            <div>
              <div class="h">Filter & Pencarian</div>
              <p class="nb-muted-2 hint" style="margin-top:6px;">
                Kombinasikan pencarian dengan cabang, rak, dan status.
              </p>
            </div>
          </div>

          <form method="GET" id="filterForm">
            <div class="ex-field">
              <label>Cari</label>
              <input class="nb-field" name="q" value="{{ $q }}" placeholder="Barcode / Accession / Inventaris">
            </div>

            <div class="ex-field">
              <label>Cabang</label>
              <select class="nb-field" name="branch_id" id="branchSelect">
                <option value="">Semua cabang</option>
                @foreach($branches as $br)
                  <option value="{{ $br->id }}" {{ $branchId===(string)$br->id?'selected':'' }}>{{ $br->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="ex-field">
              <label>Rak</label>
              <select class="nb-field" name="shelf_id" id="shelfSelect" {{ $branchId==='' ? 'disabled' : '' }}>
                <option value="">
                  {{ $branchId==='' ? 'Pilih cabang dulu' : 'Semua rak' }}
                </option>
                @foreach($shelves as $sh)
                  <option value="{{ $sh->id }}" data-branch="{{ (int)($sh->branch_id ?? 0) }}" {{ $shelfId===(string)$sh->id ? 'selected' : '' }}>
                    {{ $sh->name }}
                  </option>
                @endforeach
              </select>
              @if($branchId==='')
                <div class="ex-help nb-muted-2">Rak akan muncul setelah cabang dipilih.</div>
              @endif
            </div>

            <div class="ex-field">
              <label>Status</label>
              <select class="nb-field" name="status">
                <option value="">Semua status</option>
                @foreach($statusLabels as $k=>$v)
                  <option value="{{ $k }}" {{ $status===$k?'selected':'' }}>{{ $v }}</option>
                @endforeach
              </select>
            </div>

            <div class="ex-actionsRow">
              <button class="nb-btn nb-btn-primary" style="flex:1;">Terapkan</button>
              <a class="nb-btn" href="{{ route('eksemplar.index', $biblio->id) }}" style="flex:1; text-align:center;">Reset</a>
            </div>
          </form>
        </div>

        <div class="ex-section acc-green">
          <div class="ex-section-head">
            <div>
              <div class="h">Info</div>
              <p class="nb-muted-2 hint" style="margin-top:6px;">
                Lokasi ditampilkan sebagai <b>Cabang • Rak</b>.
              </p>
            </div>
          </div>

          <div class="ex-empty" style="border-style:solid;">
            Tips: Untuk mempercepat pencarian, masukkan barcode atau accession lengkap.
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
(function(){
  const branchSelect = document.getElementById('branchSelect');
  const shelfSelect  = document.getElementById('shelfSelect');
  const filterForm   = document.getElementById('filterForm');

  if(!branchSelect || !shelfSelect || !filterForm) return;

  // ✅ Mode 1 (recommended): shelvesAll tersedia => dependent tanpa reload
  const SHELVES_ALL = @json($shelvesJson);

  function buildShelfOptions(branchId, keepSelected){
    // keepSelected = true => coba pertahankan selected jika masih valid
    const current = shelfSelect.value || '';
    const selected = keepSelected ? current : '';

    // reset
    shelfSelect.innerHTML = '';

    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = branchId ? 'Semua rak' : 'Pilih cabang dulu';
    shelfSelect.appendChild(opt0);

    if(!branchId){
      shelfSelect.disabled = true;
      shelfSelect.value = '';
      return;
    }

    shelfSelect.disabled = false;

    // filter data
    const bid = parseInt(branchId, 10);
    const list = (Array.isArray(SHELVES_ALL) ? SHELVES_ALL : []).filter(x => parseInt(x.branch_id,10) === bid);

    for(const sh of list){
      const opt = document.createElement('option');
      opt.value = String(sh.id);
      opt.textContent = sh.name;
      shelfSelect.appendChild(opt);
    }

    // restore selected if possible
    if(selected){
      const exists = Array.from(shelfSelect.options).some(o => o.value === selected);
      shelfSelect.value = exists ? selected : '';
    } else {
      shelfSelect.value = '';
    }
  }

  const hasAll = Array.isArray(SHELVES_ALL) && SHELVES_ALL.length > 0;

  if(hasAll){
    // build on load
    buildShelfOptions(branchSelect.value || '', true);

    branchSelect.addEventListener('change', function(){
      // update shelves without reload, reset shelf_id kalau tidak cocok
      buildShelfOptions(branchSelect.value || '', false);
    });

  } else {
    // ✅ Mode 2 fallback: server-side dependent (seperti file kamu sebelumnya)
    branchSelect.addEventListener('change', function(){
      shelfSelect.value = '';
      filterForm.submit();
    });
  }

})();
</script>
@endsection
