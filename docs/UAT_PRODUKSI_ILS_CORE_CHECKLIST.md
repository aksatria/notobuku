# UAT Produksi - ILS Core Checklist

Tujuan checklist ini: menjaga modul inti ILS (`katalog`, `sirkulasi`, `anggota`, `laporan`, `serial`) plus modul klasik tambahan (`stock opname`, `copy cataloging`) tetap stabil pada level 10/10 saat rilis.

## 1. Prasyarat Wajib (Sebelum UAT)
1. Gunakan database produksi cadangan/snapshot, bukan data utama langsung.
2. Pastikan `.env` produksi sudah benar:
   1. `APP_ENV=production`
   2. `APP_DEBUG=false`
   3. kredensial DB/queue/cache valid
3. Jalankan backup sebelum deployment:
   1. backup database
   2. backup `storage/app`
4. Verifikasi migrasi aman:
   1. tidak ada `migrate:fresh` di SOP produksi
   2. hanya `php artisan migrate --force`

## 2. UAT Modul Katalog
1. Tambah bibliografi baru, simpan sukses.
2. Tambah/edit eksemplar untuk bibliografi tersebut.
3. Cari bibliografi via judul/ISBN/DDC, hasil muncul.
4. Buka detail bibliografi, metadata utama tampil.
5. Uji export katalog (CSV/XLSX bila aktif), file valid.

## 3. UAT Modul Sirkulasi
1. Proses pinjam item tersedia, status item berubah jadi `borrowed`.
2. Proses kembali item, status item kembali `available`.
3. Proses perpanjang pinjaman, tanggal jatuh tempo berubah.
4. Buka riwayat transaksi, loan code dan anggota muncul.
5. Uji denda:
   1. denda terhitung saat overdue
   2. bayar denda partial/lunas
   3. void denda non-lunas

## 4. UAT Modul Anggota
1. Tambah/edit anggota manual.
2. Import CSV anggota:
   1. preview jalan
   2. duplicate detection email/phone jalan
   3. error CSV/summary CSV dapat diunduh
   4. confirm import sukses
3. Undo batch import terakhir bekerja sesuai batas waktu.
4. Export history import CSV/XLSX berhasil.

## 5. UAT Modul Laporan
1. Filter `from/to` dan `branch` bekerja.
2. KPI muncul dan konsisten dengan data transaksi.
3. Export CSV + XLSX untuk semua tipe:
   1. `sirkulasi`
   2. `overdue`
   3. `denda`
   4. `pengadaan`
   5. `anggota`
   6. `serial`

## 6. UAT Modul Serial Issue
1. Tambah issue serial baru sukses.
2. Ubah status alur:
   1. `expected -> claimed`
   2. `claimed -> received`
   3. `expected/claimed -> missing`
3. Validasi guard:
   1. issue `received` tidak boleh di-claim ulang
4. Filter status/cabang/tanggal bekerja.
5. Export serial issue CSV/XLSX berhasil.

## 7. UAT Otorisasi (Role Matrix)
1. `super_admin/admin/staff` bisa akses endpoint operasional inti.
2. `member` tidak bisa akses endpoint staff:
   1. import anggota
   2. export laporan operasional
3. serial claim workflow
   4. stock opname
   5. copy cataloging

## 8. UAT Modul Stock Opname
1. Buat sesi opname baru dengan filter cabang/rak/status item.
2. Mulai sesi opname (`draft -> in_progress`).
3. Scan barcode item valid:
   1. line berubah menjadi `found`
4. Selesaikan sesi:
   1. line expected yang belum discan berubah `missing`
   2. ringkasan `expected/found/missing/unexpected` konsisten
5. Export CSV sesi berhasil.

## 9. UAT Modul Copy Cataloging
1. Tambah sumber copy cataloging:
   1. SRU
   2. Z39.50 gateway
   3. P2P
2. Jalankan pencarian ke sumber aktif, hasil tampil.
3. Import satu record ke katalog lokal:
   1. bibliografi baru terbentuk
   2. riwayat import tercatat
4. Validasi error handling:
   1. payload record invalid ditolak
   2. judul kosong ditandai `failed` di riwayat import

## 10. Non-Fungsional Wajib
1. Tidak ada error 500 di log untuk flow UAT.
2. p95 response endpoint inti dalam batas SLO internal.
3. Scheduler utama berjalan:
   1. snapshot import history bulanan
   2. jobs interop metrics/reconcile

## 11. Kriteria Go-Live
1. Semua poin UAT lolos.
2. Tidak ada blocker severity tinggi.
3. Stakeholder operasi menyetujui checklist final.
