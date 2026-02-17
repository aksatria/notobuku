# Fitur Utama

Berikut ringkasan fitur dengan format cepat baca.

| Fitur | Apa Itu | Gunanya |
| --- | --- | --- |
| Export MARC21 lengkap (Leader, 00X, 1XX/7XX, 245, 264, 3XX, 5XX, 6XX, 8XX, 856) | Fitur untuk mengekspor data katalog dalam format standar internasional MARC21. | Data buku bisa ditukar dengan sistem lain tanpa input ulang. |
| Profil media berbasis `media_profiles` | Template aturan katalog per jenis bahan (audio/video/map/teks/serial). | Isian metadata lebih konsisten dan input lebih cepat. |
| Konsistensi audiobook online (006/007 + 33X + 347/538) | Aturan teknis untuk katalog resource audio online. | Rekaman lebih valid dan aman saat integrasi lintas sistem. |
| Dedup field 856 berbasis URL | Pencegahan duplikasi link akses online. | Katalog lebih bersih dan tidak membingungkan pengguna. |
| Relator term/code otomatis | Otomatisasi peran kontributor (author/editor/narrator, dll). | Peran bibliografi lebih jelas untuk pencarian dan standardisasi. |
| Validasi ekspor (008 = 40, bahasa 3 huruf, aturan profile) | Pemeriksaan kualitas sebelum data diekspor. | Mengurangi error saat import/migrasi antar sistem. |
| Modul ILS inti | Modul katalog, sirkulasi, anggota, laporan. | Operasional harian bisa berjalan end-to-end dalam satu aplikasi. |
| Modul tambahan | Stock opname, serial issue, copy cataloging, OPAC, interop. | Mendukung kebutuhan operasional lanjutan dan integrasi data. |

## Catatan
Standar `MARC21/RDA` dipakai agar katalog tetap sesuai praktik internasional, namun tetap bisa disesuaikan kebutuhan lokal perpustakaan Indonesia.
