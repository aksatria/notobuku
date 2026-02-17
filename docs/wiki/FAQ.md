# FAQ NOTOBUKU

Panduan ini dibuat untuk pustakawan dan operator harian agar cepat menemukan jawaban tanpa membaca dokumen panjang.

## Daftar Isi
1. [Dasar Penggunaan](#dasar-penggunaan)
2. [Katalog dan OPAC](#katalog-dan-opac)
3. [Keanggotaan dan Sirkulasi](#keanggotaan-dan-sirkulasi)
4. [MARC21/RDA dan Interoperabilitas](#marc21rda-dan-interoperabilitas)
5. [Troubleshooting Cepat](#troubleshooting-cepat)
6. [Rujukan Lanjutan](#rujukan-lanjutan)

## Dasar Penggunaan
### 1) Apa itu NOTOBUKU?
NOTOBUKU adalah sistem manajemen perpustakaan untuk:
- katalog bibliografi dan eksemplar
- keanggotaan
- sirkulasi (pinjam, kembali, perpanjang, denda)
- laporan operasional
- OPAC
- pertukaran metadata standar (`MARC21/RDA`)

### 2) Siapa pengguna NOTOBUKU?
- `super_admin`: konfigurasi kebijakan, monitoring, governance.
- `admin/staff`: operasional harian perpustakaan.
- `member`: pencarian OPAC, pinjaman, reservasi, notifikasi.

### 3) Urutan kerja harian yang disarankan?
1. Login dan cek notifikasi/error dashboard.
2. Proses katalog/keanggotaan yang belum selesai.
3. Jalankan layanan sirkulasi.
4. Cek antrian masalah (jika ada).
5. Export laporan harian.
6. Tutup layanan dan logout.

## Katalog dan OPAC
### 4) Data minimal saat input katalog apa?
Minimal:
- judul
- pengarang
- subjek

Disarankan lengkapi:
- ISBN
- DDC
- tahun terbit
- penerbit
- lokasi rak
- barcode eksemplar

### 5) Kenapa buku tidak muncul di OPAC?
Penyebab paling umum:
1. Data bibliografi belum tersimpan sempurna.
2. Item belum aktif/tersedia.
3. Index pencarian belum terbarui.
4. Filter OPAC terlalu sempit.

Langkah cek cepat:
1. Cari buku di backoffice katalog.
2. Pastikan status item aktif.
3. Uji pencarian OPAC tanpa filter.
4. Ulang indexing/search sync jika dibutuhkan.

### 6) Kenapa hasil pencarian kurang relevan?
Periksa:
- ejaan judul/pengarang/subjek
- konsistensi metadata
- penggunaan subjek yang seragam
- parameter filter di OPAC

## Keanggotaan dan Sirkulasi
### 7) Kenapa transaksi pinjam gagal?
Penyebab umum:
1. Anggota nonaktif.
2. Batas pinjaman sudah penuh.
3. Buku sedang dipinjam anggota lain.
4. Barcode tidak valid/tidak terbaca.

Langkah penyelesaian:
1. Verifikasi status anggota.
2. Cek limit pinjaman.
3. Cek status item.
4. Scan ulang barcode atau cek input manual.

### 8) Bagaimana penanganan denda keterlambatan?
1. Sistem menampilkan nominal denda sesuai kebijakan.
2. Petugas verifikasi tanggal jatuh tempo dan tanggal kembali.
3. Proses denda sesuai SOP internal perpustakaan.
4. Catat transaksi jika ada pengecualian/keringanan.

### 9) Apa keunggulan modul keanggotaan?
- Data anggota lebih terstruktur.
- Import anggota mempercepat onboarding.
- Status dan riwayat layanan anggota lebih mudah dipantau.

### 10) Apa keunggulan modul sirkulasi?
- Alur pinjam/kembali/perpanjang lebih cepat.
- Kontrol denda lebih konsisten.
- Jejak transaksi membantu audit dan evaluasi layanan.

## MARC21/RDA dan Interoperabilitas
### 11) Untuk apa fitur export MARC21?
Untuk pertukaran data bibliografi antar sistem perpustakaan, migrasi, dan standarisasi metadata.

### 12) Apakah MARC21/RDA standar internasional?
Ya.
- `MARC21`: standar internasional format/struktur data bibliografi.
- `RDA`: standar internasional aturan isi deskripsi bibliografi.

Ringkasnya:
- `RDA` = cara menulis isi metadata.
- `MARC21` = format penyimpanan/pertukaran metadata.

### 13) Kenapa fitur MARC21/RDA penting untuk perpustakaan di Indonesia?
Karena membantu:
1. kualitas metadata lebih rapi dan konsisten
2. migrasi data lama ke sistem baru lebih aman
3. kolaborasi antar institusi lebih mudah
4. kesiapan integrasi nasional/internasional
5. adaptasi kebutuhan lokal tanpa kehilangan standar

### 14) Apa manfaat `media_profiles`?
`media_profiles` memastikan aturan metadata sesuai jenis koleksi (teks, audio, video, serial), sehingga katalog tidak campur-aduk antar format.

## Troubleshooting Cepat
### 15) Jika sistem lambat, apa yang harus dilakukan dulu?
1. Cek koneksi jaringan lokal.
2. Uji modul lain (apakah lambat di semua menu atau satu menu saja).
3. Catat jam kejadian dan user yang terdampak.
4. Catat query/aksi yang memicu lambat.
5. Eskalasi ke admin teknis dengan data catatan tersebut.

### 16) Jika terjadi error berulang, data apa yang wajib dicatat?
- waktu kejadian
- nama user
- menu/fitur yang dipakai
- ID anggota/barcode (jika relevan)
- pesan error lengkap
- langkah sebelum error muncul

## Rujukan Lanjutan
- In-app docs: `/docs`
- SOP pemula: `SOP-Harian-Pustakawan-Pemula`
- Keunggulan produk: `Keunggulan-NOTOBUKU`
