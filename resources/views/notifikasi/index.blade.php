@extends('layouts.notobuku')

@section('title', 'Notifikasi • NOTOBUKU')

@section('content')
@php
    $filter = $filter ?? request('filter', 'all');
    $items = $items ?? collect();          // ✅ dari controller
    $canMarkRead = $canMarkRead ?? false;  // ✅ dari controller
@endphp

<div class="nb-container" style="max-width:900px;margin:auto;">

    <div class="nb-card" style="padding:16px;margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div>
                <div style="font-size:18px;font-weight:800;">Notifikasi</div>
                <div class="nb-muted" style="font-size:13px;">
                    {{ $scopeLabel ?? 'Pengingat peminjaman & aktivitas sistem' }}
                </div>
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="{{ route('notifikasi.index', ['filter'=>'all']) }}"
                   class="nb-btn {{ $filter==='all'?'nb-btn-primary':'nb-btn-soft' }}">
                    Semua
                </a>

                <a href="{{ route('notifikasi.index', ['filter'=>'unread']) }}"
                   class="nb-btn {{ $filter==='unread'?'nb-btn-primary':'nb-btn-soft' }}">
                    Belum Dibaca
                </a>

                @if($canMarkRead)
                    <form method="POST" action="{{ route('notifikasi.read_all') }}">
                        @csrf
                        <button class="nb-btn nb-btn-soft">Tandai semua dibaca</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="nb-card" style="padding:0;overflow:hidden;">

        @if($items->isEmpty())
            <div style="padding:32px;text-align:center;" class="nb-muted">
                Tidak ada notifikasi.
            </div>
        @else

        <div style="width:100%;">
            @foreach($items as $n)
                @php
                    $payload = [];
                    if (!empty($n->payload)) {
                        $decoded = json_decode($n->payload, true);
                        if (is_array($decoded)) $payload = $decoded;
                    }

                    $isUnread = is_null($n->read_at ?? null);

                    // Controller sudah menambahkan field UI kalau ada (ui_message, ui_due_pretty, ui_label, ui_emoji)
                    $emoji = $n->ui_emoji ?? (($n->type ?? '') === 'overdue' ? '⚠️' : '⏰');
                    $title = ($n->type ?? '') === 'overdue' ? 'Pengingat keterlambatan pengembalian' : 'Pengingat jatuh tempo';
                    $loanCode = $n->ui_loan_code ?? ($payload['loan_code'] ?? '-');
                    $msg = $n->ui_message ?? null;
                @endphp

                <div style="
                    display:flex;
                    gap:12px;
                    padding:14px 16px;
                    border-bottom:1px solid #eef2f7;
                    align-items:flex-start;
                    background: {{ $isUnread ? 'rgba(47,128,237,.06)' : '#fff' }};
                ">

                    <div style="margin-top:2px;">
                        <span style="font-size:18px;">{{ $emoji }}</span>
                    </div>

                    <div style="flex:1;">
                        <div style="font-weight:{{ $isUnread ? '700':'500' }};">
                            {{ $title }}
                        </div>

                        <div class="nb-muted" style="font-size:13px;margin-top:2px;">
                            Kode transaksi: {{ $loanCode }}
                        </div>

                        @if($msg)
                            <div class="nb-muted" style="font-size:13px;margin-top:6px;">
                                {{ $msg }}
                            </div>
                        @endif

                        <div class="nb-muted" style="font-size:12px;margin-top:6px;">
                            {{ \Carbon\Carbon::parse($n->created_at)->diffForHumans() }}
                        </div>
                    </div>

                    @if($canMarkRead && $isUnread)
                        <form method="POST" action="{{ route('notifikasi.read', $n->id) }}">
                            @csrf
                            <button class="nb-btn nb-btn-xs nb-btn-soft">Tandai</button>
                        </form>
                    @endif

                </div>
            @endforeach
        </div>

        @if(method_exists($items, 'links'))
            <div style="padding:12px 16px;">
                {{ $items->links() }}
            </div>
        @endif

        @endif

    </div>

</div>
@endsection
