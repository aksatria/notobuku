@extends('layouts.notobuku')

@section('title', 'Buat Request Pengadaan')

@section('content')
@php
  $bookRequest = $bookRequest ?? null;
  $branches = $branches ?? collect();
  $pendingBookRequests = $pendingBookRequests ?? collect();
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
        <h1 class="saas-title">Buat Request Pengadaan</h1>
        <div class="saas-sub">Masukkan data buku yang ingin diajukan.</div>
      </div>
      <a class="nb-btn" href="{{ route('acquisitions.requests.index') }}">Kembali</a>
    </div>

    <form method="POST" action="{{ route('acquisitions.requests.store') }}" style="margin-top:12px;">
      @csrf
      <div class="grid">
        <div class="field col-12">
          <label>Ambil dari Book Request (opsional)</label>
          <select class="nb-field" name="book_request_id">
            <option value="">- pilih -</option>
            @foreach($pendingBookRequests as $br)
              <option value="{{ $br->id }}" {{ $bookRequest && $bookRequest->id === $br->id ? 'selected' : '' }}>
                #{{ $br->id }} • {{ $br->title }} • {{ $br->author ?: '-' }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="field col-12">
          <label>Judul</label>
          <input class="nb-field" name="title" value="{{ old('title', $bookRequest->title ?? '') }}" required>
        </div>
        <div class="field col-6">
          <label>Penulis</label>
          <input class="nb-field" name="author_text" value="{{ old('author_text', $bookRequest->author ?? '') }}">
        </div>
        <div class="field col-6">
          <label>ISBN</label>
          <input class="nb-field" name="isbn" value="{{ old('isbn', $bookRequest->isbn ?? '') }}">
        </div>
        <div class="field col-6">
          <label>Prioritas</label>
          <select class="nb-field" name="priority">
            @foreach(['low','normal','high','urgent'] as $p)
              <option value="{{ $p }}" {{ old('priority', 'normal')===$p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
            @endforeach
          </select>
        </div>
        <div class="field col-6">
          <label>Cabang</label>
          <select class="nb-field" name="branch_id">
            <option value="">- umum -</option>
            @foreach($branches as $br)
              <option value="{{ $br->id }}" {{ (string)old('branch_id')===(string)$br->id ? 'selected' : '' }}>{{ $br->name }} {{ $br->code ? '(' . $br->code . ')' : '' }}</option>
            @endforeach
          </select>
        </div>
        <div class="field col-6">
          <label>Estimasi Harga</label>
          <input class="nb-field" type="number" step="0.01" name="estimated_price" value="{{ old('estimated_price') }}">
        </div>
        <div class="field col-12">
          <label>Catatan</label>
          <textarea class="nb-field" name="notes" rows="3">{{ old('notes', $bookRequest->reason ?? '') }}</textarea>
        </div>
      </div>
      <div style="height:12px;"></div>
      <button class="nb-btn nb-btn-primary" type="submit">Simpan Request</button>
    </form>
  </div>
</div>
@endsection
