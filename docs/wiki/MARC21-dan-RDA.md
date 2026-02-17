# MARC21 dan RDA

## Prinsip dasar
- `RDA` mengatur isi deskripsi bibliografi.
- `MARC21` mengatur struktur penyimpanan data bibliografi.

## Konfigurasi utama
- File default: `config/marc.php`
- Parameter penting:
  - `place_codes` untuk 008/15-17
  - `media_profiles` untuk Leader/006/007/008
  - `ddc_edition` opsional untuk isian `$2` pada 082

## Override via Admin
- Menu: `Admin -> MARC Settings`
- Route: `/admin/marc/settings`
- Bisa edit JSON dan preview XML sebelum simpan.
- Bisa reset ke default.

## Validasi minimum
- Panjang 008 harus 40 karakter.
- Kode bahasa 3 huruf.
- Resource online wajib punya 856.
- Konsistensi profile media harus terpenuhi.
