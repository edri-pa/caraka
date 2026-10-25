<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Status Laporan | Caraka</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 16px; background: #f4f4f5; }
        main { max-width: 480px; margin: auto; background: #fff; padding: 16px; border-radius: 8px; }
        .ref { font-size: 28px; letter-spacing: 2px; font-weight: bold; margin: 8px 0; }
        .ok { background: #dcfce7; color: #166534; padding: 10px; border-radius: 4px; }
        dt { font-size: 13px; color: #555; margin-top: 12px; }
        dd { margin: 2px 0 0; font-size: 17px; }
        .catatan { background: #fef3c7; padding: 10px; border-radius: 4px; font-size: 14px; margin-top: 16px; }
    </style>
</head>
<body>
@php
    $labels = [
        'baru' => 'Diterima, menunggu verifikasi',
        'diverifikasi' => 'Sudah diverifikasi petugas',
        'ditugaskan' => 'Petugas sudah ditugaskan',
        'ditangani' => 'Sedang ditangani',
        'selesai' => 'Selesai',
        'tidak_terbukti' => 'Tidak terbukti',
        'dibatalkan' => 'Dibatalkan',
    ];
    $waktu = \Illuminate\Support\Carbon::parse($incident->reported_at, config('app.timezone'))
        ->setTimezone('Asia/Jakarta');
@endphp
<main>
    <h1>Status Laporan</h1>

    @if (session('baru'))
        <p class="ok">Laporan Anda sudah diterima. Simpan nomor referensi di bawah ini.</p>
    @endif

    <p>Nomor referensi:</p>
    <p class="ref">{{ $incident->reference }}</p>

    <dl>
        <dt>Status</dt>
        <dd>{{ $labels[$incident->status] ?? $incident->status }}</dd>
        <dt>Jenis kejadian</dt>
        <dd>{{ $incident->category }}</dd>
        @if ($incident->district)
            <dt>Kecamatan</dt>
            <dd>{{ $incident->district }}</dd>
        @endif
        <dt>Waktu laporan</dt>
        <dd>{{ $waktu->format('d-m-Y H:i') }} WIB</dd>
    </dl>

    <p class="catatan">
        Prototype untuk demo. Dalam keadaan darurat sungguhan, segera hubungi layanan darurat resmi (112).
    </p>
<p style="font-size:13px;color:#555;"><a href="{{ route('privasi') }}">Kebijakan privasi</a></p>
</main>

<script>
    try { localStorage.removeItem('caraka_lapor_draf'); } catch (e) {}
</script>
</body>
</html>
