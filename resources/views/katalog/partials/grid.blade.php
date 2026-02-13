{{-- Partial: grid list --}}
@php
  $highlight = $highlight ?? function ($text) {
    return e($text ?? '');
  };
  $excerpt = $excerpt ?? function ($text) {
    $text = trim((string) $text);
    if ($text === '') return '';
    return \Illuminate\Support\Str::limit(strip_tags($text), 140);
  };
@endphp

@if($biblios->count() > 0)
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
@endif
