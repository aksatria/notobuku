{{-- resources/views/member/profil.blade.php --}}
@extends('layouts.notobuku')

@section('title', 'Profil • NOTOBUKU')

@section('content')
@php
  $user = $user ?? auth()->user();
  $member = $member ?? null;
  $profile = $profile ?? null;

  $fmtDate = function ($date) {
    if (!$date) return '—';
    try { return \Carbon\Carbon::parse($date)->format('d M Y'); } catch (\Throwable $e) { return '—'; }
  };

  $initials = function ($name) {
    $name = trim((string)$name);
    if ($name === '') return 'MB';
    $parts = preg_split('/\s+/', $name);
    $a = strtoupper(substr($parts[0] ?? 'M', 0, 1));
    $b = strtoupper(substr($parts[count($parts)-1] ?? 'B', 0, 1));
    return $a.$b;
  };

  $statusLabel = function ($status) {
    $s = strtolower((string)$status);
    return match($s) {
      'active' => ['text'=>'Aktif', 'kind'=>'ok'],
      'inactive' => ['text'=>'Nonaktif', 'kind'=>'neu'],
      'banned' => ['text'=>'Diblokir', 'kind'=>'warn'],
      default => ['text'=>($status ?: '—'), 'kind'=>'neu'],
    };
  };

  $pill = function(string $text, string $kind) {
    $map = [
      'ok'   => 'background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.24);color:rgba(21,128,61,.95);',
      'warn' => 'background:rgba(251,140,0,.12);border-color:rgba(251,140,0,.26);color:rgba(180,83,9,.95);',
      'info' => 'background:rgba(30,136,229,.10);border-color:rgba(30,136,229,.22);color:rgba(21,101,192,.95);',
      'neu'  => 'background:rgba(2,6,23,.04);border-color:rgba(2,6,23,.08);color:rgba(31,41,55,.85);',
    ];
    $style = $map[$kind] ?? $map['neu'];
    return '<span class="nb-pill" style="'.$style.'">'.$text.'</span>';
  };

  $stat = $member ? $statusLabel($member->status ?? null) : ['text'=>'—', 'kind'=>'neu'];
  $public = (bool)($profile->is_public ?? false);

  $avatarUrl = null;
  if (!empty($profile->avatar_path ?? null)) {
    $avatarUrl = asset('storage/'.$profile->avatar_path);
  }
@endphp

<style>
  /* =========================
     PROFIL MEMBER — Konsisten dengan Dashboard & Beranda
     - palette soft, TANPA gradasi
     - mobile-first, no horizontal scroll
     ========================= */

  :root{
    --nb-bg:#F6F8FC;
    --nb-card:rgba(255,255,255,.94);
    --nb-surface:rgba(255,255,255,.78);
    --nb-border:rgba(15,23,42,.10);
    --nb-text:rgba(11,37,69,.92);
    --nb-sub:rgba(11,37,69,.64);
    --nb-muted:rgba(11,37,69,.54);
    --nb-shadow:0 12px 30px rgba(2,6,23,.06);

    --nb-primary:#1E88E5;
    --nb-primary-2:#1565C0;

    /* soft card backgrounds (bukan border) */
    --tint-blue: rgba(30,136,229,.08);
    --tint-green: rgba(46,125,50,.08);
    --tint-orange: rgba(251,140,0,.10);
    --tint-slate: rgba(148,163,184,.14);
  }

  html.dark, body.dark, .dark{
    --nb-bg:#0B1220;
    --nb-card:rgba(15,23,42,.60);
    --nb-surface:rgba(15,23,42,.50);
    --nb-border:rgba(148,163,184,.16);
    --nb-text:rgba(226,232,240,.92);
    --nb-sub:rgba(226,232,240,.64);
    --nb-muted:rgba(226,232,240,.54);
    --nb-shadow:0 14px 34px rgba(0,0,0,.32);

    --tint-blue: rgba(30,136,229,.18);
    --tint-green: rgba(46,125,50,.18);
    --tint-orange: rgba(251,140,0,.22);
    --tint-slate: rgba(148,163,184,.12);
  }

  html, body{ max-width:100%; overflow-x:hidden; }
  .nb-wrap{ max-width:1100px; margin:0 auto; padding: 0; }
  @media(max-width: 520px){ .nb-wrap{ padding: 0 12px; } }

  .nb-panel{
    margin: 12px 0 22px;
    background: var(--nb-card);
    border: 1px solid var(--nb-border);
    border-radius: 22px;
    box-shadow: var(--nb-shadow);
    overflow:hidden;
  }

  .nb-panel-topbar{
    height:2px;
    background: rgba(15,23,42,.08); /* tipis, bukan garis tebal */
  }
  html.dark .nb-panel-topbar{ background: rgba(148,163,184,.16); }

  .nb-head{
    padding: 16px 16px 12px;
    border-bottom:1px solid var(--nb-border);
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    flex-wrap:wrap;
  }
  .nb-title{ font-size:18px; font-weight:700; color:var(--nb-text); line-height:1.15; letter-spacing:.1px; }
  .nb-subtitle{ margin-top:4px; font-size:13px; font-weight:500; color:var(--nb-sub); line-height:1.35; }

  .nb-actions{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
  .nb-btn{
    display:inline-flex; align-items:center; justify-content:center;
    height:42px;
    padding:0 14px;
    border-radius: 14px;
    border: 1px solid var(--nb-border);
    background: rgba(255,255,255,.86);
    color: var(--nb-text);
    font-size: 13px;
    font-weight: 600;
    text-decoration:none;
    white-space:nowrap;
  }
  html.dark .nb-btn{ background: rgba(15,23,42,.45); }
  .nb-btn.primary{
    background: var(--nb-primary);
    border-color: rgba(30,136,229,.30);
    color:#fff;
  }
  .nb-btn.primary:hover{ background: var(--nb-primary-2); }
  .nb-btn.ghost{ background: transparent; }

  .nb-section{ padding: 14px 16px; border-top:1px solid var(--nb-border); }
  .nb-h2{
    font-size:14px; font-weight:700; color:var(--nb-text);
    display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
  }
  .nb-hint{ margin-top:6px; font-size:12.5px; font-weight:500; color:var(--nb-sub); }

  .nb-pill{
    display:inline-flex; align-items:center; gap:6px;
    padding: 5px 10px;
    border-radius: 999px;
    border: 1px solid var(--nb-border);
    font-size: 12px;
    font-weight: 600;
    white-space:nowrap;
  }

  .nb-grid{
    display:grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-top: 12px;
  }
  @media(min-width: 900px){
    .nb-grid{ grid-template-columns: 1fr 1fr; }
  }

  .nb-card{
    border:1px solid var(--nb-border);
    border-radius:18px;
    background: rgba(255,255,255,.88);
    padding: 14px;
    min-width:0;
  }
  html.dark .nb-card{ background: rgba(15,23,42,.52); }

  .nb-card.blue{ background: color-mix(in srgb, var(--nb-card) 86%, var(--tint-blue) 14%); }
  .nb-card.green{ background: color-mix(in srgb, var(--nb-card) 86%, var(--tint-green) 14%); }
  .nb-card.orange{ background: color-mix(in srgb, var(--nb-card) 86%, var(--tint-orange) 14%); }
  .nb-card.slate{ background: color-mix(in srgb, var(--nb-card) 88%, var(--tint-slate) 12%); }
  /* fallback kalau color-mix belum supported */
  @supports not (background: color-mix(in srgb, white 50%, black 50%)){
    .nb-card.blue{ background: rgba(30,136,229,.08); }
    .nb-card.green{ background: rgba(46,125,50,.08); }
    .nb-card.orange{ background: rgba(251,140,0,.10); }
    .nb-card.slate{ background: rgba(148,163,184,.12); }
    html.dark .nb-card.blue{ background: rgba(30,136,229,.16); }
    html.dark .nb-card.green{ background: rgba(46,125,50,.16); }
    html.dark .nb-card.orange{ background: rgba(251,140,0,.18); }
    html.dark .nb-card.slate{ background: rgba(148,163,184,.12); }
  }

  .nb-row{ display:flex; gap:12px; align-items:flex-start; }
  .nb-avatar{
    width:48px; height:48px;
    border-radius: 16px;
    border:1px solid var(--nb-border);
    background: rgba(30,136,229,.10);
    display:flex; align-items:center; justify-content:center;
    font-weight:800;
    color: rgba(21,101,192,.95);
    flex: 0 0 auto;
    overflow:hidden;
  }
  html.dark .nb-avatar{
    background: rgba(30,136,229,.18);
    color: rgba(191,219,254,.92);
  }
  .nb-avatar img{ width:100%; height:100%; object-fit:cover; display:block; }

  .nb-k{ font-size:12px; font-weight:600; color:var(--nb-muted); }
  .nb-v{ margin-top:4px; font-size:14px; font-weight:700; color:var(--nb-text); line-height:1.25; }
  .nb-small{ margin-top:6px; font-size:12.5px; font-weight:500; color:var(--nb-sub); }

  .nb-form{
    margin-top: 12px;
    display:grid;
    grid-template-columns: 1fr;
    gap: 12px;
  }
  @media(min-width: 900px){
    .nb-form.two{ grid-template-columns: 1fr 1fr; }
  }

  .nb-field{ min-width:0; }
  .nb-label{ font-size:12px; font-weight:700; color:var(--nb-muted); margin-bottom:6px; }
  .nb-input, .nb-textarea{
    width:100%;
    border-radius: 14px;
    border: 1px solid var(--nb-border);
    background: rgba(255,255,255,.90);
    color: var(--nb-text);
    font-size: 13.5px;
    font-weight: 600;
    padding: 10px 12px;
    outline:none;
  }
  html.dark .nb-input, html.dark .nb-textarea{ background: rgba(15,23,42,.45); }
  .nb-textarea{ min-height: 110px; resize: vertical; font-weight:600; }

  .nb-help{ margin-top:6px; font-size:12px; font-weight:500; color:var(--nb-sub); }

  .nb-switch{
    display:flex; align-items:center; justify-content:space-between;
    gap:12px;
    padding: 10px 12px;
    border:1px solid var(--nb-border);
    border-radius: 16px;
    background: rgba(255,255,255,.70);
  }
  html.dark .nb-switch{ background: rgba(15,23,42,.40); }
  .nb-switch .left{ min-width:0; }
  .nb-switch .ttl{ font-size:13px; font-weight:800; color:var(--nb-text); }
  .nb-switch .sub{ margin-top:2px; font-size:12px; font-weight:500; color:var(--nb-sub); }

  .nb-checkbox{
    width:44px; height:26px; border-radius:999px;
    background: rgba(148,163,184,.30);
    border: 1px solid var(--nb-border);
    position: relative;
    flex: 0 0 auto;
  }
  .nb-checkbox:after{
    content:"";
    width:22px; height:22px; border-radius:999px;
    background: rgba(255,255,255,.96);
    position:absolute; top:1px; left:1px;
    transition: transform .15s ease;
    box-shadow: 0 8px 18px rgba(2,6,23,.12);
  }
  input[type="checkbox"]:checked + .nb-checkbox{
    background: rgba(34,197,94,.28);
  }
  input[type="checkbox"]:checked + .nb-checkbox:after{
    transform: translateX(18px);
  }
  input[type="checkbox"]{ position:absolute; opacity:0; pointer-events:none; }

  .nb-errors{
    margin: 12px 16px 0;
    border: 1px solid rgba(251,140,0,.28);
    background: rgba(251,140,0,.08);
    border-radius: 16px;
    padding: 12px 14px;
    color: rgba(120,53,15,.95);
    font-size: 12.5px;
    font-weight: 600;
  }
  html.dark .nb-errors{
    background: rgba(251,140,0,.16);
    color: rgba(254,215,170,.92);
    border-color: rgba(251,140,0,.30);
  }

  .nb-success{
    margin: 12px 16px 0;
    border: 1px solid rgba(34,197,94,.26);
    background: rgba(34,197,94,.08);
    border-radius: 16px;
    padding: 12px 14px;
    color: rgba(21,128,61,.95);
    font-size: 12.5px;
    font-weight: 700;
  }
  html.dark .nb-success{
    background: rgba(34,197,94,.16);
    color: rgba(187,247,208,.92);
    border-color: rgba(34,197,94,.28);
  }

  /* Mobile polish */
  @media(max-width: 420px){
    .nb-head{ padding: 14px 14px 10px; }
    .nb-section{ padding: 12px 14px; }
    .nb-actions{ width:100%; }
    .nb-actions .nb-btn{ flex: 1 1 0; width:100%; }
  }
</style>

<div class="nb-wrap">
  <div class="nb-panel">
    <div class="nb-panel-topbar"></div>

    <div class="nb-head">
      <div style="min-width:0;">
        <div class="nb-title">Profil</div>
        <div class="nb-subtitle">Perbarui informasi akun & data member.</div>
      </div>

      <div class="nb-actions">
        <a class="nb-btn" href="{{ route('member.dashboard') }}">Dashboard</a>
        <a class="nb-btn" href="{{ route('member.security') }}">Keamanan</a>
      </div>
    </div>

    @if(session('success'))
      <div class="nb-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
      <div class="nb-errors">
        <div style="font-weight:800; margin-bottom:6px;">Periksa kembali:</div>
        <ul style="margin:0; padding-left: 18px;">
          @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- Ringkasan profil --}}
    <div class="nb-section">
      <div class="nb-h2">
        <span>Ringkasan</span>
        {!! $pill($stat['text'], $stat['kind']) !!}
      </div>

      <div class="nb-grid">
        <div class="nb-card blue">
          <div class="nb-row">
            <div class="nb-avatar" aria-hidden="true">
              @if($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="Avatar">
              @else
                {{ $initials($user->name ?? 'Member') }}
              @endif
            </div>
            <div style="min-width:0;">
              <div class="nb-k">Nama</div>
              <div class="nb-v">{{ $user->name ?? '—' }}</div>
              <div class="nb-small">{{ $user->email ?? '—' }}</div>
            </div>
          </div>
        </div>

        <div class="nb-card slate">
          <div class="nb-k">Keanggotaan</div>
          <div class="nb-v" style="margin-top:6px;">
            {{ $member->member_code ?? '—' }}
          </div>
          <div class="nb-small">
            Gabung: {{ $fmtDate($member->joined_at ?? null) }} • Role: {{ ucfirst($user->role ?? 'member') }}
          </div>
        </div>
      </div>
    </div>

    {{-- Form update --}}
    <div class="nb-section">
      <div class="nb-h2">Data Akun</div>
      <div class="nb-hint">Ini digunakan untuk login & identitas dasar.</div>

      <form method="POST" action="{{ route('member.profil.update') }}" enctype="multipart/form-data" style="margin-top:12px;">
        @csrf

        <div class="nb-form two">
          <div class="nb-field">
            <div class="nb-label">Nama</div>
            <input class="nb-input" name="name" value="{{ old('name', $user->name) }}" required>
          </div>

          <div class="nb-field">
            <div class="nb-label">Email</div>
            <input class="nb-input" name="email" type="email" value="{{ old('email', $user->email) }}" required>
            <div class="nb-help">Pastikan email aktif untuk notifikasi.</div>
          </div>

          <div class="nb-field">
            <div class="nb-label">Username (opsional)</div>
            <input class="nb-input" name="username" value="{{ old('username', $user->username ?? '') }}" placeholder="contoh: member_01">
            <div class="nb-help">Boleh huruf/angka/titik/underscore.</div>
          </div>

          <div class="nb-field">
            <div class="nb-label">Foto Profil (opsional)</div>
            <input class="nb-input" name="avatar" type="file" accept="image/*">
            <div class="nb-help">JPG/PNG/WEBP, maks 2MB.</div>
          </div>
        </div>

        <div style="margin-top:14px;"></div>

        <div class="nb-h2">Data Member</div>
        <div class="nb-hint">Kontak & alamat untuk kebutuhan layanan.</div>

        <div class="nb-form two">
          <div class="nb-field">
            <div class="nb-label">Telepon (opsional)</div>
            <input class="nb-input" name="phone" value="{{ old('phone', $member->phone ?? '') }}" placeholder="+62...">
          </div>

          <div class="nb-field">
            <div class="nb-label">Alamat (opsional)</div>
            <input class="nb-input" name="address" value="{{ old('address', $member->address ?? '') }}" placeholder="Alamat singkat">
          </div>

          <div class="nb-field" style="grid-column: 1 / -1;">
            <div class="nb-label">Bio (opsional)</div>
            <textarea class="nb-textarea" name="bio" placeholder="Ceritakan singkat tentang Anda...">{{ old('bio', $profile->bio ?? '') }}</textarea>
            <div class="nb-help">Maks 500 karakter.</div>
          </div>
        </div>

        <div style="margin-top:12px;"></div>

        <div class="nb-switch">
          <div class="left">
            <div class="ttl">Profil Publik</div>
            <div class="sub">Jika aktif, bio & avatar dapat ditampilkan di area komunitas (jika ada).</div>
          </div>
          <label style="display:flex; align-items:center; gap:10px; margin:0;">
            <input type="checkbox" name="is_public" value="1" {{ old('is_public', $public ? 1 : 0) ? 'checked' : '' }}>
            <span class="nb-checkbox" aria-hidden="true"></span>
          </label>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:14px;">
          <button class="nb-btn primary" type="submit">Simpan Perubahan</button>
          <a class="nb-btn ghost" href="{{ route('member.profil') }}">Batal</a>
        </div>
      </form>
    </div>

    {{-- Info tambahan (read-only) --}}
    <div class="nb-section">
      <div class="nb-h2">Info Tambahan</div>
      <div class="nb-hint">Informasi ini dikelola sistem.</div>

      <div class="nb-grid">
        <div class="nb-card green">
          <div class="nb-k">Status Member</div>
          <div class="nb-v" style="margin-top:6px;">{{ $stat['text'] }}</div>
          <div class="nb-small">Hubungi admin jika status tidak sesuai.</div>
        </div>

        <div class="nb-card orange">
          <div class="nb-k">Terakhir diperbarui</div>
          <div class="nb-v" style="margin-top:6px;">{{ $fmtDate($member->updated_at ?? null) }}</div>
          <div class="nb-small">Akun: {{ $fmtDate($user->updated_at ?? null) }}</div>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
