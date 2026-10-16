<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan {{ $incident->reference }} | Caraka</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/js/app.js'])
    <style>
        body { font-family: sans-serif; margin: 0; padding: 16px; background: #f4f4f5; }
        main { max-width: 760px; margin: auto; }
        section { background: #fff; border-radius: 6px; padding: 14px 16px; margin-bottom: 14px; }
        h1 { margin: 4px 0; font-size: 22px; }
        h2 { margin: 0 0 8px; font-size: 16px; }
        dl { margin: 0; display: grid; grid-template-columns: 150px 1fr; gap: 6px 12px; font-size: 14px; }
        dt { color: #555; }
        dd { margin: 0; }
        .lencana { padding: 2px 8px; border-radius: 10px; font-size: 12px; background: #fef3c7; color: #92400e; }
        .sukses { background: #dcfce7; color: #166534; padding: 10px; border-radius: 4px; margin-bottom: 12px; }
        .galat { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; margin-bottom: 12px; }
        select, input, button { padding: 8px; font-size: 15px; margin: 4px 4px 4px 0; }
        table { border-collapse: collapse; width: 100%; font-size: 13px; }
        th, td { padding: 6px 8px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .atas { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .kecil { font-size: 12px; color: #555; }
    </style>
@php
    $authUser = auth()->user();
    $liveChannel = in_array($authUser->role, ['dispatcher', 'admin'], true)
        ? 'dispatch'
        : ($authUser->agency_id ? 'agency.'.$authUser->agency_id : '');
@endphp
</head>
<body data-channel="{{ $liveChannel }}" data-incident-id="{{ $incident->id }}">
@php
    $labels = [
        'baru' => 'Baru', 'diverifikasi' => 'Diverifikasi', 'ditugaskan' => 'Ditugaskan',
        'ditangani' => 'Ditangani', 'selesai' => 'Selesai',
        'tidak_terbukti' => 'Tidak terbukti', 'dibatalkan' => 'Dibatalkan',
    ];
    $wib = fn ($waktu) => \Illuminate\Support\Carbon::parse($waktu, config('app.timezone'))
        ->setTimezone('Asia/Jakarta')->format('d-m-Y H:i');
@endphp
<main>
    <div class="atas">
        @if ($canGoBack)
            <a href="{{ route('dispatcher.laporan') }}">&laquo; Daftar laporan</a>
        @else
            <span></span>
        @endif
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    </div>

    <h1>{{ $incident->reference }}</h1>

<div id="banner-pembaruan" hidden style="background:#dbeafe;color:#1e40af;padding:10px;border-radius:4px;margin:8px 0;">
    Laporan ini diperbarui. <button type="button" id="muat-ulang">Muat ulang</button>
</div>

    @if (session('sukses'))
        <div class="sukses">{{ session('sukses') }}</div>
    @endif
    @if ($errors->any())
        <div class="galat">
            @foreach ($errors->all() as $galat)
                <div>{{ $galat }}</div>
            @endforeach
        </div>
    @endif

    <section>
        <h2>Ringkasan</h2>
        <dl>
            <dt>Status</dt>
            <dd><span class="lencana">{{ $labels[$incident->status] ?? $incident->status }}</span></dd>
            <dt>Jenis kejadian</dt>
            <dd>{{ $incident->category }}</dd>
            <dt>Prioritas</dt>
            <dd>{{ $incident->priority_score }} (1 sampai 5)</dd>
            <dt>Skor kepercayaan</dt>
            <dd>{{ $incident->trust_score }} (saran, bukan keputusan)</dd>
            <dt>Keterangan</dt>
            <dd>{{ $incident->description ?: '-' }}</dd>
            <dt>Nomor HP pelapor</dt>
            <dd>{{ $incident->reporter_phone ?: '-' }}</dd>
            <dt>Waktu laporan</dt>
            <dd>{{ $wib($incident->reported_at) }} WIB</dd>
            @if ($incident->closed_at)
                <dt>Ditutup</dt>
                <dd>{{ $wib($incident->closed_at) }} WIB</dd>
            @endif
        </dl>
    </section>

    <section>
        <h2>Lokasi</h2>
        <dl>
            <dt>Sumber</dt>
            <dd>
                @if ($incident->location_source === 'gps')
                    GPS (akurasi sekitar {{ round((float) $incident->location_accuracy_m) }} m)
                @else
                    Manual
                @endif
            </dd>
            <dt>Kecamatan</dt>
            <dd>{{ $incident->district ?: '-' }}</dd>
            <dt>Patokan</dt>
            <dd>{{ $incident->landmark_text ?: '-' }}</dd>
            @if ($incident->lat !== null && $incident->lng !== null)
                <dt>Koordinat</dt>
                <dd>
                    {{ round((float) $incident->lat, 6) }}, {{ round((float) $incident->lng, 6) }}
                    <br>
                    <a href="https://www.openstreetmap.org/?mlat={{ round((float) $incident->lat, 6) }}&amp;mlon={{ round((float) $incident->lng, 6) }}#map=17/{{ round((float) $incident->lat, 6) }}/{{ round((float) $incident->lng, 6) }}"
                       target="_blank" rel="noopener noreferrer">Buka di OpenStreetMap</a>
                </dd>
            @endif
        </dl>
    </section>

    <section>
        <h2>Instansi</h2>
        @if ($agencies->isEmpty())
            <p class="kecil">Belum ada instansi yang ditugaskan.</p>
        @else
            <ul>
                @foreach ($agencies as $agency)
                    <li>{{ $agency->name }} ({{ $agency->type }}) &mdash; {{ $agency->role }}</li>
                @endforeach
            </ul>
        @endif

        @if ($canAssign)
            <h2>Tetapkan instansi</h2>
            <form method="POST" action="{{ route('staf.laporan.instansi', $incident->id) }}">
                @csrf
                <select name="agency_id" required>
                    <option value="">-- Pilih instansi --</option>
                    @foreach ($agencyOptions as $option)
                        <option value="{{ $option->id }}">{{ $option->name }} ({{ $option->type }})</option>
                    @endforeach
                </select>
                <select name="role" required>
                    <option value="utama">Utama</option>
                    <option value="pendukung">Pendukung</option>
                </select>
                <button type="submit">Simpan instansi</button>
            </form>
        @endif
    </section>

    @if (count($allowed) > 0)
        <section>
            <h2>Ubah status</h2>
            <form method="POST" action="{{ route('staf.laporan.status', $incident->id) }}">
                @csrf
                <select name="to" required>
                    @foreach ($allowed as $tujuan)
                        <option value="{{ $tujuan }}">{{ $labels[$tujuan] ?? $tujuan }}</option>
                    @endforeach
                </select>
                <input type="text" name="note" maxlength="500" placeholder="Catatan (opsional)">
                <button type="submit">Simpan status</button>
            </form>
        </section>
    @endif

    <section>
        <h2>Riwayat</h2>
        <table>
            <thead>
                <tr><th>Waktu</th><th>Tindakan</th><th>Perubahan</th><th>Oleh</th><th>Catatan</th></tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td>{{ $wib($log->created_at) }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->from_status ?: '-' }} &rarr; {{ $log->to_status ?: '-' }}</td>
                        <td>{{ $log->pelaku ?: 'Sistem' }}</td>
                        <td>{{ $log->note ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</main>
<script>
(function () {
    var channel = document.body.getAttribute('data-channel');
    var incidentId = parseInt(document.body.getAttribute('data-incident-id'), 10);
    var banner = document.getElementById('banner-pembaruan');

    document.getElementById('muat-ulang').addEventListener('click', function () { window.location.reload(); });

    window.addEventListener('load', function () {
        if (!window.Echo || !channel) { return; }
        try {
            window.Echo.private(channel).listen('.incident.updated', function (e) {
                if (e && parseInt(e.incident_id, 10) === incidentId) { banner.hidden = false; }
            });
        } catch (err) {}
    });
})();
</script>
</body>
</html>
