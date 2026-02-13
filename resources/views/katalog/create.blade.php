{{-- resources/views/katalog/create.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Tambah Bibliografi • NOTOBUKU')

@section('content')
@php
  $role = auth()->user()->role ?? 'member';
  $canManage = in_array($role, ['super_admin','admin','staff'], true);

  $dcI18n = old('dc_i18n', []);
  if (!is_array($dcI18n)) $dcI18n = [];
  if (empty($dcI18n)) {
    $dcI18n = [
      'id' => [],
      'en' => [],
    ];
  }

  $identifiers = old('identifiers', []);
  if (!is_array($identifiers)) $identifiers = [];
  if (empty($identifiers)) {
    $identifiers = [
      ['scheme' => 'doi', 'value' => '', 'uri' => ''],
      ['scheme' => 'uri', 'value' => '', 'uri' => ''],
    ];
  }

  $authorsRoleMode = (string) old('authors_role_mode', '') === '1';
  $authorsRoleSeed = old('authors_roles_json', []);
  if (is_string($authorsRoleSeed)) {
    $decoded = json_decode($authorsRoleSeed, true);
    if (json_last_error() === JSON_ERROR_NONE) {
      $authorsRoleSeed = $decoded;
    }
  }
  if (!is_array($authorsRoleSeed)) $authorsRoleSeed = [];

  $materialTypeOptions = [
    'buku' => 'Buku (text)',
    'ebook' => 'E-Book (text)',
    'audio' => 'Audio (spoken word / music)',
    'video' => 'Video (moving image)',
    'serial' => 'Serial / Jurnal / Majalah',
    'peta' => 'Peta / Kartografi',
    'skripsi' => 'Skripsi',
    'tesis' => 'Tesis',
    'disertasi' => 'Disertasi',
    'referensi' => 'Referensi',
    'komik' => 'Komik',
    'manual' => 'Manual / Panduan',
    'software' => 'Perangkat Lunak',
    'lainnya' => 'Lainnya',
  ];
  $mediaTypeOptions = [
    'teks' => 'Teks',
    'audio' => 'Audio',
    'video' => 'Video',
    'tanpa_media' => 'Tanpa media (unmediated)',
    'komputer' => 'Komputer',
    'mikro' => 'Mikroform',
    'proyeksi' => 'Proyeksi',
    'stereografik' => 'Stereografik',
    'tak_tertentu' => 'Tak tertentu',
  ];
@endphp

<style>
  /* =========================================================
     NOTOBUKU • Katalog • Create (FINAL)
     - DESAIN TIDAK BERUBAH
     - Quill: toolbar kecil, ikon tidak tebal, NO LINK
     - Cover Buku: upload + preview (UI) -> name="cover" (sinkron controller)
     ========================================================= */

  .kc-wrap{ max-width:1120px; margin:0 auto; }
  .kc-shell{ padding:16px; }

  .kc-head{
    display:flex; align-items:flex-start; justify-content:space-between;
    gap:12px; flex-wrap:wrap; margin-bottom:12px;
  }
  .kc-head .title{ font-weight:900; letter-spacing:.12px; font-size:15px; margin:0; }
  .kc-head .sub{ margin-top:6px; font-size:12.8px; }

  .kc-layout{
    display:grid;
    grid-template-columns:minmax(0,1fr) 360px;
    gap:14px;
    align-items:start;
  }
  .kc-side{ position:sticky; top:14px; }

  .kc-section{
    padding:14px;
    border:1px solid var(--nb-border);
    border-radius:16px;
    background:var(--nb-surface);
  }
  .kc-section + .kc-section{ margin-top:12px; }

  /* Aksen background seperti KPI beranda (tanpa gradasi, tanpa strip atas) */
  .kc-section.acc-blue  { background: rgba(30,136,229,.06); border-color: rgba(30,136,229,.14); }
  .kc-section.acc-green { background: rgba(39,174,96,.06);  border-color: rgba(39,174,96,.14); }
  .kc-section.acc-slate { background: rgba(15,23,42,.035);  border-color: rgba(15,23,42,.10); }
  .kc-section.acc-amber { background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.18); }

  html.dark .kc-section.acc-blue  { background: rgba(30,136,229,.12); border-color: rgba(30,136,229,.18); }
  html.dark .kc-section.acc-green { background: rgba(39,174,96,.12);  border-color: rgba(39,174,96,.18); }
  html.dark .kc-section.acc-slate { background: rgba(148,163,184,.08); border-color: rgba(148,163,184,.14); }
  html.dark .kc-section.acc-amber { background: rgba(245,158,11,.12); border-color: rgba(245,158,11,.22); }

  .kc-section-head{
    display:flex; align-items:flex-start; justify-content:space-between; gap:10px;
    padding-bottom:10px; margin-bottom:12px;
    border-bottom:1px solid var(--nb-border);
  }
  .kc-section-head .h{ font-weight:900; letter-spacing:.1px; font-size:13.5px; }
  .kc-section-head .hint{ font-size:12.5px; margin:0; }

  .kc-toggle{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid var(--nb-border);
    background: var(--nb-surface);
    font-size:12px;
    font-weight:600;
    color: rgba(11,37,69,.85);
  }
  .kc-toggle input{ width:14px; height:14px; }
  html.dark .kc-toggle{
    color: rgba(226,232,240,.9);
    background: rgba(15,23,42,.5);
    border-color: rgba(148,163,184,.2);
  }

  .kc-quality-list{ display:grid; gap:6px; }
  .kc-quality-item{
    padding:8px 10px;
    border-radius:12px;
    border:1px solid var(--nb-border);
    background: rgba(255,255,255,.7);
    font-size:12.2px;
    line-height:1.35;
    color: rgba(11,37,69,.8);
  }
  .kc-quality-item.is-clickable{ cursor:pointer; }
  .kc-quality-item.is-clickable:hover{ filter:brightness(0.98); }
  .kc-quality-item.is-error{
    border-color: rgba(239,68,68,.28);
    background: rgba(239,68,68,.08);
    color: rgba(153,27,27,.95);
  }
  .kc-quality-item.is-warn{
    border-color: rgba(245,158,11,.28);
    background: rgba(245,158,11,.08);
    color: rgba(146,64,14,.95);
  }
  .kc-quality-empty{
    padding:10px;
    border-radius:12px;
    border:1px dashed rgba(148,163,184,.3);
    background: rgba(248,250,252,.6);
    font-size:12.2px;
    color: rgba(11,37,69,.62);
  }
  html.dark .kc-quality-item{
    background: rgba(15,23,42,.45);
    color: rgba(226,232,240,.85);
    border-color: rgba(148,163,184,.2);
  }
  html.dark .kc-quality-item.is-error{
    background: rgba(239,68,68,.16);
    color: rgba(254,226,226,.9);
  }
  html.dark .kc-quality-item.is-warn{
    background: rgba(245,158,11,.16);
    color: rgba(254,243,199,.95);
  }
  html.dark .kc-quality-empty{
    background: rgba(15,23,42,.45);
    color: rgba(226,232,240,.7);
    border-color: rgba(148,163,184,.2);
  }

  .kc-grid-1{ display:grid; grid-template-columns:1fr; gap:12px; }
  .kc-grid-2{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
  .kc-grid-3{ display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }

  .kc-field label{
    display:block;
    font-weight:800;
    font-size:12.5px;
    margin-bottom:6px;
  }

  .kc-field .nb-field{
    width:100%!important;
    box-sizing:border-box;
    font-size:12.8px;
    line-height:1.4;
    padding:9px 11px;
    border-radius:14px;
  }
  .kc-field.is-warn .nb-field{
    border-color: rgba(245,158,11,.6);
    background: rgba(245,158,11,.06);
  }
  .kc-field.is-error .nb-field{
    border-color: rgba(239,68,68,.6);
    background: rgba(239,68,68,.06);
  }

  .kc-relator-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-top:6px;
  }
  .kc-relator-toggle{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:4px 8px;
    border-radius:999px;
    border:1px solid var(--nb-border);
    background: rgba(255,255,255,.7);
    font-size:11.8px;
    font-weight:700;
    color: rgba(11,37,69,.85);
  }
  html.dark .kc-relator-toggle{
    background: rgba(15,23,42,.45);
    color: rgba(226,232,240,.85);
  }
  .kc-relator-wrap{
    margin-top:8px;
    padding:10px;
    border-radius:14px;
    border:1px dashed rgba(148,163,184,.35);
    background: rgba(248,250,252,.6);
  }
  html.dark .kc-relator-wrap{
    border-color: rgba(148,163,184,.25);
    background: rgba(15,23,42,.35);
  }
  .kc-relator-wrap.is-hidden{ display:none; }
  .kc-relator-row{
    display:grid;
    grid-template-columns: 1fr 160px auto;
    gap:8px;
    align-items:center;
  }
  @media(max-width:980px){
    .kc-relator-row{ grid-template-columns: 1fr; }
  }
  .kc-relator-actions{
    margin-top:8px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }
  .kc-relator-remove{
    width:34px;
    height:34px;
    border-radius:10px;
    border:1px solid rgba(239,68,68,.25);
    background: rgba(239,68,68,.08);
    color: rgba(220,38,38,.9);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
  }
  .kc-relator-remove:hover{ background: rgba(239,68,68,.12); }
  .kc-relator-readonly{ opacity:.75; }

  .kc-suggest{ position:relative; }
  .kc-suggest-list{
    position:absolute;
    left:0; right:0; top:calc(100% + 6px);
    z-index:20;
    background:var(--nb-surface);
    border:1px solid var(--nb-border);
    border-radius:12px;
    padding:6px;
    max-height:220px;
    overflow:auto;
    box-shadow:0 12px 24px rgba(2,6,23,.08);
  }
  html.dark .kc-suggest-list{ box-shadow:0 12px 24px rgba(0,0,0,.35); }
  .kc-suggest-item{
    display:flex; align-items:center; gap:8px;
    padding:6px 8px;
    border-radius:10px;
    cursor:pointer;
    font-size:12.6px;
    color: rgba(11,37,69,.86);
  }
  html.dark .kc-suggest-item{ color: rgba(226,232,240,.9); }
  .kc-suggest-item:hover,
  .kc-suggest-item.is-active{
    background: rgba(30,136,229,.12);
    color: rgba(30,136,229,.95);
  }
  html.dark .kc-suggest-item:hover,
  html.dark .kc-suggest-item.is-active{
    background: rgba(30,136,229,.2);
    color: rgba(226,232,240,.95);
  }
  .kc-suggest-empty{
    padding:6px 8px;
    font-size:12.4px;
    color: rgba(11,37,69,.55);
  }
  html.dark .kc-suggest-empty{ color: rgba(226,232,240,.6); }

  .kc-rda-list{ display:grid; gap:8px; }
  .kc-rda-item{
    display:flex; align-items:center; gap:8px;
    font-size:12.6px;
    color: rgba(11,37,69,.76);
  }
  html.dark .kc-rda-item{ color: rgba(226,232,240,.78); }
  .kc-rda-dot{
    width:8px; height:8px; border-radius:50%;
    background: rgba(148,163,184,.6);
    flex:0 0 auto;
  }
  .kc-rda-item.is-ok .kc-rda-dot{ background: rgba(16,185,129,.9); }
  .kc-rda-item.is-warn .kc-rda-dot{ background: rgba(245,158,11,.9); }
  .kc-rda-item.is-note .kc-rda-dot{ background: rgba(148,163,184,.6); }
  .kc-rda-score{
    margin-top:8px;
    font-size:12.2px;
    font-weight:900;
    color: rgba(11,37,69,.7);
  }
  html.dark .kc-rda-score{ color: rgba(226,232,240,.7); }

  .kc-help{ margin-top:5px; font-size:12.3px; line-height:1.45; }
  .kc-error{ margin-top:5px; font-size:12.3px; color:#dc2626; }

  .kc-actions{
    display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;
    margin-top:14px; padding-top:14px;
    border-top:1px solid var(--nb-border);
  }

  .kc-kpi{ display:flex; flex-direction:column; gap:8px; }
  .kc-kpi .row{
    display:flex; justify-content:space-between; gap:10px;
    padding:10px 12px;
    border:1px solid var(--nb-border);
    border-radius:14px;
    background:rgba(31,58,95,.03);
    font-size:12.5px;
  }
  html.dark .kc-kpi .row{ background: rgba(255,255,255,.04); }
  .kc-kpi .row .v{ font-weight:900; font-size:13px; }

  /* ---------- Quill: toolbar kecil & halus (NO LINK) ---------- */
  .kc-editor{
    border:1px solid var(--nb-border);
    border-radius:14px;
    overflow:hidden;
    background: rgba(255,255,255,.75);
  }
  html.dark .kc-editor{ background: rgba(255,255,255,.06); }

  .kc-editor .ql-toolbar{
    border:0 !important;
    border-bottom:1px solid var(--nb-border) !important;
    padding:6px 8px !important;
    background: rgba(255,255,255,.45);
  }
  html.dark .kc-editor .ql-toolbar{ background: rgba(255,255,255,.035); }

  .kc-editor .ql-toolbar .ql-formats{ margin-right:6px !important; }

  /* ukuran tombol & ikon lebih kecil, tidak tebal */
  .kc-editor .ql-toolbar button{
    width:26px !important;
    height:26px !important;
    padding:0 !important;
    border-radius:9px !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
  }

  /* tipiskan SVG quill */
  .kc-editor .ql-toolbar button svg,
  .kc-editor .ql-toolbar button .ql-stroke{
    stroke-width: 1.35 !important;
  }
  .kc-editor .ql-toolbar button .ql-fill{ fill-opacity: .85 !important; }

  /* hover lebih subtle */
  .kc-editor .ql-toolbar button:hover{
    background: rgba(31,58,95,.06);
  }
  html.dark .kc-editor .ql-toolbar button:hover{
    background: rgba(255,255,255,.06);
  }

  /* jarak antar grup */
  .kc-editor .ql-toolbar .ql-formats + .ql-formats{
    margin-left:6px !important;
  }

  .kc-editor .ql-container{ border:0 !important; font-size:12.8px !important; }
  .kc-editor .ql-editor{ padding:10px 12px !important; line-height:1.55 !important; }
  .kc-editor--notes .ql-editor{ min-height:140px; }

  .kc-editor .ql-editor.ql-blank::before{
    font-style:normal !important;
    color: rgba(11,37,69,.45) !important;
  }
  html.dark .kc-editor .ql-editor.ql-blank::before{
    color: rgba(226,232,240,.40) !important;
  }

  /* ---------- Cover uploader ---------- */
  .kc-cover{
    border:1px solid var(--nb-border);
    border-radius:16px;
    overflow:hidden;
    background: rgba(255,255,255,.65);
  }
  html.dark .kc-cover{ background: rgba(255,255,255,.05); }

  .kc-coverBody{ padding:12px; }

  .kc-coverBox{
    border:1px dashed rgba(15,23,42,.18);
    border-radius:14px;
    padding:12px;
    background: rgba(15,23,42,.02);
  }
  html.dark .kc-coverBox{
    border-color: rgba(148,163,184,.22);
    background: rgba(255,255,255,.03);
  }

  .kc-coverPreview{
    width:100%;
    aspect-ratio: 3 / 4;
    border-radius:12px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.75);
    display:flex; align-items:center; justify-content:center;
    overflow:hidden;
    position:relative;
  }
  html.dark .kc-coverPreview{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.30);
  }
  .kc-coverPreview img{ width:100%; height:100%; object-fit:cover; display:block; }

  .kc-coverEmpty{
    text-align:center;
    font-size:12.5px;
    color: rgba(11,37,69,.62);
    line-height:1.45;
    padding:8px 10px;
  }
  html.dark .kc-coverEmpty{ color: rgba(226,232,240,.66); }

  .kc-coverBtns{ display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
  .kc-coverBtns .nb-btn{ border-radius:14px; }

  .kc-coverFile{
    position:absolute;
    width:1px; height:1px;
    padding:0; margin:-1px;
    overflow:hidden; clip:rect(0,0,0,0);
    white-space:nowrap; border:0;
  }

  @media(max-width:980px){
    .kc-layout{ grid-template-columns:1fr; }
    .kc-side{ position:static; }
    .kc-grid-2,.kc-grid-3{ grid-template-columns:1fr; }
    .kc-actions .nb-btn,.kc-actions .nb-btn-primary{ width:100%; justify-content:center; }
  }
</style>

<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">

@if(!$canManage)
  <div class="nb-card kc-wrap" style="padding:16px;">
    <div style="font-weight:900;">Akses ditolak</div>
    <div class="nb-muted-2" style="margin-top:6px;">Hanya admin/staff yang dapat menambah bibliografi.</div>
  </div>
@else

<div class="kc-wrap">
  <div class="nb-card kc-shell">

    <div class="kc-head">
      <div>
        <h1 class="title">Tambah Bibliografi</h1>
        <div class="nb-muted-2 sub">Input metadata buku sebelum membuat eksemplar.</div>
      </div>
      <a href="{{ route('katalog.index') }}" class="nb-btn">Kembali</a>
    </div>

    {{-- ✅ penting: enctype untuk upload cover --}}
    <form method="POST" action="{{ route('katalog.store') }}" enctype="multipart/form-data">
      @csrf

      @if ($errors->any())
        <div class="kc-section acc-amber" style="margin-bottom:12px;">
          <div style="font-weight:900; font-size:13px;">Gagal menyimpan</div>
          <div class="nb-muted-2" style="margin-top:6px;">Periksa field berikut:</div>
          <ul style="margin:8px 0 0 18px; font-size:12.5px;">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="kc-layout">

        {{-- LEFT --}}
        <div>

          {{-- Identitas --}}
          <div class="kc-section acc-blue">
            <div class="kc-section-head">
              <div class="h">Identitas</div>
              <p class="nb-muted-2 hint">Wajib: Judul & Pengarang</p>
            </div>

            <div class="kc-grid-1">
              <div class="kc-field">
                <label>Judul <span class="nb-muted-2">*</span></label>
                <input id="field_title" type="text" class="nb-field" name="title" value="{{ old('title') }}" required
                  placeholder="contoh: Belajar Pemrograman Web Modern dengan Laravel untuk Pemula (Edisi Revisi)">
                @error('title') <div class="kc-error">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <label>Subjudul</label>
                <input id="field_subtitle" type="text" class="nb-field" name="subtitle" value="{{ old('subtitle') }}"
                  placeholder="contoh: Panduan langkah demi langkah, studi kasus, dan praktik terbaik">
                @error('subtitle') <div class="kc-error">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field kc-suggest">
                <label>Pengarang <span class="nb-muted-2">*</span></label>
                <input id="field_authors" type="text" class="nb-field" name="authors_text" value="{{ old('authors_text') }}" required
                  data-suggest="authors" data-suggest-endpoint="{{ route('authority.authors') }}" data-suggest-delim=","
                  placeholder="contoh: Andrea Hirata, Pramoedya Ananta Toer">
                <div class="nb-muted-2 kc-help">Pisahkan dengan koma jika lebih dari satu.</div>
                @error('authors_text') <div class="kc-error">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <div class="kc-relator-head">
                  <label>Relator Pengarang (MARC21/RDA)</label>
                  <label class="kc-relator-toggle">
                    <input type="checkbox" name="authors_role_mode" id="authors_role_mode" value="1" {{ $authorsRoleMode ? 'checked' : '' }}>
                    <span>Mode relator</span>
                  </label>
                </div>
                <div class="kc-relator-wrap {{ $authorsRoleMode ? '' : 'is-hidden' }}" id="relator_wrap" data-authors-role-seed='@json($authorsRoleSeed)'>
                  <div id="relator_rows"></div>
                  <div class="kc-relator-actions">
                    <button type="button" class="nb-btn" id="relator_add">Tambah Pengarang</button>
                  </div>
                </div>
                <input type="hidden" name="authors_roles_json" id="authors_roles_json" value="{{ is_string(old('authors_roles_json')) ? old('authors_roles_json') : '' }}">
                <div class="nb-muted-2 kc-help">Gunakan relator untuk kualitas MARC/RDA lebih baik (aut/edt/trl/dll).</div>
              </div>

              <div class="kc-field kc-suggest">
                <label>Subjek / Tajuk</label>
                <input id="field_subjects" type="text" class="nb-field" name="subjects_text" value="{{ old('subjects_text') }}"
                  data-suggest="subjects" data-suggest-endpoint="{{ route('authority.subjects') }}" data-suggest-delim=";"
                  placeholder="contoh: Filsafat; Agama; Sosial; Politik">
                <div class="nb-muted-2 kc-help">Pisahkan dengan koma / titik koma / enter.</div>
                @error('subjects_text') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Publikasi --}}
          <div class="kc-section acc-slate">
            <div class="kc-section-head">
              <div class="h">Publikasi</div>
              <p class="nb-muted-2 hint">Penerbit, tempat terbit, tahun, ISBN</p>
            </div>

            <div class="kc-grid-3">
              <div class="kc-field kc-suggest">
                <label>Penerbit</label>
                <input id="field_publisher" class="nb-field" name="publisher" value="{{ old('publisher') }}"
                       data-suggest="publisher" data-suggest-endpoint="{{ route('authority.publishers') }}" data-suggest-single="1"
                       placeholder="contoh: Gramedia Pustaka Utama">
                @error('publisher') <div class="kc-error">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <label>Tempat Terbit</label>
                <input id="field_place" class="nb-field" name="place_of_publication" value="{{ old('place_of_publication') }}"
                       placeholder="contoh: Jakarta">
                @error('place_of_publication') <div class="kc-error">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <label>Tahun Terbit</label>
                <input id="field_year" class="nb-field" type="number" name="publish_year" value="{{ old('publish_year') }}"
                       min="0" max="2100" placeholder="contoh: 2005">
                @error('publish_year') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
            </div>

            <div style="height:10px"></div>

            <div class="kc-grid-3">
              <div class="kc-field">
                <label>ISBN</label>
                <input id="field_isbn" class="nb-field" name="isbn" value="{{ old('isbn') }}"
                       placeholder="contoh: 978-602-433-906-7">
                @error('isbn') <div class="kc-error">{{ $message }}</div> @enderror
                  <div class="kc-actions" style="margin-top:6px; padding-top:0; border-top:0; justify-content:flex-start;">
                    <button type="button" class="nb-btn nb-btn-soft" id="isbn_fetch_btn">Ambil dari ISBN</button>
                    <label class="nb-k-select" style="margin-left:8px;">
                      <input type="checkbox" id="isbn_auto_toggle">
                      <span>Auto-ambil saat ISBN lengkap</span>
                    </label>
                    <span class="nb-muted-2" id="isbn_fetch_status" style="font-size:12px;"></span>
                  </div>
              </div>

              <div class="kc-field">
                <label>Bahasa</label>
                <input id="field_language" class="nb-field" name="language" value="{{ old('language','id') }}"
                       placeholder="contoh: id">
                @error('language') <div class="kc-error">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <label>Edisi</label>
                <input class="nb-field" name="edition" value="{{ old('edition') }}"
                       placeholder="contoh: Cet 1 / Edisi revisi">
                @error('edition') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
            </div>

            <div style="height:10px"></div>

            <div class="kc-grid-1">
              <div class="kc-field">
                <label>Deskripsi Fisik</label>
                <input id="field_physical_desc" class="nb-field" name="physical_desc" value="{{ old('physical_desc') }}"
                       placeholder="contoh: xii + 200 hlm; 21 cm">
                @error('physical_desc') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Serial --}}
          <div class="kc-section acc-amber">
            <div class="kc-section-head">
              <div class="h">Serial</div>
              <p class="nb-muted-2 hint">Khusus terbitan berseri: frekuensi, rentang terbit, catatan sumber</p>
            </div>

            <div class="kc-grid-2">
              <div class="kc-field">
                <label>Frekuensi (310)</label>
                <input class="nb-field" name="frequency" value="{{ old('frequency') }}"
                       placeholder="contoh: Bulanan">
                @error('frequency') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
              <div class="kc-field">
                <label>Frekuensi Lama (321)</label>
                <input class="nb-field" name="former_frequency" value="{{ old('former_frequency') }}"
                       placeholder="contoh: Mingguan">
                @error('former_frequency') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
            </div>

            <div style="height:10px"></div>

            <div class="kc-grid-2">
              <div class="kc-field">
                <label>Awal Terbit (362)</label>
                <input class="nb-field" name="serial_beginning" value="{{ old('serial_beginning') }}"
                       placeholder="contoh: Vol. 1, No. 1 (2020)">
                @error('serial_beginning') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
              <div class="kc-field">
                <label>Akhir Terbit (362)</label>
                <input class="nb-field" name="serial_ending" value="{{ old('serial_ending') }}"
                       placeholder="contoh: Vol. 5, No. 4 (2024)">
                @error('serial_ending') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
            </div>

            <div style="height:10px"></div>

            <div class="kc-grid-2">
              <div class="kc-field">
                <label>Issue Pertama (363$a)</label>
                <input class="nb-field" name="serial_first_issue" value="{{ old('serial_first_issue') }}"
                       placeholder="contoh: 1">
                @error('serial_first_issue') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
              <div class="kc-field">
                <label>Issue Terakhir (363$b)</label>
                <input class="nb-field" name="serial_last_issue" value="{{ old('serial_last_issue') }}"
                       placeholder="contoh: 20">
                @error('serial_last_issue') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
            </div>

            <div style="height:10px"></div>

            <div class="kc-grid-1">
              <div class="kc-field">
                <label>Catatan Sumber (588)</label>
                <input class="nb-field" name="serial_source_note" value="{{ old('serial_source_note') }}"
                       placeholder="contoh: Description based on: Vol. 1, No. 1 (2020).">
                @error('serial_source_note') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
            </div>

            <div style="height:10px"></div>

            <div class="kc-grid-2">
              <div class="kc-field">
                <label>Judul Sebelumnya (780)</label>
                <input class="nb-field" name="serial_preceding_title" value="{{ old('serial_preceding_title') }}"
                       placeholder="contoh: Jurnal Teknologi Lama">
                @error('serial_preceding_title') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
              <div class="kc-field">
                <label>ISSN Sebelumnya (780$x)</label>
                <input class="nb-field" name="serial_preceding_issn" value="{{ old('serial_preceding_issn') }}"
                       placeholder="contoh: 1234-5678">
                @error('serial_preceding_issn') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
            </div>

            <div style="height:10px"></div>

            <div class="kc-grid-2">
              <div class="kc-field">
                <label>Judul Lanjutan (785)</label>
                <input class="nb-field" name="serial_succeeding_title" value="{{ old('serial_succeeding_title') }}"
                       placeholder="contoh: Jurnal Teknologi Baru">
                @error('serial_succeeding_title') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
              <div class="kc-field">
                <label>ISSN Lanjutan (785$x)</label>
                <input class="nb-field" name="serial_succeeding_issn" value="{{ old('serial_succeeding_issn') }}"
                       placeholder="contoh: 8765-4321">
                @error('serial_succeeding_issn') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Holdings Summary --}}
          <div class="kc-section acc-amber">
            <div class="kc-section-head">
              <div class="h">Ringkasan Holdings</div>
              <p class="nb-muted-2 hint">Opsional: 866/867/868 (statement koleksi)</p>
            </div>

            <div class="kc-grid-1">
              <div class="kc-field">
                <label>866 Summary</label>
                <input class="nb-field" name="holdings_summary" value="{{ old('holdings_summary') }}"
                       placeholder="contoh: Vol. 1 (2020)-Vol. 5 (2024)">
                @error('holdings_summary') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
            </div>

            <div style="height:10px"></div>

            <div class="kc-grid-2">
              <div class="kc-field">
                <label>867 Supplement</label>
                <input class="nb-field" name="holdings_supplement" value="{{ old('holdings_supplement') }}"
                       placeholder="contoh: Suplemen Tahunan 2023">
                @error('holdings_supplement') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
              <div class="kc-field">
                <label>868 Index</label>
                <input class="nb-field" name="holdings_index" value="{{ old('holdings_index') }}"
                       placeholder="contoh: Indeks Vol. 1-5">
                @error('holdings_index') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          {{-- Klasifikasi --}}
          <div class="kc-section acc-green">
            <div class="kc-section-head">
              <div class="h">Klasifikasi</div>
              <p class="nb-muted-2 hint">DDC + Nomor Panggil + Tag</p>
            </div>

              <div class="kc-grid-2">
                <div class="kc-field">
                  <label>Jenis Konten (336)</label>
                  <select id="field_material_type" class="nb-field" name="material_type">
                    @foreach($materialTypeOptions as $val => $label)
                      <option value="{{ $val }}" {{ old('material_type', 'buku') === $val ? 'selected' : '' }}>
                        {{ $label }}
                      </option>
                    @endforeach
                  </select>
                  <div class="nb-muted-2 kc-help">Pilih jenis konten. Untuk buku cetak gunakan “Buku (text)”.</div>
                  @error('material_type') <div class="kc-error">{{ $message }}</div> @enderror
                </div>
                <div class="kc-field">
                  <label>Media (337)</label>
                  <select id="field_media_type" class="nb-field" name="media_type">
                    <option value="">Pilih media</option>
                    @foreach($mediaTypeOptions as $val => $label)
                      <option value="{{ $val }}" {{ old('media_type') === $val ? 'selected' : '' }}>
                        {{ $label }}
                      </option>
                    @endforeach
                  </select>
                  <div class="nb-muted-2 kc-help">Gunakan “Teks” untuk buku cetak atau eBook.</div>
                  @error('media_type') <div class="kc-error">{{ $message }}</div> @enderror
                </div>
              </div>

            <div style="height:10px"></div>

            <div class="kc-grid-3">
              <div class="kc-field">
                <label>DDC</label>
                <input id="field_ddc" class="nb-field" name="ddc" value="{{ old('ddc') }}"
                       placeholder="contoh: 005.113">
                @error('ddc') <div class="kc-error">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <label>Nomor Panggil</label>
                <input id="field_call_number" class="nb-field" name="call_number" value="{{ old('call_number') }}"
                       placeholder="contoh: 005.113 BEL">
                @error('call_number') <div class="kc-error">{{ $message }}</div> @enderror
              </div>

              <div class="kc-field">
                <label>Tag</label>
                <input class="nb-field" name="tags_text" value="{{ old('tags_text') }}"
                       placeholder="contoh: pemula, referensi">
                @error('tags_text') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
            </div>

            <div style="height:10px"></div>

            {{-- Catatan (Quill) --}}
            <div class="kc-grid-1">
              <div class="kc-field">
                <label>Catatan</label>
                <input type="hidden" name="notes" id="notes_input" value="{{ old('notes') }}">
                <div class="kc-editor kc-editor--notes">
                  <div id="notes_editor" data-placeholder="Tulis catatan (misal ringkasan, kondisi buku, info penting)…"></div>
                </div>
                <div class="nb-muted-2 kc-help">Bisa format teks (bold, italic, underline, list). Disimpan sebagai HTML.</div>
                @error('notes') <div class="kc-error">{{ $message }}</div> @enderror
              </div>
            </div>

          </div>

          {{-- Metadata Lintas Bahasa --}}
          <div class="kc-section acc-slate">
            <div class="kc-section-head">
              <div class="h">Metadata Lintas Bahasa</div>
              <p class="nb-muted-2 hint">Opsional: judul, creator, subject, deskripsi per bahasa (Dublin Core)</p>
            </div>

            <div id="dc_i18n_wrap" class="kc-grid-1">
              @foreach($dcI18n as $locale => $payload)
                @php
                  $payload = is_array($payload) ? $payload : [];
                @endphp
                <div class="kc-section dc-i18n-block" style="padding:12px; border-radius:14px;">
                  <div class="kc-grid-3">
                    <div class="kc-field">
                      <label>Locale</label>
                      <input class="nb-field" name="dc_i18n[{{ $locale }}][__locale]" value="{{ $locale }}" readonly>
                    </div>
                    <div class="kc-field">
                      <label>Judul</label>
                      <input class="nb-field" name="dc_i18n[{{ $locale }}][title]" value="{{ old("dc_i18n.$locale.title", $payload['title'] ?? '') }}">
                    </div>
                    <div class="kc-field">
                      <label>Bahasa</label>
                      <input class="nb-field" name="dc_i18n[{{ $locale }}][language]" value="{{ old("dc_i18n.$locale.language", $payload['language'] ?? $locale) }}">
                    </div>
                  </div>
                  <div style="height:8px"></div>
                  <div class="kc-grid-2">
                    <div class="kc-field">
                      <label>Creator (pisahkan dengan ;)</label>
                      <textarea class="nb-field dc-creator" name="dc_i18n[{{ $locale }}][creator]" rows="2">{{ old("dc_i18n.$locale.creator", is_array($payload['creator'] ?? null) ? implode('; ', $payload['creator']) : ($payload['creator'] ?? '')) }}</textarea>
                      <div class="nb-muted-2 kc-help dc-creator-preview"></div>
                    </div>
                    <div class="kc-field">
                      <label>Subject (pisahkan dengan ;)</label>
                      <textarea class="nb-field dc-subject" name="dc_i18n[{{ $locale }}][subject]" rows="2">{{ old("dc_i18n.$locale.subject", is_array($payload['subject'] ?? null) ? implode('; ', $payload['subject']) : ($payload['subject'] ?? '')) }}</textarea>
                      <div class="nb-muted-2 kc-help dc-subject-preview"></div>
                    </div>
                  </div>
                  <div style="height:8px"></div>
                  <div class="kc-grid-1">
                    <div class="kc-field">
                      <label>Deskripsi</label>
                      <textarea class="nb-field" name="dc_i18n[{{ $locale }}][description]" rows="3">{{ old("dc_i18n.$locale.description", $payload['description'] ?? '') }}</textarea>
                    </div>
                  </div>
                  <div style="height:8px"></div>
                  <div class="kc-actions" style="margin-top:0; padding-top:0; border-top:0;">
                    <button type="button" class="nb-btn dc-remove">Hapus Locale</button>
                  </div>
                </div>
              @endforeach
            </div>

            <div style="height:8px"></div>
            <div class="kc-actions" style="margin-top:0; padding-top:0; border-top:0;">
              <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <select id="dc_locale_select" class="nb-field" style="max-width:180px;">
                  <option value="">Pilih locale</option>
                  <option value="id">id</option>
                  <option value="en">en</option>
                  <option value="fr">fr</option>
                  <option value="es">es</option>
                  <option value="de">de</option>
                  <option value="ja">ja</option>
                  <option value="zh">zh</option>
                  <option value="ar">ar</option>
                </select>
                <input id="dc_locale_custom" class="nb-field" style="max-width:180px;" placeholder="locale custom">
                <button type="button" class="nb-btn" id="dc_add_locale">Tambah Bahasa</button>
              </div>
              <div id="dc_locale_error" class="kc-error" style="display:none;"></div>
            </div>
          </div>

          {{-- Global Identifiers --}}
          <div class="kc-section acc-slate">
            <div class="kc-section-head">
              <div class="h">Global Identifiers</div>
              <p class="nb-muted-2 hint">Opsional: DOI, URI, ISNI, ORCID, dll</p>
            </div>

            <div id="identifiers_wrap" class="kc-grid-1">
              @foreach($identifiers as $idx => $row)
                @php $row = is_array($row) ? $row : []; @endphp
                <div class="kc-grid-3">
                  <div class="kc-field">
                    <label>Skema</label>
                    <input class="nb-field" name="identifiers[{{ $idx }}][scheme]" value="{{ old("identifiers.$idx.scheme", $row['scheme'] ?? '') }}" placeholder="doi / uri / isni / orcid">
                  </div>
                  <div class="kc-field">
                    <label>Nilai</label>
                    <input class="nb-field" name="identifiers[{{ $idx }}][value]" value="{{ old("identifiers.$idx.value", $row['value'] ?? '') }}" placeholder="10.1234/abc">
                  </div>
                  <div class="kc-field">
                    <label>URI (opsional)</label>
                    <input class="nb-field" name="identifiers[{{ $idx }}][uri]" value="{{ old("identifiers.$idx.uri", $row['uri'] ?? '') }}" placeholder="https://example.org/...">
                  </div>
                </div>
                <div style="height:8px"></div>
              @endforeach
            </div>

            <div class="kc-actions" style="margin-top:0; padding-top:0; border-top:0;">
              <button type="button" class="nb-btn" id="id_add_row">Tambah Identifier</button>
            </div>
          </div>

          {{-- Actions --}}
          <div class="kc-actions" style="margin-top:6px; padding-top:0; border-top:0; justify-content:flex-start; flex-wrap:wrap;">
            <label class="kc-toggle">
              <input type="checkbox" name="auto_fix" value="1" {{ old('auto_fix', '1') === '1' ? 'checked' : '' }}>
              <span>Auto-fix ringan</span>
            </label>
            <span class="nb-muted-2" style="font-size:12px;">Trim spasi, normalisasi DDC/call number, lowercase bahasa.</span>
          </div>
          <div class="kc-actions">
            <button class="nb-btn nb-btn-primary" type="submit">Simpan Bibliografi</button>
            <a class="nb-btn" href="{{ route('katalog.index') }}">Batal</a>
          </div>

        </div>

        {{-- RIGHT --}}
        <div class="kc-side">

          {{-- Cover Buku --}}
          <div class="kc-section acc-slate">
            <div class="kc-section-head">
              <div class="h">Cover Buku</div>
              <p class="nb-muted-2 hint">Opsional</p>
            </div>

            {{-- ✅ name="cover" biar match validator/controller --}}
            <input id="cover_file" class="kc-coverFile" type="file" name="cover" accept="image/*">

            <div class="kc-cover">
              <div class="kc-coverBody">
                <div class="kc-coverBox">
                  <div class="kc-coverPreview" id="cover_preview">
                    <div class="kc-coverEmpty" id="cover_empty">
                      Belum ada cover.<br>
                      <span class="nb-muted-2">Format: JPG/PNG/WebP</span>
                    </div>
                    <img id="cover_img" alt="Preview cover" style="display:none;">
                  </div>

                  <div class="kc-coverBtns">
                    <button type="button" class="nb-btn" id="cover_pick">Pilih Gambar</button>
                    <button type="button" class="nb-btn" id="cover_clear" style="display:none;">Hapus</button>
                  </div>

                  @error('cover') <div class="kc-error">{{ $message }}</div> @enderror
                  <div class="nb-muted-2 kc-help">Gambar hanya untuk cover buku (opsional).</div>
                </div>
              </div>
            </div>
          </div>

          {{-- Eksemplar --}}
          <div class="kc-section acc-slate" style="margin-top:12px;">
            <div class="kc-section-head">
              <div class="h">Eksemplar</div>
              <p class="nb-muted-2 hint">Opsional</p>
            </div>

            <div class="kc-field">
              <label>Jumlah Eksemplar</label>
              <input class="nb-field" type="number" name="copies_count" value="{{ old('copies_count', 0) }}"
                     min="0" max="200" placeholder="contoh: 3">
              @error('copies_count') <div class="kc-error">{{ $message }}</div> @enderror
              <div class="nb-muted-2 kc-help">Barcode & Nomor Induk dibuat otomatis.</div>
            </div>

            <div style="height:12px"></div>

            <div class="kc-kpi">
              <div class="row"><span>Status awal</span><span class="v">available</span></div>
              <div class="row"><span>Sirkulasi</span><span class="v">item-based</span></div>
              <div class="row"><span>Standar</span><span class="v">Biblio + Items</span></div>
            </div>
          </div>

          <div class="kc-section acc-slate" style="margin-top:12px;" id="rda_panel">
            <div class="kc-section-head">
              <div class="h">Validasi RDA Core</div>
              <p class="nb-muted-2 hint">Cek cepat kualitas metadata</p>
            </div>

            <div class="kc-rda-list" id="rda_list">
              <div class="kc-rda-item" data-rda="title"><span class="kc-rda-dot"></span>Judul (wajib)</div>
              <div class="kc-rda-item" data-rda="access"><span class="kc-rda-dot"></span>Akses poin (pengarang/penerbit)</div>
              <div class="kc-rda-item" data-rda="publication"><span class="kc-rda-dot"></span>Tempat + Tahun terbit</div>
              <div class="kc-rda-item" data-rda="language"><span class="kc-rda-dot"></span>Bahasa (2-3 huruf)</div>
              <div class="kc-rda-item" data-rda="ddc"><span class="kc-rda-dot"></span>DDC (opsional)</div>
              <div class="kc-rda-item" data-rda="call"><span class="kc-rda-dot"></span>Nomor Panggil (opsional)</div>
            </div>
            <div class="kc-rda-score" id="rda_score">Skor RDA: 0/4</div>
          </div>

          <div class="kc-section acc-amber" style="margin-top:12px;" id="marc_quality"
               data-marc-errors="0" data-marc-warnings="0">
            <div class="kc-section-head">
              <div class="h">Kualitas MARC/RDA</div>
              <p class="nb-muted-2 hint">Validasi wajib & peringatan</p>
            </div>
            <div class="kc-quality-empty">Belum ada temuan kualitas.</div>
            <div class="nb-muted-2 kc-help">Klik item untuk fokus field terkait. Jika ada error, sistem meminta konfirmasi sebelum simpan.</div>
          </div>

          <div class="kc-section acc-blue" style="margin-top:12px;">
            <div class="kc-section-head">
              <div class="h">Tips cepat</div>
              <p class="nb-muted-2 hint">Kualitas data</p>
            </div>

            <div class="nb-muted-2" style="line-height:1.55; font-size:12.5px;">
              • Pastikan <b>Judul</b> dan <b>Pengarang</b> benar.<br>
              • Isi <b>DDC</b> + <b>Nomor Panggil</b> agar siap rak.<br>
              • Tajuk subjek meningkatkan akurasi pencarian.<br>
              • Eksemplar bisa dibuat sekarang atau nanti di halaman detail.
            </div>
          </div>

        </div>

      </div>
    </form>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>

<script>
  (function(){
    // ✅ cegah init dobel
    if (window.__NB_KATALOG_CREATE_INITED__) return;
    window.__NB_KATALOG_CREATE_INITED__ = true;

    // ---------- Quill (NO LINK) ----------
    function initQuill(editorId, inputId){
      var editorEl = document.getElementById(editorId);
      var inputEl  = document.getElementById(inputId);
      if(!editorEl || !inputEl) return null;

      // kalau sudah pernah di-init (misalnya karena hot-reload), skip
      if (editorEl.__nbQuillInited) return null;
      editorEl.__nbQuillInited = true;

      var q = new Quill('#' + editorId, {
        theme: 'snow',
        placeholder: editorEl.getAttribute('data-placeholder') || '',
        modules: {
          toolbar: [
            ['bold','italic','underline'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            ['clean']
          ]
        }
      });

      var initial = (inputEl.value || '').trim();
      if(initial !== ''){
        q.clipboard.dangerouslyPasteHTML(initial);
      }

      q.on('text-change', function(){
        inputEl.value = q.root.innerHTML;
      });

      var form = editorEl.closest('form');
      if(form){
        form.addEventListener('submit', function(){
          inputEl.value = q.root.innerHTML;
        });
      }
      return q;
    }
    var notesQuill = initQuill('notes_editor', 'notes_input');

    function debounce(fn, delay){
      var t;
      return function(){
        var args = arguments;
        if (t) window.clearTimeout(t);
        t = window.setTimeout(function(){ fn.apply(null, args); }, delay);
      };
    }

    // ---------- Telemetry (Autocomplete) ----------
    var telemetryUrl = "{{ route('telemetry.autocomplete') }}";
    var telemetryToken = (document.querySelector('input[name=\"_token\"]') || {}).value || '';
    var telemetryBuffer = {};
    var flushTelemetry = debounce(function(){
      if (!telemetryUrl || !telemetryToken) return;
      var payload = {
        event: 'autocomplete_select',
        path: window.location.pathname,
        counts: telemetryBuffer
      };
      telemetryBuffer = {};
      fetch(telemetryUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': telemetryToken,
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      }).catch(function(){});
    }, 1500);

    function trackAutocomplete(field){
      if (!field) field = 'unknown';
      telemetryBuffer[field] = (telemetryBuffer[field] || 0) + 1;
      try {
        var key = 'nb_autocomplete_usage';
        var current = {};
        var raw = localStorage.getItem(key);
        if (raw) current = JSON.parse(raw);
        current[field] = (current[field] || 0) + 1;
        localStorage.setItem(key, JSON.stringify(current));
      } catch (e) {}
      flushTelemetry();
    }

    // ---------- Authority Suggest ----------
    function attachSuggest(input){
      var endpoint = input.getAttribute('data-suggest-endpoint');
      if (!endpoint) return;
      var isSingle = input.getAttribute('data-suggest-single') === '1';
      var delimiter = input.getAttribute('data-suggest-delim') || ',';
      var wrap = input.closest('.kc-suggest') || input.parentElement;
      if (!wrap || wrap.querySelector('.kc-suggest-list')) return;

      var list = document.createElement('div');
      list.className = 'kc-suggest-list';
      list.hidden = true;
      wrap.appendChild(list);

      var items = [];
      var activeIndex = -1;
      var requestId = 0;

      function getToken(value){
        var lastSep = Math.max(value.lastIndexOf(','), value.lastIndexOf(';'), value.lastIndexOf('\n'));
        var prefix = lastSep >= 0 ? value.slice(0, lastSep + 1) : '';
        var token = value.slice(lastSep + 1).trim();
        return { prefix: prefix, token: token };
      }

      function closeList(){
        list.hidden = true;
        list.innerHTML = '';
        items = [];
        activeIndex = -1;
      }

      function render(data){
        list.innerHTML = '';
        items = data || [];
        activeIndex = -1;
        if (!items.length) {
          var empty = document.createElement('div');
          empty.className = 'kc-suggest-empty';
          empty.textContent = 'Tidak ada hasil';
          list.appendChild(empty);
          list.hidden = false;
          return;
        }
        items.forEach(function(item, idx){
          var row = document.createElement('div');
          row.className = 'kc-suggest-item';
          row.textContent = item.label || '';
          row.addEventListener('mousedown', function(e){
            e.preventDefault();
            applyValue(item.label || '');
          });
          list.appendChild(row);
          if (idx === 0) {
            activeIndex = 0;
            row.classList.add('is-active');
          }
        });
        list.hidden = false;
      }

      function setActive(index){
        var rows = list.querySelectorAll('.kc-suggest-item');
        rows.forEach(function(row){ row.classList.remove('is-active'); });
        if (index >= 0 && index < rows.length) {
          rows[index].classList.add('is-active');
          activeIndex = index;
        }
      }

      function applyValue(label){
        if (!label) return;
        if (isSingle) {
          input.value = label;
          closeList();
          input.dispatchEvent(new Event('input', { bubbles: true }));
          trackAutocomplete(input.getAttribute('data-suggest'));
          return;
        }
        var current = input.value || '';
        var info = getToken(current);
        var spacer = info.prefix && !/\s$/.test(info.prefix) ? ' ' : '';
        var tail = delimiter ? (delimiter + ' ') : '';
        input.value = info.prefix + spacer + label + tail;
        closeList();
        input.dispatchEvent(new Event('input', { bubbles: true }));
        trackAutocomplete(input.getAttribute('data-suggest'));
      }

      var fetchSuggest = debounce(function(){
        var info = getToken(input.value || '');
        var q = info.token;
        if (!q || q.length < 2) {
          closeList();
          return;
        }
        var id = ++requestId;
        fetch(endpoint + '?q=' + encodeURIComponent(q))
          .then(function(res){ return res.json(); })
          .then(function(data){
            if (id !== requestId) return;
            render(data || []);
          })
          .catch(function(){ closeList(); });
      }, 180);

      input.addEventListener('input', fetchSuggest);
      input.addEventListener('focus', fetchSuggest);
      input.addEventListener('blur', function(){ setTimeout(closeList, 160); });
      input.addEventListener('keydown', function(e){
        if (list.hidden) return;
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          setActive(Math.min(activeIndex + 1, items.length - 1));
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          setActive(Math.max(activeIndex - 1, 0));
        } else if (e.key === 'Enter') {
          if (activeIndex >= 0 && items[activeIndex]) {
            e.preventDefault();
            applyValue(items[activeIndex].label || '');
          }
        } else if (e.key === 'Escape') {
          closeList();
        }
      });
    }

    document.querySelectorAll('[data-suggest-endpoint]').forEach(function(input){
      attachSuggest(input);
    });

    // ---------- Relator Mode ----------
    (function(){
      var toggle = document.getElementById('authors_role_mode');
      var wrap = document.getElementById('relator_wrap');
      var rowsWrap = document.getElementById('relator_rows');
      var hidden = document.getElementById('authors_roles_json');
      var authorsInput = document.getElementById('field_authors');
      if (!toggle || !wrap || !rowsWrap || !hidden || !authorsInput) return;

      var relatorOptions = [
        { code: 'aut', label: 'Pengarang (aut)' },
        { code: 'edt', label: 'Editor (edt)' },
        { code: 'trl', label: 'Penerjemah (trl)' },
        { code: 'ill', label: 'Ilustrator (ill)' },
        { code: 'ctb', label: 'Kontributor (ctb)' },
        { code: 'cmp', label: 'Komposer (cmp)' },
        { code: 'prf', label: 'Performer (prf)' },
        { code: 'pbl', label: 'Penerbit (pbl)' }
      ];

      function normalizeRole(role){
        var r = (role || '').toString().trim().toLowerCase();
        if (!r || r === 'pengarang') return 'aut';
        return r;
      }

      function buildRow(name, role){
        var row = document.createElement('div');
        row.className = 'kc-relator-row';
        row.innerHTML = ''
          + '<input type="text" class="nb-field relator-name" placeholder="Nama pengarang">'
          + '<select class="nb-field relator-role"></select>'
          + '<button type="button" class="kc-relator-remove" title="Hapus">×</button>';

        var nameInput = row.querySelector('.relator-name');
        var roleSelect = row.querySelector('.relator-role');
        nameInput.value = name || '';

        relatorOptions.forEach(function(opt){
          var option = document.createElement('option');
          option.value = opt.code;
          option.textContent = opt.label;
          roleSelect.appendChild(option);
        });
        roleSelect.value = normalizeRole(role);

        nameInput.setAttribute('data-suggest', 'authors');
        nameInput.setAttribute('data-suggest-endpoint', '{{ route('authority.authors') }}');
        nameInput.setAttribute('data-suggest-delim', ',');
        attachSuggest(nameInput);

        row.addEventListener('input', sync);
        row.addEventListener('change', sync);
        row.querySelector('.kc-relator-remove').addEventListener('click', function(){
          row.remove();
          sync();
        });

        return row;
      }

      function seedRows(){
        rowsWrap.innerHTML = '';
        var seed = [];
        try {
          var raw = wrap.getAttribute('data-authors-role-seed') || '[]';
          seed = JSON.parse(raw);
        } catch (e) {
          seed = [];
        }
        if (!Array.isArray(seed) || !seed.length) {
          var fromText = (authorsInput.value || '').split(',').map(function(x){ return x.trim(); }).filter(Boolean);
          seed = fromText.map(function(name){ return { name: name, role: 'aut' }; });
        }
        if (!seed.length) seed = [{ name: '', role: 'aut' }];
        seed.forEach(function(item){
          rowsWrap.appendChild(buildRow(item.name || '', item.role || 'aut'));
        });
        sync();
      }

      function sync(){
        var rows = rowsWrap.querySelectorAll('.kc-relator-row');
        var payload = [];
        rows.forEach(function(row){
          var name = (row.querySelector('.relator-name')?.value || '').trim();
          var role = (row.querySelector('.relator-role')?.value || '').trim();
          if (!name) return;
          payload.push({ name: name, role: normalizeRole(role) });
        });
        hidden.value = payload.length ? JSON.stringify(payload) : '';
        if (toggle.checked) {
          var names = payload.map(function(x){ return x.name; });
          authorsInput.value = names.join(', ');
          authorsInput.readOnly = true;
          authorsInput.classList.add('kc-relator-readonly');
        } else {
          authorsInput.readOnly = false;
          authorsInput.classList.remove('kc-relator-readonly');
        }
      }

      function setMode(enabled){
        if (enabled) {
          wrap.classList.remove('is-hidden');
          seedRows();
        } else {
          wrap.classList.add('is-hidden');
          hidden.value = '';
          authorsInput.readOnly = false;
          authorsInput.classList.remove('kc-relator-readonly');
        }
      }

      document.getElementById('relator_add').addEventListener('click', function(){
        rowsWrap.appendChild(buildRow('', 'aut'));
        sync();
      });

      toggle.addEventListener('change', function(){
        setMode(toggle.checked);
      });

      if (toggle.checked) {
        setMode(true);
      }
    })();

    // ---------- RDA Quick Check ----------
    function setupRdaPanel(){
      var panel = document.getElementById('rda_panel');
      if (!panel) return;

      var fieldTitle = document.getElementById('field_title');
      var fieldAuthors = document.getElementById('field_authors');
      var fieldPublisher = document.getElementById('field_publisher');
      var fieldPlace = document.getElementById('field_place');
      var fieldYear = document.getElementById('field_year');
      var fieldLanguage = document.getElementById('field_language');
      var fieldDdc = document.getElementById('field_ddc');
      var fieldCall = document.getElementById('field_call_number');
      var scoreEl = document.getElementById('rda_score');

      var langMap = {
        id: 'ind', en: 'eng', ms: 'msa', ar: 'ara', zh: 'zho', ja: 'jpn', ko: 'kor',
        fr: 'fra', de: 'ger', es: 'spa', ru: 'rus', it: 'ita', nl: 'dut', pt: 'por',
        hi: 'hin', ur: 'urd', th: 'tha', vi: 'vie'
      };

      function normalizeLanguage(input){
        if (!input) return;
        var val = (input.value || '').trim().toLowerCase();
        if (!val || val.length !== 2) return;
        if (!langMap[val]) return;
        input.value = langMap[val];
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }

      function setState(key, state){
        var el = panel.querySelector('.kc-rda-item[data-rda=\"' + key + '\"]');
        if (!el) return;
        el.classList.remove('is-ok', 'is-warn', 'is-note');
        el.classList.add('is-' + state);
      }

      function update(){
        var title = (fieldTitle && fieldTitle.value || '').trim();
        var authors = (fieldAuthors && fieldAuthors.value || '').trim();
        var publisher = (fieldPublisher && fieldPublisher.value || '').trim();
        var place = (fieldPlace && fieldPlace.value || '').trim();
        var year = (fieldYear && fieldYear.value || '').trim();
        var language = (fieldLanguage && fieldLanguage.value || '').trim();
        var ddc = (fieldDdc && fieldDdc.value || '').trim();
        var callNumber = (fieldCall && fieldCall.value || '').trim();

        var requiredTotal = 4;
        var okCount = 0;

        if (title) { okCount++; setState('title', 'ok'); } else { setState('title', 'warn'); }
        if (authors || publisher) { okCount++; setState('access', 'ok'); } else { setState('access', 'warn'); }
        var yearOk = /^[0-9]{4}$/.test(year);
        if (place && yearOk) { okCount++; setState('publication', 'ok'); } else { setState('publication', 'warn'); }
        var lang3 = /^[a-zA-Z]{3}$/.test(language);
        var lang2 = /^[a-zA-Z]{2}$/.test(language);
        if (lang3) {
          okCount++;
          setState('language', 'ok');
        } else if (lang2) {
          okCount++;
          setState('language', 'note');
        } else {
          setState('language', 'warn');
        }

        if (!ddc) {
          setState('ddc', 'note');
        } else if (/^\d{3}(\.\d+)?$/.test(ddc)) {
          setState('ddc', 'ok');
        } else {
          setState('ddc', 'warn');
        }

        if (!callNumber) {
          setState('call', ddc ? 'warn' : 'note');
        } else {
          setState('call', 'ok');
        }

        if (scoreEl) scoreEl.textContent = 'Skor RDA: ' + okCount + '/' + requiredTotal;
      }

      [fieldTitle, fieldAuthors, fieldPublisher, fieldPlace, fieldYear, fieldLanguage, fieldDdc, fieldCall].forEach(function(el){
        if (el) el.addEventListener('input', update);
      });
      if (fieldLanguage) fieldLanguage.addEventListener('blur', function(){ normalizeLanguage(fieldLanguage); });
      update();
    }
    setupRdaPanel();

    // ---------- ISBN Autofill + MARC/RDA Live Validation ----------
    (function(){
      var isbnBtn = document.getElementById('isbn_fetch_btn');
      var isbnStatus = document.getElementById('isbn_fetch_status');
      var isbnAutoToggle = document.getElementById('isbn_auto_toggle');
      var isbnField = document.getElementById('field_isbn');
      var fieldTitle = document.getElementById('field_title');
      var fieldSubtitle = document.getElementById('field_subtitle');
      var fieldAuthors = document.getElementById('field_authors');
      var fieldPublisher = document.getElementById('field_publisher');
      var fieldPlace = document.getElementById('field_place');
      var fieldYear = document.getElementById('field_year');
      var fieldLanguage = document.getElementById('field_language');
      var fieldPhysical = document.getElementById('field_physical_desc');
      var fieldSubjects = document.getElementById('field_subjects');
      var fieldDdc = document.getElementById('field_ddc');
      var fieldCall = document.getElementById('field_call_number');
      var notesInput = document.getElementById('notes_input');
      var marcBox = document.getElementById('marc_quality');

      var isbnLookupUrl = "{{ route('katalog.isbnLookup') }}";
      var validateUrl = "{{ route('katalog.validateMetadata') }}";
      var csrfToken = (document.querySelector('input[name=\"_token\"]') || {}).value || '';

      function setStatus(msg, isError){
        if (!isbnStatus) return;
        isbnStatus.textContent = msg || '';
        isbnStatus.style.color = isError ? '#dc2626' : '';
      }

      function setIfEmpty(el, value){
        if (!el) return;
        if ((el.value || '').trim() !== '') return;
        if (!value) return;
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
      }

      function applyIsbnData(data){
        if (!data) return;
        setIfEmpty(fieldTitle, data.title);
        setIfEmpty(fieldSubtitle, data.subtitle);
        setIfEmpty(fieldAuthors, data.authors_text);
        setIfEmpty(fieldPublisher, data.publisher);
        setIfEmpty(fieldYear, data.publish_year);
        setIfEmpty(fieldLanguage, data.language);
        setIfEmpty(fieldPhysical, data.physical_desc);
        setIfEmpty(fieldSubjects, data.subjects_text);
        if (notesInput && (notesInput.value || '').trim() === '' && data.notes) {
          notesInput.value = data.notes;
          if (notesQuill && notesQuill.root) {
            notesQuill.clipboard.dangerouslyPasteHTML(data.notes);
          }
        }
      }

      function normalizeIsbn(val){
        return (val || '').replace(/[^0-9Xx]/g, '');
      }

      function fetchIsbn(silent){
        if (!isbnField) return;
        var raw = normalizeIsbn(isbnField.value || '');
        if (!raw) {
          if (!silent) setStatus('ISBN kosong.', true);
          return;
        }
        if (![10, 13].includes(raw.length)) {
          if (!silent) setStatus('ISBN belum lengkap.', true);
          return;
        }
        setStatus('Mengambil data ISBN...', false);
        fetch(isbnLookupUrl + '?isbn=' + encodeURIComponent(raw), {
          headers: { 'Accept': 'application/json' }
        }).then(function(res){
          if (!res.ok) return res.json().then(function(j){ throw new Error(j.message || 'Gagal mengambil ISBN'); });
          return res.json();
        }).then(function(payload){
          if (!payload || !payload.ok) throw new Error(payload?.message || 'Data ISBN tidak ditemukan.');
          applyIsbnData(payload.data || {});
          setStatus('Data ISBN diterapkan.', false);
          triggerMarcValidate();
        }).catch(function(err){
          setStatus(err.message || 'Gagal mengambil ISBN.', true);
        });
      }

      if (isbnBtn) {
        isbnBtn.addEventListener('click', function(){ fetchIsbn(false); });
      }

      var isbnAutoTimer = null;
      var lastIsbnFetched = '';
      function maybeAutoFetch(){
        if (!isbnAutoToggle || !isbnAutoToggle.checked) return;
        var raw = normalizeIsbn(isbnField ? isbnField.value : '');
        if (![10, 13].includes(raw.length)) return;
        if (raw === lastIsbnFetched) return;
        lastIsbnFetched = raw;
        fetchIsbn(true);
      }
      if (isbnField) {
        isbnField.addEventListener('blur', function(){
          if (isbnAutoTimer) window.clearTimeout(isbnAutoTimer);
          maybeAutoFetch();
        });
      }

      function clearFieldStates(){
        document.querySelectorAll('.kc-field.is-warn, .kc-field.is-error').forEach(function(el){
          el.classList.remove('is-warn', 'is-error');
        });
      }

      function markField(fieldId, type){
        var field = document.getElementById(fieldId);
        if (!field) return;
        var wrap = field.closest('.kc-field');
        if (!wrap) return;
        wrap.classList.add(type === 'error' ? 'is-error' : 'is-warn');
      }

      function mapField(msg){
        var m = (msg || '').toLowerCase();
        if (m.includes('082') || m.includes('ddc')) return 'field_ddc';
        if (m.includes('nomor panggil') || m.includes('call number')) return 'field_call_number';
        if (m.includes('judul')) return 'field_title';
        if (m.includes('pengarang') || m.includes('author')) return 'field_authors';
        if (m.includes('penerbit') || m.includes('publisher')) return 'field_publisher';
        if (m.includes('tempat') || m.includes('place')) return 'field_place';
        if (m.includes('tahun') || m.includes('year')) return 'field_year';
        if (m.includes('bahasa') || m.includes('language')) return 'field_language';
        if (m.includes('isbn')) return 'field_isbn';
        if (m.includes('subjek') || m.includes('subject')) return 'field_subjects';
        if (m.includes('336') || m.includes('content type') || m.includes('jenis konten')) return 'field_material_type';
        if (m.includes('337') || m.includes('media type') || m.includes('media')) return 'field_media_type';
        return null;
      }

      function applyMarcTargets(){
        if (!marcBox) return;
        marcBox.querySelectorAll('.kc-quality-item').forEach(function(el){
          if (el.getAttribute('data-target')) return;
          var fieldId = mapField(el.textContent || '');
          if (!fieldId) return;
          el.setAttribute('data-target', '#' + fieldId);
          el.classList.add('is-clickable');
          el.setAttribute('role', 'button');
          el.tabIndex = 0;
        });
      }

      function bindMarcClicks(){
        if (!marcBox || marcBox.__nbBound) return;
        marcBox.__nbBound = true;
        marcBox.addEventListener('click', function(e){
          var item = e.target.closest('.kc-quality-item.is-clickable');
          if (!item) return;
          var target = item.getAttribute('data-target');
          if (!target) return;
          e.preventDefault();
          var field = document.querySelector(target);
          if (!field) return;
          field.scrollIntoView({ behavior: 'smooth', block: 'center' });
          if (typeof field.focus === 'function') field.focus();
        });
        marcBox.addEventListener('keydown', function(e){
          var key = (e.key || '').toLowerCase();
          if (key !== 'enter' && key !== ' ') return;
          var item = e.target.closest('.kc-quality-item.is-clickable');
          if (!item) return;
          e.preventDefault();
          item.click();
        });
      }

      function renderMarc(errors, warnings){
        if (!marcBox) return;
        var totalErrors = errors.length;
        var totalWarnings = warnings.length;
        marcBox.setAttribute('data-marc-errors', String(totalErrors));
        marcBox.setAttribute('data-marc-warnings', String(totalWarnings));

        var list = marcBox.querySelector('.kc-quality-list');
        var empty = marcBox.querySelector('.kc-quality-empty');
        if (!list) {
          list = document.createElement('div');
          list.className = 'kc-quality-list';
          marcBox.appendChild(list);
        }
        list.innerHTML = '';
        if (empty) empty.remove();

        if ((totalErrors + totalWarnings) === 0) {
          var emptyEl = document.createElement('div');
          emptyEl.className = 'kc-quality-empty';
          emptyEl.textContent = 'Tidak ada temuan kualitas saat ini.';
          marcBox.appendChild(emptyEl);
          clearFieldStates();
          return;
        }

        clearFieldStates();

        errors.forEach(function(msg){
          var div = document.createElement('div');
          div.className = 'kc-quality-item is-error';
          div.textContent = msg;
          var fieldId = mapField(msg);
          if (fieldId) {
            div.setAttribute('data-target', '#' + fieldId);
            div.classList.add('is-clickable');
            div.setAttribute('role', 'button');
            div.tabIndex = 0;
          }
          list.appendChild(div);
          if (fieldId) markField(fieldId, 'error');
        });
        warnings.forEach(function(msg){
          var div = document.createElement('div');
          div.className = 'kc-quality-item is-warn';
          div.textContent = msg;
          var fieldId = mapField(msg);
          if (fieldId) {
            div.setAttribute('data-target', '#' + fieldId);
            div.classList.add('is-clickable');
            div.setAttribute('role', 'button');
            div.tabIndex = 0;
          }
          list.appendChild(div);
          if (fieldId) markField(fieldId, 'warn');
        });
      }

      function collectPayload(){
        return {
          title: fieldTitle ? fieldTitle.value : '',
          subtitle: fieldSubtitle ? fieldSubtitle.value : '',
          authors_text: fieldAuthors ? fieldAuthors.value : '',
          publisher: fieldPublisher ? fieldPublisher.value : '',
          place_of_publication: fieldPlace ? fieldPlace.value : '',
          publish_year: fieldYear ? fieldYear.value : '',
          language: fieldLanguage ? fieldLanguage.value : '',
          ddc: fieldDdc ? fieldDdc.value : '',
          call_number: fieldCall ? fieldCall.value : '',
          isbn: isbnField ? isbnField.value : '',
          physical_desc: fieldPhysical ? fieldPhysical.value : '',
          subjects_text: fieldSubjects ? fieldSubjects.value : '',
          material_type: (document.querySelector('[name=\"material_type\"]') || {}).value || '',
          media_type: (document.querySelector('[name=\"media_type\"]') || {}).value || '',
        };
      }

      var triggerMarcValidate = debounce(function(){
        if (!validateUrl || !csrfToken) return;
        var payload = collectPayload();
        fetch(validateUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
          },
          body: JSON.stringify(payload)
        })
        .then(function(res){ return res.json(); })
        .then(function(data){
          if (!data || data.ok !== true) return;
          renderMarc(data.errors || [], data.warnings || []);
        })
        .catch(function(){});
      }, 600);

      var fieldMaterialType = document.querySelector('[name="material_type"]');
      var fieldMediaType = document.querySelector('[name="media_type"]');

      function autoSetMediaType(){
        if (!fieldMaterialType || !fieldMediaType) return;
        if ((fieldMediaType.value || '').trim() !== '') return;
        var val = (fieldMaterialType.value || '').toLowerCase();
        if (!val) return;
        var map = {
          'buku': 'teks',
          'ebook': 'teks',
          'skripsi': 'teks',
          'tesis': 'teks',
          'disertasi': 'teks',
          'referensi': 'teks',
          'komik': 'teks',
          'manual': 'teks',
          'serial': 'teks',
          'peta': 'teks',
          'audio': 'audio',
          'video': 'video',
          'software': 'teks'
        };
        if (map[val]) {
          fieldMediaType.value = map[val];
          fieldMediaType.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }

      [fieldTitle, fieldSubtitle, fieldAuthors, fieldPublisher, fieldPlace, fieldYear, fieldLanguage, fieldDdc, fieldCall, fieldSubjects, fieldPhysical, isbnField, fieldMaterialType, fieldMediaType]
        .filter(Boolean)
        .forEach(function(el){
          el.addEventListener('input', triggerMarcValidate);
          el.addEventListener('change', triggerMarcValidate);
        });

      if (fieldMaterialType) {
        fieldMaterialType.addEventListener('change', autoSetMediaType);
      }

      applyMarcTargets();
      bindMarcClicks();
      triggerMarcValidate();

      window.__nbMarcValidate = triggerMarcValidate;
    })();

    // ---------- Shortcuts ----------
    (function(){
      var form = document.querySelector('form[action*=\"katalog.store\"]') || document.querySelector('form');
      if (!form) return;
      var titleInput = document.getElementById('field_title');
      var marcBox = document.getElementById('marc_quality');
      form.addEventListener('submit', function(e){
        var marcErrors = marcBox ? parseInt(marcBox.getAttribute('data-marc-errors') || '0', 10) : 0;
        var marcWarnings = marcBox ? parseInt(marcBox.getAttribute('data-marc-warnings') || '0', 10) : 0;
        if (marcErrors > 0 && !form.__nbMarcConfirmed) {
          var msg = 'Ada ' + marcErrors + ' error MARC' + (marcWarnings > 0 ? ' dan ' + marcWarnings + ' peringatan' : '') + '. Simpan tetap?';
          if (!confirm(msg)) {
            e.preventDefault();
            return;
          }
          form.__nbMarcConfirmed = true;
        }
      });
      document.addEventListener('keydown', function(e){
        var key = (e.key || '').toLowerCase();
        if ((e.ctrlKey || e.metaKey) && key === 's') {
          e.preventDefault();
          if (form.requestSubmit) form.requestSubmit();
        }
        if ((e.ctrlKey || e.metaKey) && key === 'k') {
          e.preventDefault();
          if (titleInput) titleInput.focus();
        }
      });
    })();

    // ---------- Cover Preview ----------
    var fileInput = document.getElementById('cover_file');
    var btnPick   = document.getElementById('cover_pick');
    var btnClear  = document.getElementById('cover_clear');
    var imgEl     = document.getElementById('cover_img');
    var emptyEl   = document.getElementById('cover_empty');

    function setPreview(src){
      if(src){
        imgEl.src = src;
        imgEl.style.display = '';
        emptyEl.style.display = 'none';
        btnClear.style.display = '';
      }else{
        imgEl.removeAttribute('src');
        imgEl.style.display = 'none';
        emptyEl.style.display = '';
        btnClear.style.display = 'none';
      }
    }

    if(btnPick && fileInput){
      btnPick.addEventListener('click', function(){ fileInput.click(); });
    }
    if(btnClear && fileInput){
      btnClear.addEventListener('click', function(){
        fileInput.value = '';
        setPreview('');
      });
    }
    if(fileInput){
      fileInput.addEventListener('change', function(){
        var f = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        if(!f){ setPreview(''); return; }
        var url = URL.createObjectURL(f);
        setPreview(url);
      });
    }

    setPreview('');

    // ---------- DC i18n ----------
    var dcAddBtn = document.getElementById('dc_add_locale');
    var dcWrap = document.getElementById('dc_i18n_wrap');
    var dcSelect = document.getElementById('dc_locale_select');
    var dcCustom = document.getElementById('dc_locale_custom');
    var dcError = document.getElementById('dc_locale_error');
    function setLocaleError(msg){
      if (dcError) {
        if (msg) {
          dcError.textContent = msg;
          dcError.style.display = '';
        } else {
          dcError.textContent = '';
          dcError.style.display = 'none';
        }
      }
      var err = !!msg;
      if (dcSelect) dcSelect.style.borderColor = err ? '#dc2626' : '';
      if (dcCustom) dcCustom.style.borderColor = err ? '#dc2626' : '';
    }

    if (dcCustom) {
      dcCustom.addEventListener('blur', function(){
        var v = normalizeLocale(dcCustom.value || '');
        dcCustom.value = v;
      });
    }

    if (dcAddBtn && dcWrap) {
      dcAddBtn.addEventListener('click', function(){
        setLocaleError('');
        var locale = '';
        if (dcCustom && dcCustom.value.trim() !== '') {
          locale = dcCustom.value.trim();
        } else if (dcSelect) {
          locale = dcSelect.value;
        }
        if(!locale){
          setLocaleError('Pilih locale atau isi locale custom.');
          return;
        }
        locale = normalizeLocale(locale);
        if(!locale) return;
        if (!/^[a-z]{2}(-[A-Z]{2})?$/.test(locale)) {
          setLocaleError('Format locale tidak valid. Contoh: en atau id-ID');
          return;
        }
        if (!/^[a-z]{2}(-[A-Z]{2})?$/.test(locale)) {
          alert('Format locale tidak valid. Contoh: en atau id-ID');
          return;
        }

        var existing = dcWrap.querySelectorAll('input[name^=\"dc_i18n[\"][name$=\"][__locale]\"]');
        for (var i = 0; i < existing.length; i++) {
          if ((existing[i].value || '').toLowerCase() === locale.toLowerCase()) {
            setLocaleError('Locale sudah ada.');
            return;
          }
        }

        var block = document.createElement('div');
        block.className = 'kc-section dc-i18n-block';
        block.style.padding = '12px';
        block.style.borderRadius = '14px';
        block.innerHTML = ''
          + '<div class=\"kc-grid-3\">'
          + '  <div class=\"kc-field\"><label>Locale</label><input class=\"nb-field\" name=\"dc_i18n[' + locale + '][__locale]\" value=\"' + locale + '\" readonly></div>'
          + '  <div class=\"kc-field\"><label>Judul</label><input class=\"nb-field\" name=\"dc_i18n[' + locale + '][title]\"></div>'
          + '  <div class=\"kc-field\"><label>Bahasa</label><input class=\"nb-field\" name=\"dc_i18n[' + locale + '][language]\" value=\"' + locale + '\"></div>'
          + '</div>'
          + '<div style=\"height:8px\"></div>'
          + '<div class=\"kc-grid-2\">'
          + '  <div class=\"kc-field\"><label>Creator (pisahkan dengan ;)</label><textarea class=\"nb-field dc-creator\" name=\"dc_i18n[' + locale + '][creator]\" rows=\"2\"></textarea><div class=\"nb-muted-2 kc-help dc-creator-preview\"></div></div>'
          + '  <div class=\"kc-field\"><label>Subject (pisahkan dengan ;)</label><textarea class=\"nb-field dc-subject\" name=\"dc_i18n[' + locale + '][subject]\" rows=\"2\"></textarea><div class=\"nb-muted-2 kc-help dc-subject-preview\"></div></div>'
          + '</div>'
          + '<div style=\"height:8px\"></div>'
          + '<div class=\"kc-grid-1\">'
          + '  <div class=\"kc-field\"><label>Deskripsi</label><textarea class=\"nb-field\" name=\"dc_i18n[' + locale + '][description]\" rows=\"3\"></textarea></div>'
          + '</div>';
        block.innerHTML += '<div style=\"height:8px\"></div><div class=\"kc-actions\" style=\"margin-top:0; padding-top:0; border-top:0;\"><button type=\"button\" class=\"nb-btn dc-remove\">Hapus Locale</button></div>';
        dcWrap.appendChild(block);
        if (dcCustom) dcCustom.value = '';
        setLocaleError('');
      });
    }

    function normalizeLocale(value){
      var v = (value || '').trim();
      if(!v) return '';
      var parts = v.split('-');
      var lang = (parts[0] || '').toLowerCase();
      if (!lang) return '';
      if (parts.length > 1) {
        var region = (parts[1] || '').toUpperCase();
        return lang + '-' + region;
      }
      return lang;
    }

    function splitList(value){
      if(!value) return [];
      return value.split(/[;,\n]+/).map(function(x){ return x.trim(); }).filter(Boolean);
    }

    function updatePreview(container){
      if(!container) return;
      var creatorEl = container.querySelector('.dc-creator');
      var subjectEl = container.querySelector('.dc-subject');
      var creatorPrev = container.querySelector('.dc-creator-preview');
      var subjectPrev = container.querySelector('.dc-subject-preview');
      if (creatorEl && creatorPrev) {
        var c = splitList(creatorEl.value);
        creatorPrev.textContent = c.length ? ('Preview: [' + c.join(', ') + ']') : '';
      }
      if (subjectEl && subjectPrev) {
        var s = splitList(subjectEl.value);
        subjectPrev.textContent = s.length ? ('Preview: [' + s.join(', ') + ']') : '';
      }
    }

    function bindBlockEvents(container){
      if(!container) return;
      var creatorEl = container.querySelector('.dc-creator');
      var subjectEl = container.querySelector('.dc-subject');
      var removeBtn = container.querySelector('.dc-remove');
      if (creatorEl) {
        creatorEl.addEventListener('input', function(){ updatePreview(container); });
      }
      if (subjectEl) {
        subjectEl.addEventListener('input', function(){ updatePreview(container); });
      }
      if (removeBtn) {
        removeBtn.addEventListener('click', function(){
          if (confirm('Hapus locale ini?')) {
            container.remove();
          }
        });
      }
      updatePreview(container);
    }

    if (dcWrap) {
      var blocks = dcWrap.querySelectorAll('.dc-i18n-block');
      for (var i = 0; i < blocks.length; i++) {
        bindBlockEvents(blocks[i]);
      }
      dcWrap.addEventListener('click', function(e){
        if (e.target && e.target.classList.contains('dc-remove')) {
          var block = e.target.closest('.dc-i18n-block');
          if (block) block.remove();
        }
      });
    }

    // ---------- Identifiers ----------
    var idAddBtn = document.getElementById('id_add_row');
    var idWrap = document.getElementById('identifiers_wrap');
    if (idAddBtn && idWrap) {
      idAddBtn.addEventListener('click', function(){
        var idx = idWrap.querySelectorAll('.kc-grid-3').length;
        var row = document.createElement('div');
        row.className = 'kc-grid-3';
        row.innerHTML = ''
          + '<div class=\"kc-field\"><label>Skema</label><input class=\"nb-field\" name=\"identifiers[' + idx + '][scheme]\" placeholder=\"doi / uri / isni / orcid\"></div>'
          + '<div class=\"kc-field\"><label>Nilai</label><input class=\"nb-field\" name=\"identifiers[' + idx + '][value]\" placeholder=\"10.1234/abc\"></div>'
          + '<div class=\"kc-field\"><label>URI (opsional)</label><input class=\"nb-field\" name=\"identifiers[' + idx + '][uri]\" placeholder=\"https://example.org/\"></div>';

        var spacer = document.createElement('div');
        spacer.style.height = '8px';
        idWrap.appendChild(row);
        idWrap.appendChild(spacer);
      });
    }

  })();
</script>

@endif
@endsection
