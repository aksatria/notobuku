@props([
  'tone' => 'primary', // primary|outline|ghost
  'href' => null,
  'type' => 'button',
])

@php
  $base = 'inline-flex items-center justify-center gap-2 rounded-2xl px-4 py-2 text-sm font-semibold transition shadow-sm';
  $primary = 'text-white hover:opacity-95';
  $outline = 'border border-[var(--nb-border)] bg-[var(--nb-card)] hover:shadow';
  $ghost = 'bg-transparent hover:bg-black/5 dark:hover:bg-white/5';

  $style = match($tone){
    'outline' => '',
    'ghost' => '',
    default => 'background: linear-gradient(135deg, var(--nb-sky), var(--nb-green));',
  };

  $class = $base . ' ' . match($tone){
    'outline' => $outline,
    'ghost' => $ghost,
    default => $primary,
  };
@endphp

@if($href)
  <a href="{{ $href }}" {{ $attributes->merge(['class' => $class, 'style' => $style]) }}>
    {{ $slot }}
  </a>
@else
  <button type="{{ $type }}" {{ $attributes->merge(['class' => $class, 'style' => $style]) }}>
    {{ $slot }}
  </button>
@endif
