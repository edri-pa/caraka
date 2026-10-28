# Caraka

Prototype sistem pelaporan kejadian darurat berbasis web dengan satu pintu lapor, pilot Kabupaten Temanggung, Jawa Tengah. Dibuat sebagai karya pribadi untuk lomba inovasi daerah.

**Bukan layanan darurat resmi.** Belum dipakai atau didukung pemerintah daerah mana pun, dan tidak terhubung ke sistem instansi mana pun. Dalam keadaan darurat sungguhan hubungi PSC 119 (medis) atau 110 (polisi).

Penyusun: <nama penyusun>

## Masalah yang ditangani

Saat melihat kejadian, misalnya kecelakaan dengan korban luka, warga sering bingung menghubungi polisi atau tenaga medis lebih dulu, dan tiap instansi mencatat laporan di sistemnya sendiri. Caraka mencoba menyatukannya: warga menceritakan kejadian, sistem menyarankan instansi dan fasilitas terdekat, dan petugas yang memutuskan.

## Fitur

- **Warga (tanpa akun):** tombol SOS satu ketukan (`/sos`), formulir lengkap dengan GPS atau lokasi manual dan pertanyaan korban luka (`/lapor`), serta pengecekan status lewat nomor referensi.
- **Dispatcher dan admin:** daftar laporan dengan pembaruan real-time (WebSocket, dengan pengecekan berkala sebagai cadangan), detail dengan peta, saran instansi dan fasilitas terdekat, ubah status, penandaan laporan, dan statistik waktu penanganan.
- **Responder:** daftar tugas instansinya dan perubahan status terbatas.
- **Keamanan dan privasi:** pembatasan akses per peran, audit log, pembatasan laju, hash nomor pelapor, kebijakan privasi, dan perintah anonimisasi.

## Status

Prototype dengan tes otomatis (`sail artisan test`). Data fasilitas berasal dari sumber umum dan belum diverifikasi instansi, kontak instansi berupa penanda simulasi (`DEMO-xxxx`), dan aturan saran masih draf yang belum divalidasi praktisi. Lihat bagian Keterbatasan di `docs/ARSITEKTUR.md`.

## Teknologi

Laravel 13 (Sail), PHP 8.5, PostgreSQL 18 dengan PostGIS 3.6, Redis, Laravel Reverb, Fortify (login petugas), Vite, Leaflet 1.9.4, PHPUnit 12.

## Menjalankan sehari-hari (Windows 11, WSL2, Docker Desktop)

Buka Docker Desktop dan tunggu hijau. Di terminal Ubuntu:

    cd ~/projects/caraka
    sail up -d
    sail ps

Real-time dan tampilan membutuhkan dua proses tambahan, masing-masing di tab sendiri:

    sail artisan reverb:start --debug
    rm -f public/hot && sail npm run dev

Aplikasi: http://localhost. Pemasangan dari nol di mesin baru belum diuji ulang. Variabel `.env` yang penting: koneksi PostgreSQL, `APP_KEY`, `BROADCAST_CONNECTION=reverb`, `REVERB_*`, dan `DEMO_STAFF_PASSWORD` (hanya untuk akun demo).

## Perintah penting

| Perintah | Fungsi |
|---|---|
| `sail artisan fasilitas:impor <csv> [--simpan]` | Periksa dan impor daftar fasilitas |
| `sail artisan pengguna:buat <email> <nama> --peran=...` | Buat akun petugas |
| `sail artisan laporan:anonimkan <referensi> --yakin` | Hapus data pribadi satu laporan |
| `sail artisan keamanan:periksa` | Periksa pengaturan sebelum dibuka ke luar |
| `scripts/cadangan-db.sh`, `scripts/pulihkan-db.sh` | Cadangan dan pemulihan database |
| `sail artisan test` | Jalankan seluruh tes |

## Halaman utama

| Alamat | Untuk |
|---|---|
| `/`, `/lapor`, `/sos`, `/privasi` | Warga |
| `/login` | Petugas |
| `/dispatcher/laporan`, `/statistik` | Dispatcher dan admin |
| `/responder/tugas` | Responder |

## Dokumen

- `docs/ARSITEKTUR.md`: arsitektur, aturan, keputusan, dan keterbatasan
- `docs/DEPLOY.md`: daftar periksa saat memasang di server
- `docs/NASKAH-DEMO.md`: naskah demo dan jawaban atas pertanyaan yang mungkin muncul
- `docs/RENCANA-GLADI.md`: rencana uji coba skala kecil
- `docs/CATATAN-PROYEK.md`: catatan serah terima antar sesi kerja

## Lisensi dan atribusi

Lisensi kode belum ditentukan, jadi saat ini semua hak dilindungi. Tentukan sebelum kode dibagikan. Peta dan data fasilitas memakai data kontributor OpenStreetMap (lisensi ODbL), dengan atribusi di peta. Peta memakai server tile publik OpenStreetMap yang tidak dijamin ketersediaannya dan bukan untuk penggunaan berat.
