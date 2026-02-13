{{-- resources/views/katalog/audit.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Audit Katalog • NOTOBUKU')

@section('content')
  <div class="kc-wrap" style="max-width:1100px;margin:0 auto;padding:16px;">
    <div class="kc-head" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
      <div>
        <div style="font-weight:900;font-size:16px;">Audit Katalog</div>
        <div class="nb-muted-2" style="font-size:12.5px;">
          {{ $biblio->display_title ?? $biblio->title ?? 'Bibliografi' }}
          <span style="opacity:.7;">•</span>
          ID #{{ $biblio->id }}
        </div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="nb-btn" href="{{ route('katalog.edit', $biblio->id) }}">Kembali ke Edit</a>
        <a class="nb-btn nb-btn-outline" href="{{ route('katalog.audit.csv', $biblio->id) }}">Export CSV</a>
      </div>
    </div>

    @php
      $auditFilters = $auditFilters ?? ['action' => '', 'status' => '', 'start_date' => '', 'end_date' => ''];
    @endphp

    <div class="kc-section acc-slate" style="margin-top:12px;padding:14px;border:1px solid var(--nb-border);border-radius:16px;background:var(--nb-surface);">
      <style>
        .kc-audit-filters{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; align-items:end; }
        .kc-audit-filters .kc-field label{ display:block; font-weight:800; font-size:12.5px; margin-bottom:6px; }
        .kc-audit-filters .nb-field{ width:100%; box-sizing:border-box; border-radius:14px; height:40px; }
        .kc-audit-filters select.nb-field{
          padding-right:34px;
          background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 20 20'%3E%3Cpath d='M5 7l5 5 5-5' fill='none' stroke='%236B7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
          background-repeat:no-repeat;
          background-position:right 12px center;
          appearance:none;
          -webkit-appearance:none;
          -moz-appearance:none;
        }
        .kc-audit-filters input[type="date"].nb-field{ padding-right:34px; }
        @media(max-width:980px){ .kc-audit-filters{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media(max-width:640px){ .kc-audit-filters{ grid-template-columns:1fr; } }
      </style>
      <form method="GET" action="{{ route('katalog.audit', $biblio->id) }}" class="kc-audit-filters">
        <div class="kc-field">
          <label>Aksi</label>
          <select class="nb-field" name="action">
            <option value="">Semua</option>
            <option value="create" @selected($auditFilters['action']==='create')>create</option>
            <option value="update" @selected($auditFilters['action']==='update')>update</option>
            <option value="delete" @selected($auditFilters['action']==='delete')>delete</option>
            <option value="bulk_update" @selected($auditFilters['action']==='bulk_update')>bulk_update</option>
            <option value="attachment_add" @selected($auditFilters['action']==='attachment_add')>attachment_add</option>
            <option value="attachment_delete" @selected($auditFilters['action']==='attachment_delete')>attachment_delete</option>
            <option value="download" @selected($auditFilters['action']==='download')>download</option>
          </select>
        </div>
        <div class="kc-field">
          <label>Status</label>
          <select class="nb-field" name="status">
            <option value="">Semua</option>
            <option value="success" @selected($auditFilters['status']==='success')>success</option>
            <option value="warn" @selected($auditFilters['status']==='warn')>warn</option>
            <option value="error" @selected($auditFilters['status']==='error')>error</option>
          </select>
        </div>
        <div class="kc-field">
          <label>Mulai</label>
          <input class="nb-field" type="date" name="start_date" value="{{ $auditFilters['start_date'] }}">
        </div>
        <div class="kc-field">
          <label>Sampai</label>
          <input class="nb-field" type="date" name="end_date" value="{{ $auditFilters['end_date'] }}">
        </div>
        <div style="grid-column:1 / -1; display:flex; gap:8px; flex-wrap:wrap; margin-top:4px;">
          <button class="nb-btn nb-btn-primary" type="submit">Terapkan</button>
          <a class="nb-btn" href="{{ route('katalog.audit', $biblio->id) }}">Reset</a>
          @php
            $today = now()->format('Y-m-d');
            $weekAgo = now()->subDays(6)->format('Y-m-d');
          @endphp
          <a class="nb-btn nb-btn-outline" href="{{ route('katalog.audit', $biblio->id) }}?start_date={{ $today }}&end_date={{ $today }}">Hari ini</a>
          <a class="nb-btn nb-btn-outline" href="{{ route('katalog.audit', $biblio->id) }}?start_date={{ $weekAgo }}&end_date={{ $today }}">7 hari terakhir</a>
          <a class="nb-btn nb-btn-outline" href="{{ route('katalog.audit.csv', $biblio->id) }}?action={{ urlencode($auditFilters['action']) }}&status={{ urlencode($auditFilters['status']) }}&start_date={{ urlencode($auditFilters['start_date']) }}&end_date={{ urlencode($auditFilters['end_date']) }}">Export CSV</a>
        </div>
      </form>

      @php
        $statsActions = $audits->getCollection()->groupBy('action')->map->count();
        $statsStatus = $audits->getCollection()->groupBy('status')->map->count();
      @endphp

      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:12px;">
        <div style="border:1px solid var(--nb-border);border-radius:12px;padding:10px;background:var(--nb-surface);">
          <div class="nb-muted-2" style="font-size:12px;font-weight:700;">Ringkasan Aksi (halaman ini)</div>
          <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
            @foreach($statsActions as $key => $val)
              <span class="nb-chip">{{ $key ?? '-' }}: {{ $val }}</span>
            @endforeach
            @if($statsActions->isEmpty())
              <span class="nb-muted-2" style="font-size:12px;">Tidak ada data.</span>
            @endif
          </div>
        </div>
        <div style="border:1px solid var(--nb-border);border-radius:12px;padding:10px;background:var(--nb-surface);">
          <div class="nb-muted-2" style="font-size:12px;font-weight:700;">Ringkasan Status (halaman ini)</div>
          <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
            @foreach($statsStatus as $key => $val)
              <span class="nb-chip">{{ $key ?? '-' }}: {{ $val }}</span>
            @endforeach
            @if($statsStatus->isEmpty())
              <span class="nb-muted-2" style="font-size:12px;">Tidak ada data.</span>
            @endif
          </div>
        </div>
      </div>

      @if($audits->count() === 0)
        <div class="kc-quality-empty">Belum ada riwayat audit untuk bibliografi ini.</div>
      @else
        <div style="margin-top:12px;overflow:auto;">
          <table style="width:100%;border-collapse:separate;border-spacing:0 8px;">
            <thead>
              <tr class="nb-muted-2" style="font-size:12px;text-transform:uppercase;letter-spacing:.04em;">
                <th style="text-align:left;padding:0 8px;">Waktu</th>
                <th style="text-align:left;padding:0 8px;">Aksi</th>
                <th style="text-align:left;padding:0 8px;">User</th>
                <th style="text-align:left;padding:0 8px;">Status</th>
                <th style="text-align:left;padding:0 8px;">Ringkas</th>
              </tr>
            </thead>
            <tbody>
              @foreach($audits as $a)
                @php
                  $u = $auditUsers[$a->user_id] ?? null;
                  $who = $u?->name ?? 'Sistem';
                  $time = $a->created_at?->format('d M Y H:i');
                  $meta = is_array($a->meta) ? $a->meta : [];
                  $summary = '';
                  if (!empty($meta['title'])) $summary = (string) $meta['title'];
                  elseif (!empty($meta['file_name'])) $summary = (string) $meta['file_name'];
                  elseif (!empty($meta['count'])) $summary = 'Count: ' . $meta['count'];
                  elseif (!empty($meta['biblio_id'])) $summary = 'ID: ' . $meta['biblio_id'];
                @endphp
                <tr style="background:var(--nb-surface);border:1px solid var(--nb-border);border-radius:12px;">
                  <td style="padding:10px 8px;font-size:12.5px;white-space:nowrap;">{{ $time }}</td>
                  <td style="padding:10px 8px;font-size:12.5px;font-weight:700;">
                    {{ $a->action ?? 'aksi' }}
                    <span class="nb-muted-2" style="font-weight:600;">• {{ $a->format ?? '-' }}</span>
                  </td>
                  <td style="padding:10px 8px;font-size:12.5px;">{{ $who }}</td>
                  <td style="padding:10px 8px;font-size:12.5px;">
                    <span class="nb-chip">{{ $a->status ?? '-' }}</span>
                  </td>
                  <td style="padding:10px 8px;font-size:12.5px;">
                    {{ $summary !== '' ? $summary : '-' }}
                  </td>
                </tr>
                @if(!empty($meta))
                  <tr>
                    <td colspan="5" style="padding:0 8px 6px 8px;">
                      <div class="nb-muted-2" style="font-size:12px;white-space:pre-wrap;">
                        {{ json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                      </div>
                    </td>
                  </tr>
                @endif
              @endforeach
            </tbody>
          </table>
        </div>

        <div style="margin-top:12px;">
          {{ $audits->appends($auditFilters)->links() }}
        </div>
      @endif
    </div>
  </div>
@endsection
