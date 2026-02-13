{{-- resources/views/transaksi/detail.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Detail Transaksi • NOTOBUKU')

@section('content')
@php
  $loan = $loan ?? null;
  $items = $items ?? collect();
  $fines = $fines ?? collect();

  $idr = function($n){
    $n = (int)($n ?? 0);
    return number_format($n, 0, ',', '.');
  };
  $maxRenewals = (int)config('notobuku.loans.max_renewals', 2);
  if ($maxRenewals <= 0) $maxRenewals = 2;
@endphp

<div class="nb-container" style="max-width:1100px;">
  {{-- HEADER --}}
  <div class="nb-card dt-card">
    <div class="dt-head">
      <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:14px; flex-wrap:wrap;">
        <div>
          <div class="dt-title">Detail Transaksi</div>
          <div class="dt-sub">
            Kode: <span class="dt-mono">{{ $loan->loan_code ?? '-' }}</span>
            • Member: <strong>{{ $loan->member_name ?? '-' }}</strong>
            • Cabang: <strong>{{ $loan->branch_name ?? '-' }}</strong>
          </div>
        </div>

        <div class="dt-head-actions" style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end;">
          <a href="{{ route('transaksi.riwayat') }}" class="nb-btn">Kembali</a>

          {{-- PRINT (route valid di project: transaksi.riwayat.print, pakai ?size=58|80) --}}
          @if(isset($loan->id) && \Illuminate\Support\Facades\Route::has('transaksi.riwayat.print'))
            <a href="{{ route('transaksi.riwayat.print', ['id' => $loan->id, 'size' => 58]) }}"
               class="nb-btn" target="_blank" rel="noopener">
              Print 58mm
            </a>
            <a href="{{ route('transaksi.riwayat.print', ['id' => $loan->id, 'size' => 80]) }}"
               class="nb-btn" target="_blank" rel="noopener">
              Print 80mm
            </a>
          @endif
        </div>
      </div>

      <div style="height:12px;"></div>

      <div class="dt-meta" style="display:flex; gap:10px; flex-wrap:wrap;">
        <div class="dt-chip">
          Dipinjam:
          <strong>
            {{ !empty($loan->loaned_at) ? \Carbon\Carbon::parse($loan->loaned_at)->format('d M Y H:i') : '-' }}
          </strong>
        </div>
        <div class="dt-chip">
          Jatuh tempo:
          <strong>
            {{ !empty($loan->due_at) ? \Carbon\Carbon::parse($loan->due_at)->format('d M Y H:i') : '-' }}
          </strong>
        </div>
        <div class="dt-chip">
          Status:
          <strong>{{ ucfirst($loan->status ?? '-') }}</strong>
        </div>
      </div>
    </div>
  </div>

  <div style="height:12px;"></div>

  {{-- DAFTAR ITEM --}}
  <div class="nb-card dt-card" style="padding:14px;">
    <div class="dt-sec-head">
      <div>
        <div class="dt-sec-title">Daftar Item</div>
        <div class="dt-sec-sub">Informasi item + tanggal jatuh tempo/kembali ditampilkan di sini.</div>
      </div>
    </div>

    <hr class="dt-divider">

    <div class="dt-tableWrap">
      <table class="dt-table">
        <thead>
          <tr>
            <th style="width:72px; text-align:center;">No</th>
            <th style="min-width:260px;">Barcode / Judul</th>
            <th style="width:180px;">Jatuh Tempo</th>
            <th style="width:180px;">Kembali</th>
            <th style="width:140px;">Perpanjang</th>
            <th style="width:140px;">Kondisi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($items as $it)
            @php
              $barcode = $it->barcode ?? '-';
              $title = $it->title ?? '-';
              $dueAt = $it->item_due_at ?? null;
              $returnedAt = $it->returned_at ?? null;
              $cond = $it->condition ?? '-';
              $renewCount = (int)($it->renew_count ?? 0);
              $dueDate = $dueAt ? \Illuminate\Support\Carbon::parse($dueAt) : null;
              $returnDate = $returnedAt ? \Illuminate\Support\Carbon::parse($returnedAt) : null;
              $isOverdue = $dueDate && !$returnDate && $dueDate->lt(\Illuminate\Support\Carbon::now()->startOfDay());
            @endphp
            <tr class="{{ $isOverdue ? 'is-overdue' : '' }}">
              <td style="text-align:center; font-weight:700;">{{ $loop->iteration }}</td>
              <td>
                <div class="dt-mono">{{ $barcode }}</div>
                <div class="dt-subline">{{ $title }}</div>
              </td>
              <td style="white-space:nowrap;">
                {{ $dueDate ? $dueDate->format('d M Y') : '-' }}
                @if($isOverdue)
                  <span class="dt-chip bad" style="margin-left:6px;">Overdue</span>
                @endif
              </td>
              <td style="white-space:nowrap;">
                {{ $returnDate ? $returnDate->format('d M Y') : '-' }}
              </td>
              <td style="white-space:nowrap;">
                <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;
                             color:{{ $renewCount >= max(0, $maxRenewals - 1) ? '#ef6c00' : 'rgba(11,37,69,.92)' }};
                             background:{{ $renewCount >= max(0, $maxRenewals - 1) ? 'rgba(251,140,0,.14)' : 'rgba(107,114,128,.12)' }};
                             border:1px solid {{ $renewCount >= max(0, $maxRenewals - 1) ? 'rgba(251,140,0,.35)' : 'rgba(107,114,128,.25)' }};">
                  {{ $renewCount }}/{{ $maxRenewals }}
                </span>
              </td>
              <td style="white-space:nowrap;">
                {{ $cond }}
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="nb-muted" style="padding:12px; font-weight:500;">Tidak ada item.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div style="height:12px;"></div>

  {{-- RINGKASAN DENDA --}}
  <div class="nb-card dt-card" style="padding:14px;">
    <div class="dt-sec-head">
      <div>
        <div class="dt-sec-title">Ringkasan Denda</div>
        <div class="dt-sec-sub">Kolom item & tanggal dihapus karena sudah tampil di Daftar Item.</div>
      </div>

      <div class="dt-sec-tools">
        <label class="dt-toggle">
          <input type="checkbox" id="onlyLate">
          <span>Hanya yang telat</span>
        </label>

        <label class="dt-toggle">
          <input type="checkbox" id="fastMode">
          <span>Mode cepat</span>
        </label>

        <button type="button" class="nb-btn" id="btnHitungSemua" style="border-radius:14px; font-weight:600;">
          Refresh Hitung
        </button>
      </div>
    </div>

    <hr class="dt-divider">

    {{-- KPI --}}
    <div class="dt-kpi">
      <div class="dt-kpi-box">
        <div class="k">Item Telat</div>
        <div class="v"><span id="kpiLateCount">0</span></div>
      </div>
      <div class="dt-kpi-box warn">
        <div class="k">Belum Dibayar</div>
        <div class="v">Rp <span id="kpiUnpaid">0</span></div>
      </div>
      <div class="dt-kpi-box ok">
        <div class="k">Sudah Dibayar</div>
        <div class="v">Rp <span id="kpiPaid">0</span></div>
      </div>
      <div class="dt-kpi-box">
        <div class="k">Total Aktif</div>
        <div class="v">Rp <span id="kpiTotal">0</span></div>
      </div>
    </div>

    <div class="dt-tableWrap" style="margin-top:12px;">
      <table class="dt-table dt-fine-table">
        <thead>
          <tr>
            <th style="width:72px; text-align:center;">No</th>
            <th style="width:320px;">Denda</th>
            <th style="width:260px;">Status</th>
            <th style="width:220px; text-align:right;">Aksi</th>
          </tr>
        </thead>

        <tbody>
          @forelse($items as $it)
            @php
              $loanItemId = (int)($it->loan_item_id ?? 0);
              $barcode = $it->barcode ?? '-';
            @endphp

            <tr class="fine-row" data-fine-row="1"
                data-loan-item-id="{{ $loanItemId }}"
                data-barcode="{{ $barcode }}"
                data-amount="0"
                data-status="unpaid"
                data-paid-amount="0"
                data-paid-at=""
                data-notes="">

              <td style="text-align:center; font-weight:700;">
                {{ $loop->iteration }}
              </td>

              {{-- Denda --}}
              <td>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                  <span class="dt-chip fine-lateChip">
                    <span class="fine-days">-</span> hari
                  </span>
                  <span class="dt-subline" style="margin-top:0;">
                    Tarif Rp <span class="fine-rate">-</span>/hari
                  </span>
                </div>
                <div class="dt-amount">
                  Total: Rp <span class="fine-amount">-</span>
                </div>
              </td>

              {{-- Status --}}
              <td>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                  <span class="dt-chip fine-status" style="min-width:110px; justify-content:center;">—</span>
                  <span class="dt-subline fine-paid" style="margin-top:0;">—</span>
                </div>
                <div class="dt-note fine-note">—</div>
              </td>

              {{-- Aksi --}}
              <td style="text-align:right;">
                <div class="dt-actions">
                  <button type="button" class="dt-btn" onclick="recalcRow(this.closest('.fine-row'))">
                    Hitung
                  </button>
                  <button type="button" class="dt-btn primary js-fine-pay" onclick="openPayDrawer(this.closest('.fine-row'))" disabled>
                    Bayar
                  </button>
                  <button type="button" class="dt-btn danger js-fine-void" onclick="openVoidDrawer(this.closest('.fine-row'))" disabled>
                    Void
                  </button>
                </div>
                <div class="dt-subline fine-hint" style="margin-top:6px;">—</div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="nb-muted" style="padding:12px; font-weight:500;">Tidak ada item.</td>
            </tr>
          @endforelse
        </tbody>

        <tfoot>
          <tr>
            <td colspan="4" style="padding:12px 10px;">
              <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div class="nb-muted" style="font-weight:600;">
                  Total Ringkasan Denda
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
                  <span class="dt-chip ok" title="Total denda yang sudah dibayar">
                    Lunas: Rp <span id="ftPaid">0</span>
                  </span>
                  <span class="dt-chip bad" title="Total denda yang belum dibayar">
                    Belum Dibayar: Rp <span id="ftUnpaid">0</span>
                  </span>
                  <span class="dt-chip" title="Total denda aktif (paid + unpaid, tidak termasuk void)">
                    Total Aktif: Rp <span id="ftTotal">0</span>
                  </span>
                </div>
              </div>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  {{-- DRAWERS --}}
  <div id="drawerBackdrop" class="dt-backdrop" onclick="closeDrawers()" style="display:none;"></div>

  {{-- Drawer Bayar --}}
  <div id="drawerPay" class="dt-drawer" style="display:none;">
    <div class="dt-drawer-head">
      <div>
        <div class="dt-drawer-title">Bayar Denda</div>
        <div class="dt-drawer-sub" id="payMeta">—</div>
      </div>
      <button type="button" class="dt-x" onclick="closeDrawers()">×</button>
    </div>
    <div class="dt-drawer-body">
      <form id="formPay" method="POST" action="{{ route('transaksi.denda.bayar') }}">
        @csrf
        <input type="hidden" name="loan_item_id" id="payLoanItemId" value="">
        <div class="dt-field">
          <label>Jumlah bayar</label>
          <input type="number" name="paid_amount" id="payAmount" class="dt-input" min="0" step="1" placeholder="Masukkan nominal…">
          <div class="dt-help">Boleh bayar sebagian. Jika dikosongkan, sistem akan membayar *sisa* denda untuk item ini.</div>
        </div>
        <div class="dt-field">
          <label>Catatan (opsional)</label>
          <textarea name="notes" id="payNotes" class="dt-input" rows="3" placeholder="Contoh: bayar via cash"></textarea>
        </div>
        <button type="submit" class="nb-btn is-primary" style="width:100%; border-radius:14px;">Simpan Pembayaran</button>
      </form>
    </div>
  </div>

  {{-- Drawer Void --}}
  <div id="drawerVoid" class="dt-drawer" style="display:none;">
    <div class="dt-drawer-head">
      <div>
        <div class="dt-drawer-title">Void Denda</div>
        <div class="dt-drawer-sub" id="voidMeta">—</div>
      </div>
      <button type="button" class="dt-x" onclick="closeDrawers()">×</button>
    </div>
    <div class="dt-drawer-body">
      <form id="formVoid" method="POST" action="{{ route('transaksi.denda.void') }}">
        @csrf
        <input type="hidden" name="loan_item_id" id="voidLoanItemId" value="">
        <div class="dt-field">
          <label>Alasan void</label>
          <textarea name="notes" id="voidNotes" class="dt-input" rows="3" placeholder="Contoh: salah hitung / kebijakan khusus"></textarea>
        </div>
        <button type="submit" class="nb-btn" style="width:100%; border-radius:14px; background:#ef4444; color:#fff; border-color:#ef4444;">
          Void Denda
        </button>
      </form>
    </div>
  </div>

</div>

<style>
  .dt-card{ padding:14px; }
  .dt-head .dt-title{ font-size:16px; font-weight:800; color: rgba(11,37,69,.92); }
  .dt-head .dt-sub{ margin-top:4px; color: rgba(15,23,42,.65); font-size:12.5px; }
  .dt-mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-weight:700; }
  .dt-subline{ margin-top:3px; font-size:12px; color: rgba(15,23,42,.62); }
  .dt-divider{ border:0; border-top:1px solid rgba(148,163,184,.35); margin:12px 0; }
  .dt-chip{
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 10px; border-radius:999px;
    border:1px solid rgba(148,163,184,.35);
    background: rgba(255,255,255,.65);
    color: rgba(15,23,42,.78);
    font-size:12px; font-weight:600;
  }
  .dt-chip.ok{ background: rgba(16,185,129,.12); border-color: rgba(16,185,129,.25); color: rgba(6,95,70,.95); }
  .dt-chip.bad{ background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.25); color: rgba(153,27,27,.95); }
  .dt-chip.info{ background: rgba(59,130,246,.12); border-color: rgba(59,130,246,.25); color: rgba(30,64,175,.95); }

  .dt-sec-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .dt-sec-title{ font-size:13.5px; font-weight:800; color: rgba(11,37,69,.92); }
  .dt-sec-sub{ margin-top:3px; font-size:12px; color: rgba(15,23,42,.62); }

  .dt-sec-tools{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:flex-end; }
  .dt-toggle{ display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border-radius:999px; border:1px solid rgba(148,163,184,.35); background: rgba(255,255,255,.55); font-size:12px; font-weight:600; color: rgba(15,23,42,.78); }
  .dt-toggle input{ width:16px; height:16px; }

  .dt-tableWrap{ overflow:auto; }
  .dt-table{ width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
  .dt-table thead th{
    position:sticky; top:0;
    background: rgba(248,250,252,.98);
    text-align:left;
    padding:10px 10px;
    border-bottom:1px solid rgba(148,163,184,.35);
    color: rgba(15,23,42,.85);
    font-weight:800;
  }
  .dt-table tbody td{
    padding:10px 10px;
    border-bottom:1px solid rgba(148,163,184,.20);
    vertical-align:top;
  }
  .dt-table tbody tr.is-overdue td{
    background: rgba(239,68,68,.05);
  }

  .dt-amount{ margin-top:8px; font-size:13px; font-weight:800; color: rgba(11,37,69,.92); }
  .dt-note{ margin-top:6px; font-size:12px; color: rgba(15,23,42,.62); }

  .dt-kpi{ display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:10px; }
  .dt-kpi-box{ border:1px solid rgba(148,163,184,.25); background: rgba(255,255,255,.55); border-radius:14px; padding:10px 12px; }
  .dt-kpi-box .k{ font-size:12px; color: rgba(15,23,42,.62); font-weight:700; }
  .dt-kpi-box .v{ font-size:14px; font-weight:900; margin-top:4px; color: rgba(11,37,69,.92); }
  .dt-kpi-box.warn{ background: rgba(239,68,68,.08); border-color: rgba(239,68,68,.18); }
  .dt-kpi-box.ok{ background: rgba(16,185,129,.08); border-color: rgba(16,185,129,.18); }

  .fine-row.is-zero .fine-lateChip{ opacity:.55; }
  .fine-row.is-late .fine-lateChip{ border-color: rgba(239,68,68,.25); background: rgba(239,68,68,.08); }

  .dt-actions{ display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; }
  .dt-btn{
    padding:8px 10px;
    border-radius:12px;
    border:1px solid rgba(148,163,184,.35);
    background: rgba(255,255,255,.75);
    font-weight:800;
    font-size:12px;
    cursor:pointer;
  }
  .dt-btn[disabled]{ opacity:.55; cursor:not-allowed; }
  .dt-btn.primary{ background: rgba(59,130,246,.12); border-color: rgba(59,130,246,.22); }
  .dt-btn.danger{ background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.22); }

  .dt-backdrop{ position:fixed; inset:0; background: rgba(15,23,42,.45); z-index:40; }
  .dt-drawer{ position:fixed; right:0; top:0; height:100%; width:min(420px, 92vw); background:#fff; z-index:50; border-left:1px solid rgba(148,163,184,.25); box-shadow:-10px 0 30px rgba(2,6,23,.08); }
  .dt-drawer-head{ display:flex; justify-content:space-between; align-items:flex-start; padding:14px 14px 10px; border-bottom:1px solid rgba(148,163,184,.25); gap:10px; }
  .dt-drawer-title{ font-size:14px; font-weight:900; color: rgba(11,37,69,.92); }
  .dt-drawer-sub{ font-size:12px; color: rgba(15,23,42,.62); margin-top:3px; }
  .dt-x{ border:0; background:transparent; font-size:22px; line-height:1; cursor:pointer; color: rgba(15,23,42,.65); }
  .dt-drawer-body{ padding:14px; }

  .dt-field{ margin-bottom:12px; }
  .dt-field label{ display:block; font-size:12px; font-weight:800; color: rgba(15,23,42,.78); margin-bottom:6px; }
  .dt-input{
    width:100%;
    border-radius:12px;
    border:1px solid rgba(148,163,184,.35);
    padding:10px 10px;
    background: rgba(255,255,255,.9);
    font-size:13px;
  }
  .dt-help{ margin-top:6px; font-size:12px; color: rgba(15,23,42,.60); }

  @media (max-width: 860px){
    .dt-kpi{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
</style>

<script>
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
  const recalcUrl = "{{ route('transaksi.denda.recalc') }}";

  function rupiah(n){
    n = Number(n||0);
    return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  }

  function escapeHtml(str){
    return String(str||'')
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;');
  }

  function setFineStatusChip(el, status, amount){
    if(!el) return;
    el.classList.remove('ok','bad','info');

    if(status === 'void'){
      el.textContent = 'Dibatalkan';
      el.classList.add('info');
      return;
    }
    if(status === 'paid'){
      el.textContent = 'Lunas';
      el.classList.add('ok');
      return;
    }
    if((Number(amount)||0) > 0){
      el.textContent = 'Belum Dibayar';
      el.classList.add('bad');
      return;
    }
    el.textContent = 'Tidak Ada';
    el.classList.add('ok');
  }

  function fmtPaidMeta(paidAmount, amount, paidAt){
    const a = Number(amount||0);
    const p = Number(paidAmount||0);
    if(a <= 0) return '—';
    const base = (p > 0)
      ? `Dibayar: Rp ${rupiah(p)} / Rp ${rupiah(a)}`
      : `Dibayar: Rp 0 / Rp ${rupiah(a)}`;
    return paidAt ? `${base} • ${paidAt}` : base;
  }

  function calcKPI(){
    const rows = Array.from(document.querySelectorAll('[data-fine-row="1"]'));
    let lateCount = 0;
    let sumUnpaid = 0;
    let sumPaid = 0;

    rows.forEach(r=>{
      const amount = Number(r.getAttribute('data-amount')||0);
      const status = String(r.getAttribute('data-status')||'unpaid');
      const paidAmount = Number(r.getAttribute('data-paid-amount')||0);
      const days = Number((r.querySelector('.fine-days')?.textContent||'0').replace(/\D/g,'')||0);

      const hasLate = (amount > 0 || days > 0);
      if(hasLate) lateCount++;

      if(status === 'void') return;

      const paidPart = Math.min(Math.max(paidAmount,0), Math.max(amount,0));
      const remain = Math.max((amount - paidPart), 0);
      sumPaid += paidPart;
      sumUnpaid += remain;
    });

    const total = sumPaid + sumUnpaid;

    document.getElementById('kpiLateCount').textContent = rupiah(lateCount);
    document.getElementById('kpiUnpaid').textContent = rupiah(sumUnpaid);
    document.getElementById('kpiPaid').textContent = rupiah(sumPaid);
    document.getElementById('kpiTotal').textContent = rupiah(total);

    document.getElementById('ftPaid').textContent = rupiah(sumPaid);
    document.getElementById('ftUnpaid').textContent = rupiah(sumUnpaid);
    document.getElementById('ftTotal').textContent = rupiah(total);
  }

  function applyOnlyLateFilter(){
    const only = document.getElementById('onlyLate')?.checked;
    document.querySelectorAll('[data-fine-row="1"]').forEach(r=>{
      const amount = Number(r.getAttribute('data-amount')||0);
      const days = Number((r.querySelector('.fine-days')?.textContent||'0').replace(/\D/g,'')||0);
      const isLate = (amount > 0 || days > 0);
      r.style.display = (!only || isLate) ? '' : 'none';
    });
  }

  async function recalcRow(row){
    const loanItemId = row?.getAttribute('data-loan-item-id');
    if(!row || !loanItemId) return;

    const hint = row.querySelector('.fine-hint');
    if(hint) hint.textContent = 'Menghitung…';

    try{
      const res = await fetch(recalcUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({ loan_item_id: Number(loanItemId) })
      });

      const json = await res.json();
      if(!json?.ok){
        if(hint) hint.textContent = json?.message || 'Gagal menghitung.';
        return;
      }

      const d = (json.data && Array.isArray(json.data) && json.data[0]) ? json.data[0] : (json.data || null);

      const days = Number(d?.days_late ?? 0);
      const rate = Number(d?.rate ?? json?.rate ?? 0);
      const amount = Number(d?.amount ?? 0);
      const paidAmount = Number(d?.paid_amount ?? 0);
      const paidAt = d?.paid_at ? String(d.paid_at) : '';
      const notes = d?.notes ? String(d.notes) : '';

      let status = String(d?.fine_status ?? '');
      if(!['unpaid','paid','void'].includes(status)) status = 'unpaid';

      const paidPart = Math.min(Math.max(paidAmount,0), Math.max(amount,0));
      const remain = Math.max((amount - paidPart), 0);

      if(status !== 'void'){
        if(amount > 0 && remain <= 0 && paidPart > 0) status = 'paid';
        if(amount <= 0) status = 'unpaid';
      }

      row.querySelectorAll('.fine-days').forEach(el => el.textContent = rupiah(days));
      row.querySelectorAll('.fine-rate').forEach(el => el.textContent = rupiah(rate));
      row.querySelectorAll('.fine-amount').forEach(el => el.textContent = rupiah(amount));

      row.setAttribute('data-amount', String(amount));
      row.setAttribute('data-status', status);
      row.setAttribute('data-paid-amount', String(paidAmount));
      row.setAttribute('data-paid-at', paidAt);
      row.setAttribute('data-notes', notes);

      row.classList.toggle('is-late', (days > 0 || amount > 0));
      row.classList.toggle('is-zero', (amount <= 0 && days <= 0));

      setFineStatusChip(row.querySelector('.fine-status'), status, amount);

      const paidMetaEl = row.querySelector('.fine-paid');
      if(paidMetaEl){
        paidMetaEl.textContent = (status === 'void') ? '—' : fmtPaidMeta(paidAmount, amount, paidAt);
      }

      const noteEl = row.querySelector('.fine-note');
      if(noteEl){
        noteEl.innerHTML = notes ? escapeHtml(notes) : '—';
      }

      const btnPay = row.querySelector('.js-fine-pay');
      const btnVoid = row.querySelector('.js-fine-void');

      const hasFine = amount > 0;
      const canPay = hasFine && (status !== 'paid') && (status !== 'void');
      const canVoid = hasFine && (status !== 'paid') && (status !== 'void');

      if(btnPay) btnPay.disabled = !canPay;
      if(btnVoid) btnVoid.disabled = !canVoid;

      if(hint){
        if(status === 'void'){
          hint.textContent = 'Denda sudah dibatalkan.';
        } else if(amount <= 0){
          hint.textContent = 'Tidak ada denda untuk item ini.';
        } else if(status === 'paid'){
          hint.textContent = 'Sudah lunas.';
        } else if(paidPart > 0 && remain > 0){
          hint.textContent = `Pembayaran sebagian: sisa Rp ${rupiah(remain)}.`;
        } else {
          hint.textContent = 'Klik Bayar untuk melunasi atau Void untuk membatalkan.';
        }
      }

      calcKPI();
      applyOnlyLateFilter();

    }catch(err){
      if(hint) hint.textContent = 'Error jaringan saat menghitung.';
    }
  }

  let currentRow = null;

  function openBackdrop(){ document.getElementById('drawerBackdrop').style.display = 'block'; }
  function closeBackdrop(){ document.getElementById('drawerBackdrop').style.display = 'none'; }

  function closeDrawers(){
    currentRow = null;
    closeBackdrop();
    document.getElementById('drawerPay').style.display = 'none';
    document.getElementById('drawerVoid').style.display = 'none';
  }

  function openPayDrawer(row){
    currentRow = row;
    const loanItemId = row.getAttribute('data-loan-item-id');
    const barcode = row.getAttribute('data-barcode') || '-';

    const amount = Number(row.getAttribute('data-amount')||0);
    const paidAmount = Number(row.getAttribute('data-paid-amount')||0);
    const paidPart = Math.min(Math.max(paidAmount,0), Math.max(amount,0));
    const remain = Math.max((amount - paidPart), 0);

    document.getElementById('payLoanItemId').value = loanItemId;
    document.getElementById('payMeta').textContent = `Loan Item #${loanItemId} • ${barcode} • Sisa: Rp ${rupiah(remain)}`;

    const payAmountEl = document.getElementById('payAmount');
    payAmountEl.value = '';
    // UX: batasi input maksimal sisa (backend tetap membatasi)
    if(remain > 0){
      payAmountEl.setAttribute('max', String(remain));
      payAmountEl.setAttribute('placeholder', `Maksimal Rp ${rupiah(remain)} (kosongkan untuk bayar sisa)`);
    } else {
      payAmountEl.removeAttribute('max');
      payAmountEl.setAttribute('placeholder', 'Masukkan nominal…');
    }

    document.getElementById('payNotes').value = '';
    openBackdrop();
    document.getElementById('drawerPay').style.display = 'block';
  }

  function openVoidDrawer(row){
    currentRow = row;
    const loanItemId = row.getAttribute('data-loan-item-id');
    const barcode = row.getAttribute('data-barcode') || '-';
    document.getElementById('voidLoanItemId').value = loanItemId;
    document.getElementById('voidMeta').textContent = `Loan Item #${loanItemId} • ${barcode}`;
    document.getElementById('voidNotes').value = '';
    openBackdrop();
    document.getElementById('drawerVoid').style.display = 'block';
  }

  async function recalcAll(){
    const rows = Array.from(document.querySelectorAll('[data-fine-row="1"]'));
    const fast = document.getElementById('fastMode')?.checked;

    for(const r of rows){
      await recalcRow(r);
      if(!fast){
        await new Promise(res=>setTimeout(res, 90));
      }
    }
  }

  document.getElementById('btnHitungSemua')?.addEventListener('click', recalcAll);
  document.getElementById('onlyLate')?.addEventListener('change', applyOnlyLateFilter);

  calcKPI();
</script>
@endsection
