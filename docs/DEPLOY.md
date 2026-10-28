# Daftar periksa deploy

Belum pernah diuji pada server nyata. Daftar ini dibuat dari pengalaman pengembangan dan perlu disesuaikan.

## Sebelum menyewa
- Spesifikasi minimal 4 GB RAM, Ubuntu LTS, wilayah terdekat (Indonesia atau Singapura), dan domain.
- Pastikan versi PostgreSQL dan PostGIS setara dengan pengembangan (PostgreSQL 18, PostGIS 3.6) atau uji migrasi pada versi lain.

## Server
1. Pengguna non-root, login SSH key, matikan login sandi, firewall hanya port 22, 80, dan 443 (PostgreSQL dan Redis hanya mendengarkan localhost).
2. Pasang Nginx, PHP-FPM, PostgreSQL dengan PostGIS, Redis, Composer, Node.js, dan Supervisor.
3. Sertifikat HTTPS (Let's Encrypt) dan WSS untuk Reverb di port 443 lewat Nginx.
4. Reverb dan antrean berjalan di bawah Supervisor.

## Aplikasi
1. `.env` produksi: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` berawalan https, `SESSION_SECURE_COOKIE=true`, kata sandi database kuat, dan `BROADCAST_CONNECTION=reverb`.
2. `composer install --no-dev`, `npm ci`, `npm run build`, dan `php artisan migrate --force`.
3. Jalankan seeder data acuan saja. **Jangan menjalankan UserSeeder**; buat akun dengan `pengguna:buat` dan kata sandi baru yang berbeda.
4. Batasi `trustProxies` di `bootstrap/app.php` ke alamat proxy yang sebenarnya (bukan `*`).
5. Jangan memakai `route:cache` (sebagian rute memakai closure).
6. Hapus `DEMO_STAFF_PASSWORD` dari lingkungan dan data uji (`region = UJI`).
7. Jalankan `php artisan keamanan:periksa`. Tidak boleh ada masalah kritis.

## Operasional
- Cadangan database terjadwal dengan `scripts/cadangan-db.sh`, disalin ke luar server, dan **uji pemulihannya**.
- Putar log, dan pantau ruang disk dan ketersediaan.
- Kebijakan retensi data ditetapkan dan permintaan penghapusan dilayani dengan `laporan:anonimkan`.
- Kontak nyata hanya dimasukkan setelah diverifikasi dan diizinkan instansinya.

## Uji pascadeploy
HTTPS aktif, GPS di HP berfungsi, laporan masuk ke dashboard secara real-time, login petugas dan pembatasan peran bekerja, peta tampil dengan atribusi, dan cadangan dapat dipulihkan.
