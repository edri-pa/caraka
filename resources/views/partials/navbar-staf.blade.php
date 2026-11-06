@php
    $peran = auth()->user()->role ?? null;
    $aktif = fn (...$pola) => request()->routeIs(...$pola) ? ' aria-current="page"' : '';
@endphp
<nav class="nav-kaca" aria-label="Navigasi petugas">
    <a class="nav-merek" href="{{ $peran === 'responder' ? route('responder.tugas') : route('dispatcher.laporan') }}">Caraka</a>
    <div class="nav-daftar">
        <span class="nav-cair" aria-hidden="true"></span>
        @if ($peran === 'responder')
            <a href="{{ route('responder.tugas') }}"{!! $aktif('responder.tugas', 'staf.laporan.*') !!}>Tugas saya</a>
        @else
            <a href="{{ route('dispatcher.laporan') }}"{!! $aktif('dispatcher.laporan', 'staf.laporan.*') !!}>Laporan</a>
            <a href="{{ route('statistik') }}"{!! $aktif('statistik') !!}>Statistik</a>
        @endif
    </div>
    <span class="nav-nama">{{ auth()->user()->name ?? '' }}</span>
    <form class="nav-keluar" method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Keluar</button>
    </form>
</nav>
<script src="{{ asset('js/navbar.js') }}?v={{ @filemtime(public_path('js/navbar.js')) }}" defer></script>
