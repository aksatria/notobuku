@extends('layouts.notobuku')

@section('title', 'Detail Request Pengadaan')

@section('content')
@php
  $request = $request ?? null;
  $vendors = $vendors ?? collect();
  $draftPos = $draftPos ?? collect();
  $audits = $audits ?? collect();
  $auditUsers = $auditUsers ?? collect();
  $statusTone = [
    'requested' => ['bg' => 'rgba(59,130,246,.12)', 'bd' => 'rgba(59,130,246,.28)', 'tx' => '#3b82f6'],
    'reviewed' => ['bg' => 'rgba(251,191,36,.12)', 'bd' => 'rgba(251,191,36,.28)', 'tx' => '#d97706'],
    'approved' => ['bg' => 'rgba(34,197,94,.12)', 'bd' => 'rgba(34,197,94,.28)', 'tx' => '#16a34a'],
    'rejected' => ['bg' => 'rgba(239,68,68,.12)', 'bd' => 'rgba(239,68,68,.28)', 'tx' => '#ef4444'],
    'converted_to_po' => ['bg' => 'rgba(168,85,247,.12)', 'bd' => 'rgba(168,85,247,.28)', 'tx' => '#8b5cf6'],
  ];
  $priorityTone = [
    'low' => ['bg' => 'rgba(148,163,184,.12)', 'bd' => 'rgba(148,163,184,.28)', 'tx' => '#64748b'],
    'normal' => ['bg' => 'rgba(59,130,246,.12)', 'bd' => 'rgba(59,130,246,.28)', 'tx' => '#3b82f6'],
    'high' => ['bg' => 'rgba(251,146,60,.12)', 'bd' => 'rgba(251,146,60,.28)', 'tx' => '#f59e0b'],
    'urgent' => ['bg' => 'rgba(239,68,68,.12)', 'bd' => 'rgba(239,68,68,.28)', 'tx' => '#ef4444'],
  ];
  $formatMeta = function ($meta) {
    if (!is_array($meta)) return [];
    $labels = [
      'request_id' => 'Request ID',
      'po_id' => 'PO ID',
      'po_line_id' => 'PO Line ID',
      'estimated_price' => 'Estimasi',
      'received_total' => 'Total Diterima',
      'reason' => 'Alasan',
      'priority' => 'Prioritas',
      'branch_id' => 'Cabang ID',
      'vendor_id' => 'Vendor ID',
      'source' => 'Sumber',
      'budget_warning' => 'Peringatan Budget',
    ];
    $rows = [];
    foreach ($meta as $k => $v) {
      $label = $labels[$k] ?? str_replace('_', ' ', (string) $k);
      if (is_array($v)) {
        $v = json_encode($v);
      }
      $rows[] = ['label' => $label, 'value' => (string) $v];
    }
    return $rows;
  };
@endphp

<style>
  .saas-page{ max-width:1100px; margin:0 auto; padding:0 10px 24px; display:flex; flex-direction:column; gap:14px; overflow-x:hidden; }
  .saas-card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; box-shadow:0 1px 0 rgba(17,24,39,.02); }
  .saas-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .saas-title{ font-weight:600; font-size:18px; margin:0; }
  .saas-sub{ font-size:12.5px; color:var(--nb-muted); margin-top:4px; }
  .saas-grid{ display:grid; gap:12px; grid-template-columns:repeat(2,minmax(0,1fr)); }\n  .span-2{ grid-column: span 2; }
  .saas-label{ font-size:12px; color:var(--nb-muted); font-weight:600; }
  .saas-value{ font-weight:500; word-break:break-word; overflow-wrap:anywhere; }
  .saas-badge{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; border:1px solid var(--nb-border); font-size:12px; font-weight:600; }
  .saas-field label{ display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:var(--nb-muted); }
  .saas-field .nb-field{ width:100%; padding:10px 12px; border-radius:12px; }
  .audit-item{ border:1px solid var(--nb-border); border-radius:14px; padding:12px; }
  @media(max-width:900px){ .saas-grid{ grid-template-columns:1fr; } .span-2{ grid-column: span 1; } }
</style>

<div class="saas-page">
  <div class="saas-card">
    <div class="saas-head">
      <div>
        <h1 class="saas-title">{{ $request->title }}</h1>
        <div class="saas-sub">Penulis: {{ $request->author_text ?: '-' }} • ISBN: {{ $request->isbn ?: '-' }}</div>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="nb-btn" href="{{ route('acquisitions.requests.index') }}">Kembali</a>
        @php $tone = $statusTone[$request->status] ?? ['bg'=>'rgba(148,163,184,.12)','bd'=>'rgba(148,163,184,.2)','tx'=>'#94a3b8']; @endphp
        <span class="saas-badge" style="background:{{ $tone['bg'] }}; border-color:{{ $tone['bd'] }}; color:{{ $tone['tx'] }};">
          {{ str_replace('_',' ', $request->status) }}
        </span>
      </div>
    </div>
  </div>

  <div class="saas-card">
    <div class="saas-grid">
      <div>
        <div class="saas-label">Prioritas</div>
        @php $pt = $priorityTone[$request->priority] ?? ['bg'=>'rgba(148,163,184,.12)','bd'=>'rgba(148,163,184,.2)','tx'=>'#94a3b8']; @endphp
        <span class="saas-badge" style="background:{{ $pt['bg'] }}; border-color:{{ $pt['bd'] }}; color:{{ $pt['tx'] }};">{{ $request->priority }}</span>
      </div>
      <div>
        <div class="saas-label">Estimasi Harga</div>
        <div class="saas-value">{{ $request->estimated_price !== null ? number_format((float)$request->estimated_price, 2) : '-' }}</div>
      </div>
      <div>
        <div class="saas-label">Cabang</div>
        <div class="saas-value">{{ $request->branch?->name ?? '-' }}</div>
      </div>
      <div>
        <div class="saas-label">Sumber</div>
        <div class="saas-value">{{ $request->source === 'member_request' ? 'Member Request' : 'Staff Manual' }}</div>
      </div>
      <div>
        <div class="saas-label">Requester</div>
        <div class="saas-value">{{ $request->requester?->name ?? '-' }}</div>
      </div>
      <div>
        <div class="saas-label">Dibuat</div>
        <div class="saas-value">{{ $request->created_at ? $request->created_at->format('Y-m-d H:i') : '-' }}</div>
      </div>
      @if($request->book_request_id)
        <div>
          <div class="saas-label">Book Request</div>
          <div class="saas-value">#{{ $request->book_request_id }}</div>
        </div>
      @endif
      @if($request->purchaseOrder)
        <div>
          <div class="saas-label">Purchase Order</div>
          <div class="saas-value"><a class="nb-btn" href="{{ route('acquisitions.pos.show', $request->purchaseOrder->id) }}">{{ $request->purchaseOrder->po_number }}</a></div>
        </div>
      @endif
      @if($request->purchase_order_line_id)
        <div>
          <div class="saas-label">PO Line ID</div>
          <div class="saas-value">{{ $request->purchase_order_line_id }}</div>
        </div>
      @endif
      <div class="span-2">
        <div class="saas-label">Catatan</div>
        <div class="saas-value">{{ $request->notes ?: '-' }}</div>
      </div>
      @if($request->reject_reason)
        <div class="span-2">
          <div class="saas-label">Alasan Reject</div>
          <div class="saas-value">{{ $request->reject_reason }}</div>
        </div>
      @endif
    </div>
  </div>

  <div class="saas-card">
    <div style="font-weight:600; margin-bottom:10px;">Aktivitas</div>
    <div class="saas-grid">
      <div>
        <div class="saas-label">Reviewed By</div>
        <div class="saas-value">{{ $request->reviewer?->name ?? 'Belum direview' }}</div>
        <div class="saas-sub">{{ $request->reviewed_at ? $request->reviewed_at->format('Y-m-d H:i') : '—' }}</div>
      </div>
      <div>
        <div class="saas-label">Approved By</div>
        <div class="saas-value">{{ $request->approver?->name ?? 'Belum di-approve' }}</div>
        <div class="saas-sub">{{ $request->approved_at ? $request->approved_at->format('Y-m-d H:i') : '—' }}</div>
      </div>
      <div>
        <div class="saas-label">Rejected By</div>
        <div class="saas-value">{{ $request->rejector?->name ?? 'Belum ditolak' }}</div>
        <div class="saas-sub">{{ $request->rejected_at ? $request->rejected_at->format('Y-m-d H:i') : '—' }}</div>
      </div>
    </div>
  </div>

  <div class="saas-card">
    <div style="font-weight:600; margin-bottom:10px;">Audit Log</div>
    @if($audits->count() === 0)
      <div class="saas-sub">Belum ada audit.</div>
    @else
      <div style="display:flex; flex-direction:column; gap:10px;">
        @foreach($audits as $a)
          @php $u = $auditUsers[$a->user_id] ?? null; @endphp
          <div class="audit-item">
            <div style="font-weight:600;">{{ $a->action }}</div>
            <div class="saas-sub">{{ $a->created_at ? $a->created_at->format('Y-m-d H:i') : '-' }} • {{ $u?->name ?? '-' }}</div>
            @if(!empty($a->meta))
              @php $rows = $formatMeta($a->meta); @endphp
              @if(!empty($rows))
                <div style="margin-top:8px; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:6px;">
                  @foreach($rows as $row)
                    <div class="saas-sub">
                      <span>{{ $row['label'] }}:</span>
                      <span style="color:var(--nb-text); font-weight:500;">{{ $row['value'] }}</span>
                    </div>
                  @endforeach
                </div>
              @endif
            @endif
          </div>
        @endforeach
      </div>
    @endif
  </div>

  @if($request->status === 'requested')
    <div class="saas-card">
      <div style="font-weight:600; margin-bottom:10px;">Review</div>
      <form method="POST" action="{{ route('acquisitions.requests.review', $request->id) }}">
        @csrf
        <div class="saas-grid">
          <div class="saas-field">
            <label>Estimasi Harga</label>
            <input class="nb-field" type="number" step="0.01" name="estimated_price">
          </div>
          <div class="saas-field">
            <label>Catatan</label>
            <input class="nb-field" name="notes">
          </div>
        </div>
        <div style="height:10px;"></div>
        <button class="nb-btn nb-btn-primary" type="submit">Tandai Reviewed</button>
      </form>
    </div>
  @endif

  @if(in_array($request->status, ['requested', 'reviewed'], true))
    <div class="saas-card">
      <div style="font-weight:600; margin-bottom:10px;">Approve / Reject</div>
      <div class="saas-grid">
        <form method="POST" action="{{ route('acquisitions.requests.approve', $request->id) }}">
          @csrf
          <div class="saas-field">
            <label>Estimasi Harga (opsional)</label>
            <input class="nb-field" type="number" step="0.01" name="estimated_price">
          </div>
          <div style="height:10px;"></div>
          <button class="nb-btn nb-btn-success" type="submit">Approve</button>
        </form>

        <form method="POST" action="{{ route('acquisitions.requests.reject', $request->id) }}">
          @csrf
          <div class="saas-field">
            <label>Alasan Reject</label>
            <input class="nb-field" name="reject_reason" required>
          </div>
          <div style="height:10px;"></div>
          <button class="nb-btn" type="submit" onclick="return confirm('Tolak request ini?');">Reject</button>
        </form>
      </div>
    </div>
  @endif

  @if($request->status === 'approved')
    <div class="saas-card">
      <div style="font-weight:600; margin-bottom:10px;">Convert ke PO</div>
      <form method="POST" action="{{ route('acquisitions.requests.convert', $request->id) }}">
        @csrf
        <div class="saas-grid">
          <div class="saas-field">
            <label>Pilih Draft PO (opsional)</label>
            <select class="nb-field" name="po_id">
              <option value="">- buat PO baru -</option>
              @foreach($draftPos as $po)
                <option value="{{ $po->id }}">{{ $po->po_number }}</option>
              @endforeach
            </select>
          </div>
          <div class="saas-field">
            <label>Vendor (untuk PO baru)</label>
            <select class="nb-field" name="vendor_id">
              <option value="">- pilih vendor -</option>
              @foreach($vendors as $v)
                <option value="{{ $v->id }}">{{ $v->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div style="height:10px;"></div>
        <button class="nb-btn nb-btn-primary" type="submit">Convert to PO</button>
      </form>
    </div>
  @endif
</div>
@endsection

