@extends('layouts.notobuku')

@section('title', 'Ubah Eksemplar • NOTOBUKU')

@section('content')
@php
  $biblio   = $biblio ?? null;
  $item     = $item ?? null;

  $branches = $branches ?? collect();
  $shelves  = $shelves ?? collect();

  $oldBranch = (string) old('branch_id', (string)($item->branch_id ?? ''));
  $oldShelf  = (string) old('shelf_id',  (string)($item->shelf_id ?? ''));

  $statusLabels = [
    'available'   => 'Tersedia',
    'borrowed'    => 'Dipinjam',
    'reserved'    => 'Direservasi',
    'maintenance' => 'Perawatan',
    'damaged'     => 'Rusak',
    'lost'        => 'Hilang',
  ];

  $statusDefault = (string) old('status', (string)($item->status ?? 'available'));
  $sumStatus = $statusLabels[$statusDefault] ?? ($item->status ?? '-');
@endphp

<style>
/* =========================================================
   NOTOBUKU • EKSEMPLAR • EDIT (IDENTIK DENGAN CREATE)
   - Tanpa Pengaturan Tambahan
   - Dependent Cabang -> Rak
   - Font ringan, button tanpa hover
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

/* Accent */
.ex-section.acc-blue  { background: rgba(30,136,229,.06); border-color: rgba(30,136,229,.14); }
.ex-section.acc-green { background: rgba(39,174,96,.06);  border-color: rgba(39,174,96,.14); }
.ex-section.acc-slate { background: rgba(15,23,42,.035);  border-color: rgba(15,23,42,.10); }

html.dark .ex-section.acc-blue  { background: rgba(30,136,229,.12); border-color: rgba(30,136,229,.18); }
html.dark .ex-section.acc-green { background: rgba(39,174,96,.12);  border-color: rgba(39,174,96,.18); }
html.dark .ex-section.acc-slate { background: rgba(148,163,184,.08); border-color: rgba(148,163,184,.14); }

.ex-section-head{
  display:flex; align-items:flex-start; justify-content:space-between;
  gap:10px; padding-bottom:10px; margin-bottom:12px;
  border-bottom:1px solid var(--nb-border);
}
.ex-section-head .h{ font-weight:600; font-size:13.5px; }
.ex-section-head .hint{ font-size:12.5px; margin:0; font-weight:400; }

.ex-grid-2{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
.ex-grid-3{ display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }

.ex-field label{
  display:block;
  font-weight:500;
  font-size:12.5px;
  margin-bottom:6px;
}
.ex-field .nb-field{
  width:100%!important;
  font-size:12.8px;
  padding:9px 11px;
  border-radius:14px;
}

.ex-help{ margin-top:5px; font-size:12.3px; line-height:1.45; font-weight:400; }
.ex-error{ margin-top:5px; font-size:12.3px; color:#dc2626; font-weight:400; }

.ex-actions{
  display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;
  margin-top:14px; padding-top:14px;
  border-top:1px solid var(--nb-border);
}

.ex-kpi{
  display:flex; flex-direction:column; gap:8px;
}
.ex-kpi .row{
  display:flex; justify-content:space-between; gap:10px;
  padding:10px 12px;
  border:1px solid var(--nb-border);
  border-radius:14px;
  background:rgba(31,58,95,.03);
  font-size:12.5px;
  font-weight:400;
}
html.dark .ex-kpi .row{ background: rgba(255,255,255,.04); }
.ex-kpi .row .v{ font-weight:600; font-size:13px; }

/* Button tanpa hover */
.nb-btn:hover,
.nb-btn:focus,
.nb-btn:active,
.nb-btn-primary:hover,
.nb-btn-primary:focus,
.nb-btn-primary:active,
.nb-btn.nb-btn-primary:hover,
.nb-btn.nb-btn-primary:focus,
.nb-btn.nb-btn-primary:active{
  transform:none !important;
  box-shadow:none !important;
}

@media(max-width:980px){
  .ex-layout{ grid-template-columns:1fr; }
  .ex-side{ position:static; }
  .ex-grid-2,.ex-grid-3{ grid-template-columns:1fr; }
  .ex-actions .nb-btn{ width:100%; justify-content:center; }
}
</style>

<div class="ex-wrap">
  <div class="nb-card ex-shell">

    <div class="ex-head">
      <div>
        <h1 class="title">Ubah Eksemplar</h1>
        <div class="nb-muted-2 sub">
          Bibliografi: <b>{{ $biblio->display_title ?? $biblio->title ?? '-' }}</b><br>
          Eksemplar: <b>{{ $item->barcode ?? '-' }}</b>
        </div>
      </div>
      <a href="{{ route('eksemplar.index', $biblio->id) }}" class="nb-btn">Kembali</a>
    </div>

    <form method="POST" action="{{ route('eksemplar.update', [$biblio->id, $item->id]) }}">
      @csrf
      @method('PUT')

      <div class="ex-layout">

        {{-- LEFT --}}
        <div>

          {{-- Identitas Eksemplar (identik dengan Create) --}}
          <div class="ex-section acc-blue">
            <div class="ex-section-head">
              <div class="h">Identitas Eksemplar</div>
              <p class="nb-muted-2 hint">Barcode & nomor induk</p>
            </div>

            <div class="ex-grid-2">
              <div class="ex-field">
                <label>Barcode</label>
                <input class="nb-field" name="barcode"
                       value="{{ old('barcode', (string)($item->barcode ?? '')) }}"
                       placeholder="Kosongkan untuk otomatis">
                @error('barcode') <div class="ex-error">{{ $message }}</div> @enderror
              </div>

              <div class="ex-field">
                <label>Accession Number</label>
                <input class="nb-field" name="accession_number"
                       value="{{ old('accession_number', (string)($item->accession_number ?? '')) }}"
                       placeholder="Kosongkan untuk otomatis">
                @error('accession_number') <div class="ex-error">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="ex-grid-2" style="margin-top:12px;">
              <div class="ex-field">
                <label>Kode Inventaris</label>
                <input class="nb-field" name="inventory_code"
                       value="{{ old('inventory_code', (string)($item->inventory_code ?? '')) }}"
                       placeholder="Opsional">
                @error('inventory_code') <div class="ex-error">{{ $message }}</div> @enderror
              </div>

              <div class="ex-field">
                <label>Nomor Inventaris</label>
                <input class="nb-field" name="inventory_number"
                       value="{{ old('inventory_number', (string)($item->inventory_number ?? '')) }}"
                       placeholder="Opsional">
                @error('inventory_number') <div class="ex-error">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Status & Kondisi (identik dengan Create) --}}
          <div class="ex-section acc-green">
            <div class="ex-section-head">
              <div class="h">Status & Kondisi</div>
              <p class="nb-muted-2 hint">Sirkulasi & kondisi fisik</p>
            </div>

            <div class="ex-grid-3">
              <div class="ex-field">
                <label>Status</label>
                <select class="nb-field" name="status" id="statusSelect">
                  @foreach($statusLabels as $k=>$v)
                    <option value="{{ $k }}" {{ old('status', (string)($item->status ?? 'available'))===$k?'selected':'' }}>
                      {{ $v }}
                    </option>
                  @endforeach
                </select>
                @error('status') <div class="ex-error">{{ $message }}</div> @enderror
              </div>

              <div class="ex-field">
                <label>Kondisi Fisik</label>
                <input class="nb-field" name="condition"
                       value="{{ old('condition', (string)($item->condition ?? '')) }}"
                       placeholder="contoh: Baik">
                @error('condition') <div class="ex-error">{{ $message }}</div> @enderror
              </div>

              <div class="ex-field">
                <label>Catatan</label>
                <input class="nb-field" name="notes"
                       value="{{ old('notes', (string)($item->notes ?? '')) }}"
                       placeholder="Opsional">
                @error('notes') <div class="ex-error">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="ex-grid-3" style="margin-top:12px;">
              <div class="ex-field">
                <label>Tanggal Perolehan</label>
                @php
                  $acqOld = old('acquired_at', (string)($item->acquired_at ?? ''));
                  // jika tersimpan datetime, date input butuh Y-m-d
                  $acqVal = '';
                  if ($acqOld !== '') {
                    try {
                      $acqVal = \Illuminate\Support\Carbon::parse($acqOld)->format('Y-m-d');
                    } catch (\Throwable $e) {
                      $acqVal = '';
                    }
                  }
                @endphp
                <input class="nb-field" type="date" name="acquired_at" value="{{ $acqVal }}">
                @error('acquired_at') <div class="ex-error">{{ $message }}</div> @enderror
              </div>

              <div class="ex-field">
                <label>Harga (Rp)</label>
                <input class="nb-field" type="number" step="0.01" min="0" name="price"
                       value="{{ old('price', (string)($item->price ?? '')) }}"
                       placeholder="Opsional">
                @error('price') <div class="ex-error">{{ $message }}</div> @enderror
              </div>

              <div class="ex-field">
                <label>Sumber</label>
                <input class="nb-field" name="source"
                       value="{{ old('source', (string)($item->source ?? '')) }}"
                       placeholder="Opsional">
                @error('source') <div class="ex-error">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Lokasi Fisik (identik dengan Create + dependent sama) --}}
          <div class="ex-section acc-slate">
            <div class="ex-section-head">
              <div class="h">Lokasi Fisik</div>
              <p class="nb-muted-2 hint">Cabang & rak penyimpanan</p>
            </div>

            <div class="ex-grid-3">
              <div class="ex-field">
                <label>Cabang</label>
                <select class="nb-field" name="branch_id" id="branchSelect">
                  <option value="">-</option>
                  @foreach($branches as $br)
                    <option value="{{ $br->id }}" {{ $oldBranch===(string)$br->id?'selected':'' }}>
                      {{ $br->name }}
                    </option>
                  @endforeach
                </select>
                @error('branch_id') <div class="ex-error">{{ $message }}</div> @enderror
              </div>

              <div class="ex-field">
                <label>Rak</label>
                <select class="nb-field" name="shelf_id" id="shelfSelect" data-selected="{{ $oldShelf }}">
                  <option value="" id="shelfPlaceholder">Pilih cabang dulu…</option>
                  @foreach($shelves as $sh)
                    <option value="{{ $sh->id }}" data-branch="{{ $sh->branch_id }}">
                      {{ $sh->name }}
                    </option>
                  @endforeach
                </select>
                <div class="ex-help" id="shelfHint"></div>
                @error('shelf_id') <div class="ex-error">{{ $message }}</div> @enderror
              </div>

              <div class="ex-field">
                <label>Catatan Lokasi</label>
                <input class="nb-field" name="location_note"
                       value="{{ old('location_note', (string)($item->location_note ?? '')) }}"
                       placeholder="Opsional">
                @error('location_note') <div class="ex-error">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Actions (identik dengan Create, hanya teks tombol) --}}
          <div class="ex-actions">
            <button class="nb-btn nb-btn-primary" type="submit">Simpan Perubahan</button>
            <a class="nb-btn" href="{{ route('eksemplar.index', $biblio->id) }}">Batal</a>
          </div>

        </div>

        {{-- RIGHT --}}
        <div class="ex-side">

          {{-- Ringkasan (identik konsep dengan Create, pakai data item) --}}
          <div class="ex-section acc-slate">
            <div class="ex-section-head">
              <div class="h">Ringkasan</div>
              <p class="nb-muted-2 hint">Data saat ini</p>
            </div>

            <div class="ex-kpi">
              <div class="row"><span>ID Eksemplar</span><span class="v">{{ $item->id ?? '-' }}</span></div>
              <div class="row"><span>Barcode</span><span class="v">{{ $item->barcode ?? '-' }}</span></div>
              <div class="row"><span>Status</span><span class="v">{{ $sumStatus }}</span></div>
            </div>
          </div>

          <div class="ex-section acc-blue" style="margin-top:12px;">
            <div class="ex-section-head">
              <div class="h">Tips</div>
              <p class="nb-muted-2 hint">Lokasi & data</p>
            </div>

            <div class="nb-muted-2" style="font-size:12.5px; line-height:1.55; font-weight:400;">
              • Pilih <b>cabang</b> dulu agar daftar <b>rak</b> tersaring.<br>
              • Jika rak tidak muncul, pilih ulang cabang untuk menyaring daftar rak.<br>
              • Ubah status sesuai kondisi fisik & sirkulasi buku.
            </div>
          </div>

        </div>

      </div>
    </form>

  </div>
</div>

<script>
(function(){
  const branchEl = document.getElementById('branchSelect');
  const shelfEl  = document.getElementById('shelfSelect');
  const hintEl   = document.getElementById('shelfHint');

  if(!branchEl || !shelfEl) return;

  // Kumpulkan opsi rak dari HTML (selain placeholder)
  const originalOptions = Array.from(shelfEl.querySelectorAll('option'))
    .filter(o => o.value !== '' && o.id !== 'shelfPlaceholder')
    .map(o => ({ value:o.value, text:o.textContent, branch:(o.dataset.branch||'') }));

  function setPlaceholder(text){
    let ph = shelfEl.querySelector('option[value=""]');
    if(!ph){
      ph = document.createElement('option');
      ph.value = '';
      shelfEl.prepend(ph);
    }
    ph.textContent = text;
  }

  function setShelfEnabled(enabled){
    shelfEl.disabled = !enabled;
    shelfEl.style.opacity = enabled ? '1' : '.65';
  }

  function render(branchId){
    const selectedWanted = (shelfEl.dataset.selected || '').toString();

    shelfEl.innerHTML = '';
    setPlaceholder(!branchId ? 'Pilih cabang dulu…' : '- Pilih rak -');

    if (!branchId) {
      setShelfEnabled(false);
      shelfEl.value = '';
      if (hintEl) hintEl.textContent = 'Rak akan muncul setelah memilih cabang.';
      return;
    }

    for (const o of originalOptions) {
      if (o.branch === branchId) {
        const opt = document.createElement('option');
        opt.value = o.value;
        opt.textContent = o.text;
        shelfEl.appendChild(opt);
      }
    }

    setShelfEnabled(true);
    if (hintEl) hintEl.textContent = 'Rak tersaring sesuai cabang.';

    const stillExists = Array.from(shelfEl.options).some(x => x.value === selectedWanted);
    shelfEl.value = stillExists ? selectedWanted : '';
  }

  // Init
  render(branchEl.value || '');

  branchEl.addEventListener('change', function(){
    shelfEl.dataset.selected = '';
    render(branchEl.value || '');
  });

  shelfEl.addEventListener('change', function(){
    shelfEl.dataset.selected = shelfEl.value || '';
  });
})();
</script>
@endsection
