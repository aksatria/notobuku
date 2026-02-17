@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="rk-pager">
        <div class="rk-pager-meta">
            Menampilkan {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} dari {{ $paginator->total() }} data
        </div>

        <div class="rk-pager-track">
            @if ($paginator->onFirstPage())
                <span class="rk-page-btn is-disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">‹</span>
            @else
                <a class="rk-page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">‹</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="rk-page-btn is-disabled is-ellipsis" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="rk-page-btn is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="rk-page-btn" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="rk-page-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">›</a>
            @else
                <span class="rk-page-btn is-disabled" aria-disabled="true" aria-label="@lang('pagination.next')">›</span>
            @endif
        </div>
    </nav>
@endif

