# Caraka: catatan proyek (diperbarui Okt 2026)

## Tujuan
Sistem pelaporan darurat berbasis web, pilot Kab. Temanggung, untuk lomba Krenova (jadwal belum pasti).
Prototype pribadi, anggaran nol, tanpa klaim kerja sama pemda. Demo awal: simulasi skala kecil di lingkungan atau kantor.
Target: aplikasi jadi sebelum 1 Jan 2027, fitur dibekukan 30 Nov 2026.

## Teknologi
Windows 11 + WSL2 Ubuntu, Docker Desktop, Laravel 13 (Sail), PHP 8.5, PostgreSQL 18 + PostGIS 3.6, Redis,
Reverb, Vite, Leaflet 1.9.4, Fortify (login petugas), PHPUnit 12. Folder `~/projects/caraka`, GitHub privat (SSH).
Alias `sail` hanya berlaku di dalam folder proyek. Aplikasi: http://localhost.

## Halaman dan peran
- Warga (tanpa akun): `/`, `/lapor`, `/sos`, `/lapor/{referensi}`, `/privasi`.
- Dispatcher dan admin: `/dispatcher/laporan`, `/staf/laporan/{id}`, `/statistik`.
- Responder: `/responder/tugas` dan detail tugasnya.
- Akun demo `dispatcher@`, `responder@`, `admin@caraka.test` (sandi dari `DEMO_STAFF_PASSWORD`).
  Akun baru lewat `sail artisan pengguna:buat`. Pendaftaran publik dimatikan.

## Aturan (DRAF, belum divalidasi praktisi)
- Status: baru > diverifikasi > ditugaskan > ditangani > selesai (+ tidak_terbukti, dibatalkan). Satu instansi utama.
- Saran instansi: kecelakaan+korban = medis utama, polisi pendukung; bencana dan SAR = BPBD (SAR ditangani BPBD).
- Skor kepercayaan hanya saran; SOS prioritas 5 dengan skor rendah; korban luka menaikkan prioritas satu tingkat.
- Penandaan "sengaja palsu" wajib catatan; hanya admin menghapus tanda; riwayat pelapor (hash) belum memengaruhi skor.
- Statistik: waktu sampai diverifikasi, ditugaskan, ditangani (ukuran utama), median, sampel kecil diberi peringatan.

## Data
20 kecamatan, kategori, instansi demo (kontak `DEMO-xxxx`), fasilitas dari sumber umum (belum diverifikasi, ada
tanggal cek). Impor: `sail artisan fasilitas:impor <csv> [--simpan]`. CSV isian di `database/data/`.

## Cara kerja
Semua tes harus hijau (`sail artisan test`, sekarang 324). Berkas dibuat lewat `cat > file << 'EOF'`, penambalan
lewat skrip Python yang menolak bila potongan tidak ditemukan tepat sekali. Setelah tiap fitur: uji sabotase
(rusak sengaja, tes harus gagal, pulihkan lewat Git). Label DONE tidak membuktikan hasil, buktikan isinya.
Hindari nama fungsi tes yang bentrok dengan PHPUnit (misalnya `status()`).

## Server dan uji lapangan
Server berbayar hanya mendekati lomba. Sementara: laptop + Cloudflare quick tunnel (`sail npm run build` dulu).
`trustProxies(at: '*')` hanya untuk tunnel; batasi di server publik. GPS di HP butuh HTTPS.
Bila Docker tidak terjangkau dari Ubuntu: periksa `docker context ls` (harus `default`), integrasi WSL di Docker
Desktop, lalu `wsl --shutdown`. Jangan reset atau purge data Docker.

## Dokumen
README.md, docs/ARSITEKTUR.md (keterbatasan), docs/DEPLOY.md, docs/NASKAH-DEMO.md, docs/RENCANA-GLADI.md.

## Belum dikerjakan
Dua gladi, video demo, validasi aturan dengan PSC 119/Polres/BPBD, data fasilitas terverifikasi, server berbayar,
peta jalan (OTP/panggilan balik, jarak berbasis jalan, foto bukti, CSP, antrean siaran, pemantauan).

## Masalah terbuka
Status 112 di Temanggung belum terverifikasi; cabang lama `data-fasilitas` belum dirapikan; jaringan WSL kadang
bermasalah; ganti sandi demo sebelum server publik; kebijakan retensi data belum ada; tinjauan hukum privasi belum ada.
