@php
    $peran = auth()->user()->role ?? null;
    $aktif = fn (...$pola) => request()->routeIs(...$pola) ? ' aria-current="page"' : '';
    $labelPeran = ['dispatcher' => 'Dispatcher', 'admin' => 'Admin', 'responder' => 'Responder'][$peran] ?? '';
@endphp
<script>
(function () {
    try {
        var besar = window.matchMedia('(min-width: 900px)').matches;
        var pilihan = localStorage.getItem('caraka_menu');
        document.body.setAttribute('data-menu', besar ? (pilihan === 'tersembunyi' ? 'tersembunyi' : 'terbuka') : 'tersembunyi');
    } catch (e) {}
})();
</script>
<aside class="side" id="sidebar" aria-label="Menu petugas">
    <div class="side-kepala">
        <a class="side-merek" href="{{ $peran === 'responder' ? route('responder.tugas') : route('dispatcher.laporan') }}">Caraka</a>
        <button type="button" class="side-tutup" id="side-tutup" aria-controls="sidebar" aria-expanded="true" aria-label="Sembunyikan menu" title="Sembunyikan menu">
            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
    </div>
    <nav class="side-menu" aria-label="Navigasi petugas">
        <span class="side-cair" aria-hidden="true"></span>
        @if ($peran === 'responder')
            <a href="{{ route('responder.tugas') }}"{!! $aktif('responder.tugas', 'staf.laporan.*') !!}>
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6a1 1 0 0 1 1 1v2H8V4a1 1 0 0 1 1-1zM8 5H6a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1h-2"/></svg>
                <span>Tugas saya</span>
            </a>
        @else
            <a href="{{ route('dispatcher.laporan') }}"{!! $aktif('dispatcher.laporan', 'staf.laporan.*') !!}>
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                <span>Laporan masuk</span>
            </a>
            <a href="{{ route('statistik') }}"{!! $aktif('statistik') !!}>
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
                <span>Statistik</span>
            </a>
        @endif
    </nav>
    <div class="side-kaki">
        <div>
            <div class="side-nama">{{ auth()->user()->name ?? '' }}</div>
            <div class="side-peran">{{ $labelPeran }}</div>
        </div>
        <form class="nav-keluar" method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    </div>
</aside>
<div class="side-tirai" id="side-tirai"></div>
<button type="button" class="side-buka" id="side-buka" aria-controls="sidebar" aria-expanded="true" aria-label="Tampilkan menu">
    <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    <span>Menu</span>
</button>
<script src="{{ asset('js/sidebar.js') }}?v={{ @filemtime(public_path('js/sidebar.js')) }}" defer></script>
