<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Uji Reverb</title>
    @vite(['resources/js/app.js'])
</head>
<body>
    <h1>Uji Reverb</h1>
    <p id="status">Menunggu koneksi...</p>
    <ul id="log"></ul>

    <script type="module">
        window.addEventListener('load', () => {
            window.Echo.channel('ping-test')
                .listen('.ping.test', (e) => {
                    const li = document.createElement('li');
                    li.textContent = new Date().toLocaleTimeString() + ' - ' + e.pesan;
                    document.getElementById('log').appendChild(li);
                });
            document.getElementById('status').textContent = 'Berlangganan channel ping-test';
        });
    </script>
</body>
</html>