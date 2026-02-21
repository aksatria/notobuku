# SOP Harian Pustakawan Pemula

Dokumen ini untuk staf baru agar operasional berjalan rapi, cepat, dan minim kesalahan.

## Ringkasan Shift Harian
| Tahap | Fokus |
| --- | --- |
| Sebelum buka | Cek akses sistem dan perangkat |
| Saat layanan | Katalog, anggota, pinjam-kembali-perpanjang |
| Akhir hari | Laporan, verifikasi error, penutupan layanan |

## A. Sebelum Perpustakaan Buka (10-15 menit)
1. Nyalakan komputer dan koneksi internet.
2. Buka NOTOBUKU dan login akun `admin/staff`.
3. Cek notifikasi/error di dashboard.
4. Pastikan scanner barcode dan printer berfungsi.

Checklist:
- [ ] Login berhasil
- [ ] Scanner terbaca
- [ ] Printer siap
- [ ] Tidak ada error kritis

## B. Input Buku Baru (Katalog)
1. Masuk menu `Katalog` -> `Tambah`.
2. Isi data minimal: judul, pengarang, subjek (tambahkan ISBN/DDC jika ada).
3. Simpan bibliografi.
4. Tambahkan eksemplar: barcode, lokasi rak, kondisi.
5. Simpan dan verifikasi buku tampil di daftar.

Checklist:
- [ ] Bibliografi tersimpan
- [ ] Barcode unik
- [ ] Lokasi rak terisi

## C. Keanggotaan
1. Buka menu `Anggota`.
2. Tambah anggota manual atau `Import CSV`.
3. Validasi data inti: nama, ID anggota, kontak, status aktif.
4. Simpan.

Checklist:
- [ ] ID anggota unik
- [ ] Status aktif

## D. Sirkulasi
### Pinjam
1. Buka `Sirkulasi -> Pinjam`.
2. Scan/isi ID anggota.
3. Scan barcode buku.
4. Cek tanggal jatuh tempo.
5. Klik `Konfirmasi Pinjam`.

### Kembali
1. Buka `Sirkulasi -> Kembali`.
2. Scan barcode buku.
3. Verifikasi denda (jika ada).
4. Konfirmasi `Kembali`.

### Perpanjang
1. Buka transaksi pinjaman anggota.
2. Pilih item.
3. Klik `Perpanjang`.
4. Pastikan due date baru tersimpan.

Jika transaksi gagal:
- Cek status anggota
- Cek status ketersediaan item
- Cek barcode/input data

## E. Akhir Hari (15-20 menit)
1. Buka `Laporan`.
2. Export transaksi harian (CSV/XLSX).
3. Catat error/transaksi gagal.
4. Pastikan backup/prosedur penyimpanan berjalan.
5. Logout semua akun staf.

Checklist:
- [ ] Laporan diexport
- [ ] Error dicatat
- [ ] Backup/prosedur selesai
- [ ] Semua akun logout

## F. Visitor Counter (Log Kunjungan Onsite)
Gunakan menu `Operasional -> Visitor Counter` untuk buku tamu onsite.

### Check-in Visitor
1. Pilih `Tipe Visitor`:
- `Member`: isi `Kode Member`.
- `Non-Member`: isi `Nama Visitor`.
2. Isi `Cabang`, `Tujuan`, dan `Catatan` (opsional).
3. Klik `Simpan Check-in`.

Checklist:
- [ ] Data visitor sesuai tipe
- [ ] Cabang benar
- [ ] Check-in tersimpan

### Checkout / Undo
1. Di `Daftar Kunjungan`, klik `Checkout` untuk visitor aktif.
2. Jika salah checkout, klik `Undo` (hanya dalam 5 menit).
3. Untuk banyak data sekaligus:
- centang baris lalu `Checkout Terpilih`
- atau `Checkout Semua Aktif` sesuai filter.

Checklist:
- [ ] Tidak ada visitor aktif yang terlewat di akhir layanan
- [ ] Undo hanya dipakai untuk koreksi cepat

### Audit Log Visitor Counter
1. Gunakan panel `Riwayat Aksi`.
2. Filter berdasarkan:
- `Aksi`
- `Role`
- `Keyword` (actor/action/row id/metadata)
- `Urutan` (terbaru/terlama)
- `Baris per halaman`
3. Gunakan:
- `Export Audit CSV` untuk pelaporan rutin
- `Export Audit JSON` untuk analisis teknis/integrasi
- `View JSON` untuk melihat metadata lengkap per baris.

Checklist:
- [ ] Filter sesuai periode/cabang
- [ ] Audit diexport saat rekap harian/mingguan
- [ ] Anomali dicatat (actor, waktu, aksi, row id)

## Aturan Emas
1. Jangan hapus data jika ragu; catat dan eskalasi.
2. Selalu isi data katalog minimal.
3. Verifikasi barcode sebelum simpan.
4. Catat masalah dengan detail waktu, user, ID/barcode, dan pesan error.
5. Jika sistem tidak stabil, hentikan input massal dan lapor admin teknis.
