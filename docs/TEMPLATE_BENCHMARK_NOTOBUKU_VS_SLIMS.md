# Template Benchmark Notobuku vs SLiMS (Online)

Dokumen ini dipakai untuk membandingkan performa operasional nyata antara Notobuku dan SLiMS pada kebutuhan perpustakaan yang wajib online.

## 1) Identitas Uji

- Tanggal uji:
- Lokasi:
- Penguji:
- Periode trafik:
- Catatan lingkungan:
  - CPU/RAM server Notobuku:
  - CPU/RAM server SLiMS:
  - Database:
  - Jumlah data koleksi saat uji:

## 2) Kriteria Keberhasilan (Target)

- Waktu pinjam per transaksi: `< 45 detik`
- Waktu kembali per transaksi: `< 40 detik`
- OPAC p95 latency: `< 800 ms`
- OPAC error rate: `< 1%`
- Export laporan utama: `< 10 detik`
- Uptime layanan: `>= 99.5%`

## 3) Skenario Uji Wajib

| No | Skenario | Jumlah Run | Notobuku (avg) | SLiMS (avg) | Menang |
| --- | --- | ---: | ---: | ---: | --- |
| 1 | Cari buku di OPAC (keyword umum) | 30 |  |  |  |
| 2 | Buka detail koleksi dari OPAC | 30 |  |  |  |
| 3 | Proses pinjam 1 item (barcode) | 20 |  |  |  |
| 4 | Proses kembali 1 item (barcode) | 20 |  |  |  |
| 5 | Perpanjang pinjaman | 20 |  |  |  |
| 6 | Input katalog baru + 1 eksemplar | 15 |  |  |  |
| 7 | Export laporan sirkulasi CSV | 10 |  |  |  |
| 8 | Export laporan sirkulasi XLSX | 10 |  |  |  |
| 9 | Import anggota CSV (100 baris) | 5 |  |  |  |
| 10 | Backup + verifikasi restore drill | 3 |  |  |  |

## 4) Kualitas Operasional (Skor 1-5)

| Aspek | Bobot | Notobuku | SLiMS | Catatan |
| --- | ---: | ---: | ---: | --- |
| Kecepatan kerja staf (klik lebih sedikit, flow jelas) | 25 |  |  |  |
| Kekuatan OPAC online (cepat, stabil, SEO) | 25 |  |  |  |
| Kualitas laporan & export | 15 |  |  |  |
| Reliability (backup, alert, observability) | 20 |  |  |  |
| Onboarding/panduan tim | 15 |  |  |  |
| **Total berbobot** | **100** |  |  |  |

Rumus total berbobot:
- Nilai akhir = `(Skor x Bobot)` tiap aspek, lalu dijumlahkan.

## 5) Insiden Selama Uji

| Waktu | Sistem | Modul | Gejala | Dampak | Akar masalah | Status |
| --- | --- | --- | --- | --- | --- | --- |
|  |  |  |  |  |  |  |

## 6) Ringkasan Biaya Operasional (Bulanan)

| Komponen | Notobuku | SLiMS | Catatan |
| --- | ---: | ---: | --- |
| Hosting/server |  |  |  |
| Domain + SSL |  |  |  |
| Backup/storage |  |  |  |
| Monitoring/alerting |  |  |  |
| Waktu maintenance tim (jam) |  |  |  |
| **Total** |  |  |  |

## 7) Keputusan

- Sistem terpilih:
- Alasan utama (maks 3 poin):
  1.
  2.
  3.
- Risiko yang masih harus ditutup:
  1.
  2.
- Rencana 30 hari setelah go-live:
  1.
  2.
  3.

## 8) Lampiran Bukti

- Screenshot metrik OPAC:
- File export laporan (CSV/XLSX):
- Log error penting:
- Hasil backup/restore drill:
- Catatan UAT operator:
