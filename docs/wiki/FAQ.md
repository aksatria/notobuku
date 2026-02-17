# FAQ

## Tentang NOTOBUKU
**Q: Apa itu NOTOBUKU?**  
**A:** Sistem manajemen perpustakaan untuk katalog, sirkulasi, keanggotaan, laporan, OPAC, dan interoperabilitas metadata (`MARC21/RDA`).

**Q: Siapa pengguna NOTOBUKU?**  
**A:**  
- `super_admin`: governance, konfigurasi, monitoring  
- `admin/staff`: operasional harian  
- `member`: pencarian OPAC, pinjaman, reservasi, notifikasi

## Operasional Harian
**Q: Urutan kerja harian paling aman seperti apa?**  
**A:** Login -> perbarui katalog -> proses sirkulasi -> cek anggota/notifikasi -> export laporan -> cek error -> tutup layanan.

**Q: Data minimal saat input katalog apa saja?**  
**A:** Judul, pengarang, subjek. Disarankan lengkapi ISBN, DDC, tahun, penerbit, lokasi eksemplar.

## Masalah Umum
**Q: Kenapa buku tidak muncul di OPAC?**  
**A:** Umumnya karena data belum lengkap/tersimpan, item belum aktif, index pencarian belum terbarui, atau filter terlalu sempit.

**Q: Kenapa transaksi pinjam gagal?**  
**A:** Umumnya karena status anggota tidak aktif, limit pinjaman penuh, buku sedang dipinjam orang lain, atau barcode tidak valid.

**Q: Bagaimana penanganan denda keterlambatan?**  
**A:** Sistem menampilkan denda sesuai kebijakan; petugas verifikasi dan proses sesuai SOP perpustakaan.

## MARC21 / RDA
**Q: Untuk apa fitur export MARC21?**  
**A:** Untuk pertukaran data bibliografi antar sistem, mendukung migrasi dan standarisasi metadata.

**Q: Apakah MARC21/RDA itu aturan internasional?**  
**A:** Ya.  
- `MARC21`: standar internasional format/struktur data bibliografi  
- `RDA`: standar internasional aturan isi deskripsi bibliografi  

Ringkasnya: `RDA` mengatur isi, `MARC21` mengatur wadah/format pertukaran data.

**Q: Kenapa fitur MARC21/RDA penting untuk perpustakaan Indonesia?**  
**A:** Agar kualitas metadata lebih konsisten, migrasi lebih mudah, kolaborasi antar institusi lebih lancar, dan tetap bisa menyesuaikan kebutuhan lokal.

**Q: Apa manfaat `media_profiles`?**  
**A:** Menjaga konsistensi metadata per jenis koleksi (teks/audio/video/serial).

## Panduan Lanjutan
**Q: Di mana panduan lengkap?**  
**A:**  
- In-app docs: `/docs`  
- SOP pemula: `SOP-Harian-Pustakawan-Pemula`  
- Keunggulan: `Keunggulan-NOTOBUKU`
