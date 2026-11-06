<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Caraka</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 16px; background: #f4f4f5; }
        main { max-width: 440px; margin: auto; text-align: center; }
        h1 { margin: 16px 0 4px; }
        .tombol { display: block; padding: 18px; margin: 12px 0; border-radius: 10px; color: #fff; text-decoration: none; font-size: 20px; font-weight: bold; }
        .sos { background: #dc2626; font-size: 28px; padding: 28px; }
        .lapor { background: #1d4ed8; }
        .kotak { background: #fff; padding: 14px; border-radius: 8px; margin: 12px 0; text-align: left; }
        input, button { box-sizing: border-box; width: 100%; padding: 10px; font-size: 16px; margin-top: 6px; }
        .galat { background: #fee2e2; color: #991b1b; padding: 8px; border-radius: 4px; font-size: 14px; }
        .catatan { background: #fef3c7; padding: 10px; border-radius: 4px; font-size: 14px; text-align: left; }
        .kecil { font-size: 13px; color: #555; }
    </style>
@include('partials.head-tema')
</head>
<body class="caraka">
@include('partials.navbar-warga')
<main>
    <h1>Caraka</h1>
    <p>Pelaporan kejadian darurat dalam satu pintu (prototype).</p>

    <a class="tombol sos" href="{{ route('sos.create') }}">SOS</a>
    <a class="tombol lapor" href="{{ route('lapor.create') }}">Lapor kejadian</a>

    <form class="kotak" method="POST" action="{{ route('status.cek') }}">
        @csrf
        <label for="referensi"><strong>Cek status laporan</strong></label>
        @if ($errors->has('referensi'))
            <div class="galat">{{ $errors->first('referensi') }}</div>
        @endif
        <input id="referensi" name="referensi" type="text" maxlength="20" placeholder="Nomor referensi 10 karakter" autocomplete="off">
        <button type="submit">Cek</button>
    </form>

    <p class="catatan">
        <strong>Prototype untuk demo.</strong> Tidak menggantikan layanan darurat resmi.
        Dalam keadaan darurat sungguhan, hubungi PSC 119 (medis) atau 110 (polisi).
    </p>

    <p class="kecil">
        <a href="{{ route('login') }}">Masuk untuk petugas</a> &middot;
        <a href="{{ route('privasi') }}">Kebijakan privasi</a>
    </p>
</main>
</body>
</html>
