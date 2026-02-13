# Roadmap Fast Track (>8.0)

## Target
Naikkan kematangan modul inti ILS dengan fokus:
- Keanggotaan
- Laporan operasional
- Serial issue control

## Eksekusi Cepat (MVP yang sudah dikerjakan)
1. Keanggotaan
- CRUD anggota aktif: list, tambah, edit, detail.
- Filter status dan pencarian.
- Ringkasan operasional: anggota aktif, member overdue, member dengan denda unpaid.

2. Laporan operasional
- Dashboard laporan dengan filter tanggal dan cabang.
- KPI inti: peminjaman, pengembalian, overdue, denda, purchase order.
- Tabel analitik: top judul dipinjam, top member overdue, ringkasan denda, ringkasan pengadaan.
- Export CSV per jenis laporan.

3. Serial issue control
- Entitas `serial_issues` dan workflow minimum:
  - buat issue expected
  - tandai received
  - tandai missing
- Filter status/cabang dan pencarian issue.

## Backlog Lanjutan (2-4 minggu berikutnya)
1. Keanggotaan
- Import anggota via CSV.
- Aksi bulk status.
- Kartu anggota (print PDF/QR).

2. Laporan
- Export XLSX.
- Snapshot laporan bulanan otomatis.
- Drill-down ke detail transaksi.

3. Serial issue control
- Nomor urut otomatis issue berdasarkan pola.
- Modul klaim ke vendor.
- Tampilan holdings per issue di OPAC.

