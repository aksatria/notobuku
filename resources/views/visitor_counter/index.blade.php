@extends('layouts.notobuku')

@section('title', 'Pencatatan Masuk/Keluar Pengunjung - NOTOBUKU')

@section('content')
@php
  $rows = $rows ?? null;
  $branches = $branches ?? collect();
  $date = (string) ($date ?? now()->toDateString());
  $branchId = (string) ($branchId ?? '');
  $keyword = trim((string) ($keyword ?? ''));
  $activeOnly = (bool) ($activeOnly ?? false);
  $undoReady = (bool) ($undoReady ?? false);
  $datePreset = (string) ($datePreset ?? 'custom');
  $perPage = (int) ($perPage ?? 20);
  $stats = $stats ?? ['total' => 0, 'member' => 0, 'non_member' => 0, 'active_inside' => 0];
  $auditRows = $auditRows ?? collect();
  $auditStats = $auditStats ?? ['checkin' => 0, 'checkout' => 0, 'undo' => 0];
  $auditAction = (string) ($auditAction ?? '');
  $auditRole = (string) ($auditRole ?? '');
  $auditKeyword = trim((string) ($auditKeyword ?? ''));
  $auditSort = (string) ($auditSort ?? 'latest');
  $auditPerPage = (int) ($auditPerPage ?? 15);
  $allowedAuditActions = $allowedAuditActions ?? [];
  $allowedAuditRoles = $allowedAuditRoles ?? [];
  $allowedAuditSorts = $allowedAuditSorts ?? ['latest', 'oldest'];
  $auditActionLabels = $auditActionLabels ?? [];
  $auditRoleLabels = $auditRoleLabels ?? [];
  $todayLabel = \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, d F Y');
  $oldVisitorType = old('visitor_type', 'member');
@endphp

@include('visitor_counter._styles')



<div class="v2-wrap">
  <section class="v2-surface v2-head">
    <div>
      <div class="v2-title">Pencatatan Masuk/Keluar Pengunjung</div>
      <div class="v2-sub">Log kunjungan di lokasi - {{ $todayLabel }}</div>
    </div>
    <div class="v2-role">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="v2-role-icon">
        <use href="#nb-icon-users"></use>
      </svg>
      Admin/Petugas
    </div>
  </section>

  <section class="v2-kpi">
    <div class="v2-kpi-card k-blue">
      <div class="v2-kpi-label">Total Kunjungan</div>
      <div class="v2-kpi-value">{{ number_format((int) $stats['total']) }}</div>
      <div class="v2-kpi-note">Semua kunjungan hari ini</div>
    </div>
    <div class="v2-kpi-card k-indigo">
      <div class="v2-kpi-label">Anggota</div>
      <div class="v2-kpi-value">{{ number_format((int) $stats['member']) }}</div>
      <div class="v2-kpi-note">Kunjungan anggota</div>
    </div>
    <div class="v2-kpi-card k-teal">
      <div class="v2-kpi-label">Non-Anggota</div>
      <div class="v2-kpi-value">{{ number_format((int) $stats['non_member']) }}</div>
      <div class="v2-kpi-note">Pengunjung umum</div>
    </div>
    <div class="v2-kpi-card k-green">
      <div class="v2-kpi-label">Masih di Tempat</div>
      <div class="v2-kpi-value">{{ number_format((int) $stats['active_inside']) }}</div>
      <div class="v2-kpi-note">Belum keluar</div>
    </div>
  </section>

  <section class="v2-layout">
    <div class="v2-surface">
      <div class="v2-block-title">Daftar Kunjungan</div>
      <div class="v2-block-sub">Data kunjungan harian sesuai filter aktif.</div>
      <div class="v2-list-tools">
        <label class="v2-select">
          <input type="checkbox" id="vcSelectAll">
          Pilih semua baris
        </label>
        <label class="v2-autorefresh">
          <input type="checkbox" id="vcAutoRefreshToggle" checked>
          Muat ulang otomatis 30 dtk
        </label>
        <div class="v2-refresh-status" id="vcRefreshStatus">Pembaruan terakhir: --:--:--</div>
        <div class="v2-selected-status" id="vcSelectedStatus">Terpilih: <strong>0</strong> baris</div>
        <button class="nb-btn" type="button" id="vcSelectPageBtn">Pilih semua di halaman ini</button>
        <button class="nb-btn" type="button" id="vcSelectActiveBtn">Pilih semua belum keluar</button>
        <button class="nb-btn" type="button" id="vcSelectUndoableBtn">Pilih semua yang bisa dibatalkan</button>
        <button class="nb-btn" type="button" id="vcClearSelectionBtn">Bersihkan Pilihan</button>
        <div class="v2-shortcut-hint">
          Shortcut: <kbd>Ctrl</kbd> + <kbd>A</kbd> pilih belum keluar, <kbd>Esc</kbd> bersihkan pilihan.
        </div>
        <a
          class="nb-btn"
          href="{{ route('visitor_counter.export_csv', ['date' => $date, 'preset' => $datePreset !== 'custom' ? $datePreset : null, 'branch_id' => $branchId !== '' ? $branchId : null, 'q' => $keyword !== '' ? $keyword : null, 'active_only' => $activeOnly ? 1 : null, 'undo_ready' => $undoReady ? 1 : null]) }}"
        >Unduh CSV</a>
        <form method="POST" action="{{ route('visitor_counter.checkout_selected') }}" id="vcCheckoutSelectedForm" onsubmit="return confirm('Catat keluar baris yang dipilih?');">
          @csrf
          <input type="hidden" name="date" value="{{ $date }}">
          <input type="hidden" name="preset" value="{{ $datePreset }}">
          <input type="hidden" name="branch_id" value="{{ $branchId }}">
          <input type="hidden" name="q" value="{{ $keyword }}">
          <input type="hidden" name="per_page" value="{{ $perPage }}">
          @if($activeOnly)
            <input type="hidden" name="active_only" value="1">
          @endif
          @if($undoReady)
            <input type="hidden" name="undo_ready" value="1">
          @endif
          <button class="nb-btn is-disabled" type="submit" id="vcCheckoutSelectedBtn" disabled>Keluar Terpilih</button>
        </form>
        <form method="POST" action="{{ route('visitor_counter.undo_selected') }}" id="vcUndoSelectedForm" onsubmit="return confirm('Batalkan keluar untuk baris terpilih? Hanya berlaku 5 menit setelah keluar.');">
          @csrf
          <input type="hidden" name="date" value="{{ $date }}">
          <input type="hidden" name="preset" value="{{ $datePreset }}">
          <input type="hidden" name="branch_id" value="{{ $branchId }}">
          <input type="hidden" name="q" value="{{ $keyword }}">
          <input type="hidden" name="per_page" value="{{ $perPage }}">
          @if($activeOnly)
            <input type="hidden" name="active_only" value="1">
          @endif
          @if($undoReady)
            <input type="hidden" name="undo_ready" value="1">
          @endif
          <button class="nb-btn is-disabled" type="submit" id="vcUndoSelectedBtn" disabled>Batalkan Terpilih</button>
        </form>
        <form method="POST" action="{{ route('visitor_counter.checkout_bulk') }}" onsubmit="return confirm('Catat keluar semua pengunjung aktif sesuai filter saat ini?');">
          @csrf
          <input type="hidden" name="date" value="{{ $date }}">
          <input type="hidden" name="preset" value="{{ $datePreset }}">
          <input type="hidden" name="branch_id" value="{{ $branchId }}">
          <input type="hidden" name="q" value="{{ $keyword }}">
          <input type="hidden" name="per_page" value="{{ $perPage }}">
          @if($undoReady)
            <input type="hidden" name="undo_ready" value="1">
          @endif
          <button class="nb-btn" type="submit">Keluar Semua Aktif</button>
        </form>
      </div>
      @php
        $bulkResult = session('vc_bulk_result');
        $bulkOp = is_array($bulkResult) ? (string) ($bulkResult['operation'] ?? '') : '';
        $bulkLabel = match ($bulkOp) {
          'checkout_bulk' => 'Ringkasan Keluar Massal',
          'checkout_selected' => 'Ringkasan Keluar Terpilih',
          'undo_selected' => 'Ringkasan Pembatalan Terpilih',
          default => 'Ringkasan Aksi Massal',
        };
      @endphp
      @if(is_array($bulkResult))
        <div class="v2-bulk-result">
          <div class="v2-bulk-title">{{ $bulkLabel }}</div>
          <div class="v2-bulk-chips">
            <span class="v2-bulk-chip">Dipilih<strong>{{ (int) ($bulkResult['selected_count'] ?? 0) }}</strong></span>
            <span class="v2-bulk-chip">Berhasil<strong>{{ (int) ($bulkResult['updated_count'] ?? 0) }}</strong></span>
            <span class="v2-bulk-chip">Skip<strong>{{ (int) ($bulkResult['skipped_count'] ?? 0) }}</strong></span>
            @if(((int) ($bulkResult['denied_count'] ?? 0)) > 0)
              <span class="v2-bulk-chip">Ditolak<strong>{{ (int) ($bulkResult['denied_count'] ?? 0) }}</strong></span>
            @endif
          </div>
        </div>
      @endif

      @if(!$rows || $rows->count() === 0)
        <div class="v2-empty">Belum ada data kunjungan untuk filter saat ini.</div>
      @else
        <div class="v2-list">
          @foreach($rows as $r)
            @php
              $canUndo = false;
              $undoHint = '';
              if ($r->checkout_at) {
                $checkoutAt = \Illuminate\Support\Carbon::parse($r->checkout_at);
                $remainingSeconds = max(0, (5 * 60) - $checkoutAt->diffInSeconds(now()));
                $canUndo = $remainingSeconds > 0;
                if ($canUndo) {
                  $undoHint = 'Sisa pembatalan ' . max(1, (int) ceil($remainingSeconds / 60)) . ' menit';
                } else {
                  $undoHint = 'Batas pembatalan lewat';
                }
              }
            @endphp
            <article class="v2-item">
              <div>
                <label class="v2-row-pick">
                  <input type="checkbox" class="vc-row-check" value="{{ $r->id }}" data-undoable="{{ $canUndo ? '1' : '0' }}" data-active="{{ !$r->checkout_at ? '1' : '0' }}">
                  Pilih
                </label>
                <div class="v2-time">{{ $r->checkin_at ? \Illuminate\Support\Carbon::parse($r->checkin_at)->format('H:i') : '-' }}</div>
                <div class="v2-time-sub">Keluar: {{ $r->checkout_at ? \Illuminate\Support\Carbon::parse($r->checkout_at)->format('H:i') : '-' }}</div>
                @if(!$r->checkout_at)
                  <span class="v2-state is-active">Masih di tempat</span>
                @elseif($canUndo)
                  <span class="v2-state is-undoable">Bisa dibatalkan</span>
                @else
                  <span class="v2-state is-expired">Batas pembatalan lewat</span>
                @endif
              </div>

              <div>
                <div class="v2-name">{{ $r->visitor_name ?: ($r->member_name ?: '-') }}</div>
                <div class="v2-code">{{ $r->member_code_snapshot ?: ($r->member_code ?: '-') }}</div>
                <span class="v2-type">{{ (string) $r->visitor_type === 'member' ? 'ANGGOTA' : 'NON-ANGGOTA' }}</span>
              </div>

              <div>
                <div class="v2-purpose">{{ $r->purpose ?: '-' }}</div>
                <div class="v2-loc">{{ $r->branch_name ?: '-' }}</div>
              </div>

              <div class="v2-action">
                @if(!$r->checkout_at)
                  <form method="POST" action="{{ route('visitor_counter.checkout', $r->id) }}">
                    @csrf
                    <button class="nb-btn" type="submit">Keluar</button>
                  </form>
                @else
                  <span class="v2-done">Selesai</span>
                  <div class="v2-undo-hint {{ $canUndo ? '' : 'is-expired' }}">{{ $undoHint }}</div>
                  @if($canUndo)
                    <form method="POST" action="{{ route('visitor_counter.undo_checkout', $r->id) }}" onsubmit="return confirm('Batalkan keluar pengunjung ini? Batas waktu 5 menit setelah keluar.');">
                      @csrf
                      <button class="nb-btn" type="submit">Batalkan</button>
                    </form>
                  @endif
                @endif
              </div>
            </article>
          @endforeach
        </div>

        <div class="v2-pager">{{ $rows->links() }}</div>

      <div class="v2-surface v2-audit-surface">
          <div class="v2-block-title">Riwayat Aksi</div>
          <div class="v2-block-sub">Audit masuk / keluar terbaru (hanya baca).</div>
          <div class="v2-audit-kpis">
            <div class="v2-audit-kpi">Masuk<strong>{{ number_format((int) ($auditStats['checkin'] ?? 0)) }}</strong></div>
            <div class="v2-audit-kpi">Keluar<strong>{{ number_format((int) ($auditStats['checkout'] ?? 0)) }}</strong></div>
            <div class="v2-audit-kpi">Pembatalan<strong>{{ number_format((int) ($auditStats['undo'] ?? 0)) }}</strong></div>
          </div>

          <form id="vcAuditFilterForm" method="GET" action="{{ route('visitor_counter.index') }}">
            <input type="hidden" name="date" value="{{ $date }}">
            <input type="hidden" name="preset" value="{{ $datePreset }}">
            <input type="hidden" name="branch_id" value="{{ $branchId }}">
            <input type="hidden" name="q" value="{{ $keyword }}">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            @if($activeOnly)
              <input type="hidden" name="active_only" value="1">
            @endif
            @if($undoReady)
              <input type="hidden" name="undo_ready" value="1">
            @endif
            <select class="nb-field js-audit-auto-submit" name="audit_action">
              <option value="">Semua aksi</option>
              @foreach($allowedAuditActions as $actionKey)
                <option value="{{ $actionKey }}" {{ $auditAction === $actionKey ? 'selected' : '' }}>
                  {{ $auditActionLabels[$actionKey] ?? $actionKey }}
                </option>
              @endforeach
            </select>
            <select class="nb-field js-audit-auto-submit" name="audit_role">
              <option value="">Semua peran</option>
              @foreach($allowedAuditRoles as $roleKey)
                <option value="{{ $roleKey }}" {{ $auditRole === $roleKey ? 'selected' : '' }}>
                  {{ $auditRoleLabels[$roleKey] ?? $roleKey }}
                </option>
              @endforeach
            </select>
            <select class="nb-field js-audit-auto-submit" name="audit_sort">
              @foreach($allowedAuditSorts as $sortKey)
                <option value="{{ $sortKey }}" {{ $auditSort === $sortKey ? 'selected' : '' }}>
                  {{ $sortKey === 'oldest' ? 'Terlama dulu' : 'Terbaru dulu' }}
                </option>
              @endforeach
            </select>
            <select class="nb-field js-audit-auto-submit" name="audit_per_page">
              @foreach([10,15,25,50] as $auditSize)
                <option value="{{ $auditSize }}" {{ $auditPerPage === $auditSize ? 'selected' : '' }}>
                  {{ $auditSize }} / halaman
                </option>
              @endforeach
            </select>
            <input class="nb-field js-audit-auto-input" type="text" name="audit_q" value="{{ $auditKeyword }}" placeholder="Cari pengguna / aksi / ID baris / metadata">
            <button class="nb-btn" type="submit">Terapkan Filter Audit</button>
            <a
              id="vcAuditClearBtn"
              class="nb-btn"
              href="{{ route('visitor_counter.index', ['date' => $date, 'preset' => $datePreset !== 'custom' ? $datePreset : null, 'branch_id' => $branchId !== '' ? $branchId : null, 'q' => $keyword !== '' ? $keyword : null, 'per_page' => $perPage, 'active_only' => $activeOnly ? 1 : null, 'undo_ready' => $undoReady ? 1 : null]) }}"
            >Reset Filter Audit</a>
            <a
              class="nb-btn"
              href="{{ route('visitor_counter.export_audit_csv', ['date' => $date, 'preset' => $datePreset !== 'custom' ? $datePreset : null, 'branch_id' => $branchId !== '' ? $branchId : null, 'audit_action' => $auditAction !== '' ? $auditAction : null, 'audit_role' => $auditRole !== '' ? $auditRole : null, 'audit_q' => $auditKeyword !== '' ? $auditKeyword : null, 'audit_sort' => $auditSort]) }}"
            >Unduh Audit CSV</a>
            <a
              class="nb-btn"
              href="{{ route('visitor_counter.export_audit_json', ['date' => $date, 'preset' => $datePreset !== 'custom' ? $datePreset : null, 'branch_id' => $branchId !== '' ? $branchId : null, 'audit_action' => $auditAction !== '' ? $auditAction : null, 'audit_role' => $auditRole !== '' ? $auditRole : null, 'audit_q' => $auditKeyword !== '' ? $auditKeyword : null, 'audit_sort' => $auditSort]) }}"
            >Unduh Audit JSON</a>
          </form>

          @if($auditRows->count() === 0)
            <div class="v2-empty">Belum ada log audit pencatatan pengunjung.</div>
          @else
            <div class="v2-audit-list">
              <div class="v2-audit-head">
                <div>Waktu</div>
                <div>Aksi</div>
                <div>Pengguna</div>
                <div>Ringkasan</div>
                <div>Detail</div>
              </div>
              @foreach($auditRows as $a)
                @php
                  $meta = $a->metadata_array ?? [];
                  $actorLabel = trim((string) (($a->actor_name ?? '') !== '' ? $a->actor_name : ($a->actor_role ?? '-')));
                  $branchLabel = isset($meta['branch_id']) && (int) $meta['branch_id'] > 0 ? 'Cabang #' . (int) $meta['branch_id'] : '-';
                  $action = (string) ($a->action ?? '');
                  $actionLabel = $auditActionLabels[$action] ?? $action;
                  $actorRoleLabel = $auditRoleLabels[(string) ($a->actor_role ?? '')] ?? ((string) ($a->actor_role ?? '-'));
                  $summaryParts = [
                    'Baris: ' . ($a->auditable_id ?: '-'),
                    $branchLabel,
                  ];
                  foreach (['updated_count', 'selected_count', 'denied_count', 'skipped_count', 'reason'] as $metaKey) {
                    if (array_key_exists($metaKey, $meta) && $meta[$metaKey] !== null && $meta[$metaKey] !== '') {
                      $summaryParts[] = $metaKey . ': ' . (string) $meta[$metaKey];
                    }
                  }
                  $summaryText = implode(' | ', $summaryParts);
                  $actionClass = 'is-muted';
                  if (str_contains($action, 'checkin')) {
                    $actionClass = 'is-checkin';
                  } elseif (str_contains($action, 'checkout')) {
                    $actionClass = str_contains($action, 'undo') ? 'is-undo' : 'is-checkout';
                  } elseif (str_contains($action, 'undo')) {
                    $actionClass = 'is-undo';
                  }
                @endphp
                <article class="v2-audit-item">
                  <div class="v2-audit-time">{{ $a->created_at ? \Illuminate\Support\Carbon::parse($a->created_at)->format('d/m H:i:s') : '-' }}</div>
                  <div class="v2-audit-action js-audit-hl"><span class="v2-audit-badge {{ $actionClass }}">{{ $actionLabel }}</span></div>
                  <div class="v2-audit-actor js-audit-hl">
                    {{ $actorLabel }}
                    <small>{{ $actorRoleLabel }}</small>
                  </div>
                  <div class="v2-audit-summary js-audit-hl">{{ $summaryText }}</div>
                  <div class="v2-audit-tools">
                    @if(!empty($meta))
                      <button class="v2-audit-view js-audit-view" type="button" data-meta='@json($meta, JSON_UNESCAPED_UNICODE)'>Lihat</button>
                      <button class="v2-audit-copy js-audit-copy" type="button" data-meta='@json($meta, JSON_UNESCAPED_UNICODE)'>Salin</button>
                    @endif
                  </div>
                </article>
              @endforeach
            </div>
            @if(method_exists($auditRows, 'links'))
              <div class="v2-pager v2-pager-sm">{{ $auditRows->links() }}</div>
            @endif
          @endif
        </div>
      @endif
    </div>

    <aside class="v2-side">
      <div class="v2-surface">
        <div class="v2-block-title">Filter Tanggal</div>
        <div class="v2-block-sub">Lihat log per hari dan cabang.</div>

        <form method="GET" action="{{ route('visitor_counter.index') }}">
          <div class="v2-presets">
            <a class="nb-btn {{ $datePreset === 'today' ? 'is-active' : '' }}" href="{{ route('visitor_counter.index', ['preset' => 'today', 'branch_id' => $branchId !== '' ? $branchId : null, 'q' => $keyword !== '' ? $keyword : null, 'active_only' => $activeOnly ? 1 : null, 'undo_ready' => $undoReady ? 1 : null, 'per_page' => $perPage]) }}">Hari Ini</a>
            <a class="nb-btn {{ $datePreset === 'yesterday' ? 'is-active' : '' }}" href="{{ route('visitor_counter.index', ['preset' => 'yesterday', 'branch_id' => $branchId !== '' ? $branchId : null, 'q' => $keyword !== '' ? $keyword : null, 'active_only' => $activeOnly ? 1 : null, 'undo_ready' => $undoReady ? 1 : null, 'per_page' => $perPage]) }}">Kemarin</a>
            <a class="nb-btn {{ $datePreset === 'last7' ? 'is-active' : '' }}" href="{{ route('visitor_counter.index', ['preset' => 'last7', 'branch_id' => $branchId !== '' ? $branchId : null, 'q' => $keyword !== '' ? $keyword : null, 'active_only' => $activeOnly ? 1 : null, 'undo_ready' => $undoReady ? 1 : null, 'per_page' => $perPage]) }}">7 Hari</a>
          </div>
          <input type="hidden" name="preset" value="custom">

          <div class="v2-field">
            <label>Tanggal</label>
            <input class="nb-field" type="date" name="date" value="{{ $date }}">
          </div>

          <div class="v2-field">
            <label>Cari Nama / Kode / Tujuan</label>
            <input class="nb-field" type="text" name="q" value="{{ $keyword }}" placeholder="contoh: andi, MBR-0001, referensi">
          </div>

          <div class="v2-field">
            <label>Cabang</label>
            <select class="nb-field" name="branch_id">
              <option value="">Semua cabang</option>
              @foreach($branches as $b)
                <option value="{{ $b->id }}" {{ $branchId === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="v2-field">
            <label>Baris per Halaman</label>
            <select class="nb-field" name="per_page">
              @foreach([20,50,100] as $size)
                <option value="{{ $size }}" {{ $perPage === $size ? 'selected' : '' }}>{{ $size }}</option>
              @endforeach
            </select>
          </div>

          <label class="v2-check">
            <input type="checkbox" name="active_only" value="1" {{ $activeOnly ? 'checked' : '' }}>
            Belum keluar saja
          </label>
          <label class="v2-check">
            <input type="checkbox" name="undo_ready" value="1" {{ $undoReady ? 'checked' : '' }}>
            Hanya yang bisa dibatalkan (<= 5 menit)
          </label>

          <div class="v2-btns">
            <button class="nb-btn nb-btn-primary" type="submit">Terapkan</button>
            <a class="nb-btn v2-btn-center" href="{{ route('visitor_counter.index') }}">Reset</a>
          </div>
        </form>
      </div>

      <div class="v2-surface">
        <div class="v2-block-title">Pencatatan Masuk Pengunjung</div>
        <div class="v2-block-sub">Gunakan kode anggota atau input manual.</div>

        <form method="POST" action="{{ route('visitor_counter.store') }}" id="vcCheckinForm">
          @csrf

          <div class="v2-field">
            <label>Tipe Pengunjung</label>
            <select class="nb-field {{ $errors->has('visitor_type') ? 'v2-err-input' : '' }}" name="visitor_type" id="vcVisitorType">
              <option value="member" {{ $oldVisitorType === 'member' ? 'selected' : '' }}>Anggota</option>
              <option value="non_member" {{ $oldVisitorType === 'non_member' ? 'selected' : '' }}>Non-Anggota</option>
            </select>
            @error('visitor_type')
              <div class="v2-err">{{ $message }}</div>
            @enderror
          </div>

          <div class="v2-field" id="vcMemberCodeWrap">
            <label>Kode Anggota</label>
            <input class="nb-field {{ $errors->has('member_code') ? 'v2-err-input' : '' }}" id="vcMemberCode" name="member_code" placeholder="contoh: MBR-0001" value="{{ old('member_code') }}">
            @error('member_code')
              <div class="v2-err">{{ $message }}</div>
            @enderror
          </div>

          <div class="v2-field {{ $oldVisitorType === 'member' ? 'is-hidden' : '' }}" id="vcNameWrap">
            <label>Nama Pengunjung</label>
            <input class="nb-field {{ $errors->has('visitor_name') ? 'v2-err-input' : '' }}" id="vcVisitorName" name="visitor_name" placeholder="Nama non-anggota" value="{{ old('visitor_name') }}">
            @error('visitor_name')
              <div class="v2-err">{{ $message }}</div>
            @enderror
          </div>

          <div class="v2-field">
            <label>Cabang</label>
            <select class="nb-field {{ $errors->has('branch_id') ? 'v2-err-input' : '' }}" name="branch_id">
              <option value="">-</option>
              @foreach($branches as $b)
                <option value="{{ $b->id }}" {{ old('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
              @endforeach
            </select>
            @error('branch_id')
              <div class="v2-err">{{ $message }}</div>
            @enderror
          </div>

          <div class="v2-field">
            <label>Tujuan</label>
            <input class="nb-field {{ $errors->has('purpose') ? 'v2-err-input' : '' }}" name="purpose" placeholder="Baca, pinjam, referensi, dll" value="{{ old('purpose') }}">
            @error('purpose')
              <div class="v2-err">{{ $message }}</div>
            @enderror
          </div>

          <div class="v2-field">
            <label>Catatan</label>
            <textarea class="nb-field {{ $errors->has('notes') ? 'v2-err-input' : '' }}" name="notes" rows="3" placeholder="Opsional">{{ old('notes') }}</textarea>
            @error('notes')
              <div class="v2-err">{{ $message }}</div>
            @enderror
          </div>

          <div class="v2-btns">
            <button class="nb-btn nb-btn-primary v2-btn-full" type="submit" id="vcSubmitBtn">Simpan Masuk</button>
          </div>
          <div class="v2-form-hint" id="vcCheckinHint" aria-live="polite"></div>
        </form>
      </div>
    </aside>
  </section>
</div>

<div class="v2-modal" id="vcAuditModal" aria-hidden="true">
  <div class="v2-modal-card" role="dialog" aria-modal="true" aria-labelledby="vcAuditModalTitle">
    <div class="v2-modal-head">
      <h3 class="v2-modal-title" id="vcAuditModalTitle">Detail Audit (JSON)</h3>
      <button type="button" class="v2-modal-close" id="vcAuditModalClose">Tutup</button>
    </div>
    <div class="v2-modal-body">
      <pre class="v2-modal-json" id="vcAuditJsonBody">{}</pre>
      <div class="v2-modal-actions">
        <button type="button" class="v2-audit-copy" id="vcAuditModalCopy">Salin JSON</button>
      </div>
    </div>
  </div>
</div>

@include('visitor_counter._scripts')

@endsection
