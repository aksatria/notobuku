@extends('layouts.notobuku')

@section('title', 'Sinonim Pencarian - Admin NOTOBUKU')

@section('content')
@php
  $total = $rows->total();
@endphp

<style>
  .nb-syn-wrap{
    max-width: 1100px;
    margin: 0 auto;
    --nb-card: rgba(255,255,255,.92);
    --nb-ink: rgba(11,37,69,.94);
    --nb-muted: rgba(11,37,69,.6);
    --nb-border: rgba(148,163,184,.25);
    --nb-primary: #1f6feb;
  }
  .nb-syn-card{
    background: var(--nb-card);
    border:1px solid var(--nb-border);
    border-radius:16px;
    padding:16px;
    margin-bottom:12px;
  }
  .nb-syn-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
  }
  .nb-syn-title{ font-weight:700; color: var(--nb-ink); font-size:16px; }
  .nb-syn-sub{ font-size:12.5px; color: var(--nb-muted); }
  .nb-syn-actions{ display:flex; gap:8px; flex-wrap:wrap; }
  .nb-btn{
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 12px; border-radius:12px; border:1px solid var(--nb-border);
    background: #fff; color: var(--nb-ink); font-size:12.5px; font-weight:600;
    text-decoration:none;
  }
  .nb-btn.primary{ background: linear-gradient(180deg,#1f6feb,#0b5cd6); color:#fff; border-color: rgba(31,111,235,.4); }
  .nb-input, .nb-select, .nb-textarea{
    border:1px solid var(--nb-border); border-radius:12px; padding:8px 10px; font-size:12.5px; color:var(--nb-ink);
    background:#fff;
  }
  .nb-input{ min-width:200px; }
  .nb-textarea{ min-height:70px; min-width:280px; }
  .nb-grid{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
  .nb-table{ width:100%; border-collapse:collapse; font-size:12.5px; }
  .nb-table th, .nb-table td{ padding:10px; border-top:1px solid var(--nb-border); text-align:left; }
  .nb-table th{ color: var(--nb-muted); font-weight:700; background: rgba(148,163,184,.08); }
  .nb-chip{ display:inline-flex; gap:6px; align-items:center; padding:4px 8px; border-radius:999px; background: rgba(31,111,235,.08); color: var(--nb-primary); font-weight:700; font-size:11px; }
  .nb-empty{ padding:14px; color:var(--nb-muted); text-align:center; }
  .nb-syn-sortbtn{
    display:inline-flex; align-items:center; gap:6px;
    background: transparent; border:none; padding:0; margin:0;
    font-weight:700; color: var(--nb-muted); cursor:pointer; font-size:12.5px;
  }
  .nb-syn-sortbtn:hover{ color: var(--nb-ink); }
</style>

<div class="nb-syn-wrap">
  <div class="nb-syn-card">
    <div class="nb-syn-header">
      <div>
        <div class="nb-syn-title">Kelola Sinonim Pencarian</div>
        <div class="nb-syn-sub">Total {{ number_format($total) }} sinonim tersimpan. Bisa per cabang.</div>
      </div>
      <div class="nb-syn-actions">
        <form method="POST" action="{{ route('admin.search_synonyms.sync') }}">
          @csrf
          <input type="hidden" name="limit" value="300">
          <input type="hidden" name="min" value="2">
          <input type="hidden" name="lev" value="2">
          <input type="hidden" name="prefix" value="3">
          <input type="hidden" name="aggressive" value="1">
          <button class="nb-btn" type="submit">Sinkron Otomatis</button>
        </form>
        <a class="nb-btn primary" href="{{ route('admin.koleksi') }}">Kembali</a>
      </div>
    </div>
  </div>

  <div class="nb-syn-card">
    <form method="GET" class="nb-grid">
      <input class="nb-input" type="text" name="q" placeholder="Cari istilah" value="{{ $q }}">
      <select class="nb-select" name="branch_id">
        <option value="">Semua cabang</option>
        @foreach($branches as $branch)
          <option value="{{ $branch->id }}" @selected((string)$branchId === (string)$branch->id)>{{ $branch->name }}</option>
        @endforeach
      </select>
      <select class="nb-select" name="status">
        <option value="">Semua status</option>
        <option value="pending" @selected(($statusFilter ?? '') === 'pending')>Pending</option>
        <option value="approved" @selected(($statusFilter ?? '') === 'approved')>Approved</option>
        <option value="rejected" @selected(($statusFilter ?? '') === 'rejected')>Rejected</option>
      </select>
      <button class="nb-btn" type="submit">Filter</button>
      @if($q || $branchId || ($statusFilter ?? ''))
        <a class="nb-btn" href="{{ route('admin.search_synonyms') }}">Reset</a>
      @endif
    </form>
  </div>

  <div class="nb-syn-card">
    <div class="nb-syn-header">
      <div>
        <div class="nb-syn-title">Zero-result Resolution Queue</div>
        <div class="nb-syn-sub">Query tanpa hasil yang perlu ditangani operator.</div>
      </div>
    </div>
    <table class="nb-table">
      <thead>
        <tr>
          <th>Query</th>
          <th>Frekuensi</th>
          <th>Terakhir</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse(($zeroQueue ?? collect()) as $z)
          <tr>
            <td>
              <div><b>{{ $z->query }}</b></div>
              @if(!empty($z->zero_resolution_note))
                <div class="nb-syn-sub">{{ $z->zero_resolution_note }}</div>
              @endif
              @if(!empty($z->zero_resolution_link))
                <div><a href="{{ $z->zero_resolution_link }}">Lihat sinonim terkait</a></div>
              @endif
            </td>
            <td>{{ number_format((int) ($z->search_count ?? 0), 0, ',', '.') }}</td>
            <td>{{ $z->last_searched_at ? \Illuminate\Support\Carbon::parse($z->last_searched_at)->format('d M Y H:i') : '-' }}</td>
            <td><span class="nb-chip">{{ $z->zero_result_status ?? 'open' }}</span></td>
            <td>
              @if(!empty($z->auto_suggestion_query))
                <div class="nb-syn-sub">Auto suggestion: <b>{{ $z->auto_suggestion_query }}</b> ({{ number_format((float) ($z->auto_suggestion_score ?? 0), 1) }}%)</div>
                <form method="POST" action="{{ route('admin.search_synonyms.zero_result.resolve', $z->id) }}" class="nb-grid" style="margin:6px 0;">
                  @csrf
                  <input type="hidden" name="status" value="resolved">
                  <input type="hidden" name="use_auto_suggestion" value="1">
                  <input class="nb-input" name="note" placeholder="catatan (opsional)" value="Resolved via auto-suggestion">
                  <button class="nb-btn primary" type="submit">Approve Suggestion</button>
                </form>
              @endif
              <form method="POST" action="{{ route('admin.search_synonyms.zero_result.resolve', $z->id) }}" class="nb-grid">
                @csrf
                <input type="hidden" name="status" value="resolved">
                <input class="nb-input" name="term" value="{{ $z->normalized_query ?? $z->query }}" placeholder="term sinonim">
                <input class="nb-input" name="synonyms" placeholder="contoh: {{ $z->query }}">
                <input class="nb-input" name="note" placeholder="catatan resolve (opsional)">
                <button class="nb-btn primary" type="submit">Resolve + Buat Sinonim</button>
              </form>
              <form method="POST" action="{{ route('admin.search_synonyms.zero_result.resolve', $z->id) }}" class="nb-grid" style="margin-top:6px;">
                @csrf
                <input type="hidden" name="status" value="ignored">
                <input type="hidden" name="note" value="Diabaikan operator.">
                <button class="nb-btn" type="submit">Ignore</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="nb-empty">Belum ada query zero-result yang perlu ditangani.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="nb-syn-card">
    <form method="POST" action="{{ route('admin.search_synonyms.store') }}" class="nb-grid">
      @csrf
      <input class="nb-input" type="text" name="term" placeholder="Istilah utama (contoh: ilkom)" value="{{ old('term', $prefillTerm ?? '') }}" required>
      <select class="nb-select" name="branch_id">
        <option value="">Semua cabang</option>
        @foreach($branches as $branch)
          <option value="{{ $branch->id }}">{{ $branch->name }}</option>
        @endforeach
      </select>
      <select class="nb-select" name="status">
        <option value="approved">Simpan sebagai approved</option>
        <option value="pending">Simpan sebagai pending</option>
      </select>
      <textarea class="nb-textarea" name="synonyms" placeholder="Sinonim (pisahkan dengan koma / baris baru)" required>{{ old('synonyms', $prefillSynonyms ?? '') }}</textarea>
      <button class="nb-btn primary" type="submit">Simpan Sinonim</button>
    </form>
    @error('synonyms')
      <div class="mt-2 text-sm text-red-600">{{ $message }}</div>
    @enderror
    @if(session('status'))
      <div class="mt-2 text-sm text-green-600">{{ session('status') }}</div>
    @endif
  </div>

  <div class="nb-syn-card">
    <form method="POST" action="{{ route('admin.search_synonyms.preview') }}" class="nb-grid" enctype="multipart/form-data">
      @csrf
      <input class="nb-input" type="file" name="csv_file" accept=".csv,text/csv" required>
      <select class="nb-select" name="branch_id">
        <option value="">Semua cabang</option>
        @foreach($branches as $branch)
          <option value="{{ $branch->id }}">{{ $branch->name }}</option>
        @endforeach
      </select>
      <button class="nb-btn primary" type="submit">Pratinjau CSV</button>
      <a class="nb-btn" href="{{ route('admin.search_synonyms.template') }}">Download Template</a>
      <span class="nb-syn-sub">Format: istilah, sinonim1; sinonim2</span>
    </form>
  </div>

  @if(session('synonym_import_preview'))
    @php
      $preview = session('synonym_import_preview');
      $previewRows = $preview['rows'] ?? [];
      $previewBranch = $preview['branch_id'] ?? null;
      $previewBranchName = $branches->firstWhere('id', $previewBranch)?->name ?? 'Semua';
      $previewErrors = $preview['errors'] ?? [];
      $dupEstimate = $preview['dup_estimate'] ?? 0;
      $dupTerms = $preview['dup_terms'] ?? [];
      $maxErr = 20;
      $countAll = count($previewRows);
      $countError = count(array_filter($previewRows, fn($r) => ($r['is_error'] ?? false)));
      $countDup = count(array_filter($previewRows, fn($r) => ($r['is_duplicate'] ?? false) && !($r['is_error'] ?? false)));
      $countCsvDup = count(array_filter($previewRows, function ($r) {
        if (empty($r['is_duplicate']) || !empty($r['is_error'])) return false;
        $reason = (string) ($r['duplicate_reason'] ?? '');
        return str_contains(mb_strtolower($reason), 'csv');
      }));
    @endphp
    <div class="nb-syn-card">
      <div class="nb-syn-header">
        <div>
          <div class="nb-syn-title">Pratinjau Impor</div>
          <div class="nb-syn-sub">Cabang: {{ $previewBranchName }} - {{ count($previewRows) }} baris - Perkiraan duplikat: {{ $dupEstimate }}</div>
        </div>
        <div class="nb-syn-actions">
          <a class="nb-btn" href="{{ route('admin.search_synonyms.preview_csv') }}">Ekspor Semua CSV</a>
          <a class="nb-btn" href="{{ route('admin.search_synonyms.errors') }}">Ekspor CSV Error</a>
          <a class="nb-btn" href="{{ route('admin.search_synonyms.dups') }}">Ekspor CSV Duplikat</a>
          <form method="POST" action="{{ route('admin.search_synonyms.confirm') }}">
            @csrf
            <label class="nb-syn-sub" style="display:flex;align-items:center;gap:6px;">
              <input type="checkbox" name="overwrite" value="1">
              Overwrite sinonim lama
            </label>
            <button class="nb-btn primary" type="submit">Konfirmasi Impor</button>
          </form>
          <form method="POST" action="{{ route('admin.search_synonyms.cancel') }}">
            @csrf
            <button class="nb-btn" type="submit">Batalkan Pratinjau</button>
          </form>
        </div>
      </div>
      @if(!empty($previewErrors))
        <div class="mt-3 text-sm text-red-600">
          <div class="font-semibold">Error format:</div>
          <ul>
            @foreach(array_slice($previewErrors, 0, $maxErr) as $err)
              <li>- {{ $err }}</li>
            @endforeach
          </ul>
          @if(count($previewErrors) > $maxErr)
            <div class="mt-1">... {{ count($previewErrors) - $maxErr }} error lainnya disembunyikan.</div>
          @endif
        </div>
      @endif
      @if(!empty($dupTerms))
        <div class="mt-2 text-sm text-orange-600">
          <div class="font-semibold">Duplikat terdeteksi:</div>
          <div>{{ implode(', ', array_slice($dupTerms, 0, 30)) }}@if(count($dupTerms) > 30)...@endif</div>
        </div>
      @endif
      <div class="mt-3 nb-grid">
        <button class="nb-btn" type="button" data-preview-filter="all">Semua <span class="nb-chip">{{ $countAll }}</span></button>
        <button class="nb-btn" type="button" data-preview-filter="error">Hanya Error <span class="nb-chip">{{ $countError }}</span></button>
        <button class="nb-btn" type="button" data-preview-filter="dup">Hanya Duplikat <span class="nb-chip">{{ $countDup }}</span></button>
        <span class="nb-syn-sub" style="margin-left:auto;">Duplikat di CSV: <span class="nb-chip">{{ $countCsvDup }}</span></span>
      </div>
      <select class="nb-select" id="nbPreviewSort" style="display:none;">
        <option value="line_asc">Nomor baris (A-Z)</option>
        <option value="line_desc">Nomor baris (Z-A)</option>
        <option value="term_asc">Istilah (A-Z)</option>
        <option value="term_desc">Istilah (Z-A)</option>
        <option value="status_asc">Status (OK -> Duplikat -> Error)</option>
        <option value="status_desc">Status (Error -> Duplikat -> OK)</option>
      </select>
      <div class="mt-3">
        <table class="nb-table">
          <thead>
            <tr>
              <th>
                <button type="button" class="nb-syn-sortbtn" data-sort-key="term">
                  Istilah <span class="nb-syn-sub" data-sort-indicator="term"></span>
                </button>
              </th>
              <th>Sinonim</th>
              <th>
                <button type="button" class="nb-syn-sortbtn" data-sort-key="status">
                  Status <span class="nb-syn-sub" data-sort-indicator="status"></span>
                </button>
              </th>
              <th>
                <button type="button" class="nb-syn-sortbtn" data-sort-key="line">
                  Baris <span class="nb-syn-sub" data-sort-indicator="line"></span>
                </button>
              </th>
            </tr>
          </thead>
          <tbody>
            @foreach($previewRows as $row)
              @php
                $isError = $row['is_error'] ?? false;
                $isDup = $row['is_duplicate'] ?? false;
                $status = $isError ? 'Error' : ($isDup ? 'Duplikat' : 'OK');
                $reason = $isError ? ($row['error_reason'] ?? 'Format / sinonim tidak valid') : ($isDup ? ($row['duplicate_reason'] ?? 'Duplikat') : '');
              @endphp
              <tr data-preview-row="{{ $isError ? 'error' : ($isDup ? 'dup' : 'ok') }}"
                  data-preview-term="{{ mb_strtolower((string) ($row['term'] ?? '')) }}"
                  data-preview-line="{{ (int) ($row['line'] ?? 0) }}"
                  data-preview-status="{{ $isError ? 2 : ($isDup ? 1 : 0) }}"
                  style="{{ $isError ? 'background: rgba(239,68,68,.08);' : ($isDup ? 'background: rgba(245,158,11,.10);' : '') }}">
                <td>
                  @if($isError)
                    <span class="nb-chip">Baris {{ $row['line'] ?? '-' }}</span>
                  @else
                    <span class="nb-chip">{{ $row['term'] }}</span>
                  @endif
                </td>
                <td>{{ implode(', ', $row['synonyms']) }}</td>
                <td>
                  <span class="nb-chip">{{ $status }}</span>
                  @if($reason)
                    <div class="nb-syn-sub">{{ $reason }}</div>
                  @endif
                </td>
                <td><span class="nb-chip">#{{ (int) ($row['line'] ?? 0) }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  <script>
    (() => {
      const btns = document.querySelectorAll('[data-preview-filter]');
      if (!btns.length) return;
      const rows = Array.from(document.querySelectorAll('tr[data-preview-row]'));
      const tbody = rows.length ? rows[0].parentElement : null;
      const apply = (mode) => {
        rows.forEach((row) => {
          const type = row.getAttribute('data-preview-row');
          const show = mode === 'all' || (mode === 'error' && type === 'error') || (mode === 'dup' && type === 'dup');
          row.style.display = show ? '' : 'none';
        });
      };
      const sortSelect = document.getElementById('nbPreviewSort');
      const sortButtons = Array.from(document.querySelectorAll('[data-sort-key]'));
      const sortRows = (mode) => {
        if (!tbody) return;
        const visible = rows.filter((r) => r.style.display !== 'none');
        const getTerm = (r) => r.getAttribute('data-preview-term') || '';
        const getLine = (r) => parseInt(r.getAttribute('data-preview-line') || '0', 10);
        const getStatus = (r) => parseInt(r.getAttribute('data-preview-status') || '0', 10);
        const dir = mode.endsWith('_desc') ? -1 : 1;
        let key = 'line';
        if (mode.startsWith('term')) key = 'term';
        if (mode.startsWith('status')) key = 'status';
        visible.sort((a, b) => {
          if (key === 'term') return getTerm(a).localeCompare(getTerm(b)) * dir;
          if (key === 'status') return (getStatus(a) - getStatus(b)) * dir;
          return (getLine(a) - getLine(b)) * dir;
        });
        visible.forEach((r) => tbody.appendChild(r));
      };
      btns.forEach((btn) => {
        btn.addEventListener('click', () => {
          apply(btn.getAttribute('data-preview-filter'));
          if (sortSelect) sortRows(sortSelect.value || 'line_asc');
        });
      });
      const indicators = Array.from(document.querySelectorAll('[data-sort-indicator]'));
      const updateIndicators = (mode) => {
        indicators.forEach((el) => { el.textContent = ''; });
        if (!mode) return;
        const dir = mode.endsWith('_desc') ? 'v' : '^';
        if (mode.startsWith('term')) {
          const el = document.querySelector('[data-sort-indicator="term"]');
          if (el) el.textContent = dir;
        } else if (mode.startsWith('status')) {
          const el = document.querySelector('[data-sort-indicator="status"]');
          if (el) el.textContent = dir;
        } else if (mode.startsWith('line')) {
          const el = document.querySelector('[data-sort-indicator="line"]');
          if (el) el.textContent = dir;
        }
      };
      if (sortSelect) {
        sortSelect.addEventListener('change', () => {
          sortRows(sortSelect.value);
          updateIndicators(sortSelect.value);
        });
      }
      const toggleMode = (current, key) => {
        if (!current || !current.startsWith(key)) return key + '_asc';
        return current.endsWith('_asc') ? key + '_desc' : key + '_asc';
      };
      sortButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
          const key = btn.getAttribute('data-sort-key');
          const next = toggleMode(sortSelect ? sortSelect.value : '', key);
          if (sortSelect) sortSelect.value = next;
          sortRows(next);
          updateIndicators(next);
        });
      });
      if (sortSelect) {
        sortRows(sortSelect.value || 'line_asc');
        updateIndicators(sortSelect.value || 'line_asc');
      }
    })();
  </script>

  <div class="nb-syn-card">
    @if($rows->isEmpty())
      <div class="nb-empty">Belum ada sinonim.</div>
    @else
      <table class="nb-table">
        <thead>
          <tr>
            <th>Istilah</th>
            <th>Sinonim</th>
            <th>Cabang</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $row)
            @php
              $synList = implode(', ', (array) json_decode((string) $row->synonyms, true));
              $branchName = $branches->firstWhere('id', $row->branch_id)?->name ?? 'Semua';
            @endphp
            <tr>
              <td><span class="nb-chip">{{ $row->term }}</span></td>
              <td>{{ $synList }}</td>
              <td>{{ $branchName }}</td>
              <td>
                <span class="nb-chip">{{ $row->status ?? 'approved' }}</span>
                @if(($row->status ?? '') === 'rejected' && !empty($row->rejection_note))
                  <div class="nb-syn-sub">{{ $row->rejection_note }}</div>
                @endif
              </td>
              <td>
                <div class="nb-grid">
                  @if(($row->status ?? 'approved') !== 'approved')
                    <form method="POST" action="{{ route('admin.search_synonyms.approve', $row->id) }}">
                      @csrf
                      <button class="nb-btn primary" type="submit">Approve</button>
                    </form>
                  @endif
                  @if(($row->status ?? '') !== 'rejected')
                    <form method="POST" action="{{ route('admin.search_synonyms.reject', $row->id) }}">
                      @csrf
                      <input type="hidden" name="note" value="Ditolak operator">
                      <button class="nb-btn" type="submit">Reject</button>
                    </form>
                  @endif
                  <form method="POST" action="{{ route('admin.search_synonyms.delete', $row->id) }}" onsubmit="return confirm('Hapus sinonim ini?');">
                    @csrf
                    @method('DELETE')
                    <button class="nb-btn" type="submit">Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div class="mt-4">
        {{ $rows->links() }}
      </div>
    @endif
  </div>
</div>
@endsection

