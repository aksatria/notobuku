@extends('layouts.notobuku')

@section('title', 'Pengaturan Query Pencarian - Admin NOTOBUKU')

@section('content')
<style>
  .nb-tune-wrap{max-width:1020px;margin:0 auto;--c:#0b2545;--m:rgba(11,37,69,.62);--b:rgba(148,163,184,.25);}
  .nb-tune-card{background:rgba(255,255,255,.93);border:1px solid var(--b);border-radius:16px;padding:16px;margin-bottom:12px;}
  .nb-tune-title{font-size:16px;font-weight:800;color:var(--c);}
  .nb-tune-sub{font-size:12.5px;color:var(--m);margin-top:4px;}
  .nb-tune-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:12px;}
  .nb-tune-field label{display:block;font-size:12px;font-weight:700;color:var(--m);margin-bottom:6px;}
  .nb-tune-field input{width:100%;border:1px solid var(--b);border-radius:12px;padding:8px 10px;font-size:13px;color:var(--c);background:#fff;}
  .nb-tune-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;}
  .nb-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border-radius:12px;border:1px solid var(--b);background:#fff;color:var(--c);font-size:12.5px;font-weight:700;text-decoration:none;}
  .nb-btn.primary{background:linear-gradient(180deg,#1f6feb,#0b5cd6);color:#fff;border-color:rgba(31,111,235,.4);}
  .nb-ok{font-size:12.5px;color:#166534;margin-top:8px;}
</style>

<div class="nb-tune-wrap">
  <div class="nb-tune-card">
    <div class="nb-tune-title">Query Tuning OPAC</div>
    <div class="nb-tune-sub">Atur bobot relevance agar hasil lebih presisi sesuai pola pengguna perpustakaan.</div>
    <div class="nb-tune-actions">
      <form method="POST" action="{{ route('admin.search_tuning.preset') }}">@csrf<input type="hidden" name="preset" value="school"><button type="submit" class="nb-btn">Preset Sekolah</button></form>
      <form method="POST" action="{{ route('admin.search_tuning.preset') }}">@csrf<input type="hidden" name="preset" value="university"><button type="submit" class="nb-btn">Preset Kampus</button></form>
      <form method="POST" action="{{ route('admin.search_tuning.preset') }}">@csrf<input type="hidden" name="preset" value="public"><button type="submit" class="nb-btn">Preset Umum</button></form>
    </div>
    @if(session('status'))
      <div class="nb-ok">{{ session('status') }}</div>
    @endif
  </div>

  <form class="nb-tune-card" method="POST" action="{{ route('admin.search_tuning.update') }}">
    @csrf
    <div class="nb-tune-title" style="font-size:14px;">Bobot Exact Match</div>
    <div class="nb-tune-grid">
      <div class="nb-tune-field"><label>Judul</label><input type="number" name="title_exact_weight" value="{{ old('title_exact_weight', $settings['title_exact_weight'] ?? 80) }}" min="0" max="500"></div>
      <div class="nb-tune-field"><label>Pengarang</label><input type="number" name="author_exact_weight" value="{{ old('author_exact_weight', $settings['author_exact_weight'] ?? 40) }}" min="0" max="500"></div>
      <div class="nb-tune-field"><label>Subjek</label><input type="number" name="subject_exact_weight" value="{{ old('subject_exact_weight', $settings['subject_exact_weight'] ?? 25) }}" min="0" max="500"></div>
      <div class="nb-tune-field"><label>Penerbit</label><input type="number" name="publisher_exact_weight" value="{{ old('publisher_exact_weight', $settings['publisher_exact_weight'] ?? 15) }}" min="0" max="500"></div>
      <div class="nb-tune-field"><label>ISBN</label><input type="number" name="isbn_exact_weight" value="{{ old('isbn_exact_weight', $settings['isbn_exact_weight'] ?? 100) }}" min="0" max="1000"></div>
    </div>

    <div class="nb-tune-title" style="font-size:14px;margin-top:14px;">Short Query & Availability Ranking</div>
    <div class="nb-tune-grid">
      <div class="nb-tune-field"><label>Panjang short query</label><input type="number" name="short_query_max_len" value="{{ old('short_query_max_len', $settings['short_query_max_len'] ?? 4) }}" min="1" max="12"></div>
      <div class="nb-tune-field"><label>Multiplier short query</label><input type="number" step="0.1" name="short_query_multiplier" value="{{ old('short_query_multiplier', $settings['short_query_multiplier'] ?? 1.6) }}" min="1" max="5"></div>
      <div class="nb-tune-field"><label>Bobot tersedia</label><input type="number" step="0.1" name="available_weight" value="{{ old('available_weight', $settings['available_weight'] ?? 10) }}" min="0" max="100"></div>
      <div class="nb-tune-field"><label>Penalty dipinjam</label><input type="number" step="0.1" name="borrowed_penalty" value="{{ old('borrowed_penalty', $settings['borrowed_penalty'] ?? 3) }}" min="0" max="100"></div>
      <div class="nb-tune-field"><label>Penalty reservasi</label><input type="number" step="0.1" name="reserved_penalty" value="{{ old('reserved_penalty', $settings['reserved_penalty'] ?? 2) }}" min="0" max="100"></div>
    </div>

    <div class="nb-tune-actions">
      <button type="submit" class="nb-btn primary">Simpan Tuning</button>
      <a class="nb-btn" href="{{ route('admin.search_synonyms') }}">Kelola Sinonim</a>
      <a class="nb-btn" href="{{ route('admin.search_stopwords') }}">Kelola Stopwords</a>
      <a class="nb-btn" href="{{ route('admin.search_analytics') }}">Analitik Pencarian</a>
      <a class="nb-btn" href="{{ route('admin.dashboard') }}">Kembali ke Dashboard</a>
    </div>
  </form>

  <form class="nb-tune-card" method="POST" action="{{ route('admin.search_tuning.reset') }}">
    @csrf
    <div class="nb-tune-title" style="font-size:14px;">Reset ke Bawaan</div>
    <div class="nb-tune-sub">Gunakan ini jika tuning percobaan membuat relevance turun.</div>
    <div class="nb-tune-actions">
      <button type="submit" class="nb-btn">Reset Bawaan</button>
    </div>
  </form>
</div>
@endsection
