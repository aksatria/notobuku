@extends('layouts.notobuku')

@section('content')
@php
  $rules = $rules ?? collect();
  $calendars = $calendars ?? collect();
  $closures = $closures ?? collect();
  $branches = $branches ?? collect();
  $sim = $simulation ?? null;
@endphp

<style>
  .nbcp-wrap{display:grid;gap:14px}
  .nbcp-card{background:#fff;border:1px solid #dbe3f0;border-radius:16px;padding:14px}
  .nbcp-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap}
  .nbcp-title{font-size:22px;font-weight:800;color:#0f172a;line-height:1.2}
  .nbcp-sub{font-size:14px;color:#64748b;margin-top:4px}
  .nbcp-steps{display:flex;gap:8px;flex-wrap:wrap}
  .nbcp-step{font-size:12px;font-weight:700;color:#334155;background:#f8fafc;border:1px solid #dbe3f0;border-radius:999px;padding:5px 10px}

  .nbcp-tabs{display:flex;gap:8px;flex-wrap:wrap}
  .nbcp-tab{height:36px;padding:0 12px;border-radius:10px;border:1px solid #cbd5e1;background:#fff;color:#334155;font-weight:700;cursor:pointer}
  .nbcp-tab.active{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}

  .nbcp-pane{display:none}
  .nbcp-pane.active{display:block}

  .nbcp-grid{display:grid;gap:10px}
  .nbcp-grid.cols-4{grid-template-columns:repeat(4,minmax(0,1fr))}
  .nbcp-grid.cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}
  .nbcp-grid.cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}
  .nbcp-field{display:flex;flex-direction:column;gap:5px}
  .nbcp-field label{font-size:12px;font-weight:700;color:#334155}
  .nbcp-field input,.nbcp-field select{height:36px;border:1px solid #cbd5e1;border-radius:10px;padding:0 10px;font-size:13px}

  .nbcp-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .nbcp-btn{height:36px;padding:0 12px;border-radius:10px;border:1px solid #2563eb;background:#2563eb;color:#fff;font-weight:700;cursor:pointer}
  .nbcp-btn.light{background:#fff;color:#1e293b;border-color:#cbd5e1}
  .nbcp-btn.danger{background:#fff;color:#b91c1c;border-color:#fecaca}

  .nbcp-help{font-size:12px;color:#64748b;margin-top:2px}
  .nbcp-advanced{margin-top:8px;border:1px dashed #cbd5e1;border-radius:12px;padding:10px;background:#f8fafc}
  .nbcp-advanced > summary{cursor:pointer;font-size:13px;font-weight:700;color:#334155}

  .nbcp-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:12px}
  .nbcp-table{width:100%;border-collapse:collapse;font-size:13px}
  .nbcp-table th,.nbcp-table td{padding:8px;border-bottom:1px solid #e2e8f0;vertical-align:top;white-space:nowrap}
  .nbcp-table th{text-align:left;color:#475569;font-size:11px;text-transform:uppercase;letter-spacing:.04em;background:#f8fafc}

  .nbcp-pill{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid #cbd5e1;background:#f8fafc;color:#334155}
  .nbcp-pill.ok{background:#ecfdf5;border-color:#86efac;color:#166534}
  .nbcp-pill.no{background:#fff1f2;border-color:#fda4af;color:#be123c}

  .nbcp-sim{margin-top:8px;padding:10px;border:1px dashed #93c5fd;border-radius:12px;background:#eff6ff;font-size:13px;color:#0f172a}

  @media (max-width:1200px){.nbcp-grid.cols-4,.nbcp-grid.cols-3{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media (max-width:760px){.nbcp-grid.cols-4,.nbcp-grid.cols-3,.nbcp-grid.cols-2{grid-template-columns:1fr}}
</style>

<div class="nbcp-wrap">
  <section class="nbcp-card">
    <div class="nbcp-head">
      <div>
        <div class="nbcp-title">Kebijakan Sirkulasi</div>
        <div class="nbcp-sub">Atur masa pinjam, denda, hari libur, dan uji simulasi tanpa rumit untuk pustakawan.</div>
      </div>
      <div class="nbcp-steps">
        <span class="nbcp-step">1. Atur Aturan Pinjam</span>
        <span class="nbcp-step">2. Atur Hari Libur</span>
        <span class="nbcp-step">3. Cek Simulasi</span>
      </div>
    </div>
  </section>

  <section class="nbcp-card">
    <div class="nbcp-tabs" role="tablist">
      <button type="button" class="nbcp-tab active" data-tab="rule">Aturan Pinjam</button>
      <button type="button" class="nbcp-tab" data-tab="kalender">Kalender Layanan</button>
      <button type="button" class="nbcp-tab" data-tab="simulasi">Simulasi</button>
    </div>

    <div class="nbcp-pane active" data-pane="rule" style="margin-top:12px">
      <form method="POST" action="{{ route('transaksi.policies.rules.store') }}" class="nbcp-grid cols-4">
        @csrf
        <div class="nbcp-field">
          <label>Tipe Anggota</label>
          <select name="member_type" required>
            <option value="member">Anggota</option>
            <option value="student">Mahasiswa</option>
            <option value="staff">Staf</option>
          </select>
        </div>
        <div class="nbcp-field"><label>Maks Buku Aktif</label><input type="number" name="max_items" value="3" min="1" required></div>
        <div class="nbcp-field"><label>Lama Pinjam (hari)</label><input type="number" name="default_days" value="7" min="1" required></div>
        <div class="nbcp-field"><label>Lama Perpanjang (hari)</label><input type="number" name="extend_days" value="7" min="1" required></div>
        <div class="nbcp-field"><label>Maks Perpanjang</label><input type="number" name="max_renewals" value="2" min="0" required></div>
        <div class="nbcp-field"><label>Denda per Hari (Rp)</label><input type="number" name="fine_rate_per_day" value="1000" min="0" required></div>
        <div class="nbcp-field"><label>Nama Aturan (opsional)</label><input name="name" placeholder="Contoh: Mahasiswa reguler"></div>
        <div class="nbcp-actions" style="align-self:end"><button class="nbcp-btn" type="submit">Simpan Aturan</button></div>
      </form>

      <details class="nbcp-advanced">
        <summary>Isi lanjutan untuk rule baru</summary>
        <form method="POST" action="{{ route('transaksi.policies.rules.store') }}" class="nbcp-grid cols-4" style="margin-top:10px">
          @csrf
          <input type="hidden" name="member_type" value="member">
          <input type="hidden" name="max_items" value="3">
          <input type="hidden" name="default_days" value="7">
          <input type="hidden" name="extend_days" value="7">
          <input type="hidden" name="max_renewals" value="2">
          <input type="hidden" name="fine_rate_per_day" value="1000">
          <div class="nbcp-field"><label>Cabang spesifik</label><select name="branch_id"><option value="">Semua cabang</option>@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
          <div class="nbcp-field"><label>Tipe Koleksi</label><input name="collection_type" placeholder="buku / serial / audio"></div>
          <div class="nbcp-field"><label>Grace Days</label><input type="number" name="grace_days" value="0" min="0"></div>
          <div class="nbcp-field"><label>Prioritas</label><input type="number" name="priority" value="10" min="0"></div>
          <div class="nbcp-field"><label>Boleh perpanjang jika sedang direservasi</label><select name="can_renew_if_reserved"><option value="0">Tidak</option><option value="1">Ya</option></select></div>
          <div class="nbcp-field"><label>Status Aturan</label><select name="is_active"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
          <div class="nbcp-actions" style="align-self:end"><button class="nbcp-btn light" type="submit">Simpan Aturan Lanjutan</button></div>
        </form>
      </details>

      <div class="nbcp-help">Tips: untuk operasional harian, cukup isi form ringkas di atas. Pengaturan lanjutan hanya dipakai jika memang perlu.</div>

      <div class="nbcp-table-wrap" style="margin-top:10px">
        <table class="nbcp-table">
          <thead>
            <tr>
              <th>Aturan</th>
              <th>Anggota</th>
              <th>Pinjam</th>
              <th>Perpanjang</th>
              <th>Denda</th>
              <th>Scope</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          @forelse($rules as $r)
            <tr>
              <td><strong>{{ $r->name ?: ('Aturan #' . $r->id) }}</strong></td>
              <td>{{ $r->member_type ?: 'Semua' }}</td>
              <td>{{ $r->default_days }} hari / {{ $r->max_items }} item</td>
              <td>{{ $r->extend_days }} hari, maks {{ $r->max_renewals }}x</td>
              <td>Rp {{ number_format($r->fine_rate_per_day) }} @if((int)$r->grace_days>0)<span class="nbcp-pill">grace {{ $r->grace_days }} hari</span>@endif</td>
              <td>{{ $r->branch_id ?: 'Semua cabang' }} @if($r->collection_type)<span class="nbcp-pill">{{ $r->collection_type }}</span>@endif</td>
              <td><span class="nbcp-pill {{ $r->is_active ? 'ok' : 'no' }}">{{ $r->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
              <td>
                <form method="POST" action="{{ route('transaksi.policies.rules.delete', $r->id) }}" onsubmit="return confirm('Hapus rule ini?')">
                  @csrf @method('DELETE')
                  <button class="nbcp-btn danger" type="submit">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="8">Belum ada aturan. Tambahkan 1 aturan dasar untuk mulai.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="nbcp-pane" data-pane="kalender" style="margin-top:12px">
      <div class="nbcp-grid cols-2">
        <form method="POST" action="{{ route('transaksi.policies.calendars.store') }}" class="nbcp-grid cols-2">
          @csrf
          <div class="nbcp-field"><label>Nama Kalender</label><input name="name" placeholder="Kalender Layanan Pusat" required></div>
          <div class="nbcp-field"><label>Cabang</label><select name="branch_id"><option value="">Semua cabang</option>@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
          <div class="nbcp-field"><label>Abaikan Sabtu/Minggu</label><select name="exclude_weekends"><option value="1">Ya</option><option value="0">Tidak</option></select></div>
          <div class="nbcp-actions" style="align-self:end"><button class="nbcp-btn" type="submit">Simpan Kalender</button></div>
        </form>

        <form method="POST" action="{{ route('transaksi.policies.closures.store') }}" class="nbcp-grid cols-2">
          @csrf
          <div class="nbcp-field"><label>Pilih Kalender</label><select name="calendar_id" required>@foreach($calendars as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->branch_id ?: 'Semua cabang' }})</option>@endforeach</select></div>
          <div class="nbcp-field"><label>Tanggal Libur</label><input type="date" name="closed_on" required></div>
          <div class="nbcp-field"><label>Libur berulang tiap tahun</label><select name="is_recurring_yearly"><option value="0">Tidak</option><option value="1">Ya</option></select></div>
          <div class="nbcp-actions" style="align-self:end"><button class="nbcp-btn">Tambah Libur</button></div>
        </form>
      </div>

      <div class="nbcp-table-wrap" style="margin-top:10px">
        <table class="nbcp-table">
          <thead><tr><th>Kalender</th><th>Tanggal</th><th>Keterangan</th><th>Aksi</th></tr></thead>
          <tbody>
          @forelse($closures as $cl)
            <tr>
              <td>{{ $calendars->firstWhere('id', $cl->calendar_id)?->name ?? ('Kalender #' . $cl->calendar_id) }}</td>
              <td>{{ $cl->closed_on }} @if($cl->is_recurring_yearly)<span class="nbcp-pill">Tahunan</span>@endif</td>
              <td>{{ $cl->label ?: '-' }}</td>
              <td>
                <form method="POST" action="{{ route('transaksi.policies.closures.delete', $cl->id) }}" onsubmit="return confirm('Hapus hari libur ini?')">
                  @csrf @method('DELETE')
                  <button class="nbcp-btn danger" type="submit">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="4">Belum ada hari libur.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="nbcp-pane" data-pane="simulasi" style="margin-top:12px">
      <form method="POST" action="{{ route('transaksi.policies.simulate') }}" class="nbcp-grid cols-4" id="nbcp-sim-form">
        @csrf
        <div class="nbcp-field"><label>Aksi Simulasi</label><select name="action" id="nbcp-sim-action"><option value="issue">Hitung jatuh tempo pinjam</option><option value="renew">Hitung jatuh tempo perpanjang</option><option value="fine">Hitung denda</option></select></div>
        <div class="nbcp-field"><label>Cabang</label><select name="branch_id"><option value="">Semua cabang</option>@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
        <div class="nbcp-field"><label>Tipe Anggota</label><input name="member_type" placeholder="member/student/staff"></div>
        <div class="nbcp-field"><label>Tipe Koleksi</label><input name="collection_type" placeholder="buku"></div>
        <div class="nbcp-field" data-for="issue renew"><label>Tanggal Mulai</label><input type="date" name="base_date"></div>
        <div class="nbcp-field" data-for="issue renew"><label>Jumlah Hari</label><input type="number" name="days" min="1" value="7"></div>
        <div class="nbcp-field" data-for="fine"><label>Jatuh Tempo</label><input type="datetime-local" name="due_at"></div>
        <div class="nbcp-field" data-for="fine"><label>Waktu Kembali</label><input type="datetime-local" name="returned_at"></div>
        <div class="nbcp-actions" style="align-self:end"><button class="nbcp-btn" type="submit">Jalankan Simulasi</button></div>
      </form>

      @if($sim)
        <div class="nbcp-sim">
          <div><strong>Sumber kebijakan:</strong> {{ $sim['policy']['source'] ?? '-' }} | <strong>Aturan:</strong> {{ $sim['policy']['rule_name'] ?? '-' }}</div>
          <div style="margin-top:4px"><strong>Hasil:</strong></div>
          <pre style="margin:6px 0 0;white-space:pre-wrap">{{ json_encode($sim['output'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
      @endif
    </div>
  </section>
</div>

<script>
(function(){
  const tabs = Array.from(document.querySelectorAll('.nbcp-tab'));
  const panes = Array.from(document.querySelectorAll('.nbcp-pane'));

  function activate(name){
    tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === name));
    panes.forEach(p => p.classList.toggle('active', p.dataset.pane === name));
  }

  tabs.forEach(t => t.addEventListener('click', () => activate(t.dataset.tab)));

  const action = document.getElementById('nbcp-sim-action');
  const simFields = Array.from(document.querySelectorAll('#nbcp-sim-form [data-for]'));
  function syncSimFields(){
    const mode = action ? action.value : 'issue';
    simFields.forEach(el => {
      const supports = (el.dataset.for || '').split(/\s+/).filter(Boolean);
      el.style.display = supports.includes(mode) ? '' : 'none';
    });
  }
  if(action){
    action.addEventListener('change', syncSimFields);
    syncSimFields();
  }
})();
</script>
@endsection
