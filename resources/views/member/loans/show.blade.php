{{-- resources/views/member/loans/show.blade.php --}}
@extends('layouts.member')

@section('title', 'Detail Pinjaman • NOTOBUKU')

@section('member_title','Pinjaman Saya')
@section('member_subtitle','Lihat status pinjaman dan perpanjang jika tersedia')


@section('member.content')
@php
  $today = \Carbon\Carbon::today();
  $itemsActive = (int)($summary['items_active'] ?? 0);
  $itemsOverdue = (int)($summary['items_overdue'] ?? 0);
  $nearestDue = !empty($summary['nearest_due']) ? \Carbon\Carbon::parse($summary['nearest_due']) : null;
  $maxItems = (int)config('notobuku.loans.max_items', 3);
  if ($maxItems <= 0) $maxItems = 3;
  $maxRenewals = (int)config('notobuku.loans.max_renewals', 2);
  if ($maxRenewals <= 0) $maxRenewals = 2;
  $nearLimit = $itemsActive > 0 && $itemsActive >= max(1, $maxItems - 1);
  $overdueDaysMax = (int)($summary['overdue_days_max'] ?? 0);
  $fineRate = (int)($summary['fine_rate'] ?? 1000);
  $fineEstimate = (int)($summary['fine_estimate'] ?? 0);

  $toneMap = [
    'blue' => ['bg' => 'rgba(30,136,229,.10)', 'fg' => 'var(--nb-blue)', 'bd' => 'rgba(30,136,229,.18)'],
    'green' => ['bg' => 'rgba(46,204,113,.12)', 'fg' => 'var(--nb-green-2)', 'bd' => 'rgba(46,204,113,.18)'],
    'orange' => ['bg' => 'rgba(251,140,0,.14)', 'fg' => '#ef6c00', 'bd' => 'rgba(251,140,0,.22)'],
    'slate' => ['bg' => 'rgba(107,114,128,.12)', 'fg' => '#374151', 'bd' => 'rgba(107,114,128,.20)'],
  ];

  $status = 'Selesai';
  $tone = 'green';
  if ($itemsActive > 0) {
    $status = $itemsOverdue > 0 ? 'Overdue' : 'Aktif';
    $tone = $itemsOverdue > 0 ? 'orange' : 'blue';
  }
  $toneCss = $toneMap[$tone] ?? $toneMap['slate'];

  $idr = function($n){
    $n = (int)$n;
    return 'Rp ' . number_format($n, 0, ',', '.');
  };
@endphp

<div class="nb-stack">
  @if($itemsOverdue > 0 && $overdueDaysMax > 0)
    <div style="border:1px solid rgba(231,76,60,.30); background: rgba(231,76,60,.10); color:#B33A2B; padding:10px 14px; border-radius:14px; font-size:13px; font-weight:700;">
      Overdue {{ $overdueDaysMax }} hari • Estimasi denda {{ $idr($fineEstimate) }} ({{ $idr($fineRate) }}/hari)
    </div>
  @endif
  <div class="nb-row">
    <div class="nb-row-left" style="min-width:0;">
      <span style="width:44px;height:44px;border-radius:16px;display:inline-flex;align-items:center;justify-content:center;background:{{ $toneCss['bg'] }};color:{{ $toneCss['fg'] }};border:1px solid {{ $toneCss['bd'] }};flex-shrink:0;">
        <svg style="width:20px;height:20px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
          <use href="#nb-icon-rotate"></use>
        </svg>
      </span>
      <div style="min-width:0;">
        <div style="font-size:18px;font-weight:700;line-height:1.2;">
          Detail Pinjaman <span style="font-weight:650;color:rgba(55,65,81,.72);">#{{ (int)$loan->id }}</span>
        </div>
        <div style="margin-top:4px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          <span style="font-size:11px;font-weight:600;color:{{ $toneCss['fg'] }};
                       background:{{ $toneCss['bg'] }};border:1px solid {{ $toneCss['bd'] }};
                       padding:6px 10px;border-radius:999px;">
            {{ $status }}
          </span>
          @if($nearLimit && $itemsOverdue === 0)
            <span style="font-size:11px;font-weight:600;color:#ef6c00;background:rgba(251,140,0,.14);border:1px solid rgba(251,140,0,.35);padding:6px 10px;border-radius:999px;">
              Hampir penuh: {{ $itemsActive }}/{{ $maxItems }}
            </span>
          @endif

          <div style="font-size:12.5px;color:rgba(55,65,81,.75);font-weight:500;">
            Dibuat: {{ \Carbon\Carbon::parse($loan->created_at)->format('d M Y') }}
          </div>

          @if($nearestDue)
            @php
              $diff = $today->diffInDays($nearestDue, false);
              $human = $diff < 0 ? (abs($diff) . ' hari lewat') : ($diff === 0 ? 'Hari ini' : $diff.' hari lagi');
            @endphp
            <div style="font-size:12.5px;color:rgba(55,65,81,.75);font-weight:500;">
              Jatuh tempo terdekat: <span style="font-weight:650;">{{ $nearestDue->format('d M Y') }}</span> ({{ $human }})
            </div>
          @endif
        </div>
      </div>
    </div>

    <div class="nb-row-right">
      <a class="nb-btn" href="{{ route('member.pinjaman') }}">
        <span style="width:18px;height:18px;display:inline-flex;color:rgba(17,24,39,.70);">
          <svg style="width:18px;height:18px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <use href="#nb-icon-book"></use>
          </svg>
        </span>
        Kembali
      </a>

      @if($itemsActive > 0 && $itemsOverdue === 0)
        <form method="POST" action="{{ route('member.pinjaman.extend', ['id' => (int)$loan->id]) }}" class="m-0">
          @csrf
          <button type="submit" class="nb-btn nb-btn-primary">
            Perpanjang
          </button>
        </form>
      @endif
    </div>
  </div>

  <div class="rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-surface)] shadow-sm overflow-hidden">
    <div style="padding:14px 16px;border-bottom:1px solid var(--nb-border);display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
      <div style="font-size:14.5px;font-weight:700;">Daftar Item</div>
      <div style="font-size:12.5px;color:rgba(55,65,81,.75);font-weight:500;">
        {{ count($items) }} item • {{ $itemsActive }} aktif • {{ $itemsOverdue }} overdue
      </div>
    </div>

    <div style="overflow:auto;">
      <table style="width:100%;border-collapse:separate;border-spacing:0;">
        <thead>
          <tr>
            <th style="text-align:left;padding:12px 16px;font-size:12px;font-weight:600;color:rgba(55,65,81,.72);border-bottom:1px solid var(--nb-border);">Buku</th>
            <th style="text-align:left;padding:12px 16px;font-size:12px;font-weight:600;color:rgba(55,65,81,.72);border-bottom:1px solid var(--nb-border);">Barcode</th>
            <th style="text-align:left;padding:12px 16px;font-size:12px;font-weight:600;color:rgba(55,65,81,.72);border-bottom:1px solid var(--nb-border);">Due Date</th>
            <th style="text-align:left;padding:12px 16px;font-size:12px;font-weight:600;color:rgba(55,65,81,.72);border-bottom:1px solid var(--nb-border);">Perpanjang</th>
            <th style="text-align:left;padding:12px 16px;font-size:12px;font-weight:600;color:rgba(55,65,81,.72);border-bottom:1px solid var(--nb-border);">Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach($items as $it)
            @php
              $isReturned = !empty($it->returned_at);
              $due = !empty($it->due_date) ? \Carbon\Carbon::parse($it->due_date) : null;

              $st = 'Aktif';
              $tone = 'blue';

              if ($isReturned) {
                $st = 'Selesai';
                $tone = 'green';
              } elseif ($due && $due->lt($today)) {
                $st = 'Overdue';
                $tone = 'orange';
              }

              $tcss = $toneMap[$tone] ?? $toneMap['slate'];

              $barcode = $it->barcode ?: ($it->inventory_code ?: ($it->accession_number ?: '—'));
            @endphp

            <tr>
              <td style="padding:14px 16px;border-bottom:1px solid var(--nb-border);min-width:320px;">
                <div style="font-size:13.5px;font-weight:650;color:rgba(17,24,39,.92);">
                  {{ $it->title ?: '—' }}
                </div>
                <div style="margin-top:3px;font-size:12.5px;color:rgba(55,65,81,.72);font-weight:500;">
                  Item ID: {{ (int)$it->item_id }}
                </div>
              </td>

              <td style="padding:14px 16px;border-bottom:1px solid var(--nb-border);min-width:200px;">
                <div style="font-size:12.5px;color:rgba(17,24,39,.88);font-weight:600;">
                  {{ $barcode }}
                </div>
              </td>

              <td style="padding:14px 16px;border-bottom:1px solid var(--nb-border);min-width:180px;">
                @if($due)
                  <div style="font-size:12.5px;font-weight:650;color:rgba(17,24,39,.90);">
                    {{ $due->format('d M Y') }}
                  </div>
                  <div style="margin-top:3px;font-size:12px;color:rgba(55,65,81,.72);font-weight:500;">
                    @php
                      $diff = $today->diffInDays($due, false);
                      $human = $diff < 0 ? (abs($diff).' hari lewat') : ($diff === 0 ? 'Hari ini' : $diff.' hari lagi');
                    @endphp
                    {{ $human }}
                  </div>
                @else
                  <div style="font-size:12.5px;color:rgba(55,65,81,.72);font-weight:500;">—</div>
                @endif
              </td>

              @php
                $renewCount = (int)($it->renew_count ?? 0);
                $renewRemain = max(0, $maxRenewals - $renewCount);
              @endphp
              <td style="padding:14px 16px;border-bottom:1px solid var(--nb-border);min-width:140px;">
                <span title="Maks {{ $maxRenewals }}x per item"
                      style="font-size:11px;font-weight:700;color:{{ $renewCount >= max(0, $maxRenewals - 1) ? '#ef6c00' : 'rgba(17,24,39,.85)' }};
                             background:{{ $renewCount >= max(0, $maxRenewals - 1) ? 'rgba(251,140,0,.14)' : 'rgba(107,114,128,.12)' }};
                             border:1px solid {{ $renewCount >= max(0, $maxRenewals - 1) ? 'rgba(251,140,0,.35)' : 'rgba(107,114,128,.25)' }};
                             padding:6px 10px;border-radius:999px;display:inline-flex;align-items:center;">
                  {{ $renewCount }}/{{ $maxRenewals }} • Sisa {{ $renewRemain }}
                </span>
              </td>

              <td style="padding:14px 16px;border-bottom:1px solid var(--nb-border);min-width:160px;">
                <span style="font-size:11px;font-weight:600;color:{{ $tcss['fg'] }};
                             background:{{ $tcss['bg'] }};border:1px solid {{ $tcss['bd'] }};
                             padding:6px 10px;border-radius:999px;">
                  {{ $st }}
                </span>

                @if($isReturned && $it->returned_at)
                  <div style="margin-top:6px;font-size:12px;color:rgba(55,65,81,.70);font-weight:500;">
                    Kembali: {{ \Carbon\Carbon::parse($it->returned_at)->format('d M Y') }}
                  </div>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection



