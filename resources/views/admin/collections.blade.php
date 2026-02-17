{{-- resources/views/admin/collections.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Kelola Koleksi - Admin NOTOBUKU')

@section('content')
@php
  $total = $books->total();
@endphp

<style>
  .nb-admin-collection{
    max-width: 1180px;
    margin: 0 auto;
    --nb-card: rgba(255,255,255,.92);
    --nb-ink: rgba(11,37,69,.94);
    --nb-muted: rgba(11,37,69,.6);
    --nb-border: rgba(148,163,184,.25);
    --nb-primary: #1f6feb;
    --nb-primary-soft: rgba(31,111,235,.12);
  }
  html.dark .nb-admin-collection{
    --nb-card: rgba(15,23,42,.65);
    --nb-ink: rgba(226,232,240,.95);
    --nb-muted: rgba(226,232,240,.68);
    --nb-border: rgba(148,163,184,.2);
  }
  .nb-collection-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    border:1px solid var(--nb-border);
    background: var(--nb-card);
    border-radius:18px;
    padding:16px;
  }
  .nb-collection-title{ font-size:18px; font-weight:700; color: var(--nb-ink); }
  .nb-collection-sub{ font-size:12.5px; color: var(--nb-muted); }
  .nb-collection-actions{ display:flex; gap:8px; flex-wrap:wrap; }
  .nb-btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 14px;
    border-radius:12px;
    border:1px solid var(--nb-border);
    background: var(--nb-card);
    color: var(--nb-ink);
    font-size:12.5px;
    font-weight:600;
    text-decoration:none;
    transition: box-shadow .12s ease, transform .06s ease;
  }
  .nb-btn.primary{
    background: linear-gradient(180deg, #1f6feb, #0b5cd6);
    color:#fff;
    border-color: rgba(31,111,235,.4);
    box-shadow: 0 10px 20px rgba(31,111,235,.2);
  }
  .nb-btn:hover{ box-shadow: 0 12px 22px rgba(15,23,42,.12); }
  .nb-btn:active{ transform: translateY(1px); }

  .nb-collection-filters{
    margin-top:12px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
  }
  .nb-input{
    height:36px;
    border-radius:12px;
    border:1px solid var(--nb-border);
    background: var(--nb-card);
    padding:0 12px;
    font-size:12.5px;
    color: var(--nb-ink);
    min-width:240px;
  }

  .nb-collection-table{
    margin-top:12px;
    border:1px solid var(--nb-border);
    background: var(--nb-card);
    border-radius:18px;
    overflow:hidden;
  }
  .nb-collection-table table{
    width:100%;
    border-collapse:collapse;
    font-size:12.5px;
  }
  .nb-collection-table th{
    text-align:left;
    padding:12px;
    color: var(--nb-muted);
    font-weight:700;
    background: rgba(148,163,184,.08);
  }
  .nb-collection-table td{
    padding:12px;
    border-top:1px solid var(--nb-border);
    vertical-align:top;
    color: var(--nb-ink);
  }
  .nb-cover{
    width:46px;
    height:62px;
    border-radius:10px;
    overflow:hidden;
    background: rgba(148,163,184,.2);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:11px;
    font-weight:700;
    color: var(--nb-muted);
  }
  .nb-title{ font-weight:700; color: var(--nb-ink); }
  .nb-meta{ font-size:11.5px; color: var(--nb-muted); margin-top:4px; }
  .nb-chip{
    display:inline-flex;
    align-items:center;
    gap:4px;
    padding:3px 8px;
    border-radius:999px;
    border:1px solid rgba(31,111,235,.2);
    background: var(--nb-primary-soft);
    color: var(--nb-primary);
    font-size:11px;
    font-weight:700;
  }
  .nb-actions{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
  }
  .nb-actions form{ display:inline; }
  .nb-btn.small{ padding:6px 10px; font-size:11.5px; }
  .nb-btn.danger{
    border-color: rgba(239,68,68,.4);
    color: #b91c1c;
    background: rgba(239,68,68,.08);
  }
  .nb-search-indicator{
    font-size:12px;
    color: var(--nb-muted);
    padding:6px 10px;
  }
  .nb-pagination{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-top:12px;
  }
  .nb-pagination .nb-btn{
    padding:6px 10px;
    font-size:11.5px;
  }
  .nb-pagination .nb-btn.is-active{
    background: var(--nb-primary-soft);
    color: var(--nb-primary);
    border-color: rgba(31,111,235,.3);
  }
  .nb-empty{
    padding:18px;
    text-align:center;
    color: var(--nb-muted);
    font-size:12.5px;
  }
</style>

<div class="nb-admin-collection">
  <div class="nb-collection-header">
    <div>
      <div class="nb-collection-title">Kelola Koleksi</div>
      <div class="nb-collection-sub">Total {{ number_format($total) }} judul di katalog.</div>
    </div>
    <div class="nb-collection-actions">
      <a class="nb-btn" href="{{ route('katalog.index') }}">Buka Katalog Lengkap</a>
      <a class="nb-btn" href="{{ route('admin.search_synonyms') }}">Kelola Sinonim</a>
      <a class="nb-btn" href="{{ route('admin.search_tuning') }}">Query Tuning</a>
      <a class="nb-btn" href="{{ route('admin.search_analytics') }}">Search Analytics</a>
      <a class="nb-btn primary" href="{{ route('katalog.create') }}">Tambah Koleksi</a>
    </div>
  </div>

  <form method="GET" class="nb-collection-filters" id="admin-quick-search">
    <input class="nb-input" id="admin-quick-search-input" type="text" name="q" placeholder="Cari judul, pengarang, ISBN, penerbit..." value="{{ $q }}" autocomplete="off">
    <button class="nb-btn" type="submit">Cari</button>
    @if($q !== '')
      <a class="nb-btn" href="{{ route('admin.koleksi') }}">Reset</a>
    @endif
    <span class="nb-search-indicator" id="admin-search-indicator" style="display:none;">Sedang mencari...</span>
  </form>

  <div class="nb-collection-table">
    @if($books->isEmpty())
      <div class="nb-empty">Belum ada koleksi yang cocok.</div>
    @else
      <table id="admin-collection-table">
        <thead>
          <tr>
            <th>Cover</th>
            <th>Judul</th>
            <th>Pengarang</th>
            <th>Terbit</th>
            <th>Stok</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody id="admin-collection-tbody">
          @foreach($books as $book)
            @php
              $authors = $book->authors->pluck('name')->filter()->take(2)->implode(', ');
              $authors = $authors !== '' ? $authors : '-';
              $year = $book->publish_year ?: '-';
              $publisher = $book->publisher ?: '-';
            @endphp
            <tr data-search="{{ strtolower($book->display_title.' '.$book->subtitle.' '.$authors.' '.$publisher.' '.$book->isbn) }}">
              <td>
                <div class="nb-cover">
                  @if(!empty($book->cover_path))
                    <img src="{{ asset('storage/'.$book->cover_path) }}" alt="Cover" class="h-full w-full object-cover">
                  @else
                    NB
                  @endif
                </div>
              </td>
              <td>
                <div class="nb-title">{{ $book->display_title }}</div>
                <div class="nb-meta">{{ $publisher }}</div>
                <div class="nb-meta">ISBN: {{ $book->isbn ?: '-' }}</div>
              </td>
              <td>{{ $authors }}</td>
              <td>{{ $year }}</td>
              <td>
                <span class="nb-chip">{{ $book->available_items_count ?? 0 }}/{{ $book->items_count ?? 0 }}</span>
              </td>
              <td>
                <div class="nb-actions">
                  <a class="nb-btn small" href="{{ route('katalog.show', $book->id) }}">Lihat</a>
                  <a class="nb-btn small" href="{{ route('katalog.edit', $book->id) }}">Edit</a>
                  <form method="POST" action="{{ route('katalog.destroy', $book->id) }}" onsubmit="return confirm('Hapus koleksi ini?');">
                    @csrf
                    @method('DELETE')
                    <button class="nb-btn small danger" type="submit">Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="mt-4" id="admin-pagination-server">
    {{ $books->links() }}
  </div>
  <div class="nb-pagination" id="admin-pagination-ajax" style="display:none;"></div>
</div>

<script>
  (() => {
    const input = document.getElementById('admin-quick-search-input');
    const tbody = document.getElementById('admin-collection-tbody');
    const indicator = document.getElementById('admin-search-indicator');
    const paginationAjax = document.getElementById('admin-pagination-ajax');
    const paginationServer = document.getElementById('admin-pagination-server');
    if (!input || !tbody) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let timer = null;
    let controller = null;
    let lastMeta = null;
    const debounceMs = 300;

    const renderRows = (items) => {
      if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="6"><div class="nb-empty">Belum ada koleksi yang cocok.</div></td></tr>';
        return;
      }

      tbody.innerHTML = items.map((book) => {
        const authors = (book.authors || []).join(', ') || '-';
        const year = book.publish_year || '-';
        const publisher = book.publisher || '-';
        const isbn = book.isbn || '-';
        const coverHtml = book.cover_url
          ? `<img src="${book.cover_url}" alt="Cover" class="h-full w-full object-cover">`
          : 'NB';
        return `
          <tr>
            <td><div class="nb-cover">${coverHtml}</div></td>
            <td>
              <div class="nb-title">${book.title || '-'}</div>
              <div class="nb-meta">${publisher}</div>
              <div class="nb-meta">ISBN: ${isbn}</div>
            </td>
            <td>${authors}</td>
            <td>${year}</td>
            <td><span class="nb-chip">${book.available_items_count ?? 0}/${book.items_count ?? 0}</span></td>
            <td>
              <div class="nb-actions">
                <a class="nb-btn small" href="${book.show_url}">Lihat</a>
                <a class="nb-btn small" href="${book.edit_url}">Edit</a>
                <form method="POST" action="${book.delete_url}" onsubmit="return confirm('Hapus koleksi ini?');">
                  <input type="hidden" name="_token" value="${csrf}">
                  <input type="hidden" name="_method" value="DELETE">
                  <button class="nb-btn small danger" type="submit">Hapus</button>
                </form>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    };

    const renderPagination = (meta) => {
      if (!paginationAjax || !meta) return;
      const current = meta.current_page || 1;
      const last = meta.last_page || 1;
      if (last <= 1) {
        paginationAjax.style.display = 'none';
        return;
      }

      const pages = [];
      const windowSize = 2;
      const start = Math.max(1, current - windowSize);
      const end = Math.min(last, current + windowSize);
      for (let i = start; i <= end; i++) pages.push(i);

      const btn = (label, page, disabled = false, active = false) => {
        const cls = `nb-btn${active ? ' is-active' : ''}`;
        return `<button class="${cls}" data-page="${page}" ${disabled ? 'disabled' : ''}>${label}</button>`;
      };

      paginationAjax.innerHTML = [
        btn('«', 1, current === 1),
        btn('‹', Math.max(1, current - 1), current === 1),
        ...pages.map((p) => btn(p, p, false, p === current)),
        btn('›', Math.min(last, current + 1), current === last),
        btn('»', last, current === last),
      ].join('');
      paginationAjax.style.display = 'flex';
    };

    const fetchSearch = (q, page = 1) => {
      if (controller) controller.abort();
      controller = new AbortController();
      const url = new URL(`{{ route('admin.koleksi.search') }}`, window.location.origin);
      if (q) url.searchParams.set('q', q);
      if (page) url.searchParams.set('page', page);

      if (indicator) indicator.style.display = 'inline-flex';

      fetch(url.toString(), { signal: controller.signal, headers: { 'Accept': 'application/json' } })
        .then((res) => res.ok ? res.json() : Promise.reject(res))
        .then((json) => {
          renderRows(json.data || []);
          lastMeta = json.meta || null;
          renderPagination(lastMeta);
          if (paginationServer) paginationServer.style.display = 'none';
        })
        .catch((err) => {
          if (err.name === 'AbortError') return;
        })
        .finally(() => {
          if (indicator) indicator.style.display = 'none';
        });
    };

    input.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        fetchSearch(input.value.trim(), 1);
      }, debounceMs);
    });

    if (paginationAjax) {
      paginationAjax.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLButtonElement)) return;
        const page = Number(target.dataset.page || 1);
        fetchSearch(input.value.trim(), page);
      });
    }
  })();
</script>

@endsection
