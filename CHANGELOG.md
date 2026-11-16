# Riwayat perubahan

Format mengikuti Keep a Changelog. Nomor versi ada di berkas `VERSION`. Saat membuat penanda Git:
ubah `VERSION`, pindahkan isi "Belum dirilis" ke bagian versi baru, lalu `git tag -a vX.Y.Z`.

## [0.12.0] Ketahanan dan versi

### Diperbaiki
- Penerapan saran instansi kini atomik: laporan dikunci dan saran dihitung ulang di dalam kunci, sehingga instansi utama yang baru ditetapkan petugas lain tidak tertimpa.
- Kata sandi akun demo dibaca lewat konfigurasi (`config/caraka.php`), bukan `env()` langsung, supaya aman bila konfigurasi di-cache di server.

### Ditambahkan
- Nomor versi aplikasi (berkas `VERSION`) di kaki halaman dan di `demo:periksa`. Komit Git hanya tampil untuk petugas.
- Alur CI GitHub Actions (tes pada setiap push) dan pemantauan pembaruan aksi lewat Dependabot.

## [0.11.0] Demo kapan saja
- `demo:periksa`, `demo:isi`, skrip `mulai-demo.sh` dan `akhiri-demo.sh`, dan `docs/DEMO-CEPAT.md`.

## [0.10.0] Bukti uji dan akses nomor responder
- `gladi:ekspor` (ekspor tanpa data pribadi) dan lembar catatan gladi.
- Nomor pelapor untuk responder: hanya selama tugas berjalan, dan akses tercatat.

## [0.9.0] Kandidat rilis
- Fitur wajib lengkap: lapor, SOS, dashboard real-time, peta dan saran fasilitas, saran instansi, penandaan, statistik, privasi, keamanan, dan dokumentasi.
