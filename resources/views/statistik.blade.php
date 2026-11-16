<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statistik | Caraka</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 16px; background: #f4f4f5; }
        main { max-width: 900px; margin: auto; }
        header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        h1 { margin: 8px 0; font-size: 22px; }
        h2 { font-size: 16px; margin: 20px 0 8px; }
        .filter { margin: 8px 0; display: flex; gap: 6px; }
        .filter a { padding: 6px 10px; background: #e5e7eb; border-radius: 4px; text-decoration: none; color: #111; font-size: 14px; }
        .filter a.aktif { background: #1d4ed8; color: #fff; }
        .kartu { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
        .kotak { background: #fff; border-radius: 6px; padding: 14px 16px; }
        .kotak.utama { border: 2px solid #1d4ed8; }
        .angka { font-size: 28px; font-weight: bold; margin: 4px 0; }
        .kecil { font-size: 13px; color: #555; }
        .peringatan { background: #fef3c7; color: #92400e; padding: 10px; border-radius: 4px; margin: 12px 0; }
        table { border-collapse: collapse; width: 100%; font-size: 14px; background: #fff; border-radius: 6px; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        ul { font-size: 13px; color: #444; padding-left: 18px; }
        button { padding: 6px 12px; cursor: pointer; }
    </style>
@include('partials.head-tema')
</head>
<body class="caraka staf">
@include('partials.navbar-staf')
@php
    $labelStatus = [
        'baru' => 'Baru', 'diverifikasi' => 'Diverifikasi', 'ditugaskan' => 'Ditugaskan',
        'ditangani' => 'Ditangani', 'selesai' => 'Selesai',
        'tidak_terbukti' => 'Tidak terbukti', 'dibatalkan' => 'Dibatalkan',
    ];
@endphp
<main>
    <header>
        <a href="{{ route('dispatcher.laporan') }}">&laquo; Daftar laporan</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    </header>

    <h1>Statistik</h1>

    <nav class="filter">
        <a href="{{ route('statistik') }}" class="{{ $periode === 'semua' ? 'aktif' : '' }}">Semua waktu</a>
        <a href="{{ route('statistik', ['periode' => '30']) }}" class="{{ $periode === '30' ? 'aktif' : '' }}">30 hari</a>
        <a href="{{ route('statistik', ['periode' => '7']) }}" class="{{ $periode === '7' ? 'aktif' : '' }}">7 hari</a>
    </nav>

    @if ($demo > 0)
        <p class="peringatan"><strong>Data demo.</strong> Statistik ini memuat {{ $demo }} laporan DEMO fiktif. Hapus dengan demo:isi --hapus sebelum mengambil bukti.</p>
    @endif

    @if ($data['total'] === 0)
        <p class="peringatan">Belum ada laporan pada periode ini.</p>
    @else
        @if ($data['total'] < 10)
            <p class="peringatan">
                <strong>Sampel kecil</strong> ({{ $data['total'] }} laporan). Angka di bawah belum representatif,
                dan pada tahap prototype umumnya berasal dari simulasi.
            </p>
        @endif

        <h2>Waktu sejak laporan masuk</h2>
        <div class="kartu">
            @foreach ($data['stages'] as $kode => $tahap)
                <div class="kotak {{ $kode === 'ditangani' ? 'utama' : '' }}">
                    <strong>Waktu sampai {{ strtolower($tahap['label']) }}</strong>
                    @if ($kode === 'ditangani')<span class="kecil">(ukuran utama)</span>@endif
                    <div class="angka">{{ $format($tahap['median']) }}</div>
                    <div class="kecil">median dari {{ $tahap['count'] }} laporan</div>
                    <div class="kecil">
                        rata-rata {{ $format($tahap['mean']) }} &middot;
                        tercepat {{ $format($tahap['min']) }} &middot;
                        terlama {{ $format($tahap['max']) }}
                    </div>
                    <div class="kecil">belum tercapai, masih berjalan: {{ $tahap['pending'] }}</div>
                </div>
            @endforeach
        </div>

        <h2>Ringkasan laporan</h2>
        <table>
            <tr><th>Laporan yang dihitung</th><td>{{ $data['total'] }}</td></tr>
            <tr><th>Ditandai dan tidak dihitung</th><td>{{ $data['ditandai']['sengaja_palsu'] }} sengaja palsu, {{ $data['ditandai']['lainnya'] }} alasan lain</td></tr>
            <tr><th>Dengan lokasi GPS</th><td>{{ $data['gps']['dengan_gps'] }} (akurasi median {{ $data['gps']['median_akurasi'] === null ? '-' : round($data['gps']['median_akurasi']).' m' }})</td></tr>
            <tr><th>Lokasi manual</th><td>{{ $data['gps']['manual'] }}</td></tr>
            <tr><th>Berasal dari tombol SOS</th><td>{{ $data['sos'] }}</td></tr>
            @foreach ($data['status'] as $status => $jumlah)
                <tr><th>Status {{ $labelStatus[$status] ?? $status }}</th><td>{{ $jumlah }}</td></tr>
            @endforeach
        </table>

        <h2>Cara menghitung</h2>
        <ul>
            <li>Waktu dihitung dari laporan masuk sampai <strong>pertama kali</strong> tahap itu tercapai menurut catatan audit.</li>
            <li>Nilai utama adalah median (nilai tengah), supaya satu laporan yang sangat lambat tidak merusak gambaran.</li>
            <li>Laporan yang ditandai tidak dihitung. Selisih waktu negatif diabaikan.</li>
            <li>Laporan yang dibatalkan atau tidak terbukti tidak dianggap masih menunggu.</li>
            <li>Statistik hanya mencerminkan laporan di sistem ini, bukan kondisi seluruh wilayah.</li>
        </ul>
    @endif
</main>
@include('partials.versi')
</body>
</html>
