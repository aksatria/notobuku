@extends('layouts.notobuku')

@section('title', 'Rule Reservasi')

@section('content')
<div style="max-width:1200px;margin:0 auto;display:grid;gap:16px;">
    @include('partials.flash')

    <div class="nb-card" style="padding:16px;">
        <h1 style="font-size:24px;font-weight:700;margin:0 0 6px;">Rule Matrix Reservasi</h1>
        <p style="margin:0;color:#64748b;">Atur kuota, masa hold, batas antrean, dan prioritas otomatis per segmen.</p>
    </div>

    <div class="nb-card" style="padding:16px;">
        <form method="POST" action="{{ route('reservasi.rules.store') }}" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;">
            @csrf
            <input name="label" required placeholder="Label aturan" class="nb-input" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;">
            <input name="branch_id" placeholder="ID cabang (opsional)" class="nb-input" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;">
            <input name="member_type" placeholder="member_type (mis: dosen)" class="nb-input" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;">
            <input name="collection_type" placeholder="collection_type (mis: buku)" class="nb-input" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;">
            <input name="max_active_reservations" type="number" min="1" max="100" value="5" placeholder="Maks aktif" class="nb-input" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;">
            <input name="max_queue_per_biblio" type="number" min="1" max="500" value="30" placeholder="Maks antrean" class="nb-input" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;">
            <input name="hold_hours" type="number" min="1" max="168" value="48" placeholder="Hold (jam)" class="nb-input" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;">
            <input name="priority_weight" type="number" value="0" placeholder="Bobot prioritas" class="nb-input" style="padding:10px;border:1px solid #cbd5e1;border-radius:10px;">
            <input name="notes" placeholder="Catatan" class="nb-input" style="grid-column:span 3;padding:10px;border:1px solid #cbd5e1;border-radius:10px;">
            <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_enabled" checked> Aktif</label>
            <button class="nb-btn nb-btn-primary" style="grid-column:span 4;justify-self:end;">Simpan Rule</button>
        </form>
    </div>

    <div class="nb-card" style="padding:0;overflow:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
            <tr style="background:#f8fafc;color:#475569;">
                <th style="padding:10px;border-bottom:1px solid #e2e8f0;text-align:left;">Label</th>
                <th style="padding:10px;border-bottom:1px solid #e2e8f0;text-align:left;">Scope</th>
                <th style="padding:10px;border-bottom:1px solid #e2e8f0;text-align:right;">Kuota</th>
                <th style="padding:10px;border-bottom:1px solid #e2e8f0;text-align:right;">Maks Antrean</th>
                <th style="padding:10px;border-bottom:1px solid #e2e8f0;text-align:right;">Hold</th>
                <th style="padding:10px;border-bottom:1px solid #e2e8f0;text-align:right;">Prioritas</th>
                <th style="padding:10px;border-bottom:1px solid #e2e8f0;text-align:center;">Status</th>
                <th style="padding:10px;border-bottom:1px solid #e2e8f0;text-align:center;">Aksi</th>
            </tr>
            </thead>
            <tbody>
            @forelse($rules as $r)
                <tr>
                    <td style="padding:10px;border-bottom:1px solid #e2e8f0;">{{ $r->label ?: 'Rule #' . $r->id }}</td>
                    <td style="padding:10px;border-bottom:1px solid #e2e8f0;">
                        Cabang: {{ $r->branch_id ?: 'semua' }}<br>
                        Member: {{ $r->member_type ?: 'semua' }}<br>
                        Koleksi: {{ $r->collection_type ?: 'semua' }}
                    </td>
                    <td style="padding:10px;border-bottom:1px solid #e2e8f0;text-align:right;">{{ $r->max_active_reservations }}</td>
                    <td style="padding:10px;border-bottom:1px solid #e2e8f0;text-align:right;">{{ $r->max_queue_per_biblio }}</td>
                    <td style="padding:10px;border-bottom:1px solid #e2e8f0;text-align:right;">{{ $r->hold_hours }} jam</td>
                    <td style="padding:10px;border-bottom:1px solid #e2e8f0;text-align:right;">{{ $r->priority_weight }}</td>
                    <td style="padding:10px;border-bottom:1px solid #e2e8f0;text-align:center;">{{ $r->is_enabled ? 'Aktif' : 'Nonaktif' }}</td>
                    <td style="padding:10px;border-bottom:1px solid #e2e8f0;text-align:center;">
                        <form method="POST" action="{{ route('reservasi.rules.toggle', $r->id) }}">
                            @csrf
                            <button class="nb-btn nb-btn-soft" type="submit">Ubah</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="padding:18px;text-align:center;color:#64748b;">Belum ada rule khusus. Sistem pakai default config.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
