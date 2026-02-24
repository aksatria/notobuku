<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Slip {{ $loan->loan_code ?? '' }} • 58mm</title>

  <style>
    /* ====== PRINT BASE ====== */
    @page { size: 58mm auto; margin: 3mm; }
    html, body { padding:0; margin:0; background:#fff; color:#111; }
    body { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 10.8px; line-height: 1.22; }

    .wrap { width: 52mm; } /* 58 - margin */
    .center { text-align:center; }
    .muted { opacity:.75; }

    .hr { border-top: 1px dashed #222; margin: 7px 0; }
    .hr2 { border-top: 1px solid #222; margin: 5px 0; }

    .h1 { font-weight: 900; font-size: 12px; letter-spacing: .3px; }
    .b { font-weight:900; }
    .small { font-size: 10.2px; }

    .row { display:flex; gap:6px; }
    .row .k { width: 16mm; opacity:.85; }
    .row .v { flex:1; }

    .item { padding: 6px 0; border-bottom: 1px dashed rgba(0,0,0,.35); }
    .item:last-child { border-bottom: none; }

    .badge {
      display:inline-block;
      padding: 1px 6px;
      border: 1px solid #111;
      border-radius: 999px;
      font-size: 10px;
      font-weight: 800;
    }

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
    <button class="btn" onclick="window.print()">Cetak</button>
    <button class="btn" onclick="window.close()">Tutup</button>
  </div>

  <div class="paper">
    <div class="wrap">

      <div class="center">
        <div class="h1">{{ config('app.name', 'NOTOBUKU') }}</div>
        <div class="muted small">Slip • 58mm</div>
      </div>

      <div class="hr"></div>

      @php
        $loanedAt = !empty($loan->loaned_at) ? \Carbon\Carbon::parse($loan->loaned_at) : null;
        $dueAt    = !empty($loan->due_at) ? \Carbon\Carbon::parse($loan->due_at) : null;
        $closedAt = !empty($loan->closed_at) ? \Carbon\Carbon::parse($loan->closed_at) : null;
        $status = (string)($loan->status ?? '');
      @endphp

      <div class="row"><div class="k">Kode</div><div class="v b">{{ $loan->loan_code ?? '-' }}</div></div>
      <div class="row"><div class="k">Anggota</div><div class="v">{{ $loan->anggota_name ?? '-' }}</div></div>
      <div class="row"><div class="k">ID</div><div class="v">{{ $loan->anggota_code ?? '-' }}</div></div>

      <div class="hr2"></div>

      <div class="row"><div class="k">Status</div><div class="v"><span class="badge">{{ strtoupper($status ?: 'open') }}</span></div></div>
      <div class="row"><div class="k">Pinjam</div><div class="v">{{ $loanedAt ? $loanedAt->format('d/m H:i') : '-' }}</div></div>
      <div class="row"><div class="k">Jatuh</div><div class="v">{{ $dueAt ? $dueAt->format('d/m H:i') : '-' }}</div></div>
      <div class="row"><div class="k">Tutup</div><div class="v">{{ $closedAt ? $closedAt->format('d/m H:i') : '-' }}</div></div>

      <div class="hr"></div>

      <div class="b">Item ({{ count($items ?? []) }})</div>

      @foreach(($items ?? []) as $it)
        @php
          $barcode = (string)($it->barcode ?? '-');
          $t = trim((string)($it->title ?? ''));
          $returnedAt = !empty($it->returned_at) ? \Carbon\Carbon::parse($it->returned_at) : null;
          $liDue = !empty($it->item_due_at) ? \Carbon\Carbon::parse($it->item_due_at) : null;

          $isLate = false;
          if ($liDue && $returnedAt) $isLate = $returnedAt->greaterThan($liDue);
          if ($liDue && !$returnedAt) $isLate = now()->greaterThan($liDue);
        @endphp

        <div class="item">
          <div class="b">{{ $barcode }}</div>
          @if($t !== '')
            <div class="small">{{ \Illuminate\Support\Str::limit($t, 62) }}</div>
          @endif

          <div class="small muted" style="margin-top:3px;">
            Jatuh tempo: {{ $liDue ? $liDue->format('d/m H:i') : '-' }}
            @if($returnedAt)
              • Kembali: {{ $returnedAt->format('d/m H:i') }}
            @endif
            @if($isLate)
              • <span style="font-weight:900;">TERLAMBAT</span>
            @endif
          </div>
        </div>
      @endforeach

      <div class="hr"></div>

      <div class="small muted">
        Cetak: {{ isset($printedAt) ? $printedAt->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}
        <br>
        Petugas: {{ $loan->created_by_name ?? '-' }}
      </div>

      <div class="center small muted" style="margin-top:9px;">
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


