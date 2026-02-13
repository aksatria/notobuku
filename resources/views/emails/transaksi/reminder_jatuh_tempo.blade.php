<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pengingat Pengembalian Buku</title>
    <style>
        body{
            font-family: Arial, Helvetica, sans-serif;
            background:#f6f8fb;
            color:#1f2937;
            line-height:1.6;
        }
        .card{
            max-width:600px;
            margin:20px auto;
            background:#ffffff;
            border-radius:10px;
            padding:24px;
            border:1px solid #e5e7eb;
        }
        .title{
            font-size:18px;
            font-weight:bold;
            margin-bottom:12px;
        }
        .box{
            background:#f9fafb;
            border:1px solid #e5e7eb;
            border-radius:8px;
            padding:12px;
            margin:12px 0;
        }
        .footer{
            margin-top:20px;
            font-size:13px;
            color:#6b7280;
        }
    </style>
</head>
<body>

<div class="card">

    @if(($payload['type'] ?? '') === 'due_soon')
        {{-- ================= H-1 TEMPLATE ================= --}}
        <div class="title">📚 Pengingat pengembalian buku besok</div>

        <p>Halo <strong>{{ $payload['member_name'] ?? 'Member' }}</strong>,</p>

        <p>
            Kami ingin mengingatkan bahwa masa peminjaman buku Anda akan berakhir
            <strong>besok</strong>.
        </p>

        <div class="box">
            <div><strong>Kode transaksi:</strong> {{ $payload['loan_code'] ?? '-' }}</div>
            <div><strong>Tanggal jatuh tempo:</strong> {{ $payload['due_date'] ?? '-' }}</div>
        </div>

        <p>
            Jika sudah selesai membaca, silakan melakukan pengembalian ke perpustakaan.
            Apabila membutuhkan perpanjangan waktu, Anda dapat menghubungi petugas kami.
        </p>

        <p>
            Terima kasih telah menggunakan layanan perpustakaan 😊
        </p>

    @else
        {{-- ================= H+1 TEMPLATE ================= --}}
        <div class="title">📖 Pengingat pengembalian buku</div>

        <p>Halo <strong>{{ $payload['member_name'] ?? 'Member' }}</strong>,</p>

        <p>
            Kami ingin mengingatkan bahwa masa peminjaman buku Anda telah melewati tanggal jatuh tempo.
        </p>

        <div class="box">
            <div><strong>Kode transaksi:</strong> {{ $payload['loan_code'] ?? '-' }}</div>
            <div><strong>Tanggal jatuh tempo:</strong> {{ $payload['due_date'] ?? '-' }}</div>
        </div>

        <p>
            Jika memungkinkan, mohon dapat segera mengembalikan buku tersebut ke perpustakaan.
        </p>

        <p>
            Apabila buku sudah dikembalikan, silakan abaikan pesan ini.
        </p>

        <p>
            Terima kasih atas perhatian dan kerja samanya 😊
        </p>
    @endif

    <p>
        Salam hangat,<br>
        <strong>Tim Perpustakaan</strong>
    </p>

    <div class="footer">
        Pesan ini dikirim otomatis oleh sistem NOTOBUKU.
    </div>

</div>

</body>
</html>
