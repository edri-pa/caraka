<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Uji GPS</title>
    <style>
        body { font-family: sans-serif; padding: 16px; max-width: 480px; margin: auto; }
        button { font-size: 18px; padding: 12px 16px; width: 100%; }
        pre { background: #f3f3f3; padding: 12px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>Uji GPS</h1>
    <p>Halaman aman (HTTPS): <strong id="https"></strong></p>
    <button id="ambil">Ambil lokasi saya</button>
    <pre id="hasil">Belum ada data.</pre>

    <script>
        document.getElementById('https').textContent = window.isSecureContext ? 'ya' : 'TIDAK';

        const hasil = document.getElementById('hasil');
        document.getElementById('ambil').addEventListener('click', () => {
            if (!('geolocation' in navigator)) {
                hasil.textContent = 'Browser tidak mendukung geolokasi.';
                return;
            }
            hasil.textContent = 'Mengambil lokasi...';
            const mulai = performance.now();
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const c = pos.coords;
                    hasil.textContent =
                        'Lintang   : ' + c.latitude + '\n' +
                        'Bujur     : ' + c.longitude + '\n' +
                        'Akurasi   : ' + Math.round(c.accuracy) + ' meter\n' +
                        'Waktu ambil: ' + Math.round(performance.now() - mulai) + ' ms\n' +
                        'Stempel waktu: ' + new Date(pos.timestamp).toLocaleString();
                },
                (err) => {
                    hasil.textContent = 'Gagal (kode ' + err.code + '): ' + err.message;
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        });
    </script>
</body>
</html>