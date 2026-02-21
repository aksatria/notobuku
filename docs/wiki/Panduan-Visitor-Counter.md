# Panduan Visitor Counter

Panduan ini fokus pada pencatatan kunjungan onsite harian di NOTOBUKU untuk role `super_admin`, `admin`, dan `staff`.

## Tujuan
- Mencatat check-in visitor onsite dengan rapi.
- Memastikan checkout harian terkendali.
- Menyediakan jejak audit untuk monitoring dan pelaporan.

## Lokasi Menu
- Sidebar: `Operasional -> Visitor Counter`

## Alur Cepat
1. Tentukan filter tanggal/cabang sesuai sesi layanan.
2. Lakukan check-in visitor (member/non-member).
3. Jalankan checkout saat visitor selesai.
4. Tinjau panel audit untuk verifikasi aksi.
5. Export audit jika perlu rekap.

## Check-in Visitor
1. Pilih `Tipe Visitor`.
- `Member`: isi `Kode Member`.
- `Non-Member`: isi `Nama Visitor`.
2. Isi `Cabang`, `Tujuan`, dan `Catatan` (opsional).
3. Klik `Simpan Check-in`.

Catatan:
- Validasi member dilakukan saat submit.
- Jika input tidak valid, field error akan ditandai.

## Checkout dan Undo
- `Checkout` dipakai untuk menutup kunjungan aktif.
- `Undo` hanya tersedia maksimal 5 menit setelah checkout.
- Bulk action:
  - `Checkout Terpilih` untuk baris yang dicentang.
  - `Checkout Semua Aktif` sesuai filter aktif.

## Filter Data Kunjungan
Filter utama (panel kiri/kanan sesuai layout) mendukung:
- Tanggal (`custom`, `hari ini`, `kemarin`, `7 hari`)
- Cabang
- Keyword (nama/kode/tujuan)
- `Belum checkout saja`
- Baris per halaman

## Audit Log (Riwayat Aksi)
Panel audit mendukung:
- Filter `Aksi`
- Filter `Role`
- Pencarian `Keyword` (actor/action/row id/metadata)
- Sort `Terbaru dulu` / `Terlama dulu`
- `Baris per halaman` (`10/15/25/50`)

Tambahan UX:
- Filter action/role/sort/per-page auto-submit.
- Keyword audit auto-submit dengan debounce.
- Tersedia `Clear Filter Audit`.
- Tersedia `View JSON` dan `Copy JSON` untuk metadata per baris.

## Export
- `Export CSV` untuk log kunjungan utama.
- `Export Audit CSV` untuk audit aksi.
- `Export Audit JSON` untuk analisis teknis/integrasi.

## Checklist Harian
- [ ] Semua visitor onsite tercatat check-in.
- [ ] Tidak ada visitor aktif tersisa saat tutup layanan.
- [ ] Audit sudah ditinjau untuk anomali.
- [ ] Export audit dilakukan saat rekap harian/mingguan.

## Troubleshooting Singkat
- `Member tidak ditemukan`: pastikan kode member benar dan masih aktif.
- `Batas undo checkout sudah lewat`: lakukan check-in ulang jika memang perlu.
- Data tidak muncul: cek filter tanggal/cabang/keyword dan status `Belum checkout saja`.
