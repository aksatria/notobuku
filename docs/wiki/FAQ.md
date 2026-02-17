# FAQ

## Apa itu NOTOBUKU?
NOTOBUKU adalah sistem manajemen perpustakaan untuk katalog, sirkulasi, keanggotaan, laporan, OPAC, dan interoperabilitas metadata (MARC21/RDA).

## Siapa yang memakai NOTOBUKU?
- `super_admin`: pengaturan, governance, monitoring.
- `admin/staff`: operasional harian (katalog, anggota, sirkulasi, laporan).
- `member`: pencarian OPAC, pinjaman, reservasi, notifikasi.

## Bagaimana urutan kerja harian paling aman?
1. Login.
2. Input/perbarui katalog.
3. Kelola transaksi pinjam-kembali-perpanjang.
4. Cek anggota dan notifikasi.
5. Export laporan harian.
6. Cek error dan tutup layanan.

## Apa data minimal saat input katalog?
Minimal isi judul, pengarang, subjek. Lebih baik lengkapi juga ISBN, DDC, tahun, penerbit, dan lokasi eksemplar.

## Kenapa buku tidak muncul di OPAC?
Penyebab umum:
- Data bibliografi belum lengkap/tersimpan.
- Item belum aktif atau status tidak tersedia.
- Index pencarian belum terbarui.
- Filter OPAC terlalu sempit.

## Kenapa transaksi pinjam gagal?
Penyebab umum:
- Status anggota tidak aktif.
- Batas pinjaman anggota sudah penuh.
- Buku sedang dipinjam anggota lain.
- Barcode salah atau item belum terdaftar benar.

## Bagaimana kalau ada denda keterlambatan?
Sistem menampilkan denda sesuai aturan kebijakan. Petugas memverifikasi nominal, lalu memproses sesuai prosedur perpustakaan.

## Untuk apa fitur export MARC21?
Untuk pertukaran data bibliografi antar sistem perpustakaan. Ini membantu migrasi, integrasi, dan standarisasi metadata.

## Apa manfaat `media_profiles`?
`media_profiles` membuat aturan metadata per jenis koleksi (teks/audio/video/serial) agar hasil katalog konsisten.

## Di mana panduan pengguna lengkap?
- In-app docs: `/docs`
- SOP pemula: `SOP-Harian-Pustakawan-Pemula`
- Keunggulan produk: `Keunggulan-NOTOBUKU`
