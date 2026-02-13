{{-- resources/views/layouts/member.blade.php --}}
@extends('layouts.notobuku')

@section('title', trim($__env->yieldContent('title')) ?: 'Member Area • NOTOBUKU')

@section('content')
  <div class="mx-auto max-w-5xl px-4 py-6">
    <div class="rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-card)] p-6">
      <div class="mb-5">
        <h1 class="text-xl font-semibold">@yield('member_title','Member Area')</h1>
        <div class="text-sm text-gray-600">@yield('member_subtitle','')</div>
      </div>

      {{-- Flash --}}
      @if (session('success'))
        <div class="mb-4 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
          {{ session('success') }}
        </div>
      @endif

      @if (session('error'))
        <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {{ session('error') }}
        </div>
      @endif

      @yield('member.content')
    </div>
  </div>
@endsection
