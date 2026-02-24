<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cetak Label Eksemplar</title>
  <style>
    :root{
      --ink:#0f172a;
      --muted:#475569;
      --line:#cbd5e1;
      --bg:#f8fafc;
      --accent:#1565c0;
    }
    *{ box-sizing:border-box; }
    body{
      margin:0;
      padding:20px;
      font-family:"Segoe UI", Tahoma, Arial, sans-serif;
      color:var(--ink);
      background:var(--bg);
    }
    .toolbar{
      max-width:1120px;
      margin:0 auto 12px auto;
      display:flex;
      justify-content:space-between;
      gap:10px;
      align-items:center;
      flex-wrap:wrap;
    }
    .meta{
      font-size:13px;
      color:var(--muted);
    }
    .btn{
      border:1px solid var(--line);
      background:#fff;
      border-radius:10px;
      padding:9px 12px;
      font-weight:700;
      cursor:pointer;
    }
    .btn.primary{
      border-color:transparent;
      color:#fff;
      background:linear-gradient(90deg,#1e88e5,var(--accent));
    }
    .sheet{
      max-width:1120px;
      margin:0 auto;
      display:grid;
      grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
      gap:12px;
    }
    .label{
      border:1px solid var(--line);
      border-radius:14px;
      background:#fff;
      padding:10px 10px 8px;
      min-height:185px;
      display:flex;
      flex-direction:column;
      justify-content:space-between;
    }
    .title{
      font-size:13px;
      font-weight:800;
      line-height:1.35;
      margin-bottom:6px;
      min-height:34px;
    }
    .line{
      font-size:12px;
      color:var(--muted);
      margin-top:3px;
      line-height:1.3;
    }
    .barcode-wrap{
      margin-top:8px;
      border:1px solid var(--line);
      border-radius:10px;
      padding:6px 6px 2px;
      text-align:center;
    }
    .barcode-wrap img{
      width:100%;
      height:56px;
      object-fit:contain;
      background:#fff;
    }
    .barcode-text{
      margin-top:2px;
      font-size:11px;
      font-weight:700;
      letter-spacing:.08em;
      color:#0f172a;
    }
    .empty{
      max-width:1120px;
      margin:0 auto;
      border:1px dashed var(--line);
      border-radius:14px;
      background:#fff;
      padding:16px;
      color:var(--muted);
    }
    @media print{
      body{ background:#fff; padding:0; }
      .toolbar{ display:none !important; }
      .sheet{
        max-width:none;
        gap:6mm;
        padding:6mm;
        grid-template-columns:repeat(3, 1fr);
      }
      .label{
        border:0.25mm solid #d1d5db;
        border-radius:3mm;
        min-height:52mm;
        page-break-inside:avoid;
      }
    }
  </style>
</head>
<body>
@php
  $items = $items ?? collect();
@endphp
  <div class="toolbar">
    <div class="meta">
      <strong>Cetak Label Eksemplar</strong><br>
      {{ $biblio->display_title ?? $biblio->title ?? '-' }} • {{ $items->count() }} item
    </div>
    <div style="display:flex; gap:8px;">
      <button class="btn" type="button" onclick="window.close()">Tutup</button>
      <button class="btn primary" type="button" onclick="window.print()">Cetak</button>
    </div>
  </div>

  @if($items->isEmpty())
    <div class="empty">Tidak ada data eksemplar untuk dicetak.</div>
  @else
    <section class="sheet">
      @foreach($items as $it)
        @php
          $barcode = (string) ($it->barcode ?? '');
          $barcodeUrl = 'https://barcode.tec-it.com/barcode.ashx?translate-esc=on&data=' . urlencode($barcode) . '&code=Code128&multiplebarcodes=false&unit=Fit&dpi=96&imagetype=Jpg&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0';
          $loc = trim(((string) ($it->branch_name ?? '')) . ' • ' . ((string) ($it->shelf_name ?? '')), " •");
        @endphp
        <article class="label">
          <div>
            <div class="title">{{ $biblio->display_title ?? $biblio->title ?? '-' }}</div>
            <div class="line">Call Number: {{ $biblio->call_number ?: '-' }}</div>
            <div class="line">Accession: {{ $it->accession_number ?: '-' }}</div>
            <div class="line">Inventaris: {{ $it->inventory_code ?: '-' }}</div>
            <div class="line">Lokasi: {{ $loc !== '' ? $loc : '-' }}</div>
          </div>
          <div class="barcode-wrap">
            <img src="{{ $barcodeUrl }}" alt="Barcode {{ $barcode }}">
            <div class="barcode-text">{{ $barcode !== '' ? $barcode : '-' }}</div>
          </div>
        </article>
      @endforeach
    </section>
  @endif
</body>
</html>


