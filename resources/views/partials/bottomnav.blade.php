{{-- resources/views/partials/bottomnav.blade.php --}}
@php
  $user = auth()->user();
  $role = $user->role ?? 'member';
  $isStaff = in_array($role, ['super_admin','admin','staff'], true);
  $isMember = !$isStaff;

  if ($isMember) {
    $items = [
      ['route'=>'beranda','label'=>'Beranda','icon'=>'#nb-icon-home','tone'=>'#4A90E2','active_match'=>fn()=>request()->routeIs('beranda') || request()->routeIs('app')],
      ['route'=>'member.dashboard','label'=>'Member','icon'=>'#nb-icon-home','tone'=>'#1e88e5','active_match'=>fn()=>request()->routeIs('member.dashboard') || request()->routeIs('member.home')],
      ['route'=>'member.pinjaman','label'=>'Pinjaman','icon'=>'#nb-icon-rotate','tone'=>'#fb8c00','active_match'=>fn()=>request()->routeIs('member.pinjaman*')],
      ['route'=>'katalog.index','label'=>'Katalog','icon'=>'#nb-icon-book','tone'=>'#27AE60','active_match'=>fn()=>request()->routeIs('katalog.*')],
      ['route'=>'komunitas.feed','label'=>'Komunitas','icon'=>'#nb-icon-chat','tone'=>'#14B8A6','active_match'=>fn()=>request()->routeIs('komunitas.*')],
    ];
  } else {
    $items = [
      ['route'=>'beranda','label'=>'Beranda','icon'=>'#nb-icon-home','tone'=>'#4A90E2','active_match'=>fn()=>request()->routeIs('beranda') || request()->routeIs('app')],
      ['route'=>'katalog.index','label'=>'Katalog','icon'=>'#nb-icon-book','tone'=>'#27AE60','active_match'=>fn()=>request()->routeIs('katalog.*')],
      ['route'=>'transaksi.index','label'=>'Transaksi','icon'=>'#nb-icon-rotate','tone'=>'#F59E0B','active_match'=>fn()=>request()->routeIs('transaksi.*')],
      ['route'=>'komunitas.feed','label'=>'Komunitas','icon'=>'#nb-icon-chat','tone'=>'#14B8A6','active_match'=>fn()=>request()->routeIs('komunitas.*')],
    ];
  }
@endphp

<style>
@media (max-width: 1023px){
  .nb-bottomnav, .nb-bottomnav *{ box-sizing:border-box; }

  .nb-bottomnav{
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    max-width: 100%;
    z-index: 60;
    padding: 10px 8px calc(env(safe-area-inset-bottom) + 10px);
    overflow-x: hidden;
  }

  .nb-bottomnav .wrap{
    background: rgba(255,255,255,.78);
    border: 1px solid var(--nb-border);
    box-shadow: var(--nb-shadow);
    backdrop-filter: blur(10px);
    border-radius: 22px;
    padding: 10px 8px;
    display:flex;
    align-items:center;
    justify-content:space-around;
    gap: 4px;
    max-width: 100%;
    overflow-x: hidden;
  }
  html.dark .nb-bottomnav .wrap{ background: rgba(15,27,46,.72); }

  .nb-bn-item{
    flex: 1;
    min-width: 0;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap: 6px;
    padding: 8px 6px;
    border-radius: 16px;
    transition: transform .15s ease, background .15s ease;
  }
  .nb-bn-item:hover{ transform: translateY(-1px); }
  .nb-bn-item.active{ background: rgba(30,136,229,.10); }

  .nb-bn-ico{
    width:22px;height:22px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
  }
  .nb-bn-ico svg{ width:22px;height:22px; }

  .nb-bn-t{
    font-size: 11px;
    color: var(--nb-muted);
    font-weight: 700;
    line-height: 1;
    white-space:nowrap;
    max-width: 100%;
    overflow:hidden;
    text-overflow:ellipsis;
  }
  .nb-bn-item.active .nb-bn-t{ color: var(--nb-text); }
}
</style>

<div class="nb-bottomnav">
  <div class="wrap">
    @foreach($items as $it)
      @php $active = ($it['active_match'])(); @endphp
      <a href="{{ route($it['route']) }}"
         class="nb-bn-item {{ $active ? 'active' : '' }}"
         aria-current="{{ $active ? 'page' : 'false' }}"
         title="{{ $it['label'] }}">
        <span class="nb-bn-ico" style="color:{{ $it['tone'] }}">
          <svg viewBox="0 0 24 24"><use href="{{ $it['icon'] }}"/></svg>
        </span>
        <span class="nb-bn-t">{{ $it['label'] }}</span>
      </a>
    @endforeach
  </div>
</div>
