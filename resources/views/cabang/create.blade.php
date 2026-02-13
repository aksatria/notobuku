{{-- resources/views/cabang/create.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Tambah Cabang • NOTOBUKU')

@section('content')
@php
  $role = auth()->user()->role ?? 'member';
  $canManage = in_array($role, ['super_admin','admin','staff'], true);
@endphp

<style>
  /* =========================================================
     NOTOBUKU • Master • Cabang • Create
     ========================================================= */

  .cb-wrap{ max-width:960px; margin:0 auto; }
  .cb-shell{ padding:16px; }

  .cb-head{
    display:flex; justify-content:space-between; align-items:flex-start;
    gap:12px; flex-wrap:wrap; margin-bottom:12px;
  }
  .cb-title{ font-size:15px; font-weight:900; margin:0; }
  .cb-sub{ margin-top:6px; font-size:12.8px; line-height:1.45; }

  .cb-section{
    padding:14px;
    border:1px solid var(--nb-border);
    border-radius:16px;
    background:var(--nb-surface);
  }
  .cb-section + .cb-section{ margin-top:12px; }

  .cb-section-head{
    display:flex; justify-content:space-between; gap:10px;
    padding-bottom:10px; margin-bottom:12px;
    border-bottom:1px solid var(--nb-border);
  }
  .cb-section-head .h{ font-size:13.5px; font-weight:900; }

  .cb-grid-2{ display:grid; grid-template-columns:repeat(2,1fr); gap:12px; }
  .cb-grid-1{ display:grid; grid-template-columns:1fr; gap:12px; }

  .cb-field label{
    display:block; font-weight:800; font-size:12.5px; margin-bottom:6px;
  }
  .cb-field .nb-field{
    width:100%!important;
    font-size:12.8px;
    padding:9px 11px;
    border-radius:14px;
  }

  .cb-help{ margin-top:5px; font-size:12.3px; color:var(--nb-muted); }
  .cb-error{ margin-top:5px; font-size:12.3px; color:#dc2626; }

  .cb-actions{
    display:flex; gap:10px; justify-content:flex-end;
    padding-top:14px; margin-top:14px;
    border-top:1px solid var(--nb-border);
  }

  @media(max-width:900px){
    .cb-grid-2{ grid-template-columns:1fr; }
    .cb-actions .nb-btn{ width:100%; justify-content:center; }
  }
</style>

@if(!$canManage)
  <div class="nb-card cb-wrap" style="padding:16px;">
    <div style="font-weight:900;">Akses ditolak</div>
    <div class="nb-muted-2" style="margin-top:6px;">
      Hanya admin atau staff yang dapat mengelola cabang.
    </div>
  </div>
@else

<div class="cb-wrap">
  <div class="nb-card cb-shell">

    <div class="cb-head">
      <div>
        <h1 class="cb-title">Tambah Cabang</h1>
        <div class="nb-muted-2 cb-sub">
          Cabang digunakan untuk menentukan lokasi fisik eksemplar.
        </div>
      </div>
      <a href="{{ route('cabang.index') }}" class="nb-btn">Kembali</a>
    </div>

    <form method="POST" action="{{ route('cabang.store') }}">
      @csrf

      <div class="cb-section">
        <div class="cb-section-head">
          <div class="h">Informasi Cabang</div>
        </div>

        <div class="cb-grid-2">
          <div class="cb-field">
            <label>Nama Cabang <span class="nb-muted-2">*</span></label>
            <input class="nb-field" name="name" required
                   value="{{ old('name') }}"
                   placeholder="contoh: Perpustakaan Pusat">
            @error('name') <div class="cb-error">{{ $message }}</div> @enderror
          </div>

          <div class="cb-field">
            <label>Kode Cabang</label>
            <input class="nb-field" name="code"
                   value="{{ old('code') }}"
                   placeholder="contoh: PUSAT">
            <div class="cb-help">Opsional, harus unik per institusi.</div>
            @error('code') <div class="cb-error">{{ $message }}</div> @enderror
          </div>
        </div>

        <div class="cb-grid-1" style="margin-top:12px;">
          <div class="cb-field">
            <label>Alamat</label>
            <input class="nb-field" name="address"
                   value="{{ old('address') }}"
                   placeholder="contoh: Jl. Merdeka No. 10, Jakarta">
            @error('address') <div class="cb-error">{{ $message }}</div> @enderror
          </div>

          <div class="cb-field">
            <label>Catatan</label>
            <textarea class="nb-field" name="notes" rows="3"
                      placeholder="Keterangan tambahan (opsional)">{{ old('notes') }}</textarea>
            @error('notes') <div class="cb-error">{{ $message }}</div> @enderror
          </div>
        </div>
      </div>

      <div class="cb-actions">
        <button type="submit" class="nb-btn nb-btn-primary">Simpan Cabang</button>
        <a href="{{ route('cabang.index') }}" class="nb-btn">Batal</a>
      </div>

    </form>
  </div>
</div>
@endif
@endsection
