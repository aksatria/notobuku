@extends('layouts.notobuku')

@section('title', 'Panduan Pengguna - NOTOBUKU')

@section('content')
@php
  $role = (string) (auth()->user()->role ?? 'member');
  $roleLabel = match ($role) {
    'super_admin' => 'Super Admin',
    'admin' => 'Admin',
    'staff' => 'Petugas',
    'member' => 'Anggota',
    default => ucfirst((string) $role),
  };
  $isStaffRole = in_array($role, ['super_admin', 'admin', 'staff'], true);
@endphp

<style>
  .doc-wrap { max-width: 1240px; margin: 0 auto; }
  .doc-card { border: 1px solid var(--nb-border); border-radius: 18px; padding: 16px; background: var(--nb-surface); }
  .doc-muted { color: var(--nb-muted); font-size: 13px; }
  .doc-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
  .doc-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
  .doc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
  .doc-table th, .doc-table td { border-bottom: 1px solid var(--nb-border); padding: 9px 8px; text-align: left; vertical-align: top; }
  .doc-table th { color: var(--nb-muted); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
  .doc-table tr:last-child td { border-bottom: 0; }

  .doc-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
  .doc-tab {
    border: 1px solid var(--nb-border);
    border-radius: 999px;
    padding: 8px 12px;
    background: var(--nb-surface);
    font-size: 13px;
    font-weight: 700;
    color: var(--nb-text);
    cursor: pointer;
  }
  .doc-tab[aria-selected="true"] {
    color: #fff;
    border-color: transparent;
    background: linear-gradient(90deg, #1e88e5, #1565c0);
  }

  .doc-panel { display: none; margin-top: 14px; }
  .doc-panel.is-active { display: block; }

  .doc-tone-blue { background: rgba(30,136,229,.09); border-color: rgba(30,136,229,.22); }
  .doc-tone-green { background: rgba(39,174,96,.09); border-color: rgba(39,174,96,.22); }
  .doc-tone-violet { background: rgba(139,92,246,.10); border-color: rgba(139,92,246,.24); }
  .doc-tone-amber { background: rgba(245,158,11,.11); border-color: rgba(245,158,11,.24); }
  .doc-tone-indigo { background: rgba(99,102,241,.11); border-color: rgba(99,102,241,.24); }
  .doc-tone-fuchsia { background: rgba(217,70,239,.11); border-color: rgba(217,70,239,.24); }
  .doc-tone-cyan { background: rgba(6,182,212,.11); border-color: rgba(6,182,212,.24); }
  .doc-tone-rose { background: rgba(244,63,94,.11); border-color: rgba(244,63,94,.24); }
  .doc-tone-slate { background: rgba(51,65,85,.07); border-color: rgba(51,65,85,.20); }

  @media (max-width: 980px) {
    .doc-grid-2, .doc-grid-3 { grid-template-columns: 1fr; }
  }
</style>

<div class="doc-wrap px-4 pb-16 pt-10">
  <div class="doc-card">
    <div class="doc-muted" style="font-weight:700;">NOTOBUKU - Dokumentasi Pengguna</div>
    <h1 style="margin:6px 0 0; font-size:26px; font-weight:900;">Panduan Penggunaan Aplikasi</h1>
    <p class="doc-muted" style="margin-top:8px;">Panduan lengkap per peran. Anda login sebagai <strong>{{ $roleLabel }}</strong>.</p>

    <div class="doc-tabs" role="tablist" aria-label="Tab dokumentasi">
      <button class="doc-tab" type="button" role="tab" aria-selected="true" data-doc-tab="overview">Ringkasan</button>
      <button class="doc-tab" type="button" role="tab" aria-selected="false" data-doc-tab="panduan">Panduan Lengkap</button>
      <button class="doc-tab" type="button" role="tab" aria-selected="false" data-doc-tab="marc">MARC/RDA</button>
      <button class="doc-tab" type="button" role="tab" aria-selected="false" data-doc-tab="katalog">Field Katalog</button>
      <button class="doc-tab" type="button" role="tab" aria-selected="false" data-doc-tab="superadmin">Super Admin</button>
      <button class="doc-tab" type="button" role="tab" aria-selected="false" data-doc-tab="staff">Petugas/Admin</button>
      <button class="doc-tab" type="button" role="tab" aria-selected="false" data-doc-tab="member">Anggota</button>
      <button class="doc-tab" type="button" role="tab" aria-selected="false" data-doc-tab="ops">SOP & FAQ</button>
    </div>
  </div>

  <section class="doc-panel is-active" data-doc-panel="overview" role="tabpanel">
    <div class="doc-card doc-tone-cyan" style="margin-bottom:12px;">
      <h3 style="margin:0; font-size:16px; font-weight:900;">Pembaruan Terbaru Dokumentasi</h3>
      <ul style="margin:8px 0 0; padding-left:14px; font-size:13px; line-height:1.7;">
        <li>Modul baru: <strong>Stock Opname</strong> (buat sesi, mulai, pindai barcode, selesaikan, ekspor CSV).</li>
        <li>Modul baru: <strong>Copy Cataloging</strong> (SRU/Z39.50 gateway/P2P, cari rekaman, impor ke katalog).</li>
        <li>Checklist UAT diperluas untuk dua modul klasik ini agar siap operasional produksi.</li>
      </ul>
    </div>

    <div class="doc-grid-3">
      <div class="doc-card doc-tone-blue">
        <h3 style="margin:0; font-size:16px; font-weight:900;">Mulai Cepat</h3>
        <ol style="margin:10px 0 0 18px; font-size:13px; line-height:1.6;">
          <li>Buka menu sesuai peran.</li>
          <li>Gunakan <code>Ctrl+K</code> untuk cari menu.</li>
          <li>Cek notifikasi untuk tugas tertunda.</li>
        </ol>
      </div>
      <div class="doc-card doc-tone-green">
        <h3 style="margin:0; font-size:16px; font-weight:900;">Standar Data</h3>
        <ul style="margin:10px 0 0; padding-left:14px; font-size:13px; line-height:1.6;">
          <li>Kode unik konsisten (member_code/barcode/issue_code).</li>
          <li>Gunakan format tanggal valid.</li>
          <li>Selalu lakukan pratinjau sebelum impor massal.</li>
        </ul>
      </div>
      <div class="doc-card doc-tone-amber">
        <h3 style="margin:0; font-size:16px; font-weight:900;">Troubleshooting</h3>
        <ul style="margin:10px 0 0; padding-left:14px; font-size:13px; line-height:1.6;">
          <li>Data kosong: cek filter tanggal/cabang/status.</li>
          <li>Impor gagal: unduh error CSV, perbaiki, ulang pratinjau.</li>
          <li>Tombol hilang: cek peran pengguna.</li>
        </ul>
      </div>
    </div>

    <div class="doc-card doc-tone-slate" style="margin-top:12px;">
      <h3 style="margin:0; font-size:16px; font-weight:900;">Matriks Akses Peran</h3>
      <div style="overflow:auto; margin-top:8px;">
        <table class="doc-table">
          <thead>
            <tr><th>Fitur</th><th>Super Admin</th><th>Petugas/Admin</th><th>Anggota</th></tr>
          </thead>
          <tbody>
            <tr><td>Katalog lihat</td><td>Ya</td><td>Ya</td><td>Ya</td></tr>
            <tr><td>Katalog kelola</td><td>Ya</td><td>Ya</td><td>Tidak</td></tr>
            <tr><td>Transaksi sirkulasi</td><td>Ya</td><td>Ya</td><td>Tidak</td></tr>
            <tr><td>Impor anggota</td><td>Ya</td><td>Ya</td><td>Tidak</td></tr>
            <tr><td>Laporan ekspor</td><td>Ya</td><td>Ya</td><td>Tidak</td></tr>
            <tr><td>Serial claim workflow</td><td>Ya</td><td>Ya</td><td>Tidak</td></tr>
            <tr><td>Stock opname</td><td>Ya</td><td>Ya</td><td>Tidak</td></tr>
            <tr><td>Copy cataloging (SRU/Z39.50/P2P)</td><td>Ya</td><td>Ya</td><td>Tidak</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="doc-panel" data-doc-panel="panduan" role="tabpanel">
    <div class="doc-card doc-tone-slate">
      <h3 style="margin:0; font-size:16px; font-weight:900;">Panduan Modul Operasional (Fungsi + Cara Penggunaan)</h3>
      <p class="doc-muted" style="margin-top:8px;">Bagian ini merangkum fungsi inti tiap modul dan langkah pakai praktis untuk operasional harian.</p>
    </div>

    <div class="doc-grid-2" style="margin-top:12px;">
      <div class="doc-card doc-tone-blue">
        <h3 style="margin:0; font-size:16px; font-weight:900;">1) Katalog</h3>
        <p style="margin-top:8px; font-size:13px;"><strong>Fungsi:</strong> Menyimpan metadata bibliografi dan data eksemplar koleksi.</p>
        <ol style="margin:8px 0 0 18px; font-size:13px; line-height:1.7;">
          <li>Buka menu <strong>Katalog</strong> -> klik <strong>Tambah Bibliografi</strong>.</li>
          <li>Isi field wajib: judul, pengarang, penerbit, tahun, ISBN/DDC/nomor panggil sesuai kebutuhan.</li>
          <li>Simpan bibliografi, lalu tambah eksemplar (barcode, cabang, rak, status).</li>
          <li>Gunakan fitur pencarian untuk validasi apakah data sudah masuk.</li>
        </ol>
      </div>

      <div class="doc-card doc-tone-green">
        <h3 style="margin:0; font-size:16px; font-weight:900;">2) Sirkulasi</h3>
        <p style="margin-top:8px; font-size:13px;"><strong>Fungsi:</strong> Proses pinjam, kembali, perpanjang, serta pengelolaan denda.</p>
        <ol style="margin:8px 0 0 18px; font-size:13px; line-height:1.7;">
          <li><strong>Pinjam:</strong> pilih anggota -> scan barcode item -> simpan transaksi.</li>
          <li><strong>Kembali:</strong> scan barcode item -> konfirmasi pengembalian -> cek keterlambatan.</li>
          <li><strong>Perpanjang:</strong> cari pinjaman aktif -> proses perpanjangan sesuai aturan.</li>
          <li><strong>Denda:</strong> buka menu denda untuk recalculation, pembayaran, atau void.</li>
        </ol>
      </div>

      <div class="doc-card doc-tone-violet">
        <h3 style="margin:0; font-size:16px; font-weight:900;">3) Anggota</h3>
        <p style="margin-top:8px; font-size:13px;"><strong>Fungsi:</strong> Kelola data anggota secara manual maupun massal (CSV).</p>
        <ol style="margin:8px 0 0 18px; font-size:13px; line-height:1.7;">
          <li>Tambah/edit anggota dari menu <strong>Anggota</strong>.</li>
          <li>Untuk impor massal: upload CSV -> <strong>Pratinjau</strong> -> cek duplikat/error -> <strong>Konfirmasi</strong>.</li>
          <li>Unduh <strong>CSV Error</strong> jika ada baris invalid.</li>
          <li>Jika salah impor, gunakan <strong>Batalkan batch terakhir</strong>.</li>
        </ol>
      </div>

      <div class="doc-card doc-tone-amber">
        <h3 style="margin:0; font-size:16px; font-weight:900;">4) Laporan</h3>
        <p style="margin-top:8px; font-size:13px;"><strong>Fungsi:</strong> Monitoring KPI operasional dan ekspor audit periodik.</p>
        <ol style="margin:8px 0 0 18px; font-size:13px; line-height:1.7;">
          <li>Pilih rentang tanggal, cabang, operator, dan status pinjaman.</li>
          <li>Klik <strong>Terapkan</strong> untuk memuat data KPI dan tabel detail.</li>
          <li>Ekspor data per kategori (CSV/XLSX): sirkulasi, overdue, denda, pengadaan, anggota, serial.</li>
          <li>Gunakan laporan audit untuk rekonsiliasi bulanan.</li>
        </ol>
      </div>

      <div class="doc-card doc-tone-cyan">
        <h3 style="margin:0; font-size:16px; font-weight:900;">5) Serial Issue</h3>
        <p style="margin-top:8px; font-size:13px;"><strong>Fungsi:</strong> Kontrol terbitan berkala (issue terjadwal, diterima, hilang, klaim).</p>
        <ol style="margin:8px 0 0 18px; font-size:13px; line-height:1.7;">
          <li>Tambah issue baru (kode issue, volume/nomor, tanggal terbit, tanggal terjadwal).</li>
          <li>Update status sesuai kondisi: <strong>Diterima</strong>, <strong>Tandai hilang</strong>, atau <strong>Klaim</strong>.</li>
          <li>Gunakan filter status/cabang/tanggal untuk monitoring backlog.</li>
          <li>Ekspor CSV/XLSX untuk audit serial periodik.</li>
        </ol>
      </div>

      <div class="doc-card doc-tone-rose">
        <h3 style="margin:0; font-size:16px; font-weight:900;">6) Stock Opname</h3>
        <p style="margin-top:8px; font-size:13px;"><strong>Fungsi:</strong> Audit stok fisik koleksi berdasarkan barcode.</p>
        <ol style="margin:8px 0 0 18px; font-size:13px; line-height:1.7;">
          <li>Buat sesi opname (opsional filter cabang/rak/status item).</li>
          <li>Mulai sesi, lalu pindai barcode item satu per satu.</li>
          <li>Tutup sesi untuk menghasilkan ringkasan: target, temuan, hilang, tak terduga.</li>
          <li>Ekspor hasil sesi dalam CSV sebagai dokumen audit.</li>
        </ol>
      </div>

      <div class="doc-card doc-tone-indigo">
        <h3 style="margin:0; font-size:16px; font-weight:900;">7) Copy Cataloging (SRU/Z39.50/P2P)</h3>
        <p style="margin-top:8px; font-size:13px;"><strong>Fungsi:</strong> Mengambil metadata dari sumber eksternal untuk mempercepat input katalog.</p>
        <ol style="margin:8px 0 0 18px; font-size:13px; line-height:1.7;">
          <li>Tambahkan sumber (nama, protokol, endpoint).</li>
          <li>Jalankan pencarian berdasarkan judul/ISBN/pengarang.</li>
          <li>Pilih hasil dan klik <strong>Impor</strong> untuk membuat bibliografi lokal.</li>
          <li>Buka bibliografi hasil impor di menu Katalog lalu lengkapi metadata lokal jika perlu.</li>
        </ol>
      </div>

      <div class="doc-card doc-tone-fuchsia">
        <h3 style="margin:0; font-size:16px; font-weight:900;">8) OPAC & Interop</h3>
        <p style="margin-top:8px; font-size:13px;"><strong>Fungsi:</strong> Akses publik katalog dan interoperabilitas (OAI/SRU) untuk discovery lintas sistem.</p>
        <ol style="margin:8px 0 0 18px; font-size:13px; line-height:1.7;">
          <li>Gunakan OPAC untuk pencarian publik dan pemeriksaan ketersediaan.</li>
          <li>Pantau metrik OPAC/interop dari dashboard admin (p95, error rate, burn-rate).</li>
          <li>Jika health menurun, gunakan refresh/manual troubleshooting sebelum eskalasi.</li>
          <li>Simpan hasil ekspor metrik untuk audit performa bulanan.</li>
        </ol>
      </div>
    </div>
  </section>

  <section class="doc-panel" data-doc-panel="marc" role="tabpanel">
    <div class="doc-grid-3">
      <div class="doc-card doc-tone-blue">
        <h3 style="margin:0; font-size:16px; font-weight:900;">MARC</h3>
        <p style="margin-top:8px; font-size:13px;">Format struktur data bibliografi agar data bisa diproses mesin.</p>
      </div>
      <div class="doc-card doc-tone-green">
        <h3 style="margin:0; font-size:16px; font-weight:900;">MARC21</h3>
        <p style="margin-top:8px; font-size:13px;">Standar MARC yang dipakai luas. Contoh tag: <code>245</code>, <code>100</code>, <code>082</code>.</p>
      </div>
      <div class="doc-card doc-tone-violet">
        <h3 style="margin:0; font-size:16px; font-weight:900;">RDA</h3>
        <p style="margin-top:8px; font-size:13px;">Aturan isi katalog modern: apa yang ditulis dan cara menulisnya.</p>
      </div>
    </div>

    <div class="doc-card doc-tone-amber" style="margin-top:12px;">
      <h3 style="margin:0; font-size:16px; font-weight:900;">Kenapa Dipakai</h3>
      <ul style="margin:8px 0 0; padding-left:14px; font-size:13px; line-height:1.7;">
        <li>Konsistensi data antar pustakawan dan antar cabang.</li>
        <li>Interoperabilitas lintas sistem (impor/ekspor, OAI/SRU, union catalog).</li>
        <li>Pencarian OPAC lebih akurat dan minim duplikasi metadata.</li>
        <li>Naikkan kualitas praktik perpustakaan ke standar profesional.</li>
      </ul>
      <p style="margin-top:8px; font-size:13px;">Hambatan umumnya di lapangan adalah SOP dan pelatihan yang belum merata, bukan sekadar alatnya.</p>
    </div>

    <div class="doc-card doc-tone-slate" style="margin-top:12px;">
      <h3 style="margin:0; font-size:16px; font-weight:900;">Contoh Record MARC21 (Ringkas)</h3>
      <pre style="margin-top:10px; padding:10px; border-radius:10px; border:1px solid var(--nb-border); background:rgba(15,23,42,.05); font-size:12px; overflow:auto;">100 1# $a Sulistyo-Basuki, $e author
245 10 $a Pengantar Ilmu Perpustakaan : $b teori dan praktik
264 #1 $a Jakarta : $b Gramedia, $c 2023
300 ## $a xii, 245 hlm. ; $c 24 cm
020 ## $a 9786020321234
082 04 $a 020
650 #4 $a Ilmu perpustakaan
336 ## $a text $2 rdacontent
337 ## $a unmediated $2 rdamedia
338 ## $a volume $2 rdacarrier</pre>
    </div>
  </section>

  <section class="doc-panel" data-doc-panel="katalog" role="tabpanel">
    <div class="doc-grid-2">
      <div class="doc-card doc-tone-indigo">
        <h3 style="margin:0; font-size:16px; font-weight:900;">Contoh Pengisian Cepat (Buku)</h3>
        <ul style="margin:8px 0 0; padding-left:14px; font-size:13px; line-height:1.6;">
          <li><code>title</code>: Pengantar Ilmu Perpustakaan</li>
          <li><code>subtitle</code>: Teori dan Praktik Layanan Informasi</li>
          <li><code>authors_text</code>: Sulistyo-Basuki</li>
          <li><code>publisher</code>: Gramedia Pustaka Utama</li>
          <li><code>place_of_publication</code>: Jakarta</li>
          <li><code>publish_year</code>: 2023</li>
          <li><code>language</code>: id</li>
          <li><code>isbn</code>: 9786020321234</li>
          <li><code>ddc</code>: 020</li>
          <li><code>call_number</code>: 020 SUL p</li>
        </ul>
      </div>
      <div class="doc-card doc-tone-fuchsia">
        <h3 style="margin:0; font-size:16px; font-weight:900;">Contoh Pengisian Cepat (Serial)</h3>
        <ul style="margin:8px 0 0; padding-left:14px; font-size:13px; line-height:1.6;">
          <li><code>material_type</code>: serial</li>
          <li><code>frequency</code>: Bulanan</li>
          <li><code>former_frequency</code>: Mingguan</li>
          <li><code>serial_beginning</code>: Vol.1 No.1 (2020)-</li>
          <li><code>serial_first_issue</code>: Vol.1 No.1</li>
          <li><code>serial_last_issue</code>: Vol.5 No.12</li>
          <li><code>serial_preceding_title</code>: Jurnal Informasi Lama</li>
          <li><code>serial_preceding_issn</code>: 1412-1234</li>
          <li><code>holdings_summary</code>: Vol.1(2020)-Vol.5(2024)</li>
        </ul>
      </div>
    </div>

    <div class="doc-card doc-tone-cyan" style="margin-top:12px;">
      <h3 style="margin:0; font-size:16px; font-weight:900;">Field -> Fungsi -> Cara Isi</h3>
      <div style="overflow:auto; margin-top:8px;">
        <table class="doc-table">
          <thead>
            <tr><th>Field</th><th>Fungsi</th><th>Cara Isi</th></tr>
          </thead>
          <tbody>
            <tr><td><code>title</code></td><td>Judul utama</td><td>Wajib. Isi sesuai sumber utama.</td></tr>
            <tr><td><code>authors_text</code></td><td>Pengarang</td><td>Wajib. Pisahkan multi pengarang dengan koma.</td></tr>
            <tr><td><code>subjects_text</code></td><td>Subjek/topik</td><td>Pisahkan dengan koma atau titik koma.</td></tr>
            <tr><td><code>isbn</code></td><td>Identifier buku</td><td>Isi ISBN valid 10/13 digit.</td></tr>
            <tr><td><code>ddc</code></td><td>Klasifikasi</td><td>Isi nomor DDC sesuai subjek koleksi.</td></tr>
            <tr><td><code>call_number</code></td><td>Penempatan rak</td><td>Isi nomor panggil lokal institusi.</td></tr>
            <tr><td><code>material_type</code>/<code>media_type</code></td><td>Tipe konten/media</td><td>Pilih sesuai format fisik/isi.</td></tr>
            <tr><td><code>cover</code></td><td>Sampul</td><td>Unggah JPG/PNG/WEBP max 2MB.</td></tr>
            <tr><td><code>copies_count</code></td><td>Eksemplar awal</td><td>Isi jumlah item yang mau dibuat otomatis.</td></tr>
            <tr><td><code>dc_i18n[...]</code></td><td>Metadata multilingual</td><td>Tambahkan locale lalu isi field inti.</td></tr>
            <tr><td><code>identifiers[]</code></td><td>Identifier tambahan</td><td>Isi scheme + value (+ URI opsional).</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="doc-card doc-tone-rose" style="margin-top:12px;">
      <h3 style="margin:0; font-size:16px; font-weight:900;">Mapping ke MARC21</h3>
      <div style="overflow:auto; margin-top:8px;">
        <table class="doc-table">
          <thead><tr><th>Field Form</th><th>MARC21</th><th>Kegunaan</th></tr></thead>
          <tbody>
            <tr><td><code>title</code></td><td><code>245 $a</code></td><td>Judul utama</td></tr>
            <tr><td><code>subtitle</code></td><td><code>245 $b</code></td><td>Subjudul</td></tr>
            <tr><td><code>authors_text</code></td><td><code>100/700 $a</code></td><td>Pengarang utama/tambahan</td></tr>
            <tr><td><code>authors_roles_json</code></td><td><code>100/700 $e/$4</code></td><td>Relator peran</td></tr>
            <tr><td><code>publisher/place/year</code></td><td><code>264 $a/$b/$c</code></td><td>Publikasi</td></tr>
            <tr><td><code>isbn</code></td><td><code>020 $a</code></td><td>ISBN</td></tr>
            <tr><td><code>ddc</code></td><td><code>082 $a</code></td><td>DDC</td></tr>
            <tr><td><code>material_type/media_type</code></td><td><code>336/337/338</code></td><td>RDA content/media/carrier</td></tr>
            <tr><td><code>frequency/former_frequency</code></td><td><code>310/321</code></td><td>Frekuensi serial</td></tr>
            <tr><td><code>serial_beginning/ending</code></td><td><code>362</code></td><td>Chronology serial</td></tr>
            <tr><td><code>serial_preceding/succeeding</code></td><td><code>780/785</code></td><td>Riwayat judul serial</td></tr>
            <tr><td><code>holdings_summary/supplement/index</code></td><td><code>866/867/868</code></td><td>Ringkasan holdings</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="doc-panel" data-doc-panel="superadmin" role="tabpanel">
    <div class="doc-card doc-tone-blue">
      <h3 style="margin:0; font-size:16px; font-weight:900;">Fitur, Fungsi, Cara Pakai - Super Admin</h3>
      <div style="overflow:auto; margin-top:8px;">
        <table class="doc-table">
          <thead><tr><th>Fitur</th><th>Fungsi</th><th>Cara Pemakaian</th></tr></thead>
          <tbody>
            <tr><td>Switch Cabang</td><td>Ubah konteks kerja lintas cabang</td><td>Sidebar -> pilih cabang -> cek indikator aktif</td></tr>
            <tr><td>Laporan</td><td>Audit operasional periodik</td><td>Laporan -> set filter -> Terapkan -> ekspor CSV/XLSX</td></tr>
            <tr><td>Interop Health</td><td>Monitor integrasi OAI/SRU</td><td>Dashboard Admin -> lihat kartu health -> Refresh now saat perlu</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="doc-panel" data-doc-panel="staff" role="tabpanel">
    <div class="doc-card doc-tone-green">
      <h3 style="margin:0; font-size:16px; font-weight:900;">Fitur, Fungsi, Cara Pakai - Petugas/Admin</h3>
      <div style="overflow:auto; margin-top:8px;">
        <table class="doc-table">
          <thead><tr><th>Fitur</th><th>Fungsi</th><th>Cara Pemakaian</th></tr></thead>
          <tbody>
            <tr><td>Pinjam</td><td>Mencatat peminjaman</td><td>Transaksi -> Pinjam -> cari anggota -> scan barcode -> Simpan</td></tr>
            <tr><td>Kembali</td><td>Menutup pinjaman + proses denda</td><td>Transaksi -> Kembali -> scan barcode -> konfirmasi -> Simpan</td></tr>
            <tr><td>Impor CSV Anggota</td><td>Input anggota massal</td><td>Anggota -> Impor -> Pratinjau -> cek duplikat/error -> Konfirmasi</td></tr>
            <tr><td>Batalkan Batch</td><td>Membatalkan impor terakhir</td><td>Anggota -> Batalkan batch terakhir -> konfirmasi</td></tr>
            <tr><td>Serial Workflow</td><td>Kontrol issue serial</td><td>Serial Issue -> tambah -> Claim/Missing/Receive</td></tr>
            <tr><td>Stock Opname</td><td>Audit stok fisik koleksi</td><td>Stock Opname -> buat sesi -> Mulai -> scan barcode -> Selesaikan -> ekspor CSV</td></tr>
            <tr><td>Copy Cataloging</td><td>Ambil metadata bibliografi dari sumber eksternal</td><td>Copy Cataloging -> pilih sumber -> cari -> impor record -> review metadata di Katalog</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="doc-panel" data-doc-panel="member" role="tabpanel">
    <div class="doc-card doc-tone-violet">
      <h3 style="margin:0; font-size:16px; font-weight:900;">Fitur, Fungsi, Cara Pakai - Anggota</h3>
      <div style="overflow:auto; margin-top:8px;">
        <table class="doc-table">
          <thead><tr><th>Fitur</th><th>Fungsi</th><th>Cara Pemakaian</th></tr></thead>
          <tbody>
            <tr><td>Katalog</td><td>Cari dan lihat detail buku</td><td>Buka Katalog -> ketik kata kunci -> buka detail judul</td></tr>
            <tr><td>Pinjaman Saya</td><td>Pantau pinjaman aktif/riwayat</td><td>Menu Anggota -> Pinjaman -> cek status & due date</td></tr>
            <tr><td>Reservasi</td><td>Mengantri judul yang dibutuhkan</td><td>Pilih judul -> buat reservasi -> pantau notifikasi</td></tr>
            <tr><td>Notifikasi</td><td>Update transaksi akun</td><td>Buka Notifikasi -> baca -> tandai selesai</td></tr>
            <tr><td>Pustakawan Digital</td><td>Rekomendasi bacaan</td><td>Buka fitur -> ajukan pertanyaan -> simpan hasil</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="doc-panel" data-doc-panel="ops" role="tabpanel">
    <div class="doc-grid-2">
      <div class="doc-card doc-tone-indigo">
        <h3 style="margin:0; font-size:16px; font-weight:900;">SOP Rutin</h3>
        <ul style="margin:8px 0 0; padding-left:14px; font-size:13px; line-height:1.7;">
          <li><strong>Harian:</strong> cek notifikasi/error, rekonsiliasi transaksi, review serial terlambat.</li>
          <li><strong>Mingguan:</strong> review overdue/denda, kualitas data anggota, validasi laporan cabang, dan sesi stock opname aktif.</li>
          <li><strong>Bulanan:</strong> ekspor arsip laporan, rekap riwayat impor, audit serial claim.</li>
        </ul>
        @if($isStaffRole)
          <div style="margin-top:10px;">
            <a class="doc-tab" href="{{ route('docs.uat-checklist') }}" style="text-decoration:none;">Buka UAT Produksi</a>
          </div>
        @endif
      </div>
      <div class="doc-card doc-tone-amber">
        <h3 style="margin:0; font-size:16px; font-weight:900;">FAQ Cepat</h3>
        <ul style="margin:8px 0 0; padding-left:14px; font-size:13px; line-height:1.7;">
          <li><strong>Data laporan kosong?</strong> Cek filter tanggal/cabang/status.</li>
          <li><strong>Impor gagal?</strong> Unduh error CSV, perbaiki, pratinjau ulang.</li>
          <li><strong>Tombol tidak tampil?</strong> Verifikasi peran akun.</li>
          <li><strong>Lapor bug?</strong> Sertakan waktu, menu, langkah, dan screenshot.</li>
        </ul>
      </div>
    </div>
  </section>
</div>

<script>
  (function () {
    const tabs = Array.from(document.querySelectorAll('[data-doc-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-doc-panel]'));
    if (!tabs.length || !panels.length) return;

    function activate(key) {
      tabs.forEach((t) => {
        const on = t.getAttribute('data-doc-tab') === key;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      panels.forEach((p) => {
        const on = p.getAttribute('data-doc-panel') === key;
        p.classList.toggle('is-active', on);
      });
      try { localStorage.setItem('nb_docs_tab', key); } catch (_) {}
    }

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => activate(tab.getAttribute('data-doc-tab')));
    });

    let initial = 'overview';
    try {
      const saved = localStorage.getItem('nb_docs_tab');
      if (saved && tabs.some((t) => t.getAttribute('data-doc-tab') === saved)) initial = saved;
    } catch (_) {}
    activate(initial);
  })();
</script>
@endsection


