{{-- resources/views/placeholders/dashboard.blade.php --}}
@extends('layouts.notobuku')

@section('title', ($title ?? 'Dashboard') . ' • NOTOBUKU')

@section('content')
@php
  $tone = $tone ?? 'blue';
@endphp

<div class="nb-stack">

  <div class="nb-card">
    <div class="nb-cardhead {{ $tone === 'green' ? 'green' : 'blue' }}">
      <div class="left">
        <div class="icon">
          <svg style="width:22px;height:22px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <use href="#nb-icon-home"></use>
          </svg>
        </div>
        <div style="min-width:0;">
          <div class="title nb-clip">{{ $title ?? 'Dashboard' }}</div>
          <div class="sub nb-clip">{{ $subtitle ?? 'Kelola sistem NOTOBUKU' }}</div>
        </div>
      </div>

      <span class="nb-chip">Aktif</span>
    </div>

    <div class="nb-card pad">
      <div class="nb-row">
        <div style="min-width:0;">
          <p class="nb-title">Aksi cepat</p>
          <p class="nb-sub">Placeholder rapi dulu. Nanti diisi statistik asli & modul.</p>
        </div>
        <div class="nb-row-right">
          <button class="nb-btn" type="button" data-nb-open-search>
            <svg style="width:18px;height:18px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><use href="#nb-icon-search"></use></svg>
            Cari (Ctrl K)
          </button>
          <a href="{{ route('katalog.index') }}" class="nb-btn nb-btn-primary">Buka Katalog</a>
          <a href="{{ route('komunitas.feed') }}" class="nb-btn nb-btn-success">Buka Komunitas</a>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
