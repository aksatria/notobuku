@extends('layouts.notobuku')

@section('title', 'Vendor')

@section('content')
@php
  $vendors = $vendors ?? null;
  $q = (string)($q ?? '');
@endphp

<style>
  .saas-page{ max-width:1100px; margin:0 auto; padding:0 10px 24px; display:flex; flex-direction:column; gap:14px; overflow-x:hidden; }
  .saas-card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; box-shadow:0 1px 0 rgba(17,24,39,.02); }
  .saas-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .saas-title{ font-weight:600; font-size:18px; margin:0; }
  .saas-sub{ font-size:12.5px; color:var(--nb-muted); margin-top:4px; }
  .saas-grid{ display:grid; gap:12px; grid-template-columns:repeat(12,minmax(0,1fr)); }
  .col-4{ grid-column:span 4; }
  .col-8{ grid-column:span 8; }
  .col-12{ grid-column:span 12; }
  .field label{ display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:var(--nb-muted); }
  .field .nb-field{ width:100%; padding:10px 12px; border-radius:12px; }
  .list{ display:flex; flex-direction:column; gap:10px; }
  .item{ border:1px solid var(--nb-border); border-radius:16px; padding:14px; background:var(--nb-surface); }
  .item-grid{ display:grid; gap:12px; grid-template-columns:2fr 1fr; align-items:start; }
  .meta{ font-size:12px; color:var(--nb-muted); }
  .value{ font-weight:500; word-break:break-word; overflow-wrap:anywhere; }
  .actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
  .empty{ padding:14px; border:1px solid var(--nb-border); border-radius:16px; background:var(--nb-surface); }
  @media(max-width:860px){ .item-grid{ grid-template-columns:1fr; } .actions{ justify-content:flex-start; } .col-4,.col-8{ grid-column:span 12; } }
</style>

<div class="saas-page">
  <div class="saas-card">
    <div class="saas-head">
      <div>
        <h1 class="saas-title">Vendor</h1>
        <div class="saas-sub">Data pemasok buku.</div>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="nb-btn" href="{{ route('acquisitions.requests.index') }}">Request</a>
        <a class="nb-btn nb-btn-primary" href="{{ route('acquisitions.vendors.create') }}">Tambah Vendor</a>
      </div>
    </div>

    <form method="GET" action="{{ route('acquisitions.vendors.index') }}" style="margin-top:12px;">
      <div class="saas-grid">
        <div class="field col-4">
          <label>Cari</label>
          <input class="nb-field" name="q" value="{{ $q }}" placeholder="Nama vendor">
        </div>
        <div class="col-8" style="display:flex; gap:10px; align-items:end;">
          <button class="nb-btn nb-btn-primary" type="submit">Cari</button>
          <a class="nb-btn" href="{{ route('acquisitions.vendors.index') }}">Reset</a>
        </div>
      </div>
    </form>
  </div>

  <div class="saas-card">
    @if(!$vendors || $vendors->count() === 0)
      <div class="empty">
        <div style="font-weight:600;">Belum ada vendor</div>
      </div>
    @else
      <div class="list">
        @foreach($vendors as $v)
          <div class="item">
            <div class="item-grid">
              <div>
                <div class="value" style="font-weight:600;">{{ $v->name }}</div>
                <div class="meta">ID: {{ $v->id }}</div>
                <div class="meta" style="margin-top:6px;">Catatan: {{ $v->notes ?: '-' }}</div>
              </div>
              <div class="actions">
                <a class="nb-btn" href="{{ route('acquisitions.vendors.edit', $v->id) }}">Edit</a>
                <form method="POST" action="{{ route('acquisitions.vendors.destroy', $v->id) }}">
                  @csrf
                  @method('DELETE')
                  <button class="nb-btn" type="submit" onclick="return confirm('Hapus vendor ini?');">Hapus</button>
                </form>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      <div style="height:10px;"></div>
      {{ $vendors->links() }}
    @endif
  </div>
</div>
@endsection
