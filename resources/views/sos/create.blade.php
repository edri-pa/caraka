<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SOS | Caraka</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 16px; background: #fef2f2; text-align: center; }
        main { max-width: 420px; margin: auto; }
        h1 { margin: 8px 0; }
        #sos { width: 100%; font-size: 40px; font-weight: bold; padding: 48px 16px; margin: 20px 0; border: 0; border-radius: 16px; background: #dc2626; color: #fff; cursor: pointer; }
        #sos[disabled] { background: #9ca3af; }
        #status { min-height: 24px; font-size: 15px; }
        .catatan { background: #fef3c7; padding: 10px; border-radius: 4px; font-size: 14px; text-align: left; }
        a { color: #1d4ed8; }
    </style>
</head>
<body>
<main>
    <h1>Butuh bantuan darurat?</h1>
    <p>Tekan tombol satu kali. Lokasi Anda dikirim otomatis bila diizinkan.</p>

    <form id="form-sos" method="POST" action="{{ route('sos.store') }}">
        @csrf
        <input type="hidden" id="latitude" name="latitude">
        <input type="hidden" id="longitude" name="longitude">
        <input type="hidden" id="accuracy" name="accuracy">
        <button type="submit" id="sos">SOS</button>
    </form>
    <p id="status" role="status"></p>

    <p class="catatan">
        <strong>Prototype untuk demo.</strong> Tombol ini tidak menggantikan layanan darurat resmi.
        Dalam keadaan darurat sungguhan, segera hubungi PSC 119 (medis) atau 110 (polisi).
    </p>
    <p><a href="{{ route('lapor.create') }}">Lapor dengan keterangan lengkap</a></p>
</main>

<script>
(function () {
    var form = document.getElementById('form-sos');
    var tombol = document.getElementById('sos');
    var status = document.getElementById('status');
    var sedangKirim = false;

    function kirim() {
        if (sedangKirim) { return; }
        sedangKirim = true;
        tombol.disabled = true;
        form.submit();
    }

    form.addEventListener('submit', function (e) {
        if (sedangKirim) { return; }
        e.preventDefault();
        tombol.disabled = true;
        status.textContent = 'Mengambil lokasi...';

        if (!('geolocation' in navigator)) {
            status.textContent = 'GPS tidak tersedia. Mengirim tanpa lokasi...';
            kirim();
            return;
        }

        var selesai = false;
        var batas = setTimeout(function () {
            if (selesai) { return; }
            selesai = true;
            status.textContent = 'Lokasi belum didapat. Mengirim tanpa lokasi...';
            kirim();
        }, 8000);

        navigator.geolocation.getCurrentPosition(function (pos) {
            if (selesai) { return; }
            selesai = true;
            clearTimeout(batas);
            document.getElementById('latitude').value = pos.coords.latitude;
            document.getElementById('longitude').value = pos.coords.longitude;
            document.getElementById('accuracy').value = Math.round(pos.coords.accuracy);
            status.textContent = 'Mengirim...';
            kirim();
        }, function () {
            if (selesai) { return; }
            selesai = true;
            clearTimeout(batas);
            status.textContent = 'Lokasi tidak diizinkan. Mengirim tanpa lokasi...';
            kirim();
        }, { enableHighAccuracy: true, timeout: 7000, maximumAge: 10000 });
    });
})();
</script>
</body>
</html>
