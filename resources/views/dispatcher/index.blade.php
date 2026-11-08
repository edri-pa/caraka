<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Masuk | Caraka</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/app.js'])
    <style>
        body { font-family: sans-serif; margin: 0; padding: 16px; background: #f4f4f5; }
        header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        h1 { margin: 0; font-size: 22px; }
        .filter { margin: 12px 0; display: flex; gap: 6px; flex-wrap: wrap; }
        .filter a { padding: 6px 10px; background: #e5e7eb; border-radius: 4px; text-decoration: none; color: #111; font-size: 14px; }
        .filter a.aktif { background: #1d4ed8; color: #fff; }
        .tabel { overflow-x: auto; background: #fff; border-radius: 6px; }
        table { border-collapse: collapse; width: 100%; font-size: 14px; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { background: #f9fafb; white-space: nowrap; }
        .lencana { padding: 2px 8px; border-radius: 10px; font-size: 12px; white-space: nowrap; }
        .st-baru { background: #fee2e2; color: #991b1b; }
        .st-diverifikasi, .st-ditugaskan, .st-ditangani { background: #fef3c7; color: #92400e; }
        .st-selesai { background: #dcfce7; color: #166534; }
        .st-tidak_terbukti, .st-dibatalkan { background: #e5e7eb; color: #374151; }
        .prio-5, .prio-4 { font-weight: bold; color: #b91c1c; }
        .kecil { font-size: 12px; color: #555; }
        .kosong { padding: 24px; text-align: center; color: #555; }
        .halaman { margin: 12px 0; display: flex; gap: 12px; }
        button { padding: 6px 12px; cursor: pointer; }
    </style>
@include('partials.head-tema')
</head>
<body class="caraka staf" data-channel="dispatch" data-signature="{{ $signature }}" data-ringkasan-url="{{ route('dispatcher.ringkasan') }}">
@include('partials.navbar-staf')
@php
    $labels = [
        'baru' => 'Baru', 'diverifikasi' => 'Diverifikasi', 'ditugaskan' => 'Ditugaskan',
        'ditangani' => 'Ditangani', 'selesai' => 'Selesai',
        'tidak_terbukti' => 'Tidak terbukti', 'dibatalkan' => 'Dibatalkan',
        'aktif' => 'Aktif', 'semua' => 'Semua',
    ];
@endphp
<header>
    <div>
        <h1>Laporan Masuk</h1>
        <span class="kecil">{{ $activeCount }} laporan aktif &middot; masuk sebagai {{ auth()->user()->name }} ({{ auth()->user()->role }})</span>
    </div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Keluar</button>
    </form>
</header>

<div id="banner-pembaruan" hidden style="background:#dbeafe;color:#1e40af;padding:10px;border-radius:4px;margin:8px 0;">
    Ada pembaruan laporan. <button type="button" id="muat-ulang">Muat ulang</button>
</div>
<p id="status-koneksi" class="kecil">Real-time: menghubungkan...</p>

<nav class="filter">
    @foreach ($filters as $f)
        <a href="{{ route('dispatcher.laporan', ['status' => $f]) }}" class="{{ $f === $filter ? 'aktif' : '' }}">{{ $labels[$f] ?? $f }}</a>
    @endforeach
</nav>

<div class="tabel">
    @if ($incidents->isEmpty())
        <p class="kosong">Belum ada laporan pada filter ini.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Prioritas</th>
                    <th>Referensi</th>
                    <th>Status</th>
                    <th>Jenis</th>
                    <th>Lokasi</th>
                    <th title="Menilai kelengkapan sinyal (GPS, akurasi, nomor HP), bukan kebenaran isi laporan">Skor sinyal</th>
                    <th>Keterangan</th>
                    <th>Nomor HP</th>
                    <th>Waktu</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($incidents as $i)
                @php
                    $waktu = \Illuminate\Support\Carbon::parse($i->reported_at, config('app.timezone'));
                    $menunggu = max(0, (int) $waktu->diffInMinutes(now()));
                    $label = $i->trust_score >= 70 ? 'tinggi' : ($i->trust_score >= 50 ? 'sedang' : 'rendah');
                @endphp
                <tr>
                    <td class="prio-{{ $i->priority_score }}">{{ $i->priority_score }}</td>
                    <td><a href="{{ route('staf.laporan.show', $i->id) }}">{{ $i->reference }}</a></td>
                    <td><span class="lencana st-{{ $i->status }}">{{ $labels[$i->status] ?? $i->status }}</span></td>
                    <td>{{ $i->category }}</td>
                    <td>
                        @if ($i->location_source === 'gps')
                            GPS (&plusmn;{{ round((float) $i->location_accuracy_m) }} m)
                        @else
                            Manual
                        @endif
                        @if ($i->district)<br>{{ $i->district }}@endif
                        @if ($i->landmark_text)<br><span class="kecil">{{ $i->landmark_text }}</span>@endif
                    </td>
                    <td>{{ $i->trust_score }} <span class="kecil">({{ $label }})</span></td>
                    <td>{{ $i->description ?: '-' }}</td>
                    <td>{{ $i->reporter_phone ?: '-' }}</td>
                    <td>
                        {{ $waktu->copy()->setTimezone('Asia/Jakarta')->format('d-m H:i') }}
                        <br><span class="kecil">
                            @if (in_array($i->status, ['baru', 'diverifikasi'], true))
                                menunggu {{ app(\App\Services\IncidentStatistics::class)->formatDuration($menunggu * 60) }}
                            @elseif (in_array($i->status, ['ditugaskan', 'ditangani'], true))
                                berjalan {{ app(\App\Services\IncidentStatistics::class)->formatDuration($menunggu * 60) }}
                            @else
                                ditutup
                            @endif
                        </span>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<p class="kecil">Skor sinyal hanya menilai kelengkapan data (GPS, akurasi, nomor HP), bukan kebenaran isi laporan. Petugas yang memutuskan.</p>

<div class="halaman">
    @if ($incidents->previousPageUrl())<a href="{{ $incidents->previousPageUrl() }}">&laquo; Sebelumnya</a>@endif
    @if ($incidents->nextPageUrl())<a href="{{ $incidents->nextPageUrl() }}">Berikutnya &raquo;</a>@endif
</div>
<script>
(function () {
    var banner = document.getElementById('banner-pembaruan');
    var koneksi = document.getElementById('status-koneksi');
    var channel = document.body.getAttribute('data-channel');
    var signature = document.body.getAttribute('data-signature');
    var ringkasanUrl = document.body.getAttribute('data-ringkasan-url');

    document.getElementById('muat-ulang').addEventListener('click', function () { window.location.reload(); });

    function tampilkanBanner() { banner.hidden = false; }
    function setKoneksi(teks) { koneksi.textContent = teks; }
    var tersambung = false;
    setTimeout(function () {
        if (!tersambung) { setKoneksi('Real-time belum tersambung. Pengecekan berkala (30 detik) berjalan.'); }
    }, 6000);

    window.addEventListener('load', function () {
        if (!window.Echo || !channel) {
            setKoneksi('Real-time tidak aktif. Pengecekan berkala berjalan.');
            return;
        }
        try {
            var ch = window.Echo.private(channel);
            ch.listen('.incident.updated', function () { tampilkanBanner(); });
            if (ch.subscribed) { ch.subscribed(function () { tersambung = true; setKoneksi('Real-time aktif'); }); }
            if (ch.error) { ch.error(function () { setKoneksi('Real-time gagal. Pengecekan berkala berjalan.'); }); }
        } catch (e) {
            setKoneksi('Real-time gagal. Pengecekan berkala berjalan.');
        }
    });

    // Cadangan: tanya server tiap 30 detik, hanya saat tab terlihat.
    setInterval(function () {
        if (document.hidden) { return; }
        fetch(ringkasanUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) { if (d && d.signature && d.signature !== signature) { tampilkanBanner(); } })
            .catch(function () {});
    }, 30000);
})();
</script>
</body>
</html>
