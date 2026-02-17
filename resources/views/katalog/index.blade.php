{{-- resources/views/katalog/index.blade.php --}}
@extends('layouts.notobuku')

@section('title', $seoTitle ?? 'Katalog - NOTOBUKU')

@section('content')
@php
  $q = $q ?? '';
  $title = $title ?? '';
  $authorName = $author_name ?? '';
  $subjectTerm = $subject_term ?? '';
  $isbn = $isbn ?? '';
  $callNumber = $call_number ?? '';
  $language = $language ?? '';
  $materialType = $material_type ?? '';
  $mediaType = $media_type ?? '';
  $languageList = collect($languageList ?? [])->map(fn($v) => (string) $v)->filter()->values()->all();
  $materialTypeList = collect($materialTypeList ?? [])->map(fn($v) => (string) $v)->filter()->values()->all();
  $mediaTypeList = collect($mediaTypeList ?? [])->map(fn($v) => (string) $v)->filter()->values()->all();
  if (empty($languageList) && trim((string) $language) !== '') $languageList = [(string) $language];
  if (empty($materialTypeList) && trim((string) $materialType) !== '') $materialTypeList = [(string) $materialType];
  if (empty($mediaTypeList) && trim((string) $mediaType) !== '') $mediaTypeList = [(string) $mediaType];
  $ddc = $ddc ?? '';
  $year = $year ?? '';
  $yearFrom = (int) ($yearFrom ?? 0);
  $yearTo = (int) ($yearTo ?? 0);
  $onlyAvailable = $onlyAvailable ?? false;
  $author = $author ?? '';
  $subject = $subject ?? '';
  $publisher = $publisher ?? '';
  $authorList = collect($authorList ?? [])->map(fn($v) => (int) $v)->filter(fn($v) => $v > 0)->values()->all();
  $subjectList = collect($subjectList ?? [])->map(fn($v) => (int) $v)->filter(fn($v) => $v > 0)->values()->all();
  $publisherList = collect($publisherList ?? [])->map(fn($v) => (string) $v)->filter()->values()->all();
  $branchList = collect($branchList ?? [])->map(fn($v) => (int) $v)->filter(fn($v) => $v > 0)->values()->all();
  if (empty($authorList) && trim((string) $author) !== '') $authorList = [(int) $author];
  if (empty($subjectList) && trim((string) $subject) !== '') $subjectList = [(int) $subject];
  if (empty($publisherList) && trim((string) $publisher) !== '') $publisherList = [(string) $publisher];
  $qfFields = $qfFields ?? (array) request()->query('qf_field', []);
  $qfValues = $qfValues ?? (array) request()->query('qf_value', []);
  $qfOp = $qfOp ?? (string) request()->query('qf_op', 'AND');
  $qfExact = $qfExact ?? ((string) request()->query('qf_exact', '') === '1');
  $languageOptions = $languageOptions ?? collect();
  $materialTypeOptions = $materialTypeOptions ?? collect();
  $mediaTypeOptions = $mediaTypeOptions ?? collect();
  $branchOptions = $branchOptions ?? collect();
  $sort = $sort ?? 'relevant';
  $rankMode = $rankMode ?? (string) request()->query('rank', 'institution');
  $rankMode = ($rankMode === 'personal' && auth()->check() && !$isPublic) ? 'personal' : 'institution';
  $authorFacets = $authorFacets ?? collect();
  $subjectFacets = $subjectFacets ?? collect();
  $publisherFacets = $publisherFacets ?? collect();
  $languageFacets = $languageFacets ?? collect();
  $materialTypeFacets = $materialTypeFacets ?? collect();
  $mediaTypeFacets = $mediaTypeFacets ?? collect();
  $yearFacets = $yearFacets ?? collect();
  $branchFacets = $branchFacets ?? collect();
  $availabilityFacets = $availabilityFacets ?? ['available' => 0, 'unavailable' => 0];
  $didYouMean = $didYouMean ?? null;
  $showDiscovery = $showDiscovery ?? false;
  $trendingBooks = $trendingBooks ?? collect();
  $newArrivals = $newArrivals ?? collect();
  $indexRouteName = $indexRouteName ?? 'katalog.index';
  $showRouteName = $showRouteName ?? 'katalog.show';
  $isPublic = $isPublic ?? request()->routeIs('opac.*');
  $opacPrefetchUrls = $opacPrefetchUrls ?? [];
  $canManage = $canManage ?? false;
  $user = auth()->user();
  $role = (string) ($user?->role ?? 'member');
  $isAdminLike = in_array($role, ['super_admin', 'admin'], true);
  $isStaff = $role === 'staff';
  $katalogSkeleton = (bool) ($user?->katalog_skeleton_enabled ?? false);
  $preloadSet = (bool) ($user?->katalog_preload_set ?? false);
  $katalogPreloadMargin = (int) ($user?->katalog_preload_margin ?? 300);
  if (!$preloadSet) {
    $katalogPreloadMargin = $isAdminLike ? 800 : ($isStaff ? 500 : 300);
  }
  if (!in_array($katalogPreloadMargin, [300, 800], true)) {
    if (!in_array($katalogPreloadMargin, [300, 500, 800], true)) {
      $katalogPreloadMargin = 300;
    }
  }
  $hasQuery = trim((string) $q) !== '';
  $hasFilters = trim((string) $ddc) !== '' || trim((string) $year) !== '' || !empty($publisherList)
    || !empty($authorList) || !empty($subjectList) || !empty($languageList) || !empty($materialTypeList)
    || !empty($mediaTypeList) || !empty($branchList) || $yearFrom > 0 || $yearTo > 0 || $onlyAvailable;
  $qfPairs = collect($qfFields)
    ->map(function ($field, $i) use ($qfValues) {
      return ['field' => $field, 'value' => $qfValues[$i] ?? ''];
    })
    ->filter(fn($row) => trim((string) ($row['field'] ?? '')) !== '' && trim((string) ($row['value'] ?? '')) !== '')
    ->values();

  $hasAdvanced = trim((string) $title) !== '' || trim((string) $authorName) !== '' || trim((string) $subjectTerm) !== ''
    || trim((string) $isbn) !== '' || trim((string) $callNumber) !== '' || trim((string) $language) !== ''
    || trim((string) $materialType) !== '' || trim((string) $mediaType) !== ''
    || $qfPairs->isNotEmpty();
  $advancedCount = collect([$title, $authorName, $subjectTerm, $isbn, $callNumber, $language, $materialType, $mediaType])
    ->filter(fn($v) => trim((string) $v) !== '')
    ->count() + $qfPairs->count() + count($branchList);
  $forceShelves = $forceShelves ?? false;
  $showShelves = $forceShelves;

  // ? helper ringkas untuk filter aktif
  $activeFilters = [];
  if(trim((string)$q) !== '') $activeFilters[] = ['label'=>'Pencarian', 'value'=>$q, 'key' => 'q'];
  if(trim((string)$title) !== '') $activeFilters[] = ['label'=>'Judul', 'value'=>$title, 'key' => 'title'];
  if(trim((string)$authorName) !== '') $activeFilters[] = ['label'=>'Pengarang (teks)', 'value'=>$authorName, 'key' => 'author_name'];
  if(trim((string)$subjectTerm) !== '') $activeFilters[] = ['label'=>'Subjek (teks)', 'value'=>$subjectTerm, 'key' => 'subject_term'];
  if(trim((string)$isbn) !== '') $activeFilters[] = ['label'=>'ISBN', 'value'=>$isbn, 'key' => 'isbn'];
  if(trim((string)$callNumber) !== '') $activeFilters[] = ['label'=>'No. Panggil', 'value'=>$callNumber, 'key' => 'call_number'];
  foreach($languageList as $langFilter) $activeFilters[] = ['label'=>'Bahasa', 'value'=>$langFilter, 'key' => 'language', 'item' => $langFilter];
  foreach($materialTypeList as $matFilter) $activeFilters[] = ['label'=>'Jenis', 'value'=>$matFilter, 'key' => 'material_type', 'item' => $matFilter];
  foreach($mediaTypeList as $mediaFilter) $activeFilters[] = ['label'=>'Media', 'value'=>$mediaFilter, 'key' => 'media_type', 'item' => $mediaFilter];
  if(trim((string)$ddc) !== '') $activeFilters[] = ['label'=>'DDC', 'value'=>$ddc, 'key' => 'ddc'];
  if(trim((string)$year) !== '') $activeFilters[] = ['label'=>'Tahun', 'value'=>$year, 'key' => 'year'];
  if($yearFrom > 0 || $yearTo > 0) {
    $activeFilters[] = ['label'=>'Rentang Tahun', 'value'=>trim(($yearFrom > 0 ? $yearFrom : '...').' - '.($yearTo > 0 ? $yearTo : '...')), 'key' => 'year_from'];
  }
  foreach($publisherList as $pubFilter) $activeFilters[] = ['label'=>'Penerbit', 'value'=>$pubFilter, 'key' => 'publisher', 'item' => $pubFilter];
  foreach($branchList as $branchId) {
    $branchLabel = $branchOptions->firstWhere('id', $branchId)?->name
      ?? $branchFacets->firstWhere('id', $branchId)?->name
      ?? ('Cabang #' . $branchId);
    $activeFilters[] = ['label'=>'Cabang', 'value'=>$branchLabel, 'key' => 'branch', 'item' => (string) $branchId];
  }
  if($qfPairs->isNotEmpty()) {
    $builderText = $qfPairs->map(function ($row) {
      return strtoupper((string) $row['field']) . ': ' . trim((string) $row['value']);
    })->implode(' | ');
    $activeFilters[] = ['label'=>'Builder', 'value'=>$builderText, 'key' => 'qf_field'];
  }
  foreach($authorList as $authorId) {
    $authorLabel = $authorFacets->firstWhere('id', $authorId)?->name ?? (string) $authorId;
    $activeFilters[] = ['label'=>'Pengarang', 'value'=>$authorLabel, 'key' => 'author', 'item' => (string) $authorId];
  }
  foreach($subjectList as $subjectId) {
    $subjectLabel = $subjectFacets->firstWhere('id', $subjectId)?->term ?? $subjectFacets->firstWhere('id', $subjectId)?->name ?? (string) $subjectId;
    $activeFilters[] = ['label'=>'Subjek', 'value'=>$subjectLabel, 'key' => 'subject', 'item' => (string) $subjectId];
  }
  if($onlyAvailable) $activeFilters[] = ['label'=>'Ketersediaan', 'value'=>'Hanya yang tersedia', 'key' => 'available'];
  $sortMap = ['relevant' => 'Relevan', 'latest' => 'Terbaru', 'popular' => 'Populer', 'available' => 'Tersedia dulu'];
  $sortLabel = $sortMap[$sort] ?? 'Relevan';
  if($sort !== 'relevant') $activeFilters[] = ['label'=>'Urutkan', 'value'=>$sortLabel, 'key' => 'sort'];
  $showFilterBar = count($activeFilters) > 0 || $isPublic;
  $canPersonalRank = auth()->check() && !$isPublic;

  $totalResults = (int) ($biblios->total() ?? 0);
  $fromResult = (int) ($biblios->firstItem() ?? 0);
  $toResult = (int) ($biblios->lastItem() ?? 0);
  $formatQuick = [
    ['label' => 'Audio', 'value' => 'audio', 'icon' => 'AUD'],
    ['label' => 'Video', 'value' => 'video', 'icon' => 'VID'],
    ['label' => 'Serial', 'value' => 'serial', 'icon' => 'SER'],
  ];
  $pageCollection = $biblios->getCollection();
  $pageAvailable = (int) $pageCollection->sum('available_items_count');
  $pageItems = (int) $pageCollection->sum('items_count');
  $activeFilterCount = count($activeFilters);
  $topLanguageFacets = collect($languageFacets)->take(6)->values();
  $topMaterialFacets = collect($materialTypeFacets)->take(6)->values();
  $topMediaFacets = collect($mediaTypeFacets)->take(6)->values();
  $topYearFacets = collect($yearFacets)->take(6)->values();
  $topBranchFacets = collect($branchFacets)->take(6)->values();
  $yearCandidates = collect($yearFacets)
    ->map(fn($r) => (int) ($r->label ?? 0))
    ->filter(fn($v) => $v > 0)
    ->values();
  $yearNow = (int) now()->format('Y');
  $yearMinBound = $yearCandidates->isNotEmpty() ? max(1000, (int) $yearCandidates->min()) : max(1000, $yearNow - 30);
  $yearMaxBound = $yearCandidates->isNotEmpty() ? min(3000, (int) $yearCandidates->max()) : min(3000, $yearNow + 1);
  if ($yearMinBound > $yearMaxBound) {
    $yearMinBound = max(1000, $yearNow - 30);
    $yearMaxBound = min(3000, $yearNow + 1);
  }
  $yearSliderFrom = $yearFrom > 0 ? $yearFrom : ((int) $year > 0 ? (int) $year : $yearMinBound);
  $yearSliderTo = $yearTo > 0 ? $yearTo : ((int) $year > 0 ? (int) $year : $yearMaxBound);
  if ($yearSliderFrom < $yearMinBound) $yearSliderFrom = $yearMinBound;
  if ($yearSliderTo > $yearMaxBound) $yearSliderTo = $yearMaxBound;
  if ($yearSliderFrom > $yearSliderTo) {
    $tmpYear = $yearSliderFrom;
    $yearSliderFrom = $yearSliderTo;
    $yearSliderTo = $tmpYear;
  }

  $seoTitle = $isPublic
    ? (($q !== '' ? 'Hasil OPAC: ' . $q . ' - ' : '') . 'Katalog OPAC NOTOBUKU')
    : 'Katalog - NOTOBUKU';
  $seoDescription = $isPublic
    ? ($q !== ''
      ? "Temukan koleksi untuk kata kunci '{$q}'. Tersedia {$totalResults} hasil di OPAC NOTOBUKU."
      : "Jelajahi katalog OPAC NOTOBUKU. Total koleksi tersedia: {$totalResults} judul.")
    : 'Katalog internal NOTOBUKU.';
  $canonicalUrl = $isPublic ? request()->url() . (request()->getQueryString() ? ('?' . request()->getQueryString()) : '') : null;
  $pageNum = (int) request()->query('page', 1);
  $queryParamCount = collect(request()->query())
    ->reject(fn($v, $k) => $k === 'page' || $v === null || $v === '' || $v === [] || $v === false)
    ->count();
  $crawlNoIndex = $isPublic && (($pageNum > 20) || ($queryParamCount >= 4) || ($q !== '' && $queryParamCount >= 2));
  if ($crawlNoIndex && $isPublic) {
    $canonicalUrl = route('opac.index', array_filter(['q' => $q !== '' ? $q : null]));
  }

  $queryTokens = collect(preg_split('/\s+/', $q))
    ->filter(fn($t) => $t !== '' && mb_strlen($t) >= 3)
    ->unique()
    ->take(6)
    ->values();
  $highlight = function ($text) use ($queryTokens, $q) {
    $text = e($text ?? '');
    if ($q === '' || $queryTokens->isEmpty()) return $text;
    foreach ($queryTokens as $token) {
      $pattern = '/' . preg_quote($token, '/') . '/i';
      $text = preg_replace($pattern, '<mark class="nb-k-mark">$0</mark>', $text);
    }
    return $text;
  };

  // ? pagination helper (premium range)
  $p = $biblios;
  $cur = (int) $p->currentPage();
  $last = (int) $p->lastPage();
  $isPaginated = $last > 1;

  $window = 2; // kiri/kanan current
  $start = max(1, $cur - $window);
  $end = min($last, $cur + $window);

  // rapihin kalau dekat ujung
  if ($cur <= 3) { $start = 1; $end = min($last, 5); }
  if ($cur >= $last - 2) { $end = $last; $start = max(1, $last - 4); }

  $pageNums = range($start, $end);

  $pageUrl = function(int $page) use ($p) {
      // preserve query string dari paginator (karena controller pakai withQueryString)
      return $p->url($page);
  };

  $filterClearUrl = function(string $key, ?string $item = null) use ($indexRouteName) {
    $params = request()->query();
    if ($item !== null && isset($params[$key]) && is_array($params[$key])) {
      $params[$key] = array_values(array_filter((array) $params[$key], fn($v) => (string) $v !== (string) $item));
      if (count($params[$key]) === 0) {
        unset($params[$key]);
      }
    } else {
      unset($params[$key]);
    }
    if ($key === 'qf_field') {
      unset($params['qf_value'], $params['qf_op'], $params['qf_exact']);
    }
    if ($key === 'year_from') {
      unset($params['year_to']);
    }
    return route($indexRouteName, $params);
  };

  $facetToggleUrl = function (string $key, string $value) use ($indexRouteName) {
    $params = request()->query();
    $current = $params[$key] ?? [];
    if (!is_array($current)) {
      $current = $current === '' ? [] : [$current];
    }
    $exists = in_array((string) $value, array_map('strval', $current), true);
    if ($exists) {
      $current = array_values(array_filter($current, fn($v) => (string) $v !== (string) $value));
    } else {
      $current[] = (string) $value;
    }
    if (count($current) > 0) {
      $params[$key] = $current;
    } else {
      unset($params[$key]);
    }
    $params['page'] = 1;
    return route($indexRouteName, $params);
  };

  $excerpt = function ($text) {
    $text = trim((string) $text);
    if ($text === '') return '';
    return \Illuminate\Support\Str::limit(strip_tags($text), 140);
  };
  $suggestUrl = $isPublic ? route('opac.suggest') : route('katalog.suggest');
@endphp

@if($isPublic)
  @push('head')
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="robots" content="{{ $crawlNoIndex ? 'noindex,follow,max-image-preview:large' : 'index,follow,max-image-preview:large' }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="alternate" hreflang="id-ID" href="{{ $canonicalUrl }}">
    <link rel="alternate" hreflang="x-default" href="{{ $canonicalUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:site_name" content="NOTOBUKU OPAC">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <script type="application/ld+json">
      {!! json_encode([
        '@' . 'context' => 'https://schema.org',
        '@' . 'type' => 'SearchResultsPage',
        'name' => $seoTitle,
        'description' => $seoDescription,
        'url' => $canonicalUrl,
        'mainEntity' => [
          '@' . 'type' => 'ItemList',
          'numberOfItems' => $totalResults,
          'itemListElement' => $pageCollection->take(10)->values()->map(function ($b, $i) use ($showRouteName) {
            return [
              '@' . 'type' => 'ListItem',
              'position' => $i + 1,
              'url' => route($showRouteName, $b->id),
              'name' => (string) ($b->display_title ?? $b->title ?? '-'),
            ];
          })->all(),
        ],
        'potentialAction' => [
          '@' . 'type' => 'SearchAction',
          'target' => route('opac.index') . '?q={search_term_string}',
          'query-input' => 'required name=search_term_string',
        ],
      ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}
    </script>
  @endpush
@endif

@if($isPublic && !empty($opacPrefetchUrls))
  @push('scripts')
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var urls = @json(array_values($opacPrefetchUrls));
        if (!Array.isArray(urls) || urls.length === 0) return;

        var run = function () {
          urls.forEach(function (u) {
            if (!u) return;
            try {
              var link = document.createElement('link');
              link.rel = 'prefetch';
              link.href = u;
              link.as = 'document';
              document.head.appendChild(link);
            } catch (_) {}
            fetch(u, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }).catch(function(){});
          });
        };

        if ('requestIdleCallback' in window) {
          window.requestIdleCallback(run, { timeout: 1800 });
        } else {
          setTimeout(run, 900);
        }
      });
    </script>
  @endpush
@endif

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.getElementById('nbFilterToggle');
    var wrap = document.getElementById('nbFilterWrap');
    if (!toggle || !wrap) return;

    var isMobile = window.matchMedia && window.matchMedia('(max-width: 980px)').matches;
    if (!isMobile) {
      wrap.classList.remove('is-collapsed');
      toggle.setAttribute('aria-expanded', 'true');
      return;
    }

    toggle.addEventListener('click', function () {
      var collapsed = wrap.classList.toggle('is-collapsed');
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      toggle.querySelector('span:nth-child(2)').textContent = collapsed ? 'Ketuk untuk buka' : 'Ketuk untuk tutup';
    });
  });

  document.addEventListener('keydown', function (e) {
    var key = (e.key || '').toLowerCase();
    var target = e.target;
    var isTyping = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable);
    var shortcutModal = document.getElementById('nbShortcutModal');
    var modalOpen = shortcutModal && !shortcutModal.classList.contains('is-hidden');
    if ((e.ctrlKey || e.metaKey) && key === 'k') {
      e.preventDefault();
      var input = document.querySelector('input[name="q"]');
      if (input) input.focus();
    }
    if (!isTyping && !modalOpen && key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
      e.preventDefault();
      var quick = document.querySelector('input[name="q"]');
      if (quick) quick.focus();
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    var advToggle = document.getElementById('nbAdvancedToggle');
    var advWrap = document.getElementById('nbAdvancedWrap');
    var advClose = document.getElementById('nbAdvancedClose');
    var advBackdrop = advWrap ? advWrap.querySelector('[data-close-advanced]') : null;
    if (!advToggle || !advWrap) return;

    var setAdvancedOpen = function (open) {
      advWrap.classList.toggle('is-open', open);
      advWrap.setAttribute('aria-hidden', open ? 'false' : 'true');
      advToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.classList.toggle('nb-k-lock', open);
    };

    advToggle.addEventListener('click', function () {
      setAdvancedOpen(!advWrap.classList.contains('is-open'));
    });

    if (advClose) advClose.addEventListener('click', function () { setAdvancedOpen(false); });
    if (advBackdrop) advBackdrop.addEventListener('click', function () { setAdvancedOpen(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && advWrap.classList.contains('is-open')) {
        setAdvancedOpen(false);
      }
    });
  });

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form[action*="katalog"]') || document.querySelector('form');
    if (!form) return;

    var debounceTimer;
    var debounceMs = 450;

    var scheduleSubmit = function () {
      if (debounceTimer) window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(function () {
        form.requestSubmit();
      }, debounceMs);
    };

    form.querySelectorAll('[data-nb-autosubmit="1"]').forEach(function (input) {
      input.addEventListener('input', scheduleSubmit);
    });

    form.querySelectorAll('[data-nb-autosubmit-change="1"]').forEach(function (input) {
      input.addEventListener('change', function () {
        form.requestSubmit();
      });
    });
  });

  document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('nbSearchInput');
    var box = document.getElementById('nbSearchSuggest');
    var previewBox = document.getElementById('nbSearchPreview');
    var filterEl = document.getElementById('nbSuggestFilter');
    var cacheEl = document.getElementById('nbSearchCache');
    var wrap = input ? input.closest('.nb-k-suggestWrap') : null;
    if (!input || !box) return;

    var suggestUrl = @json($suggestUrl);
    var timer;
    var active = -1;

    var typeLabels = {
      title: 'judul',
      author: 'pengarang',
      subject: 'subjek',
      publisher: 'penerbit',
      isbn: 'isbn',
      ddc: 'ddc',
      call_number: 'no. panggil'
    };

    var emptyLabels = {
      title: 'Judul',
      author: 'Pengarang',
      subject: 'Subjek',
      publisher: 'Penerbit',
      isbn: 'ISBN',
      ddc: 'DDC',
      call_number: 'No. Panggil'
    };

    var renderItems = function (items) {
      box.innerHTML = '';
      active = -1;
      if (!items || items.length === 0) {
        var typeKey = filterEl ? (filterEl.value || '') : '';
        var label = emptyLabels[typeKey] || 'semua tipe';
        box.innerHTML = '<div class="nb-k-suggestEmpty">Tidak ada saran untuk ' + label + '.</div>';
        box.classList.add('is-open');
        return;
      }

      items.forEach(function (item, idx) {
        var row = document.createElement('div');
        row.className = 'nb-k-suggestItem';
        row.dataset.index = idx;
        row.dataset.url = item.url || '';
        var typeLabel = typeLabels[item.type] || 'item';
        row.innerHTML = '<span class="nb-k-suggestType">' + typeLabel + '</span>' +
                        '<span>' + (item.label || item.value || '') + '</span>';
        row.addEventListener('mousedown', function (e) {
          e.preventDefault();
          if (item.url) {
            window.location.href = item.url;
          } else {
            input.value = item.value || '';
            input.form && input.form.requestSubmit();
          }
        });
        box.appendChild(row);
      });
      box.classList.add('is-open');
    };

    var renderPreview = function (items) {
      if (!previewBox) return;
      previewBox.innerHTML = '';
      if (!items || items.length === 0) {
        previewBox.classList.remove('is-open');
        return;
      }
      var q = (input.value || '').trim();
      var highlight = function (text) {
        if (!q) return text || '';
        var re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig');
        return (text || '').replace(re, '<mark class="nb-k-mark">$1</mark>');
      };
      items.forEach(function (item) {
        var row = document.createElement('a');
        row.className = 'nb-k-previewItem';
        row.href = item.url || '#';
        row.innerHTML =
          '<div class="nb-k-previewCover">' +
            (item.cover ? '<img src="' + item.cover + '" alt="cover">' : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 4.2h10A2.2 2.2 0 0 1 18.2 6.4V20H7.4A1.4 1.4 0 0 0 6 21.4V4.2Z" stroke="currentColor" stroke-width="1.6"/><path d="M6 18.8h12.2" stroke="currentColor" stroke-width="1.6" opacity=".9"/></svg>') +
          '</div>' +
          '<div>' +
            '<div class="nb-k-previewTitle">' + highlight(item.title || '-') + '</div>' +
            '<div class="nb-k-previewMeta">' + highlight(item.authors || '-') + (item.year ? (' â€¢ ' + item.year) : '') + '</div>' +
          '</div>';
        previewBox.appendChild(row);
      });
      previewBox.classList.add('is-open');
    };

    var fetchSuggest = function () {
      var q = (input.value || '').trim();
      if (q.length < 2) {
        box.classList.remove('is-open');
        if (previewBox) previewBox.classList.remove('is-open');
        if (cacheEl) cacheEl.classList.remove('is-open');
        return;
      }
      var type = filterEl ? (filterEl.value || '') : '';
      var url = suggestUrl + '?q=' + encodeURIComponent(q);
      if (type) url += '&type=' + encodeURIComponent(type);
      fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (res) { return res.json(); })
        .then(function (data) {
          renderItems((data && data.items) || []);
          renderPreview((data && data.preview) || []);
          if (cacheEl) {
            if (data && data.cache_hit) {
              cacheEl.textContent = 'Cache hit';
              cacheEl.classList.add('is-open');
            } else {
              cacheEl.classList.remove('is-open');
            }
          }
        })
        .catch(function () { box.classList.remove('is-open'); });
    };

    input.addEventListener('input', function () {
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(fetchSuggest, 220);
    });

    if (filterEl) {
      filterEl.addEventListener('change', function () {
        fetchSuggest();
      });
    }

    input.addEventListener('focus', function () {
      if ((input.value || '').trim().length >= 2) fetchSuggest();
    });

    document.addEventListener('click', function (e) {
      if (!wrap || !wrap.contains(e.target)) {
        box.classList.remove('is-open');
        if (previewBox) previewBox.classList.remove('is-open');
        if (cacheEl) cacheEl.classList.remove('is-open');
      }
    });

    input.addEventListener('keydown', function (e) {
      if (!box.classList.contains('is-open')) return;
      var items = Array.from(box.querySelectorAll('.nb-k-suggestItem'));
      if (items.length === 0) return;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        active = Math.min(active + 1, items.length - 1);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        active = Math.max(active - 1, 0);
      } else if (e.key === 'Enter') {
        if (active >= 0 && items[active]) {
          e.preventDefault();
          items[active].dispatchEvent(new MouseEvent('mousedown'));
        } else {
          box.classList.remove('is-open');
          if (previewBox) previewBox.classList.remove('is-open');
          if (cacheEl) cacheEl.classList.remove('is-open');
        }
        return;
      } else if (e.key === 'Escape') {
        box.classList.remove('is-open');
        return;
      }
      items.forEach(function (el, i) {
        el.style.background = i === active ? 'rgba(30,136,229,.12)' : '';
      });
    });
  });

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('nbFilterForm') || document.querySelector('form[action*="katalog"]') || document.querySelector('form');
    var results = document.getElementById('nbSearchResults');
    var input = document.getElementById('nbSearchInput');
    if (!form || !results || !input) return;

    var timer;
    var debounceMs = 380;
    var loading = false;
    var nextUrl = results.dataset.nextUrl || '';
    var enableSkeleton = form.dataset.nbSkeleton === '1';
    var infiniteEnabled = form.dataset.nbInfinite === '1';
    var hidePagination = false;
    var preloadMargin = parseInt(form.dataset.nbPreload || '300', 10);
    var sentinel = document.getElementById('nbInfiniteSentinel');
    var skeletonToggle = document.getElementById('nbSkeletonToggle');
    var preloadSlider = document.getElementById('nbPreloadSlider');
    var preloadValue = document.getElementById('nbPreloadValue');
    var preloadLabel = document.getElementById('nbPreloadLabel');
    var facetWrap = document.getElementById('nbFacetRailWrap');
    var facetUrl = @json($isPublic ? route('opac.facets') : route('katalog.facets'));
    var prefUrl = @json(route('preferences.katalog_ui.set'));
    var csrf = @json(csrf_token());
    var facetRefreshTimer = null;
    var facetAbortController = null;
    var facetRequestSeq = 0;
    var lastFacetQuery = '';
    var facetRefreshPendingWhenHidden = false;
    var explicitApplySubmit = false;
    var applyBtn = form.querySelector('.nb-k-ibtn.apply');
    if (applyBtn) {
      applyBtn.addEventListener('click', function () {
        explicitApplySubmit = true;
      });
    }

    if (skeletonToggle) {
      var isOn = skeletonToggle.checked;
      enableSkeleton = isOn;
      form.dataset.nbSkeleton = isOn ? '1' : '0';
      skeletonToggle.addEventListener('change', function () {
        enableSkeleton = !!skeletonToggle.checked;
        form.dataset.nbSkeleton = enableSkeleton ? '1' : '0';
        savePrefs();
      });
    }

    if (preloadSlider) {
      var applyPreload = function () {
        var val = parseInt(preloadSlider.value || '300', 10);
        if (![300, 500, 800].includes(val)) {
          val = val < 500 ? 300 : (val < 800 ? 500 : 800);
        }
        preloadMargin = val;
        form.dataset.nbPreload = String(preloadMargin);
        if (preloadValue) preloadValue.textContent = String(preloadMargin);
        if (preloadLabel) {
          preloadLabel.textContent = preloadMargin === 300 ? 'Normal' : (preloadMargin === 500 ? 'Seimbang' : 'Agresif');
        }
      };
      applyPreload();
      preloadSlider.addEventListener('input', applyPreload);
      preloadSlider.addEventListener('change', function () {
        applyPreload();
        savePrefs();
      });
    }

    function savePrefs() {
      if (!prefUrl) return;
      var payload = {
        skeleton_enabled: enableSkeleton ? 1 : 0,
        preload_margin: preloadMargin
      };
      fetch(prefUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      }).catch(function () {});
    }

    var actionPath = (function () {
      try {
        return new URL(form.action || '', window.location.href).pathname || window.location.pathname;
      } catch (e) {
        return window.location.pathname;
      }
    })();

    var normalizeUrl = function (input) {
      if (!input) return '';
      try {
        var u = new URL(input, window.location.href);
        return u.pathname + u.search;
      } catch (e) {
        return input;
      }
    };

    var refreshFacets = function (force) {
      if (!facetWrap || !facetUrl) return;
      if (document.hidden) {
        facetRefreshPendingWhenHidden = true;
        return;
      }
      var params = new URLSearchParams(new FormData(form));
      params.set('ajax', '1');
      params.set('facets_only', '1');
      var query = params.toString();
      if (!force && query === lastFacetQuery) return;

      if (facetAbortController && typeof facetAbortController.abort === 'function') {
        facetAbortController.abort();
      }
      facetAbortController = (typeof AbortController !== 'undefined') ? new AbortController() : null;
      facetRequestSeq += 1;
      var currentSeq = facetRequestSeq;
      facetWrap.classList.add('is-loading');

      fetch(facetUrl + '?' + query, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal: facetAbortController ? facetAbortController.signal : undefined
      })
        .then(function (res) {
          if (!res.ok) throw new Error('http');
          return res.json();
        })
        .then(function (data) {
          if (currentSeq !== facetRequestSeq) return;
          if (data && data.facet_html) {
            facetWrap.innerHTML = data.facet_html;
            lastFacetQuery = query;
            facetRefreshPendingWhenHidden = false;
          }
        })
        .catch(function (err) {
          if (err && err.name === 'AbortError') return;
        })
        .finally(function () {
          facetWrap.classList.remove('is-loading');
        });
    };

    var scheduleFacetRefresh = function () {
      if (!facetWrap || !facetUrl) return;
      if (facetRefreshTimer) window.clearTimeout(facetRefreshTimer);
      facetRefreshTimer = window.setTimeout(function () {
        refreshFacets();
      }, 160);
    };

    document.addEventListener('visibilitychange', function () {
      if (!document.hidden && facetRefreshPendingWhenHidden) {
        scheduleFacetRefresh();
      }
    });

    var renderSkeleton = function () {
      var cards = [];
      for (var i = 0; i < 6; i++) {
        cards.push(
          '<div class="nb-k-skelCard">' +
          '<div class="nb-k-skelLine lg"></div>' +
          '<div style="height:8px"></div>' +
          '<div class="nb-k-skelLine md"></div>' +
          '<div style="height:8px"></div>' +
          '<div class="nb-k-skelLine sm"></div>' +
          '</div>'
        );
      }
      results.innerHTML = '<div class="nb-k-skeleton">' + cards.join('') + '</div>';
    };

    var preloadIfShort = function () {
      if (!nextUrl || loading) return;
      var docHeight = document.body.offsetHeight;
      if (docHeight < window.innerHeight + 200) {
        loadMore();
      }
    };

    var showAjaxError = function () {
      var existing = document.getElementById('nbAjaxError');
      if (!existing) {
        existing = document.createElement('div');
        existing.id = 'nbAjaxError';
        existing.className = 'nb-k-error';
        existing.textContent = 'Gagal memuat hasil. Coba lagi.';
        results.prepend(existing);
      } else {
        existing.style.display = '';
      }
    };
    var hideAjaxError = function () {
      var existing = document.getElementById('nbAjaxError');
      if (existing) existing.style.display = 'none';
    };

    var buildParams = function (keepFilters) {
      var params = new URLSearchParams(new FormData(form));
      if (!keepFilters) {
          [
            'title', 'author_name', 'subject_term', 'isbn', 'call_number',
            'ddc', 'year', 'year_from', 'year_to', 'available',
            'language', 'language[]', 'material_type', 'material_type[]', 'media_type', 'media_type[]',
            'publisher', 'publisher[]', 'author', 'author[]', 'subject', 'subject[]', 'branch', 'branch[]',
            'qf_op', 'qf_exact', 'qf_field', 'qf_field[]', 'qf_value', 'qf_value[]'
          ].forEach(function (key) { params.delete(key); });
      }
      return params;
    };

    var resetAdvancedControls = function () {
      // Sinkronkan UI form dengan mode pencarian cepat (tanpa filter lanjutan)
      form.querySelectorAll('input[name="title"], input[name="author_name"], input[name="subject_term"], input[name="isbn"], input[name="call_number"], input[name="ddc"], input[name="year"], input[name="year_from"], input[name="year_to"]')
        .forEach(function (el) { el.value = ''; });

      form.querySelectorAll('select[name="language[]"], select[name="material_type[]"], select[name="media_type[]"], select[name="publisher[]"], select[name="author[]"], select[name="subject[]"], select[name="branch[]"]')
        .forEach(function (sel) {
          Array.from(sel.options || []).forEach(function (opt) { opt.selected = false; });
        });

      form.querySelectorAll('select[name="qf_field[]"]').forEach(function (sel) { sel.value = ''; });
      form.querySelectorAll('input[name="qf_value[]"]').forEach(function (el) { el.value = ''; });

      var qfOp = form.querySelector('select[name="qf_op"]');
      if (qfOp) qfOp.value = 'AND';
      var qfExact = form.querySelector('input[name="qf_exact"]');
      if (qfExact) qfExact.checked = false;

      var available = form.querySelector('input[name="available"]');
      if (available) available.checked = false;
    };

      var fetchResults = function (urlOverride, opts) {
        opts = opts || {};
        var keepFilters = !!opts.keepFilters;
        if (!keepFilters && !urlOverride) {
          resetAdvancedControls();
        }
        var params = buildParams(keepFilters);
        params.set('ajax', '1');
        var url = urlOverride || (actionPath + '?' + params.toString());
        url = normalizeUrl(url);

      loading = true;
      if (enableSkeleton) {
        renderSkeleton();
      }
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (res) {
          if (!res.ok) throw new Error('http');
          var ct = (res.headers && res.headers.get && res.headers.get('content-type')) || '';
          if (ct.indexOf('application/json') === -1) {
            var cleanParams = buildParams(keepFilters);
            window.location.href = actionPath + '?' + cleanParams.toString();
            return null;
          }
          return res.json();
        })
        .then(function (data) {
          if (!data) return;
          if (data && data.html) {
            results.innerHTML = data.html;
            nextUrl = normalizeUrl(data.next_page_url || '');
            hideAjaxError();

            // Post-render hooks tidak boleh menjatuhkan request utama.
            try {
              var cleanParams = buildParams(keepFilters);
              var newUrl = actionPath + '?' + cleanParams.toString();
              history.replaceState({}, '', newUrl);
            } catch (err) {}

            try {
              if (window.nbKatalogSetupBatch) window.nbKatalogSetupBatch();
            } catch (err) {}

            try {
              scheduleFacetRefresh();
            } catch (err) {}
          }
        })
        .catch(function () {
          showAjaxError();
        })
        .finally(function () {
          loading = false;
          var pag = document.getElementById('nbPagination');
          if (pag && infiniteEnabled && hidePagination) pag.style.display = 'none';
          preloadIfShort();
        });
      };

      window.nbKatalogFetchResults = fetchResults;

    input.addEventListener('input', function () {
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        fetchResults(null, { keepFilters: false });
      }, debounceMs);
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var keepFilters = explicitApplySubmit;
      explicitApplySubmit = false;
      fetchResults(null, { keepFilters: keepFilters });
    });

    form.querySelectorAll('[data-nb-autosubmit="1"]').forEach(function (el) {
      el.addEventListener('input', function () {
        if (timer) window.clearTimeout(timer);
        timer = window.setTimeout(function () { fetchResults(null, { keepFilters: true }); }, debounceMs);
      });
    });
    form.querySelectorAll('[data-nb-autosubmit-change="1"]').forEach(function (el) {
      el.addEventListener('change', function () {
        fetchResults(null, { keepFilters: true });
      });
    });

    results.addEventListener('click', function (e) {
      var link = e.target.closest && e.target.closest('#nbPagination a');
      if (!link) return;
      e.preventDefault();
      fetchResults(link.href);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    var loadMore = function () {
      if (loading || !nextUrl) return;
      loading = true;
      var baseNext = normalizeUrl(nextUrl);
      var url = baseNext + (baseNext.includes('?') ? '&' : '?') + 'ajax=1&grid_only=1';
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (res) {
          if (!res.ok) throw new Error('http');
          var ct = (res.headers && res.headers.get && res.headers.get('content-type')) || '';
          if (ct.indexOf('application/json') === -1) {
            window.location.href = url.replace(/([?&])ajax=1(&|$)/, '$1').replace(/([?&])grid_only=1(&|$)/, '$1');
            return null;
          }
          return res.json();
        })
        .then(function (data) {
          if (!data) return;
            if (data && data.html) {
              var grid = document.getElementById('nbKGrid');
              if (grid) {
                var temp = document.createElement('div');
                temp.innerHTML = data.html;
              var newGrid = temp.querySelector('#nbKGrid');
              if (newGrid) {
                while (newGrid.firstChild) {
                  grid.appendChild(newGrid.firstChild);
                }
              }
            }
            nextUrl = normalizeUrl(data.next_page_url || '');
            scheduleFacetRefresh();
          }
          loading = false;
          preloadIfShort();
        })
        .catch(function () { loading = false; });
    };

    if (infiniteEnabled) {
      var pag = document.getElementById('nbPagination');
      if (pag && hidePagination) pag.style.display = 'none';
      if (sentinel && 'IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
          if (!entries.length) return;
          if (entries[0].isIntersecting) {
            loadMore();
          }
        }, { rootMargin: preloadMargin + 'px 0px' });
        observer.observe(sentinel);
      } else {
        window.addEventListener('scroll', function () {
          var scrollBottom = window.innerHeight + window.scrollY;
          var docHeight = document.body.offsetHeight;
          if (docHeight - scrollBottom < preloadMargin) {
            loadMore();
          }
        });
      }
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    var buttons = Array.prototype.slice.call(document.querySelectorAll('.nb-k-carouselBtn'));
    if (!buttons.length) return;
    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var targetId = btn.getAttribute('data-carousel');
        var dir = btn.getAttribute('data-dir');
        var row = document.getElementById(targetId);
        if (!row) return;
        var card = row.querySelector('.nb-k-mini');
        var step = card ? (card.getBoundingClientRect().width + 12) : 300;
        row.scrollBy({ left: dir === 'prev' ? -step : step, behavior: 'smooth' });
      });
    });
  });

  document.addEventListener('DOMContentLoaded', function () {
    var setupAuto = function (rowId) {
      var row = document.getElementById(rowId);
      if (!row) return;
      var intervalMs = 3500;
      var timer = null;

      var stepSize = function () {
        var card = row.querySelector('.nb-k-mini');
        return card ? (card.getBoundingClientRect().width + 12) : 300;
      };

      var tick = function () {
        var step = stepSize();
        if (row.scrollLeft + row.clientWidth >= row.scrollWidth - 5) {
          row.scrollTo({ left: 0, behavior: 'smooth' });
        } else {
          row.scrollBy({ left: step, behavior: 'smooth' });
        }
      };

      var start = function () {
        if (timer) return;
        timer = window.setInterval(tick, intervalMs);
      };
      var stop = function () {
        if (timer) {
          window.clearInterval(timer);
          timer = null;
        }
      };

      row.addEventListener('mouseenter', stop);
      row.addEventListener('mouseleave', start);
      row.addEventListener('focusin', stop);
      row.addEventListener('focusout', start);
      start();
    };

    setupAuto('nbPopularRow');
    setupAuto('nbNewRow');
  });

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('nbFilterForm') || document.querySelector('form[action*="katalog"]');
    var wrappers = Array.prototype.slice.call(document.querySelectorAll('[data-nb-year-range]'));
    if (!form || wrappers.length === 0) return;

    wrappers.forEach(function (wrap) {
      var min = parseInt(wrap.getAttribute('data-year-min') || '0', 10);
      var max = parseInt(wrap.getAttribute('data-year-max') || '0', 10);
      var fromSlider = wrap.querySelector('[data-year-from-slider]');
      var toSlider = wrap.querySelector('[data-year-to-slider]');
      var fromLabel = wrap.querySelector('[data-year-from-label]');
      var toLabel = wrap.querySelector('[data-year-to-label]');
      var fromInput = form.querySelector('input[name="year_from"]');
      var toInput = form.querySelector('input[name="year_to"]');
      if (!fromSlider || !toSlider || !fromInput || !toInput) return;

      var sync = function () {
        var fromVal = parseInt(fromSlider.value || String(min), 10);
        var toVal = parseInt(toSlider.value || String(max), 10);
        if (fromVal > toVal) {
          var t = fromVal; fromVal = toVal; toVal = t;
          fromSlider.value = String(fromVal);
          toSlider.value = String(toVal);
        }
        fromInput.value = String(fromVal);
        toInput.value = String(toVal);
        if (fromLabel) fromLabel.textContent = String(fromVal);
        if (toLabel) toLabel.textContent = String(toVal);
      };

      var timer;
      var submitSoon = function () {
        if (timer) window.clearTimeout(timer);
        timer = window.setTimeout(function () {
          if (window.nbKatalogFetchResults) {
            window.nbKatalogFetchResults();
          } else {
            form.requestSubmit();
          }
        }, 220);
      };

      fromSlider.addEventListener('input', function () { sync(); });
      toSlider.addEventListener('input', function () { sync(); });
      fromSlider.addEventListener('change', function () { sync(); submitSoon(); });
      toSlider.addEventListener('change', function () { sync(); submitSoon(); });
      sync();
    });
  });
</script>

<style>
  /* =========================================================
     KATALOG - GRID + FILTER SEJAJAR
     + Pagination premium mobile
     ========================================================= */

  .nb-k-wrap{ max-width:1180px; margin:0 auto; }

  /* ---------- Header ---------- */
  .nb-k-head{ padding:10px 12px; }
  .nb-k-head{ overflow: visible; }
  .nb-k-head{ border-radius:18px; }
  .nb-k-headTop{
    display:flex; align-items:flex-start; justify-content:space-between;
    gap:12px; flex-wrap:wrap;
  }
  .nb-k-title{
    font-size:16px;
    font-weight:650;
    letter-spacing:.1px;
    color: rgba(11,37,69,.94);
    line-height:1.2;
  }
  html.dark .nb-k-title{ color: rgba(226,232,240,.92); }

  .nb-k-sub{
    margin-top:3px;
    font-size:12px;
    font-weight:450;
    color: rgba(11,37,69,.70);
    line-height:1.35;
  }
  html.dark .nb-k-sub{ color: rgba(226,232,240,.70); }

  .nb-k-divider{
    height:1px; border:0; margin:8px 0;
    background: linear-gradient(90deg, rgba(15,23,42,.10), rgba(15,23,42,.05), rgba(15,23,42,.10));
  }
  html.dark .nb-k-divider{
    background: linear-gradient(90deg, rgba(148,163,184,.20), rgba(148,163,184,.10), rgba(148,163,184,.20));
  }

  /* =========================================================
     FILTER GRID
     ========================================================= */
  .nb-k-filter{
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap:6px;
    align-items:end;
  }

  .nb-k-filter .actions{ grid-column: 1 / -1; justify-content:flex-end; }
  .nb-k-yearRange{
    display:flex;
    flex-direction:column;
    gap:8px;
    padding:8px 10px;
    border:1px solid rgba(148,163,184,.28);
    border-radius:12px;
    background:rgba(255,255,255,.72);
  }
  .nb-k-yearRange input[type="range"]{
    width:100%;
    accent-color:#1e88e5;
  }
  .nb-k-yearRangeMeta{
    display:flex;
    justify-content:space-between;
    font-size:12px;
    font-weight:700;
    color:rgba(11,37,69,.8);
  }
  html.dark .nb-k-yearRange{
    background:rgba(15,23,42,.4);
    border-color:rgba(148,163,184,.22);
  }
  html.dark .nb-k-yearRangeMeta{ color:rgba(226,232,240,.85); }
  .nb-k-suggestRow{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:nowrap;
  }
  .nb-k-suggestRow .nb-field{
    flex:1 1 520px;
    min-width:320px;
  }
  .nb-k-suggestRow .nb-k-suggestFilter{
    flex:0 0 140px;
    appearance:none;
    -webkit-appearance:none;
    -moz-appearance:none;
    background:
      linear-gradient(180deg, rgba(255,255,255,.95), rgba(248,250,252,.95));
    border:1px solid rgba(15,23,42,.16);
    padding-right:30px;
    position:relative;
    background-image:
      linear-gradient(180deg, rgba(255,255,255,.95), rgba(248,250,252,.95)),
      url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none'><path d='M7 9.5l5 5 5-5' stroke='%235a6b82' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'/></svg>");
    background-repeat:no-repeat;
    background-position:right 8px center, right 8px center;
    background-size: auto, 12px 12px;
  }
  .nb-k-suggestRow .nb-k-suggestFilter::-ms-expand{ display:none; }
  .nb-k-suggestRow .nb-k-suggestFilter:focus{
    outline:2px solid rgba(30,136,229,.2);
    outline-offset:1px;
  }
  .nb-k-inlineActions{
    display:flex;
    align-items:center;
    gap:6px;
    flex:0 0 auto;
  }
  @media (max-width: 980px){
    .nb-k-suggestRow{
      flex-wrap:wrap;
    }
    .nb-k-suggestRow .nb-k-suggestFilter{
      flex:1 1 120px;
    }
  }

  @media (max-width: 980px){
    .nb-k-filter{ align-items:start; }
    .nb-k-filter .actions{ justify-content:flex-start; }
  }

  /* =========================================================
     MOBILE FILTER DRAWER
     ========================================================= */
  .nb-k-filterToggle{
    display:none;
    width:100%;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding:8px 10px;
    border-radius:12px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.88);
    font-size:12.5px;
    font-weight:600;
    color: rgba(11,37,69,.9);
    transition: border-color .12s ease, box-shadow .12s ease, background .12s ease;
  }
  .nb-k-filterToggle:hover,
  .nb-k-advancedToggle:hover,
  .nb-k-shortcutToggle:hover{
    border-color: rgba(30,136,229,.24);
    box-shadow: 0 10px 22px rgba(2,6,23,.06);
  }
  .nb-k-advancedToggle{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.88);
    font-size:11.5px;
    font-weight:600;
    color: rgba(11,37,69,.85);
    cursor:pointer;
    transition: border-color .12s ease, box-shadow .12s ease, background .12s ease;
  }
  .nb-k-shortcutToggle{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.88);
    font-size:11.5px;
    font-weight:600;
    color: rgba(11,37,69,.85);
    cursor:pointer;
    transition: border-color .12s ease, box-shadow .12s ease, background .12s ease;
  }
  .nb-k-advancedToggle .count{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:18px;
    height:18px;
    padding:0 6px;
    border-radius:999px;
    background: rgba(30,136,229,.15);
    color: rgba(30,136,229,.9);
    font-size:11px;
    font-weight:600;
  }
  html.dark .nb-k-advancedToggle{
    border-color: rgba(148,163,184,.22);
    background: rgba(15,23,42,.45);
    color: rgba(226,232,240,.9);
  }
  html.dark .nb-k-shortcutToggle{
    border-color: rgba(148,163,184,.22);
    background: rgba(15,23,42,.45);
    color: rgba(226,232,240,.9);
  }
  .nb-k-advancedWrap{
    position:fixed;
    inset:0;
    z-index:1200;
    display:none;
  }
  .nb-k-advancedWrap.is-open{ display:block; }
  .nb-k-advancedBackdrop{
    position:absolute;
    inset:0;
    background:rgba(15,23,42,.36);
  }
  .nb-k-advancedPanel{
    position:absolute;
    top:0;
    right:0;
    width:min(780px, 94vw);
    height:100%;
    overflow:auto;
    padding:14px 14px 18px;
    border-left:1px solid rgba(148,163,184,.28);
    background: rgba(248,250,252,.98);
    box-shadow: -18px 0 40px rgba(2,6,23,.2);
  }
  .nb-k-advancedHead{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:10px;
  }
  .nb-k-advancedTitle{
    font-size:14px;
    font-weight:800;
    color: rgba(15,23,42,.92);
  }
  .nb-k-advancedSub{
    margin-top:2px;
    font-size:12px;
    color: rgba(51,65,85,.75);
  }
  .nb-k-advancedClose{
    width:34px;
    height:34px;
    border-radius:10px;
    border:1px solid rgba(148,163,184,.35);
    background:#fff;
    color: rgba(15,23,42,.86);
    font-weight:700;
    font-size:18px;
    line-height:1;
    cursor:pointer;
  }
  .nb-k-advPrefs{
    margin-top:10px;
    padding-top:10px;
    border-top:1px dashed rgba(148,163,184,.22);
  }
  .nb-k-advPrefsRow{
    display:flex;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
  }
  html.dark .nb-k-advancedPanel{
    border-color: rgba(148,163,184,.22);
    background: rgba(15,23,42,.96);
    box-shadow: -18px 0 40px rgba(0,0,0,.45);
  }
  html.dark .nb-k-advancedTitle{ color: rgba(248,250,252,.94); }
  html.dark .nb-k-advancedSub{ color: rgba(226,232,240,.65); }
  html.dark .nb-k-advancedClose{
    border-color: rgba(148,163,184,.28);
    background: rgba(15,23,42,.6);
    color: rgba(226,232,240,.92);
  }
  body.nb-k-lock{ overflow:hidden; }
  .nb-k-advBuilder{
    margin-top:12px;
    padding-top:12px;
    border-top:1px dashed rgba(148,163,184,.22);
  }
  .nb-k-advHead{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
    flex-wrap:wrap;
  }
  .nb-k-advTitle{
    font-size:13px;
    font-weight:600;
    color: rgba(11,37,69,.82);
  }
  .nb-k-advSub{
    margin-top:4px;
    font-size:11.5px;
    font-weight:500;
    color: rgba(11,37,69,.55);
  }
  html.dark .nb-k-advTitle{ color: rgba(226,232,240,.85); }
  html.dark .nb-k-advSub{ color: rgba(226,232,240,.58); }
  .nb-k-advControls{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
  }
  .nb-k-advSelect{
    height:34px;
    border-radius:999px !important;
    padding:0 12px;
    font-size:12.5px;
  }
  .nb-k-advExact{
    display:flex;
    align-items:center;
    gap:8px;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.9);
    font-size:12px;
    font-weight:600;
    color: rgba(11,37,69,.8);
  }
  .nb-k-advExact input{ width:14px; height:14px; }
  html.dark .nb-k-advExact{
    border-color: rgba(148,163,184,.22);
    background: rgba(15,23,42,.45);
    color: rgba(226,232,240,.85);
  }
  .nb-k-advGrid{
    margin-top:10px;
    display:grid;
    gap:8px;
  }
  .nb-k-advRow{
    display:grid;
    grid-template-columns: minmax(140px, 200px) 1fr;
    gap:8px;
  }
  @media (max-width: 980px){
    .nb-k-advRow{ grid-template-columns: 1fr; }
  }
  html.dark .nb-k-filterToggle{
    border-color: rgba(148,163,184,.22);
    background: rgba(15,23,42,.4);
    color: rgba(226,232,240,.9);
  }
  .nb-k-filterWrap.is-collapsed{ display:none; }
  .nb-k-filterWrap{ margin-top:8px; }
  @media (max-width: 980px){
    .nb-k-filterToggle{ display:flex; }
  }

  /* =========================================================
     AVAILABILITY BADGE (explicit)
     ========================================================= */
  .nb-k-status{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:600;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.9);
    color: rgba(11,37,69,.9);
  }
  .nb-k-status.ok{
    border-color: rgba(16,185,129,.35);
    background: rgba(16,185,129,.12);
    color: rgba(6,95,70,.95);
  }
  .nb-k-status.no{
    border-color: rgba(239,68,68,.35);
    background: rgba(239,68,68,.10);
    color: rgba(153,27,27,.95);
  }
  .nb-k-format{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 8px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.9);
    font-size:11.5px;
    font-weight:600;
    color: rgba(11,37,69,.85);
  }
  .nb-k-formatIcon{
    font-size:10px;
    font-weight:800;
    letter-spacing:.08em;
    padding:2px 6px;
    border-radius:999px;
    background: rgba(30,136,229,.16);
    color: rgba(30,136,229,.95);
  }
  html.dark .nb-k-format{
    border-color: rgba(148,163,184,.18);
    background: rgba(15,23,42,.45);
    color: rgba(226,232,240,.9);
  }
  html.dark .nb-k-formatIcon{
    background: rgba(30,136,229,.28);
    color: rgba(226,232,240,.95);
  }

  .nb-k-fieldBlock{ display:flex; flex-direction:column; min-width:0; }
  .nb-k-fieldBlock .nb-field{ height:34px; font-size:12.5px; }
  .nb-k-fieldBlock.span2{ grid-column: span 2; }
  @media (max-width: 980px){
    .nb-k-fieldBlock.span2{ grid-column: 1 / -1; }
  }

  .nb-k-suggestWrap{ position:relative; }
  .nb-k-suggestFilter{
    margin-top:6px;
    height:32px;
    border-radius:10px !important;
    font-size:12px;
    padding:0 10px;
    background: rgba(255,255,255,.95);
  }
  html.dark .nb-k-suggestFilter{
    background: rgba(15,23,42,.6);
  }
  .nb-k-skeleton{
    display:grid;
    grid-template-columns: repeat(2, minmax(0,1fr));
    gap:10px;
  }
  @media (min-width: 980px){
    .nb-k-skeleton{ grid-template-columns: repeat(3, minmax(0,1fr)); }
  }
  @media (max-width: 740px){
    .nb-k-skeleton{ grid-template-columns: 1fr; }
  }
  .nb-k-skelCard{
    border-radius:16px;
    border:1px solid rgba(15,23,42,.08);
    background: rgba(255,255,255,.8);
    padding:12px;
    overflow:hidden;
  }
  html.dark .nb-k-skelCard{
    background: rgba(15,23,42,.5);
    border-color: rgba(148,163,184,.15);
  }
  .nb-k-skelLine{
    height:10px;
    border-radius:999px;
    background: linear-gradient(90deg, rgba(226,232,240,.4), rgba(226,232,240,.9), rgba(226,232,240,.4));
    background-size: 200% 100%;
    animation: nbShimmer 1.2s infinite;
  }
  .nb-k-skelLine.sm{ width:60%; }
  .nb-k-skelLine.md{ width:80%; }
  .nb-k-skelLine.lg{ width:100%; }
  @keyframes nbShimmer{
    0%{ background-position:200% 0; }
    100%{ background-position:-200% 0; }
  }
  .nb-k-error{
    background: rgba(239,68,68,.08);
    border: 1px solid rgba(239,68,68,.25);
    color: #991b1b;
    padding: 10px 12px;
    border-radius: 12px;
    font-size: 12.5px;
    margin-bottom: 10px;
  }
  .nb-k-cache{
    margin-top:6px;
    font-size:11px;
    color: rgba(11,37,69,.55);
    display:none;
  }
  .nb-k-cache.is-open{ display:block; }
  html.dark .nb-k-cache{ color: rgba(226,232,240,.6); }
  .nb-k-suggest{
    position:absolute;
    top:calc(100% + 6px);
    left:0; right:0;
    background:#fff;
    border:1px solid rgba(15,23,42,.12);
    border-radius:12px;
    box-shadow:0 16px 32px rgba(2,6,23,.12);
    z-index:50;
    display:none;
    max-height:280px;
    overflow:auto;
  }
  html.dark .nb-k-suggest{
    background:rgba(15,23,42,.95);
    border-color: rgba(148,163,184,.24);
  }
  .nb-k-suggest.is-open{ display:block; }
  .nb-k-suggestItem{
    display:flex; gap:8px; align-items:center;
    padding:8px 10px;
    border-bottom:1px solid rgba(15,23,42,.08);
    font-size:12.5px;
    color: rgba(11,37,69,.92);
    cursor:pointer;
  }
  .nb-k-suggestItem:last-child{ border-bottom:0; }
  .nb-k-suggestItem:hover{ background: rgba(30,136,229,.08); }
  html.dark .nb-k-suggestItem{
    color: rgba(226,232,240,.9);
    border-bottom-color: rgba(148,163,184,.15);
  }
  .nb-k-suggestType{
    display:inline-flex; align-items:center; justify-content:center;
    min-width:42px; height:20px;
    padding:0 6px;
    border-radius:999px;
    font-size:10px; font-weight:700;
    background: rgba(30,136,229,.12);
    color: rgba(30,136,229,.95);
  }
  .nb-k-suggestEmpty{
    padding:8px 10px; font-size:12px; color: rgba(11,37,69,.6);
  }
  html.dark .nb-k-suggestEmpty{ color: rgba(226,232,240,.6); }

  .nb-k-preview{
    margin-top:8px;
    border:1px solid rgba(15,23,42,.12);
    border-radius:12px;
    background: rgba(255,255,255,.9);
    display:none;
    overflow:hidden;
  }
  html.dark .nb-k-preview{
    background: rgba(15,23,42,.45);
    border-color: rgba(148,163,184,.24);
  }
  .nb-k-preview.is-open{ display:block; }
  .nb-k-previewItem{
    display:flex; gap:10px; padding:8px 10px; align-items:center;
    border-bottom:1px solid rgba(15,23,42,.08);
  }
  .nb-k-previewItem:last-child{ border-bottom:0; }
  .nb-k-previewCover{
    width:42px; height:56px; border-radius:8px; overflow:hidden;
    background: rgba(15,23,42,.08);
    display:flex; align-items:center; justify-content:center;
    flex:0 0 auto;
  }
  .nb-k-previewCover img{ width:100%; height:100%; object-fit:cover; }
  .nb-k-previewTitle{ font-size:12.5px; font-weight:700; color: rgba(11,37,69,.92); }
  .nb-k-previewMeta{ font-size:11.5px; color: rgba(11,37,69,.6); margin-top:2px; }
  html.dark .nb-k-previewTitle{ color: rgba(226,232,240,.92); }
  html.dark .nb-k-previewMeta{ color: rgba(226,232,240,.6); }


  .nb-k-label{
    display:block;
    margin-bottom:6px;
    font-size:12px;
    font-weight:600;
    letter-spacing:.12px;
    color: rgba(11,37,69,.76);
  }
  html.dark .nb-k-label{ color: rgba(226,232,240,.76); }

  .nb-k-label-ghost{ opacity:0; pointer-events:none; user-select:none; }

  .nb-k-helpRow{
    margin-top:8px;
    display:flex;
    justify-content:flex-end;
    align-items:center;
  }
  .nb-k-tipMini{ position:relative; }
  .nb-k-tipMini > summary{
    list-style:none;
    display:inline-flex;
    align-items:center;
    gap:6px;
    font-size:12px;
    font-weight:700;
    color: rgba(30,64,175,.92);
    background: rgba(219,234,254,.65);
    border:1px solid rgba(147,197,253,.7);
    border-radius:999px;
    padding:5px 10px;
    cursor:pointer;
    user-select:none;
  }
  .nb-k-tipMini > summary::-webkit-details-marker{ display:none; }
  .nb-k-tipMini > summary::before{
    content:'?';
    width:16px;
    height:16px;
    border-radius:999px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:11px;
    font-weight:800;
    border:1px solid rgba(59,130,246,.35);
    background:#fff;
    color: rgba(30,64,175,.95);
  }
  .nb-k-tipPanel{
    position:absolute;
    right:0;
    top:calc(100% + 8px);
    min-width:320px;
    max-width:min(520px, 78vw);
    z-index:50;
    border-radius:12px;
    border:1px solid rgba(148,163,184,.3);
    background:#fff;
    box-shadow: 0 16px 34px rgba(2,6,23,.16);
    padding:10px 12px;
    font-size:12.5px;
    line-height:1.45;
    color: rgba(51,65,85,.9);
  }
  html.dark .nb-k-tipMini > summary{
    color: rgba(191,219,254,.95);
    background: rgba(30,58,138,.34);
    border-color: rgba(96,165,250,.45);
  }
  html.dark .nb-k-tipMini > summary::before{
    background: rgba(15,23,42,.72);
    color: rgba(191,219,254,.95);
    border-color: rgba(96,165,250,.35);
  }
  html.dark .nb-k-tipPanel{
    border-color: rgba(148,163,184,.3);
    background: rgba(15,23,42,.96);
    color: rgba(226,232,240,.88);
    box-shadow: 0 16px 34px rgba(0,0,0,.45);
  }
  .nb-k-muted-sm{
    font-size:11px;
    font-weight:600;
    color: rgba(11,37,69,.55);
  }
  .nb-k-muted-md{
    font-size:12px;
    font-weight:650;
    color: rgba(11,37,69,.65);
  }
  .nb-k-pageInfo{
    font-size:12.5px;
    font-weight:600;
    color: rgba(11,37,69,.60);
    text-align:center;
  }
  .nb-k-pageTotal{
    font-size:12px;
    color: rgba(11,37,69,.50);
    text-align:center;
  }
  html.dark .nb-k-muted-sm{ color: rgba(226,232,240,.65); }
  html.dark .nb-k-muted-md{ color: rgba(226,232,240,.70); }
  html.dark .nb-k-pageInfo{ color: rgba(226,232,240,.68); }
  html.dark .nb-k-pageTotal{ color: rgba(226,232,240,.62); }

  .nb-k-filter .nb-field{
    border-radius:12px !important;
    padding-left:10px;
    padding-right:10px;
    width:100%;
    min-width:0;
    font-size:12.5px;
  }

  .nb-k-ava{
    height:34px;
    display:flex;
    align-items:center;
    gap:10px;
    padding:0 10px;
    border-radius:12px;
    border:1px solid var(--nb-border);
    background: var(--nb-surface);
    user-select:none;
    min-width: 210px;
    max-width: 100%;
    overflow:hidden;
  }
  .nb-k-ava input{
    width:16px; height:16px;
    flex: 0 0 auto;
    accent-color: rgba(30,136,229,.95);
  }
  .nb-k-ava span{
    display:block;
    font-weight:600;
    font-size:12px;
    color: rgba(11,37,69,.88);
    line-height:1.05;
    white-space:normal;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  html.dark .nb-k-ava span{ color: rgba(226,232,240,.88); }

  /* =========================================================
     TOP ACTIONS (apply/reset)
     ========================================================= */
  .nb-k-actionsTop{
    display:flex; gap:6px;
    align-items:center;
    justify-content:flex-end;
  }
  @media (max-width: 980px){ .nb-k-actionsTop{ justify-content:flex-start; } }

  .nb-k-ibtn{
    width:34px; height:34px;
    border-radius:12px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.78);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    user-select:none;
    transition: background .12s ease, border-color .12s ease, transform .06s ease, box-shadow .12s ease, color .12s ease;
    text-decoration:none;
  }
  .nb-k-ibtn:active{ transform: translateY(1px); }
  .nb-k-ibtn svg{ width:18px; height:18px; }
  .nb-k-ibtn:focus-visible,
  .nb-k-miniBtn:focus-visible,
  .nb-k-quickChip:focus-visible,
  .nb-pg-btn:focus-visible{
    outline:2px solid rgba(30,136,229,.35);
    outline-offset:2px;
  }

  .nb-k-ibtn.apply{
    border-color: rgba(30,136,229,.22);
    background: linear-gradient(180deg, rgba(30,136,229,1), rgba(21,101,192,1));
    color:#fff;
    box-shadow: 0 14px 26px rgba(30,136,229,.22);
  }
  .nb-k-ibtn.apply:hover{ box-shadow: 0 16px 30px rgba(30,136,229,.26); }
  .nb-k-ibtn.apply svg{ color:#fff; }

  .nb-k-ibtn.reset{
    color: rgba(11,37,69,.92);
    border-color: rgba(15,23,42,.12);
    background: rgba(255,255,255,.78);
  }
  html.dark .nb-k-ibtn.reset{ color: rgba(226,232,240,.92); border-color: rgba(148,163,184,.16); background: rgba(15,23,42,.40); }
  .nb-k-ibtn.reset:hover{
    border-color: rgba(30,136,229,.18);
    box-shadow: 0 14px 26px rgba(2,6,23,.06);
  }
  html.dark .nb-k-ibtn.reset:hover{
    border-color: rgba(147,197,253,.18);
    box-shadow: 0 14px 26px rgba(0,0,0,.22);
  }
  .nb-k-ibtn-inline{ width:auto; padding:0 14px; gap:8px; }
  .nb-k-ibtn-pill{ width:auto; padding:0 12px; gap:8px; height:36px; border-radius:999px; }
  /* Tombol tambah: hover kebaca + premium */
  .nb-k-addBtn{
    transition: background .12s ease, border-color .12s ease, transform .06s ease, box-shadow .12s ease, color .12s ease;
  }
  .nb-k-addBtn:hover{
    box-shadow: 0 16px 30px rgba(30,136,229,.22);
    filter: saturate(1.05);
  }
  .nb-k-addBtn:active{ transform: translateY(1px); }

  /* ---------- Filter aktif ---------- */
  .nb-k-activeBar{
    margin-top:10px;
    padding:10px 12px;
    border-radius:16px;
    border:1px solid rgba(30,136,229,.14);
    background: rgba(30,136,229,.06);
    display:flex;
    gap:10px;
    align-items:flex-start;
    justify-content:space-between;
    flex-wrap:wrap;
  }
  html.dark .nb-k-activeBar{
    border-color: rgba(30,136,229,.18);
    background: rgba(30,136,229,.12);
  }
  .nb-k-activeBar .left{
    display:flex; gap:8px; flex-wrap:wrap; align-items:center;
    font-size:12.6px;
    color: rgba(11,37,69,.74);
    line-height:1.35;
  }
  .nb-k-activeLabel{
    font-size:11px;
    font-weight:700;
    letter-spacing:.12em;
    text-transform:uppercase;
    color: rgba(11,37,69,.60);
  }
  html.dark .nb-k-activeLabel{ color: rgba(226,232,240,.65); }

  /* =========================================================
     RESULT SUMMARY + QUICK FACETS
     ========================================================= */
  .nb-k-summary{
    display:flex;
    gap:12px;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
    padding:10px 12px;
    border-radius:14px;
    border:1px solid rgba(30,136,229,.18);
    background:linear-gradient(135deg, rgba(30,136,229,.08), rgba(39,174,96,.06));
  }
  html.dark .nb-k-summary{
    border-color: rgba(30,136,229,.22);
    background:linear-gradient(135deg, rgba(30,136,229,.18), rgba(15,23,42,.35));
  }
  .nb-k-summary .left{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
    font-size:12.5px;
    color: rgba(11,37,69,.78);
    font-weight:550;
  }
  html.dark .nb-k-summary .left{ color: rgba(226,232,240,.78); }
  .nb-k-summary .right{
    display:flex;
    gap:8px;
    align-items:center;
    flex-wrap:wrap;
  }
  .sr-only{
    position:absolute !important;
    width:1px !important;
    height:1px !important;
    padding:0 !important;
    margin:-1px !important;
    overflow:hidden !important;
    clip:rect(0, 0, 0, 0) !important;
    white-space:nowrap !important;
    border:0 !important;
  }
  .nb-k-dym{
    margin-top:10px;
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
    padding:10px 12px;
    border-radius:12px;
    border:1px solid rgba(14,116,144,.2);
    background:rgba(103,232,249,.14);
    color:rgba(12,74,110,.95);
    font-size:12.5px;
    font-weight:600;
  }
  .nb-k-dym-label{
    text-transform:uppercase;
    letter-spacing:.09em;
    font-size:11px;
    font-weight:800;
    color:rgba(8,47,73,.82);
  }
  .nb-k-dym-link{
    display:inline-flex;
    align-items:center;
    border-radius:999px;
    padding:5px 10px;
    border:1px solid rgba(3,105,161,.3);
    background:#fff;
    color:rgba(3,105,161,.95);
    font-weight:700;
  }
  .nb-k-facetRail{
    margin-top:10px;
    display:flex;
    flex-wrap:wrap;
    gap:10px;
  }
  .nb-k-facetGroup{
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:6px;
  }
  .nb-k-facetTitle{
    font-size:11.5px;
    font-weight:800;
    color:rgba(15,23,42,.72);
    text-transform:uppercase;
    letter-spacing:.08em;
  }
  .nb-k-facetChip{
    display:inline-flex;
    align-items:center;
    padding:4px 10px;
    border-radius:999px;
    border:1px solid rgba(148,163,184,.36);
    background:rgba(255,255,255,.84);
    color:rgba(15,23,42,.8);
    font-size:12px;
    font-weight:600;
  }
  .nb-k-facetChip.is-active{
    border-color: rgba(30,136,229,.5);
    background: rgba(30,136,229,.14);
    color: rgba(11,89,176,.95);
  }
  html.dark .nb-k-facetChip.is-active{
    border-color: rgba(96,165,250,.5);
    background: rgba(30,64,175,.26);
    color: rgba(191,219,254,.96);
  }
  .nb-k-facetCompact{
    margin-top:10px;
    padding:10px 12px;
    border:1px solid rgba(191,219,254,.8);
    border-radius:14px;
    background:rgba(241,245,249,.62);
    display:grid;
    gap:10px;
  }
  .nb-k-facetMain{ display:grid; gap:8px; }
  .nb-k-facetRow{
    display:flex;
    align-items:center;
    gap:6px;
    flex-wrap:wrap;
  }
  .nb-k-facetLabel{
    min-width:96px;
    font-size:11.5px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.07em;
    color:rgba(30,41,59,.78);
  }
  .nb-k-advFacet{
    border:1px dashed rgba(148,163,184,.42);
    border-radius:12px;
    padding:8px 10px;
    background:rgba(255,255,255,.6);
  }
  .nb-k-advFacet > summary{
    cursor:pointer;
    list-style:none;
    font-weight:700;
    font-size:12.5px;
    color:rgba(30,64,175,.95);
  }
  .nb-k-advFacet > summary::-webkit-details-marker{ display:none; }
  .nb-k-facetAdvGrid{
    display:grid;
    gap:8px;
    margin-top:8px;
  }
  .nb-k-kpiMini{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }
  .nb-k-kpiPill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    border-radius:999px;
    border:1px solid rgba(148,163,184,.35);
    background:rgba(255,255,255,.82);
    padding:6px 10px;
    font-size:12px;
    color:rgba(30,41,59,.82);
    font-weight:600;
  }
  .nb-k-kpiPill b{ font-weight:800; color:rgba(15,23,42,.95); }
  #nbFacetRailWrap{
    transition: opacity .16s ease;
  }
  #nbFacetRailWrap.is-loading{
    opacity:.62;
    pointer-events:none;
  }
  html.dark .nb-k-facetCompact{
    border-color: rgba(148,163,184,.22);
    background: rgba(15,23,42,.35);
  }
  html.dark .nb-k-facetLabel{ color: rgba(226,232,240,.76); }
  html.dark .nb-k-advFacet{
    border-color: rgba(148,163,184,.28);
    background: rgba(2,6,23,.32);
  }
  html.dark .nb-k-advFacet > summary{ color: rgba(147,197,253,.95); }
  html.dark .nb-k-kpiPill{
    border-color: rgba(148,163,184,.3);
    background: rgba(2,6,23,.42);
    color: rgba(226,232,240,.86);
  }
  html.dark .nb-k-kpiPill b{ color: rgba(248,250,252,.95); }

  /* ---------- Batch edit (staff) ---------- */
  .nb-k-batchBar{
    margin-top:10px;
    padding:10px 12px;
    border-radius:16px;
    border:1px dashed rgba(30,136,229,.22);
    background: rgba(30,136,229,.06);
    display:flex;
    gap:10px;
    align-items:flex-start;
    flex-wrap:wrap;
  }
  html.dark .nb-k-batchBar{
    border-color: rgba(148,163,184,.24);
    background: rgba(30,136,229,.12);
  }
  .nb-k-batchBar.is-hidden{ display:none !important; }
  .nb-k-batchBar[hidden]{ display:none !important; }
  .nb-k-batchLeft{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; flex:1 1 auto; }
  .nb-k-batchRight{ margin-left:0; display:grid; gap:10px; flex:1 1 100%; width:100%; }
  .nb-k-batchCount{ font-size:12.5px; font-weight:700; color: rgba(11,37,69,.7); }
  html.dark .nb-k-batchCount{ color: rgba(226,232,240,.8); }
  .nb-k-batchHint{
    font-size:11.5px;
    font-weight:600;
    color: rgba(11,37,69,.55);
  }
  html.dark .nb-k-batchHint{ color: rgba(226,232,240,.6); }
  .nb-k-batchSelect{
    display:inline-flex; align-items:center; gap:6px;
    padding:4px 10px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.85);
    font-size:12px; font-weight:600;
  }
  .nb-k-batchSelect input{ width:14px; height:14px; }
  html.dark .nb-k-batchSelect{
    border-color: rgba(148,163,184,.2);
    background: rgba(15,23,42,.5);
    color: rgba(226,232,240,.9);
  }
  .nb-k-batchField{ min-width:160px; flex:1 1 160px; }
  .nb-k-batchField:disabled{ opacity:.7; cursor:not-allowed; }
  .nb-k-batchApply:disabled,
  .nb-k-batchUndo:disabled,
  #nbBatchClear:disabled{ opacity:.6; cursor:not-allowed; }
  .nb-k-batchFields{ display:grid; gap:10px; width:100%; }
  .nb-k-batchRow{
    display:grid;
    grid-template-columns: repeat(4, minmax(160px, 1fr));
    gap:8px;
    align-items:center;
    background: rgba(15,23,42,.02);
    border:1px solid rgba(148,163,184,.18);
    border-radius:12px;
    padding:8px;
  }
  .nb-k-batchActions{
    display:flex;
    gap:8px;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
    width:100%;
    padding-top:10px;
    border-top:1px dashed rgba(148,163,184,.22);
  }
  .nb-k-batchActions .nb-btn{
    min-height:40px;
    padding:9px 14px;
    border-radius:12px;
    font-weight:700;
    white-space:nowrap;
  }
  .nb-k-batchApply{ min-width:130px; }
  .nb-k-batchUndo{
    border-color: rgba(245,158,11,.35);
    background: rgba(245,158,11,.08);
    color: rgba(146,64,14,.95);
  }
  #nbBatchClear{
    border-color: rgba(148,163,184,.35);
    background: rgba(255,255,255,.75);
    color: rgba(30,41,59,.88);
  }
  html.dark .nb-k-batchUndo{
    border-color: rgba(251,191,36,.35);
    background: rgba(217,119,6,.18);
    color: rgba(254,240,138,.95);
  }
  html.dark #nbBatchClear{
    border-color: rgba(148,163,184,.25);
    background: rgba(15,23,42,.45);
    color: rgba(226,232,240,.9);
  }

  /* ---------- Shortcut list ---------- */
  .nb-k-shortcuts{
    position:fixed;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:1200;
  }
  .nb-k-shortcuts.is-hidden{ display:none; }
  .nb-k-shortcuts-backdrop{
    position:absolute;
    inset:0;
    background: rgba(15,23,42,.55);
  }
  .nb-k-shortcuts-card{
    position:relative;
    width:min(560px, 92vw);
    padding:16px;
    border-radius:18px;
    border:1px solid rgba(148,163,184,.22);
    background:#fff;
    box-shadow: 0 20px 40px rgba(2,6,23,.25);
    z-index:1;
  }
  html.dark .nb-k-shortcuts-card{
    background: rgba(15,23,42,.95);
    border-color: rgba(148,163,184,.24);
    box-shadow: 0 24px 46px rgba(0,0,0,.45);
  }
  .nb-k-shortcuts-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:10px;
  }
  .nb-k-shortcuts-title{
    font-size:13.5px;
    font-weight:800;
    color: rgba(11,37,69,.9);
  }
  html.dark .nb-k-shortcuts-title{ color: rgba(226,232,240,.92); }
  .nb-k-shortcuts-close{
    width:28px;
    height:28px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.9);
    font-size:14px;
    font-weight:700;
    color: rgba(11,37,69,.8);
    cursor:pointer;
  }
  html.dark .nb-k-shortcuts-close{
    border-color: rgba(148,163,184,.24);
    background: rgba(15,23,42,.6);
    color: rgba(226,232,240,.9);
  }
  .nb-k-shortcuts-grid{
    display:grid;
    gap:8px;
  }
  .nb-k-shortcuts-row{
    display:flex;
    justify-content:space-between;
    gap:12px;
    padding:8px 0;
    border-bottom:1px dashed rgba(148,163,184,.25);
    font-size:12.5px;
    color: rgba(11,37,69,.8);
  }
  .nb-k-shortcuts-row:last-child{ border-bottom:0; }
  html.dark .nb-k-shortcuts-row{ color: rgba(226,232,240,.8); }
  .nb-k-kbd{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:2px 6px;
    border-radius:6px;
    border:1px solid rgba(148,163,184,.4);
    background: rgba(248,250,252,.95);
    font-size:11px;
    font-weight:700;
    letter-spacing:.02em;
  }
  html.dark .nb-k-kbd{
    background: rgba(15,23,42,.6);
    border-color: rgba(148,163,184,.3);
    color: rgba(226,232,240,.9);
  }
  .nb-k-shortcuts-note{
    margin-top:10px;
    font-size:11.5px;
    color: rgba(11,37,69,.6);
  }
  html.dark .nb-k-shortcuts-note{ color: rgba(226,232,240,.6); }

  .nb-k-select{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 8px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.9);
    font-size:11.5px;
    font-weight:650;
    color: rgba(11,37,69,.85);
  }
  .nb-k-select input{ width:14px; height:14px; accent-color: rgba(30,136,229,.95); }
  html.dark .nb-k-select{
    border-color: rgba(148,163,184,.18);
    background: rgba(15,23,42,.55);
    color: rgba(226,232,240,.9);
  }
  .nb-k-item.is-selected{
    border-color: rgba(30,136,229,.35);
    box-shadow: 0 12px 24px rgba(30,136,229,.12);
  }
  html.dark .nb-k-item.is-selected{
    border-color: rgba(147,197,253,.35);
    box-shadow: 0 12px 24px rgba(15,23,42,.45);
  }
  .nb-k-summary b{ font-weight:600; }
  .nb-k-summary .left > span + span::before{
    content:"\2022";
    margin:0 6px;
    color: rgba(11,37,69,.45);
    font-weight:700;
  }
  .nb-k-summary .left > .nb-k-opac::before{
    content:"";
    margin:0;
  }
  html.dark .nb-k-summary .left > span + span::before{ color: rgba(226,232,240,.45); }
  .nb-k-opac{
    display:inline-flex;
    align-items:center;
    gap:6px;
    font-size:11.5px;
    font-weight:700;
    letter-spacing:.08em;
    text-transform:uppercase;
    padding:3px 8px;
    border-radius:999px;
    border:1px solid rgba(30,136,229,.18);
    background: rgba(30,136,229,.10);
    color: rgba(30,136,229,.95);
  }
  html.dark .nb-k-opac{
    border-color: rgba(147,197,253,.18);
    background: rgba(30,136,229,.18);
    color: rgba(226,232,240,.92);
  }

  .nb-k-insights{
    margin-top:10px;
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap:10px;
  }
  .nb-k-insight{
    padding:10px 12px;
    border-radius:14px;
    border:1px solid rgba(148,163,184,.20);
    background: #ffffff;
    box-shadow: 0 10px 24px rgba(2,6,23,.04);
  }
  html.dark .nb-k-insight{
    border-color: rgba(148,163,184,.18);
    background: rgba(15,23,42,.45);
    box-shadow: 0 10px 24px rgba(0,0,0,.25);
  }
  .nb-k-insight.tone-blue{
    background: linear-gradient(135deg, rgba(59,130,246,1), rgba(37,99,235,1));
    border-color: rgba(59,130,246,.35);
    color:#fff;
  }
  .nb-k-insight.tone-green{
    background: linear-gradient(135deg, rgba(34,197,94,1), rgba(22,163,74,1));
    border-color: rgba(34,197,94,.35);
    color:#fff;
  }
  .nb-k-insight.tone-indigo{
    background: linear-gradient(135deg, rgba(99,102,241,1), rgba(79,70,229,1));
    border-color: rgba(99,102,241,.35);
    color:#fff;
  }
  .nb-k-insight.tone-blue .label,
  .nb-k-insight.tone-blue .value,
  .nb-k-insight.tone-blue .meta,
  .nb-k-insight.tone-green .label,
  .nb-k-insight.tone-green .value,
  .nb-k-insight.tone-green .meta,
  .nb-k-insight.tone-indigo .label,
  .nb-k-insight.tone-indigo .value,
  .nb-k-insight.tone-indigo .meta{
    color:#fff;
  }
  .nb-k-insight.tone-blue .label,
  .nb-k-insight.tone-green .label,
  .nb-k-insight.tone-indigo .label{ opacity:.85; }
  .nb-k-insight.tone-blue .meta,
  .nb-k-insight.tone-green .meta,
  .nb-k-insight.tone-indigo .meta{ opacity:.9; }
  html.dark .nb-k-insight.tone-blue,
  html.dark .nb-k-insight.tone-green,
  html.dark .nb-k-insight.tone-indigo{
    box-shadow: 0 14px 28px rgba(0,0,0,.30);
  }
  .nb-k-insight .label{
    font-size:11px;
    font-weight:700;
    letter-spacing:.12em;
    text-transform:uppercase;
    color: rgba(11,37,69,.55);
  }
  html.dark .nb-k-insight .label{ color: rgba(226,232,240,.55); }
  .nb-k-insight .value{
    margin-top:6px;
    font-size:18px;
    font-weight:700;
    color: rgba(11,37,69,.92);
  }
  html.dark .nb-k-insight .value{ color: rgba(226,232,240,.92); }
  .nb-k-insight .meta{
    margin-top:4px;
    font-size:12px;
    font-weight:500;
    color: rgba(11,37,69,.62);
  }
  html.dark .nb-k-insight .meta{ color: rgba(226,232,240,.64); }

  .nb-k-quick{
    display:flex;
    gap:8px;
    align-items:center;
    flex-wrap:wrap;
    margin-top:8px;
  }
  .nb-k-quickLabel{
    font-size:12px;
    font-weight:600;
    letter-spacing:.08px;
    color: rgba(11,37,69,.66);
  }
  html.dark .nb-k-quickLabel{ color: rgba(226,232,240,.66); }
  .nb-k-quickChip{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid rgba(30,136,229,.25);
    background:rgba(30,136,229,.08);
    color: rgba(11,37,69,.86);
    font-size:12px;
    font-weight:600;
    line-height:1;
    text-decoration:none;
    transition: transform .08s ease, border-color .12s ease, box-shadow .12s ease;
  }
  .nb-k-quickIcon{
    font-size:10px;
    font-weight:800;
    letter-spacing:.08em;
    padding:2px 6px;
    border-radius:999px;
    background: rgba(30,136,229,.18);
    color: rgba(30,136,229,.95);
  }
  html.dark .nb-k-quickChip{
    border-color: rgba(148,163,184,.22);
    background: rgba(15,23,42,.4);
    color: rgba(226,232,240,.9);
  }
  html.dark .nb-k-quickIcon{
    background: rgba(30,136,229,.28);
    color: rgba(226,232,240,.95);
  }
  .nb-k-quickChip:hover{
    transform: translateY(-1px);
    border-color: rgba(30,136,229,.35);
    box-shadow: 0 6px 16px rgba(15,23,42,.08);
  }
  .nb-k-quickChip:focus-visible{
    outline:none;
    border-color: rgba(30,136,229,.45);
    box-shadow: 0 0 0 3px rgba(30,136,229,.18);
  }
  .nb-k-quickGroup{
    margin-top:10px;
    padding:12px;
    border-radius:14px;
    border:1px solid rgba(15,23,42,.08);
    background: rgba(255,255,255,.90);
    box-shadow: 0 12px 30px rgba(2,6,23,.06);
  }
  html.dark .nb-k-quickGroup{
    border-color: rgba(148,163,184,.14);
    background: rgba(15,23,42,.58);
    box-shadow: 0 14px 34px rgba(0,0,0,.32);
  }

  .nb-k-sortForm{
    display:flex;
    gap:8px;
    align-items:center;
    flex-wrap:wrap;
  }
  .nb-k-sortSelect{
    min-width:165px;
    height:36px;
    border-radius:10px;
    font-size:12.5px;
    padding:0 10px;
  }

  /* Stabilize header rendering (avoid blur/overlap) */
  .nb-k-head{
    position: static;
    z-index: 1;
    backdrop-filter: none;
    background: #ffffff;
  }
  html.dark .nb-k-head{
    background: #0b1220;
  }
  html.dark .nb-k-activeBar .left{ color: rgba(226,232,240,.78); }
  .nb-k-activeBar .right{
    margin-left:auto;
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
  }
  .nb-k-chip{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.75);
    font-size:12px;
    font-weight:600;
    line-height:1;
  }
  html.dark .nb-k-chip{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.40);
  }
  .nb-k-chip b{ font-weight:600; }
  .nb-k-chip .nb-k-chip-close{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:18px;
    height:18px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.9);
    color: rgba(11,37,69,.75);
    font-size:12px;
    line-height:1;
    text-decoration:none;
  }
  .nb-k-chip .nb-k-chip-close:focus-visible{
    outline:none;
    border-color: rgba(30,136,229,.35);
    box-shadow: 0 0 0 3px rgba(30,136,229,.16);
  }
  .nb-k-chip .nb-k-chip-close:hover{
    border-color: rgba(30,136,229,.22);
    color: rgba(30,136,229,.9);
  }
  html.dark .nb-k-chip .nb-k-chip-close{
    border-color: rgba(148,163,184,.18);
    background: rgba(15,23,42,.45);
    color: rgba(226,232,240,.72);
  }

  .nb-k-resetAll{
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 10px;
    border-radius:999px;
    border:1px dashed rgba(30,136,229,.26);
    color: rgba(30,136,229,.95);
    font-weight:600; font-size:12px;
    text-decoration:none;
    background: rgba(255,255,255,.9);
  }
  .nb-k-resetAll:hover{
    background: rgba(30,136,229,.08);
    border-color: rgba(30,136,229,.35);
  }
  html.dark .nb-k-resetAll{
    background: rgba(15,23,42,.5);
    border-color: rgba(147,197,253,.28);
    color: rgba(147,197,253,.95);
  }

  @media (max-width: 860px){
    .nb-k-activeBar{ flex-direction:column; align-items:flex-start; }
    .nb-k-activeBar .right{ margin-left:0; width:100%; }
  }

  .nb-k-mark{
    background: rgba(255,230,138,.65);
    padding: 0 2px;
    border-radius: 4px;
  }

  .nb-k-sectionSpace{ height:24px; }
  .nb-k-gap-sm{ height:10px; }
  .nb-k-gap-md{ height:14px; }
  .nb-k-m-0{ margin:0; }
  .nb-k-mt-0{ margin-top:0; }
  .nb-k-mt-6{ margin-top:6px; }
  .nb-k-mt-8{ margin-top:8px; }
  .nb-k-pad-12{ padding:12px; }
  .nb-k-pad-14{ padding:14px; }
  .nb-k-radius-18{ border-radius:18px; }
  .nb-k-miniDivider{
    height:1px;
    border:0;
    margin:12px 0;
    background: linear-gradient(90deg, rgba(15,23,42,.08), rgba(15,23,42,.04), rgba(15,23,42,.08));
  }
  html.dark .nb-k-miniDivider{
    background: linear-gradient(90deg, rgba(148,163,184,.16), rgba(148,163,184,.08), rgba(148,163,184,.16));
  }
  @media (max-width: 768px){
    .nb-k-miniDivider{ display:none; }
  }

  .nb-k-shelves{
    transition: opacity .18s ease, transform .18s ease, max-height .18s ease;
    opacity:1;
    transform: translateY(0);
    max-height: 2000px;
  }
  .nb-k-shelves.is-hidden{
    opacity:0;
    transform: translateY(-6px);
    max-height: 0;
    overflow: hidden;
    pointer-events:none;
  }

  /* Switch */
  .nb-k-switch{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:4px 10px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.75);
    font-size:12px;
    font-weight:600;
    color: rgba(11,37,69,.78);
  }
  html.dark .nb-k-switch{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.40);
    color: rgba(226,232,240,.78);
  }
  .nb-k-switch input{
    appearance:none;
    width:34px;
    height:20px;
    background: rgba(148,163,184,.35);
    border-radius:999px;
    position:relative;
    outline:none;
    cursor:pointer;
    transition: background .15s ease;
  }
  .nb-k-switch input::after{
    content:"";
    width:16px;
    height:16px;
    background:#fff;
    border-radius:50%;
    position:absolute;
    top:2px;
    left:2px;
    transition: transform .15s ease;
    box-shadow: 0 2px 6px rgba(15,23,42,.18);
  }
  .nb-k-switch input:checked{
    background: rgba(30,136,229,.85);
  }
  .nb-k-switch input:checked::after{
    transform: translateX(14px);
  }
  /* ---------- Alerts ---------- */
  .nb-alert{ padding:12px 14px; border-radius:18px; }
  .nb-alert .h{ font-size:12.5px; font-weight:600; letter-spacing:.12px; }
  .nb-alert .m{
    margin-top:4px;
    font-size:13px;
    font-weight:500;
    color: rgba(11,37,69,.72);
    line-height:1.35;
  }
  html.dark .nb-alert .m{ color: rgba(226,232,240,.74); }

  .nb-k-empty{
    padding:16px;
  }
  .nb-k-emptyRow{
    display:flex;
    gap:12px;
    align-items:flex-start;
    flex-wrap:wrap;
  }
  .nb-k-emptyIcon{
    width:52px;
    height:52px;
    border-radius:16px;
    border:1px solid rgba(30,136,229,.18);
    background: rgba(30,136,229,.08);
    display:flex;
    align-items:center;
    justify-content:center;
    color: rgba(30,136,229,.95);
    flex:0 0 auto;
  }
  .nb-k-emptyTitle{
    font-size:14px;
    font-weight:600;
    color: rgba(11,37,69,.94);
  }
  html.dark .nb-k-emptyTitle{ color: rgba(226,232,240,.92); }
  .nb-k-emptyDesc{
    margin-top:6px;
    font-size:13px;
    color: rgba(11,37,69,.70);
    line-height:1.35;
  }
  html.dark .nb-k-emptyDesc{ color: rgba(226,232,240,.70); }
  .nb-k-emptyList{
    margin:6px 0 0 18px;
    font-size:12.6px;
    color: rgba(11,37,69,.68);
    line-height:1.5;
  }
  html.dark .nb-k-emptyList{ color: rgba(226,232,240,.70); }
  .nb-k-emptyActions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }
  @media (max-width: 640px){
    .nb-k-emptyIcon{
      width:44px;
      height:44px;
      border-radius:14px;
    }
  }

  /* ---------- GRID LIST ---------- */
  .nb-k-grid{
    display:grid;
    grid-template-columns: repeat(2, minmax(0,1fr));
    gap:12px;
  }
  @media (min-width: 1240px){
    .nb-k-grid{ grid-template-columns: repeat(3, minmax(0,1fr)); }
  }
  @media (max-width: 700px){
    .nb-k-grid{ grid-template-columns: 1fr; }
  }

  .nb-k-item{
    padding:10px;
    border-radius:14px;
    overflow:hidden;
    position:relative;
    border:1px solid rgba(148,163,184,.25);
    background:#ffffff;
    transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
  }
  html.dark .nb-k-item{
    border-color: rgba(148,163,184,.18);
    background:#0f172a;
  }
  .nb-k-item:hover{
    border-color: rgba(30,136,229,.25);
    box-shadow: 0 14px 28px rgba(2,6,23,.08);
    transform: translateY(-2px);
  }
  html.dark .nb-k-item:hover{
    border-color: rgba(147,197,253,.22);
    box-shadow: 0 14px 28px rgba(0,0,0,.28);
  }

  .nb-k-click{ text-decoration:none; color:inherit; display:block; }
  .nb-k-top{ display:flex; gap:10px; align-items:flex-start; }

  .nb-k-ico{
    width:46px; height:46px;
    border-radius:12px;
    border:1px solid var(--nb-border);
    background: rgba(255,255,255,.55);
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0;
    color: rgba(11,37,69,.78);
    overflow:hidden;
    position:relative;
  }
  html.dark .nb-k-ico{ background: rgba(255,255,255,.06); color: rgba(226,232,240,.78); }

  .nb-k-ico{
    background: linear-gradient(135deg, rgba(30,136,229,.12), rgba(16,185,129,.12));
    border-color: rgba(148,163,184,.25);
    color: rgba(11,37,69,.78);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.55);
  }
  html.dark .nb-k-ico{
    background: linear-gradient(135deg, rgba(30,136,229,.22), rgba(16,185,129,.12));
    border-color: rgba(148,163,184,.25);
    color: rgba(226,232,240,.85);
  }

  .nb-k-thumb{ width:100%; height:100%; object-fit:cover; display:block; }

  .nb-k-main{ min-width:0; flex:1; }

  .nb-k-titleRow{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; }

  .nb-k-biblioTitle{
    font-size:13.2px;
    font-weight:600;
    letter-spacing:.01px;
    line-height:1.2;
    color: rgba(11,37,69,.94);
    min-width:0;
    flex: 1 1 auto;
    display:-webkit-box;
    -webkit-line-clamp:2;
    -webkit-box-orient: vertical;
    white-space:normal;
    overflow:hidden;
    text-overflow:ellipsis;
    max-width:100%;
  }
  html.dark .nb-k-biblioTitle{ color: rgba(226,232,240,.92); }

  .nb-k-subtitle{
    margin-top:4px;
    font-size:12.8px;
    font-weight:500;
    color: rgba(11,37,69,.62);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  html.dark .nb-k-subtitle{ color: rgba(226,232,240,.66); }

  .nb-k-meta{
    margin-top:8px;
    font-size:12.8px;
    font-weight:500;
    color: rgba(11,37,69,.70);
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    line-height:1.35;
  }
  html.dark .nb-k-meta{ color: rgba(226,232,240,.72); }
  .nb-k-meta > span + span::before{
    content:"\2022";
    margin:0 6px 0 2px;
    color: rgba(11,37,69,.45);
    font-weight:700;
  }
  html.dark .nb-k-meta > span + span::before{ color: rgba(226,232,240,.45); }

  .nb-k-badges{ margin-top:6px; display:flex; gap:6px; flex-wrap:wrap; }
  .nb-k-badges .nb-badge{
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-weight:550 !important;
    font-size:12px;
    letter-spacing:.15px;
  }
  .nb-badge-latest{
    border-color: rgba(30,136,229,.25);
    background: rgba(30,136,229,.08);
    color: rgba(30,136,229,.9);
  }
  .nb-badge-popular{
    border-color: rgba(245,158,11,.25);
    background: rgba(245,158,11,.10);
    color: rgba(161,98,7,.9);
  }

  .nb-k-avab{
    border-radius:999px;
    padding:4px 10px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.75);
    font-size:12px;
    font-weight:650;
    color: rgba(11,37,69,.78);
    white-space:nowrap;
  }
  html.dark .nb-k-avab{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.40);
    color: rgba(226,232,240,.78);
  }
  .nb-k-avab.ok{ border-color: rgba(39,174,96,.22); background: rgba(39,174,96,.10); color: rgba(20,132,70,.95); }
  html.dark .nb-k-avab.ok{ border-color: rgba(39,174,96,.22); background: rgba(39,174,96,.14); color: rgba(134,239,172,.92); }
  .nb-k-avab.no{ border-color: rgba(148,163,184,.18); background: rgba(148,163,184,.10); color: rgba(11,37,69,.70); }
  html.dark .nb-k-avab.no{ border-color: rgba(148,163,184,.16); background: rgba(148,163,184,.10); color: rgba(226,232,240,.70); }

  .nb-k-arrow{ margin-left:auto; opacity:.55; padding-top:2px; }

  /* ---------- Actions bottom ---------- */
  .nb-k-actionsBottom{
    margin-top:12px;
    padding-top:12px;
    border-top:1px solid var(--nb-border);
    display:flex;
    gap:10px;
    justify-content:space-between;
    flex-wrap:wrap;
    align-items:center;
  }
  .nb-k-actionsLeft{
    display:flex;
    align-items:center;
    gap:8px;
    flex:1 1 auto;
  }
  .nb-k-actionsRight{
    display:flex;
    align-items:center;
    gap:8px;
  }

  .nb-k-miniBtn{
    width:36px; height:36px;
    border-radius:12px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.80);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    user-select:none;
    text-decoration:none;
    transition: background .12s ease, border-color .12s ease, box-shadow .12s ease, transform .06s ease, color .12s ease;
    color: rgba(11,37,69,.86);
  }
  .nb-k-miniBtn:active{ transform: translateY(1px); }
  .nb-k-miniBtn svg{ width:16px; height:16px; }

  html.dark .nb-k-miniBtn{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.42);
    color: rgba(226,232,240,.88);
  }

  .nb-k-miniBtn:hover{
    background: rgba(255,255,255,.92);
    box-shadow: 0 12px 22px rgba(2,6,23,.06);
  }
  html.dark .nb-k-miniBtn:hover{
    background: rgba(255,255,255,.08);
    box-shadow: 0 12px 22px rgba(0,0,0,.22);
  }

  .nb-k-miniBtn.edit{
    border-color: rgba(30,136,229,.18);
    color: rgba(30,136,229,.92);
    background: rgba(30,136,229,.08);
  }
  html.dark .nb-k-miniBtn.edit{
    border-color: rgba(147,197,253,.18);
    color: rgba(147,197,253,.92);
    background: rgba(30,136,229,.16);
  }

  .nb-k-miniBtn.del{
    border-color: rgba(220,38,38,.22);
    color: rgba(220,38,38,.92);
    background: rgba(220,38,38,.06);
  }
  html.dark .nb-k-miniBtn.del{
    border-color: rgba(248,113,113,.22);
    color: rgba(248,113,113,.92);
    background: rgba(220,38,38,.14);
  }

  /* =========================================================
     PAGINATION PREMIUM (custom)
     ========================================================= */
  .nb-pg{
    display:flex;
    justify-content:center;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
    padding:4px 2px;
  }

  .nb-pg-left,
  .nb-pg-right{
    display:flex;
    gap:8px;
    align-items:center;
    flex: 0 0 auto;
  }

  .nb-pg-pages{
    display:flex;
    gap:8px;
    align-items:center;
    flex-wrap:wrap;
    justify-content:center;
    flex: 1 1 auto;
    min-width: 0;
  }

  .nb-pg-btn{
    height:38px;
    min-width:38px;
    padding:0 12px;
    border-radius:14px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.80);
    color: rgba(11,37,69,.88);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    text-decoration:none;
    font-size:13px;
    font-weight:650;
    letter-spacing:.05px;
    user-select:none;
    transition: background .12s ease, border-color .12s ease, transform .06s ease, box-shadow .12s ease, color .12s ease;
  }
  .nb-pg-btn:active{ transform: translateY(1px); }
  .nb-pg-btn:hover{
    border-color: rgba(30,136,229,.18);
    box-shadow: 0 12px 22px rgba(2,6,23,.06);
  }

  html.dark .nb-pg-btn{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.42);
    color: rgba(226,232,240,.88);
  }
  html.dark .nb-pg-btn:hover{
    border-color: rgba(147,197,253,.18);
    box-shadow: 0 12px 22px rgba(0,0,0,.22);
  }

  /* icon lock ukuran (anti svg global) */
  .nb-pg-btn svg{
    width:18px !important;
    height:18px !important;
    flex: 0 0 auto;
  }

  .nb-pg-btn.is-active{
    border-color: rgba(30,136,229,.22);
    background: linear-gradient(180deg, rgba(30,136,229,1), rgba(21,101,192,1));
    color:#fff;
    box-shadow: 0 14px 26px rgba(30,136,229,.22);
  }
  .nb-pg-btn.is-active:hover{ box-shadow: 0 16px 30px rgba(30,136,229,.26); }

  .nb-pg-btn.is-disabled{
    opacity:.55;
    cursor:not-allowed;
    box-shadow:none !important;
    transform:none !important;
  }

  .nb-pg-ellipsis{
    height:38px;
    min-width:38px;
    padding:0 10px;
    border-radius:14px;
    border:1px dashed rgba(15,23,42,.14);
    color: rgba(11,37,69,.55);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:13px;
    font-weight:600;
    letter-spacing:.08px;
    user-select:none;
    background: rgba(255,255,255,.55);
  }
  html.dark .nb-pg-ellipsis{
    border-color: rgba(148,163,184,.16);
    color: rgba(226,232,240,.60);
    background: rgba(15,23,42,.28);
  }

  /* mobile: rapihin spacing agar tidak kebesaran */
  @media (max-width: 420px){
    .nb-pg{ gap:8px; }
    .nb-pg-btn{ height:36px; min-width:36px; padding:0 10px; border-radius:13px; font-size:12.8px; }
    .nb-pg-ellipsis{ height:36px; min-width:36px; border-radius:13px; }
  }
  .nb-k-meta b{ font-weight:600; }

  /* =========================================================
     DISCOVERY SHELVES (Trending & New Arrivals)
     ========================================================= */
  .nb-k-shelf{
    padding:14px;
    border-radius:18px;
  }
  .nb-k-shelf-pop{
    background:
      linear-gradient(135deg, rgba(30,136,229,.18), rgba(255,255,255,.9) 55%),
      radial-gradient(1200px 300px at 0% 0%, rgba(30,136,229,.12), transparent 70%);
    border:1px solid rgba(30,136,229,.28);
  }
  .nb-k-shelf-new{
    background:
      linear-gradient(135deg, rgba(16,185,129,.18), rgba(255,255,255,.9) 55%),
      radial-gradient(1200px 300px at 0% 0%, rgba(16,185,129,.12), transparent 70%);
    border:1px solid rgba(16,185,129,.28);
  }
  .nb-k-shelfTitle{
    font-size:13.5px;
    font-weight:560;
    letter-spacing:.04px;
    color: rgba(11,37,69,.92);
  }
  .nb-k-shelfPill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:3px 8px;
    border-radius:999px;
    font-size:11px;
    font-weight:700;
    letter-spacing:.2px;
    margin-right:6px;
  }
  .nb-k-shelfPill svg{
    width:12px;
    height:12px;
  }
  .nb-k-shelfPill.pop{
    background: rgba(30,136,229,.15);
    color: rgba(21,101,192,.95);
    border:1px solid rgba(30,136,229,.25);
  }
  .nb-k-shelfPill.new{
    background: rgba(16,185,129,.15);
    color: rgba(5,150,105,.95);
    border:1px solid rgba(16,185,129,.25);
  }
  .nb-k-shelfBadge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    margin-left:6px;
    padding:3px 8px;
    border-radius:999px;
    font-size:11px;
    font-weight:600;
    color:#0f172a;
    background:rgba(59,130,246,.10);
    border:1px solid rgba(59,130,246,.22);
  }
  .nb-k-shelfBadge-latest{
    color:#064e3b;
    background:rgba(16,185,129,.10);
    border-color:rgba(16,185,129,.22);
  }
  html.dark .nb-k-shelfTitle{ color: rgba(226,232,240,.92); }
  .nb-k-shelfSub{
    margin-top:4px;
    font-size:12.8px;
    color: rgba(11,37,69,.62);
  }
  html.dark .nb-k-shelfSub{ color: rgba(226,232,240,.66); }
  .nb-k-shelfGrid{
    margin-top:12px;
    display:grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap:12px;
  }
  .nb-k-shelfActions{
    display:flex;
    align-items:center;
    gap:6px;
    flex-wrap:wrap;
  }
  .nb-k-carouselBtn{
    width:32px;
    height:32px;
    border-radius:10px;
    border:1px solid rgba(15,23,42,.12);
    background: rgba(255,255,255,.9);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    color: rgba(11,37,69,.85);
    transition: border-color .12s ease, box-shadow .12s ease, transform .08s ease;
  }
  .nb-k-carouselBtn:active{ transform: translateY(1px); }
  .nb-k-carouselBtn:hover{ border-color: rgba(30,136,229,.25); box-shadow: 0 8px 20px rgba(2,6,23,.08); }
  .nb-k-shelfRow{
    display:flex;
    gap:12px;
    overflow-x:hidden;
    scroll-snap-type:x proximity;
    padding-bottom:6px;
    scroll-behavior:smooth;
  }
  .nb-k-shelfRow .nb-k-mini{
    flex:0 0 280px;
    min-width:280px;
    scroll-snap-align:start;
  }
  .nb-k-shelfRow{ -ms-overflow-style: none; scrollbar-width: none; }
  .nb-k-shelfRow::-webkit-scrollbar{ display:none; }
  @media (max-width: 980px){
    .nb-k-shelfGrid{ grid-template-columns: repeat(2, minmax(0,1fr)); }
    .nb-k-shelfRow .nb-k-mini{ flex-basis:240px; min-width:240px; }
  }
  @media (max-width: 640px){
    .nb-k-shelfGrid{ grid-template-columns: 1fr; }
    .nb-k-shelfRow .nb-k-mini{ flex-basis:200px; min-width:200px; }
  }
  .nb-k-mini{
    padding:12px;
    border-radius:16px;
    border:1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.75);
    display:flex;
    gap:10px;
    align-items:flex-start;
    transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
    text-decoration:none;
    color:inherit;
  }
  html.dark .nb-k-mini{
    border-color: rgba(148,163,184,.16);
    background: rgba(15,23,42,.40);
  }
  .nb-k-shelf-pop .nb-k-mini{
    border-color: rgba(30,136,229,.18);
    background: rgba(255,255,255,.9);
  }
  .nb-k-shelf-new .nb-k-mini{
    border-color: rgba(16,185,129,.18);
    background: rgba(255,255,255,.9);
  }
  .nb-k-mini:hover{
    border-color: rgba(30,136,229,.20);
    box-shadow: 0 14px 24px rgba(2,6,23,.08);
    transform: translateY(-2px);
  }
  .nb-k-mini:focus-visible{
    outline:none;
    border-color: rgba(30,136,229,.35);
    box-shadow: 0 0 0 3px rgba(30,136,229,.18);
  }
  html.dark .nb-k-mini:hover{
    border-color: rgba(147,197,253,.22);
    box-shadow: 0 14px 24px rgba(0,0,0,.28);
  }
  .nb-k-miniCover{
    width:48px;
    height:64px;
    border-radius:10px;
    border:1px solid var(--nb-border);
    overflow:hidden;
    flex: 0 0 auto;
    background: rgba(255,255,255,.60);
    display:flex;
    align-items:center;
    justify-content:center;
  }
  html.dark .nb-k-miniCover{ background: rgba(255,255,255,.08); }
  .nb-k-miniCover img{ width:100%; height:100%; object-fit:cover; }
  .nb-k-miniTitle{
    font-size:13px;
    font-weight:560;
    color: rgba(11,37,69,.92);
    line-height:1.2;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  html.dark .nb-k-miniTitle{ color: rgba(226,232,240,.92); }
  .nb-k-miniMeta{
    margin-top:6px;
    font-size:12.2px;
    color: rgba(11,37,69,.62);
    line-height:1.35;
  }
  html.dark .nb-k-miniMeta{ color: rgba(226,232,240,.66); }
  .nb-k-miniAva{
    margin-top:8px;
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 8px;
    border-radius:999px;
    border:1px solid rgba(15,23,42,.12);
    font-size:11.5px;
    font-weight:600;
    color: rgba(11,37,69,.74);
  }
  html.dark .nb-k-miniAva{
    border-color: rgba(148,163,184,.16);
    color: rgba(226,232,240,.74);
  }
  .nb-k-miniTag{
    border-radius:999px;
    padding:4px 8px;
    font-size:11.5px;
    font-weight:700;
    border:1px solid rgba(148,163,184,.18);
    background: rgba(148,163,184,.10);
    color: rgba(11,37,69,.78);
  }
  .nb-k-miniTag.pop{
    border-color: rgba(39,174,96,.22);
    background: rgba(39,174,96,.10);
    color: rgba(20,132,70,.95);
  }
  .nb-k-miniTag.new{
    border-color: rgba(30,136,229,.22);
    background: rgba(30,136,229,.10);
    color: rgba(30,136,229,.95);
  }
  .nb-k-toggle{
    margin-top:6px;
    display:inline-flex;
    align-items:center;
    gap:8px;
    font-size:11.5px;
    color: rgba(11,37,69,.7);
  }
  .nb-k-toggle input{ transform: translateY(1px); }
  html.dark .nb-k-toggle{ color: rgba(226,232,240,.7); }
  html.dark .nb-k-miniTag{
    border-color: rgba(148,163,184,.18);
    background: rgba(148,163,184,.12);
    color: rgba(226,232,240,.78);
  }
    .nb-k-batchSearch{ min-width:220px; }
    .nb-k-batchBar .nb-field{ border-radius:10px; }
    .nb-k-batchField{ min-width:160px; }
    @media (max-width: 1280px){
      .nb-k-batchRow{ grid-template-columns: repeat(3, minmax(160px, 1fr)); }
    }
    @media (max-width: 980px){
      .nb-k-batchRow{ grid-template-columns: repeat(2, minmax(160px, 1fr)); }
    }
    @media (max-width: 720px){
      .nb-k-batchRow{ grid-template-columns: 1fr; }
    }
    .nb-k-batchBar{ display:grid; gap:10px; border-radius:16px; }
    .nb-k-batchLeft{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .nb-k-batchRight{ display:grid; gap:10px; width:100%; }
    @media (max-width: 720px){
      .nb-k-batchActions{ justify-content:stretch; }
      .nb-k-batchActions .nb-btn{ flex:1 1 100%; }
    }

    .nb-k-modal{
      position:fixed;
      inset:0;
      display:flex;
      align-items:center;
      justify-content:center;
      z-index:1250;
    }
    .nb-k-modal.is-hidden{ display:none; }
    .nb-k-modalBackdrop{
      position:absolute;
      inset:0;
      background: rgba(15,23,42,.55);
    }
    .nb-k-modalCard{
      position:relative;
      width:min(640px, 92vw);
      border-radius:18px;
      border:1px solid rgba(148,163,184,.22);
      background:#fff;
      box-shadow: 0 24px 52px rgba(2,6,23,.25);
      z-index:1;
      padding:16px;
    }
    html.dark .nb-k-modalCard{
      background: rgba(15,23,42,.95);
      border-color: rgba(148,163,184,.24);
      box-shadow: 0 28px 56px rgba(0,0,0,.45);
    }
    .nb-k-modalHeader{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:12px;
      margin-bottom:10px;
    }
    .nb-k-modalTitle{
      font-size:14px;
      font-weight:800;
      color: rgba(11,37,69,.9);
    }
    html.dark .nb-k-modalTitle{ color: rgba(226,232,240,.92); }
    .nb-k-modalSub{
      font-size:12px;
      color: rgba(11,37,69,.62);
      margin-top:2px;
    }
    html.dark .nb-k-modalSub{ color: rgba(226,232,240,.62); }
    .nb-k-modalClose{
      width:30px;
      height:30px;
      border-radius:999px;
      border:1px solid rgba(15,23,42,.12);
      background: rgba(255,255,255,.92);
      font-size:14px;
      font-weight:700;
      cursor:pointer;
    }
    html.dark .nb-k-modalClose{
      border-color: rgba(148,163,184,.24);
      background: rgba(15,23,42,.6);
      color: rgba(226,232,240,.9);
    }
    .nb-k-modalRow{
      display:grid;
      grid-template-columns:repeat(2, minmax(0, 1fr));
      gap:12px;
      margin-bottom:12px;
    }
    .nb-k-modalStat{
      border:1px solid rgba(148,163,184,.18);
      background: rgba(148,163,184,.08);
      border-radius:14px;
      padding:10px 12px;
    }
    .nb-k-modalStat .label{
      font-size:11.5px;
      color: rgba(11,37,69,.6);
    }
    .nb-k-modalStat .value{
      margin-top:4px;
      font-weight:700;
      color: rgba(11,37,69,.92);
    }
    html.dark .nb-k-modalStat{
      background: rgba(15,23,42,.55);
      border-color: rgba(148,163,184,.2);
    }
    html.dark .nb-k-modalStat .label{ color: rgba(226,232,240,.6); }
    html.dark .nb-k-modalStat .value{ color: rgba(226,232,240,.95); }
    .nb-k-modalListWrap{
      border-top:1px dashed rgba(148,163,184,.25);
      padding-top:10px;
    }
    .nb-k-modalListTitle{
      font-size:12px;
      font-weight:700;
      color: rgba(11,37,69,.78);
      margin-bottom:8px;
    }
    html.dark .nb-k-modalListTitle{ color: rgba(226,232,240,.82); }
    .nb-k-modalList{
      list-style:none;
      margin:0;
      padding:0;
      display:grid;
      gap:6px;
      font-size:12.5px;
      color: rgba(11,37,69,.78);
    }
    html.dark .nb-k-modalList{ color: rgba(226,232,240,.82); }
    .nb-k-modalActions{
      display:flex;
      justify-content:flex-end;
      gap:8px;
      margin-top:14px;
    }
  </style>

<div class="nb-k-wrap">

  {{-- HEADER + FILTER --}}
  <div class="nb-card nb-k-head">
    <div class="nb-k-headTop">
      <div style="min-width:260px;">
        <div class="nb-k-title">Katalog</div>
        <div class="nb-k-sub">Bibliografi (judul) + status ketersediaan eksemplar.</div>
        @if($isPublic)
          <div class="nb-k-opac nb-k-mt-6">OPAC Mode</div>
        @endif
      </div>

      @if($canManage)
        <a href="{{ route('katalog.create') }}" class="nb-btn nb-btn-primary nb-k-addBtn">
          <span aria-hidden="true" style="display:inline-flex;width:18px;height:18px;align-items:center;justify-content:center;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
          Tambah Bibliografi
        </a>
      @endif
    </div>

    <hr class="nb-k-divider">

    <form id="nbFilterForm"
          method="GET"
          action="{{ route($indexRouteName) }}"
          data-nb-skeleton="{{ $katalogSkeleton ? '1' : '0' }}"
          data-nb-preload="{{ $katalogPreloadMargin }}"
          data-nb-infinite="1">
      <input type="hidden" name="rank" value="{{ $rankMode }}">
      <button type="button" class="nb-k-filterToggle" id="nbFilterToggle" aria-expanded="false" aria-controls="nbFilterWrap">
        <span>Filter & Pencarian</span>
        <span style="opacity:.7; font-size:12px;">Ketuk untuk buka</span>
      </button>

      <div class="nb-k-filterWrap is-collapsed" id="nbFilterWrap">
      <div class="nb-k-filter">

        <div class="nb-k-fieldBlock span2">
          <label class="nb-k-label">Pencarian</label>
          <div class="nb-k-suggestWrap nb-k-suggestRow">
            <input class="nb-field" id="nbSearchInput" type="text" name="q" value="{{ $q }}"
                   placeholder="Judul, pengarang, ISBN, DDC, nomor panggil."
                   data-nb-autosubmit="0"
                   autocomplete="off">
            <select class="nb-field nb-k-suggestFilter" id="nbSuggestFilter" aria-label="Filter saran">
              <option value="">Semua</option>
              <option value="title">Judul</option>
              <option value="author">Pengarang</option>
              <option value="subject">Subjek</option>
              <option value="publisher">Penerbit</option>
              <option value="isbn">ISBN</option>
              <option value="ddc">DDC</option>
              <option value="call_number">No. Panggil</option>
            </select>
            <div class="nb-k-suggest" id="nbSearchSuggest"></div>
            <div class="nb-k-preview" id="nbSearchPreview"></div>
            @if(!$isPublic)
              <div class="nb-k-cache" id="nbSearchCache"></div>
            @endif

            <div class="nb-k-inlineActions">
              <button class="nb-k-ibtn apply" type="submit" aria-label="Terapkan filter" title="Terapkan">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M9 16.2 4.8 12l1.4-1.4L9 13.4l8.8-8.8L19.2 6 9 16.2Z"/>
                </svg>
              </button>

              <a class="nb-k-ibtn reset"
                 href="{{ route($indexRouteName) }}"
                 aria-label="Reset filter"
                 title="Reset">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                  <path fill="currentColor" d="M12 6V3L8 7l4 4V8c2.76 0 5 2.24 5 5a5 5 0 0 1-8.66 3.54l-1.42 1.42A7 7 0 0 0 19 13c0-3.87-3.13-7-7-7Z"/>
                </svg>
              </a>

              <button type="button"
                      class="nb-k-advancedToggle"
                      id="nbAdvancedToggle"
                      aria-expanded="{{ $hasAdvanced ? 'true' : 'false' }}"
                      aria-controls="nbAdvancedWrap">
                Advanced
                @if($advancedCount > 0)
                  <span class="count">{{ $advancedCount }}</span>
                @endif
              </button>
              @if($canManage)
                <button type="button"
                        class="nb-k-shortcutToggle"
                        id="nbShortcutToggle"
                        aria-expanded="false"
                        aria-controls="nbShortcutModal">
                  Shortcut
                </button>
              @endif
            </div>
          </div>
        </div>


      </div>
        <div class="nb-k-advancedWrap" id="nbAdvancedWrap" aria-hidden="true">
          <button class="nb-k-advancedBackdrop" type="button" data-close-advanced aria-label="Tutup panel filter lanjutan"></button>
          <div class="nb-k-advancedPanel">
          <div class="nb-k-advancedHead">
            <div>
              <div class="nb-k-advancedTitle">Filter Lanjutan</div>
              <div class="nb-k-advancedSub">Atur filter detail tanpa memenuhi halaman utama.</div>
            </div>
            <button type="button" class="nb-k-advancedClose" id="nbAdvancedClose" aria-label="Tutup">×</button>
          </div>
        <div class="nb-k-filter">
          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">DDC</label>
            <input class="nb-field" type="text" name="ddc" value="{{ $ddc }}" placeholder="contoh: 005.133" data-nb-autosubmit="1">
          </div>

          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Tahun (tepat)</label>
            <input class="nb-field" type="number" name="year" value="{{ $year }}" placeholder="2024" min="0" max="2100" data-nb-autosubmit="1">
          </div>
          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Tahun Dari</label>
            <input class="nb-field" type="number" name="year_from" value="{{ $yearFrom > 0 ? $yearFrom : '' }}" placeholder="2010" min="0" max="2100" data-nb-autosubmit="1">
          </div>
          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Tahun Sampai</label>
            <input class="nb-field" type="number" name="year_to" value="{{ $yearTo > 0 ? $yearTo : '' }}" placeholder="2025" min="0" max="2100" data-nb-autosubmit="1">
          </div>
          <div class="nb-k-fieldBlock" style="grid-column: 1 / -1;">
            <label class="nb-k-label">Rentang Tahun Cepat</label>
            <div class="nb-k-yearRange" data-nb-year-range data-year-min="{{ $yearMinBound }}" data-year-max="{{ $yearMaxBound }}">
              <input type="range" min="{{ $yearMinBound }}" max="{{ $yearMaxBound }}" value="{{ $yearSliderFrom }}" data-year-from-slider>
              <input type="range" min="{{ $yearMinBound }}" max="{{ $yearMaxBound }}" value="{{ $yearSliderTo }}" data-year-to-slider>
              <div class="nb-k-yearRangeMeta">
                <span data-year-from-label>{{ $yearSliderFrom }}</span>
                <span data-year-to-label>{{ $yearSliderTo }}</span>
              </div>
            </div>
          </div>

          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Penerbit</label>
            <select class="nb-field" name="publisher[]" multiple size="4" data-nb-autosubmit-change="1">
              @foreach($publisherFacets as $pub)
                <option value="{{ $pub->publisher }}" {{ in_array((string) $pub->publisher, array_map('strval', $publisherList), true) ? 'selected' : '' }}>
                  {{ $pub->publisher }} ({{ $pub->total }})
                </option>
              @endforeach
            </select>
            <datalist id="publisherOptions">
              @foreach($publisherFacets as $pub)
                <option value="{{ $pub->publisher }}">
              @endforeach
            </datalist>
          </div>

          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Pengarang</label>
            <select class="nb-field" name="author[]" multiple size="4" data-nb-autosubmit-change="1">
              @foreach($authorFacets as $a)
                <option value="{{ $a->id }}" {{ in_array((int) $a->id, array_map('intval', $authorList), true) ? 'selected' : '' }}>
                  {{ $a->name }} ({{ $a->total }})
                </option>
              @endforeach
            </select>
          </div>

          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Subjek</label>
            <select class="nb-field" name="subject[]" multiple size="4" data-nb-autosubmit-change="1">
              @foreach($subjectFacets as $s)
                <option value="{{ $s->id }}" {{ in_array((int) $s->id, array_map('intval', $subjectList), true) ? 'selected' : '' }}>
                  {{ $s->term ?? $s->name }} ({{ $s->total }})
                </option>
              @endforeach
            </select>
          </div>

          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Urutkan</label>
            <select class="nb-field" name="sort" data-nb-autosubmit-change="1">
              <option value="relevant" {{ $sort === 'relevant' ? 'selected' : '' }}>Relevan</option>
              <option value="latest" {{ $sort === 'latest' ? 'selected' : '' }}>Terbaru</option>
              <option value="popular" {{ $sort === 'popular' ? 'selected' : '' }}>Populer</option>
              <option value="available" {{ $sort === 'available' ? 'selected' : '' }}>Tersedia dulu</option>
            </select>
          </div>

          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Ketersediaan</label>
            <label class="nb-k-ava">
              <input type="checkbox" name="available" value="1" {{ $onlyAvailable ? 'checked' : '' }} data-nb-autosubmit-change="1">
              <span>Hanya yang tersedia</span>
            </label>
          </div>

          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Judul</label>
            <input class="nb-field" type="text" name="title" value="{{ $title }}" placeholder="Judul spesifik" data-nb-autosubmit="1">
          </div>

          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Pengarang (teks)</label>
            <input class="nb-field" type="text" name="author_name" value="{{ $authorName }}" placeholder="Nama pengarang" data-nb-autosubmit="1">
          </div>

          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Subjek (teks)</label>
            <input class="nb-field" type="text" name="subject_term" value="{{ $subjectTerm }}" placeholder="Topik/subjek" data-nb-autosubmit="1">
          </div>

          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">ISBN</label>
            <input class="nb-field" type="text" name="isbn" value="{{ $isbn }}" placeholder="ISBN" data-nb-autosubmit="1">
          </div>

          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">No. Panggil</label>
            <input class="nb-field" type="text" name="call_number" value="{{ $callNumber }}" placeholder="Nomor panggil" data-nb-autosubmit="1">
          </div>

          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Bahasa</label>
            <select class="nb-field" name="language[]" multiple size="4" data-nb-autosubmit-change="1">
              @foreach($languageOptions as $opt)
                <option value="{{ $opt }}" {{ in_array((string) $opt, array_map('strval', $languageList), true) ? 'selected' : '' }}>{{ $opt }}</option>
              @endforeach
            </select>
          </div>

          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Jenis</label>
            <select class="nb-field" name="material_type[]" multiple size="4" data-nb-autosubmit-change="1">
              @foreach($materialTypeOptions as $opt)
                <option value="{{ $opt }}" {{ in_array((string) $opt, array_map('strval', $materialTypeList), true) ? 'selected' : '' }}>{{ $opt }}</option>
              @endforeach
            </select>
          </div>

          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Media</label>
            <select class="nb-field" name="media_type[]" multiple size="4" data-nb-autosubmit-change="1">
              @foreach($mediaTypeOptions as $opt)
                <option value="{{ $opt }}" {{ in_array((string) $opt, array_map('strval', $mediaTypeList), true) ? 'selected' : '' }}>{{ $opt }}</option>
              @endforeach
            </select>
          </div>
          <div class="nb-k-fieldBlock">
            <label class="nb-k-label">Cabang</label>
            <select class="nb-field" name="branch[]" multiple size="4" data-nb-autosubmit-change="1">
              @foreach($branchOptions as $opt)
                <option value="{{ $opt->id }}" {{ in_array((int) $opt->id, array_map('intval', $branchList), true) ? 'selected' : '' }}>
                  {{ $opt->name }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        @if(!$isPublic)
          <div class="nb-k-advPrefs">
            <div class="nb-k-advTitle" style="margin-bottom:6px;">Pengaturan Tampilan (Admin)</div>
            <div class="nb-k-advSub" style="margin-bottom:8px;">Opsional. Hanya mempengaruhi tampilanmu.</div>
            <div class="nb-k-advPrefsRow">
              <label class="nb-k-toggle" for="nbSkeletonToggle" title="Tampilkan skeleton saat update">
                <input type="checkbox" id="nbSkeletonToggle" {{ $katalogSkeleton ? 'checked' : '' }}>
                <span>Skeleton</span>
              </label>
              <div class="nb-k-toggle" style="gap:10px; align-items:center;"
                   title="Normal: 300 (hemat jaringan). Seimbang: 500 (lebih halus). Agresif: 800 (paling cepat, lebih banyak request).">
                <label for="nbPreloadSlider">Preload</label>
                <input type="range" id="nbPreloadSlider" min="300" max="800" step="100" value="{{ $katalogPreloadMargin }}" list="nbPreloadMarks">
                <datalist id="nbPreloadMarks">
                  <option value="300" label="Normal"></option>
                  <option value="500" label="Seimbang"></option>
                  <option value="800" label="Agresif"></option>
                </datalist>
                <span id="nbPreloadValue">{{ $katalogPreloadMargin }}</span>
                <span id="nbPreloadLabel" style="font-weight:600; opacity:.8;"></span>
              </div>
            </div>
          </div>
        @endif

        <div class="nb-k-advBuilder">
          <div class="nb-k-advHead">
            <div>
              <div class="nb-k-advTitle">Advanced Query Builder</div>
              <div class="nb-k-advSub">Fielded search + operator AND/OR + exact match.</div>
            </div>
            <div class="nb-k-advControls">
              <select class="nb-field nb-k-advSelect" name="qf_op" data-nb-autosubmit-change="1">
                <option value="AND" {{ strtoupper($qfOp) === 'AND' ? 'selected' : '' }}>AND</option>
                <option value="OR" {{ strtoupper($qfOp) === 'OR' ? 'selected' : '' }}>OR</option>
              </select>
              <label class="nb-k-advExact">
                <input type="checkbox" name="qf_exact" value="1" {{ $qfExact ? 'checked' : '' }} data-nb-autosubmit-change="1">
                <span>Exact match</span>
              </label>
            </div>
          </div>

          <div class="nb-k-advGrid">
            @php
              $builderRows = range(0, 2);
              $fieldMap = [
                'title' => 'Judul',
                'author' => 'Pengarang',
                'subject' => 'Subjek',
                'publisher' => 'Penerbit',
                'isbn' => 'ISBN',
                'ddc' => 'DDC',
                'call_number' => 'No. Panggil',
                'notes' => 'Catatan',
              ];
            @endphp
            @foreach($builderRows as $i)
              @php
                $fieldVal = $qfFields[$i] ?? '';
                $valueVal = $qfValues[$i] ?? '';
              @endphp
              <div class="nb-k-advRow">
                <select class="nb-field" name="qf_field[]" data-nb-autosubmit-change="1">
                  <option value="">Pilih field</option>
                  @foreach($fieldMap as $fKey => $fLabel)
                    <option value="{{ $fKey }}" {{ (string)$fieldVal === (string)$fKey ? 'selected' : '' }}>
                      {{ $fLabel }}
                    </option>
                  @endforeach
                </select>
                <input class="nb-field" type="text" name="qf_value[]" value="{{ $valueVal }}" placeholder="Isi kata kunci" data-nb-autosubmit="1">
              </div>
            @endforeach
          </div>
        </div>
        @if($advancedCount > 0)
          <div class="nb-k-mt-8">
            <a class="nb-k-resetAll" href="{{ route($indexRouteName) }}" title="Reset advanced filter">
              Reset Advanced
            </a>
          </div>
        @endif
        </div>
      </div>
      </div>
      <div class="nb-k-helpRow">
        <details class="nb-k-tipMini">
          <summary>Bantuan cepat</summary>
          <div class="nb-k-tipPanel">
            Ketik ISBN, DDC, atau nomor panggil untuk pencarian cepat. Gunakan <b>Ctrl+K</b> untuk fokus ke kolom pencarian.
          </div>
        </details>
      </div>

    @if($showFilterBar)
      <div class="nb-k-activeBar">
          <div class="left">
            <span class="nb-k-activeLabel">Filter aktif</span>
            @foreach($activeFilters as $f)
              <span class="nb-k-chip" title="{{ $f['label'] }}: {{ $f['value'] }}">
                <b>{{ $f['label'] }}</b> {{ $f['value'] }}
                @if(!empty($f['key']))
                  <a class="nb-k-chip-close" href="{{ $filterClearUrl($f['key'], $f['item'] ?? null) }}" title="Hapus filter {{ $f['label'] }}" aria-label="Hapus filter {{ $f['label'] }}">&times;</a>
                @endif
              </span>
            @endforeach
          </div>
          <div class="right">
            @if(count($activeFilters) > 0)
              <a class="nb-k-resetAll" href="{{ route($indexRouteName) }}" title="Reset semua filter">
                Reset Semua
              </a>
            @endif
            @if($isPublic)
              <label class="nb-k-switch" title="Tampilkan Shelf">
                <input type="checkbox" id="toggleShelves" name="shelves" value="1" {{ $forceShelves ? 'checked' : '' }}>

                <span>Tampilkan Shelf</span>
              </label>
              <span class="nb-k-muted-sm">Simpan preferensi</span>
            @endif
          </div>
      </div>
    @endif

    </form>
  </div>

  <div class="nb-k-sectionSpace"></div>

  @if($biblios->count() > 0)
    <div class="sr-only" role="status" aria-live="polite" aria-atomic="true">
      Menampilkan {{ number_format($fromResult, 0, ',', '.') }} sampai {{ number_format($toResult, 0, ',', '.') }} dari {{ number_format($totalResults, 0, ',', '.') }} hasil katalog.
    </div>
    <div class="nb-k-summary">
      <div class="left">
        <span>Menampilkan <b>{{ number_format($fromResult, 0, ',', '.') }}</b>-<b>{{ number_format($toResult, 0, ',', '.') }}</b> dari <b>{{ number_format($totalResults, 0, ',', '.') }}</b> hasil</span>
        @if($isPublic)
          <span class="nb-k-opac">OPAC Publik</span>
        @endif
      </div>
      <div class="right">
        <form method="GET" action="{{ route($indexRouteName) }}" class="nb-k-sortForm">
          @foreach(request()->except(['sort', 'rank', 'page']) as $k => $v)
            @if(is_array($v))
              @foreach($v as $vv)
                <input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">
              @endforeach
            @else
              <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endif
          @endforeach
          <select class="nb-field nb-k-sortSelect" name="sort" onchange="this.form.submit()">
            <option value="relevant" {{ $sort === 'relevant' ? 'selected' : '' }}>Urut: Relevan</option>
            <option value="latest" {{ $sort === 'latest' ? 'selected' : '' }}>Urut: Terbaru</option>
            <option value="popular" {{ $sort === 'popular' ? 'selected' : '' }}>Urut: Populer</option>
            <option value="available" {{ $sort === 'available' ? 'selected' : '' }}>Urut: Tersedia dulu</option>
          </select>
          @if($canPersonalRank)
            <select class="nb-field nb-k-sortSelect" name="rank" onchange="this.form.submit()">
              <option value="institution" {{ $rankMode === 'institution' ? 'selected' : '' }}>Ranking: Institusi</option>
              <option value="personal" {{ $rankMode === 'personal' ? 'selected' : '' }}>Ranking: Personal</option>
            </select>
          @else
            <input type="hidden" name="rank" value="{{ $rankMode }}">
          @endif
        </form>
      </div>
    </div>
    @if($didYouMean && trim((string) $didYouMean) !== '')
      <div class="nb-k-dym" role="status" aria-live="polite">
        <span class="nb-k-dym-label">Saran pencarian</span>
        <span class="nb-k-dym-text">Mungkin maksud Anda:</span>
        <a class="nb-k-dym-link" href="{{ route($indexRouteName, array_merge(request()->query(), ['q' => $didYouMean, 'page' => 1])) }}">
          {{ $didYouMean }}
        </a>
      </div>
    @endif
    <div id="nbFacetRailWrap">
      @include('katalog.partials.facets')
    </div>

    @if($canManage)
      <div class="nb-k-batchBar is-hidden" id="nbBatchBar" hidden aria-hidden="true">
        <div class="nb-k-batchLeft">
          <label class="nb-k-batchSelect">
            <input type="checkbox" id="nbBatchAll">
            <span>Pilih semua (halaman)</span>
          </label>
          <span class="nb-k-batchCount" id="nbBatchCount">0 terpilih</span>
          <span class="nb-k-batchHint">Tip: Shift+klik untuk pilih rentang. Ctrl+Shift+B fokus batch.</span>
        </div>
          <form class="nb-k-batchRight" id="nbBatchForm" method="POST" action="{{ route('katalog.bulkUpdate') }}">
            @csrf
            <input type="hidden" name="ids" id="nbBatchIds" value="">
            <div class="nb-k-batchFields">
              <div class="nb-k-batchRow">
                <input class="nb-field nb-k-batchField nb-k-batchSearch" type="text" id="nbBatchSearch" placeholder="Cari cepat di katalog..." autocomplete="off">
                <select class="nb-field nb-k-batchField" name="material_type">
                  <option value="">Set Jenis (opsional)</option>
                  @foreach($materialTypeOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                  @endforeach
                </select>
                <select class="nb-field nb-k-batchField" name="media_type">
                  <option value="">Set Media (opsional)</option>
                  @foreach($mediaTypeOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                  @endforeach
                </select>
                <select class="nb-field nb-k-batchField" name="language">
                  <option value="">Set Bahasa (opsional)</option>
                  @foreach($languageOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                  @endforeach
                </select>
              </div>
              <div class="nb-k-batchRow">
                <input class="nb-field nb-k-batchField" type="text" name="publisher" placeholder="Set Penerbit (opsional)">
                <input class="nb-field nb-k-batchField" type="text" name="ddc" placeholder="Set DDC (opsional)">
                <input class="nb-field nb-k-batchField" type="text" name="tags_text" list="nbTagOptions" placeholder="Tag (pisah koma)">
                <datalist id="nbTagOptions">
                  @foreach($tagOptions as $tag)
                    <option value="{{ $tag }}"></option>
                  @endforeach
                </datalist>
                <select class="nb-field nb-k-batchField" name="item_status">
                  <option value="">Set Status Item (opsional)</option>
                  @foreach($itemStatusOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                  @endforeach
                </select>
                <select class="nb-field nb-k-batchField" name="branch_id">
                  <option value="">Set Cabang (opsional)</option>
                  @foreach($branchOptions as $b)
                    <option value="{{ $b->id }}">{{ $b->name }}{{ $b->is_active ? '' : ' (nonaktif)' }}</option>
                  @endforeach
                </select>
                <select class="nb-field nb-k-batchField" name="shelf_id">
                  <option value="">Set Rak (opsional)</option>
                  @foreach($shelfOptions as $s)
                    <option value="{{ $s->id }}">
                      {{ trim(($s->branch_name ? $s->branch_name . ' â€¢ ' : '') . $s->name . ($s->code ? ' (' . $s->code . ')' : '') . ($s->is_active ? '' : ' (nonaktif)')) }}
                    </option>
                  @endforeach
                </select>
                <input class="nb-field nb-k-batchField" type="text" name="location_note" placeholder="Catatan lokasi (opsional)">
              </div>
            </div>
            <div class="nb-k-batchActions">
              <button class="nb-btn nb-btn-primary nb-k-batchApply" type="submit">Terapkan</button>
              <button class="nb-btn nb-k-batchUndo" type="button" id="nbBatchUndo">Undo batch terakhir</button>
              <button class="nb-btn" type="button" id="nbBatchClear">Bersihkan</button>
            </div>
          </form>
          <form id="nbBatchUndoForm" method="POST" action="{{ route('katalog.bulkUndo') }}" class="nb-k-batchUndoForm">
            @csrf
          </form>
        </div>
    @endif

    @if($showShelves && !$hasFilters && !$hasQuery)
      <div class="nb-k-quickGroup">
      <div class="nb-k-quick">
        <span class="nb-k-quickLabel">Format cepat:</span>
        @foreach($formatQuick as $fmt)
          <a class="nb-k-quickChip" href="{{ route($indexRouteName, ['media_type' => $fmt['value']]) }}" aria-label="Filter format {{ $fmt['label'] }}">
            <span class="nb-k-quickIcon">{{ $fmt['icon'] }}</span>
            {{ $fmt['label'] }}
          </a>
        @endforeach
      </div>
      </div>
    @endif

    <div class="nb-k-sectionSpace"></div>
  @endif

  {{-- FLASH --}}
  @if(session('success'))
    <div class="nb-card nb-alert" style="border:1px solid rgba(39,174,96,.25); background:rgba(39,174,96,.08);">
      <div class="h">Berhasil</div>
      <div class="m">{{ session('success') }}</div>
    </div>
    <div class="nb-k-gap-sm"></div>
  @endif

  @if(session('error'))
    <div class="nb-card nb-alert" style="border:1px solid rgba(220,38,38,.25); background:rgba(220,38,38,.06);">
      <div class="h">Gagal</div>
      <div class="m">{{ session('error') }}</div>
    </div>
    <div class="nb-k-gap-sm"></div>
  @endif

  <div id="nbSearchResults" data-next-url="{{ $biblios->nextPageUrl() }}">
  {{-- EMPTY --}}
  @if($biblios->count() === 0)
    <div class="nb-card nb-k-empty">
      <div class="nb-k-emptyRow">
        <div class="nb-k-emptyIcon">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/>
            <path d="M16.5 16.5L21 21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M8 11h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </div>
        <div>
        <div class="nb-k-emptyTitle">
          {{ $isPublic ? 'Katalog OPAC belum menampilkan judul ini' : 'Belum ketemu judulnya' }}
        </div>
        <div class="nb-k-emptyDesc">
          {{ $isPublic ? 'Coba kata kunci lain, atau lihat shelf populer untuk menemukan koleksi yang tersedia.' : 'Tidak ada hasil yang pas. Coba variasi kata atau longgarkan filter.' }}
        </div>
          <div class="nb-k-gap-sm"></div>
          <div class="nb-k-sub nb-k-mt-0">
            {{ $isPublic ? 'Coba ini:' : 'Saran cepat:' }}
          </div>
          <ul class="nb-k-emptyList">
            <li>Gunakan kata kunci yang lebih umum atau lebih pendek.</li>
            <li>Periksa ejaan atau singkatan.</li>
            <li>Kurangi filter (tahun, penerbit, subjek, pengarang).</li>
          </ul>
          <div class="nb-k-gap-sm"></div>
          <div class="nb-k-emptyActions">
            @if($hasFilters || $hasQuery)
              <a class="nb-btn" href="{{ route($indexRouteName) }}">Reset filter</a>
            @endif
            @if(!$isPublic && !$hasFilters && !$hasQuery && $canManage)
              <a class="nb-btn nb-btn-primary" href="{{ route('katalog.create') }}">Tambah bibliografi</a>
            @endif
            <a class="nb-btn nb-btn-primary" href="{{ route($indexRouteName, ['shelves' => 1]) }}">Lihat shelf populer</a>
          </div>
        </div>
      </div>
    </div>

    {{-- kalau kosong tapi filter aktif, tampilkan CTA reset --}}
    @if(count($activeFilters) > 0)
      <div class="nb-k-gap-sm"></div>
      <div class="nb-card nb-k-pad-14" style="border:1px solid rgba(30,136,229,.14); background:rgba(30,136,229,.06);">
        <div style="font-weight:600; letter-spacing:.1px; font-size:13px;">Hasil kosong karena filter aktif</div>
        <div class="nb-k-sub nb-k-mt-6">Coba reset filter atau kurangi kriteria.</div>
        <div class="nb-k-gap-sm"></div>
        <a class="nb-k-ibtn reset nb-k-ibtn-inline"
           href="{{ route($indexRouteName) }}" aria-label="Reset filter" title="Reset">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <path fill="currentColor" d="M12 6V3L8 7l4 4V8c2.76 0 5 2.24 5 5a5 5 0 0 1-8.66 3.54l-1.42 1.42A7 7 0 0 0 19 13c0-3.87-3.13-7-7-7Z"/>
          </svg>
          Reset Filter
        </a>
      </div>
    @endif

  @else

    @if(($trendingBooks->count() > 0 || $newArrivals->count() > 0))
      <div class="nb-k-shelves {{ $showShelves ? '' : 'is-hidden' }}">
      <div class="nb-card nb-k-shelf nb-k-shelf-pop">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
          <div>
            <div class="nb-k-shelfTitle">
              <span class="nb-k-shelfPill pop">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M13.5 2.5c.2 2.2-.7 3.7-2 5.2-1.1 1.3-1.9 2.6-1.7 4.3.2 1.9 1.8 3.5 4.2 3.5 3.2 0 5.5-2.6 5.5-5.8 0-3.9-2.3-5.7-6-7.2ZM9.1 9.6c-2.2 2.1-4.1 4.2-4.1 7 0 3.3 2.6 5.9 7 5.9 4.1 0 7-2.5 7-6.6-1.2 1.2-2.7 1.8-4.6 1.8-3.4 0-6-2.1-6.5-5.2-.3-1.8.1-3.4 1.2-5Z" fill="currentColor"/>
                </svg>
                Populer
              </span>
              Populer Saat Ini
              <span class="nb-k-shelfBadge">
                Per cabang: {{ !empty($activeBranchLabel) ? $activeBranchLabel : 'Global' }}
              </span>
            </div>
            <div class="nb-k-shelfSub">Judul yang paling banyak dilihat. Per cabang aktif.</div>
          </div>
          <div class="nb-k-shelfActions">
            <button type="button" class="nb-k-carouselBtn" data-carousel="nbPopularRow" data-dir="prev" aria-label="Sebelumnya">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
            <button type="button" class="nb-k-carouselBtn" data-carousel="nbPopularRow" data-dir="next" aria-label="Berikutnya">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="nb-k-shelfGrid nb-k-shelfRow" id="nbPopularRow">
          @foreach($trendingBooks as $t)
            @php
              $tCover = !empty($t->cover_path) ? asset('storage/'.$t->cover_path) : null;
              $tAuthors = $t->authors?->pluck('name')->take(2)->implode(', ') ?? '-';
              $tAvailable = (int)($t->available_items_count ?? 0);
              $tTotal = (int)($t->items_count ?? 0);
            @endphp
            <a class="nb-k-mini" href="{{ route($showRouteName, $t->id) }}">
              <div class="nb-k-miniCover">
                @if($tCover)
                  <img src="{{ $tCover }}" alt="Cover {{ $t->display_title ?? $t->title }}" loading="lazy" decoding="async">
                @else
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 4.2h10A2.2 2.2 0 0 1 18.2 6.4V20H7.4A1.4 1.4 0 0 0 6 21.4V4.2Z" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M6 18.8h12.2" stroke="currentColor" stroke-width="1.6" opacity=".9"/>
                  </svg>
                @endif
              </div>
              <div style="min-width:0;">
                <div class="nb-k-miniTitle">{!! $highlight($t->display_title ?? $t->title) !!}</div>
                <div class="nb-k-miniMeta">{{ $tAuthors }}</div>
                <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                  <span class="nb-k-miniAva">{{ $tAvailable }} / {{ $tTotal }} eksemplar</span>
                  <span class="nb-k-miniTag pop">Populer</span>
                </div>
              </div>
            </a>
          @endforeach
        </div>
      </div>

      <div class="nb-k-sectionSpace"></div>
      <hr class="nb-k-miniDivider">

      <div class="nb-card nb-k-shelf nb-k-shelf-new">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
          <div>
            <div class="nb-k-shelfTitle">
              <span class="nb-k-shelfPill new">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 3l2.2 4.5L19 9l-4.2 3.1 1.6 4.9L12 14.6 7.6 17 9.2 12 5 9l4.8-1.5L12 3Z" fill="currentColor"/>
                </svg>
                Terbaru
              </span>
              Koleksi Terbaru
              <span class="nb-k-shelfBadge nb-k-shelfBadge-latest">
                Per cabang: {{ !empty($activeBranchLabel) ? $activeBranchLabel : 'Global' }}
              </span>
            </div>
            <div class="nb-k-shelfSub">Judul yang baru ditambahkan. Per cabang aktif.</div>
          </div>
          <div class="nb-k-shelfActions">
            <button type="button" class="nb-k-carouselBtn" data-carousel="nbNewRow" data-dir="prev" aria-label="Sebelumnya">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
            <button type="button" class="nb-k-carouselBtn" data-carousel="nbNewRow" data-dir="next" aria-label="Berikutnya">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="nb-k-shelfGrid nb-k-shelfRow" id="nbNewRow">
          @foreach($newArrivals as $n)
            @php
              $nCover = !empty($n->cover_path) ? asset('storage/'.$n->cover_path) : null;
              $nAuthors = $n->authors?->pluck('name')->take(2)->implode(', ') ?? '-';
              $nAvailable = (int)($n->available_items_count ?? 0);
              $nTotal = (int)($n->items_count ?? 0);
            @endphp
            <a class="nb-k-mini" href="{{ route($showRouteName, $n->id) }}">
              <div class="nb-k-miniCover">
                @if($nCover)
                  <img src="{{ $nCover }}" alt="Cover {{ $n->display_title ?? $n->title }}" loading="lazy" decoding="async">
                @else
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 4.2h10A2.2 2.2 0 0 1 18.2 6.4V20H7.4A1.4 1.4 0 0 0 6 21.4V4.2Z" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M6 18.8h12.2" stroke="currentColor" stroke-width="1.6" opacity=".9"/>
                  </svg>
                @endif
              </div>
              <div style="min-width:0;">
                <div class="nb-k-miniTitle">{!! $highlight($n->display_title ?? $n->title) !!}</div>
                <div class="nb-k-miniMeta">{{ $nAuthors }}</div>
                <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                  <span class="nb-k-miniAva">{{ $nAvailable }} / {{ $nTotal }} eksemplar</span>
                  <span class="nb-k-miniTag new">Baru</span>
                </div>
              </div>
            </a>
          @endforeach
        </div>
      </div>

      <div class="nb-k-sectionSpace"></div>
      </div>
    @endif

    {{-- GRID LIST --}}
    <div class="nb-k-grid" id="nbKGrid">
      @foreach($biblios as $b)
        @php
          $authors = $b->authors->pluck('name')->take(3)->implode(', ');
          $authors = $authors !== '' ? $authors : '-';
          $authorsHighlighted = $highlight($authors);
          $titleHighlighted = $highlight($b->display_title ?? $b->title);
          $subtitleHighlighted = $highlight($b->subtitle);
          $summarySource = $b->general_note ?? $b->notes ?? $b->bibliography_note ?? $b->ai_summary ?? null;
          $summary = $excerpt($summarySource);
          $summaryHighlighted = $highlight($summary);
          $available = (int)($b->available_items_count ?? 0);
          $total = (int)($b->items_count ?? 0);
          $firstItem = $b->items->firstWhere('status', 'available') ?? $b->items->first();
          $branchName = $firstItem?->branch?->name ?? null;
          $shelfName = $firstItem?->shelf?->name ?? null;
          $itemStatus = $firstItem?->status ?? null;

          $avaClass = $available > 0 ? 'ok' : 'no';

          $coverUrl = !empty($b->cover_path) ? asset('storage/'.$b->cover_path) : null;
          $avaTitle = "Tersedia {$available} dari {$total} eksemplar";
          $format = strtolower(trim((string) ($b->material_type ?? $b->media_type ?? '')));
          $formatIcon = match (true) {
            str_contains($format, 'audio') => 'AUD',
            str_contains($format, 'video') => 'VID',
            str_contains($format, 'serial') => 'SER',
            str_contains($format, 'peta') => 'MAP',
            default => 'BK',
          };
        @endphp

        <div class="nb-card nb-k-item">
          <a href="{{ route($showRouteName, $b->id) }}" class="nb-k-click" aria-label="Buka detail {{ $b->display_title ?? $b->title }}">
            <div class="nb-k-top">
              <div class="nb-k-ico" title="{{ $b->display_title ?? $b->title }}">
                @if($coverUrl)
                  <img class="nb-k-thumb" src="{{ $coverUrl }}" alt="Cover: {{ $b->display_title ?? $b->title }}" loading="lazy" decoding="async">
                @else
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 3.8h10A2.2 2.2 0 0 1 18.2 6v14.2H7.6A1.6 1.6 0 0 0 6 21.8V3.8Z" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M6 19.6h12.2" stroke="currentColor" stroke-width="1.8" opacity=".9"/>
                  </svg>
                @endif
              </div>

              <div class="nb-k-main">
                <div class="nb-k-titleRow">
                  <div class="nb-k-biblioTitle">{!! $titleHighlighted !!}</div>

                  <span class="nb-k-avab {{ $avaClass }}"
                        title="{{ $avaTitle }}"
                        aria-label="{{ $avaTitle }}">
                    {{ $available }} / {{ $total }}
                    <span style="font-weight:600; opacity:.9;">eksemplar</span>
                  </span>
                </div>

                @if(!empty($b->subtitle))
                  <div class="nb-k-subtitle">{!! $subtitleHighlighted !!}</div>
                @endif

                <div class="nb-k-meta">
                  <span><b>Pengarang:</b> {!! $authorsHighlighted !!}</span>
                  @if(!empty($b->publisher))
                    <span>{{ $b->publisher }}</span>
                  @endif
                  @if(!empty($b->publish_year))
                    <span>{{ $b->publish_year }}</span>
                  @endif
                  @if($branchName)
                    <span>{{ $branchName }}</span>
                  @endif
                  @if($shelfName)
                    <span>{{ $shelfName }}</span>
                  @endif
                  @if($itemStatus)
                    <span>{{ ucfirst($itemStatus) }}</span>
                  @endif
                </div>

                @if($summary !== '')
                  <div class="nb-k-sub nb-k-mt-6">{!! $summaryHighlighted !!}</div>
                @endif

                <div class="nb-k-badges">
                  <span class="nb-k-status {{ $avaClass }}">
                    {{ $available > 0 ? 'Tersedia' : 'Tidak tersedia' }}
                  </span>
                  @if(!empty($format))
                    <span class="nb-k-format" title="Format: {{ $format }}">
                      <span class="nb-k-formatIcon">{{ $formatIcon }}</span>
                      {{ $format }}
                    </span>
                  @endif
                  @if($sort === 'latest')
                    <span class="nb-badge nb-badge-latest">Terbaru</span>
                  @elseif($sort === 'popular')
                    <span class="nb-badge nb-badge-popular">Populer</span>
                  @endif
                  @if(!empty($b->isbn))
                    <span class="nb-badge" title="ISBN: {{ $b->isbn }}">ISBN: {{ $b->isbn }}</span>
                  @endif
                  @if(!empty($b->ddc))
                    <span class="nb-badge nb-badge-blue" title="DDC: {{ $b->ddc }}">DDC: {{ $b->ddc }}</span>
                  @endif
                  @if(!empty($b->call_number))
                    <span class="nb-badge" title="Nomor Panggil: {{ $b->call_number }}">No. Panggil: {{ $b->call_number }}</span>
                  @endif
                  @if(!empty($b->material_type))
                    <span class="nb-badge" title="Jenis: {{ $b->material_type }}">Jenis: {{ $b->material_type }}</span>
                  @endif
                  @if(!empty($b->media_type))
                    <span class="nb-badge" title="Media: {{ $b->media_type }}">Media: {{ $b->media_type }}</span>
                  @endif
                  @if(!empty($b->language))
                    <span class="nb-badge" title="Bahasa: {{ $b->language }}">Bahasa: {{ $b->language }}</span>
                  @endif
        </div>
              </div>

              <div class="nb-k-arrow" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                  <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
            </div>
          </a>

          @if($canManage)
            <div class="nb-k-actionsBottom">
              <div class="nb-k-actionsLeft">
                <label class="nb-k-select" onclick="event.stopPropagation();">
                  <input type="checkbox" class="nb-k-selectInput" value="{{ $b->id }}" aria-label="Pilih {{ $b->display_title ?? $b->title }}">
                  <span>Pilih</span>
                </label>
              </div>
              <div class="nb-k-actionsRight">
                <a class="nb-k-miniBtn edit"
                   href="{{ route('katalog.edit', $b->id) }}"
                   aria-label="Edit {{ $b->display_title ?? $b->title }}"
                   title="Edit"
                   onclick="event.stopPropagation();">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path fill="currentColor" d="m14.06 9.02.92.92L6.92 18H6v-.92l8.06-8.06ZM17.66 3c-.25 0-.51.1-.7.29l-1.83 1.83 3.05 3.05 1.83-1.83c.39-.39.39-1.02 0-1.41l-1.83-1.83c-.2-.2-.45-.29-.7-.29ZM14.06 6.19 4 16.25V20h3.75L17.81 9.94l-3.75-3.75Z"/>
                  </svg>
                </a>

                <form method="POST"
                      action="{{ route('katalog.destroy', $b->id) }}"
                      class="nb-k-m-0"
                      onclick="event.stopPropagation();"
                      onsubmit="event.stopPropagation(); return confirm('Hapus bibliografi ini? Catatan: Tidak bisa dihapus jika masih punya eksemplar.');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="nb-k-miniBtn del" aria-label="Hapus {{ $b->display_title ?? $b->title }}" title="Hapus">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                      <path fill="currentColor" d="M9 3h6l1 2h5v2H3V5h5l1-2Zm1 7h2v9h-2v-9Zm4 0h2v9h-2v-9ZM7 10h2v9H7v-9Z"/>
                    </svg>
                  </button>
                </form>
              </div>
            </div>
          @endif
        </div>
      @endforeach
    </div>
    {{-- Pagination premium --}}
    @if($isPaginated)
      <div class="nb-k-gap-md"></div>

      <div class="nb-card nb-k-pad-12 nb-k-radius-18" id="nbPagination">
        <div class="nb-pg" aria-label="Navigasi halaman katalog">

          {{-- Prev --}}
          <div class="nb-pg-left">
            @if($p->onFirstPage())
              <span class="nb-pg-btn is-disabled" aria-disabled="true" title="Sebelumnya">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
                  <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span style="display:none;" class="hide-sm">Prev</span>
              </span>
            @else
              <a class="nb-pg-btn"
                 href="{{ $p->previousPageUrl() }}"
                 rel="prev"
                 aria-label="Halaman sebelumnya"
                 title="Sebelumnya">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
                  <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span style="display:none;" class="hide-sm">Prev</span>
              </a>
            @endif
          </div>

          {{-- Pages --}}
          <div class="nb-pg-pages">

            {{-- first --}}
            @if($start > 1)
              <a class="nb-pg-btn" href="{{ $pageUrl(1) }}" aria-label="Halaman 1" title="Halaman 1">1</a>
              @if($start > 2)
                <span class="nb-pg-ellipsis" aria-hidden="true">...</span>
              @endif
            @endif

            {{-- window --}}
            @foreach($pageNums as $num)
              @if($num === $cur)
                <span class="nb-pg-btn is-active" aria-current="page" title="Halaman {{ $num }}">{{ $num }}</span>
              @else
                <a class="nb-pg-btn" href="{{ $pageUrl($num) }}" aria-label="Halaman {{ $num }}" title="Halaman {{ $num }}">{{ $num }}</a>
              @endif
            @endforeach

            {{-- last --}}
            @if($end < $last)
              @if($end < $last - 1)
                <span class="nb-pg-ellipsis" aria-hidden="true">...</span>
              @endif
              <a class="nb-pg-btn" href="{{ $pageUrl($last) }}" aria-label="Halaman {{ $last }}" title="Halaman {{ $last }}">{{ $last }}</a>
            @endif

          </div>

          {{-- Next --}}
          <div class="nb-pg-right">
            @if($p->hasMorePages())
              <a class="nb-pg-btn"
                 href="{{ $p->nextPageUrl() }}"
                 rel="next"
                 aria-label="Halaman berikutnya"
                 title="Berikutnya">
                <span style="display:none;" class="hide-sm">Next</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
                  <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </a>
            @else
              <span class="nb-pg-btn is-disabled" aria-disabled="true" title="Berikutnya">
                <span style="display:none;" class="hide-sm">Next</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">
                  <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>
            @endif
          </div>

        </div>

        {{-- kecil: info halaman --}}
        <div class="nb-k-mt-8 nb-k-pageInfo">
          Halaman <span style="font-weight:900;">{{ $cur }}</span> dari <span style="font-weight:900;">{{ $last }}</span>
        </div>
        <div class="nb-k-pageTotal">Total data: {{ number_format($p->total(), 0, ',', '.') }}</div>
      </div>
    @endif

  @endif

  </div>
  <div id="nbInfiniteSentinel" aria-hidden="true"></div>

</div>

@if($canManage)
  <div class="nb-k-shortcuts is-hidden" id="nbShortcutModal" aria-hidden="true">
    <div class="nb-k-shortcuts-backdrop" data-nb-shortcut-close></div>
    <div class="nb-k-shortcuts-card" role="dialog" aria-modal="true" aria-labelledby="nbShortcutTitle">
      <div class="nb-k-shortcuts-head">
        <div class="nb-k-shortcuts-title" id="nbShortcutTitle">Shortcut Katalog</div>
        <button type="button" class="nb-k-shortcuts-close" data-nb-shortcut-close aria-label="Tutup">x</button>
      </div>
      <div class="nb-k-shortcuts-grid">
        <div class="nb-k-shortcuts-row">
          <div>
            <span class="nb-k-kbd">Ctrl</span> + <span class="nb-k-kbd">K</span>
          </div>
          <div>Fokus ke pencarian</div>
        </div>
        <div class="nb-k-shortcuts-row">
          <div>
            <span class="nb-k-kbd">/</span>
          </div>
          <div>Cari cepat dari halaman</div>
        </div>
        <div class="nb-k-shortcuts-row">
          <div>
            <span class="nb-k-kbd">Shift</span> + <span class="nb-k-kbd">?</span>
          </div>
          <div>Buka daftar shortcut</div>
        </div>
        <div class="nb-k-shortcuts-row">
          <div>
            <span class="nb-k-kbd">Ctrl</span> + <span class="nb-k-kbd">Shift</span> + <span class="nb-k-kbd">A</span>
          </div>
          <div>Toggle pilih semua di halaman</div>
        </div>
        <div class="nb-k-shortcuts-row">
          <div>
            <span class="nb-k-kbd">Ctrl</span> + <span class="nb-k-kbd">Shift</span> + <span class="nb-k-kbd">B</span>
          </div>
          <div>Fokus ke batch edit</div>
        </div>
        <div class="nb-k-shortcuts-row">
          <div>
            <span class="nb-k-kbd">Ctrl</span> + <span class="nb-k-kbd">Enter</span>
          </div>
          <div>Terapkan batch edit (saat terbuka)</div>
        </div>
        <div class="nb-k-shortcuts-row">
          <div>
            <span class="nb-k-kbd">Esc</span>
          </div>
          <div>Tutup modal atau bersihkan pilihan</div>
        </div>
      </div>
      <div class="nb-k-shortcuts-note">Tip: Shift+klik checkbox untuk memilih rentang.</div>
    </div>
  </div>

  <div class="nb-k-modal is-hidden" id="nbBatchConfirm" aria-hidden="true">
    <div class="nb-k-modalBackdrop" data-batch-close></div>
    <div class="nb-k-modalCard" role="dialog" aria-modal="true" aria-labelledby="nbBatchConfirmTitle">
      <div class="nb-k-modalHeader">
        <div>
          <div class="nb-k-modalTitle" id="nbBatchConfirmTitle">Konfirmasi Batch Update</div>
          <div class="nb-k-modalSub" id="nbBatchConfirmSub">Periksa perubahan sebelum menerapkan.</div>
        </div>
        <button class="nb-k-modalClose" type="button" aria-label="Tutup" data-batch-close>x</button>
      </div>
      <div class="nb-k-modalBody">
        <div class="nb-k-modalRow">
          <div class="nb-k-modalStat">
            <div class="label">Jumlah item</div>
            <div class="value" id="nbBatchConfirmCount">0</div>
          </div>
          <div class="nb-k-modalStat">
            <div class="label">Field diubah</div>
            <div class="value" id="nbBatchConfirmFields">-</div>
          </div>
        </div>
        <div class="nb-k-modalListWrap">
          <div class="nb-k-modalListTitle">Preview 10 item pertama</div>
          <ul class="nb-k-modalList" id="nbBatchConfirmList"></ul>
        </div>
      </div>
      <div class="nb-k-modalActions">
        <button class="nb-btn" type="button" data-batch-close>Batal</button>
        <button class="nb-btn nb-btn-primary" type="button" id="nbBatchConfirmApply">Terapkan</button>
      </div>
    </div>
  </div>
@endif

<script>
  (function(){
    const toggle = document.getElementById('toggleShelves');
    const shelves = document.querySelector('.nb-k-shelves');
    if (!toggle) return;
    const key = 'nb_opac_shelves';
    const stored = localStorage.getItem(key);
    const url = new URL(window.location.href);
    const hasParam = url.searchParams.get('shelves') === '1';

    if (stored === '1' && !hasParam && !toggle.checked) {
      toggle.checked = true;
      if (shelves) shelves.classList.remove('is-hidden');
      const nextUrl = new URL(window.location.href);
      nextUrl.searchParams.set('shelves', '1');
      window.history.replaceState({}, '', nextUrl);
    }

    toggle.addEventListener('change', function(){
      localStorage.setItem(key, this.checked ? '1' : '0');
      if (shelves) shelves.classList.toggle('is-hidden', !this.checked);
      const nextUrl = new URL(window.location.href);
      if (this.checked) {
        nextUrl.searchParams.set('shelves', '1');
      } else {
        nextUrl.searchParams.delete('shelves');
      }
      window.history.replaceState({}, '', nextUrl);
      fetch('{{ route('opac.preferences.shelves') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ enabled: this.checked ? '1' : '0' })
      }).catch(() => {});
    });
  })();
</script>

@if($canManage)
<script>
  document.addEventListener('DOMContentLoaded', function(){
    var lastIndex = null;
    var confirmPending = false;
    var batchSearchTimer = null;
    var previewUrl = @json(route('katalog.bulkPreview'));
    var csrfToken = @json(csrf_token());
    var shortcutToggle = document.getElementById('nbShortcutToggle');
    var shortcutModal = document.getElementById('nbShortcutModal');
    var shortcutClose = Array.prototype.slice.call(document.querySelectorAll('[data-nb-shortcut-close]'));

    var getCheckboxes = function () {
      return Array.prototype.slice.call(document.querySelectorAll('.nb-k-selectInput'));
    };

    var getBatchEls = function () {
      return {
        bar: document.getElementById('nbBatchBar'),
        countEl: document.getElementById('nbBatchCount'),
        idsInput: document.getElementById('nbBatchIds'),
        selectAll: document.getElementById('nbBatchAll'),
        clearBtn: document.getElementById('nbBatchClear'),
        batchForm: document.getElementById('nbBatchForm'),
        batchFields: Array.prototype.slice.call(document.querySelectorAll('#nbBatchForm .nb-k-batchField')),
        batchApply: document.querySelector('#nbBatchForm .nb-k-batchApply'),
        undoBtn: document.getElementById('nbBatchUndo'),
        undoForm: document.getElementById('nbBatchUndoForm'),
        searchInput: document.getElementById('nbBatchSearch')
      };
    };

    var setBatchDisabled = function (disabled) {
      var els = getBatchEls();
      if (!els.bar || !els.countEl || !els.idsInput) return;
      els.batchFields.forEach(function (el) { el.disabled = disabled; });
      if (els.batchApply) els.batchApply.disabled = disabled;
      els.bar.classList.toggle('is-disabled', disabled);
    };

    var refresh = function () {
      var els = getBatchEls();
      var checkboxes = getCheckboxes();
      if (!els.bar || !els.countEl || !els.idsInput) return;
      var selected = [];
      checkboxes.forEach(function (cb) {
        var card = cb.closest('.nb-k-item');
        if (card) card.classList.toggle('is-selected', cb.checked);
        if (cb.checked) selected.push(cb.value);
      });
      els.countEl.textContent = selected.length + ' terpilih';
      els.idsInput.value = selected.join(',');
      var isEmpty = selected.length === 0;
      els.bar.classList.toggle('is-hidden', isEmpty);
      if (isEmpty) {
        els.bar.setAttribute('hidden', 'hidden');
        els.bar.setAttribute('aria-hidden', 'true');
      } else {
        els.bar.removeAttribute('hidden');
        els.bar.setAttribute('aria-hidden', 'false');
      }
      setBatchDisabled(isEmpty);
      if (els.selectAll) {
        els.selectAll.checked = selected.length === checkboxes.length && checkboxes.length > 0;
      }
    };

    var openBatchModal = function (data) {
      var modal = document.getElementById('nbBatchConfirm');
      if (!modal) return;
      var countEl = document.getElementById('nbBatchConfirmCount');
      var fieldsEl = document.getElementById('nbBatchConfirmFields');
      var listEl = document.getElementById('nbBatchConfirmList');
      var subEl = document.getElementById('nbBatchConfirmSub');
      var applyBtn = document.getElementById('nbBatchConfirmApply');

      var count = data && typeof data.count === 'number' ? data.count : 0;
      var fields = (data && data.fields) ? data.fields : [];
      if (countEl) countEl.textContent = String(count);
      if (fieldsEl) fieldsEl.textContent = fields.length ? fields.join(', ') : '-';
      if (subEl) subEl.textContent = count > 0 ? ('Total ' + count + ' item akan diperbarui.') : 'Tidak ada item yang cocok.';
      if (listEl) {
        listEl.innerHTML = '';
        var items = (data && data.items) ? data.items : [];
        if (!items.length) {
          var li = document.createElement('li');
          li.textContent = 'Tidak ada preview item.';
          listEl.appendChild(li);
        } else {
          items.forEach(function (it) {
            var li = document.createElement('li');
            li.textContent = (it.title || '-') + (it.authors ? (' - ' + it.authors) : '');
            listEl.appendChild(li);
          });
        }
      }
      if (applyBtn) applyBtn.disabled = count <= 0;
      modal.classList.remove('is-hidden');
      modal.setAttribute('aria-hidden', 'false');
    };

    var closeBatchModal = function () {
      var modal = document.getElementById('nbBatchConfirm');
      if (!modal) return;
      modal.classList.add('is-hidden');
      modal.setAttribute('aria-hidden', 'true');
    };

    var setupBatchBar = function () {
      // Hard reset selection state on page load to avoid stale checked state from browser cache.
      var els = getBatchEls();
      getCheckboxes().forEach(function (cb) { cb.checked = false; });
      if (els && els.selectAll) {
        els.selectAll.checked = false;
      }
      refresh();
    };

    window.nbKatalogSetupBatch = setupBatchBar;
    setupBatchBar();
    window.addEventListener('pageshow', function () {
      // Handle bfcache restore (back/forward navigation) consistently.
      setupBatchBar();
    });

    document.addEventListener('click', function (e) {
      var cb = e.target.closest && e.target.closest('.nb-k-selectInput');
      if (cb) {
        var checkboxes = getCheckboxes();
        var idx = checkboxes.indexOf(cb);
        if (e.shiftKey && lastIndex !== null && idx !== -1) {
          var start = Math.min(lastIndex, idx);
          var end = Math.max(lastIndex, idx);
          for (var i = start; i <= end; i++) {
            checkboxes[i].checked = cb.checked;
          }
        }
        lastIndex = idx;
        refresh();
        return;
      }

      var clearBtn = e.target.closest && e.target.closest('#nbBatchClear');
      if (clearBtn) {
        getCheckboxes().forEach(function (box) { box.checked = false; });
        lastIndex = null;
        refresh();
        return;
      }

      var undoBtn = e.target.closest && e.target.closest('#nbBatchUndo');
      if (undoBtn) {
        var undoForm = document.getElementById('nbBatchUndoForm');
        if (undoForm && window.confirm('Batalkan batch update terakhir?')) {
          if (undoForm.requestSubmit) undoForm.requestSubmit();
          else undoForm.submit();
        }
        return;
      }

      var closeBtn = e.target.closest && e.target.closest('[data-batch-close]');
      if (closeBtn) {
        closeBatchModal();
      }
    });

    document.addEventListener('change', function (e) {
      if (e.target && e.target.id === 'nbBatchAll') {
        var checkboxes = getCheckboxes();
        checkboxes.forEach(function (cb) { cb.checked = e.target.checked; });
        lastIndex = null;
        refresh();
        return;
      }
      if (e.target && e.target.classList && e.target.classList.contains('nb-k-selectInput')) {
        refresh();
      }
    });

    document.addEventListener('input', function (e) {
      if (!e.target || e.target.id !== 'nbBatchSearch') return;
      var mainInput = document.getElementById('nbSearchInput');
      if (!mainInput) return;
      mainInput.value = e.target.value || '';
      if (batchSearchTimer) window.clearTimeout(batchSearchTimer);
      batchSearchTimer = window.setTimeout(function () {
        if (window.nbKatalogFetchResults) window.nbKatalogFetchResults();
      }, 320);
    });

    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || form.id !== 'nbBatchForm') return;
      if (confirmPending) {
        confirmPending = false;
        return;
      }
      e.preventDefault();
      var payload = new FormData(form);
      fetch(previewUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        body: payload
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { ok: res.ok, data: data };
          });
        })
        .then(function (resp) {
          if (!resp.ok) {
            var msg = (resp.data && resp.data.message) ? resp.data.message : 'Gagal menyiapkan preview batch.';
            window.alert(msg);
            return;
          }
          openBatchModal(resp.data || {});
        })
        .catch(function () {
          window.alert('Gagal menyiapkan preview batch.');
        });
    });

    var confirmApply = document.getElementById('nbBatchConfirmApply');
    if (confirmApply) {
      confirmApply.addEventListener('click', function () {
        var form = document.getElementById('nbBatchForm');
        if (!form) return;
        closeBatchModal();
        confirmPending = true;
        if (form.requestSubmit) form.requestSubmit();
        else form.submit();
      });
    }

    function openShortcuts(){
      if (!shortcutModal) return;
      shortcutModal.classList.remove('is-hidden');
      shortcutModal.setAttribute('aria-hidden', 'false');
      if (shortcutToggle) shortcutToggle.setAttribute('aria-expanded', 'true');
    }

    function closeShortcuts(){
      if (!shortcutModal) return;
      shortcutModal.classList.add('is-hidden');
      shortcutModal.setAttribute('aria-hidden', 'true');
      if (shortcutToggle) shortcutToggle.setAttribute('aria-expanded', 'false');
    }

    if (shortcutToggle) {
      shortcutToggle.addEventListener('click', function(){
        if (shortcutModal && shortcutModal.classList.contains('is-hidden')) {
          openShortcuts();
        } else {
          closeShortcuts();
        }
      });
    }
    if (shortcutClose.length) {
      shortcutClose.forEach(function(btn){
        btn.addEventListener('click', closeShortcuts);
      });
    }

    document.addEventListener('keydown', function(e){
      var key = (e.key || '').toLowerCase();
      var target = e.target;
      var isTyping = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable);
      var modalOpen = shortcutModal && !shortcutModal.classList.contains('is-hidden');
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && key === 'a') {
        e.preventDefault();
        var selectAll = document.getElementById('nbBatchAll');
        if (selectAll) {
          selectAll.checked = !selectAll.checked;
          getCheckboxes().forEach(function(cb){ cb.checked = selectAll.checked; });
          lastIndex = null;
          refresh();
        }
      }
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && key === 'b') {
        e.preventDefault();
        var bar = document.getElementById('nbBatchBar');
        if (bar && !bar.classList.contains('is-hidden')) {
          var firstField = bar.querySelector('.nb-k-batchField');
          if (firstField) firstField.focus();
        }
      }
      if ((e.ctrlKey || e.metaKey) && key === 'enter') {
        var bar2 = document.getElementById('nbBatchBar');
        if (bar2 && !bar2.classList.contains('is-hidden')) {
          e.preventDefault();
          var batchForm = document.getElementById('nbBatchForm');
          if (batchForm && batchForm.requestSubmit) batchForm.requestSubmit();
        }
      }
      if (!isTyping && key === '?') {
        e.preventDefault();
        openShortcuts();
      }
      if (key === 'escape') {
        if (modalOpen) {
          closeShortcuts();
          return;
        }
        closeBatchModal();
        getCheckboxes().forEach(function(cb){ cb.checked = false; });
        refresh();
      }
    });
  });
</script>
@endif
@endsection
