@props([
  'tone' => 'info', // success|info|warn|danger
])

@php
  $base = 'inline-flex items-center gap-2 rounded-xl px-3 py-1 text-xs font-bold border';
  $map = [
    'success' => ['bg'=>'rgba(39,174,96,.14)','bd'=>'rgba(39,174,96,.25)','tx'=>'#1E7A44','dot'=>'#27AE60'],
    'warn'    => ['bg'=>'rgba(245,158,11,.14)','bd'=>'rgba(245,158,11,.25)','tx'=>'#B45309','dot'=>'#F59E0B'],
    'danger'  => ['bg'=>'rgba(239,68,68,.14)','bd'=>'rgba(239,68,68,.25)','tx'=>'#B91C1C','dot'=>'#EF4444'],
    'info'    => ['bg'=>'rgba(74,144,226,.14)','bd'=>'rgba(74,144,226,.25)','tx'=>'#1F3A5F','dot'=>'#4A90E2'],
  ];
  $c = $map[$tone] ?? $map['info'];
@endphp

<span {{ $attributes->merge(['class'=>$base]) }}
      style="background: {{ $c['bg'] }}; border-color: {{ $c['bd'] }}; color: {{ $c['tx'] }};">
  <span class="h-2 w-2 rounded-full" style="background: {{ $c['dot'] }};"></span>
  {{ $slot }}
</span>
