@props([
  'title' => 'Judul',
  'value' => '0',
  'hint' => '',
  'icon' => '📌',
  'tone' => 'blue', // blue|green|sky
])

@php
  $bg = match($tone){
    'green' => 'linear-gradient(135deg, rgba(39,174,96,.18), rgba(39,174,96,.08))',
    'sky'   => 'linear-gradient(135deg, rgba(74,144,226,.18), rgba(74,144,226,.08))',
    default => 'linear-gradient(135deg, rgba(31,58,95,.18), rgba(31,58,95,.08))',
  };
@endphp

<div class="rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-card)] shadow-sm overflow-hidden">
  <div class="p-4" style="background: {{ $bg }};">
    <div class="flex items-start justify-between gap-3">
      <div>
        <div class="text-xs font-semibold text-[var(--nb-muted)]">{{ $title }}</div>
        <div class="mt-1 text-2xl font-extrabold tracking-tight">{{ $value }}</div>
        @if($hint)
          <div class="mt-1 text-xs text-[var(--nb-muted)]">{{ $hint }}</div>
        @endif
      </div>
      <div class="h-10 w-10 rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-card)] flex items-center justify-center text-lg">
        {{ $icon }}
      </div>
    </div>
  </div>
</div>
