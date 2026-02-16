## NOTOBUKU — Sistem Perpustakaan + Exporter MARC21/RDA

NOTOBUKU adalah sistem manajemen perpustakaan dengan exporter MARC21 yang konsisten dengan praktik RDA dan interoperable untuk Koha, Alma, WorldCat, dan ILS lainnya.

![NOTOBUKU](https://img.shields.io/badge/NOTOBUKU-MARC21%2FRDA-blue)
[![circulation-stable](https://github.com/aksatria/notobuku/actions/workflows/circulation-stable.yml/badge.svg?branch=main)](https://github.com/aksatria/notobuku/actions/workflows/circulation-stable.yml)
![Coverage](https://img.shields.io/badge/Coverage-n%2Fa-lightgrey)

## Instalasi Singkat
1. `composer install`
2. `cp .env.example .env`
3. Atur koneksi database di `.env`
4. `php artisan key:generate`
5. `php artisan migrate`
6. (Opsional) `php artisan db:seed` untuk data demo awal

## Quickstart — Docker (local/dev)

Prerequisites: Docker and Docker Compose installed.

1. Start services:

```bash
docker compose up -d
```

2. App will be available at http://localhost:8000 — phpMyAdmin at http://localhost:8080 and Meilisearch at http://localhost:7700.

3. The `app` container runs `composer install` and attempts `php artisan migrate` on first start. To get a shell in the app container:

```bash
docker compose exec app bash
```

4. To run migrations or seed manually:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed
```

5. Useful Makefile shortcuts (see `Makefile`): `make up`, `make down`, `make bash`, `make migrate`, `make test`.

Notes:
- If you prefer nginx+php-fpm, replace the `app` service command and add an `nginx` service in `docker-compose.yml`.
- Adjust `.env` to set `DB_HOST=db`, `MEILISEARCH_HOST=http://meilisearch:7700`, and Redis host `redis` when running via Docker.

## Konfigurasi Ekspor MARC
- Default MARC berada di `config/marc.php`:
  - `place_codes` untuk kode tempat terbit (008/15–17).
  - `media_profiles` untuk Leader/006/007/008 dan variasinya.
- `ddc_edition` (opsional) untuk mengisi `$2` pada 082.
- Override via Admin:
  - Buka `Admin → MARC Settings` (`/admin/marc/settings`).
  - Edit JSON untuk `place_codes_city`, `place_codes_country`, dan `media_profiles`.
  - Tombol **Preview** menampilkan contoh XML MARC dari input formulir.
- Reset ke default:
  - Gunakan tombol **Reset** di halaman MARC Settings.
  
Catatan: badge CI/Coverage saat ini placeholder karena belum ada pipeline CI.

## Fitur Utama
- Export MARC21 lengkap (Leader, 00X, 1XX/7XX, 245, 264, 3XX, 5XX, 6XX, 8XX, 856).
- Profil media berbasis `media_profiles` (audio/video/map/teks/serial).
- Audiobook online: leader/006/007 konsisten audio non-music + 336/337/338 + 347/538.
- Dedup 856 berdasarkan URL.
- Relator term/code otomatis (author/editor/narrator/dll).
- Indikator nama personal: inverted vs direct order (gelar/sufiks dikecualikan; tanggal authority memaksa inverted).
- Validasi ekspor (008 panjang 40, kode bahasa 3 huruf, 856 wajib untuk online, aturan berbasis profile).

## User Documentation
- Dokumentasi pengguna (tab per role): `http://notobuku.test/docs` atau route `docs.index`.
- Tab `Panduan Lengkap` di `/docs` berisi penjelasan fungsi + cara penggunaan per modul:
  - Katalog
  - Sirkulasi
  - Anggota
  - Laporan
  - Serial Issue
  - Stock Opname
  - Copy Cataloging
  - OPAC & Interop
- Ditujukan untuk:
  - `super_admin`: governance, lintas cabang, monitoring.
  - `admin/staff`: operasional harian (katalog, sirkulasi, anggota, laporan, serial).
  - `member`: pencarian, pinjaman, reservasi, notifikasi, pustakawan digital.
- Checklist UAT produksi: route `docs.uat-checklist`.
- Dokumen checklist UAT (markdown): `docs/UAT_PRODUKSI_ILS_CORE_CHECKLIST.md`.

## Standar Metadata (MARC/MARC21/RDA)
- Penjelasan ringkas tersedia di tab `MARC/RDA` pada halaman `/docs`.
- Prinsip:
  - `RDA` mengatur **isi deskripsi** bibliografi.
  - `MARC21` mengatur **struktur penyimpanan** data bibliografi.
- Mapping field form katalog ke MARC21 tersedia di tab `Field Katalog` pada halaman `/docs`.

## Operasional Harian (Ringkas)
1. Katalog: input bibliografi -> tambah eksemplar.
2. Sirkulasi: pinjam/kembali/perpanjang -> review denda.
3. Anggota: import CSV via preview -> confirm -> undo bila perlu.
4. Laporan: filter periode/cabang -> export CSV/XLSX.
5. Serial: kelola expected/missing/claimed/received -> export audit.
6. Stock Opname: buat sesi -> mulai -> scan barcode -> selesaikan -> export CSV.
7. Copy Cataloging: pilih sumber SRU/Z39.50 gateway/P2P -> cari -> impor ke katalog -> review metadata.

## Modul ILS Klasik Tambahan
- `Stock Opname`
  - Route: `stock_takes.index`
  - Fitur: sesi opname, line expected/found/missing/unexpected, export CSV audit.
- `Copy Cataloging Client`
  - Route: `copy_cataloging.index`
  - Fitur: manajemen source (`SRU`, `Z39.50 gateway`, `P2P`), search eksternal, import bibliografi lokal.

## Testing & Stabilitas Environment
- Gunakan database testing terpisah (`notobuku_test`) untuk semua suite.
- Hindari command destruktif di DB non-testing (guard permanen aktif di aplikasi).
- Gunakan script sekuensial stabil:
  - `composer run test:stable` (reset DB testing + full suite)
  - `composer run test:circulation:stable` (reset DB testing + smoke suite sirkulasi)
- Shortcut Docker/Makefile:
  - `make test-stable`
  - `make test-circulation-stable`
- Untuk regresi modul klasik:
  - `php artisan test tests/Feature/StockTakeWorkflowTest.php --env=testing`
  - `php artisan test tests/Feature/CopyCatalogingWorkflowTest.php --env=testing`
- Stabilitas `CopyCatalogingWorkflowTest` sudah diperkuat:
  - migrasi schema testing dijalankan aman di setup test
  - transaksi DB dipakai untuk rollback per test

## Stability-to-10 Ops Pack
- `Catalog Quality Gate` (katalog create/update):
  - Wajib: judul, pengarang, minimal 1 subjek, `ISBN`, dan `DDC`.
  - Warning duplikasi ISBN/judul ter-normalisasi.
- `Zero-result governance` (open queue -> closed):
  - Command triage: `php artisan notobuku:search-zero-triage`
  - Menutup antrean `zero_result_status=open` ke `resolved_auto/ignored` dengan catatan audit.
- Alert SLO OPAC:
  - Command: `php artisan notobuku:opac-slo-alert`
  - Email target via `.env`: `NB_OPAC_SLO_ALERT_EMAIL_TO` + fallback PIC ops `NB_CATALOG_OPS_EMAIL_TO`
- Bukti scale/reliability katalog:
  - Command: `php artisan notobuku:catalog-scale-proof --samples=60`
  - Output JSON: `storage/app/reports/catalog-scale/*.json` (p50/p95/p99/error-rate)
- Sertifikat readiness internal:
  - Command: `php artisan notobuku:readiness-certificate`
  - Strict mode (auto FAIL jika trafik observasi kurang): `php artisan notobuku:readiness-certificate --strict-ready`
  - Output: `storage/app/reports/readiness/readiness-YYYYMMDD.md` + `.json`
- Backup + Restore Drill non-destruktif:
  - Snapshot: `php artisan notobuku:backup-core-snapshot --tag=manual`
  - Drill verifikasi: `php artisan notobuku:backup-restore-drill`
  - Tidak menghapus atau overwrite data aplikasi.
- UAT rutin + sign-off operator:
  - Generate checklist: `php artisan notobuku:uat-generate`
  - Sign-off: `php artisan notobuku:uat-signoff --status=pass --operator=\"Nama Operator\" --note=\"OK\"`
  - Auto sign-off harian: `php artisan notobuku:uat-auto-signoff --strict-ready --window-days=30`
  - Data sign-off tersimpan di tabel `uat_signoffs`.
  - Scheduler default:
    - `notobuku:readiness-certificate --strict-ready --window-days=30` (harian)
    - `notobuku:uat-auto-signoff --strict-ready --window-days=30` (harian, jika `NB_UAT_AUTO_SIGNOFF_ENABLED=true`)

## Kepatuhan & Konsistensi
- MARC21: Leader/006/007/008 konsisten per media profile.
- RDA: 33X dan 347/538 untuk resource digital.
- Authority-friendly: indikator personal name eksplisit (`ind1=1` untuk inverted, `ind1=0` untuk direct).

## Contoh Output (Audiobook Online)
```text
LDR  00000nim a2200000 a 4500
006  i        
007  sd fmnngnn
008  260209s2024    xx o     d           ind

100  10 $a Budi Santosa $e narrator $4 nrt
245  10 $a Kisah Suara Nusantara $b Musim 1 $c Narrated by Budi Santosa
264  _1 $a Bandung $b Penerbit Nusantara $c 2024
300  __ $a 1 online audio file
336  __ $a spoken word $b spw $2 rdacontent
337  __ $a computer $b c $2 rdamedia
338  __ $a online resource $b cr $2 rdacarrier
347  __ $a audio file
538  __ $a Mode of access: World Wide Web.
655  _7 $a Audiobooks $2 local
856  40 $u https://katalog.example.id/audio/kisah-suara-nusantara $y Akses Online
```

## Matrix Uji (Ringkas)
| Area | Cakupan | Status |
| --- | --- | --- |
| MARC21 008 | Panjang 40, place/lang/online/form | ✅ |
| Leader/006/007 | Konsisten per media profile | ✅ |
| Audiobook online | Audio non-music + 33X + 347/538 | ✅ |
| Dedup 856 | URL unik | ✅ |
| Indikator personal name | Inverted vs direct order | ✅ |

## Dibangun Dengan Laravel (Opsional)

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
