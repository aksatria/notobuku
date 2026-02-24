@extends('layouts.notobuku')

@section('title', 'Pengadaan - Permintaan')

@section('content')
@php
  $requests = $requests ?? null;
  $status = (string)($status ?? '');
  $q = (string)($q ?? '');
  $priority = (string)($priority ?? '');
  $source = (string)($source ?? '');
  $branchId = (string)($branchId ?? '');
  $unconvertedOnly = (string)($unconvertedOnly ?? '');
  $branches = $branches ?? collect();
  $vendors = $vendors ?? collect();
  $draftPos = $draftPos ?? collect();
  $statusCounts = $statusCounts ?? [];
  $statusTone = [
    'requested' => ['bg' => 'rgba(59,130,246,.10)', 'bd' => 'rgba(59,130,246,.28)', 'tx' => '#3b82f6'],
    'reviewed' => ['bg' => 'rgba(251,191,36,.12)', 'bd' => 'rgba(251,191,36,.28)', 'tx' => '#d97706'],
    'approved' => ['bg' => 'rgba(34,197,94,.12)', 'bd' => 'rgba(34,197,94,.28)', 'tx' => '#16a34a'],
    'rejected' => ['bg' => 'rgba(239,68,68,.12)', 'bd' => 'rgba(239,68,68,.28)', 'tx' => '#ef4444'],
    'converted_to_po' => ['bg' => 'rgba(168,85,247,.10)', 'bd' => 'rgba(168,85,247,.28)', 'tx' => '#8b5cf6'],
  ];
  $priorityTone = [
    'low' => ['bg' => 'rgba(148,163,184,.12)', 'bd' => 'rgba(148,163,184,.28)', 'tx' => '#64748b'],
    'normal' => ['bg' => 'rgba(59,130,246,.10)', 'bd' => 'rgba(59,130,246,.28)', 'tx' => '#3b82f6'],
    'high' => ['bg' => 'rgba(251,146,60,.12)', 'bd' => 'rgba(251,146,60,.28)', 'tx' => '#f59e0b'],
    'urgent' => ['bg' => 'rgba(239,68,68,.12)', 'bd' => 'rgba(239,68,68,.28)', 'tx' => '#ef4444'],
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
     .pill{ display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; border:1px solid var(--nb-border); font-size:12px; font-weight:500; }  }
  .list{ display:flex; flex-direction:column; gap:10px; }
  .item{ border:1px solid var(--nb-border); border-radius:16px; padding:14px; background:var(--nb-surface); }
  .item-grid{ display:grid; gap:12px; grid-template-columns:2.2fr 1fr 1fr 1fr 1fr 1fr 1fr; align-items:start; }
  .meta{ font-size:12px; color:var(--nb-muted); }
  .value{ font-weight:500; word-break:break-word; overflow-wrap:anywhere; }
  .badge{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; border:1px solid var(--nb-border); font-size:12px; font-weight:500; }
  .actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
  .empty{ padding:14px; border:1px solid var(--nb-border); border-radius:16px; background:var(--nb-surface); }
  @media(max-width:1100px){ .item-grid{ grid-template-columns:1fr 1fr; } .actions{ justify-content:flex-start; } }
  @media(max-width:720px){ .item-grid{ grid-template-columns:1fr; } .col-3,.col-4,.col-6{ grid-column:span 12; } }
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

  <div class="saas-card">
    <div class="saas-head">
      <div>
        <h1 class="saas-title">Permintaan Pengadaan</h1>
        <div class="saas-sub">Daftar permintaan pengadaan dari staf / anggota.</div>
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a class="nb-btn" href="{{ route('acquisitions.pos.index') }}">Lihat PO</a>
        <a class="nb-btn nb-btn-primary" href="{{ route('acquisitions.requests.create') }}">Buat Permintaan</a>
      </div>
    </div>

    <form method="GET" action="{{ route('acquisitions.requests.index') }}" style="margin-top:12px;">
      <div class="saas-grid">
        <div class="field col-3">
          <label>Prioritas</label>
          <select class="nb-field" name="priority">
            <option value="" {{ $priority==='' ? 'selected' : '' }}>Semua</option>
            @foreach(['low','normal','high','urgent'] as $p)
              <option value="{{ $p }}" {{ $priority===$p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
            @endforeach
          </select>
        </div>
        <div class="field col-3">
          <label>Status</label>
          <select class="nb-field" name="status">
            <option value="" {{ $status==='' ? 'selected' : '' }}>Semua</option>
            <option value="requested" {{ $status==='requested' ? 'selected' : '' }}>Diminta</option>
            <option value="reviewed" {{ $status==='reviewed' ? 'selected' : '' }}>Ditinjau</option>
            <option value="approved" {{ $status==='approved' ? 'selected' : '' }}>Disetujui</option>
            <option value="rejected" {{ $status==='rejected' ? 'selected' : '' }}>Ditolak</option>
            <option value="converted_to_po" {{ $status==='converted_to_po' ? 'selected' : '' }}>Dikonversi</option>
          </select>
        </div>
        <div class="field col-3">
          <label>Sumber</label>
          <select class="nb-field" name="source">
            <option value="" {{ $source==='' ? 'selected' : '' }}>Semua</option>
            <option value="staff_manual" {{ $source==='staff_manual' ? 'selected' : '' }}>Input Staf</option>
            <option value="member_request" {{ $source==='member_request' ? 'selected' : '' }}>Permintaan Anggota</option>
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
        <div class="field col-6">
          <label>Cari</label>
          <input class="nb-field" type="text" name="q" value="{{ $q }}" placeholder="Judul / penulis / ISBN">
        </div>
        <div class="field col-3">
          <label>Konversi</label>
          <label style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:12.5px;">
            <input type="checkbox" name="unconverted_only" value="1" {{ ($unconvertedOnly !== '' && $unconvertedOnly !== '0') ? 'checked' : '' }}>
            Hanya belum dikonversi
          </label>
        </div>
        <div class="col-3" style="display:flex; gap:10px; align-items:end;">
          <button class="nb-btn nb-btn-primary" type="submit">Terapkan</button>
          <a class="nb-btn" href="{{ route('acquisitions.requests.index') }}">Reset</a>
        </div>
      </div>
    </form>

    <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
      @foreach(['requested','reviewed','approved','rejected','converted_to_po'] as $st)
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
    <div style="font-weight:600; margin-bottom:8px;">Konversi Massal ke PO</div>
    <form method="POST" action="{{ route('acquisitions.requests.bulk_convert') }}" id="bulkConvertForm">
      @csrf
      <div class="saas-grid">
        <div class="field col-3">
          <label>Draft PO (opsional)</label>
          <select class="nb-field" name="po_id" id="bulkPoId">
            <option value="">- buat PO baru -</option>
            @foreach($draftPos as $po)
              <option value="{{ $po->id }}">{{ $po->po_number }}</option>
            @endforeach
          </select>
        </div>
        <div class="field col-3">
          <label>Vendor (untuk PO baru)</label>
          <select class="nb-field" name="vendor_id" id="bulkVendorId">
            <option value="">- pilih vendor -</option>
            @foreach($vendors as $v)
              <option value="{{ $v->id }}">{{ $v->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="field col-3">
          <label>Cabang (untuk PO baru)</label>
          <select class="nb-field" name="branch_id">
            <option value="">- umum -</option>
            @foreach($branches as $br)
              <option value="{{ $br->id }}">{{ $br->name }} {{ $br->code ? '(' . $br->code . ')' : '' }}</option>
            @endforeach
          </select>
        </div>
        <div class="field col-3">
          <label>Currency</label>
          <input class="nb-field" name="currency" value="IDR">
        </div>
        <div class="col-12" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
          <button class="nb-btn nb-btn-success" type="submit" id="bulkConvertBtn" disabled>Konversi Terpilih</button>
          <span class="meta">Hanya permintaan berstatus <b>disetujui</b> yang bisa dipilih.</span>
        </div>
      </div>
    </form>
  </div>

  <div class="saas-card">
    @if(!$requests || $requests->count() === 0)
      <div class="empty">
        <div style="font-weight:600;">Belum ada permintaan</div>
        <div class="saas-sub">Buat permintaan baru untuk memulai pengadaan.</div>
      </div>
    @else
      <div class="list">
        @foreach($requests as $r)
          @php
            $canSelect = $r->status === 'approved';
            $canQuick = in_array($r->status, ['requested','reviewed'], true);
          @endphp
          <div class="item">
            <div class="item-grid">
              <div>
                <div class="value">{{ $r->title }}</div>
                <div class="meta" style="margin-top:4px;">{{ $r->author_text ?: '-' }} • ISBN: {{ $r->isbn ?: '-' }}</div>
                <div style="margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                  @php $tone = $statusTone[$r->status] ?? ['bg'=>'rgba(148,163,184,.12)','bd'=>'rgba(148,163,184,.2)','tx'=>'#94a3b8']; @endphp
                  <span class="badge" style="background:{{ $tone['bg'] }}; border-color:{{ $tone['bd'] }}; color:{{ $tone['tx'] }};">
                    {{ str_replace('_',' ', $r->status) }}
                  </span>
                  @php $pt = $priorityTone[$r->priority] ?? ['bg'=>'rgba(148,163,184,.12)','bd'=>'rgba(148,163,184,.2)','tx'=>'#94a3b8']; @endphp
                  <span class="badge" style="background:{{ $pt['bg'] }}; border-color:{{ $pt['bd'] }}; color:{{ $pt['tx'] }};">
                    {{ $r->priority }}
                  </span>
                </div>
              </div>
              <div>
                <div class="meta">Cabang</div>
                <div class="value">{{ $r->branch?->name ?? '-' }}</div>
              </div>
              <div>
                <div class="meta">Peminta</div>
                <div class="value">{{ $r->requester?->name ?? '-' }}</div>
                <div class="meta">{{ $r->source === 'member_request' ? 'Anggota' : 'Staf' }}</div>
              </div>
              <div>
                <div class="meta">Estimasi</div>
                <div class="value">{{ $r->estimated_price !== null ? number_format((float)$r->estimated_price, 2) : '-' }}</div>
                @if($r->status !== 'converted_to_po')
                  <form method="POST" action="{{ route('acquisitions.requests.update_estimate', $r->id) }}" style="margin-top:6px; display:flex; gap:6px; align-items:center;">
                    @csrf
                    <input class="nb-field" type="number" step="0.01" name="estimated_price" value="{{ $r->estimated_price }}" style="max-width:140px;">
                    <button class="nb-btn" type="submit">Perbarui</button>
                  </form>
                @endif
              </div>
              <div>
                <div class="meta">Qty (bulk)</div>
                <input class="nb-field" type="number" form="bulkConvertForm" name="quantities[{{ $r->id }}]" value="1" min="1" style="max-width:90px;" {{ $canSelect ? '' : 'disabled' }}>
                <div style="margin-top:8px;">
                  <label style="display:flex; align-items:center; gap:6px; font-size:12px; color:var(--nb-muted);">
                    <input type="checkbox" class="bulk-item" form="bulkConvertForm" name="request_ids[]" value="{{ $r->id }}" {{ $canSelect ? '' : 'disabled' }}>
                    Pilih
                  </label>
                </div>
              </div>
              <div>
                <div class="meta">PO</div>
                @if($r->purchaseOrder)
                  <a class="nb-btn" href="{{ route('acquisitions.pos.show', $r->purchaseOrder->id) }}">{{ $r->purchaseOrder->po_number }}</a>
                @else
                  <div class="value">-</div>
                @endif
              </div>
              <div class="actions">
                <a class="nb-btn" href="{{ route('acquisitions.requests.show', $r->id) }}">Detail</a>
                @if($canQuick)
                  <form method="POST" action="{{ route('acquisitions.requests.approve', $r->id) }}">
                    @csrf
                    <button class="nb-btn nb-btn-success" type="submit">Setujui</button>
                  </form>
                  <form method="POST" action="{{ route('acquisitions.requests.reject', $r->id) }}" class="quick-reject">
                    @csrf
                    <input type="hidden" name="reject_reason" value="">
                    <button class="nb-btn" type="submit">Tolak</button>
                  </form>
                @endif
              </div>
            </div>
          </div>
        @endforeach
      </div>

      <div style="height:10px;"></div>
      {{ $requests->links() }}
    @endif
  </div>
</div>

<script>
  (function(){
    var items = document.querySelectorAll('.bulk-item');
    var btn = document.getElementById('bulkConvertBtn');
    items.forEach(function(cb){ cb.addEventListener('change', updateBtn); });
    function updateBtn(){
      var any = false;
      items.forEach(function(cb){ if(cb.checked) any = true; });
      if(btn) btn.disabled = !any;
    }

    document.querySelectorAll('.quick-reject').forEach(function(form){
      form.addEventListener('submit', function(e){
        var reason = prompt('Alasan penolakan?');
        if(!reason){ e.preventDefault(); return; }
        var input = form.querySelector('input[name="reject_reason"]');
        if(input) input.value = reason;
      });
    });

    var poSelect = document.getElementById('bulkPoId');
    var vendorSelect = document.getElementById('bulkVendorId');
    if(poSelect && vendorSelect){
      poSelect.addEventListener('change', function(){
        if(poSelect.value){ vendorSelect.value = ''; }
      });
    }
  })();
</script>
@endsection


