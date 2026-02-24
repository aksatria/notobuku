<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kartu Anggota - {{ $member->member_code }}</title>
  <style>
    :root{
      --nb-blue-1:#1565c0;
      --nb-blue-2:#1e88e5;
      --nb-ink:#0f172a;
      --nb-muted:#475569;
      --nb-line:#cbd5e1;
      --nb-bg:#f8fafc;
    }
    *{ box-sizing:border-box; }
    body{
      margin:0;
      padding:24px;
      font-family: "Segoe UI", Tahoma, Arial, sans-serif;
      background:var(--nb-bg);
      color:var(--nb-ink);
    }
    .toolbar{
      max-width:860px;
      margin:0 auto 14px auto;
      display:flex;
      gap:8px;
      justify-content:flex-end;
    }
    .btn{
      border:1px solid var(--nb-line);
      background:#fff;
      border-radius:10px;
      padding:9px 12px;
      font-weight:700;
      cursor:pointer;
    }
    .btn.primary{
      border-color:transparent;
      color:#fff;
      background:linear-gradient(90deg,var(--nb-blue-2),var(--nb-blue-1));
    }
    .sheet{
      max-width:860px;
      margin:0 auto;
      display:grid;
      grid-template-columns:repeat(2, minmax(0, 1fr));
      gap:18px;
    }
    .card{
      position:relative;
      width:100%;
      max-width:420px;
      aspect-ratio:1.586 / 1;
      border-radius:18px;
      overflow:hidden;
      background:#fff;
      border:1px solid var(--nb-line);
      box-shadow:0 12px 30px rgba(15,23,42,.10);
    }
    .front-head{
      padding:16px 16px 12px 16px;
      color:#fff;
      background:linear-gradient(120deg,var(--nb-blue-1),var(--nb-blue-2));
    }
    .org{
      font-size:11px;
      letter-spacing:.12em;
      opacity:.9;
      text-transform:uppercase;
    }
    .title{
      margin-top:6px;
      font-size:18px;
      font-weight:800;
      line-height:1.1;
    }
    .front-body{
      padding:14px 16px;
      display:grid;
      grid-template-columns:1fr auto;
      gap:12px;
      align-items:start;
    }
    .name{ font-size:18px; font-weight:800; line-height:1.2; }
    .code{
      margin-top:6px;
      display:inline-block;
      background:#e2e8f0;
      border-radius:999px;
      padding:3px 8px;
      font-size:12px;
      font-weight:700;
    }
    .meta{
      margin-top:10px;
      display:grid;
      gap:6px;
      font-size:12px;
      color:var(--nb-muted);
    }
    .qr{
      width:94px;
      height:94px;
      border:1px solid var(--nb-line);
      border-radius:10px;
      background:#fff;
      overflow:hidden;
      display:flex;
      align-items:center;
      justify-content:center;
    }
    .qr img{ width:100%; height:100%; object-fit:cover; }
    .status{
      position:absolute;
      right:14px;
      bottom:12px;
      font-size:11px;
      font-weight:800;
      letter-spacing:.08em;
      color:#0f172a;
      background:#f1f5f9;
      border:1px solid var(--nb-line);
      border-radius:999px;
      padding:4px 8px;
    }
    .back{
      padding:14px 16px;
      display:flex;
      flex-direction:column;
      gap:12px;
    }
    .back-strip{
      height:42px;
      background:#111827;
      margin:0 -16px;
    }
    .hint{
      font-size:12px;
      color:var(--nb-muted);
      line-height:1.45;
    }
    .sig{
      margin-top:auto;
      border-top:1px dashed var(--nb-line);
      padding-top:10px;
      font-size:11px;
      color:#64748b;
      display:flex;
      justify-content:space-between;
    }
    @media (max-width:900px){
      .sheet{ grid-template-columns:1fr; justify-items:center; }
    }
    @media print{
      body{ background:#fff; padding:0; }
      .toolbar{ display:none !important; }
      .sheet{ max-width:none; gap:10mm; padding:8mm; }
      .card{
        width:85.6mm;
        height:54mm;
        max-width:none;
        box-shadow:none;
        border:0.3mm solid #d1d5db;
      }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button class="btn" type="button" onclick="window.close()">Tutup</button>
    <button class="btn primary" type="button" onclick="window.print()">Cetak</button>
  </div>

  <div class="sheet">
    <section class="card" aria-label="Kartu depan">
      <div class="front-head">
        <div class="org">NOTOBUKU LIBRARY</div>
        <div class="title">Kartu Anggota</div>
      </div>
      <div class="front-body">
        <div>
          <div class="name">{{ $member->full_name ?: '-' }}</div>
          <div class="code">{{ $member->member_code ?: ('MBR-' . $member->id) }}</div>
          <div class="meta">
            @if(!empty($hasMemberType))
              <div>Tipe: {{ $member->member_type ?: 'member' }}</div>
            @endif
            @if(!empty($hasEmail) && !empty($member->email))
              <div>Email: {{ $member->email }}</div>
            @endif
            <div>Bergabung: {{ $member->joined_at ? \Illuminate\Support\Carbon::parse($member->joined_at)->format('d M Y') : '-' }}</div>
          </div>
        </div>
        <div class="qr">
          <img src="{{ $qrUrl }}" alt="QR Anggota">
        </div>
      </div>
      <div class="status">{{ $statusLabel }}</div>
    </section>

    <section class="card back" aria-label="Kartu belakang">
      <div class="back-strip"></div>
      <div class="hint">
        Kartu ini adalah identitas anggota perpustakaan. Mohon dibawa saat transaksi peminjaman, pengembalian, dan layanan sirkulasi lainnya.
      </div>
      <div class="hint">
        Jika kartu hilang, segera lapor petugas untuk pemblokiran dan pencetakan ulang.
      </div>
      <div class="sig">
        <span>Kode: {{ $member->member_code ?: ('MBR-' . $member->id) }}</span>
        <span>ID: {{ (int) $member->id }}</span>
      </div>
    </section>
  </div>
</body>
</html>

