@extends('layouts.notobuku')

@section('title', 'Pesanan Pembelian')

@section('content')
@php
  $pos = $pos ?? null;
  $status = (string)($status ?? '');
  $q = (string)($q ?? '');
  $vendorId = (string)($vendorId ?? '');
  $branchId = (string)($branchId ?? '');
  $vendors = $vendors ?? collect();
  $branches = $branches ?? collect();
  $statusCounts = $statusCounts ?? [];
  $statusTone = [
    'draft' => ['bg' => 'rgba(148,163,184,.10)', 'bd' => 'rgba(148,163,184,.28)', 'tx' => '#64748b'],
    'ordered' => ['bg' => 'rgba(59,130,246,.10)', 'bd' => 'rgba(59,130,246,.28)', 'tx' => '#3b82f6'],
    'partially_received' => ['bg' => 'rgba(251,146,60,.12)', 'bd' => 'rgba(251,146,60,.28)', 'tx' => '#f59e0b'],
    'received' => ['bg' => 'rgba(34,197,94,.12)', 'bd' => 'rgba(34,197,94,.28)', 'tx' => '#16a34a'],
    'cancelled' => ['bg' => 'rgba(239,68,68,.12)', 'bd' => 'rgba(239,68,68,.28)', 'tx' => '#ef4444'],
  ];
@endphp

<style>
  .saas-page{ max-width:1240px; margin:0 auto; padding:0 10px 24px; display:flex; flex-direction:column; gap:16px; overflow-x:hidden; }
  .saas-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .saas-title{ font-weight:600; font-size:18px; margin:0; letter-spacing:.2px; }
  .saas-sub{ font-size:13px; margin-top:6px; color:var(--nb-muted); }
  .saas-card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; box-shadow:0 1px 0 rgba(17,24,39,.02); }
  .saas-grid{ display:grid; gap:12px; grid-template-columns:repeat(12,minmax(0,1fr)); }
  .col-3{ grid-column:span 3; }
  .col-4{ grid-column:span 4; }
  .col-6{ grid-column:span 6; }
  .col-12{ grid-column:span 12; }
  .field label{ display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:var(--nb-muted); }
  .field .nb-field{ width:100%; padding:10px 12px; border-radius:12px; }
  .pill{ display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; border:1px solid var(--nb-border); font-size:12px; font-weight:500; }
  .list{ display:flex; flex-direction:column; gap:10px; }
  .item{ border:1px solid var(--nb-border); border-radius:16px; padding:14px; background:var(--nb-surface); }
  .item-grid{ display:grid; gap:12px; grid-template-columns:2fr 1fr 1fr 1fr 1fr; align-items:start; }
  .meta{ font-size:12px; color:var(--nb-muted); }
  .value{ font-weight:500; word-break:break-word; overflow-wrap:anywhere; }
  .badge{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; border:1px solid var(--nb-border); font-size:12px; font-weight:500; }
  .actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
  .empty{ padding:14px; border:1px solid var(--nb-border); border-radius:16px; background:var(--nb-surface); }
  @media(max-width:1100px){ .item-grid{ grid-template-columns:1fr 1fr; } .actions{ justify-content:flex-start; } }
  @media(max-width:720px){ .item-grid{ grid-template-columns:1fr; } .col-3,.col-4,.col-6{ grid-column:span 12; } }
</style>

<div class="saas-page">
  <div class="saas-card">
    <div class="saas-head">
      <div>
        <h1 class="saas-title">Pesanan Pembelian</h1>
        <div class="saas-sub">Kelola draft hingga penerimaan barang.</div>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="nb-btn" href="{{ route('acquisitions.requests.index') }}">Permintaan</a>
        <a class="nb-btn nb-btn-primary" href="{{ route('acquisitions.pos.create') }}">Buat PO</a>
      </div>
    </div>

    <form method="GET" action="{{ route('acquisitions.pos.index') }}" style="margin-top:12px;">
      <div class="saas-grid">
        <div class="field col-3">
          <label>Status</label>
          <select class="nb-field" name="status">
            <option value="" {{ $status==='' ? 'selected' : '' }}>Semua</option>
            <option value="draft" {{ $status==='draft' ? 'selected' : '' }}>Draft</option>
            <option value="ordered" {{ $status==='ordered' ? 'selected' : '' }}>Dipesan</option>
            <option value="partially_received" {{ $status==='partially_received' ? 'selected' : '' }}>Diterima Sebagian</option>
            <option value="received" {{ $status==='received' ? 'selected' : '' }}>Diterima</option>
            <option value="cancelled" {{ $status==='cancelled' ? 'selected' : '' }}>Dibatalkan</option>
          </select>
        </div>
        <div class="field col-3">
          <label>Vendor</label>
          <select class="nb-field" name="vendor_id">
            <option value="" {{ $vendorId==='' ? 'selected' : '' }}>Semua</option>
            @foreach($vendors as $v)
              <option value="{{ $v->id }}" {{ (string)$vendorId===(string)$v->id ? 'selected' : '' }}>{{ $v->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="field col-3">
          <label>Cabang</label>
          <select class="nb-field" name="branch_id">
            <option value="" {{ $branchId==='' ? 'selected' : '' }}>Semua</option>
            @foreach($branches as $br)
              <option value="{{ $br->id }}" {{ (string)$branchId===(string)$br->id ? 'selected' : '' }}>
                {{ $br->name }} {{ $br->code ? '(' . $br->code . ')' : '' }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="field col-3">
          <label>Cari</label>
          <input class="nb-field" name="q" value="{{ $q }}" placeholder="Nomor PO">
        </div>
        <div class="col-12" style="display:flex; gap:10px; align-items:end;">
          <button class="nb-btn nb-btn-primary" type="submit">Terapkan</button>
          <a class="nb-btn" href="{{ route('acquisitions.pos.index') }}">Reset</a>
        </div>
      </div>
    </form>

    <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
      @foreach(['draft','ordered','partially_received','received','cancelled'] as $st)
        @php
          $tone = $statusTone[$st] ?? ['bg'=>'rgba(148,163,184,.12)','bd'=>'rgba(148,163,184,.2)','tx'=>'#94a3b8'];
          $cnt = (int)($statusCounts[$st] ?? 0);
        @endphp
        <div class="pill" style="background:{{ $tone['bg'] }}; border-color:{{ $tone['bd'] }}; color:{{ $tone['tx'] }};">
          <span style="font-weight:600;">{{ $cnt }}</span>
          <span style="text-transform:capitalize;">{{ str_replace('_',' ', $st) }}</span>
        </div>
      @endforeach
    </div>
  </div>

  <div class="saas-card">
    @if(!$pos || $pos->count() === 0)
      <div class="empty">
        <div style="font-weight:600;">Belum ada PO</div>
      </div>
    @else
      <div class="list">
        @foreach($pos as $po)
          @php $tone = $statusTone[$po->status] ?? ['bg'=>'rgba(148,163,184,.12)','bd'=>'rgba(148,163,184,.2)','tx'=>'#94a3b8']; @endphp
          <div class="item">
            <div class="item-grid">
              <div>
                <div class="value">{{ $po->po_number }}</div>
                <div class="meta" style="margin-top:4px;">{{ $po->created_at }}</div>
              </div>
              <div>
                <div class="meta">Vendor</div>
                <div class="value">{{ $po->vendor?->name ?? '-' }}</div>
              </div>
              <div>
                <div class="meta">Cabang</div>
                <div class="value">{{ $po->branch?->name ?? '-' }}</div>
              </div>
              <div>
                <div class="meta">Status</div>
                <span class="badge" style="background:{{ $tone['bg'] }}; border-color:{{ $tone['bd'] }}; color:{{ $tone['tx'] }};">
                  {{ str_replace('_',' ', $po->status) }}
                </span>
              </div>
              <div>
                <div class="meta">Total</div>
                <div class="value">{{ $po->currency }} {{ number_format((float)$po->total_amount, 2) }}</div>
                <div class="actions" style="margin-top:10px;">
                  <a class="nb-btn" href="{{ route('acquisitions.pos.show', $po->id) }}">Detail</a>
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      <div style="height:10px;"></div>
      {{ $pos->links() }}
    @endif
  </div>
</div>
@endsection


