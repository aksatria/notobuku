{{-- Partial: hasil pencarian katalog (untuk AJAX) --}}
@php
  $p = $biblios ?? null;
  $fromResult = $fromResult ?? (int) ($p?->firstItem() ?? 0);
  $toResult = $toResult ?? (int) ($p?->lastItem() ?? 0);
  $totalResults = $totalResults ?? (int) ($p?->total() ?? 0);
  $pageCollection = $pageCollection ?? ($p?->getCollection() ?? collect());
  $pageAvailable = $pageAvailable ?? (int) $pageCollection->sum('available_items_count');
  $pageItems = $pageItems ?? (int) $pageCollection->sum('items_count');
  $activeFilterCount = $activeFilterCount ?? 0;
  $sortLabel = $sortLabel ?? 'Relevan';
  $rankMode = $rankMode ?? 'institution';
  $indexRouteName = $indexRouteName ?? 'katalog.index';
  $isPublic = $isPublic ?? request()->routeIs('opac.*');
  $canPersonalRank = $canPersonalRank ?? (auth()->check() && !$isPublic);
  $hasFilters = $hasFilters ?? false;
  $hasQuery = $hasQuery ?? false;
  $canManage = $canManage ?? false;
  $quickAuthors = $quickAuthors ?? collect();
  $quickSubjects = $quickSubjects ?? collect();
  $quickPublishers = $quickPublishers ?? collect();
  $formatQuick = $formatQuick ?? [];
  $tagOptions = $tagOptions ?? [];
  $branchOptions = $branchOptions ?? collect();
  $shelfOptions = $shelfOptions ?? collect();
  $itemStatusOptions = $itemStatusOptions ?? [];
  $showShelves = $showShelves ?? false;
  $showDiscovery = $showDiscovery ?? false;
  $trendingBooks = $trendingBooks ?? collect();
  $newArrivals = $newArrivals ?? collect();
  $filterClearUrl = $filterClearUrl ?? function (string $key) use ($indexRouteName) {
    $params = request()->query();
    unset($params[$key]);
    if ($key === 'qf_field') {
      unset($params['qf_value'], $params['qf_op'], $params['qf_exact']);
    }
    return route($indexRouteName, $params);
  };
  $highlight = $highlight ?? function ($text) {
    return e($text ?? '');
  };
  $excerpt = $excerpt ?? function ($text) {
    $text = trim((string) $text);
    if ($text === '') return '';
    return \Illuminate\Support\Str::limit(strip_tags($text), 140);
  };
  $activeFilters = $activeFilters ?? [];
  $showFilterBar = $showFilterBar ?? (count($activeFilters) > 0 || $isPublic);
  $cur = $cur ?? (int) ($p?->currentPage() ?? 1);
  $last = $last ?? (int) ($p?->lastPage() ?? 1);
  $isPaginated = $isPaginated ?? ($last > 1);
  $pageUrl = $pageUrl ?? function (int $page) use ($p) {
    return $p?->url($page) ?? '#';
  };
  $window = $window ?? 2;
  $start = $start ?? max(1, $cur - $window);
  $end = $end ?? min($last, $cur + $window);
  if ($cur <= 3) { $start = 1; $end = min($last, 5); }
  if ($cur >= $last - 2) { $end = $last; $start = max(1, $last - 4); }
  $pageNums = $pageNums ?? range($start, $end);
@endphp

@if($biblios->count() > 0)
  <div class="nb-k-summary">
    <div class="left">
      <span>Menampilkan <b>{{ number_format($fromResult, 0, ',', '.') }}</b>-<b>{{ number_format($toResult, 0, ',', '.') }}</b> dari <b>{{ number_format($totalResults, 0, ',', '.') }}</b> hasil</span>
      @if($isPublic)
        <span class="nb-k-opac">OPAC Publik</span>
      @endif
    </div>
    <div class="right">
      <span class="nb-k-muted-md">Urut:</span>
      <span class="nb-k-chip nb-k-m-0">{{ $sortLabel }}</span>
      @if($canPersonalRank)
        <div class="nb-k-rankToggle" title="Mode ranking">
          <a class="nb-k-rankBtn {{ $rankMode === 'institution' ? 'is-active' : '' }}"
             href="{{ route($indexRouteName, array_merge(request()->query(), ['rank' => 'institution'])) }}">
            Institusi
          </a>
          <a class="nb-k-rankBtn {{ $rankMode === 'personal' ? 'is-active' : '' }}"
             href="{{ route($indexRouteName, array_merge(request()->query(), ['rank' => 'personal'])) }}">
            Personal
          </a>
        </div>
      @endif
    </div>
  </div>
  <div class="nb-k-insights">
    <div class="nb-k-insight tone-blue">
      <div class="label">Judul Terindeks</div>
      <div class="value">{{ number_format($totalResults, 0, ',', '.') }}</div>
      <div class="meta">Total di katalog</div>
    </div>
    <div class="nb-k-insight tone-green">
      <div class="label">Eksemplar Tersedia</div>
      <div class="value">{{ number_format($pageAvailable, 0, ',', '.') }} / {{ number_format($pageItems, 0, ',', '.') }}</div>
      <div class="meta">Tersedia / total di halaman ini</div>
    </div>
    <div class="nb-k-insight tone-indigo">
      <div class="label">Filter Aktif</div>
      <div class="value">{{ $activeFilterCount }}</div>
      <div class="meta">{{ $activeFilterCount > 0 ? 'Klik chip untuk hapus' : 'Belum ada filter' }}</div>
    </div>
  </div>

  @if($canManage)
    <div class="nb-k-batchBar is-hidden" id="nbBatchBar">
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
                  {{ trim(($s->branch_name ? $s->branch_name . ' - ' : '') . $s->name . ($s->code ? ' (' . $s->code . ')' : '') . ($s->is_active ? '' : ' (nonaktif)')) }}
                </option>
              @endforeach
            </select>
            <input class="nb-field nb-k-batchField" type="text" name="location_note" placeholder="Catatan lokasi (opsional)">
          </div>
        </div></div>
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

  @if(!$hasFilters && !$hasQuery)
    <div class="nb-k-quickGroup">
    <div class="nb-k-quick">
      @if($quickAuthors->count() > 0)
        <span class="nb-k-quickLabel">Pengarang populer:</span>
        @foreach($quickAuthors as $qa)
          <a class="nb-k-quickChip" href="{{ route($indexRouteName, ['author' => $qa->id]) }}" aria-label="Filter pengarang {{ $qa->name }}">
            {{ $qa->name }} ({{ $qa->total }})
          </a>
        @endforeach
      @endif
    </div>
    <div class="nb-k-quick">
      @if($quickSubjects->count() > 0)
        <span class="nb-k-quickLabel">Subjek populer:</span>
        @foreach($quickSubjects as $qs)
          <a class="nb-k-quickChip" href="{{ route($indexRouteName, ['subject' => $qs->id]) }}" aria-label="Filter subjek {{ $qs->term ?? $qs->name }}">
            {{ $qs->term ?? $qs->name }} ({{ $qs->total }})
          </a>
        @endforeach
      @endif
    </div>
    <div class="nb-k-quick">
      @if($quickPublishers->count() > 0)
        <span class="nb-k-quickLabel">Penerbit populer:</span>
        @foreach($quickPublishers as $qp)
          <a class="nb-k-quickChip" href="{{ route($indexRouteName, ['publisher' => $qp->publisher]) }}" aria-label="Filter penerbit {{ $qp->publisher }}">
            {{ $qp->publisher }} ({{ $qp->total }})
          </a>
        @endforeach
      @endif
    </div>
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
    <div class="nb-card nb-k-shelf">
      <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
        <div>
          <div class="nb-k-shelfTitle">
            Populer Saat Ini
            <span class="nb-k-shelfBadge">
              Per cabang: {{ !empty($activeBranchLabel) ? $activeBranchLabel : 'Global' }}
            </span>
          </div>
          <div class="nb-k-shelfSub">Judul yang paling banyak eksemplarnya.</div>
        </div>
        <a class="nb-k-ibtn reset nb-k-ibtn-pill"
           href="{{ route($indexRouteName, ['sort' => 'popular', 'shelves' => 1]) }}" title="Lihat semua populer">
          <span aria-hidden="true" style="display:inline-flex;width:16px;height:16px;align-items:center;justify-content:center;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
              <path d="M5 12h14M13 5l6 7-6 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          Lihat Semua
        </a>
      </div>
      <div class="nb-k-shelfGrid">
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

    <div class="nb-card nb-k-shelf">
      <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
        <div>
          <div class="nb-k-shelfTitle">
            Koleksi Terbaru
            <span class="nb-k-shelfBadge nb-k-shelfBadge-latest">
              Per cabang: {{ !empty($activeBranchLabel) ? $activeBranchLabel : 'Global' }}
            </span>
          </div>
          <div class="nb-k-shelfSub">Judul yang baru ditambahkan.</div>
        </div>
        <a class="nb-k-ibtn reset nb-k-ibtn-pill"
           href="{{ route($indexRouteName, ['sort' => 'latest', 'shelves' => 1]) }}" title="Lihat semua terbaru">
          <span aria-hidden="true" style="display:inline-flex;width:16px;height:16px;align-items:center;justify-content:center;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
              <path d="M5 12h14M13 5l6 7-6 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          Lihat Semua
        </a>
      </div>
      <div class="nb-k-shelfGrid">
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
              <a class="nb-k-miniBtn edit" href="{{ route('katalog.edit', $b->id) }}" aria-label="Edit {{ $b->display_title ?? $b->title }}" title="Edit">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                  <path fill="currentColor" d="m14 3 7 7-10 10H4v-7L14 3Zm-2 2L6 11v4h4l6-6-4-4Z"/>
                </svg>
              </a>
              <form method="POST" action="{{ route('katalog.destroy', $b->id) }}" onsubmit="return confirm('Hapus bibliografi ini?')">
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

