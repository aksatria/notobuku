{{-- resources/views/admin/marc-settings.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Pengaturan MARC - NOTOBUKU')

@php
  $defaultCity = json_encode($defaults['place_codes_city'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $defaultCountry = json_encode($defaults['place_codes_country'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $defaultProfiles = json_encode($defaults['media_profiles'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $defaultPolicy = json_encode($policyDefault ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $presetBalanced = json_encode(($policyPresets['balanced'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $presetStrict = json_encode(($policyPresets['strict'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $presetStrictInstitution = json_encode(($policyPresets['strict_institution'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

  $cityValue = old('place_codes_city', !empty($overrides['place_codes_city']) ? json_encode($overrides['place_codes_city'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $defaultCity);
  $countryValue = old('place_codes_country', !empty($overrides['place_codes_country']) ? json_encode($overrides['place_codes_country'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $defaultCountry);
  $profilesValue = old('media_profiles', !empty($overrides['media_profiles']) ? json_encode($overrides['media_profiles'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $defaultProfiles);
  $ddcEditionValue = old('ddc_edition', $overrides['ddc_edition'] ?? $defaults['ddc_edition'] ?? '23');
  $ddcModeValue = old('ddc_rules_validation_mode', $overrides['ddc_rules_validation_mode'] ?? $defaults['ddc_rules_validation_mode'] ?? 'warn');
  $onlineDetectionValue = old('online_detection_mode', $overrides['online_detection_mode'] ?? $defaults['online_detection_mode'] ?? 'strict');
  $policyNameValue = old('policy_name', $policyDraft?->name ?? $policyPublished?->name ?? 'RDA Core');
  $policyPayloadValue = old('policy_payload',
    !empty($policyDraft?->payload_json) ? json_encode($policyDraft->payload_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $defaultPolicy
  );
@endphp

@section('content')
<style>
  .nb-marc-page {
    max-width: 1180px;
    margin: 0 auto;
    color: var(--nb-text, #0b2545);
  }
  .nb-marc-page * { box-sizing: border-box; }
  .nb-marc-page .hidden { display: none !important; }
  .nb-marc-page .overflow-hidden { overflow: hidden; }
  .nb-marc-page .overflow-auto { overflow: auto; }
  .nb-marc-page .rounded-3xl { border-radius: 22px; }
  .nb-marc-page .rounded-2xl { border-radius: 16px; }
  .nb-marc-page .rounded-xl { border-radius: 12px; }
  .nb-marc-page .rounded-lg { border-radius: 10px; }
  .nb-marc-page .rounded-md { border-radius: 8px; }
  .nb-marc-page .rounded-full { border-radius: 999px; }
  .nb-marc-page .border { border: 1px solid var(--nb-border, rgba(148,163,184,.35)); }
  .nb-marc-page .shadow-sm { box-shadow: 0 8px 20px rgba(15, 23, 42, .06); }
  .nb-marc-page .bg-white,
  .nb-marc-page .bg-\[var\(--nb-surface\)\],
  .nb-marc-page .bg-\[var\(--nb-card\)\] { background: var(--nb-card, #fff); }
  .nb-marc-page .bg-white\/70 { background: rgba(255,255,255,.7); }
  .nb-marc-page .bg-white\/80 { background: rgba(255,255,255,.8); }
  .nb-marc-page .p-6 { padding: 24px; }
  .nb-marc-page .p-4 { padding: 16px; }
  .nb-marc-page .p-3 { padding: 12px; }
  .nb-marc-page .p-2 { padding: 8px; }
  .nb-marc-page .px-6 { padding-left: 24px; padding-right: 24px; }
  .nb-marc-page .px-5 { padding-left: 20px; padding-right: 20px; }
  .nb-marc-page .px-4 { padding-left: 16px; padding-right: 16px; }
  .nb-marc-page .px-3 { padding-left: 12px; padding-right: 12px; }
  .nb-marc-page .py-3 { padding-top: 12px; padding-bottom: 12px; }
  .nb-marc-page .py-2 { padding-top: 8px; padding-bottom: 8px; }
  .nb-marc-page .py-1\.5 { padding-top: 6px; padding-bottom: 6px; }
  .nb-marc-page .py-1 { padding-top: 4px; padding-bottom: 4px; }
  .nb-marc-page .py-0\.5 { padding-top: 2px; padding-bottom: 2px; }
  .nb-marc-page .mt-1 { margin-top: 4px; }
  .nb-marc-page .mt-2 { margin-top: 8px; }
  .nb-marc-page .mt-3 { margin-top: 12px; }
  .nb-marc-page .mt-4 { margin-top: 16px; }
  .nb-marc-page .mb-1 { margin-bottom: 4px; }
  .nb-marc-page .mb-2 { margin-bottom: 8px; }
  .nb-marc-page .mb-3 { margin-bottom: 12px; }
  .nb-marc-page .m-0 { margin: 0; }
  .nb-marc-page .w-full { width: 100%; }
  .nb-marc-page .h-12 { height: 48px; }
  .nb-marc-page .w-12 { width: 48px; }
  .nb-marc-page .h-4 { height: 16px; }
  .nb-marc-page .w-4 { width: 16px; }
  .nb-marc-page .text-xl { font-size: 1.375rem; line-height: 1.35; }
  .nb-marc-page .text-lg { font-size: 1.0625rem; }
  .nb-marc-page .text-sm { font-size: .875rem; line-height: 1.45; }
  .nb-marc-page .text-xs { font-size: .8125rem; line-height: 1.4; }
  .nb-marc-page .text-\[11px\] { font-size: 11px; line-height: 1.4; }
  .nb-marc-page .font-extrabold { font-weight: 800; }
  .nb-marc-page .font-bold { font-weight: 700; }
  .nb-marc-page .font-semibold { font-weight: 600; }
  .nb-marc-page .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
  .nb-marc-page .leading-relaxed { line-height: 1.6; }
  .nb-marc-page .underline { text-decoration: underline; }
  .nb-marc-page .grid { display: grid; }
  .nb-marc-page .grid-cols-1 { grid-template-columns: 1fr; }
  .nb-marc-page .grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .nb-marc-page .gap-2 { gap: 8px; }
  .nb-marc-page .gap-3 { gap: 12px; }
  .nb-marc-page .gap-4 { gap: 16px; }
  .nb-marc-page .space-y-2 > * + * { margin-top: 8px; }
  .nb-marc-page .space-y-1 > * + * { margin-top: 4px; }
  .nb-marc-page .flex { display: flex; }
  .nb-marc-page .flex-wrap { flex-wrap: wrap; }
  .nb-marc-page .items-center { align-items: center; }
  .nb-marc-page .items-end { align-items: flex-end; }
  .nb-marc-page .justify-center { justify-content: center; }
  .nb-marc-page .self-center { align-self: center; }
  .nb-marc-page .cursor-pointer { cursor: pointer; }
  .nb-marc-page .nb-stack { display: grid; gap: 14px; }
  .nb-marc-page .nb-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
  }
  .nb-marc-page .nb-row-left {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
  }
  .nb-marc-page .nb-row-right { display: flex; gap: 8px; flex-wrap: wrap; }
  .nb-marc-page textarea,
  .nb-marc-page input[type="text"],
  .nb-marc-page input[type="number"],
  .nb-marc-page input[type="date"],
  .nb-marc-page select {
    width: 100%;
    border: 1px solid var(--nb-border, rgba(148,163,184,.35));
    background: #fff;
    border-radius: 10px;
    padding: 8px 10px;
    font-size: 13px;
    color: var(--nb-text, #0b2545);
  }
  .nb-marc-page textarea { min-height: 110px; }
  .nb-marc-page button { cursor: pointer; }
  .nb-marc-page pre { white-space: pre-wrap; word-break: break-word; }
  .nb-marc-page details summary { list-style: none; }
  .nb-marc-page details summary::-webkit-details-marker { display: none; }
  @media (min-width: 768px) {
    .nb-marc-page .md\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .nb-marc-page .md\:grid-cols-6 { grid-template-columns: repeat(6, minmax(0, 1fr)); }
    .nb-marc-page .md\:col-span-1 { grid-column: span 1 / span 1; }
    .nb-marc-page .md\:col-span-2 { grid-column: span 2 / span 2; }
    .nb-marc-page .md\:col-span-6 { grid-column: span 6 / span 6; }
    .nb-marc-page .md\:mt-7 { margin-top: 28px; }
  }
  @media (min-width: 1024px) {
    .nb-marc-page .lg\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
</style>
<div class="nb-marc-page rounded-3xl border border-[var(--nb-border)] bg-[var(--nb-card)] shadow-sm overflow-hidden">
  <div style="height:6px;background:var(--nb-grad)"></div>
  <div class="p-6 nb-stack">
    <div class="nb-row">
      <div class="nb-row-left">
        <div class="h-12 w-12 rounded-2xl flex items-center justify-center"
             style="background: var(--nb-soft-blue); color: var(--nb-blue);">
          <span class="text-lg font-extrabold">MARC</span>
        </div>
        <div>
          <h1 class="text-xl font-extrabold m-0">Pengaturan MARC</h1>
          <div class="text-sm text-[var(--nb-muted)]">
            Kelola mapping tempat terbit dan media profile tanpa deploy.
          </div>
        </div>
      </div>
    </div>

    @if(session('success'))
      <div class="rounded-2xl p-3 border border-green-200 bg-green-50 text-green-700 text-sm font-semibold">
        {{ session('success') }}
      </div>
    @endif

    @if($errors->any())
      <div class="rounded-2xl p-3 border border-red-200 bg-red-50 text-red-700 text-sm font-semibold">
        Ada JSON yang tidak valid. Perbaiki lalu simpan ulang.
      </div>
    @endif

    <form method="POST" action="{{ route('admin.marc.settings.update') }}" class="nb-stack">
      @csrf

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-4">
          <div class="text-sm font-extrabold mb-1">Kode Tempat: Kota</div>
          <div class="text-xs text-[var(--nb-muted)] mb-2">
            Format JSON objek. Contoh: {"jakarta":"io","london":"xxk"}
          </div>
          <textarea name="place_codes_city" rows="16" data-json-field="place_codes_city"
                  class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-3 text-sm font-mono">{{ $cityValue }}</textarea>
          <div class="text-xs mt-2 text-red-600 hidden" data-json-error="place_codes_city"></div>
        </div>

        <div class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-4">
          <div class="text-sm font-extrabold mb-1">Kode Tempat: Negara</div>
          <div class="text-xs text-[var(--nb-muted)] mb-2">
            Format JSON objek. Contoh: {"indonesia":"io","united states":"xxu"}
          </div>
          <textarea name="place_codes_country" rows="16" data-json-field="place_codes_country"
                  class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-3 text-sm font-mono">{{ $countryValue }}</textarea>
          <div class="text-xs mt-2 text-red-600 hidden" data-json-error="place_codes_country"></div>
        </div>
      </div>

      <div class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-4">
        <div class="text-sm font-extrabold mb-1">Profil Media</div>
        <div class="text-xs text-[var(--nb-muted)] mb-2">
          Format JSON array. Contoh: [{"name":"video","keywords":["video"],"pattern_006":"g                 ","pattern_007":"vd","pattern_008":"{entered}{status}{date1}{date2}{place}                {lang}  "}]
        </div>
        <div class="text-[11px] text-[var(--nb-muted)] mb-2">
          Panduan pattern_008 (40 karakter): pos 00-05=entered, 06=status, 07-10=date1, 11-14=date2, 15-17=place, 23=form of item, 35-37=lang.
          pattern_007 sebaiknya lebih panjang (sesuai jenis) - bisa pakai spasi untuk posisi yang tidak diketahui.
        </div>
        <textarea name="media_profiles" rows="16" data-json-field="media_profiles"
                  class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-3 text-sm font-mono">{{ $profilesValue }}</textarea>
        <div class="text-xs mt-2 text-red-600 hidden" data-json-error="media_profiles"></div>
        <div class="text-xs mt-2 text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2 hidden"
             data-profile-warning></div>
      </div>

      <div class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-4">
        <div class="text-sm font-extrabold mb-1">DDC Rules</div>
        <div class="text-xs text-[var(--nb-muted)] mb-3">Atur edisi DDC dan mode validasi.</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">DDC Edition (082 $2)</div>
            <input type="text" name="ddc_edition"
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="{{ $ddcEditionValue }}" placeholder="23" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">DDC Validation Mode</div>
            <select name="ddc_rules_validation_mode"
                    class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm">
              <option value="warn" @selected($ddcModeValue === 'warn')>warn (bawaan)</option>
              <option value="error" @selected($ddcModeValue === 'error')>error (blokir ekspor)</option>
            </select>
          </div>
        </div>
        <div class="mt-3">
          <div class="text-xs text-[var(--nb-muted)] mb-1">Mode Deteksi Online</div>
          <select name="online_detection_mode"
                  class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm">
            <option value="strict" @selected($onlineDetectionValue === 'strict')>strict (recommended)</option>
            <option value="loose" @selected($onlineDetectionValue === 'loose')>loose</option>
          </select>
          <div class="text-[11px] text-[var(--nb-muted)] mt-1">
            Strict: online hanya jika ada identifier skema uri/url (value/uri terisi).
            Loose: online juga bisa dipicu kata kunci (ebook/online/computer/digital) atau ada uri tanpa skema.
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-4">
        <div class="text-sm font-extrabold mb-1">Kebijakan MARC</div>
        <div class="text-xs text-[var(--nb-muted)] mb-3">
          Kebijakan menentukan tingkat aturan (warn/error) untuk validasi MARC. Draft harus dipublikasikan agar aktif.
        </div>
        @if($canGlobalPolicy)
          <div class="mb-3">
            <div class="text-xs text-[var(--nb-muted)] mb-1">Cakupan Kebijakan</div>
            <select name="policy_scope" id="policy_scope"
                    class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm">
              <option value="institution" @selected(($policyScope ?? 'institution') === 'institution')>Institusi Saat Ini</option>
              <option value="global" @selected(($policyScope ?? '') === 'global')>Global (cadangan)</option>
            </select>
            <div class="text-[11px] text-[var(--nb-muted)] mt-1">
              Global dipakai jika institusi belum punya kebijakan terpublikasi.
            </div>
          </div>
        @endif
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Nama Kebijakan</div>
            <input type="text" name="policy_name"
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="{{ $policyNameValue }}" placeholder="RDA Core" />
          </div>
          <div class="text-xs text-[var(--nb-muted)] md:mt-7">
            @if($policyPublished)
              Terpublikasi v{{ $policyPublished->version }} ({{ $policyPublished->name }})
            @else
              Belum ada kebijakan terpublikasi.
            @endif
          </div>
        </div>
        <div class="mt-3">
          <div class="text-xs text-[var(--nb-muted)] mb-1">Muatan JSON</div>
          <textarea name="policy_payload" rows="10"
                    class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-3 text-sm font-mono">{{ $policyPayloadValue }}</textarea>
          <div class="text-[11px] text-[var(--nb-muted)] mt-1">
            Contoh: {"rules":{"relator_uncontrolled":"warn","audio_missing_narrator":"warn","serial_frequency_missing":"warn","ddc_missing_call_number":"warn"}}
          </div>
          <div class="mt-2 flex flex-wrap gap-2">
            <button type="button"
                    class="rounded-xl px-3 py-1.5 text-[11px] font-bold border border-slate-200 bg-white"
                    data-policy-preset="balanced">
              Preset Balanced
            </button>
            <button type="button"
                    class="rounded-xl px-3 py-1.5 text-[11px] font-bold border border-rose-200 bg-rose-50 text-rose-700"
                    data-policy-preset="strict">
              Preset Ketat
            </button>
            <button type="button"
                    class="rounded-xl px-3 py-1.5 text-[11px] font-bold border border-amber-200 bg-amber-50 text-amber-700"
                    data-policy-preset="strict_institution">
              Ketat (Institusi)
            </button>
            <span class="text-[11px] text-[var(--nb-muted)] self-center">Preset hanya mengisi textarea, belum disimpan.</span>
          </div>
          <div class="text-[11px] text-[var(--nb-muted)] mt-2">
            Panduan policy (ringkas):
          </div>
          <div class="text-[11px] text-[var(--nb-muted)]">
            `warn` = boleh ekspor, muncul di audit. `error` = blok ekspor.
          </div>
          <div class="text-[11px] text-[var(--nb-muted)]">
            Aturan serial: `serial_008_missing`, `serial_008_detail_missing`, `serial_362_missing`, `serial_588_missing`.
          </div>
          <div class="text-[11px] text-[var(--nb-muted)]">
            Aturan authority: `authority_missing`, `authority_uri_missing`, `authority_dedup`.
          </div>
          <div class="text-[11px] text-[var(--nb-muted)]">
            Aturan relator: `relator_uncontrolled`, `relator_missing`.
          </div>
          <div class="text-[11px] text-[var(--nb-muted)]">
            Aturan metadata: `publisher_missing`, `material_type_missing`, `media_type_missing`, `call_number_missing`, `isbn_invalid`, `issn_invalid`, `physical_desc_missing`.
          </div>
          <div class="text-[11px] text-[var(--nb-muted)] mt-2">
            Dokumentasi lengkap: <a href="/docs/marc-policy" class="underline">/docs/marc-policy</a>
          </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
          <button type="submit"
                  formaction="{{ route('admin.marc.policy.draft') }}"
                  class="rounded-2xl px-4 py-2 text-xs font-extrabold"
                  style="background:#0f172a;color:#fff;border:1px solid rgba(15,23,42,.12);">
            Simpan Draft
          </button>
          @if($policyDraft)
            <button type="submit"
                    formaction="{{ route('admin.marc.policy.publish') }}"
                    class="rounded-2xl px-4 py-2 text-xs font-extrabold"
                    style="background:#047857;color:#fff;border:1px solid rgba(4,120,87,.12);">
              Publikasikan Draft v{{ $policyDraft->version }}
            </button>
            <input type="hidden" name="policy_id" value="{{ $policyDraft->id }}" />
          @endif
        </div>
        @php $policyDiff = $policyDiff ?? []; @endphp
        @if(!empty($policyDiff))
          <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
            <div class="font-semibold mb-2">Perbedaan Draft vs Terpublikasi</div>
            <div class="grid grid-cols-1 gap-2">
              <div class="grid grid-cols-3 gap-2 font-semibold">
                <div>Aturan</div>
                <div>Terpublikasi</div>
                <div>Draft</div>
              </div>
              @foreach($policyDiff as $row)
                @php
                  $sev = $row['severity_change'] ?? '';
                  $draftClass = $sev === 'up' ? 'text-rose-700 font-semibold' : ($sev === 'down' ? 'text-emerald-700 font-semibold' : '');
                  $changeType = $row['change_type'] ?? 'changed';
                  $badgeClass = $changeType === 'added' ? 'bg-emerald-100 text-emerald-700' : ($changeType === 'removed' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700');
                  $draftVal = (string) ($row['draft'] ?? '');
                  $sevBadgeClass = $draftVal === 'error' ? 'bg-rose-100 text-rose-700' : ($draftVal === 'warn' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700');
                @endphp
                <details class="rounded-lg border border-amber-100 bg-amber-50/60 p-2">
                  <summary class="grid grid-cols-3 gap-2 cursor-pointer">
                    <div class="font-mono flex items-center gap-2">
                      <span>{{ $row['rule'] }}</span>
                      <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $badgeClass }}">
                        {{ $changeType }}
                      </span>
                    </div>
                    <div>{{ $row['published'] }}</div>
                    <div class="flex items-center gap-2 {{ $draftClass }}">
                      <span>{{ $row['draft'] }}</span>
                      @if($draftVal === 'warn' || $draftVal === 'error')
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $sevBadgeClass }}"
                              title="{{ strtoupper($draftVal) }} severity">
                          {{ strtoupper($draftVal) }}
                        </span>
                      @endif
                    </div>
                  </summary>
                  <div class="mt-2 grid grid-cols-1 gap-2 text-[11px] text-amber-900">
                    <div>
                      <div class="font-semibold">JSON Terpublikasi</div>
                      <pre class="mt-1 rounded-md border border-amber-100 bg-white/80 p-2 overflow-auto">{{ $row['published_json'] ?? '{}' }}</pre>
                    </div>
                    <div>
                    <div class="font-semibold">JSON Draft</div>
                      <pre class="mt-1 rounded-md border border-amber-100 bg-white/80 p-2 overflow-auto">{{ $row['draft_json'] ?? '{}' }}</pre>
                    </div>
                  </div>
                </details>
              @endforeach
            </div>
          </div>
        @endif
      </div>

      <div class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-4">
        <div class="text-sm font-extrabold mb-1">Audit Kebijakan</div>
        <div class="text-xs text-[var(--nb-muted)] mb-3">Riwayat perubahan kebijakan (draft/publikasi).</div>
        @php $policyAudits = $policyAudits ?? collect(); @endphp
          @php $policyAuditUsers = $policyAuditUsers ?? collect(); @endphp
          @php $auditFilters = $auditFilters ?? ['start_date' => null, 'end_date' => null, 'include_global' => '1', 'action' => '', 'status' => '']; @endphp
          @php $csvDefaultColumns = ['id','action','status','user_name','user_email','policy_name','policy_version','institution_id','created_at']; @endphp
          @php $csvSelectedColumns = request()->query('columns', $csvDefaultColumns); @endphp
          @php $csvSelectedColumns = is_array($csvSelectedColumns) ? $csvSelectedColumns : $csvDefaultColumns; @endphp
          @php $csvLimit = (int) request()->query('limit', 500); @endphp
          @php $csvLimit = $csvLimit < 1 ? 500 : ($csvLimit > 1000 ? 1000 : $csvLimit); @endphp
        <div class="mb-3 grid grid-cols-1 md:grid-cols-6 gap-2">
          <div>
            <div class="text-[11px] text-[var(--nb-muted)] mb-1">Tanggal Mulai</div>
            <input type="date" name="start_date"
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-xs"
                   value="{{ $auditFilters['start_date'] ?? '' }}" form="auditFilterForm" />
          </div>
          <div>
            <div class="text-[11px] text-[var(--nb-muted)] mb-1">Tanggal Selesai</div>
            <input type="date" name="end_date"
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-xs"
                   value="{{ $auditFilters['end_date'] ?? '' }}" form="auditFilterForm" />
          </div>
          <div>
            <div class="text-[11px] text-[var(--nb-muted)] mb-1">Aksi</div>
            <select name="action"
                    form="auditFilterForm"
                    class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-xs">
              <option value="">Semua</option>
              <option value="marc_policy_draft" @selected(($auditFilters['action'] ?? '') === 'marc_policy_draft')>draft</option>
              <option value="marc_policy_publish" @selected(($auditFilters['action'] ?? '') === 'marc_policy_publish')>publikasi</option>
            </select>
          </div>
          <div>
            <div class="text-[11px] text-[var(--nb-muted)] mb-1">Status</div>
            <select name="status"
                    form="auditFilterForm"
                    class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-xs">
              <option value="">Semua</option>
              <option value="draft" @selected(($auditFilters['status'] ?? '') === 'draft')>draft</option>
              <option value="published" @selected(($auditFilters['status'] ?? '') === 'published')>terpublikasi</option>
            </select>
          </div>
          <div class="flex items-end">
            <label class="flex items-center gap-2 text-xs text-[var(--nb-muted)]">
              <input type="checkbox" name="include_global" value="1"
                     form="auditFilterForm"
                     class="h-4 w-4 rounded border border-[var(--nb-border)]"
                     @checked(($auditFilters['include_global'] ?? '1') !== '0') />
              Sertakan global
            </label>
          </div>
          <div class="flex items-end">
            <form id="auditFilterForm" method="GET" action="{{ route('admin.marc.settings') }}" class="w-full">
              @if(!empty($policyScope))
                <input type="hidden" name="policy_scope" value="{{ $policyScope }}" />
              @endif
              <button type="submit"
                      class="w-full rounded-xl px-3 py-2 text-xs font-extrabold"
                      style="background:#0f172a;color:#fff;border:1px solid rgba(15,23,42,.12);">
                Terapkan Filter
              </button>
            </form>
          </div>
        </div>
        <div class="mb-3 grid grid-cols-1 md:grid-cols-6 gap-2">
          <form method="GET" action="{{ route('admin.marc.policy.api.audits.csv') }}" class="md:col-span-6 grid grid-cols-1 md:grid-cols-6 gap-2">
            <input type="hidden" name="start_date" value="{{ $auditFilters['start_date'] ?? '' }}" />
            <input type="hidden" name="end_date" value="{{ $auditFilters['end_date'] ?? '' }}" />
            <input type="hidden" name="include_global" value="{{ $auditFilters['include_global'] ?? '1' }}" />
            <input type="hidden" name="action" value="{{ $auditFilters['action'] ?? '' }}" />
            <input type="hidden" name="status" value="{{ $auditFilters['status'] ?? '' }}" />
            @if(!empty($policyScope))
              <input type="hidden" name="policy_scope" value="{{ $policyScope }}" />
            @endif

              <div class="md:col-span-3">
                <div class="flex items-center justify-between mb-1">
                  <div class="text-[11px] text-[var(--nb-muted)]">CSV Columns</div>
                  <div class="flex items-center gap-2">
                    <button type="button" data-columns-select-all
                            class="rounded-full border border-[var(--nb-border)] px-2 py-0.5 text-[10px] font-semibold text-[var(--nb-muted)] hover:text-[var(--nb-text)]">
                      Pilih semua
                    </button>
                    <button type="button" data-columns-clear-all
                            class="rounded-full border border-[var(--nb-border)] px-2 py-0.5 text-[10px] font-semibold text-[var(--nb-muted)] hover:text-[var(--nb-text)]">
                      Kosongkan semua
                    </button>
                  </div>
                </div>
                <select name="columns[]" multiple data-columns-select
                        class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-xs h-28">
                  @foreach(['id','action','status','user_id','user_name','user_email','user_role','policy_id','policy_name','policy_version','institution_id','created_at'] as $col)
                    <option value="{{ $col }}" @selected(in_array($col, $csvSelectedColumns, true))>{{ $col }}</option>
                  @endforeach
                </select>
                <div class="text-[11px] text-[var(--nb-muted)] mt-1">Gunakan Ctrl/Command untuk multi-select.</div>
              </div>
              <div class="md:col-span-1">
                <div class="text-[11px] text-[var(--nb-muted)] mb-1">Limit</div>
                <input type="number" name="limit" min="1" max="1000" value="{{ $csvLimit }}"
                       class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-xs" />
              </div>
            <div class="md:col-span-2 flex items-end">
              <button type="submit"
                      class="w-full rounded-xl px-3 py-2 text-xs font-extrabold"
                      style="background:#111827;color:#fff;border:1px solid rgba(17,24,39,.12);">
                Ekspor CSV (kustom)
              </button>
            </div>
          </form>
        </div>
        @if($policyAudits->count() === 0)
          <div class="text-xs text-[var(--nb-muted)]">Belum ada audit.</div>
        @else
          <div class="space-y-2">
            @foreach($policyAudits as $a)
              @php $u = $policyAuditUsers[$a->user_id] ?? null; @endphp
              <div class="rounded-xl border border-[var(--nb-border)] bg-white/70 p-3 text-xs">
                <div class="font-semibold">
                  {{ $a->action }} - {{ $a->status }}
                </div>
                <div class="text-[var(--nb-muted)]">
                  {{ $a->created_at?->format('Y-m-d H:i') }}
                  @if($u)
                    - {{ $u->name }}
                  @endif
                </div>
                @if(is_array($a->meta))
                  <div class="mt-1 text-[11px] text-[var(--nb-muted)]">
                    Kebijakan: {{ $a->meta['name'] ?? '-' }} v{{ $a->meta['version'] ?? '-' }}
                    @if(isset($a->meta['institution_id']))
                      - Institusi: {{ $a->meta['institution_id'] ?? '-' }}
                    @endif
                  </div>
                @endif
              </div>
            @endforeach
          </div>
        @endif
      </div>

      <div class="rounded-2xl border border-[var(--nb-border)] bg-[var(--nb-surface)] p-4">
        <div class="nb-row">
          <div class="nb-row-left">
            <div class="text-sm font-extrabold">Pratinjau MARC</div>
            <div class="text-xs text-[var(--nb-muted)]">Pratinjau MARCXML berdasarkan mapping yang sedang diedit.</div>
          </div>
          <div class="nb-row-right">
            <button type="button"
                    class="rounded-2xl px-4 py-2 text-xs font-extrabold"
                    style="background:#111827;color:#fff;border:1px solid rgba(17,24,39,.12);"
                    data-marc-preview-btn>
              Buat Pratinjau
            </button>
          </div>
        </div>
        <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Judul</div>
            <input type="text" data-marc-preview-title
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="Pratinjau MARC" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Subtitle</div>
            <input type="text" data-marc-preview-subtitle
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Judul Varian (246)</div>
            <input type="text" data-marc-preview-variant-title
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="" placeholder="Judul alternatif" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Judul Sebelumnya (247)</div>
            <input type="text" data-marc-preview-former-title
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="" placeholder="Judul sebelumnya" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Tempat Terbit</div>
            <input type="text" data-marc-preview-place
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="Jakarta" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Penerbit</div>
            <input type="text" data-marc-preview-publisher
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Tahun</div>
            <input type="number" data-marc-preview-year
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="2024" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Bahasa</div>
            <input type="text" data-marc-preview-language
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="id" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Pengarang</div>
            <input type="text" data-marc-preview-author
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="" placeholder="Nama1, Nama2" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Peran Pengarang</div>
            <select data-marc-preview-author-role
                    class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm">
              <option value="pengarang">Pengarang</option>
              <option value="editor">Editor</option>
              <option value="ilustrator">Ilustrator</option>
              <option value="penerjemah">Penerjemah</option>
              <option value="meeting">Pertemuan</option>
            </select>
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Peran Pengarang (Kustom)</div>
            <input type="text" data-marc-preview-author-role-custom
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="" placeholder="mis. kontributor" />
          </div>
          <div class="md:col-span-2">
            <div class="text-xs text-[var(--nb-muted)] mb-1">Nama Pertemuan (111/711)</div>
            <input type="text" data-marc-preview-meetings
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="" placeholder="Konferensi A; Seminar B" />
            <div class="text-[11px] text-[var(--nb-muted)] mt-1">
              Nama pertemuan akan masuk ke 111 (entri utama) atau 711 (entri tambahan).
            </div>
            <div class="text-[11px] text-[var(--nb-muted)] mt-1 hidden" data-meeting-error></div>
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Responsibility Statement (245$c)</div>
            <input type="text" data-marc-preview-resp
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="" placeholder="Oleh Nama Penulis" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Catatan Isi (505)</div>
            <input type="text" data-marc-preview-contents
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="" placeholder="Bab 1; Bab 2" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Tipe Material</div>
            <input type="text" data-marc-preview-material
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="ebook" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Tipe Media</div>
            <input type="text" data-marc-preview-media
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="online" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Catatan Sitasi (510)</div>
            <input type="text" data-marc-preview-citation
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="" placeholder="Dirujuk dalam..." />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Target Pembaca (521)</div>
            <input type="text" data-marc-preview-audience
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="" placeholder="Dewasa / Anak" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Catatan Bahasa (546)</div>
            <input type="text" data-marc-preview-language-note
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="" placeholder="Teks dalam bahasa Indonesia." />
          </div>
          <div class="md:col-span-2">
            <div class="text-xs text-[var(--nb-muted)] mb-1">Catatan Lokal (590)</div>
            <input type="text" data-marc-preview-local-note
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="" placeholder="Catatan lokal" />
          </div>
          <div class="md:col-span-2">
            <div class="text-xs text-[var(--nb-muted)] mb-1">Subjek (650)</div>
            <input type="text" data-marc-preview-subjects
                   class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm"
                   value="" placeholder="Subjek 1; Subjek 2" />
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Skema Subjek</div>
            <select data-marc-preview-subject-scheme
                    class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm">
              <option value="local">Local</option>
              <option value="lcsh">LCSH</option>
            </select>
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Jenis Subjek (6xx)</div>
            <select data-marc-preview-subject-type
                    class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm">
              <option value="topic">Topik (650)</option>
              <option value="person">Nama Orang (600)</option>
              <option value="corporate">Organisasi (610)</option>
              <option value="meeting">Pertemuan (611)</option>
              <option value="uniform">Judul Seragam (630)</option>
              <option value="geographic">Geografis (651)</option>
            </select>
          </div>
          <div>
            <div class="text-xs text-[var(--nb-muted)] mb-1">Indikator Pertemuan 1 (111/711)</div>
            <select data-marc-preview-meeting-ind1
                    class="w-full rounded-xl border border-[var(--nb-border)] bg-[var(--nb-surface)] px-3 py-2 text-sm">
              <option value=" ">Blank</option>
              <option value="0">0</option>
              <option value="1">1</option>
              <option value="2">2</option>
            </select>
          </div>
          <div class="flex items-center gap-2 mt-6">
            <input type="checkbox" id="meetingForceMain" data-marc-preview-meeting-main
                   class="h-4 w-4 rounded border border-[var(--nb-border)]" />
            <label for="meetingForceMain" class="text-xs text-[var(--nb-muted)]">
              Paksa meeting jadi main entry (111) walau ada author personal.
            </label>
          </div>
        </div>
        <div class="mt-4 rounded-xl border border-[var(--nb-border)] bg-white/70 p-3 text-xs leading-relaxed"
             data-marc-summary>
          Ringkasan MARC akan muncul di sini setelah preview dibuat.
        </div>
        <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 hidden"
             data-marc-validation>
          Isu validasi akan muncul di sini jika ada.
        </div>
        <div class="mt-3 rounded-xl border border-sky-200 bg-sky-50 p-3 text-xs text-sky-900 hidden"
             data-marc-qa>
          QA MARC akan muncul di sini jika ada temuan.
        </div>
        <div class="mt-3 rounded-xl border border-[var(--nb-border)] bg-white/70 p-3 text-xs leading-relaxed hidden"
             data-marc-diff>
          Pratinjau perbedaan akan muncul setelah pratinjau kedua.
        </div>
        <div class="mt-2 hidden" data-marc-diff-actions>
          <button type="button"
                  class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                  data-marc-diff-clear>
            Bersihkan Diff
          </button>
        </div>
        <pre class="mt-3 rounded-xl border border-[var(--nb-border)] bg-white/80 p-3 text-xs leading-relaxed overflow-auto"
             style="max-height:360px"
             data-marc-preview>
Klik "Buat Pratinjau" untuk melihat MARCXML.</pre>
      </div>

      <div class="nb-row">
        <div class="nb-row-left">
          <button type="submit" data-json-submit
                  class="rounded-2xl px-6 py-3 text-sm font-extrabold text-white"
                  style="
                    background: linear-gradient(90deg,#0ea5e9,#2563eb);
                    box-shadow: 0 10px 18px rgba(37,99,235,.25);
                    border: 1px solid rgba(37,99,235,.35);
                  ">
            Simpan Pengaturan
          </button>
        </div>
        <div class="nb-row-right">
          <button type="button"
                  onclick="document.getElementById('marcResetForm').submit()"
                  class="rounded-2xl px-5 py-3 text-sm font-extrabold"
                  style="
                    background: #fff;
                    color: #b91c1c;
                    border: 1px solid rgba(185,28,28,.25);
                  ">
            Reset ke Default
          </button>
        </div>
      </div>
    </form>

    <form id="marcResetForm" method="POST" action="{{ route('admin.marc.settings.reset') }}" class="hidden">
      @csrf
    </form>
  </div>
</div>

<script>
(() => {
  const policyPresets = {
    balanced: {!! $presetBalanced !!},
    strict: {!! $presetStrict !!},
    strict_institution: {!! $presetStrictInstitution !!}
  };
  const policyTextarea = document.querySelector('textarea[name="policy_payload"]');
  const policyNameInput = document.querySelector('input[name="policy_name"]');
  document.querySelectorAll('[data-policy-preset]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const key = btn.getAttribute('data-policy-preset');
      const payload = policyPresets[key];
      if (!payload || !policyTextarea) return;
      policyTextarea.value = JSON.stringify(payload, null, 2);
      if (policyNameInput) {
        policyNameInput.value = key === 'strict'
          ? 'RDA Core (Ketat)'
          : (key === 'strict_institution' ? 'RDA Core (Ketat - Institusi)' : 'RDA Core');
      }
    });
  });

  const fields = [
    { name: 'place_codes_city', type: 'place' },
    { name: 'place_codes_country', type: 'place' },
    { name: 'media_profiles', type: 'profiles' },
  ];

  const submitBtn = document.querySelector('[data-json-submit]');

  function setError(name, message) {
    const el = document.querySelector(`[data-json-error="${name}"]`);
    const input = document.querySelector(`[data-json-field="${name}"]`);
    if (!el) return;
    if (message) {
      el.textContent = message;
      el.classList.remove('hidden');
      if (input) {
        input.style.borderColor = 'rgba(239,68,68,.6)';
        input.style.boxShadow = '0 0 0 4px rgba(239,68,68,.12)';
      }
    } else {
      el.textContent = '';
      el.classList.add('hidden');
      if (input) {
        input.style.borderColor = '';
        input.style.boxShadow = '';
      }
    }
  }

  function parseJson(value) {
    const trimmed = (value || '').trim();
    if (trimmed === '') return { value: [], error: '' };
    try {
      return { value: JSON.parse(trimmed), error: '' };
    } catch (e) {
      let msg = 'JSON tidak valid.';
      const m = String(e && e.message ? e.message : '');
      const posMatch = m.match(/position\s+(\d+)/i);
      if (posMatch && posMatch[1]) {
        const pos = parseInt(posMatch[1], 10);
        if (!Number.isNaN(pos)) {
          const before = trimmed.slice(0, pos);
          const line = before.split('\n').length;
          const col = before.length - before.lastIndexOf('\n');
          msg = `JSON tidak valid di baris ${line}, kolom ${col}.`;
          return { value: null, error: msg, pos };
        }
      }
      return { value: null, error: msg };
    }
  }

  function validatePlace(obj) {
    if (!obj || Array.isArray(obj) || typeof obj !== 'object') return 'Harus JSON object.';
    for (const [k, v] of Object.entries(obj)) {
      if (!k || typeof k !== 'string') return 'Key harus string.';
      if (!v || typeof v !== 'string') return 'Value harus string.';
      const len = v.trim().length;
      if (len < 2 || len > 3) return 'Value harus 2-3 karakter.';
    }
    return '';
  }

  function validateProfiles(arr) {
    if (!Array.isArray(arr)) return 'Harus JSON array.';
    for (const row of arr) {
      if (!row || typeof row !== 'object') return 'Setiap item harus object.';
      if (!row.name || typeof row.name !== 'string') return 'name wajib string.';
      if (!Array.isArray(row.keywords) || row.keywords.length === 0) return 'keywords wajib array non-kosong.';
      if (!row.type_006 || typeof row.type_006 !== 'string' || row.type_006.trim().length !== 1) return 'type_006 wajib 1 karakter.';
      if (!row.type_007 || typeof row.type_007 !== 'string' || row.type_007.trim().length < 2) return 'type_007 wajib min 2 karakter.';
      if (row.pattern_006 && (typeof row.pattern_006 !== 'string' || row.pattern_006.length !== 18)) {
        return 'pattern_006 wajib 18 karakter.';
      }
      if (row.pattern_007 && (typeof row.pattern_007 !== 'string' || row.pattern_007.length < 2)) {
        return 'pattern_007 wajib minimal 2 karakter.';
      }
      const checkPattern008 = (val) => {
        if (!val) return '';
        if (typeof val !== 'string' || val.length !== 40) return 'pattern_008 wajib 40 karakter.';
        const hasEntered = val.includes('{entered}');
        const hasStatus = val.includes('{status}');
        const hasDate1 = val.includes('{date1}');
        const hasDate2 = val.includes('{date2}');
        const hasPlace = val.includes('{place}');
        const hasLang = val.includes('{lang}');
        if (!hasEntered || !hasStatus || !hasDate1 || !hasDate2 || !hasPlace || !hasLang) {
          return 'pattern_008 harus berisi {entered}{status}{date1}{date2}{place}{lang}.';
        }
        return '';
      };
      const p008 = checkPattern008(row.pattern_008);
      if (p008) return p008;
      const p008b = checkPattern008(row.pattern_008_books);
      if (p008b) return p008b;
      const p008cf = checkPattern008(row.pattern_008_cf);
      if (p008cf) return p008cf;
      const p008v = checkPattern008(row.pattern_008_visual);
      if (p008v) return p008v;
      const p008a = checkPattern008(row.pattern_008_audio);
      if (p008a) return p008a;
      const p008m = checkPattern008(row.pattern_008_music);
      if (p008m) return p008m;
    }
    return '';
  }

  function detectProfileWarnings(arr) {
    if (!Array.isArray(arr)) return [];
    const warnings = [];
    arr.forEach((row) => {
      if (!row || typeof row !== 'object') return;
      const name = String(row.name || '').toLowerCase().trim();
      const type006 = String(row.type_006 || '').toLowerCase().trim();
      const type007 = String(row.type_007 || '').toLowerCase().trim();
      const isMusic = type006 === 'j'
        || name.includes('music')
        || name.includes('musik');
      const isAudio = type006 === 'i'
        || (name.includes('audio') && type006 !== 'j')
        || (type007.startsWith('sd') && type006 !== 'j');
      if (isMusic && !row.pattern_008_music) {
        warnings.push(`Profile musik "${row.name || 'tanpa-nama'}" belum punya pattern_008_music.`);
      }
      if (isAudio && !row.pattern_008_audio) {
        warnings.push(`Profile audio "${row.name || 'tanpa-nama'}" belum punya pattern_008_audio.`);
      }
    });
    return warnings;
  }

  function updateProfileWarnings() {
    const box = document.querySelector('[data-profile-warning]');
    const input = document.querySelector('[data-json-field="media_profiles"]');
    if (!box || !input) return;
    const parsed = parseJson(input.value);
    if (parsed.value === null) {
      box.classList.add('hidden');
      box.textContent = '';
      return;
    }
    const warnings = detectProfileWarnings(parsed.value);
    if (warnings.length > 0) {
      box.innerHTML = '<div class="font-semibold mb-1">Peringatan</div>' +
        warnings.map((w) => `<div>- ${w}</div>`).join('');
      box.classList.remove('hidden');
    } else {
      box.classList.add('hidden');
      box.textContent = '';
    }
  }

  function normalizeMeetingNames(input) {
    const raw = String(input || '');
    const parts = raw.split(/[;,\\n]/).map((x) => x.trim()).filter(Boolean);
    const unique = [];
    for (const name of parts) {
      const key = name.toLowerCase();
      if (!unique.find((v) => v.toLowerCase() === key)) {
        unique.push(name);
      }
    }
    return unique;
  }

  function setMeetingError(message) {
    const el = document.querySelector('[data-meeting-error]');
    const input = document.querySelector('[data-marc-preview-meetings]');
    if (!el || !input) return;
    if (message) {
      el.textContent = message;
      el.classList.remove('hidden');
      input.style.borderColor = 'rgba(239,68,68,.6)';
      input.style.boxShadow = '0 0 0 4px rgba(239,68,68,.12)';
    } else {
      el.textContent = '';
      el.classList.add('hidden');
      input.style.borderColor = '';
      input.style.boxShadow = '';
    }
  }

  function renderSummary(xmlText) {
    const summary = document.querySelector('[data-marc-summary]');
    const validationBox = document.querySelector('[data-marc-validation]');
    const qaBox = document.querySelector('[data-marc-qa]');
    const diffBox = document.querySelector('[data-marc-diff]');
    if (!summary) return;
    try {
      const parser = new DOMParser();
      const doc = parser.parseFromString(xmlText, 'application/xml');
      const record = doc.querySelector('record');
      if (!record) {
        summary.textContent = 'Ringkasan tidak tersedia.';
        return;
      }

      const validationIssues = [];
      doc.childNodes.forEach((node) => {
        if (node.nodeType === Node.COMMENT_NODE && String(node.nodeValue || '').includes('VALIDATION ERRORS:')) {
          const text = String(node.nodeValue || '').replace('VALIDATION ERRORS:', '').trim();
          text.split('|').map((x) => x.trim()).filter(Boolean).forEach((msg) => validationIssues.push(msg));
        }
      });

      if (validationBox) {
        if (validationIssues.length > 0) {
          validationBox.classList.remove('hidden');
          validationBox.innerHTML = '<div class="font-semibold mb-2">Isu Validasi</div>' +
            validationIssues.map((m) => `<div>- ${m}</div>`).join('');
        } else {
          validationBox.classList.add('hidden');
          validationBox.textContent = '';
        }
      }

      const lines = [];
      record.querySelectorAll('controlfield').forEach((cf) => {
        const tag = cf.getAttribute('tag') || '';
        const value = (cf.textContent || '').trim();
        if (tag) {
          lines.push(`${tag} ${value}`);
        }
      });

      const highlight008 = (value) => {
        if (!value) return '';
        const parts = value.split('');
        const span = (start, end, cls) => {
          for (let i = start; i <= end; i++) {
            if (parts[i] !== undefined) {
              parts[i] = `<span class="${cls}">${parts[i]}</span>`;
            }
          }
        };
        span(15, 17, 'bg-amber-200');
        span(23, 23, 'bg-emerald-200');
        span(35, 37, 'bg-sky-200');
        return parts.join('');
      };
      const highlight006 = (value) => {
        if (!value) return '';
        const parts = value.split('');
        if (parts[0] !== undefined) {
          parts[0] = `<span class="bg-amber-200">${parts[0]}</span>`;
        }
        return parts.join('');
      };
      const highlight007 = (value) => {
        if (!value) return '';
        const parts = value.split('');
        if (parts[0] !== undefined) parts[0] = `<span class="bg-amber-200">${parts[0]}</span>`;
        if (parts[1] !== undefined) parts[1] = `<span class="bg-amber-200">${parts[1]}</span>`;
        return parts.join('');
      };

      const controlLines = [];
      record.querySelectorAll('controlfield').forEach((cf) => {
        const tag = cf.getAttribute('tag') || '';
        const value = (cf.textContent || '').trim();
        if (!tag) return;
        if (tag === '006') {
          controlLines.push(`${tag} ${highlight006(value)}`);
        } else if (tag === '007') {
          controlLines.push(`${tag} ${highlight007(value)}`);
        } else if (tag === '008') {
          controlLines.push(`${tag} ${highlight008(value)}`);
        } else {
          controlLines.push(`${tag} ${value}`);
        }
      });

      const getSubfieldsText = (tag) => {
        return Array.from(record.querySelectorAll(`datafield[tag="${tag}"]`)).map((df) => {
          const ind1 = df.getAttribute('ind1') || ' ';
          const ind2 = df.getAttribute('ind2') || ' ';
          const subs = Array.from(df.querySelectorAll('subfield')).map((sf) => {
            const code = sf.getAttribute('code') || '';
            const value = (sf.textContent || '').trim();
            return code && value ? `$${code} ${value}` : '';
          }).filter(Boolean).join(' ');
          return `${tag} ${ind1}${ind2} ${subs}`;
        });
      };

      const summaryLines = [
        ...getSubfieldsText('245'),
        ...getSubfieldsText('264'),
        ...getSubfieldsText('300'),
        ...getSubfieldsText('336'),
        ...getSubfieldsText('337'),
        ...getSubfieldsText('338'),
      ];
      const accessPoints = [
        ...getSubfieldsText('100'),
        ...getSubfieldsText('110'),
        ...getSubfieldsText('111'),
        ...getSubfieldsText('700'),
        ...getSubfieldsText('710'),
        ...getSubfieldsText('711'),
      ];
      const subjects = [
        ...getSubfieldsText('600'),
        ...getSubfieldsText('610'),
        ...getSubfieldsText('611'),
        ...getSubfieldsText('630'),
        ...getSubfieldsText('651'),
        ...getSubfieldsText('650'),
      ];

      lines.length = 0;
      lines.push(...summaryLines, ...accessPoints, ...subjects);

      const controlHtml = controlLines.length
        ? '<div class="font-semibold mb-2">Control Fields</div><div class="space-y-1 font-mono">' +
          controlLines.map((l) => `<div>${l}</div>`).join('') +
          '</div>'
        : '';

      const summaryHtml = lines.length
        ? '<div class="font-semibold mb-2">Ringkasan MARC</div><div class="space-y-1">' +
          lines.map((l) => `<div>${l}</div>`).join('') +
          '</div>'
        : 'Ringkasan tidak tersedia.';

      summary.innerHTML = summaryHtml + (controlHtml ? `<div class="mt-3">${controlHtml}</div>` : '');

      if (qaBox) {
        const qaIssues = [];
        const has336 = record.querySelectorAll('datafield[tag="336"]').length > 0;
        const has337 = record.querySelectorAll('datafield[tag="337"]').length > 0;
        const has338 = record.querySelectorAll('datafield[tag="338"]').length > 0;
        if (!has336 || !has337 || !has338) {
          qaIssues.push('RDA 3xx belum lengkap (butuh 336/337/338).');
        }

        const f245 = record.querySelector('datafield[tag="245"]');
        const ind2 = f245?.getAttribute('ind2') ?? '';
        if (!f245) {
          qaIssues.push('245 tidak ditemukan.');
        } else if (ind2 === '') {
          qaIssues.push('245 ind2 tidak terisi (non-filing indicator).');
        }

        const has1xx = record.querySelectorAll('datafield[tag="100"],datafield[tag="110"],datafield[tag="111"]').length > 0;
        const has7xx = record.querySelectorAll('datafield[tag="700"],datafield[tag="710"],datafield[tag="711"]').length > 0;
        if (!has1xx && !has7xx) {
          qaIssues.push('Access point belum ada (1xx/7xx).');
        }

        const df264 = Array.from(record.querySelectorAll('datafield[tag="264"]'));
        const has264 = df264.length > 0;
        if (!has264) {
          qaIssues.push('264 belum ada (publication statement).');
        } else {
          const hasInd2_1 = df264.some((df) => (df.getAttribute('ind2') || '') === '1');
          if (!hasInd2_1) {
            qaIssues.push('264 ind2=1 tidak ditemukan (publication statement).');
          }
        }

        const has300 = record.querySelectorAll('datafield[tag="300"]').length > 0;
        if (!has300) {
          qaIssues.push('300 belum ada (physical description).');
        }

        const accessFields = Array.from(record.querySelectorAll('datafield[tag="100"],datafield[tag="110"],datafield[tag="111"],datafield[tag="700"],datafield[tag="710"],datafield[tag="711"]'));
        if (accessFields.length > 0) {
          const hasRelator = accessFields.some((df) => {
            return df.querySelector('subfield[code="e"],subfield[code="4"]');
          });
          if (!hasRelator) {
            qaIssues.push('Relator ($e/$4) belum ada di access point.');
          }
        }

        const cf008 = record.querySelector('controlfield[tag="008"]')?.textContent || '';
        if (cf008 && cf008.length !== 40) {
          qaIssues.push('008 tidak 40 karakter.');
        }
        if (cf008 && cf008.length >= 38) {
          const lang008 = cf008.slice(35, 38);
          const langs041 = Array.from(record.querySelectorAll('datafield[tag="041"] subfield[code="a"]'))
            .map((sf) => (sf.textContent || '').trim())
            .filter(Boolean);
          if (lang008 && lang008 !== 'und') {
            if (langs041.length === 0) {
              qaIssues.push('041 belum ada padahal 008/35-37 berisi bahasa.');
            } else if (!langs041.includes(lang008)) {
              qaIssues.push(`041 tidak memuat bahasa 008 (${lang008}).`);
            }
          }
        }

        const cf007 = record.querySelector('controlfield[tag="007"]')?.textContent || '';
        const cf006 = record.querySelector('controlfield[tag="006"]')?.textContent || '';
        const cf007Type = (cf007 || '').trim().slice(0, 2);
        const cf006Type = (cf006 || '').trim().slice(0, 1);
        const has310 = record.querySelectorAll('datafield[tag="310"]').length > 0;
        const has321 = record.querySelectorAll('datafield[tag="321"]').length > 0;
        if (cf008 && (cf006Type === 's' || cf007Type.startsWith('cr'))) {
          if (!has310) {
            qaIssues.push('Serial: 310 (current frequency) sebaiknya diisi.');
          }
        }

        const df336 = Array.from(record.querySelectorAll('datafield[tag="336"] subfield[code="a"]'))
          .map((sf) => (sf.textContent || '').trim().toLowerCase())
          .filter(Boolean);
        const df336b = Array.from(record.querySelectorAll('datafield[tag="336"] subfield[code="b"]'))
          .map((sf) => (sf.textContent || '').trim().toLowerCase())
          .filter(Boolean);
        const df336_2 = Array.from(record.querySelectorAll('datafield[tag="336"] subfield[code="2"]'))
          .map((sf) => (sf.textContent || '').trim().toLowerCase())
          .filter(Boolean);
        const df337 = Array.from(record.querySelectorAll('datafield[tag="337"] subfield[code="a"]'))
          .map((sf) => (sf.textContent || '').trim().toLowerCase())
          .filter(Boolean);
        const df337b = Array.from(record.querySelectorAll('datafield[tag="337"] subfield[code="b"]'))
          .map((sf) => (sf.textContent || '').trim().toLowerCase())
          .filter(Boolean);
        const df337_2 = Array.from(record.querySelectorAll('datafield[tag="337"] subfield[code="2"]'))
          .map((sf) => (sf.textContent || '').trim().toLowerCase())
          .filter(Boolean);
        const df338 = Array.from(record.querySelectorAll('datafield[tag="338"] subfield[code="a"]'))
          .map((sf) => (sf.textContent || '').trim().toLowerCase())
          .filter(Boolean);
        const df338b = Array.from(record.querySelectorAll('datafield[tag="338"] subfield[code="b"]'))
          .map((sf) => (sf.textContent || '').trim().toLowerCase())
          .filter(Boolean);
        const df338_2 = Array.from(record.querySelectorAll('datafield[tag="338"] subfield[code="2"]'))
          .map((sf) => (sf.textContent || '').trim().toLowerCase())
          .filter(Boolean);

        const hasAny = (arr, needles) => needles.some((n) => arr.includes(n));
        const hasAll = (arr, needles) => needles.every((n) => arr.includes(n));
        const requireSubfields = (tag, bVals, twoVals, expected2) => {
          if (bVals.length === 0) qaIssues.push(`${tag} subfield $b wajib.`);
          if (twoVals.length === 0) qaIssues.push(`${tag} subfield $2 wajib.`);
          if (expected2 && twoVals.length > 0 && !twoVals.includes(expected2)) {
            qaIssues.push(`${tag} $2 harus "${expected2}".`);
          }
        };

        requireSubfields('336', df336b, df336_2, 'rdacontent');
        requireSubfields('337', df337b, df337_2, 'rdamedia');
        requireSubfields('338', df338b, df338_2, 'rdacarrier');

        if (cf007Type.startsWith('sd') || cf006Type === 'i' || cf006Type === 'j') {
          if (!hasAny(df336, ['spoken word', 'performed music'])) {
            qaIssues.push('336 tidak selaras dengan audio (harus spoken word / performed music).');
          }
          if (!hasAny(df337, ['audio', 'computer'])) {
            qaIssues.push('337 tidak selaras dengan audio (audio/computer).');
          }
          if (!hasAny(df338, ['audio disc', 'online resource'])) {
            qaIssues.push('338 tidak selaras dengan audio (audio disc/online resource).');
          }
        } else if (cf007Type.startsWith('vd') || cf007Type.startsWith('v')) {
          if (!hasAny(df336, ['two-dimensional moving image'])) {
            qaIssues.push('336 tidak selaras dengan video (two-dimensional moving image).');
          }
          if (!hasAny(df337, ['video', 'computer'])) {
            qaIssues.push('337 tidak selaras dengan video (video/computer).');
          }
          if (!hasAny(df338, ['videodisc', 'online resource'])) {
            qaIssues.push('338 tidak selaras dengan video (videodisc/online resource).');
          }
        } else if (cf006Type === 'e' || cf006Type === 'f') {
          if (!hasAny(df336, ['cartographic image'])) {
            qaIssues.push('336 tidak selaras dengan map (cartographic image).');
          }
          if (!hasAny(df337, ['unmediated', 'computer'])) {
            qaIssues.push('337 tidak selaras dengan map (unmediated/computer).');
          }
          if (!hasAny(df338, ['sheet', 'online resource'])) {
            qaIssues.push('338 tidak selaras dengan map (sheet/online resource).');
          }
        } else if (cf006Type === 'm' || cf007Type.startsWith('cr')) {
          if (!hasAny(df337, ['computer']) || !hasAny(df338, ['online resource'])) {
            qaIssues.push('337/338 tidak selaras dengan computer/online resource.');
          }
        }

        const ind2Val = f245?.getAttribute('ind2') ?? '';
        if (f245 && (ind2Val === '' || !/^[0-9]$/.test(ind2Val))) {
          qaIssues.push('245 ind2 harus digit 0-9.');
        }

        const personalTags = ['100', '700'];
        personalTags.forEach((tag) => {
          record.querySelectorAll(`datafield[tag="${tag}"]`).forEach((df) => {
            const ind1 = df.getAttribute('ind1') || '';
            if (ind1 === ' ') {
              qaIssues.push(`${tag} ind1 sebaiknya 0 atau 1 (bukan blank).`);
            }
          });
        });

        const has856 = record.querySelectorAll('datafield[tag="856"] subfield[code="u"]').length > 0;
        if (cf008 && cf008.length >= 24) {
          const formOfItem = cf008[23] || '';
          if (formOfItem === 'o' && !has856) {
            qaIssues.push('008/23=o tapi 856$u tidak ada.');
          }
          if (formOfItem !== 'o' && has856) {
            qaIssues.push('856$u ada tapi 008/23 bukan o (cek online flag).');
          }
        }

        if (has856) {
          const has538 = record.querySelectorAll('datafield[tag="538"]').length > 0;
          if (!has538) {
            qaIssues.push('538 belum ada untuk resource online.');
          }
        }

        if (cf007Type.startsWith('sd')) {
          const has347 = record.querySelectorAll('datafield[tag="347"]').length > 0;
          if (!has347) {
            qaIssues.push('347 belum ada untuk audio (digital file characteristics).');
          }
        }
        if (cf007Type.startsWith('vd') || cf007Type.startsWith('v')) {
          const has347 = record.querySelectorAll('datafield[tag="347"]').length > 0;
          if (!has347) {
            qaIssues.push('347 belum ada untuk video (digital file characteristics).');
          }
        }
        if (cf007Type.startsWith('cr')) {
          const has347 = record.querySelectorAll('datafield[tag="347"]').length > 0;
          if (!has347) {
            qaIssues.push('347 belum ada untuk resource digital (digital file characteristics).');
          }
        }

        if (qaIssues.length > 0) {
          qaBox.classList.remove('hidden');
          qaBox.innerHTML = '<div class="font-semibold mb-2">QA RDA Core</div>' +
            qaIssues.map((m) => `<div>- ${m}</div>`).join('');
        } else {
          qaBox.classList.add('hidden');
          qaBox.textContent = '';
        }
      }

      if (diffBox) {
        if (window.__lastMarcPreviewXml) {
          const oldLines = window.__lastMarcPreviewXml.split('\n');
          const newLines = xmlText.split('\n');
          const lcsTable = Array(oldLines.length + 1).fill(null).map(() => Array(newLines.length + 1).fill(0));
          for (let i = oldLines.length - 1; i >= 0; i--) {
            for (let j = newLines.length - 1; j >= 0; j--) {
              if (oldLines[i] === newLines[j]) {
                lcsTable[i][j] = lcsTable[i + 1][j + 1] + 1;
              } else {
                lcsTable[i][j] = Math.max(lcsTable[i + 1][j], lcsTable[i][j + 1]);
              }
            }
          }
          const diffs = [];
          const diffChars = (a, b, mode) => {
            const max = Math.max(a.length, b.length);
            const out = [];
            for (let k = 0; k < max; k++) {
              const ca = a[k] || '';
              const cb = b[k] || '';
              if (ca === cb) {
                out.push(cb || ca);
              } else {
                if (mode === 'add' && cb) {
                  out.push(`<span class="bg-emerald-100 text-emerald-800">${cb}</span>`);
                } else if (mode === 'del' && ca) {
                  out.push(`<span class="bg-rose-100 text-rose-800">${ca}</span>`);
                }
              }
            }
            return out.join('');
          };
          let i = 0;
          let j = 0;
          while (i < oldLines.length && j < newLines.length) {
            if (oldLines[i] === newLines[j]) {
              diffs.push(`<div class="text-slate-500">  ${oldLines[i]}</div>`);
              i++;
              j++;
            } else if (lcsTable[i + 1][j] >= lcsTable[i][j + 1]) {
              diffs.push(`<div class="text-rose-700">- ${diffChars(oldLines[i] || '', newLines[j] || '', 'del')}</div>`);
              i++;
            } else {
              diffs.push(`<div class="text-emerald-700">+ ${diffChars(oldLines[i] || '', newLines[j] || '', 'add')}</div>`);
              j++;
            }
          }
          while (i < oldLines.length) {
            diffs.push(`<div class="text-rose-700">- ${diffChars(oldLines[i] || '', '', 'del')}</div>`);
            i++;
          }
          while (j < newLines.length) {
            diffs.push(`<div class="text-emerald-700">+ ${diffChars('', newLines[j] || '', 'add')}</div>`);
            j++;
          }
          if (diffs.length > 0) {
            diffBox.classList.remove('hidden');
            diffBox.innerHTML = '<div class="font-semibold mb-2">Pratinjau Perbedaan</div><div class="space-y-1 font-mono">' +
              diffs.join('') +
              '</div>';
            const diffActions = document.querySelector('[data-marc-diff-actions]');
            if (diffActions) diffActions.classList.remove('hidden');
          } else {
            diffBox.classList.add('hidden');
            diffBox.textContent = '';
            const diffActions = document.querySelector('[data-marc-diff-actions]');
            if (diffActions) diffActions.classList.add('hidden');
          }
        }
        window.__lastMarcPreviewXml = xmlText;
      }
    } catch (e) {
      summary.textContent = 'Ringkasan tidak tersedia.';
    }
  }

  async function buildPreview() {
    const out = document.querySelector('[data-marc-preview]');
    if (!out) return;

    const meetingsInput = document.querySelector('[data-marc-preview-meetings]')?.value || '';
    const meetings = normalizeMeetingNames(meetingsInput);
    if (meetingsInput.trim() !== '' && meetings.length === 0) {
      setMeetingError('Nama pertemuan harus berisi minimal 1 nama.');
      return;
    }
    if (meetings.length > 0) {
      setMeetingError('');
    }

      const payload = {
        title: document.querySelector('[data-marc-preview-title]')?.value || '',
        subtitle: document.querySelector('[data-marc-preview-subtitle]')?.value || '',
        variant_title: document.querySelector('[data-marc-preview-variant-title]')?.value || '',
        former_title: document.querySelector('[data-marc-preview-former-title]')?.value || '',
        place_of_publication: document.querySelector('[data-marc-preview-place]')?.value || '',
        publisher: document.querySelector('[data-marc-preview-publisher]')?.value || '',
      publish_year: parseInt(document.querySelector('[data-marc-preview-year]')?.value || '0', 10) || null,
      language: document.querySelector('[data-marc-preview-language]')?.value || '',
      author: document.querySelector('[data-marc-preview-author]')?.value || '',
      author_role: (document.querySelector('[data-marc-preview-author-role-custom]')?.value || '').trim()
        || (document.querySelector('[data-marc-preview-author-role]')?.value || ''),
      responsibility_statement: document.querySelector('[data-marc-preview-resp]')?.value || '',
      contents_note: document.querySelector('[data-marc-preview-contents]')?.value || '',
      subjects: document.querySelector('[data-marc-preview-subjects]')?.value || '',
      subject_scheme: document.querySelector('[data-marc-preview-subject-scheme]')?.value || '',
      subject_type: document.querySelector('[data-marc-preview-subject-type]')?.value || '',
      material_type: document.querySelector('[data-marc-preview-material]')?.value || '',
      media_type: document.querySelector('[data-marc-preview-media]')?.value || '',
      citation_note: document.querySelector('[data-marc-preview-citation]')?.value || '',
      audience_note: document.querySelector('[data-marc-preview-audience]')?.value || '',
      language_note: document.querySelector('[data-marc-preview-language-note]')?.value || '',
      local_note: document.querySelector('[data-marc-preview-local-note]')?.value || '',
      meeting_names: meetings.join('; '),
      meeting_ind1: document.querySelector('[data-marc-preview-meeting-ind1]')?.value || ' ',
      force_meeting_main: document.querySelector('[data-marc-preview-meeting-main]')?.checked || false,
    };

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    out.textContent = 'Generating preview...';
    try {
      const res = await fetch("{{ route('admin.marc.settings.preview') }}", {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/xml',
          'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });

      const contentType = res.headers.get('content-type') || '';
      const text = await res.text();
      if (!res.ok) {
        out.textContent = 'Gagal generate preview: ' + text;
        return;
      }
      if (!contentType.includes('xml') || text.trim().toLowerCase().startsWith('<!doctype html')) {
        out.textContent = 'Gagal generate preview: sesi login mungkin habis. Silakan refresh dan coba lagi.';
        return;
      }
      renderSummary(text);
      out.textContent = text;
    } catch (e) {
      out.textContent = 'Gagal generate preview.';
    }
  }

  function validateAll() {
    let hasError = false;
    for (const f of fields) {
      const input = document.querySelector(`[data-json-field="${f.name}"]`);
      if (!input) continue;
      const parsed = parseJson(input.value);
      if (parsed.value === null) {
        setError(f.name, parsed.error || 'JSON tidak valid.');
        if (typeof parsed.pos === 'number') {
          const input = document.querySelector(`[data-json-field="${f.name}"]`);
          if (input) {
            const pos = Math.min(parsed.pos, input.value.length);
            input.focus();
            input.setSelectionRange(pos, pos);
          }
        }
        hasError = true;
        continue;
      }
      const err = f.type === 'place' ? validatePlace(parsed.value) : validateProfiles(parsed.value);
      setError(f.name, err);
      if (err) hasError = true;
    }
    updateProfileWarnings();
    if (submitBtn) submitBtn.disabled = hasError;
    return !hasError;
  }

  function updateMeetingControls() {
    const meetingsInput = document.querySelector('[data-marc-preview-meetings]');
    const meetingInd1 = document.querySelector('[data-marc-preview-meeting-ind1]');
    const meetingMain = document.querySelector('[data-marc-preview-meeting-main]');
    if (!meetingsInput || !meetingInd1 || !meetingMain) return;

    const hasMeetings = meetingsInput.value.trim() !== '';
    meetingInd1.disabled = !hasMeetings;
    meetingMain.disabled = !hasMeetings;
    if (!hasMeetings) {
      meetingInd1.value = ' ';
      meetingMain.checked = false;
      setMeetingError('');
    }
  }

  fields.forEach((f) => {
    const input = document.querySelector(`[data-json-field="${f.name}"]`);
    if (!input) return;
    input.addEventListener('input', () => validateAll());
  });

  const meetingsInput = document.querySelector('[data-marc-preview-meetings]');
  if (meetingsInput) {
    meetingsInput.addEventListener('input', () => {
      const meetings = normalizeMeetingNames(meetingsInput.value);
      if (meetingsInput.value.trim() !== '' && meetings.length === 0) {
        setMeetingError('Nama pertemuan harus berisi minimal 1 nama.');
      } else {
        setMeetingError('');
      }
      updateMeetingControls();
    });
  }

  const previewBtn = document.querySelector('[data-marc-preview-btn]');
  if (previewBtn) {
    previewBtn.addEventListener('click', buildPreview);
  }

  const diffClearBtn = document.querySelector('[data-marc-diff-clear]');
  if (diffClearBtn) {
    diffClearBtn.addEventListener('click', () => {
      window.__lastMarcPreviewXml = '';
      const diffBox = document.querySelector('[data-marc-diff]');
      const diffActions = document.querySelector('[data-marc-diff-actions]');
      if (diffBox) {
        diffBox.classList.add('hidden');
        diffBox.textContent = '';
      }
      if (diffActions) diffActions.classList.add('hidden');
    });
  }

  const columnsSelect = document.querySelector('[data-columns-select]');
  const columnsSelectAll = document.querySelector('[data-columns-select-all]');
  const columnsClearAll = document.querySelector('[data-columns-clear-all]');
  if (columnsSelect && columnsSelectAll && columnsClearAll) {
    const setAllColumns = (value) => {
      Array.from(columnsSelect.options).forEach((option) => {
        option.selected = value;
      });
    };
    columnsSelectAll.addEventListener('click', () => setAllColumns(true));
    columnsClearAll.addEventListener('click', () => setAllColumns(false));
  }

  updateMeetingControls();
  validateAll();
  updateProfileWarnings();
})();
</script>
@if($canGlobalPolicy)
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var scope = document.getElementById('policy_scope');
    if (!scope) return;
    scope.addEventListener('change', function () {
      var url = new URL(window.location.href);
      url.searchParams.set('policy_scope', this.value);
      window.location.href = url.toString();
    });
  });
</script>
@endif
@endsection

