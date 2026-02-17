@php
  $indexRouteName = $indexRouteName ?? (request()->routeIs('opac.*') ? 'opac.index' : 'katalog.index');
  $authorFacets = $authorFacets ?? collect();
  $subjectFacets = $subjectFacets ?? collect();
  $publisherFacets = $publisherFacets ?? collect();
  $languageFacets = $languageFacets ?? collect();
  $materialTypeFacets = $materialTypeFacets ?? collect();
  $mediaTypeFacets = $mediaTypeFacets ?? collect();
  $yearFacets = $yearFacets ?? collect();
  $branchFacets = $branchFacets ?? collect();
  $availabilityFacets = $availabilityFacets ?? ['available' => 0, 'unavailable' => 0];

  $authorList = collect($authorList ?? [])->map(fn($v) => (int) $v)->filter(fn($v) => $v > 0)->values()->all();
  $subjectList = collect($subjectList ?? [])->map(fn($v) => (int) $v)->filter(fn($v) => $v > 0)->values()->all();
  $publisherList = collect($publisherList ?? [])->map(fn($v) => (string) $v)->filter()->values()->all();
  $languageList = collect($languageList ?? [])->map(fn($v) => (string) $v)->filter()->values()->all();
  $materialTypeList = collect($materialTypeList ?? [])->map(fn($v) => (string) $v)->filter()->values()->all();
  $mediaTypeList = collect($mediaTypeList ?? [])->map(fn($v) => (string) $v)->filter()->values()->all();
  $branchList = collect($branchList ?? [])->map(fn($v) => (int) $v)->filter(fn($v) => $v > 0)->values()->all();
  $onlyAvailable = (bool) ($onlyAvailable ?? false);

  $topYearFacets = collect($yearFacets)->take(6)->values();
  $topBranchFacets = collect($branchFacets)->take(6)->values();
  $topMaterialFacets = collect($materialTypeFacets)->take(6)->values();
  $topLanguageFacets = collect($languageFacets)->take(6)->values();
  $topMediaFacets = collect($mediaTypeFacets)->take(6)->values();

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

  $pageCollection = $biblios->getCollection();
  $pageAvailable = (int) $pageCollection->sum('available_items_count');
  $pageItems = (int) $pageCollection->sum('items_count');
  $totalResults = (int) ($biblios->total() ?? 0);
  $activeFilterCount = isset($activeFilterCount)
    ? (int) $activeFilterCount
    : (int) collect(request()->query())
      ->reject(fn($value, $key) => in_array((string) $key, [
        'page', 'ajax', 'facets_only', 'grid_only',
        'q', 'rank', 'sort',
        'qf_op', 'qf_exact',
      ], true))
      ->filter(function ($value, $key) {
        if (is_array($value)) {
          $clean = collect($value)
            ->map(fn($v) => trim((string) $v))
            ->filter(fn($v) => $v !== '')
            ->values();
          return $clean->isNotEmpty();
        }

        $raw = trim((string) $value);
        if ($raw === '' || $raw === '0') {
          return false;
        }

        return true;
      })
      ->count();

  $availableParams = request()->query();
  if ($onlyAvailable) {
    unset($availableParams['available']);
  } else {
    $availableParams['available'] = '1';
  }
  $availableParams['page'] = 1;
@endphp

<div class="nb-k-facetCompact">
  <div class="nb-k-facetMain">
    <div class="nb-k-facetRow">
      <span class="nb-k-facetLabel">Ketersediaan</span>
      <a class="nb-k-facetChip {{ $onlyAvailable ? 'is-active' : '' }}" href="{{ route($indexRouteName, $availableParams) }}">
        {{ $onlyAvailable ? 'Semua status' : 'Hanya tersedia' }}
      </a>
    </div>

    @if($topBranchFacets->isNotEmpty())
      <div class="nb-k-facetRow">
        <span class="nb-k-facetLabel">Cabang</span>
        @foreach($topBranchFacets->take(4) as $branchFacet)
          @php
            $branchIdFacet = (int) ($branchFacet->id ?? 0);
            $branchActive = in_array($branchIdFacet, array_map('intval', $branchList), true);
          @endphp
          <a class="nb-k-facetChip {{ $branchActive ? 'is-active' : '' }}" href="{{ $facetToggleUrl('branch', (string) $branchIdFacet) }}">
            {{ $branchFacet->name ?? '-' }} ({{ (int) ($branchFacet->total ?? 0) }})
          </a>
        @endforeach
      </div>
    @endif

    @if($topYearFacets->isNotEmpty())
      <div class="nb-k-facetRow">
        <span class="nb-k-facetLabel">Tahun</span>
        @foreach($topYearFacets->take(4) as $yrFacet)
          @php
            $yr = (string) ($yrFacet->label ?? $yrFacet);
            $yrTotal = (int) ($yrFacet->total ?? 0);
          @endphp
          <a class="nb-k-facetChip" href="{{ route($indexRouteName, array_merge(request()->query(), ['year' => $yr, 'page' => 1])) }}">
            {{ $yr }}@if($yrTotal > 0) ({{ $yrTotal }}) @endif
          </a>
        @endforeach
      </div>
    @endif

    @if($topMaterialFacets->isNotEmpty())
      <div class="nb-k-facetRow">
        <span class="nb-k-facetLabel">Jenis</span>
        @foreach($topMaterialFacets->take(4) as $matFacet)
          @php
            $mat = (string) ($matFacet->label ?? $matFacet);
            $matTotal = (int) ($matFacet->total ?? 0);
            $matActive = in_array($mat, array_map('strval', $materialTypeList), true);
          @endphp
          <a class="nb-k-facetChip {{ $matActive ? 'is-active' : '' }}" href="{{ $facetToggleUrl('material_type', $mat) }}">
            {{ $mat }}@if($matTotal > 0) ({{ $matTotal }}) @endif
          </a>
        @endforeach
      </div>
    @endif
  </div>

  <details class="nb-k-advFacet" {{ $activeFilterCount > 2 ? 'open' : '' }}>
    <summary>Filter lanjutan</summary>
    <div class="nb-k-facetAdvGrid">
      @if($authorFacets->isNotEmpty())
        <div class="nb-k-facetRow">
          <span class="nb-k-facetLabel">Pengarang</span>
          @foreach($authorFacets->take(6) as $a)
            @php $authorActive = in_array((int) $a->id, array_map('intval', $authorList), true); @endphp
            <a class="nb-k-facetChip {{ $authorActive ? 'is-active' : '' }}" href="{{ $facetToggleUrl('author', (string) $a->id) }}">{{ $a->name }} ({{ $a->total }})</a>
          @endforeach
        </div>
      @endif

      @if($subjectFacets->isNotEmpty())
        <div class="nb-k-facetRow">
          <span class="nb-k-facetLabel">Subjek</span>
          @foreach($subjectFacets->take(6) as $s)
            @php
              $subjectLabel = $s->term ?: $s->name;
              $subjectActive = in_array((int) $s->id, array_map('intval', $subjectList), true);
            @endphp
            <a class="nb-k-facetChip {{ $subjectActive ? 'is-active' : '' }}" href="{{ $facetToggleUrl('subject', (string) $s->id) }}">{{ $subjectLabel }} ({{ $s->total }})</a>
          @endforeach
        </div>
      @endif

      @if($topLanguageFacets->isNotEmpty())
        <div class="nb-k-facetRow">
          <span class="nb-k-facetLabel">Bahasa</span>
          @foreach($topLanguageFacets->take(6) as $langFacet)
            @php
              $lang = (string) ($langFacet->label ?? $langFacet);
              $langTotal = (int) ($langFacet->total ?? 0);
              $langActive = in_array($lang, array_map('strval', $languageList), true);
            @endphp
            <a class="nb-k-facetChip {{ $langActive ? 'is-active' : '' }}" href="{{ $facetToggleUrl('language', $lang) }}">{{ $lang }}@if($langTotal > 0) ({{ $langTotal }}) @endif</a>
          @endforeach
        </div>
      @endif

      @if($topMediaFacets->isNotEmpty())
        <div class="nb-k-facetRow">
          <span class="nb-k-facetLabel">Media</span>
          @foreach($topMediaFacets->take(6) as $mediaFacet)
            @php
              $media = (string) ($mediaFacet->label ?? $mediaFacet);
              $mediaTotal = (int) ($mediaFacet->total ?? 0);
              $mediaActive = in_array($media, array_map('strval', $mediaTypeList), true);
            @endphp
            <a class="nb-k-facetChip {{ $mediaActive ? 'is-active' : '' }}" href="{{ $facetToggleUrl('media_type', $media) }}">{{ $media }}@if($mediaTotal > 0) ({{ $mediaTotal }}) @endif</a>
          @endforeach
        </div>
      @endif

      @if($publisherFacets->isNotEmpty())
        <div class="nb-k-facetRow">
          <span class="nb-k-facetLabel">Penerbit</span>
          @foreach($publisherFacets->take(6) as $pub)
            @php $pubActive = in_array((string) $pub->publisher, array_map('strval', $publisherList), true); @endphp
            <a class="nb-k-facetChip {{ $pubActive ? 'is-active' : '' }}" href="{{ $facetToggleUrl('publisher', (string) $pub->publisher) }}">{{ $pub->publisher }} ({{ $pub->total }})</a>
          @endforeach
        </div>
      @endif
    </div>
  </details>

  <div class="nb-k-kpiMini">
    <span class="nb-k-kpiPill">Judul: <b>{{ number_format($totalResults, 0, ',', '.') }}</b></span>
    <span class="nb-k-kpiPill">Tersedia: <b>{{ number_format($pageAvailable, 0, ',', '.') }}/{{ number_format($pageItems, 0, ',', '.') }}</b></span>
    <span class="nb-k-kpiPill">Filter aktif: <b>{{ $activeFilterCount }}</b></span>
  </div>
</div>
