# Riwayat perubahan

Format mengikuti Keep a Changelog. Nomor versi ada di berkas `VERSION`.
Isi tiap bagian ditulis dari isi penanda Git, bukan dari rencana. Tidak semua nomor versi punya penanda (tidak ada 0.11.0).

## [0.12.1] Versi aplikasi, konfigurasi, dan pembaruan keamanan

### Diperbaiki
- Dependensi diperbarui untuk menutup 12 advisori keamanan yang dilaporkan `composer audit` (laravel/framework dan paket symfony).
- Audit composer memakai `--locked`, dan audit npm tetap berjalan walau audit composer gagal.
- `scripts/mulai-demo.sh` tidak lagi mengambil alamat API Cloudflare sebagai alamat tunnel.
- Kata sandi akun demo dibaca lewat konfigurasi (`config/caraka.php`), bukan `env()` langsung, supaya aman bila konfigurasi di-cache di server.

### Ditambahkan
- Nomor versi aplikasi (berkas `VERSION`) di kaki halaman dan di `demo:periksa`. Komit Git hanya tampil untuk petugas.
- Tes pelindung konfigurasi (`CarakaConfigTest`, `ConfigEnvTest`) dan tes alur CI yang lebih lengkap.

### Catatan
Penanda `v0.12.0` sudah dipublikasikan lebih dulu dan tidak memuat perubahan di atas.

## [0.12.0] Pekerjaan sejak 0.10.0
- `gladi:ekspor` (ekspor tanpa data pribadi) dan lembar catatan gladi.
- `demo:periksa`, `demo:isi`, skrip `mulai-demo.sh` dan `akhiri-demo.sh`, dan `docs/DEMO-CEPAT.md`.
- Penerapan saran instansi yang atomik (`IncidentWorkflow::applySuggestions`).
- Alur CI GitHub Actions awal.

## [0.10.0] Nomor pelapor untuk responder dan penyempurnaan tampilan
- Nomor pelapor untuk responder: hanya selama tugas berjalan, dan akses tercatat.
- Tema kaca merah dan navbar kaca cair, serta perbaikan dari uji tangkapan layar (label waktu, skor sinyal, kontras).

## [0.9.0] Kandidat rilis
- Fitur wajib lengkap: lapor, SOS, dashboard real-time, peta dan saran fasilitas, saran instansi, penandaan, statistik, privasi, keamanan, dan dokumentasi.
