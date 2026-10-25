<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul }} | Caraka</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 16px; background: #f4f4f5; display: grid; min-height: 100vh; place-items: center; }
        main { max-width: 440px; background: #fff; padding: 24px; border-radius: 8px; text-align: center; }
        .kode { font-size: 48px; font-weight: bold; color: #9ca3af; margin: 0; }
        .catatan { background: #fef3c7; padding: 10px; border-radius: 4px; font-size: 14px; text-align: left; }
    </style>
</head>
<body>
<main>
    <p class="kode">{{ $kode }}</p>
    <h1>{{ $judul }}</h1>
    <p>{{ $pesan }}</p>
    <p><a href="{{ url('/') }}">Kembali ke beranda</a></p>
    <p class="catatan"><strong>Prototype untuk demo.</strong> Dalam keadaan darurat sungguhan, hubungi PSC 119 (medis) atau 110 (polisi).</p>
</main>
</body>
</html>
