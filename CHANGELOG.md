# Riwayat perubahan

Format mengikuti Keep a Changelog. Nomor versi ada di berkas `VERSION`. Saat membuat penanda Git:
ubah `VERSION`, pindahkan isi "Belum dirilis" ke bagian versi baru, lalu `git tag -a vX.Y.Z`.

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
Penanda `v0.12.0` sudah dipublikasikan lebih dulu dan tidak memuat perubahan di atas. Gunakan 0.12.1.

## [0.12.0] Penerapan saran atomik dan CI awal
- Metode penerapan saran instansi yang atomik (`IncidentWorkflow::applySuggestions`): laporan dikunci dan saran dihitung ulang di dalam kunci.
- Alur CI GitHub Actions awal (tes pada setiap push).

## [0.11.0] Demo kapan saja
- `demo:periksa`, `demo:isi`, skrip `mulai-demo.sh` dan `akhiri-demo.sh`, dan `docs/DEMO-CEPAT.md`.

## [0.10.0] Bukti uji dan akses nomor responder
- `gladi:ekspor` (ekspor tanpa data pribadi) dan lembar catatan gladi.
- Nomor pelapor untuk responder: hanya selama tugas berjalan, dan akses tercatat.

## [0.9.0] Kandidat rilis
- Fitur wajib lengkap: lapor, SOS, dashboard real-time, peta dan saran fasilitas, saran instansi, penandaan, statistik, privasi, keamanan, dan dokumentasi.
