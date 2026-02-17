{{-- resources/views/katalog/show.blade.php --}}
@extends('layouts.notobuku')

@section('title', ($biblio->display_title ?? $biblio->title ?? 'Detail Katalog') . ' - NOTOBUKU')

@section('content')
@php
  $canManage = (bool)($canManage ?? false);
  $isMemberViewer = auth()->check() && ((string)(auth()->user()->role ?? 'member') === 'member');
  $indexRouteName = $indexRouteName ?? 'katalog.index';
  $showRouteName = $showRouteName ?? 'katalog.show';
  $relatedBiblios = $relatedBiblios ?? collect();
  $currentAuthorIds = $biblio->authors?->pluck('id')->filter()->values() ?? collect();
  $currentSubjectIds = $biblio->subjects?->pluck('id')->filter()->values() ?? collect();
  $firstSubjectId = $currentSubjectIds->first();
  $firstAuthorId = $currentAuthorIds->first();

  $safe = function($v, $d='-'){
    return (isset($v) && $v !== '' && $v !== null) ? $v : $d;
  };

  // Authors
  $authors = '-';
  try{
    if(isset($biblio) && isset($biblio->authors) && method_exists($biblio->authors,'pluck')){
      $authors = $biblio->authors->pluck('name')->filter()->take(6)->implode(', ');
      $authors = $authors !== '' ? $authors : '-';
    }
  }catch(\Throwable $e){}

  // Subjects
  $subjects = '-';
  try{
    if(isset($biblio) && isset($biblio->subjects) && method_exists($biblio->subjects,'pluck')){
      $subjects = $biblio->subjects
        ->map(function($s){
          return $s->term ?? $s->name ?? null;
        })
        ->filter()
        ->take(10)
        ->implode('; ');
      $subjects = $subjects !== '' ? $subjects : '-';
    }
  }catch(\Throwable $e){}

  // Tags
  $tags = '-';
  try{
    if(isset($biblio) && isset($biblio->tags) && method_exists($biblio->tags,'pluck')){
      $tags = $biblio->tags->pluck('name')->filter()->take(12)->implode(', ');
      $tags = $tags !== '' ? $tags : '-';
    }
  }catch(\Throwable $e){}

  // Counts from withCount
  $itemsCount = (int)($biblio->items_count ?? 0);
  $availableCount = (int)($biblio->available_items_count ?? 0);

  $stateClass = $availableCount > 0 ? 'is-ok' : 'is-no';
  $avaClass = $availableCount > 0 ? 'ok' : 'no';

  // Status label (UI only; values tetap)
  $statusLabels = [
    'available' => 'Tersedia',
    'borrowed' => 'Dipinjam',
    'reserved' => 'Direservasi',
    'maintenance' => 'Perawatan',
    'damaged' => 'Rusak',
    'lost' => 'Hilang',
    'missing' => 'Hilang',
  ];

  // Stable tone by id
  $tones = ['tone-blue','tone-green','tone-indigo','tone-teal'];
  $toneClass = $tones[((int)($biblio->id ?? 0)) % 4];

  // Cover
  $coverPath = $biblio->cover_path ?? null;
  $coverUrl = $coverPath ? asset('storage/' . ltrim($coverPath,'/')) : null;

  // Notes (HTML from editor)
  $notesHtml = trim((string)($biblio->notes ?? ''));
@endphp

@if($isPublic ?? false)
  @php
    $seoTitle = trim((string)($biblio->display_title ?? $biblio->title ?? 'Detail Katalog'));
    $seoDesc = trim(strip_tags((string)($biblio->notes ?? '')));
    if ($seoDesc === '') {
      $seoDesc = 'Detail bibliografi, ketersediaan eksemplar, dan informasi klasifikasi koleksi di OPAC NOTOBUKU.';
    }
    $seoDesc = \Illuminate\Support\Str::limit($seoDesc, 160);
    $canonicalUrl = request()->url();
    $coverPath = $biblio->cover_path ?? null;
    $coverImage = $coverPath ? asset('storage/' . ltrim($coverPath, '/')) : null;
    $authorNames = collect($biblio->authors ?? [])->pluck('name')->filter()->values()->all();
    $offerAvailability = ((int)($biblio->available_items_count ?? 0)) > 0
      ? 'https://schema.org/InStock'
      : 'https://schema.org/OutOfStock';
    $bookIdentifiers = collect([
      trim((string)($biblio->isbn ?? '')),
      trim((string)($biblio->issn ?? '')),
      trim((string)($biblio->call_number ?? '')),
    ])->filter()->values()->all();
  @endphp
  @push('head')
    <meta name="description" content="{{ $seoDesc }}">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="alternate" hreflang="id-ID" href="{{ $canonicalUrl }}">
    <link rel="alternate" hreflang="x-default" href="{{ $canonicalUrl }}">
    <meta property="og:type" content="book">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDesc }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    @if($coverImage)
      <meta property="og:image" content="{{ $coverImage }}">
    @endif
    <script type="application/ld+json">
      {!! json_encode([
        '@' . 'context' => 'https://schema.org',
        '@' . 'graph' => [
          [
            '@' . 'type' => 'WebPage',
            '@' . 'id' => $canonicalUrl . '#webpage',
            'url' => $canonicalUrl,
            'name' => $seoTitle,
            'description' => $seoDesc,
            'inLanguage' => 'id-ID',
            'isPartOf' => [
              '@' . 'type' => 'WebSite',
              'name' => 'NOTOBUKU OPAC',
              'url' => route('opac.index'),
            ],
            'breadcrumb' => [
              '@' . 'id' => $canonicalUrl . '#breadcrumb',
            ],
          ],
          [
            '@' . 'type' => 'BreadcrumbList',
            '@' . 'id' => $canonicalUrl . '#breadcrumb',
            'itemListElement' => [
              [
                '@' . 'type' => 'ListItem',
                'position' => 1,
                'name' => 'OPAC',
                'item' => route('opac.index'),
              ],
              [
                '@' . 'type' => 'ListItem',
                'position' => 2,
                'name' => $seoTitle,
                'item' => $canonicalUrl,
              ],
            ],
          ],
          [
            '@' . 'type' => 'Book',
            '@' . 'id' => $canonicalUrl . '#book',
            'name' => $seoTitle,
            'author' => collect($authorNames)->map(fn($n) => ['@' . 'type' => 'Person', 'name' => $n])->values()->all(),
            'inLanguage' => (string)($biblio->language ?? 'id'),
            'isbn' => (string)($biblio->isbn ?? ''),
            'datePublished' => (string)($biblio->publish_year ?? ''),
            'publisher' => !empty($biblio->publisher) ? ['@' . 'type' => 'Organization', 'name' => (string)$biblio->publisher] : null,
            'bookFormat' => trim((string)($biblio->media_type ?? '')) !== '' ? 'https://schema.org/EBook' : 'https://schema.org/Book',
            'identifier' => $bookIdentifiers,
            'description' => $seoDesc,
            'url' => $canonicalUrl,
            'image' => $coverImage,
            'offers' => [
              '@' . 'type' => 'AggregateOffer',
              'availability' => $offerAvailability,
              'offerCount' => (int)($biblio->items_count ?? 0),
              'inventoryLevel' => [
                '@' . 'type' => 'QuantitativeValue',
                'value' => (int)($biblio->available_items_count ?? 0),
              ],
            ],
          ],
        ],
      ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}
    </script>
  @endpush
@endif

<style>
  /* =========================================================
     KATALOG SHOW — STYLE "SEBELUMNYA" + DATA LEBIH LENGKAP
     - Tidak mengubah logic/route/fitur
     - Perbaiki tabel eksemplar: rapi, tanpa scroll, tanpa kolom No. Induk
     - ✅ Tambah tombol reservasi DI daftar eksemplar (member bisa pilih perpus)
     ========================================================= */

  .nb-ks-wrap{ max-width:1180px; margin:0 auto; }

  .nb-ks-topbar{
    display:flex; align-items:center; justify-content:space-between;
    gap:10px; flex-wrap:wrap;
  }

  .nb-ks-back{
    display:inline-flex; align-items:center; gap:8px;
    text-decoration:none;
    font-weight:800;
    color: rgba(11,37,69,.80);
  }
  html.dark .nb-ks-back{ color: rgba(226,232,240,.80); }

  .nb-ks-title{
    font-size:16px;
    font-weight:900;
    letter-spacing:.15px;
    color: rgba(11,37,69,.95);
    line-height:1.2;
  }
  html.dark .nb-ks-title{ color: rgba(226,232,240,.94); }

  .nb-ks-subtitle{
    margin-top:6px;
    font-size:13px;
    font-weight:500;
    color: rgba(11,37,69,.62);
    line-height:1.35;
  }
  html.dark .nb-ks-subtitle{ color: rgba(226,232,240,.66); }

  .nb-ks-divider{
    height:1px; border:0; margin:12px 0;
    background: linear-gradient(90deg, rgba(15,23,42,.10), rgba(15,23,42,.05), rgba(15,23,42,.10));
  }
  html.dark .nb-ks-divider{
    background: linear-gradient(90deg, rgba(148,163,184,.20), rgba(148,163,184,.10), rgba(148,163,184,.20));
  }

  /* ---------- Head Card ---------- */
  .nb-ks-head{
    padding:14px;
    border-radius:18px;
    overflow:hidden;
    position:relative;
  }
  .nb-ks-head::before{
    content:"";
    position:absolute; top:0; left:0; right:0;
    height:3px;
    background: rgba(148,163,184,.28);
  }
  .nb-ks-head.is-ok::before{
    background: linear-gradient(90deg, rgba(39,174,96,.95), rgba(30,136,229,.95));
  }
  .nb-ks-head.is-no::before{
    background: rgba(148,163,184,.28);
  }
  html.dark .nb-ks-head.is-no::before{ background: rgba(148,163,184,.20); }

  .nb-ks-head.tone-blue   { background: rgba(30,136,229,.06); border-color: rgba(30,136,229,.18); }
  .nb-ks-head.tone-green  { background: rgba(39,174,96,.06); border-color: rgba(39,174,96,.18); }
  .nb-ks-head.tone-indigo { background: rgba(99,102,241,.06); border-color: rgba(99,102,241,.18); }
  .nb-ks-head.tone-teal   { background: rgba(20,184,166,.06); border-color: rgba(20,184,166,.18); }

  html.dark .nb-ks-head.tone-blue   { background: rgba(30,136,229,.12); border-color: rgba(30,136,229,.20); }
  html.dark .nb-ks-head.tone-green  { background: rgba(39,174,96,.12); border-color: rgba(39,174,96,.20); }
  html.dark .nb-ks-head.tone-indigo { background: rgba(99,102,241,.12); border-color: rgba(99,102,241,.20); }
  html.dark .nb-ks-head.tone-teal   { background: rgba(20,184,166,.12); border-color: rgba(20,184,166,.20); }

  .nb-ks-headGrid{
    display:grid;
    grid-template-columns: 1.4fr .9fr;
    gap:12px;
    align-items:start;
  }
  @media(max-width: 980px){
    .nb-ks-headGrid{ grid-template-columns: 1fr; }
  }

  .nb-ks-hero{
    display:flex;
    gap:12px;
    align-items:flex-start;
    min-width:0;
  }

  /* Cover */
  .nb-ks-cover{
    width:78px;
    height:104px;
    border-radius:16px;
    border:1px solid var(--nb-border);
    background: rgba(255,255,255,.65);
    overflow:hidden;
    flex: 0 0 auto;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  html.dark .nb-ks-cover{ background: rgba(255,255,255,.06); }
  .nb-ks-cover img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
  }
  .nb-ks-cover-fallback{
    width:100%;
    height:100%;
    display:flex;
    align-items:center;
    justify-content:center;
    color: rgba(11,37,69,.70);
  }
  html.dark .nb-ks-cover-fallback{ color: rgba(226,232,240,.72); }
  .nb-ks-cover-fallback svg{ width:26px; height:26px; opacity:.9; }

  .nb-ks-metaRow{
    margin-top:8px;
    font-size:13px;
    font-weight:500;
    color: rgba(11,37,69,.72);
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    line-height:1.35;
  }
  html.dark .nb-ks-metaRow{ color: rgba(226,232,240,.74); }

  .nb-ks-badges{ margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; }
  .nb-ks-badges.stack{ flex-direction:column; align-items:flex-start; gap:6px; }
  .nb-ks-badges.stack .nb-badge{ display:inline-flex; width:fit-content; max-width:100%; }
  .nb-ks-badges .nb-badge{
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size:12px;
    letter-spacing:.12px;
  }

  /* ---------- Right side: stats + actions ---------- */
  .nb-ks-side{
    display:flex;
    flex-direction:column;
    gap:10px;
  }

  .nb-ks-stats{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:10px;
  }
  @media(max-width: 520px){
    .nb-ks-stats{ grid-template-columns: 1fr; }
  }
  .nb-ks-stat{
    padding:12px;
    border-radius:16px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.70);
  }
  html.dark .nb-ks-stat{
    border-color: rgba(148,163,184,.14);
    background: rgba(15,23,42,.40);
  }
  .nb-ks-stat .k{
    font-size:12px;
    font-weight:700;
    color: rgba(11,37,69,.62);
  }
  html.dark .nb-ks-stat .k{ color: rgba(226,232,240,.66); }
  .nb-ks-stat .v{
    margin-top:4px;
    font-size:15px;
    font-weight:900;
    letter-spacing:.1px;
    color: rgba(11,37,69,.92);
  }
  html.dark .nb-ks-stat .v{ color: rgba(226,232,240,.92); }

  .nb-ks-avapill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    border-radius:999px;
    padding:6px 12px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.75);
    font-size:12px;
    font-weight:750;
    color: rgba(11,37,69,.78);
    white-space:nowrap;
  }
  html.dark .nb-ks-avapill{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.40);
    color: rgba(226,232,240,.78);
  }
  .nb-ks-avapill.no{ border-color: rgba(148,163,184,.18); background: rgba(148,163,184,.10); color: rgba(11,37,69,.70); }
  html.dark .nb-ks-avapill.no{ border-color: rgba(148,163,184,.16); background: rgba(148,163,184,.10); color: rgba(226,232,240,.70); }

  .nb-ks-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    justify-content:flex-end;
    align-items:center;
  }
  @media(max-width: 980px){
    .nb-ks-actions{ justify-content:flex-start; }
  }

  .nb-ks-ibtn{
    width:44px;
    height:44px;
    border-radius:16px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.78);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    user-select:none;
    text-decoration:none;
    transition: background .12s ease, border-color .12s ease, box-shadow .12s ease, transform .06s ease, color .12s ease;
    color: rgba(11,37,69,.92);
  }
  html.dark .nb-ks-ibtn{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.40);
    color: rgba(226,232,240,.92);
  }
  .nb-ks-ibtn:active{ transform: translateY(1px); }
  .nb-ks-ibtn svg{ width:18px; height:18px; }

  .nb-ks-ibtn.primary{
    border-color: rgba(30,136,229,.22);
    background: linear-gradient(180deg, rgba(30,136,229,1), rgba(21,101,192,1));
    color:#fff;
    box-shadow: 0 14px 26px rgba(30,136,229,.22);
  }
  .nb-ks-ibtn.primary svg{ color:#fff; }

  .nb-ks-ibtn.danger{
    border-color: rgba(220,38,38,.22);
    background: rgba(220,38,38,.06);
    color: rgba(220,38,38,.92);
  }
  html.dark .nb-ks-ibtn.danger{
    border-color: rgba(248,113,113,.22);
    background: rgba(220,38,38,.14);
    color: rgba(248,113,113,.92);
  }

  .nb-ks-ibtn.neutral:hover{
    background: rgba(255,255,255,.92);
    border-color: rgba(30,136,229,.18);
    box-shadow: 0 14px 26px rgba(2,6,23,.06);
  }
  html.dark .nb-ks-ibtn.neutral:hover{
    background: rgba(255,255,255,.08);
    border-color: rgba(147,197,253,.18);
    box-shadow: 0 14px 26px rgba(0,0,0,.22);
  }

  .nb-ks-secTitle{
    font-size:13.5px;
    font-weight:900;
    letter-spacing:.12px;
    color: rgba(11,37,69,.92);
  }
  html.dark .nb-ks-secTitle{ color: rgba(226,232,240,.92); }

  .nb-ks-secSub{
    margin-top:4px;
    font-size:12.8px;
    font-weight:500;
    color: rgba(11,37,69,.62);
    line-height:1.35;
  }
  html.dark .nb-ks-secSub{ color: rgba(226,232,240,.66); }

  /* Data grid (metadata) */
  .nb-ks-metaGrid{
    display:grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap:10px;
  }
  @media(max-width: 900px){
    .nb-ks-metaGrid{ grid-template-columns: 1fr; }
  }

  .nb-ks-kv{
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.70);
    border-radius:16px;
    padding:12px;
  }
  html.dark .nb-ks-kv{
    border-color: rgba(148,163,184,.14);
    background: rgba(15,23,42,.40);
  }
  .nb-ks-kv .k{
    font-size:12px;
    font-weight:800;
    color: rgba(11,37,69,.62);
  }
  html.dark .nb-ks-kv .k{ color: rgba(226,232,240,.66); }
  .nb-ks-kv .v{
    margin-top:6px;
    font-size:13px;
    font-weight:500;
    color: rgba(11,37,69,.88);
    line-height:1.55;
    word-break:break-word;
  }
  html.dark .nb-ks-kv .v{ color: rgba(226,232,240,.86); }

  /* Notes viewer (HTML) */
  .nb-ks-notes{
    margin-top:10px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.70);
    border-radius:16px;
    padding:12px;
  }
  html.dark .nb-ks-notes{
    border-color: rgba(148,163,184,.14);
    background: rgba(15,23,42,.40);
  }
  .nb-ks-notes .v{
    font-size:13px;
    font-weight:500;
    color: rgba(11,37,69,.88);
    line-height:1.65;
  }
  html.dark .nb-ks-notes .v{ color: rgba(226,232,240,.86); }
  .nb-ks-notes .v p{ margin:0 0 10px; }
  .nb-ks-notes .v p:last-child{ margin-bottom:0; }
  .nb-ks-notes .v ul, .nb-ks-notes .v ol{ padding-left:18px; margin:8px 0; }
  .nb-ks-notes .v a{ color: inherit; text-decoration: underline; }

  /* ---------- Eksemplar chips + actions ---------- */
  .nb-ks-mono{
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-weight:650;
    letter-spacing:.12px;
    font-size:12px;
  }

  .nb-ks-chip{
    display:inline-flex;
    align-items:center;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.70);
    font-size:12px;
    font-weight:750;
    color: rgba(11,37,69,.76);
    white-space:nowrap;
  }
  html.dark .nb-ks-chip{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.40);
    color: rgba(226,232,240,.76);
  }
  .nb-ks-chip.ok{ border-color: rgba(39,174,96,.22); background: rgba(39,174,96,.10); color: rgba(20,132,70,.95); }
  html.dark .nb-ks-chip.ok{ border-color: rgba(39,174,96,.22); background: rgba(39,174,96,.14); color: rgba(134,239,172,.92); }
  .nb-ks-chip.bad{ border-color: rgba(231,76,60,.22); background: rgba(231,76,60,.10); color: rgba(176,46,34,.95); }
  html.dark .nb-ks-chip.bad{ border-color: rgba(248,113,113,.22); background: rgba(231,76,60,.14); color: rgba(254,202,202,.92); }

  .nb-ks-miniBtn{
    width:34px;
    height:34px;
    border-radius:12px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.80);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    user-select:none;
    text-decoration:none;
    color: rgba(11,37,69,.86);
    transition: background .12s ease, border-color .12s ease, box-shadow .12s ease, transform .06s ease, color .12s ease;
  }
  html.dark .nb-ks-miniBtn{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.42);
    color: rgba(226,232,240,.88);
  }
  .nb-ks-miniBtn:active{ transform: translateY(1px); }
  .nb-ks-miniBtn svg{ width:16px; height:16px; }

  .nb-ks-miniBtn.edit{
    border-color: rgba(30,136,229,.18);
    color: rgba(30,136,229,.92);
    background: rgba(30,136,229,.08);
  }
  html.dark .nb-ks-miniBtn.edit{
    border-color: rgba(147,197,253,.18);
    color: rgba(147,197,253,.92);
    background: rgba(30,136,229,.16);
  }
  .nb-ks-miniBtn.del{
    border-color: rgba(220,38,38,.22);
    color: rgba(220,38,38,.92);
    background: rgba(220,38,38,.06);
  }
  html.dark .nb-ks-miniBtn.del{
    border-color: rgba(248,113,113,.22);
    color: rgba(248,113,113,.92);
    background: rgba(220,38,38,.14);
  }

  .nb-ks-actionsRow{
    display:flex;
    gap:8px;
    align-items:center;
    justify-content:flex-end;
    white-space:nowrap;
  }

  /* ✅ TABLE: rapi, NO SCROLL, NO kolom No. Induk */
  .nb-ks-tableWrap{ overflow: visible; }
  .nb-ks-table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    table-layout: fixed;
    min-width: 0;
  }
  .nb-ks-table thead th{
    text-align:left;
    font-size:12px;
    font-weight:800;
    color: rgba(11,37,69,.74);
    padding:10px 12px;
    border-bottom:1px solid var(--nb-border);
    background: rgba(255,255,255,.55);
    position:sticky; top:0;
    white-space:nowrap;
  }
  html.dark .nb-ks-table thead th{
    color: rgba(226,232,240,.74);
    background: rgba(255,255,255,.06);
  }
  .nb-ks-table tbody td{
    padding:12px 12px;
    border-bottom:1px solid rgba(15,23,42,.08);
    font-size:13px;
    color: rgba(11,37,69,.86);
    vertical-align:middle;
  }
  html.dark .nb-ks-table tbody td{
    border-bottom-color: rgba(148,163,184,.10);
    color: rgba(226,232,240,.86);
  }

  .nb-ks-mono{
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .nb-ks-subline{
    margin-top:4px;
    font-size:12px;
    color: rgba(11,37,69,.55);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  html.dark .nb-ks-subline{ color: rgba(226,232,240,.55); }

  .nb-ks-td-center{ text-align:center; }
  .nb-ks-td-center .nb-ks-chip{ justify-content:center; }

  .nb-ks-loc{
    display:flex;
    flex-direction:column;
    gap:4px;
    min-width:0;
  }
  .nb-ks-loc .b{
    font-weight:700;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .nb-ks-loc .s{
    font-size:12.3px;
    color: rgba(11,37,69,.62);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  html.dark .nb-ks-loc .s{ color: rgba(226,232,240,.62); }

  /* tombol reservasi (member) */
  .nb-ks-resBtn{ display:inline-flex; align-items:center; gap:8px; }
  .nb-ks-resBtn svg{ width:16px; height:16px; }

  /* ✅ NO SCROLL: di layar kecil ubah jadi card */
  @media (max-width: 860px){
    .nb-ks-tableWrap{ overflow: visible; }
    .nb-ks-table{ table-layout: auto; }
    .nb-ks-table thead{ display:none; }
    .nb-ks-table,
    .nb-ks-table tbody,
    .nb-ks-table tr,
    .nb-ks-table td{
      display:block;
      width:100%;
    }
    .nb-ks-table tr{
      border:1px solid rgba(15,23,42,.10);
      border-radius:16px;
      background: rgba(255,255,255,.70);
      padding:14px;
      margin-bottom:12px;
      box-shadow: 0 8px 16px rgba(15,23,42,.06);
    }
    html.dark .nb-ks-table tr{
      border-color: rgba(148,163,184,.14);
      background: rgba(15,23,42,.40);
    }
    .nb-ks-table tbody td{
      border:0;
      padding:8px 0;
    }
    .nb-ks-table tbody td[data-label]::before{
      content: attr(data-label);
      display:block;
      font-size:12px;
      font-weight:800;
      color: rgba(11,37,69,.62);
      margin-bottom:4px;
    }
    html.dark .nb-ks-table tbody td[data-label]::before{
      color: rgba(226,232,240,.66);
    }
    .nb-ks-table tr{
      position:relative;
    }
    .nb-ks-mobileBadges{
      display:flex;
      gap:8px;
      align-items:center;
      flex-wrap:wrap;
      margin-bottom:10px;
    }
    .nb-ks-mobileBadges .nb-ks-chip{
      margin:0;
    }
    .nb-ks-table tbody td[data-label="Status"],
    .nb-ks-table tbody td[data-label="Kondisi"]{
      display:none;
    }
    .nb-ks-actionsRow{ justify-content:flex-start; }
    .nb-ks-td-center{ text-align:left; }
  }

  .nb-ks-paging{ overflow:auto; }

  /* ---------- Related Books ---------- */
  .nb-ks-relatedGrid{
    display:grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap:16px;
  }
  .nb-ks-relatedSection{
    background: rgba(255,255,255,.85);
  }
  .nb-ks-relatedHeader{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
  }
  .nb-ks-relatedText{
    display:flex;
    flex-direction:column;
    gap:4px;
  }
  .nb-ks-relatedTitleLink{
    text-decoration:none;
    color:inherit;
  }
  .nb-ks-relatedActions{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
  }
  .nb-ks-relatedCta{
    border-radius:999px !important;
    font-size:12px;
    padding:6px 10px;
  }
  .nb-ks-relatedCta.is-disabled{
    opacity:.55;
    cursor:not-allowed;
    pointer-events:none;
  }
  .nb-ks-relatedLabel{
    font-size:11.5px;
    font-weight:700;
    color: rgba(11,37,69,.60);
  }
  html.dark .nb-ks-relatedLabel{ color: rgba(226,232,240,.62); }
  .nb-ks-relatedDivider{
    height:1px;
    margin:12px 0 14px;
    background: linear-gradient(90deg, rgba(15,23,42,.06), rgba(15,23,42,.12), rgba(15,23,42,.06));
  }
  @media (max-width: 980px){
    .nb-ks-relatedGrid{ grid-template-columns: repeat(2, minmax(0,1fr)); }
  }
  @media (max-width: 640px){
    .nb-ks-relatedGrid{ grid-template-columns: 1fr; }
  }
  .nb-ks-relatedCard{
    padding:14px;
    border-radius:18px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.80);
    display:flex;
    gap:12px;
    align-items:flex-start;
    text-decoration:none;
    transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease, background .12s ease;
  }
  .nb-ks-relatedCard:hover{
    transform: translateY(-2px);
    border-color: rgba(30,136,229,.18);
    box-shadow: 0 12px 24px rgba(15,23,42,.08);
    background: rgba(255,255,255,.95);
  }
  html.dark .nb-ks-relatedCard{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.40);
  }
  html.dark .nb-ks-relatedCard:hover{
    border-color: rgba(99,102,241,.30);
    background: rgba(15,23,42,.55);
    box-shadow: 0 16px 28px rgba(2,6,23,.35);
  }
  .nb-ks-relatedCover{
    width:52px;
    height:70px;
    border-radius:10px;
    border:1px solid var(--nb-border);
    overflow:hidden;
    background: rgba(255,255,255,.60);
    display:flex;
    align-items:center;
    justify-content:center;
    flex: 0 0 auto;
  }
  .nb-ks-relatedInfo{
    min-width:0;
    display:flex;
    flex-direction:column;
    gap:6px;
    width:100%;
  }
  html.dark .nb-ks-relatedCover{ background: rgba(255,255,255,.08); }
  .nb-ks-relatedCover img{ width:100%; height:100%; object-fit:cover; }
  .nb-ks-relatedTitle{
    font-size:13.5px;
    font-weight:800;
    color: rgba(11,37,69,.92);
    line-height:1.35;
    display:-webkit-box;
    -webkit-line-clamp:2;
    -webkit-box-orient:vertical;
    overflow:hidden;
  }
  html.dark .nb-ks-relatedTitle{ color: rgba(226,232,240,.92); }
  .nb-ks-relatedMeta{
    margin-top:0;
    font-size:12.2px;
    color: rgba(11,37,69,.62);
  }
  html.dark .nb-ks-relatedMeta{ color: rgba(226,232,240,.66); }
  .nb-ks-relatedReason{
    margin-top:0;
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 8px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(15,23,42,.04);
    font-size:11.5px;
    font-weight:700;
    color: rgba(11,37,69,.74);
    position:relative;
    text-decoration:none;
  }
  button.nb-ks-relatedReason{
    appearance:none;
    background: rgba(15,23,42,.04);
    cursor:pointer;
  }
  /* tooltip styles removed (reason badge now explicit) */
  .nb-ks-relatedReason.is-subject{
    border-color: rgba(30,136,229,.22);
    background: rgba(30,136,229,.08);
    color: rgba(30,136,229,.92);
  }
  .nb-ks-relatedReason.is-author{
    border-color: rgba(20,184,166,.22);
    background: rgba(20,184,166,.10);
    color: rgba(13,148,136,.95);
  }
  .nb-ks-relatedReason.is-generic{
    border-color: rgba(148,163,184,.18);
    background: rgba(148,163,184,.10);
    color: rgba(51,65,85,.78);
  }
  html.dark .nb-ks-relatedReason{
    border-color: rgba(148,163,184,.16);
    background: rgba(148,163,184,.10);
    color: rgba(226,232,240,.74);
  }
  html.dark .nb-ks-relatedReason.is-subject{
    border-color: rgba(99,102,241,.28);
    background: rgba(99,102,241,.16);
    color: rgba(199,210,254,.92);
  }
  html.dark .nb-ks-relatedReason.is-author{
    border-color: rgba(45,212,191,.26);
    background: rgba(45,212,191,.16);
    color: rgba(153,246,228,.92);
  }
  html.dark .nb-ks-relatedReason.is-generic{
    border-color: rgba(148,163,184,.22);
    background: rgba(148,163,184,.12);
    color: rgba(226,232,240,.74);
  }
  .nb-ks-relatedAva{
    margin-top:8px;
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 8px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    font-size:11.5px;
    font-weight:700;
    color: rgba(11,37,69,.74);
  }
  .nb-ks-relatedBadges{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
  }
  .nb-ks-tip{
    position:relative;
    display:inline-flex;
    align-items:center;
    gap:6px;
  }
  .nb-ks-tip-bubble{
    position:absolute;
    left:0;
    top:calc(100% + 6px);
    background: rgba(15,23,42,.94);
    color:#fff;
    font-size:11px;
    font-weight:600;
    padding:6px 8px;
    border-radius:8px;
    white-space:nowrap;
    opacity:0;
    pointer-events:none;
    transform: translateY(-4px);
    transition: opacity .12s ease, transform .12s ease;
    box-shadow: 0 10px 18px rgba(15,23,42,.18);
    z-index:5;
  }
  .nb-ks-tip:hover .nb-ks-tip-bubble,
  .nb-ks-tip:focus-within .nb-ks-tip-bubble{
    opacity:1;
    transform: translateY(0);
  }
  html.dark .nb-ks-tip-bubble{
    background: rgba(2,6,23,.92);
    color: rgba(226,232,240,.98);
  }

  .nb-ks-section{
    margin-top:16px;
  }
  @media (max-width: 768px){
    .nb-ks-section{ margin-top:22px; }
    .nb-ks-head{ padding:16px; }
    .nb-ks-metaGrid{ gap:12px; }
    .nb-ks-notes{ margin-top:14px; }
  }
  html.dark .nb-ks-relatedAva{
    border-color: rgba(148,163,184,.16);
    color: rgba(226,232,240,.74);
  }

  .nb-ks-itemsMobile{ display:none; }
  @media (max-width: 768px){
    .nb-ks-tableWrap{ display:none; }
    .nb-ks-itemsMobile{
      display:grid;
      gap:10px;
      margin-top:4px;
    }
    .nb-ks-itemCard{
      border:1px solid var(--nb-border);
      background: var(--nb-surface);
      border-radius:14px;
      padding:12px;
      box-shadow: 0 10px 20px rgba(2,6,23,.05);
      display:grid;
      gap:8px;
    }
    .nb-ks-itemRow{
      display:flex;
      justify-content:space-between;
      gap:10px;
      align-items:flex-start;
      flex-wrap:wrap;
    }
    .nb-ks-itemMeta{
      font-size:12.5px;
      color: rgba(11,37,69,.72);
    }
    html.dark .nb-ks-itemMeta{ color: rgba(226,232,240,.72); }
    .nb-ks-itemBadges{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      align-items:center;
    }
  }
</style>

<div class="nb-ks-wrap">

  {{-- TOP NAV --}}
  <div class="nb-ks-topbar">
    <a href="{{ route($indexRouteName) }}" class="nb-ks-back">
      <span aria-hidden="true" style="display:inline-flex;width:18px;height:18px;align-items:center;justify-content:center;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      Kembali
    </a>

    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:flex-end;">
      @if($isPublic)
        <span style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; border:1px solid rgba(30,136,229,.20); background:rgba(30,136,229,.08); font-size:11.5px; font-weight:800; letter-spacing:.08px; color:rgba(30,136,229,.95);">
          OPAC Mode
        </span>
      @endif
      <span class="nb-ks-avapill {{ $avaClass }}">
        {{ (int)$availableCount }} tersedia / {{ (int)$itemsCount }} eksemplar
      </span>

      @if($canManage)
        {{-- Kelola Eksemplar --}}
        <a class="nb-ks-ibtn neutral" href="{{ route('eksemplar.index', $biblio->id) }}" title="Kelola Eksemplar" aria-label="Kelola Eksemplar">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path fill="currentColor" d="M4 6h16v2H4V6Zm0 5h16v2H4v-2Zm0 5h10v2H4v-2Z"/>
          </svg>
        </a>

        {{-- Tambah Eksemplar --}}
        <a class="nb-ks-ibtn primary" href="{{ route('eksemplar.create', $biblio->id) }}" title="Tambah Eksemplar" aria-label="Tambah Eksemplar">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path fill="currentColor" d="M19 11H13V5h-2v6H5v2h6v6h2v-6h6v-2Z"/>
          </svg>
        </a>

        {{-- Edit Bibliografi --}}
        <a class="nb-ks-ibtn neutral" href="{{ route('katalog.edit', $biblio->id) }}" title="Edit Bibliografi" aria-label="Edit Bibliografi">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path fill="currentColor" d="m14.06 9.02.92.92L6.92 18H6v-.92l8.06-8.06ZM17.66 3c-.25 0-.51.1-.7.29l-1.83 1.83 3.05 3.05 1.83-1.83c.39-.39.39-1.02 0-1.41l-1.83-1.83c-.2-.2-.45-.29-.7-.29ZM14.06 6.19 4 16.25V20h3.75L17.81 9.94l-3.75-3.75Z"/>
          </svg>
        </a>

        {{-- Hapus Bibliografi --}}
        <form method="POST" action="{{ route('katalog.destroy', $biblio->id) }}" style="margin:0;"
          onsubmit="return confirm('Hapus bibliografi ini? Catatan: Tidak bisa dihapus jika masih punya eksemplar.');">
          @csrf
          @method('DELETE')
          <button type="submit" class="nb-ks-ibtn danger" title="Hapus Bibliografi" aria-label="Hapus Bibliografi">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
              <path fill="currentColor" d="M9 3h6l1 2h5v2H3V5h5l1-2Zm1 7h2v9h-2v-9Zm4 0h2v9h-2v-9ZM7 10h2v9H7v-9Z"/>
            </svg>
          </button>
        </form>
      @endif
    </div>
  </div>

  <div style="height:10px;"></div>

  {{-- FLASH --}}
  @if(session('success'))
    <div class="nb-card" style="padding:12px 14px; border:1px solid rgba(39,174,96,.25); background:rgba(39,174,96,.08); border-radius:18px;">
      <div style="font-size:12.5px; font-weight:900; letter-spacing:.12px;">Berhasil</div>
      <div class="nb-muted-2" style="margin-top:4px;">{{ session('success') }}</div>
    </div>
    <div style="height:10px;"></div>
  @endif

  @if(session('error'))
    <div class="nb-card" style="padding:12px 14px; border:1px solid rgba(220,38,38,.25); background:rgba(220,38,38,.06); border-radius:18px;">
      <div style="font-size:12.5px; font-weight:900; letter-spacing:.12px;">Gagal</div>
      <div class="nb-muted-2" style="margin-top:4px;">{{ session('error') }}</div>
    </div>
    <div style="height:10px;"></div>
  @endif

  {{-- HEAD (detail biblio) --}}
  <div class="nb-card nb-ks-head {{ $stateClass }} {{ $toneClass }}">
    <div class="nb-ks-headGrid">

      <div style="min-width:0;">
        <div class="nb-ks-hero">

          {{-- Cover --}}
          <div class="nb-ks-cover" title="Cover buku">
            @if($coverUrl)
              <img src="{{ $coverUrl }}" alt="Cover {{ $safe($biblio->display_title ?? $biblio->title) }}">
            @else
              <div class="nb-ks-cover-fallback" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                  <path d="M6 4.2h10A2.2 2.2 0 0 1 18.2 6.4V20H7.4A1.4 1.4 0 0 0 6 21.4V4.2Z" stroke="currentColor" stroke-width="1.7"/>
                  <path d="M6 18.8h12.2" stroke="currentColor" stroke-width="1.7" opacity=".9"/>
                </svg>
              </div>
            @endif
          </div>

          <div style="min-width:0; flex:1;">
            <div class="nb-ks-title">{{ $safe($biblio->display_title ?? $biblio->title, 'Detail Katalog') }}</div>

            @if(!empty($biblio->subtitle))
              <div class="nb-ks-subtitle">{{ $biblio->subtitle }}</div>
            @else
              <div class="nb-ks-subtitle">Informasi bibliografi + daftar eksemplar.</div>
            @endif

            <div class="nb-ks-metaRow">
              <span><b>Pengarang:</b> {{ $authors }}</span>
              @if(!empty($biblio->publisher))
                <span>- <b>Penerbit:</b> {{ $biblio->publisher }}</span>
              @endif
              @if(!empty($biblio->publish_year))
                <span>- <b>Tahun:</b> {{ $biblio->publish_year }}</span>
              @endif
              @if(!empty($biblio->language))
                <span>- <b>Bahasa:</b> {{ $biblio->language }}</span>
              @endif
            </div>

            <div class="nb-ks-badges stack">
              <span class="nb-badge">ISBN: {{ $safe($biblio->isbn ?? null) }}</span>
              <span class="nb-badge nb-badge-blue">DDC: {{ $safe($biblio->ddc ?? null) }}</span>
              <span class="nb-badge">No. Panggil: {{ $safe($biblio->call_number ?? null) }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="nb-ks-side">
        <div class="nb-ks-stats">
          <div class="nb-ks-stat">
            <div class="k">Total Eksemplar</div>
            <div class="v">{{ (int)$itemsCount }}</div>
          </div>
          <div class="nb-ks-stat">
            <div class="k">Tersedia</div>
            <div class="v">{{ (int)$availableCount }}</div>
          </div>
        </div>

        <div class="nb-ks-stat" style="padding:12px; border-radius:16px;">
          <div class="k">Ketersediaan</div>
          <div style="margin-top:8px;">
            <span class="nb-ks-avapill {{ $avaClass }}">
              {{ (int)$availableCount }} tersedia / {{ (int)$itemsCount }} total
            </span>
          </div>
          <div class="nb-ks-secSub" style="margin-top:8px;">
            Status tersedia dihitung dari eksemplar dengan status <span class="nb-ks-mono">available</span>.
          </div>
        </div>
      </div>

    </div>
  </div>

  <div style="height:12px;"></div>

  {{-- METADATA DETAIL --}}
  <div class="nb-card nb-ks-section" style="padding:14px;">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
      <div>
        <div class="nb-ks-secTitle">Detail Bibliografi</div>
        <div class="nb-ks-secSub">Lengkapi data agar siap rak & pencarian lebih akurat.</div>
      </div>

      @if($canManage)
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:flex-end;">
          <a href="{{ route('katalog.edit', $biblio->id) }}" class="nb-btn" style="border-radius:16px;">
            Edit Bibliografi
          </a>
        </div>
      @endif
    </div>

    <hr class="nb-ks-divider">

    <div class="nb-ks-metaGrid">
      <div class="nb-ks-kv">
        <div class="k">Subjek / Tajuk</div>
        <div class="v">{{ $subjects }}</div>
      </div>

      <div class="nb-ks-kv">
        <div class="k">Tag</div>
        <div class="v">{{ $tags }}</div>
      </div>

      <div class="nb-ks-kv">
        <div class="k">Tempat Terbit</div>
        <div class="v">{{ $safe($biblio->place_of_publication ?? null) }}</div>
      </div>

      <div class="nb-ks-kv">
        <div class="k">Edisi</div>
        <div class="v">{{ $safe($biblio->edition ?? null) }}</div>
      </div>

      <div class="nb-ks-kv" style="grid-column: 1 / -1;">
        <div class="k">Deskripsi Fisik</div>
        <div class="v">{{ $safe($biblio->physical_desc ?? null) }}</div>
      </div>
    </div>

  <div class="nb-ks-notes">
      <div class="k" style="font-size:12px;font-weight:800;color:rgba(11,37,69,.62);">Catatan</div>
      <div class="v" style="margin-top:6px;">
        @if($notesHtml !== '')
          {!! $notesHtml !!}
        @else
          <span class="nb-muted-2">-</span>
        @endif
      </div>
    </div>
  </div>

  <div style="height:12px;"></div>

  {{-- LAMPIRAN DIGITAL --}}
  <div class="nb-card nb-ks-section" style="padding:14px;">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
      <div>
        <div class="nb-ks-secTitle">Lampiran Digital</div>
        <div class="nb-ks-secSub">Dokumen atau file pendukung untuk koleksi ini.</div>
      </div>
    </div>

    <hr class="nb-ks-divider">

    @php
      $attachmentList = $attachments ?? collect();
      $downloadRoute = $isPublic ? 'opac.attachments.download' : 'katalog.attachments.download';
      $formatSize = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes <= 0) return '-';
        $units = ['B','KB','MB','GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
          $bytes /= 1024;
          $i++;
        }
        return number_format($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
      };
    @endphp

    @if($attachmentList->isEmpty())
      <div class="nb-muted-2">Belum ada lampiran.</div>
    @else
      <div style="display:flex; flex-direction:column; gap:10px;">
        @foreach($attachmentList as $att)
          @php
            $mime = strtolower((string)($att->mime_type ?? ''));
            $isPdf = $mime === 'application/pdf' || str_ends_with(strtolower((string)($att->file_name ?? '')), '.pdf');
            $inlineUrl = route($downloadRoute, [$biblio->id, $att->id]) . '?inline=1';
          @endphp
          <div style="border:1px solid var(--nb-border); border-radius:12px; padding:10px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
            <div style="min-width:0;">
              <div style="font-weight:700;">{{ $att->title }}</div>
              <div class="nb-muted-2" style="font-size:12px;">
                {{ strtoupper((string)($att->visibility ?? 'staff')) }}
                @if(!empty($att->file_name)) - {{ $att->file_name }} @endif
                @if(!empty($att->file_size)) - {{ $formatSize($att->file_size) }} @endif
              </div>
            </div>
            <div>
              <a class="nb-btn" href="{{ route($downloadRoute, [$biblio->id, $att->id]) }}" style="border-radius:12px;">Unduh</a>
            </div>
          </div>
          @if($isPdf)
            <details style="margin-top:-6px;" data-pdf-url="{{ $inlineUrl }}" data-pdf-canvas="pdf_canvas_{{ $att->id }}">
              <summary class="nb-btn" style="display:inline-flex; border-radius:12px; margin-bottom:8px;">Preview PDF</summary>
              <div style="border:1px solid var(--nb-border); border-radius:12px; overflow:hidden; padding:10px;">
                <div class="nb-muted-2" style="font-size:12px; margin-bottom:8px;">Preview PDF (halaman 1).</div>
                <div class="nb-ks-pdf-toolbar" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:10px;">
                  <button type="button" class="nb-btn nb-ks-pdf-prev">Prev</button>
                  <button type="button" class="nb-btn nb-ks-pdf-next">Next</button>
                  <span class="nb-muted-2 nb-ks-pdf-page" style="font-size:12px;">Hal 1 / 1</span>
                  <input type="number" class="nb-field nb-ks-pdf-jump" min="1" value="1" style="width:90px; height:32px;" placeholder="Ke hal">
                  <div style="flex:1 1 auto;"></div>
                  <button type="button" class="nb-btn nb-ks-pdf-zoom-out">-</button>
                  <button type="button" class="nb-btn nb-ks-pdf-zoom-in">+</button>
                  <span class="nb-muted-2 nb-ks-pdf-zoom" style="font-size:12px;">100%</span>
                  <button type="button" class="nb-btn nb-ks-pdf-fit">Fit Width</button>
                  <button type="button" class="nb-btn nb-ks-pdf-full">Fullscreen</button>
                </div>
                <div class="nb-ks-pdf-wrap" style="width:100%; overflow:auto;">
                  <canvas id="pdf_canvas_{{ $att->id }}" style="max-width:100%;"></canvas>
                </div>
              </div>
            </details>
          @endif
        @endforeach
      </div>
    @endif
  </div>

  <div style="height:12px;"></div>

  {{-- EKSEMPLAR --}}
  <div class="nb-card nb-ks-section" style="padding:14px;">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap;">
      <div>
        <div class="nb-ks-secTitle">Daftar Eksemplar</div>
        <div class="nb-ks-secSub">Setiap eksemplar punya barcode, status, kondisi, dan lokasi. @if($isMemberViewer)Klik <b style="color:inherit;">Reservasi di sini</b> pada lokasi perpustakaan yang kamu pilih.@endif</div>
      </div>

      @if($canManage)
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:flex-end;">
          <a href="{{ route('eksemplar.index', $biblio->id) }}" class="nb-btn" style="border-radius:16px;">
            Kelola
          </a>
          <a href="{{ route('eksemplar.create', $biblio->id) }}" class="nb-btn nb-btn-primary" style="border-radius:16px;">
            Tambah Eksemplar
          </a>
        </div>
      @endif
    </div>

    <hr class="nb-ks-divider">

    @php
      $itemsPaginator = $items ?? null;
      $hasItems = $itemsPaginator && method_exists($itemsPaginator,'count') ? ($itemsPaginator->count() > 0) : false;
    @endphp

    @if(!$hasItems)
      <div class="nb-muted-2" style="padding:10px 0;">
        Belum ada eksemplar.
        @if($canManage)
          <span>Silakan klik <b style="color:inherit;">Tambah Eksemplar</b>.</span>
        @endif
      </div>
    @else
      <div class="nb-ks-itemsMobile">
        @foreach($items as $it)
          @php
            $barcode = $safe($it->barcode ?? null);

            $statusCode = strtolower((string)($it->status ?? ''));
            $status = $safe($it->status ?? null);
            $statusLabel = $statusLabels[$statusCode] ?? $status;
            $condition = $safe($it->condition ?? 'Baik');

            $branchName = trim((string)($it->branch_name ?? ''));
            if($branchName === '' && isset($it->branch) && isset($it->branch->name)) $branchName = trim((string)$it->branch->name);

            $shelfName = trim((string)($it->shelf_name ?? ''));
            if($shelfName === '' && isset($it->shelf) && isset($it->shelf->name)) $shelfName = trim((string)$it->shelf->name);
            if($shelfName === ''){
              $maybe = trim((string)($it->rack_name ?? ($it->rack->name ?? '')));
              if($maybe !== '' && $maybe !== $branchName) $shelfName = $maybe;
            }
            if($shelfName === $branchName) $shelfName = '';

            $chipClass = 'nb-ks-chip';
            if($statusCode === 'available') $chipClass .= ' ok';
            elseif(in_array($statusCode, ['lost','missing','damaged'], true)) $chipClass .= ' bad';

            $kondChip = 'nb-ks-chip';
            if(in_array(strtolower((string)$condition), ['baik','good','ok'], true)) $kondChip .= ' ok';
            if(in_array(strtolower((string)$condition), ['rusak','buruk','damaged','bad'], true)) $kondChip .= ' bad';

            $reserveInstitutionId = (int)($it->institution_id ?? 0);
          @endphp
          <div class="nb-ks-itemCard">
            <div class="nb-ks-itemRow">
              <div>
                <div style="font-weight:800; font-size:13px;">{{ $barcode }}</div>
                @if(!empty($it->id))
                  <div class="nb-ks-itemMeta">Item #{{ $it->id }}</div>
                @endif
              </div>
              <div class="nb-ks-itemBadges">
                <span class="{{ $chipClass }}">{{ $statusLabel }}</span>
                <span class="{{ $kondChip }}">{{ $condition }}</span>
              </div>
            </div>
            <div class="nb-ks-itemMeta">
              {{ $branchName !== '' ? $branchName : '-' }}
              @if($shelfName !== '') - {{ $shelfName }} @endif
            </div>
            @if($isMemberViewer)
              <form method="POST" action="{{ route('reservasi.store') }}">
                @csrf
                <input type="hidden" name="biblio_id" value="{{ (int)($biblio->id ?? 0) }}">
                @if($reserveInstitutionId > 0)
                  <input type="hidden" name="institution_id" value="{{ $reserveInstitutionId }}">
                @endif
                <button type="submit" class="nb-btn nb-btn-primary nb-ks-resBtn" style="border-radius:12px; padding:8px 12px; font-size:12px;">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="currentColor" d="M7 2h10a2 2 0 0 1 2 2v18l-7-3-7 3V4a2 2 0 0 1 2-2Z"/>
                  </svg>
                  Reservasi di sini
                </button>
              </form>
            @endif
          </div>
        @endforeach
      </div>

      <div class="nb-ks-tableWrap">
        <table class="nb-ks-table">
          <thead>
            <tr>
              <th style="width:260px;">Barcode</th>
              <th style="width:150px; text-align:center;">Status</th>
              <th style="width:150px; text-align:center;">Kondisi</th>
              <th>Lokasi</th>
              @if($canManage)
                <th style="width:110px; text-align:right;">Aksi</th>
              @endif
            </tr>
          </thead>
          <tbody>
            @foreach($items as $it)
              @php
                $barcode = $safe($it->barcode ?? null);

                $statusCode = strtolower((string)($it->status ?? ''));
                $status = $safe($it->status ?? null);
                $statusLabel = $statusLabels[$statusCode] ?? $status;
                $condition = $safe($it->condition ?? 'Baik'); // DEFAULT jika tidak ada

                // ✅ FIX lokasi dobel: ambil 2 level saja: Cabang + Rak (shelf)
                $branchName = trim((string)($it->branch_name ?? ''));
                if($branchName === '' && isset($it->branch) && isset($it->branch->name)) $branchName = trim((string)$it->branch->name);

                $shelfName = trim((string)($it->shelf_name ?? ''));
                if($shelfName === '' && isset($it->shelf) && isset($it->shelf->name)) $shelfName = trim((string)$it->shelf->name);

                // fallback lama (jika ada field lain), tapi jangan sampai dobel
                if($shelfName === ''){
                  $maybe = trim((string)($it->rack_name ?? ($it->rack->name ?? '')));
                  if($maybe !== '' && $maybe !== $branchName) $shelfName = $maybe;
                }
                if($shelfName === $branchName) $shelfName = ''; // prevent duplicate text

                $chipClass = 'nb-ks-chip';
                if($statusCode === 'available') $chipClass .= ' ok';
                elseif(in_array($statusCode, ['lost','missing','damaged'], true)) $chipClass .= ' bad';

                $kondChip = 'nb-ks-chip';
                if(in_array(strtolower((string)$condition), ['baik','good','ok'], true)) $kondChip .= ' ok';
                if(in_array(strtolower((string)$condition), ['rusak','buruk','damaged','bad'], true)) $kondChip .= ' bad';

                $reserveInstitutionId = (int)($it->institution_id ?? 0);
              @endphp

              <tr>
                <td data-label="Barcode">
                  <div class="nb-ks-mobileBadges">
                    <span class="{{ $chipClass }}">{{ $statusLabel }}</span>
                    <span class="{{ $kondChip }}">{{ $condition }}</span>
                  </div>
                  <div class="nb-ks-mono">{{ $barcode }}</div>
                  @if(!empty($it->id))
                    <div class="nb-ks-subline">Item #{{ $it->id }}</div>
                  @endif
                </td>

                <td class="nb-ks-td-center" data-label="Status">
                  <span class="{{ $chipClass }}">{{ $statusLabel }}</span>
                </td>

                <td class="nb-ks-td-center" data-label="Kondisi">
                  <span class="{{ $kondChip }}">{{ $condition }}</span>
                </td>

                <td data-label="Lokasi">
                  <div class="nb-ks-loc">
                    <div class="b">{{ $branchName !== '' ? $branchName : '-' }}</div>
                    <div class="s">{{ $shelfName !== '' ? $shelfName : '-' }}</div>

                    @if($isMemberViewer)
                      <form method="POST" action="{{ route('reservasi.store') }}" style="margin-top:8px;">
                        @csrf
                        <input type="hidden" name="biblio_id" value="{{ (int)($biblio->id ?? 0) }}">
                        @if($reserveInstitutionId > 0)
                          <input type="hidden" name="institution_id" value="{{ $reserveInstitutionId }}">
                        @endif
                        <button type="submit" class="nb-btn nb-btn-primary nb-ks-resBtn" style="border-radius:12px; padding:8px 12px; font-size:12px;">
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="currentColor" d="M7 2h10a2 2 0 0 1 2 2v18l-7-3-7 3V4a2 2 0 0 1 2-2Z"/>
                          </svg>
                          Reservasi di sini
                        </button>
                      </form>
                    @endif
                  </div>
                </td>

                @if($canManage)
                  <td style="text-align:right;" data-label="Aksi">
                    <div class="nb-ks-actionsRow">
                      <a class="nb-ks-miniBtn edit"
                         href="{{ route('eksemplar.edit', [$biblio->id, $it->id]) }}"
                         aria-label="Edit Eksemplar" title="Edit Eksemplar">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                          <path fill="currentColor" d="m14.06 9.02.92.92L6.92 18H6v-.92l8.06-8.06ZM17.66 3c-.25 0-.51.1-.7.29l-1.83 1.83 3.05 3.05 1.83-1.83c.39-.39.39-1.02 0-1.41l-1.83-1.83c-.2-.2-.45-.29-.7-.29ZM14.06 6.19 4 16.25V20h3.75L17.81 9.94l-3.75-3.75Z"/>
                        </svg>
                      </a>

                      <form method="POST"
                            action="{{ route('eksemplar.destroy', [$biblio->id, $it->id]) }}"
                            style="margin:0;"
                            onsubmit="return confirm('Hapus eksemplar ini?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="nb-ks-miniBtn del" aria-label="Hapus Eksemplar" title="Hapus Eksemplar">
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M9 3h6l1 2h5v2H3V5h5l1-2Zm1 7h2v9h-2v-9Zm4 0h2v9h-2v-9ZM7 10h2v9H7v-9Z"/>
                          </svg>
                        </button>
                      </form>
                    </div>
                  </td>
                @endif
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div style="height:12px;"></div>

      <div class="nb-ks-paging">
        {{ $items->links() }}
      </div>
    @endif
  </div>

  @if($relatedBiblios->count() > 0)
    <div style="height:12px;"></div>
    <div class="nb-card nb-ks-relatedSection nb-ks-section" style="padding:16px;">
      <div class="nb-ks-relatedHeader">
        <div class="nb-ks-relatedText">
          @if($isPublic && $firstSubjectId)
            <a href="{{ route($indexRouteName, ['subject' => $firstSubjectId, 'shelves' => 1]) }}" class="nb-ks-relatedTitleLink">
              <div class="nb-ks-secTitle">Koleksi Serupa</div>
            </a>
          @else
            <div class="nb-ks-secTitle">Koleksi Serupa</div>
          @endif
          <div class="nb-ks-secSub">Judul yang terkait berdasarkan pengarang atau subjek yang sama.</div>
        </div>
        <div class="nb-ks-relatedActions">
          @if($firstSubjectId)
            <a href="{{ route($indexRouteName, ['subject' => $firstSubjectId, 'shelves' => 1]) }}" class="nb-btn nb-ks-relatedCta">
              <span style="margin-right:6px;">🔎</span> Lihat semua subjek
            </a>
          @else
            <span class="nb-btn nb-ks-relatedCta is-disabled">
              <span style="margin-right:6px;">🔎</span> Lihat semua subjek
            </span>
          @endif

          @if($firstAuthorId)
            <a href="{{ route($indexRouteName, ['author' => $firstAuthorId, 'shelves' => 1]) }}" class="nb-btn nb-ks-relatedCta">
              <span style="margin-right:6px;">👤</span> Lihat semua pengarang
            </a>
          @else
            <span class="nb-btn nb-ks-relatedCta is-disabled">
              <span style="margin-right:6px;">👤</span> Lihat semua pengarang
            </span>
          @endif
        </div>
      </div>

      <div class="nb-ks-relatedDivider"></div>

      <div class="nb-ks-relatedGrid">
        @foreach($relatedBiblios as $rel)
          @php
            $relCover = !empty($rel->cover_path) ? asset('storage/'.ltrim($rel->cover_path,'/')) : null;
            $relAuthors = $rel->authors?->pluck('name')->take(2)->implode(', ') ?? '-';
            $relAvailable = (int)($rel->available_items_count ?? 0);
            $relTotal = (int)($rel->items_count ?? 0);
            $relAuthorIds = $rel->authors?->pluck('id')->filter()->values() ?? collect();
            $relSubjectIds = $rel->subjects?->pluck('id')->filter()->values() ?? collect();
            $matchBySubject = $currentSubjectIds->intersect($relSubjectIds)->isNotEmpty();
            $matchByAuthor = $currentAuthorIds->intersect($relAuthorIds)->isNotEmpty();
            $matchReason = $matchBySubject ? 'Subjek yang sama' : ($matchByAuthor ? 'Pengarang yang sama' : 'Topik terkait');
            $reasonClass = $matchBySubject ? 'is-subject' : ($matchByAuthor ? 'is-author' : 'is-generic');
            $reasonIcon = $matchBySubject ? '🎯' : ($matchByAuthor ? '👤' : '🧠');
            $firstRelAuthorId = $relAuthorIds->first();
            $authorFilterUrl = ($matchByAuthor && $firstRelAuthorId) ? route($indexRouteName, ['author' => $firstRelAuthorId, 'shelves' => 1]) : null;
          @endphp
          <a href="{{ route($showRouteName, $rel->id) }}" class="nb-ks-relatedCard">
            <div class="nb-ks-relatedCover">
              @if($relCover)
                <img src="{{ $relCover }}" alt="Cover {{ $rel->display_title ?? $rel->title }}">
              @else
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M6 4.2h10A2.2 2.2 0 0 1 18.2 6.4V20H7.4A1.4 1.4 0 0 0 6 21.4V4.2Z" stroke="currentColor" stroke-width="1.6"/>
                  <path d="M6 18.8h12.2" stroke="currentColor" stroke-width="1.6" opacity=".9"/>
                </svg>
              @endif
            </div>
            <div class="nb-ks-relatedInfo">
              <div class="nb-ks-relatedTitle">{{ $rel->display_title ?? $rel->title }}</div>
              <div class="nb-ks-relatedMeta">{{ $relAuthors }}</div>
              <div class="nb-ks-relatedBadges">
                <span class="nb-ks-relatedLabel">Alasan:</span>
                @if($authorFilterUrl)
                  <button type="button" class="nb-ks-relatedReason {{ $reasonClass }}"
                    onclick="event.stopPropagation(); window.location.href='{{ $authorFilterUrl }}';">
                    <span class="nb-ks-tip">
                      <span aria-hidden="true">{{ $reasonIcon }}</span>
                      {{ $matchReason }}
                      <span class="nb-ks-tip-bubble">Mirip karena: {{ $matchReason }}</span>
                    </span>
                  </button>
                @else
                  <span class="nb-ks-relatedReason {{ $reasonClass }}">
                    <span class="nb-ks-tip">
                      <span aria-hidden="true">{{ $reasonIcon }}</span>
                      {{ $matchReason }}
                      <span class="nb-ks-tip-bubble">Mirip karena: {{ $matchReason }}</span>
                    </span>
                  </span>
                @endif
                <span class="nb-ks-relatedAva">{{ $relAvailable }} / {{ $relTotal }} eksemplar</span>
              </div>
            </div>
          </a>
        @endforeach
      </div>
    </div>
  @endif

</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (!window.pdfjsLib) return;
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    document.querySelectorAll('details[data-pdf-url]').forEach(function (el) {
      var url = el.getAttribute('data-pdf-url');
      var canvasId = el.getAttribute('data-pdf-canvas');
      var canvas = canvasId ? document.getElementById(canvasId) : null;
      if (!url || !canvas) return;

      var state = {
        pdf: null,
        page: 1,
        total: 1,
        scale: 1.2,
        fitWidth: false
      };

      var pageLabel = el.querySelector('.nb-ks-pdf-page');
      var zoomLabel = el.querySelector('.nb-ks-pdf-zoom');
      var btnPrev = el.querySelector('.nb-ks-pdf-prev');
      var btnNext = el.querySelector('.nb-ks-pdf-next');
      var btnZoomIn = el.querySelector('.nb-ks-pdf-zoom-in');
      var btnZoomOut = el.querySelector('.nb-ks-pdf-zoom-out');
      var btnFit = el.querySelector('.nb-ks-pdf-fit');
      var btnFull = el.querySelector('.nb-ks-pdf-full');
      var wrap = el.querySelector('.nb-ks-pdf-wrap');
      var jumpInput = el.querySelector('.nb-ks-pdf-jump');

      var updateLabels = function () {
        if (pageLabel) {
          pageLabel.textContent = 'Hal ' + state.page + ' / ' + state.total;
        }
        if (zoomLabel) {
          zoomLabel.textContent = Math.round(state.scale * 100) + '%';
        }
        if (jumpInput) {
          jumpInput.value = state.page;
          jumpInput.max = state.total;
        }
        if (btnPrev) btnPrev.disabled = state.page <= 1;
        if (btnNext) btnNext.disabled = state.page >= state.total;
      };

      var renderPage = function () {
        if (!state.pdf) return;
        var ctx = canvas.getContext('2d');
        state.pdf.getPage(state.page).then(function (page) {
          if (state.fitWidth && wrap) {
            var base = page.getViewport({ scale: 1 });
            var targetWidth = wrap.clientWidth || base.width;
            state.scale = targetWidth / base.width;
          }
          var viewport = page.getViewport({ scale: state.scale });
          canvas.height = viewport.height;
          canvas.width = viewport.width;
          return page.render({ canvasContext: ctx, viewport: viewport }).promise;
        }).then(function () {
          updateLabels();
        }).catch(function () {
          canvas.parentElement.innerHTML = '<div class="nb-muted-2">Preview gagal dimuat. Silakan unduh file.</div>';
        });
      };

      var renderPdf = function () {
        if (el.dataset.rendered === '1') return;
        el.dataset.rendered = '1';
        pdfjsLib.getDocument(url).promise.then(function (pdf) {
          state.pdf = pdf;
          state.total = pdf.numPages || 1;
          state.page = 1;
          updateLabels();
          renderPage();
        }).catch(function () {
          el.dataset.rendered = '0';
          canvas.parentElement.innerHTML = '<div class="nb-muted-2">Preview gagal dimuat. Silakan unduh file.</div>';
        });
      };

      el.addEventListener('toggle', function () {
        if (el.open) renderPdf();
      });

      if (btnPrev) {
        btnPrev.addEventListener('click', function () {
          if (state.page > 1) {
            state.page -= 1;
            renderPage();
          }
        });
      }
      if (btnNext) {
        btnNext.addEventListener('click', function () {
          if (state.page < state.total) {
            state.page += 1;
            renderPage();
          }
        });
      }
      if (btnZoomIn) {
        btnZoomIn.addEventListener('click', function () {
          state.fitWidth = false;
          state.scale = Math.min(state.scale + 0.2, 3.0);
          renderPage();
        });
      }
      if (btnZoomOut) {
        btnZoomOut.addEventListener('click', function () {
          state.fitWidth = false;
          state.scale = Math.max(state.scale - 0.2, 0.6);
          renderPage();
        });
      }
      if (btnFit) {
        btnFit.addEventListener('click', function () {
          state.fitWidth = true;
          renderPage();
        });
      }
      if (btnFull && wrap) {
        btnFull.addEventListener('click', function () {
          if (wrap.requestFullscreen) {
            wrap.requestFullscreen();
          }
        });
      }
      if (jumpInput) {
        jumpInput.addEventListener('change', function () {
          var val = parseInt(jumpInput.value, 10);
          if (!val || isNaN(val)) return;
          if (val < 1) val = 1;
          if (val > state.total) val = state.total;
          state.page = val;
          renderPage();
        });
      }

      if (el.open) renderPdf();
    });
  });
</script>
@endsection




