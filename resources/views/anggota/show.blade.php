@extends('layouts.notobuku')

@section('title', 'Detail Anggota')

@section('content')
@php
  $stats = $stats ?? ['active_loans' => 0, 'overdue_items' => 0, 'unpaid_fines' => 0];
  $recentLoans = $recentLoans ?? [];
@endphp

<style>
  .page{ max-width:980px; margin:0 auto; display:flex; flex-direction:column; gap:14px; }
  .card{ background:var(--nb-surface); border:1px solid var(--nb-border); border-radius:18px; padding:16px; }
  .title{ margin:0; font-size:20px; font-weight:700; }
  .muted{ color:var(--nb-muted); font-size:13px; }
  .grid{ display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; }
  .kpi{ border:1px solid var(--nb-border); border-radius:14px; padding:10px; }
  .kpi .label{ font-size:12px; color:var(--nb-muted); }
  .kpi .value{ margin-top:4px; font-size:20px; font-weight:700; }
  .tag{ display:inline-flex; align-items:center; border:1px solid var(--nb-border); border-radius:999px; padding:4px 9px; font-size:11px; font-weight:700; }
  table{ width:100%; border-collapse:collapse; }
  th, td{ border-bottom:1px solid var(--nb-border); padding:11px 8px; text-align:left; font-size:13px; }
  th{ color:var(--nb-muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
  .btn{ display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--nb-border); border-radius:12px; padding:10px 12px; font-weight:600; }
  .btn-primary{ background:linear-gradient(90deg,#1e88e5,#1565c0); color:#fff; border-color:transparent; }
  @media (max-width:980px){ .grid{ grid-template-columns:1fr; } }
</style>

<div class="page">
  <div class="card">
    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
      <div>
        <h1 class="title">{{ $member->full_name }}</h1>
        <div class="muted">{{ $member->member_code }}</div>
      </div>
      <div style="display:flex; gap:8px;">
        <a class="btn" href="{{ route('anggota.index') }}">Kembali</a>
        <a class="btn btn-primary" href="{{ route('anggota.edit', $member->id) }}">Edit</a>
      </div>
    </div>
    <div style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:14px;">
      <div><div class="muted">Telepon</div><div>{{ $member->phone ?: '-' }}</div></div>
      @if(!empty($hasEmail))
        <div><div class="muted">Email</div><div>{{ $member->email ?: '-' }}</div></div>
      @endif
      <div><div class="muted">Status</div><div><span class="tag">{{ strtoupper($member->status) }}</span></div></div>
      <div><div class="muted">Tanggal Bergabung</div><div>{{ $member->joined_at ? \Illuminate\Support\Carbon::parse($member->joined_at)->format('d M Y') : '-' }}</div></div>
      @if(!empty($hasMemberType))
        <div><div class="muted">Tipe Anggota</div><div>{{ $member->member_type ?: '-' }}</div></div>
      @endif
      <div style="grid-column:1/-1;"><div class="muted">Alamat</div><div>{{ $member->address ?: '-' }}</div></div>
    </div>
  </div>

  <div class="card">
    <div class="grid">
      <div class="kpi"><div class="label">Pinjaman Aktif</div><div class="value">{{ number_format((int) $stats['active_loans']) }}</div></div>
      <div class="kpi"><div class="label">Item Overdue</div><div class="value">{{ number_format((int) $stats['overdue_items']) }}</div></div>
      <div class="kpi"><div class="label">Denda Unpaid</div><div class="value">Rp {{ number_format((float) $stats['unpaid_fines'], 0, ',', '.') }}</div></div>
    </div>
  </div>

  <div class="card">
    <h2 class="title" style="font-size:16px;">Riwayat Pinjaman Terbaru</h2>
    @if(empty($recentLoans))
      <div class="muted" style="margin-top:8px;">Belum ada riwayat pinjaman.</div>
    @else
      <div style="overflow:auto; margin-top:8px;">
        <table>
          <thead>
            <tr>
              <th>Kode</th>
              <th>Status</th>
              <th>Loaned At</th>
              <th>Due At</th>
              <th>Closed At</th>
            </tr>
          </thead>
          <tbody>
            @foreach($recentLoans as $row)
              <tr>
                <td>{{ $row['loan_code'] }}</td>
                <td>{{ strtoupper($row['status']) }}</td>
                <td>{{ $row['loaned_at'] ? \Illuminate\Support\Carbon::parse($row['loaned_at'])->format('d M Y H:i') : '-' }}</td>
                <td>{{ $row['due_at'] ? \Illuminate\Support\Carbon::parse($row['due_at'])->format('d M Y H:i') : '-' }}</td>
                <td>{{ $row['closed_at'] ? \Illuminate\Support\Carbon::parse($row['closed_at'])->format('d M Y H:i') : '-' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
@endsection

