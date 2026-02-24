@php
    $title = 'Ganti Cabang (Super Admin)';
@endphp

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 0; background: #f6f7f9; }
        .wrap { max-width: 900px; margin: 24px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #e6e8ee; border-radius: 12px; padding: 16px; }
        .row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        select, button { padding: 10px 12px; border-radius: 10px; border: 1px solid #d7dbe6; }
        button { cursor: pointer; border: none; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-secondary { background: #e5e7eb; color: #111827; }
        .muted { color: #6b7280; font-size: 14px; margin-top: 8px; }
        .error { color: #b91c1c; font-size: 14px; margin-top: 10px; }
        .ok { color: #047857; font-size: 14px; margin-top: 10px; }
        .topbar { display:flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div style="font-weight: 700;">{{ $title }}</div>
        <div class="row">
            <a href="{{ route('admin.dashboard') }}">Kembali ke Dashboard</a>
            <a href="{{ route('transaksi.index') }}">Ke Transaksi</a>
        </div>
    </div>

    <div class="card">
        <div style="font-weight:600; margin-bottom: 8px;">Cabang Aktif untuk Transaksi</div>

        <form method="POST" action="{{ route('preferences.active_branch.set') }}">
            @csrf
            <div class="row">
                <select name="branch_id" required>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" @selected((int)$active_branch_id === (int)$b->id)>
                            {{ $b->name }} @if((int)$b->is_active !== 1) (Nonaktif) @endif
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="btn-primary">Gunakan</button>
            </div>
        </form>

        <form method="POST" action="{{ route('preferences.active_branch.reset') }}" style="margin-top: 10px;">
            @csrf
            <button type="submit" class="btn-secondary">Reset (gunakan cabang akun)</button>
        </form>

        <div class="muted">
            Catatan: halaman transaksi (pinjam/kembali/perpanjang) tetap tanpa pilihan cabang.
            Cabang aktif di sini dipakai sebagai konteks transaksi untuk super admin.
        </div>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        @if(session('status'))
            <div class="ok">{{ session('status') }}</div>
        @endif
    </div>
</div>
</body>
</html>
