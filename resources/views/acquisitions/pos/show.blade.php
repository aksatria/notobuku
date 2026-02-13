@extends('layouts.notobuku')

@section('title', 'Detail PO')

@section('content')
@php
  $po = $po ?? null;
  $budget = $budget ?? null;
  $totalLines = $po?->lines?->count() ?? 0;
  $totalQty = $po?->lines?->sum('quantity') ?? 0;
  $receivedQty = $po?->lines?->sum('received_quantity') ?? 0;
  $remainingQty = max(0, $totalQty - $receivedQty);
  $progress = $totalQty > 0 ? round(($receivedQty / $totalQty) * 100) : 0;
  $statusTone = [
    'draft' => ['bg' => 'rgba(148,163,184,.12)', 'bd' => 'rgba(148,163,184,.28)', 'tx' => '#64748b'],
    'ordered' => ['bg' => 'rgba(59,130,246,.12)', 'bd' => 'rgba(59,130,246,.28)', 'tx' => '#3b82f6'],
    'partially_received' => ['bg' => 'rgba(251,146,60,.12)', 'bd' => 'rgba(251,146,60,.28)', 'tx' => '#f59e0b'],
    'received' => ['bg' => 'rgba(34,197,94,.12)', 'bd' => 'rgba(34,197,94,.28)', 'tx' => '#16a34a'],
    'cancelled' => ['bg' => 'rgba(239,68,68,.12)', 'bd' => 'rgba(239,68,68,.28)', 'tx' => '#ef4444'],
  ];
@endphp

<style>
  .saas-page{ max-width:1100px; margin:0 auto; padding:0 10px 24px; display:flex; flex-direction:column; gap:14px; overflow-x:hidden; }
  .saas-card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; box-shadow:0 1px 0 rgba(17,24,39,.02); }
  .saas-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .saas-title{ font-weight:600; font-size:18px; margin:0; }
  .saas-sub{ font-size:12.5px; color:var(--nb-muted); margin-top:4px; }
  .saas-grid{ display:grid; gap:12px; grid-template-columns:repeat(2,minmax(0,1fr)); }
  .saas-label{ font-size:12px; color:var(--nb-muted); font-weight:600; }
  .saas-value{ font-weight:500; word-break:break-word; overflow-wrap:anywhere; }
  .saas-badge{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; border:1px solid var(--nb-border); font-size:12px; font-weight:600; }
  .pill{ display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:14px; border:1px solid var(--nb-border); background:var(--nb-surface); font-size:12px; font-weight:600; }
  .progress{ height:8px; background:rgba(148,163,184,.2); border-radius:999px; overflow:hidden; }
  .progress > span{ display:block; height:100%; background:linear-gradient(90deg, #1e88e5, #22c55e); }
  .list{ display:flex; flex-direction:column; gap:10px; }
  .line-card{ border:1px solid var(--nb-border); border-radius:14px; padding:12px; }
  .line-grid{ display:grid; gap:10px; grid-template-columns:2fr 1fr 1fr; }
  .line-meta{ font-size:12px; color:var(--nb-muted); }
  .field label{ display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:var(--nb-muted); }
  .field .nb-field{ width:100%; padding:10px 12px; border-radius:12px; }
  @media(max-width:900px){ .saas-grid{ grid-template-columns:1fr; } .line-grid{ grid-template-columns:1fr; } }
</style>

<div class="saas-page">
  @if(session('success'))
    <div class="saas-card" style="border-color:rgba(34,197,94,.35); background:rgba(34,197,94,.10);">
      <div style="font-weight:600; font-size:13px;">Sukses</div>
      <div class="saas-sub">{{ session('success') }}</div>
    </div>
  @endif
  @if(session('error'))
    <div class="saas-card" style="border-color:rgba(239,68,68,.35); background:rgba(239,68,68,.10);">
      <div style="font-weight:600; font-size:13px;">Gagal</div>
      <div class="saas-sub">{{ session('error') }}</div>
    </div>
  @endif
  @if(session('warning'))
    <div class="saas-card" style="border-color:rgba(251,146,60,.35); background:rgba(251,146,60,.10);">
      <div style="font-weight:600; font-size:13px;">Peringatan Budget</div>
      <div class="saas-sub">{{ session('warning') }}</div>
    </div>
  @endif

  <div class="saas-card">
    <div class="saas-head">
      <div>
        <h1 class="saas-title">{{ $po->po_number }}</h1>
        <div class="saas-sub">Vendor: {{ $po->vendor?->name ?? '-' }} • Cabang: {{ $po->branch?->name ?? '-' }}</div>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="nb-btn" href="{{ route('acquisitions.pos.index') }}">Kembali</a>
        @php $tone = $statusTone[$po->status] ?? ['bg'=>'rgba(148,163,184,.12)','bd'=>'rgba(148,163,184,.2)','tx'=>'#94a3b8']; @endphp
        <span class="saas-badge" style="background:{{ $tone['bg'] }}; border-color:{{ $tone['bd'] }}; color:{{ $tone['tx'] }};">
          {{ str_replace('_',' ', $po->status) }}
        </span>
      </div>
    </div>
  </div>

  <div class="saas-card">
    <div class="saas-grid">
      <div>
        <div class="saas-label">Currency</div>
        <div class="saas-value">{{ $po->currency }}</div>
      </div>
      <div>
        <div class="saas-label">Total</div>
        <div class="saas-value">{{ number_format((float)$po->total_amount, 2) }}</div>
      </div>
      <div>
        <div class="saas-label">Ordered At</div>
        <div class="saas-value">{{ $po->ordered_at ?: '-' }}</div>
      </div>
      <div>
        <div class="saas-label">Received At</div>
        <div class="saas-value">{{ $po->received_at ?: '-' }}</div>
      </div>
    </div>
    <div style="height:10px;"></div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
      <div class="pill">Lines: {{ $totalLines }}</div>
      <div class="pill">Qty Total: {{ $totalQty }}</div>
      <div class="pill">Received: {{ $receivedQty }}</div>
      <div class="pill">Remaining: {{ $remainingQty }}</div>
      <div class="pill" style="flex:1; min-width:200px;">
        <span>Progress {{ $progress }}%</span>
        <div class="progress" style="flex:1;"><span style="width:{{ $progress }}%"></span></div>
      </div>
    </div>
  </div>

  @if($budget)
    @php $budgetRemaining = (float)$budget->amount - (float)$budget->spent; @endphp
    <div class="saas-card">
      <div style="font-weight:600; margin-bottom:8px;">Budget Tahun {{ $budget->year }}</div>
      <div class="saas-grid">
        <div>
          <div class="saas-label">Total Budget</div>
          <div class="saas-value">{{ number_format((float)$budget->amount, 2) }}</div>
        </div>
        <div>
          <div class="saas-label">Terpakai</div>
          <div class="saas-value">{{ number_format((float)$budget->spent, 2) }}</div>
        </div>
        <div>
          <div class="saas-label">Sisa</div>
          <div class="saas-value">{{ number_format((float)$budgetRemaining, 2) }}</div>
        </div>
      </div>
    </div>
  @endif

  <div class="saas-card">
    <div style="font-weight:600; margin-bottom:10px;">Lines</div>
    @if($po->lines->count() === 0)
      <div class="saas-sub">Belum ada line.</div>
    @else
      <div class="list">
        @foreach($po->lines as $line)
          @php $lineRemaining = max(0, (int)$line->quantity - (int)$line->received_quantity); @endphp
          <div class="line-card">
            <div class="line-grid">
              <div>
                <div style="font-weight:600;">{{ $line->title }}</div>
                <div class="line-meta" style="margin-top:4px;">{{ $line->author_text ?: '-' }} • ISBN: {{ $line->isbn ?: '-' }}</div>
              </div>
              <div>
                <div class="line-meta">Qty</div>
                <div class="saas-value">{{ $line->quantity }}</div>
                <div class="line-meta" style="margin-top:6px;">Received</div>
                <div class="saas-value">{{ $line->received_quantity }}</div>
              </div>
              <div>
                <div class="line-meta">Sisa</div>
                <div class="saas-value">{{ $lineRemaining }}</div>
                <div class="line-meta" style="margin-top:6px;">Harga</div>
                <div class="saas-value">{{ number_format((float)$line->unit_price, 2) }}</div>
                <div class="line-meta" style="margin-top:6px;">Total</div>
                <div class="saas-value">{{ number_format((float)$line->line_total, 2) }}</div>
                <div style="margin-top:8px;">
                  <span class="saas-badge">{{ $line->status }}</span>
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  <div class="saas-card">
    <div style="font-weight:600; margin-bottom:10px;">Riwayat Goods Receipt</div>
    @if($po->receipts->count() === 0)
      <div class="saas-sub">Belum ada penerimaan.</div>
    @else
      <div class="list">
        @foreach($po->receipts as $rc)
          <div class="line-card">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
              <div style="font-weight:600;">GR #{{ $rc->id }}</div>
              <div class="saas-sub">{{ $rc->received_at ? $rc->received_at->format('Y-m-d H:i') : '-' }}</div>
            </div>
            <div class="saas-sub" style="margin-top:4px;">Diterima oleh: {{ $rc->receiver?->name ?? '-' }}</div>
            @if($rc->notes)
              <div style="margin-top:6px;">{{ $rc->notes }}</div>
            @endif
            <div style="height:8px;"></div>
            <div class="list">
              @foreach($rc->lines as $ln)
                <div class="line-card" style="padding:10px;">
                  <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                    <div class="saas-value">{{ $ln->purchaseOrderLine?->title ?? '-' }}</div>
                    <div class="saas-sub">Qty: <span style="color:var(--nb-text); font-weight:500;">{{ $ln->quantity_received }}</span></div>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  @if($po->status === 'draft')
    <div class="saas-card">
      <div style="font-weight:600; margin-bottom:10px;">Tambah Line</div>
      <form method="POST" action="{{ route('acquisitions.pos.add_line', $po->id) }}">
        @csrf
        <div class="saas-grid">
          <div class="field" style="grid-column: span 2;">
            <label>Judul</label>
            <input class="nb-field" name="title" required>
          </div>
          <div class="field">
            <label>Penulis</label>
            <input class="nb-field" name="author_text">
          </div>
          <div class="field">
            <label>ISBN</label>
            <input class="nb-field" name="isbn">
          </div>
          <div class="field">
            <label>Qty</label>
            <input class="nb-field" type="number" name="quantity" value="1" min="1">
          </div>
          <div class="field">
            <label>Harga Satuan</label>
            <input class="nb-field" type="number" step="0.01" name="unit_price" value="0">
          </div>
        </div>
        <div style="height:10px;"></div>
        <button class="nb-btn nb-btn-primary" type="submit">Tambah Line</button>
      </form>
    </div>

    <form method="POST" action="{{ route('acquisitions.pos.order', $po->id) }}">
      @csrf
      <button class="nb-btn nb-btn-success" type="submit">Order PO</button>
    </form>
  @endif

  @if(in_array($po->status, ['ordered', 'partially_received'], true))
    <div class="saas-card">
      <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
        <div style="font-weight:600;">Penerimaan Barang</div>
        <button class="nb-btn" type="button" data-fill-receive>Isi semua sisa</button>
      </div>
      <form method="POST" action="{{ route('acquisitions.pos.receive', $po->id) }}">
        @csrf
        <div class="saas-grid">
          <div class="field">
            <label>Tanggal Terima</label>
            <input class="nb-field" type="date" name="received_at" value="{{ now()->format('Y-m-d') }}">
          </div>
          <div class="field">
            <label>Catatan</label>
            <input class="nb-field" name="notes">
          </div>
        </div>
        <div style="height:10px;"></div>

        @foreach($po->lines as $line)
          @php $lineRemaining = max(0, (int)$line->quantity - (int)$line->received_quantity); @endphp
          <div class="field" style="margin-bottom:8px;">
            <label>{{ $line->title }} (sisa {{ $lineRemaining }})</label>
            <input class="nb-field" type="number" name="lines[{{ $loop->index }}][quantity_received]" min="0" max="{{ $lineRemaining }}" value="0" data-receive-input data-max="{{ $lineRemaining }}">
            <input type="hidden" name="lines[{{ $loop->index }}][line_id]" value="{{ $line->id }}">
          </div>
        @endforeach

        <button class="nb-btn nb-btn-primary" type="submit">Simpan Penerimaan</button>
      </form>
    </div>
  @endif

  @if(!in_array($po->status, ['cancelled', 'received'], true))
    <form method="POST" action="{{ route('acquisitions.pos.cancel', $po->id) }}">
      @csrf
      <button class="nb-btn" type="submit" onclick="return confirm('Batalkan PO ini?');">Batalkan PO</button>
    </form>
  @endif
</div>

<script>
  (function(){
    var btn = document.querySelector('[data-fill-receive]');
    if(!btn) return;
    btn.addEventListener('click', function(){
      document.querySelectorAll('[data-receive-input]').forEach(function(input){
        var max = parseInt(input.getAttribute('data-max') || '0', 10);
        if(!isNaN(max) && max > 0) input.value = String(max);
      });
    });
  })();
</script>
@endsection
