@extends('layouts.notobuku')

@section('title', 'Edit Anggota')

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
    <h1 class="title">Edit Anggota</h1>
    <div class="muted">{{ $member->full_name }} ({{ $member->member_code }})</div>

    <form method="POST" action="{{ route('anggota.update', $member->id) }}">
      @csrf
      @method('PUT')
      <div class="grid">
        <div class="field">
          <label>Kode Anggota</label>
          <input class="nb-field" name="member_code" value="{{ old('member_code', $member->member_code) }}" required>
          @error('member_code')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="field">
          <label>Nama Lengkap</label>
          <input class="nb-field" name="full_name" value="{{ old('full_name', $member->full_name) }}" required>
          @error('full_name')<div class="err">{{ $message }}</div>@enderror
        </div>

        @if(!empty($hasMemberType))
          <div class="field">
            <label>Tipe Anggota</label>
            <input class="nb-field" name="member_type" value="{{ old('member_type', $member->member_type) }}">
            @error('member_type')<div class="err">{{ $message }}</div>@enderror
          </div>
        @endif
        <div class="field">
          <label>Status</label>
          <select class="nb-field" name="status" required>
            <option value="active" @selected(old('status', $member->status) === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $member->status) === 'inactive')>Inactive</option>
            <option value="suspended" @selected(old('status', $member->status) === 'suspended')>Suspended</option>
          </select>
          @error('status')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div class="field">
          <label>Telepon</label>
          <input class="nb-field" name="phone" value="{{ old('phone', $member->phone) }}">
          @error('phone')<div class="err">{{ $message }}</div>@enderror
        </div>
        @if(!empty($hasEmail))
          <div class="field">
            <label>Email</label>
            <input class="nb-field" type="email" name="email" value="{{ old('email', $member->email) }}">
            @error('email')<div class="err">{{ $message }}</div>@enderror
          </div>
        @endif
        <div class="field">
          <label>Tanggal Bergabung</label>
          <input class="nb-field" type="date" name="joined_at" value="{{ old('joined_at', $member->joined_at ? \Illuminate\Support\Carbon::parse($member->joined_at)->toDateString() : '') }}">
          @error('joined_at')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="field" style="grid-column:1/-1;">
          <label>Alamat</label>
          <textarea class="nb-field" name="address" rows="3">{{ old('address', $member->address) }}</textarea>
          @error('address')<div class="err">{{ $message }}</div>@enderror
        </div>
      </div>

      <div style="display:flex; gap:10px; margin-top:14px;">
        <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
        <a class="btn" href="{{ route('anggota.index') }}">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection

