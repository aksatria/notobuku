{{-- resources/views/rak/edit.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Edit Rak • NOTOBUKU')

@section('content')
@php
  $role = auth()->user()->role ?? 'member';
  $canManage = in_array($role, ['super_admin','admin','staff'], true);

  // dari controller:
  // $shelf (rak yang diedit)
  // $branches (list cabang)
  $branches = $branches ?? collect();

  $currentBranchId = old('branch_id', (string)($shelf->branch_id ?? ''));
@endphp

<style>
/* =========================================================
   NOTOBUKU • Rak • Edit (match Katalog Create)
   ========================================================= */

  .re-wrap{ max-width:1120px; margin:0 auto; }
  .re-shell{ padding:16px; }

  .re-head{
    display:flex; align-items:flex-start; justify-content:space-between;
    gap:12px; flex-wrap:wrap; margin-bottom:12px;
  }
  .re-head .title{ font-weight:900; letter-spacing:.12px; font-size:15px; margin:0; }
  .re-head .sub{ margin-top:6px; font-size:12.8px; }

  .re-layout{
    display:grid;
    grid-template-columns:minmax(0,1fr) 360px;
    gap:14px;
    align-items:start;
  }
  .re-side{ position:sticky; top:14px; }

  .re-section{
    padding:14px;
    border:1px solid var(--nb-border);
    border-radius:16px;
    background:var(--nb-surface);
  }
  .re-section + .re-section{ margin-top:12px; }

  .re-section.acc-blue  { background: rgba(30,136,229,.06); border-color: rgba(30,136,229,.14); }
  .re-section.acc-green { background: rgba(39,174,96,.06);  border-color: rgba(39,174,96,.14); }
  .re-section.acc-slate { background: rgba(15,23,42,.035);  border-color: rgba(15,23,42,.10); }

  html.dark .re-section.acc-blue  { background: rgba(30,136,229,.12); border-color: rgba(30,136,229,.18); }
  html.dark .re-section.acc-green { background: rgba(39,174,96,.12);  border-color: rgba(39,174,96,.18); }
  html.dark .re-section.acc-slate { background: rgba(148,163,184,.08); border-color: rgba(148,163,184,.14); }

  .re-section-head{
    display:flex; align-items:flex-start; justify-content:space-between; gap:10px;
    padding-bottom:10px; margin-bottom:12px;
    border-bottom:1px solid var(--nb-border);
  }
  .re-section-head .h{ font-weight:900; letter-spacing:.1px; font-size:13.5px; }
  .re-section-head .hint{ font-size:12.5px; margin:0; }

  .re-grid-1{ display:grid; grid-template-columns:1fr; gap:12px; }
  .re-grid-2{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }

  .re-field label{
    display:block;
    font-weight:800;
    font-size:12.5px;
    margin-bottom:6px;
  }

  .re-field .nb-field,
  .re-field textarea.nb-field{
    width:100%!important;
    box-sizing:border-box;
    font-size:12.8px;
    line-height:1.4;
    padding:9px 11px;
    border-radius:14px;
  }

  .re-help{ margin-top:5px; font-size:12.3px; line-height:1.45; }
  .re-error{ margin-top:5px; font-size:12.3px; color:#dc2626; }

  .re-actions{
    display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;
    margin-top:14px; padding-top:14px;
    border-top:1px solid var(--nb-border);
  }

  .re-kpi{ display:flex; flex-direction:column; gap:8px; }
  .re-kpi .row{
    display:flex; justify-content:space-between; gap:10px;
    padding:10px 12px;
    border:1px solid var(--nb-border);
    border-radius:14px;
    background:rgba(31,58,95,.03);
    font-size:12.5px;
  }
  html.dark .re-kpi .row{ background: rgba(255,255,255,.04); }
  .re-kpi .row .v{ font-weight:900; font-size:13px; }

  /* badge kecil */
  .re-badge{
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(15,23,42,.03);
    color: rgba(11,37,69,.85);
    white-space:nowrap;
  }
  html.dark .re-badge{
    border-color: rgba(148,163,184,.18);
    background: rgba(255,255,255,.04);
    color: rgba(226,232,240,.86);
  }
  .re-badge.ok{
    border-color: rgba(39,174,96,.22);
    background: rgba(39,174,96,.08);
    color: rgba(18,102,58,.95);
  }
  html.dark .re-badge.ok{
    border-color: rgba(39,174,96,.28);
    background: rgba(39,174,96,.14);
    color: rgba(187,247,208,.92);
  }
  .re-badge.off{
    border-color: rgba(251,140,0,.22);
    background: rgba(251,140,0,.08);
    color: rgba(156,87,0,.95);
  }
  html.dark .re-badge.off{
    border-color: rgba(251,140,0,.28);
    background: rgba(251,140,0,.14);
    color: rgba(254,215,170,.92);
  }

  /* delete card */
  .re-danger{
    border:1px solid rgba(220,38,38,.18);
    background: rgba(220,38,38,.04);
    border-radius:16px;
    padding:12px;
  }
  html.dark .re-danger{
    border-color: rgba(248,113,113,.20);
    background: rgba(248,113,113,.06);
  }

  @media(max-width:980px){
    .re-layout{ grid-template-columns:1fr; }
    .re-side{ position:static; }
    .re-grid-2{ grid-template-columns:1fr; }
    .re-actions .nb-btn,.re-actions .nb-btn-primary{ width:100%; justify-content:center; }
  }
</style>

@if(!$canManage)
  <div class="nb-card re-wrap" style="padding:16px;">
    <div style="font-weight:900;">Akses ditolak</div>
    <div class="nb-muted-2" style="margin-top:6px;">Hanya admin/staff yang dapat mengubah rak.</div>
  </div>
@else

<div class="re-wrap">
  <div class="nb-card re-shell">

    <div class="re-head">
      <div>
        <h1 class="title">Edit Rak</h1>
        <div class="nb-muted-2 sub">
          Perbarui informasi rak.
          <span style="margin-left:8px;">
            @if((int)($shelf->is_active ?? 1) === 1)
              <span class="re-badge ok">Aktif</span>
            @else
              <span class="re-badge off">Nonaktif</span>
            @endif
          </span>
        </div>
      </div>
      <a href="{{ route('rak.index') }}" class="nb-btn">Kembali</a>
    </div>

    <form method="POST" action="{{ route('rak.update', $shelf->id) }}">
      @csrf
      @method('PUT')

      <div class="re-layout">

        {{-- LEFT --}}
        <div>

          {{-- Identitas --}}
          <div class="re-section acc-blue">
            <div class="re-section-head">
              <div class="h">Identitas</div>
              <p class="nb-muted-2 hint">Cabang & Nama</p>
            </div>

            <div class="re-grid-2">
              <div class="re-field">
                <label>Cabang <span class="nb-muted-2">*</span></label>
                <select name="branch_id" class="nb-field" required>
                  <option value="" disabled {{ ($currentBranchId==='' || $currentBranchId===null) ? 'selected' : '' }}>Pilih cabang…</option>
                  @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected((string)$b->id === (string)$currentBranchId)>
                      {{ $b->name }}
                    </option>
                  @endforeach
                </select>
                <div class="nb-muted-2 re-help">Pindahkan rak ke cabang lain jika memang diperlukan.</div>
                @error('branch_id') <div class="re-error">{{ $message }}</div> @enderror
              </div>

              <div class="re-field">
                <label>Nama Rak <span class="nb-muted-2">*</span></label>
                <input type="text" name="name" class="nb-field" value="{{ old('name', $shelf->name) }}" required
                       placeholder="contoh: Rak Fiksi">
                @error('name') <div class="re-error">{{ $message }}</div> @enderror
              </div>
            </div>

            <div style="height:10px"></div>

            <div class="re-grid-2">
              <div class="re-field">
                <label>Kode Rak</label>
                <input type="text" name="code" class="nb-field" value="{{ old('code', $shelf->code) }}"
                       placeholder="contoh: FIK">
                @error('code') <div class="re-error">{{ $message }}</div> @enderror
              </div>

              <div class="re-field">
                <label>Urutan (Sort Order)</label>
                <input type="number" name="sort_order" class="nb-field" value="{{ old('sort_order', (int)($shelf->sort_order ?? 0)) }}"
                       min="0" max="9999" placeholder="contoh: 10">
                @error('sort_order') <div class="re-error">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Lokasi & Catatan --}}
          <div class="re-section acc-slate">
            <div class="re-section-head">
              <div class="h">Lokasi</div>
              <p class="nb-muted-2 hint">Opsional</p>
            </div>

            <div class="re-grid-1">
              <div class="re-field">
                <label>Lokasi</label>
                <input type="text" name="location" class="nb-field" value="{{ old('location', $shelf->location) }}"
                       placeholder="contoh: Lantai 1 dekat pintu masuk">
                @error('location') <div class="re-error">{{ $message }}</div> @enderror
              </div>

              <div class="re-field">
                <label>Catatan</label>
                <textarea name="notes" class="nb-field" rows="4"
                          placeholder="contoh: khusus koleksi baru, dll">{{ old('notes', $shelf->notes) }}</textarea>
                @error('notes') <div class="re-error">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Status --}}
          <div class="re-section acc-green">
            <div class="re-section-head">
              <div class="h">Status</div>
              <p class="nb-muted-2 hint">Aktif/nonaktif</p>
            </div>

            <div class="re-grid-1">
              <div class="re-field">
                <label>Aktif?</label>
                <select name="is_active" class="nb-field">
                  <option value="1" @selected((string)old('is_active', (string)($shelf->is_active ?? 1))==='1')>Aktif</option>
                  <option value="0" @selected((string)old('is_active', (string)($shelf->is_active ?? 1))==='0')>Nonaktif</option>
                </select>
                <div class="nb-muted-2 re-help">Jika nonaktif, rak tidak direkomendasikan untuk eksemplar baru.</div>
                @error('is_active') <div class="re-error">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Actions --}}
          <div class="re-actions">
            <button class="nb-btn nb-btn-primary" type="submit">Simpan Perubahan</button>
            <a class="nb-btn" href="{{ route('rak.index') }}">Batal</a>
          </div>

        </div>

        {{-- RIGHT --}}
        <div class="re-side">

          {{-- Info --}}
          <div class="re-section acc-slate">
            <div class="re-section-head">
              <div class="h">Info</div>
              <p class="nb-muted-2 hint">ID & waktu</p>
            </div>

            <div class="re-kpi">
              <div class="row"><span>ID</span><span class="v">#{{ $shelf->id }}</span></div>
              <div class="row"><span>Dibuat</span><span class="v">{{ optional($shelf->created_at)->format('d M Y H:i') }}</span></div>
              <div class="row"><span>Diubah</span><span class="v">{{ optional($shelf->updated_at)->format('d M Y H:i') }}</span></div>
            </div>
          </div>

          {{-- Danger zone --}}
          <div class="re-section acc-slate" style="margin-top:12px;">
            <div class="re-section-head">
              <div class="h">Hapus Rak</div>
              <p class="nb-muted-2 hint">Opsional</p>
            </div>

            <div class="re-danger">
              <div style="font-weight:900; font-size:13px;">Zona berbahaya</div>
              <div class="nb-muted-2" style="margin-top:6px; font-size:12.5px; line-height:1.55;">
                Hapus rak hanya jika tidak dipakai eksemplar. Sistem biasanya akan menolak jika masih terpakai.
              </div>

              <div style="height:10px;"></div>

              <form method="POST" action="{{ route('rak.destroy', $shelf->id) }}"
                    onsubmit="return confirm('Hapus rak ini? Tindakan tidak bisa dibatalkan.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="nb-btn" style="border-color: rgba(220,38,38,.28); color:#dc2626;">
                  Hapus Rak
                </button>
              </form>
            </div>
          </div>

        </div>

      </div>
    </form>

  </div>
</div>

@endif
@endsection
