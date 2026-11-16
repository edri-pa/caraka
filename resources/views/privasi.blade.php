<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kebijakan privasi | Caraka</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 16px; background: #f4f4f5; line-height: 1.5; }
        main { max-width: 640px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; }
        h2 { font-size: 18px; margin-top: 24px; }
        .catatan { background: #fef3c7; padding: 10px; border-radius: 4px; font-size: 14px; }
    </style>
@include('partials.head-tema')
</head>
<body class="caraka">
@include('partials.navbar-warga')
<main>
    <h1>Kebijakan privasi</h1>
    <p class="catatan">Caraka adalah prototype untuk uji coba dan lomba inovasi. Dokumen ini menjelaskan praktik prototype dan belum ditinjau ahli hukum. Caraka bukan layanan darurat resmi.</p>

    <h2>Data yang dikumpulkan</h2>
    <ul>
        <li>Jenis kejadian, keterangan singkat, dan apakah ada korban luka.</li>
        <li>Lokasi: koordinat GPS bila Anda mengizinkan, atau kecamatan dan patokan lokasi yang Anda tulis.</li>
        <li>Nomor HP, hanya bila Anda mengisinya. Sistem juga menyimpan versi sandi (hash) dari nomor itu untuk mengenali laporan berulang tanpa menampilkan nomornya.</li>
        <li>Waktu laporan. Alamat IP dipakai untuk membatasi penyalahgunaan dan dapat tercatat di log server.</li>
    </ul>

    <h2>Untuk apa data dipakai</h2>
    <p>Untuk meneruskan laporan ke petugas, menyarankan instansi dan fasilitas terdekat, dan menghitung statistik waktu penanganan. Data tidak dijual dan tidak dipakai untuk iklan.</p>

    <h2>Siapa yang dapat melihat</h2>
    <ul>
        <li>Dispatcher dan admin dapat melihat laporan secara lengkap, termasuk nomor HP.</li>
        <li>Responder hanya melihat tugas yang ditugaskan ke instansinya. Nomor HP pelapor dapat dilihat responder yang ditugaskan selama tugas berjalan, hanya untuk menemukan lokasi dan menangani kejadian. Setiap akses tercatat, dan nomor disembunyikan dari responder setelah tugas ditutup.</li>
        <li>Halaman status publik hanya menampilkan nomor referensi, status, jenis kejadian, kecamatan, dan waktu.</li>
    </ul>

    <h2>Berapa lama data disimpan</h2>
    <p>Pada tahap prototype, data uji dihapus setelah kegiatan uji selesai. Aturan penyimpanan untuk penggunaan nyata belum ditetapkan dan harus disepakati dengan pengelola layanan.</p>

    <h2>Keamanan</h2>
    <p>Kata sandi petugas disimpan dalam bentuk hash, dan akses dibatasi menurut peran. Prototype ini belum diaudit keamanannya oleh pihak independen.</p>

    <h2>Permintaan penghapusan</h2>
    <p>Anda dapat meminta data pribadi pada laporan Anda dihapus dengan menyebutkan nomor referensi kepada penyelenggara uji coba yang memberi Anda tautan ini. Laporan tetap tercatat tanpa data pribadi agar statistik dan jejak audit tidak rusak.</p>

    <p><a href="{{ route('beranda') }}">&laquo; Kembali ke beranda</a></p>
</main>
@include('partials.versi')
</body>
</html>
