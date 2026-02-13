{{-- resources/views/partials/flash.blade.php --}}
@php
  $types = [
    'success' => ['bg'=>'linear-gradient(90deg,#2ecc71,#1fa85a)', 'tx'=>'#0b2d18', 'label'=>'Berhasil'],
    'error'   => ['bg'=>'linear-gradient(90deg,#e53935,#c62828)', 'tx'=>'#fff',    'label'=>'Gagal'],
    'warning' => ['bg'=>'linear-gradient(90deg,#fb8c00,#ef6c00)', 'tx'=>'#2b1600', 'label'=>'Peringatan'],
    'info'    => ['bg'=>'linear-gradient(90deg,#1e88e5,#1565c0)', 'tx'=>'#fff',    'label'=>'Info'],
  ];

  $found = null;
  foreach(array_keys($types) as $k){
    if(session()->has($k)){ $found = $k; break; }
  }
@endphp

@if($found)
  @php
    $t = $types[$found];
    $msg = session($found);
  @endphp

  <div id="nb-toast"
       style="
         position:fixed;
         right:18px;
         top:18px;
         z-index:80;
         width:min(520px, calc(100% - 36px));
       ">
    <div style="
      border-radius:18px;
      overflow:hidden;
      box-shadow: 0 16px 34px rgba(17,24,39,.18);
    ">
      <div style="padding:14px 14px; background:{{ $t['bg'] }}; color:{{ $t['tx'] }};">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px;">
          <div style="min-width:0;">
            <div style="font-weight:700; font-size:13px; line-height:1.1;">{{ $t['label'] }}</div>
            <div style="font-size:13px; margin-top:6px; line-height:1.5; word-break:break-word; font-weight:400;">
              {{ is_string($msg) ? $msg : 'OK' }}
            </div>
          </div>
          <button type="button"
                  onclick="document.getElementById('nb-toast')?.remove()"
                  style="
                    border:none;
                    background:rgba(255,255,255,.18);
                    color:inherit;
                    width:38px;height:38px;
                    border-radius:14px;
                    cursor:pointer;
                    font-weight:700;
                  ">
            ×
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    setTimeout(() => {
      const el = document.getElementById('nb-toast');
      if(!el) return;
      el.style.opacity = '0';
      el.style.transform = 'translateY(-6px)';
      el.style.transition = 'all .18s ease';
      setTimeout(()=> el.remove(), 220);
    }, 4500);
  </script>
@endif
