{{-- resources/views/placeholders/page.blade.php --}}
@extends('layouts.notobuku')

@section('title', ($title ?? 'Halaman') . ' • NOTOBUKU')

@section('content')
@php
  $tone = $tone ?? 'blue';
  $primary = $primary ?? null; // ['label'=>..., 'href'=>...]
@endphp

<div class="nb-stack">
  <div class="nb-card">
    <div class="nb-cardhead {{ $tone === 'green' ? 'green' : 'blue' }}">
      <div class="left">
        <div class="icon">
          <svg style="width:22px;height:22px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <use href="{{ $tone === 'green' ? '#nb-icon-chat' : '#nb-icon-book' }}"></use>
          </svg>
        </div>
        <div style="min-width:0;">
          <div class="title nb-clip">{{ $title ?? 'Halaman' }}</div>
          <div class="sub nb-clip">{{ $subtitle ?? 'Placeholder rapi dulu (nanti isi modul nyata).' }}</div>
        </div>
      </div>

      <span class="nb-chip">Placeholder</span>
    </div>

    <div class="nb-card pad">
      <div class="nb-row">
        <div style="min-width:0;">
          <p class="nb-title">Konten belum diaktifkan</p>
          <p class="nb-sub">
            Ini placeholder yang aman supaya UI tidak error. Nanti kita isi modul asli step-by-step.
          </p>
        </div>

        <div class="nb-row-right">
          <button class="nb-btn" type="button" data-nb-open-search>
            <svg style="width:18px;height:18px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
              <use href="#nb-icon-search"></use>
            </svg>
            Cari (Ctrl K)
          </button>

          @if($primary && !empty($primary['label']))
            <a href="{{ $primary['href'] ?? '#' }}"
               class="nb-btn {{ ($tone === 'green') ? 'nb-btn-success' : 'nb-btn-primary' }}">
              {{ $primary['label'] }}
            </a>
          @endif
        </div>
      </div>

      <div class="nb-divider" style="margin:16px 0;"></div>

      <div style="display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px;">
        <div class="nb-card pad" style="box-shadow:none;">
          <p class="nb-title">Yang akan dibuat</p>
          <ul style="margin:10px 0 0; padding-left:18px; color:var(--nb-muted); font-size:13px; line-height:1.7;">
            <li>List + pencarian</li>
            <li>Detail</li>
            <li>Form tambah/edit (sesuai role)</li>
          </ul>
        </div>
        <div class="nb-card pad" style="box-shadow:none;">
          <p class="nb-title">Standar UI</p>
          <ul style="margin:10px 0 0; padding-left:18px; color:var(--nb-muted); font-size:13px; line-height:1.7;">
            <li>Header konsisten (biru/hijau)</li>
            <li>Spacing seragam</li>
            <li>Tombol tegas & rapi</li>
          </ul>
        </div>
      </div>

    </div>
  </div>
</div>

<style>
  @media (max-width: 900px){
    div[style*="grid-template-columns:repeat(2"]{ grid-template-columns:1fr !important; }
  }
</style>
@endsection
