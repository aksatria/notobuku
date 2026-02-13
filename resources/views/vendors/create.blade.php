@extends('layouts.notobuku')

@section('title', 'Tambah Vendor')

@section('content')
<style>
  .saas-page{ max-width:900px; margin:0 auto; padding:0 10px 24px; display:flex; flex-direction:column; gap:14px; overflow-x:hidden; }
  .saas-card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; box-shadow:0 1px 0 rgba(17,24,39,.02); }
  .saas-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .saas-title{ font-weight:600; font-size:18px; margin:0; }
  .saas-sub{ font-size:12.5px; color:var(--nb-muted); margin-top:4px; }
  .field label{ display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:var(--nb-muted); }
  .field .nb-field{ width:100%; padding:10px 12px; border-radius:12px; }
</style>

<div class="saas-page">
  <div class="saas-card">
    <div class="saas-head">
      <div>
        <h1 class="saas-title">Tambah Vendor</h1>
        <div class="saas-sub">Lengkapi data vendor.</div>
      </div>
      <a class="nb-btn" href="{{ route('acquisitions.vendors.index') }}">Kembali</a>
    </div>

    <form method="POST" action="{{ route('acquisitions.vendors.store') }}" style="margin-top:12px;">
      @csrf
      <div class="field" style="margin-bottom:10px;">
        <label>Nama</label>
        <input class="nb-field" name="name" required>
      </div>
      <div class="field" style="margin-bottom:10px;">
        <label>Catatan</label>
        <textarea class="nb-field" name="notes" rows="3"></textarea>
      </div>
      <button class="nb-btn nb-btn-primary" type="submit">Simpan</button>
    </form>
  </div>
</div>
@endsection
