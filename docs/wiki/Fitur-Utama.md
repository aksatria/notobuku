# Fitur Utama

Berikut penjelasan sederhana "apa itu" dan "gunanya":

1. Export MARC21 lengkap (Leader, 00X, 1XX/7XX, 245, 264, 3XX, 5XX, 6XX, 8XX, 856)  
Apa itu: fitur untuk mengeluarkan data katalog dalam format standar internasional MARC21.  
Gunanya: data buku bisa dipakai atau ditukar dengan sistem lain (Koha, Alma, dan lain-lain) tanpa input ulang.

2. Profil media berbasis `media_profiles` (audio/video/map/teks/serial)  
Apa itu: template aturan katalog per jenis bahan.  
Gunanya: isian metadata otomatis sesuai tipe koleksi, jadi lebih konsisten dan cepat.

3. Konsistensi audiobook online (006/007 + 33X + 347/538)  
Apa itu: aturan khusus agar rekaman MARC audiobook online terisi field teknis yang benar.  
Gunanya: hasil katalog valid, mudah dibaca sistem lain, dan tidak ditolak saat integrasi.

4. Dedup field 856 berbasis URL  
Apa itu: mencegah link akses online (856) ganda untuk URL yang sama.  
Gunanya: katalog lebih bersih, pengguna tidak bingung karena link dobel.

5. Relator term/code otomatis (author/editor/narrator, dll)  
Apa itu: sistem otomatis memberi peran kontributor (misalnya penulis, editor, narator).  
Gunanya: peran orang di data bibliografi jelas, pencarian dan standar katalog lebih baik.

6. Validasi ekspor (008 panjang 40, kode bahasa 3 huruf, aturan profile)  
Apa itu: pemeriksaan otomatis sebelum data diekspor.  
Gunanya: mencegah data salah format, mengurangi error saat import ke sistem lain.

7. Modul ILS inti: katalog, sirkulasi, anggota, laporan  
Apa itu: fungsi dasar operasional perpustakaan.  
Gunanya: kerja harian berjalan lengkap dalam satu aplikasi (input buku, pinjam-kembali, kelola anggota, laporan).

8. Modul tambahan: stock opname, serial issue, copy cataloging, OPAC dan interop  
Apa itu: fitur lanjutan untuk kebutuhan operasional nyata.  
Gunanya:
- Stock opname: cek fisik koleksi vs data sistem.
- Serial issue: kelola majalah atau jurnal terbitan berkala.
- Copy cataloging: ambil data katalog dari sumber lain agar cepat.
- OPAC: katalog online untuk pemustaka.
- Interop: integrasi atau pertukaran data antar sistem.
