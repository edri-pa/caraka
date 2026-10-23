<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SOS terkirim | Caraka</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 16px; background: #f4f4f5; }
        main { max-width: 480px; margin: auto; background: #fff; padding: 16px; border-radius: 8px; }
        .ref { font-size: 28px; letter-spacing: 2px; font-weight: bold; margin: 4px 0 12px; }
        .ok { background: #dcfce7; color: #166534; padding: 10px; border-radius: 4px; }
        .peringatan { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; }
        .galat { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; margin: 8px 0; font-size: 14px; }
        .catatan { background: #fef3c7; padding: 10px; border-radius: 4px; font-size: 14px; margin-top: 16px; }
        label { display: block; margin: 12px 0 4px; font-weight: bold; font-size: 14px; }
        select, input, button { width: 100%; box-sizing: border-box; padding: 10px; font-size: 16px; }
        button { margin-top: 16px; cursor: pointer; }
    </style>
</head>
<body>
<main>
    <h1>SOS terkirim</h1>

    @if (session('tersimpan'))
        <p class="ok">Keterangan tambahan tersimpan.</p>
    @endif

    <p>Nomor referensi:</p>
    <p class="ref">{{ $reference }}</p>

    @if ($hasLocation)
        <p class="ok">Lokasi Anda sudah terkirim.</p>
    @else
        <p class="peringatan">
            <strong>Lokasi tidak terkirim.</strong> Tulis patokan lokasi di bawah, dan segera hubungi
            PSC 119 (medis) atau 110 (polisi) jika keadaan darurat.
        </p>
    @endif

    @if ($errors->any())
        <div class="galat">
            @foreach ($errors->all() as $galat)
                <div>{{ $galat }}</div>
            @endforeach
        </div>
    @endif

    <h2>Keterangan tambahan (opsional)</h2>
    <form method="POST" action="{{ route('sos.tambahan', $reference) }}">
        @csrf
        <label for="has_casualty">Ada korban luka?</label>
        <select id="has_casualty" name="has_casualty">
            <option value="">Tidak tahu</option>
            <option value="1">Ya, ada korban luka</option>
            <option value="0">Tidak ada korban luka</option>
        </select>

        <label for="landmark_text">Patokan lokasi</label>
        <input id="landmark_text" name="landmark_text" type="text" maxlength="255" placeholder="Contoh: depan pasar, dekat balai desa">

        <label for="reporter_phone">Nomor HP agar bisa dihubungi</label>
        <input id="reporter_phone" name="reporter_phone" type="tel" inputmode="tel">

        <button type="submit">Kirim keterangan tambahan</button>
    </form>

    <p><a href="{{ route('lapor.show', $reference) }}">Lihat status laporan</a></p>

    <p class="catatan">Prototype untuk demo. Tidak menggantikan layanan darurat resmi.</p>
</main>
</body>
</html>
