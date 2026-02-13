@extends('layouts.notobuku')

@section('title', 'Buat PO')

@section('content')
@php
  $vendors = $vendors ?? collect();
  $branches = $branches ?? collect();
@endphp

<style>
  .saas-page{ max-width:1100px; margin:0 auto; padding:0 10px 24px; }
  .saas-card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; }
  .saas-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .saas-title{ font-weight:600; font-size:18px; margin:0; }
  .saas-sub{ font-size:13px; color:var(--nb-muted); margin-top:6px; }
  .grid{ display:grid; gap:12px; grid-template-columns:repeat(12,minmax(0,1fr)); }
  .col-6{ grid-column:span 6; }
  .col-12{ grid-column:span 12; }
  .field label{ display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:var(--nb-muted); }
  .field .nb-field{ width:100%; padding:10px 12px; border-radius:12px; }
  @media(max-width:900px){ .col-6{ grid-column:span 12; } }
</style>

<div class="saas-page">
  <div class="saas-card">
    <div class="saas-head">
      <div>
        <h1 class="saas-title">Buat Purchase Order</h1>
        <div class="saas-sub">Tambahkan vendor dan cabang.</div>
      </div>
      <a class="nb-btn" href="{{ route('acquisitions.pos.index') }}">Kembali</a>
    </div>

    <form method="POST" action="{{ route('acquisitions.pos.store') }}" style="margin-top:12px;">
      @csrf
      <div class="grid">
        <div class="field col-6">
          <label>Vendor</label>
          <select class="nb-field" name="vendor_id" required>
            <option value="">- pilih vendor -</option>
            @foreach($vendors as $v)
              <option value="{{ $v->id }}">{{ $v->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="field col-6">
          <label>Cabang</label>
          <select class="nb-field" name="branch_id">
            <option value="">- umum -</option>
            @foreach($branches as $br)
              <option value="{{ $br->id }}">{{ $br->name }} {{ $br->code ? '(' . $br->code . ')' : '' }}</option>
            @endforeach
          </select>
        </div>
        <div class="field col-6">
          <label>Currency</label>
          <input class="nb-field" name="currency" value="IDR">
        </div>
      </div>
      <div style="height:12px;"></div>
      <button class="nb-btn nb-btn-primary" type="submit">Simpan PO</button>
    </form>
  </div>
</div>
@endsection
