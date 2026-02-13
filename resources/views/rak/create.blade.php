{{-- resources/views/rak/create.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Tambah Rak • NOTOBUKU')

@section('content')
@php
  $role = auth()->user()->role ?? 'member';
  $canManage = in_array($role, ['super_admin','admin','staff'], true);

  // branches wajib dari controller (untuk pilih cabang)
  $branches = $branches ?? collect();

  // default: kalau cuma 1 cabang, auto select
  $defaultBranchId = old('branch_id');
  if(($defaultBranchId === null || $defaultBranchId === '') && $branches->count() === 1){
    $defaultBranchId = (string)($branches->first()->id ?? '');
  }
@endphp

<style>
/* =========================================================
   NOTOBUKU • Rak • Create (match Katalog Create)
   - Layout 2 kolom (form + side)
   - Field rapi
   - Aksi rapi
   ========================================================= */

  .rc-wrap{ max-width:1120px; margin:0 auto; }
  .rc-shell{ padding:16px; }

  .rc-head{
    display:flex; align-items:flex-start; justify-content:space-between;
    gap:12px; flex-wrap:wrap; margin-bottom:12px;
  }
  .rc-head .title{ font-weight:900; letter-spacing:.12px; font-size:15px; margin:0; }
  .rc-head .sub{ margin-top:6px; font-size:12.8px; }

  .rc-layout{
    display:grid;
    grid-template-columns:minmax(0,1fr) 360px;
    gap:14px;
    align-items:start;
  }
  .rc-side{ position:sticky; top:14px; }

  .rc-section{
    padding:14px;
    border:1px solid var(--nb-border);
    border-radius:16px;
    background:var(--nb-surface);
  }
  .rc-section + .rc-section{ margin-top:12px; }

  .rc-section.acc-blue  { background: rgba(30,136,229,.06); border-color: rgba(30,136,229,.14); }
  .rc-section.acc-green { background: rgba(39,174,96,.06);  border-color: rgba(39,174,96,.14); }
  .rc-section.acc-slate { background: rgba(15,23,42,.035);  border-color: rgba(15,23,42,.10); }

  html.dark .rc-section.acc-blue  { background: rgba(30,136,229,.12); border-color: rgba(30,136,229,.18); }
  html.dark .rc-section.acc-green { background: rgba(39,174,96,.12);  border-color: rgba(39,174,96,.18); }
  html.dark .rc-section.acc-slate { background: rgba(148,163,184,.08); border-color: rgba(148,163,184,.14); }

  .rc-section-head{
    display:flex; align-items:flex-start; justify-content:space-between; gap:10px;
    padding-bottom:10px; margin-bottom:12px;
    border-bottom:1px solid var(--nb-border);
  }
  .rc-section-head .h{ font-weight:900; letter-spacing:.1px; font-size:13.5px; }
  .rc-section-head .hint{ font-size:12.5px; margin:0; }

  .rc-grid-1{ display:grid; grid-template-columns:1fr; gap:12px; }
  .rc-grid-2{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }

  .rc-field label{
    display:block;
    font-weight:800;
    font-size:12.5px;
    margin-bottom:6px;
  }

  .rc-field .nb-field,
  .rc-field textarea.nb-field{
    width:100%!important;
    box-sizing:border-box;
    font-size:12.8px;
    line-height:1.4;
    padding:9px 11px;
    border-radius:14px;
  }

  .rc-help{ margin-top:5px; font-size:12.3px; line-height:1.45; }
  .rc-error{ margin-top:5px; font-size:12.3px; color:#dc2626; }

  .rc-actions{
    display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;
    margin-top:14px; padding-top:14px;
    border-top:1px solid var(--nb-border);
  }

  .rc-kpi{ display:flex; flex-direction:column; gap:8px; }
  .rc-kpi .row{
    display:flex; justify-content:space-between; gap:10px;
    padding:10px 12px;
    border:1px solid var(--nb-border);
    border-radius:14px;
    background:rgba(31,58,95,.03);
    font-size:12.5px;
  }
  html.dark .rc-kpi .row{ background: rgba(255,255,255,.04); }
  .rc-kpi .row .v{ font-weight:900; font-size:13px; }

  @media(max-width:980px){
    .rc-layout{ grid-template-columns:1fr; }
    .rc-side{ position:static; }
    .rc-grid-2{ grid-template-columns:1fr; }
    .rc-actions .nb-btn,.rc-actions .nb-btn-primary{ width:100%; justify-content:center; }
  }
</style>

@if(!$canManage)
  <div class="nb-card rc-wrap" style="padding:16px;">
    <div style="font-weight:900;">Akses ditolak</div>
    <div class="nb-muted-2" style="margin-top:6px;">Hanya admin/staff yang dapat menambah rak.</div>
  </div>
@else

<div class="rc-wrap">
  <div class="nb-card rc-shell">

    <div class="rc-head">
      <div>
        <h1 class="title">Tambah Rak</h1>
        <div class="nb-muted-2 sub">Buat rak penyimpanan dan hubungkan ke cabang.</div>
      </div>
      <a href="{{ route('rak.index') }}" class="nb-btn">Kembali</a>
    </div>

    <form method="POST" action="{{ route('rak.store') }}">
      @csrf

      <div class="rc-layout">

        {{-- LEFT --}}
        <div>

          {{-- Identitas Rak --}}
          <div class="rc-section acc-blue">
            <div class="rc-section-head">
              <div class="h">Identitas</div>
              <p class="nb-muted-2 hint">Wajib: Cabang & Nama</p>
            </div>

            <div class="rc-grid-2">
              <div class="rc-field">
                <label>Cabang <span class="nb-muted-2">*</span></label>
                <select name="branch_id" class="nb-field" required>
                  <option value="" disabled {{ ($defaultBranchId==='' || $defaultBranchId===null) ? 'selected' : '' }}>Pilih cabang…</option>
                  @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected((string)$b->id === (string)$defaultBranchId)>
                      {{ $b->name }}
                    </option>
                  @endforeach
                </select>
                <div class="nb-muted-2 rc-help">Cabang diisi melalui menu <b>Master → Cabang</b>.</div>
                @error('branch_id') <div class="rc-error">{{ $message }}</div> @enderror
              </div>

              <div class="rc-field">
                <label>Nama Rak <span class="nb-muted-2">*</span></label>
                <input type="text" name="name" class="nb-field" value="{{ old('name') }}" required
                       placeholder="contoh: Rak Fiksi / Rak Referensi / Rak Anak">
                @error('name') <div class="rc-error">{{ $message }}</div> @enderror
              </div>
            </div>

            <div style="height:10px"></div>

            <div class="rc-grid-2">
              <div class="rc-field">
                <label>Kode Rak</label>
                <input type="text" name="code" class="nb-field" value="{{ old('code') }}"
                       placeholder="contoh: FIK / REF / ANK">
                <div class="nb-muted-2 rc-help">Opsional, tapi sebaiknya unik per cabang (untuk label).</div>
                @error('code') <div class="rc-error">{{ $message }}</div> @enderror
              </div>

              <div class="rc-field">
                <label>Urutan (Sort Order)</label>
                <input type="number" name="sort_order" class="nb-field" value="{{ old('sort_order', 0) }}"
                       min="0" max="9999" placeholder="contoh: 10">
                <div class="nb-muted-2 rc-help">Angka kecil tampil lebih atas.</div>
                @error('sort_order') <div class="rc-error">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Lokasi & Catatan --}}
          <div class="rc-section acc-slate">
            <div class="rc-section-head">
              <div class="h">Lokasi</div>
              <p class="nb-muted-2 hint">Opsional</p>
            </div>

            <div class="rc-grid-1">
              <div class="rc-field">
                <label>Lokasi</label>
                <input type="text" name="location" class="nb-field" value="{{ old('location') }}"
                       placeholder="contoh: Lantai 1, dekat pintu masuk / Ruang Baca Utama">
                @error('location') <div class="rc-error">{{ $message }}</div> @enderror
              </div>

              <div class="rc-field">
                <label>Catatan</label>
                <textarea name="notes" class="nb-field" rows="4"
                          placeholder="contoh: khusus koleksi baru, tidak boleh dipinjam keluar, dll">{{ old('notes') }}</textarea>
                @error('notes') <div class="rc-error">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Status --}}
          <div class="rc-section acc-green">
            <div class="rc-section-head">
              <div class="h">Status</div>
              <p class="nb-muted-2 hint">Default aktif</p>
            </div>

            <div class="rc-grid-1">
              <div class="rc-field">
                <label>Aktif?</label>
                <select name="is_active" class="nb-field">
                  <option value="1" @selected((string)old('is_active','1')==='1')>Aktif</option>
                  <option value="0" @selected((string)old('is_active','1')==='0')>Nonaktif</option>
                </select>
                <div class="nb-muted-2 rc-help">Rak nonaktif tidak disarankan dipakai untuk eksemplar baru.</div>
                @error('is_active') <div class="rc-error">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Actions --}}
          <div class="rc-actions">
            <button class="nb-btn nb-btn-primary" type="submit">Simpan Rak</button>
            <a class="nb-btn" href="{{ route('rak.index') }}">Batal</a>
          </div>

        </div>

        {{-- RIGHT --}}
        <div class="rc-side">
          <div class="rc-section acc-slate">
            <div class="rc-section-head">
              <div class="h">Ringkasan</div>
              <p class="nb-muted-2 hint">Checklist</p>
            </div>

            <div class="rc-kpi">
              <div class="row"><span>Keterkaitan</span><span class="v">Cabang → Rak</span></div>
              <div class="row"><span>Dipakai oleh</span><span class="v">Eksemplar</span></div>
              <div class="row"><span>Standar</span><span class="v">Master Data</span></div>
            </div>

            <div style="height:12px;"></div>

            <div class="nb-muted-2" style="line-height:1.55; font-size:12.5px;">
              • Isi <b>Nama</b> yang mudah dipahami.<br>
              • Pakai <b>Kode</b> jika ingin label cepat.<br>
              • Isi <b>Lokasi</b> agar staff gampang mengarahkan.<br>
              • Nonaktifkan rak jika sudah tidak dipakai.
            </div>
          </div>
        </div>

      </div>
    </form>

  </div>
</div>

@endif
@endsection
