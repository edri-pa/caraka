<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tugas Saya | Caraka</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 16px; background: #f4f4f5; }
        header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        h1 { margin: 0; font-size: 22px; }
        .tab { margin: 12px 0; display: flex; gap: 6px; }
        .tab a { padding: 6px 10px; background: #e5e7eb; border-radius: 4px; text-decoration: none; color: #111; font-size: 14px; }
        .tab a.aktif { background: #1d4ed8; color: #fff; }
        .tabel { overflow-x: auto; background: #fff; border-radius: 6px; }
        table { border-collapse: collapse; width: 100%; font-size: 14px; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { background: #f9fafb; white-space: nowrap; }
        .lencana { padding: 2px 8px; border-radius: 10px; font-size: 12px; background: #fef3c7; color: #92400e; white-space: nowrap; }
        .prio-5, .prio-4 { font-weight: bold; color: #b91c1c; }
        .kecil { font-size: 12px; color: #555; }
        .kosong { padding: 24px; text-align: center; color: #555; }
        .info { background: #fef3c7; padding: 10px; border-radius: 4px; margin: 12px 0; }
        button { padding: 6px 12px; cursor: pointer; }
    </style>
</head>
<body>
@php
    $labels = [
        'ditugaskan' => 'Ditugaskan', 'ditangani' => 'Ditangani', 'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan', 'tidak_terbukti' => 'Tidak terbukti',
    ];
@endphp
<header>
    <div>
        <h1>Tugas Saya</h1>
        <span class="kecil">
            {{ auth()->user()->name }} &middot;
            {{ $agency ? $agency->name : 'belum terhubung ke instansi' }}
        </span>
    </div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Keluar</button>
    </form>
</header>

@if ($agency === null)
    <p class="info">Akun ini belum terhubung ke instansi, jadi belum ada tugas yang bisa ditampilkan. Hubungi admin.</p>
@else
    <nav class="tab">
        <a href="{{ route('responder.tugas') }}" class="{{ $tab === 'aktif' ? 'aktif' : '' }}">Tugas aktif</a>
        <a href="{{ route('responder.tugas', ['tab' => 'riwayat']) }}" class="{{ $tab === 'riwayat' ? 'aktif' : '' }}">Riwayat</a>
    </nav>

    <div class="tabel">
        @if ($incidents->isEmpty())
            <p class="kosong">{{ $tab === 'aktif' ? 'Belum ada tugas aktif.' : 'Belum ada riwayat.' }}</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Prioritas</th>
                        <th>Referensi</th>
                        <th>Status</th>
                        <th>Jenis</th>
                        <th>Lokasi</th>
                        <th>Peran</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($incidents as $i)
                    @php
                        $waktu = \Illuminate\Support\Carbon::parse($i->reported_at, config('app.timezone'))->setTimezone('Asia/Jakarta');
                    @endphp
                    <tr>
                        <td class="prio-{{ $i->priority_score }}">{{ $i->priority_score }}</td>
                        <td><a href="{{ route('staf.laporan.show', $i->id) }}">{{ $i->reference }}</a></td>
                        <td><span class="lencana">{{ $labels[$i->status] ?? $i->status }}</span></td>
                        <td>{{ $i->category }}</td>
                        <td>
                            {{ $i->location_source === 'gps' ? 'GPS' : 'Manual' }}
                            @if ($i->district)<br>{{ $i->district }}@endif
                            @if ($i->landmark_text)<br><span class="kecil">{{ $i->landmark_text }}</span>@endif
                        </td>
                        <td>{{ $i->peran }}</td>
                        <td>{{ $waktu->format('d-m H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endif
</body>
</html>
