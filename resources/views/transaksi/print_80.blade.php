<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Slip {{ $loan->loan_code ?? '' }} • 80mm</title>

  <style>
    /* ====== PRINT BASE ====== */
    @page { size: 80mm auto; margin: 4mm; }
    html, body { padding:0; margin:0; background:#fff; color:#111; }
    body { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 11.5px; line-height: 1.25; }

    .wrap { width: 72mm; } /* 80 - margin */
    .center { text-align:center; }
    .right { text-align:right; }
    .muted { opacity:.75; }

    .hr { border-top: 1px dashed #222; margin: 8px 0; }
    .hr2 { border-top: 1px solid #222; margin: 6px 0; }

    .h1 { font-weight: 900; font-size: 13px; letter-spacing: .4px; }
    .h2 { font-weight: 800; font-size: 12px; }

    .row { display:flex; gap:6px; }
    .row .k { width: 20mm; opacity:.8; }
    .row .v { flex:1; }

    .items { margin-top: 6px; }
    .item { padding: 6px 0; border-bottom: 1px dashed rgba(0,0,0,.35); }
    .item:last-child { border-bottom: none; }
    .b { font-weight:900; }
    .small { font-size: 10.8px; }

    .badge {
      display:inline-block;
      padding: 1px 6px;
      border: 1px solid #111;
      border-radius: 999px;
      font-size: 10.5px;
      font-weight: 800;
    }

    .foot { margin-top: 10px; }
    .btns { display:none; }

    @media screen {
      body { padding: 12px; background: #f4f6f8; }
      .paper { background:#fff; padding: 10px; border:1px solid #ddd; border-radius: 10px; display:inline-block; }
      .btns { display:flex; gap:8px; margin: 10px 0; }
      .btn {
        border:1px solid #111; border-radius: 10px;
        padding: 8px 10px; cursor:pointer;
        background:#fff; font-weight:800;
      }
    }
  </style>
</head>
<body>
  <div class="btns">
    <button class="btn" onclick="window.print()">Print</button>
    <button class="btn" onclick="window.close()">Tutup</button>
  </div>

  <div class="paper">
    <div class="wrap">

      <div class="center">
        <div class="h1">{{ config('app.name', 'NOTOBUKU') }}</div>
        <div class="muted small">Slip Transaksi • 80mm</div>
      </div>

      <div class="hr"></div>

      <div class="row"><div class="k">Kode</div><div class="v b">{{ $loan->loan_code ?? '-' }}</div></div>
      <div class="row"><div class="k">Member</div><div class="v">{{ $loan->member_name ?? '-' }}</div></div>
      <div class="row"><div class="k">ID</div><div class="v">{{ $loan->member_code ?? '-' }}</div></div>
      <div class="row"><div class="k">Cabang</div><div class="v">{{ $loan->branch_name ?? '-' }}</div></div>

      <div class="hr2"></div>

      @php
        $loanedAt = !empty($loan->loaned_at) ? \Carbon\Carbon::parse($loan->loaned_at) : null;
        $dueAt    = !empty($loan->due_at) ? \Carbon\Carbon::parse($loan->due_at) : null;
        $closedAt = !empty($loan->closed_at) ? \Carbon\Carbon::parse($loan->closed_at) : null;

        $status = (string)($loan->status ?? '');
        $badge = $status ?: 'open';
      @endphp

      <div class="row"><div class="k">Status</div><div class="v"><span class="badge">{{ strtoupper($badge) }}</span></div></div>
      <div class="row"><div class="k">Pinjam</div><div class="v">{{ $loanedAt ? $loanedAt->format('d/m/Y H:i') : '-' }}</div></div>
      <div class="row"><div class="k">Jatuh</div><div class="v">{{ $dueAt ? $dueAt->format('d/m/Y H:i') : '-' }}</div></div>
      <div class="row"><div class="k">Tutup</div><div class="v">{{ $closedAt ? $closedAt->format('d/m/Y H:i') : '-' }}</div></div>

      <div class="hr"></div>

      <div class="h2">Daftar Item ({{ count($items ?? []) }})</div>

      <div class="items">
        @foreach(($items ?? []) as $it)
          @php
            $t = trim((string)($it->title ?? ''));
            $barcode = (string)($it->barcode ?? '-');
            $call = trim((string)($it->call_number ?? ''));
            $returnedAt = !empty($it->returned_at) ? \Carbon\Carbon::parse($it->returned_at) : null;

            $liDue = !empty($it->item_due_at) ? \Carbon\Carbon::parse($it->item_due_at) : null;
            $isLate = false;
            if ($liDue && $returnedAt) $isLate = $returnedAt->greaterThan($liDue);
            if ($liDue && !$returnedAt) $isLate = now()->greaterThan($liDue);
          @endphp

          <div class="item">
            <div class="b">{{ $barcode }}</div>
            @if($t !== '')
              <div class="small">{{ $t }}</div>
            @endif
            @if($call !== '')
              <div class="small muted">Call: {{ $call }}</div>
            @endif

            <div class="small muted" style="margin-top:4px;">
              Due: {{ $liDue ? $liDue->format('d/m/Y H:i') : '-' }}
              @if($returnedAt)
                • Returned: {{ $returnedAt->format('d/m/Y H:i') }}
              @endif
              @if($isLate)
                • <span style="font-weight:900;">TERLAMBAT</span>
              @endif
            </div>
          </div>
        @endforeach
      </div>

      <div class="hr"></div>

      <div class="small muted">
        Dicetak: {{ isset($printedAt) ? $printedAt->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}
        <br>
        Petugas: {{ $loan->created_by_name ?? '-' }}
      </div>

      <div class="foot center small muted" style="margin-top:10px;">
        Terima kasih 🙏
      </div>

    </div>
  </div>

  <script>
    // Auto print (opsional). Kalau mau otomatis: uncomment.
    // setTimeout(()=>window.print(), 250);
  </script>
</body>
</html>
