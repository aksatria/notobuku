@extends('layouts.notobuku')

@section('title', 'Dokumentasi Policy MARC • NOTOBUKU')

@section('content')
<div class="mx-auto max-w-4xl px-4 pb-16 pt-10">
  <div class="rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-6">
    <div class="text-xs font-semibold text-[var(--nb-muted)]">NOTOBUKU • Dokumentasi</div>
    <h1 class="mt-2 text-2xl font-extrabold">Policy MARC</h1>
    <p class="mt-2 text-sm text-[var(--nb-muted)]">
      Ringkasan cara kerja policy validasi MARC, rule kunci, dan praktik terbaik.
    </p>
  </div>

  <div class="mt-6 grid gap-4">
    <div class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-5">
      <div class="text-sm font-extrabold">Severity</div>
      <div class="mt-2 text-sm text-[var(--nb-text)]">
        `warn` = boleh export, dicatat di audit. `error` = blok export.
      </div>
    </div>

    <div class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-5">
      <div class="text-sm font-extrabold">Rule Kunci</div>
      <div class="mt-2 text-sm text-[var(--nb-text)]">
        Serial: `serial_008_missing`, `serial_008_detail_missing`, `serial_362_missing`, `serial_588_missing`.
      </div>
      <div class="mt-1 text-sm text-[var(--nb-text)]">
        Authority: `authority_missing`, `authority_uri_missing`, `authority_dedup`.
      </div>
      <div class="mt-1 text-sm text-[var(--nb-text)]">
        Relator: `relator_uncontrolled`, `relator_missing`.
      </div>
      <div class="mt-1 text-sm text-[var(--nb-text)]">
        Konsistensi: `control_field_mismatch`.
      </div>
    </div>

    <div class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-5">
      <div class="text-sm font-extrabold">Authority URI Priority</div>
      <div class="mt-2 text-sm text-[var(--nb-text)]">
        Urutan `$1` default: LCNAF → VIAF → ISNI → Wikidata → URI.
      </div>
      <div class="mt-1 text-sm text-[var(--nb-muted)]">
        Konfigurasi di `config/marc.php`: `authority_source_priority`, `authority_uri_map`.
      </div>
    </div>

    <div class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-5">
      <div class="text-sm font-extrabold">Serial 008 (Ringkas)</div>
      <div class="mt-2 text-sm text-[var(--nb-text)]">
        008/18–19: frequency & regularity. 008/21: type of continuing resource.
      </div>
      <div class="mt-1 text-sm text-[var(--nb-text)]">
        008/23: form of item. Online serial seharusnya `o`.
      </div>
    </div>

    <div class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-5">
      <div class="text-sm font-extrabold">Holdings Summary</div>
      <div class="mt-2 text-sm text-[var(--nb-text)]">
        866/867/868 dipakai untuk ringkasan koleksi, suplemen, dan indeks.
      </div>
      <div class="mt-1 text-sm text-[var(--nb-muted)]">
        Diisi dari field `holdings_summary`, `holdings_supplement`, `holdings_index`.
      </div>
    </div>

    <div class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-5">
      <div class="text-sm font-extrabold">Contoh Payload</div>
      <pre class="mt-3 whitespace-pre-wrap rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-3 text-xs">{"rules":{"relator_uncontrolled":"warn","audio_missing_narrator":"warn","serial_008_detail_missing":"warn","authority_uri_missing":"warn"}}</pre>
    </div>
  </div>
</div>
@endsection
