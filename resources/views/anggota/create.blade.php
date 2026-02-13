@extends('layouts.notobuku')

@section('title', 'Tambah Anggota')

@section('content')
<style>
  .page{ max-width:860px; margin:0 auto; }
  .card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; }
  .title{ margin:0; font-size:20px; font-weight:700; }
  .muted{ color:var(--nb-muted); font-size:13px; }
  .grid{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:14px; }
  .field{ display:flex; flex-direction:column; gap:6px; }
  .field label{ font-size:12px; font-weight:600; color:var(--nb-muted); }
  .nb-field{ width:100%; border:1px solid var(--nb-border); border-radius:12px; padding:10px 12px; background:var(--nb-surface); }
  .err{ font-size:12px; color:#b91c1c; }
  .btn{ display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--nb-border); border-radius:12px; padding:10px 12px; font-weight:600; }
  .btn-primary{ background:linear-gradient(90deg,#1e88e5,#1565c0); color:#fff; border-color:transparent; }
  @media (max-width:860px){ .grid{ grid-template-columns:1fr; } }
</style>

<div class="page">
  <div class="card">
    <h1 class="title">Tambah Anggota</h1>
    <div class="muted">Isi data dasar anggota untuk transaksi sirkulasi dan laporan.</div>

    <form method="POST" action="{{ route('anggota.store') }}">
      @csrf
      <div class="grid">
        <div class="field">
          <label>Kode Anggota</label>
          <input class="nb-field" name="member_code" value="{{ old('member_code') }}" required>
          @error('member_code')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="field">
          <label>Nama Lengkap</label>
          <input class="nb-field" name="full_name" value="{{ old('full_name') }}" required>
          @error('full_name')<div class="err">{{ $message }}</div>@enderror
        </div>

        @if(!empty($hasMemberType))
          <div class="field">
            <label>Tipe Anggota</label>
            <input class="nb-field" name="member_type" value="{{ old('member_type', 'member') }}">
            @error('member_type')<div class="err">{{ $message }}</div>@enderror
          </div>
        @endif

        <div class="field">
          <label>Status</label>
          <select class="nb-field" name="status" required>
            <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
            <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
            <option value="suspended" @selected(old('status') === 'suspended')>Suspended</option>
          </select>
          @error('status')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div class="field">
          <label>Telepon</label>
          <input class="nb-field" name="phone" value="{{ old('phone') }}">
          @error('phone')<div class="err">{{ $message }}</div>@enderror
        </div>
        @if(!empty($hasEmail))
          <div class="field">
            <label>Email</label>
            <input class="nb-field" type="email" name="email" value="{{ old('email') }}">
            @error('email')<div class="err">{{ $message }}</div>@enderror
          </div>
        @endif
        <div class="field">
          <label>Tanggal Bergabung</label>
          <input class="nb-field" type="date" name="joined_at" value="{{ old('joined_at') }}">
          @error('joined_at')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="field" style="grid-column:1/-1;">
          <label>Alamat</label>
          <textarea class="nb-field" name="address" rows="3">{{ old('address') }}</textarea>
          @error('address')<div class="err">{{ $message }}</div>@enderror
        </div>
      </div>

      <div style="display:flex; gap:10px; margin-top:14px;">
        <button class="btn btn-primary" type="submit">Simpan</button>
        <a class="btn" href="{{ route('anggota.index') }}">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection

