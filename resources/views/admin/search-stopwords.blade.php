@extends('layouts.notobuku')

@section('title', 'Stopwords Search - Admin NOTOBUKU')

@section('content')
<style>
  .nb-sw-wrap{max-width:980px;margin:0 auto;--c:#0b2545;--m:rgba(11,37,69,.62);--b:rgba(148,163,184,.25);}
  .nb-sw-card{background:rgba(255,255,255,.93);border:1px solid var(--b);border-radius:16px;padding:16px;margin-bottom:12px;}
  .nb-sw-title{font-size:16px;font-weight:800;color:var(--c);}
  .nb-sw-sub{font-size:12.5px;color:var(--m);margin-top:4px;}
  .nb-sw-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px;}
  .nb-sw-input,.nb-sw-select{border:1px solid var(--b);border-radius:12px;padding:8px 10px;background:#fff;color:var(--c);font-size:13px;}
  .nb-sw-input{min-width:320px;}
  .nb-btn{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border-radius:12px;border:1px solid var(--b);background:#fff;color:var(--c);font-size:12.5px;font-weight:700;text-decoration:none;}
  .nb-btn.primary{background:linear-gradient(180deg,#1f6feb,#0b5cd6);color:#fff;border-color:rgba(31,111,235,.4);}
  .nb-sw-table{width:100%;border-collapse:collapse;font-size:12.5px;}
  .nb-sw-table th,.nb-sw-table td{padding:10px;border-top:1px solid var(--b);text-align:left;}
  .nb-sw-table th{font-weight:700;color:var(--m);background:rgba(148,163,184,.08);}
  .nb-ok{font-size:12.5px;color:#166534;margin-top:8px;}
</style>

<div class="nb-sw-wrap">
  <div class="nb-sw-card">
    <div class="nb-sw-title">Stopwords Manager</div>
    <div class="nb-sw-sub">Kelola kata umum yang diabaikan saat ekspansi query/saran agar hasil lebih presisi.</div>
    @if(session('status'))<div class="nb-ok">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ route('admin.search_stopwords.store') }}" class="nb-sw-row">
      @csrf
      <input class="nb-sw-input" name="words" placeholder="contoh: dan, atau, yang, di" required>
      <select class="nb-sw-select" name="branch_id">
        <option value="">Global institusi</option>
        @foreach($branches as $b)
          <option value="{{ $b->id }}">{{ $b->name }}</option>
        @endforeach
      </select>
      <button class="nb-btn primary" type="submit">Tambah Stopwords</button>
      <a class="nb-btn" href="{{ route('admin.search_tuning') }}">Query Tuning</a>
    </form>
  </div>

  <div class="nb-sw-card">
    <table class="nb-sw-table">
      <thead>
        <tr><th>Kata</th><th>Scope</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          @php $bName = $branches->firstWhere('id', $r->branch_id)?->name ?? 'Global'; @endphp
          <tr>
            <td>{{ $r->word }}</td>
            <td>{{ $bName }}</td>
            <td>
              <form method="POST" action="{{ route('admin.search_stopwords.delete', $r->id) }}" onsubmit="return confirm('Hapus stopword ini?');">
                @csrf @method('DELETE')
                <button type="submit" class="nb-btn">Hapus</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="3" class="nb-sw-sub">Belum ada stopwords tambahan.</td></tr>
        @endforelse
      </tbody>
    </table>
    <div class="nb-sw-row">{{ $rows->links() }}</div>
  </div>
</div>
@endsection

