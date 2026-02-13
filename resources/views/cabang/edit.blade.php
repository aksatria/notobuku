{{-- resources/views/cabang/edit.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Edit Cabang • NOTOBUKU')

@section('content')
@php
  $role = auth()->user()->role ?? 'member';
  $canManage = in_array($role, ['super_admin','admin','staff'], true);
@endphp

<style>
  /* =========================================================
     NOTOBUKU • Master • Cabang • Edit (RAPI)
     ========================================================= */

  .cb-wrap{ max-width:960px; margin:0 auto; }
  .cb-shell{ padding:16px; }

  .cb-head{
    display:flex; justify-content:space-between; align-items:flex-start;
    gap:12px; flex-wrap:wrap; margin-bottom:12px;
  }
  .cb-title{ font-size:15px; font-weight:900; margin:0; }
  .cb-sub{ margin-top:6px; font-size:12.8px; }

  .cb-layout{
    display:grid;
    grid-template-columns:minmax(0,1fr) 300px;
    gap:14px;
    align-items:start;
  }

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

  /* STATUS CARD */
  .cb-status{
    display:flex; flex-direction:column; gap:10px;
    border:1px solid var(--nb-border);
    border-radius:16px;
    padding:14px;
    background:rgba(15,23,42,.03);
  }
  html.dark .cb-status{ background:rgba(255,255,255,.04); }

  .cb-status .row{
    display:flex; justify-content:space-between; gap:8px;
    font-size:12.5px;
  }
  .cb-status .v{ font-weight:900; }

  .cb-badge{
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 12px;
    border-radius:999px;
    font-size:12.5px;
    font-weight:800;
    border:1px solid var(--nb-border);
    width:fit-content;
  }
  .cb-dot{ width:9px; height:9px; border-radius:50%; }
  .on{ background:#22c55e; }
  .off{ background:#ef4444; }

  @media(max-width:980px){
    .cb-layout{ grid-template-columns:1fr; }
    .cb-actions .nb-btn{ width:100%; justify-content:center; }
  }
</style>

@if(!$canManage)
  <div class="nb-card cb-wrap" style="padding:16px;">
    <div style="font-weight:900;">Akses ditolak</div>
  </div>
@else

<div class="cb-wrap">
  <div class="nb-card cb-shell">

    <div class="cb-head">
      <div>
        <h1 class="cb-title">Edit Cabang</h1>
        <div class="cb-sub nb-muted-2">
          Kelola informasi cabang perpustakaan
        </div>
      </div>
      <a href="{{ route('cabang.index') }}" class="nb-btn">Kembali</a>
    </div>

    <form method="POST" action="{{ route('cabang.update', $branch->id) }}">
      @csrf
      @method('PUT')

      <div class="cb-layout">

        {{-- LEFT --}}
        <div>

          <div class="cb-section">
            <div class="cb-section-head">
              <div class="h">Informasi Cabang</div>
            </div>

            <div class="cb-grid-2">
              <div class="cb-field">
                <label>Nama Cabang <span class="nb-muted-2">*</span></label>
                <input class="nb-field" name="name" required
                       value="{{ old('name', $branch->name) }}">
                @error('name') <div class="cb-error">{{ $message }}</div> @enderror
              </div>

              <div class="cb-field">
                <label>Kode Cabang</label>
                <input class="nb-field" name="code"
                       value="{{ old('code', $branch->code) }}">
                <div class="cb-help">Opsional, harus unik per institusi.</div>
                @error('code') <div class="cb-error">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="cb-grid-1" style="margin-top:12px;">
              <div class="cb-field">
                <label>Alamat</label>
                <input class="nb-field" name="address"
                       value="{{ old('address', $branch->address) }}">
                @error('address') <div class="cb-error">{{ $message }}</div> @enderror
              </div>

              <div class="cb-field">
                <label>Catatan</label>
                <textarea class="nb-field" name="notes" rows="3">{{ old('notes', $branch->notes) }}</textarea>
                @error('notes') <div class="cb-error">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          <div class="cb-actions">
            <button type="submit" class="nb-btn nb-btn-primary">Simpan Perubahan</button>
            <a href="{{ route('cabang.index') }}" class="nb-btn">Batal</a>
          </div>

        </div>

        {{-- RIGHT --}}
        <div>
          <div class="cb-section">
            <div class="cb-section-head">
              <div class="h">Status Cabang</div>
            </div>

            <div class="cb-status">
              <div class="cb-badge">
                <span class="cb-dot {{ $branch->is_active ? 'on' : 'off' }}"></span>
                {{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}
              </div>

              <div class="row">
                <span>ID Cabang</span>
                <span class="v">{{ $branch->id }}</span>
              </div>

              <div class="row">
                <span>Digunakan Eksemplar</span>
                <span class="v">
                  {{ \Illuminate\Support\Facades\DB::table('items')->where('branch_id',$branch->id)->count() }}
                </span>
              </div>

              <form method="POST" action="{{ route('cabang.toggle', $branch->id) }}">
                @csrf
                <button type="submit" class="nb-btn {{ $branch->is_active ? '' : 'nb-btn-success' }}">
                  {{ $branch->is_active ? 'Nonaktifkan Cabang' : 'Aktifkan Cabang' }}
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
