@php
    $aktif = fn (...$pola) => request()->routeIs(...$pola) ? ' aria-current="page"' : '';
@endphp
<nav class="nav-kaca" aria-label="Navigasi utama">
    <a class="nav-merek" href="{{ route('beranda') }}">Caraka</a>
    <div class="nav-daftar">
        <span class="nav-cair" aria-hidden="true"></span>
        <a href="{{ route('beranda') }}"{!! $aktif('beranda') !!}>Beranda</a>
        <a href="{{ route('lapor.create') }}"{!! $aktif('lapor.*') !!}>Lapor</a>
        <a class="nav-sos" href="{{ route('sos.create') }}"{!! $aktif('sos.*') !!}>SOS</a>
        <a href="{{ route('privasi') }}"{!! $aktif('privasi') !!}>Privasi</a>
    </div>
</nav>
<script src="{{ asset('js/navbar.js') }}?v={{ @filemtime(public_path('js/navbar.js')) }}" defer></script>
