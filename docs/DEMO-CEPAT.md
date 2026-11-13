# Menyalakan demo kapan saja

Prasyarat sekali: Docker Desktop terpasang, proyek di `~/projects/caraka`, akun petugas sudah dibuat, fasilitas sudah diimpor, dan (untuk HP) `cloudflared` terpasang.

## Setiap kali demo
1. Buka Docker Desktop dan tunggu hijau.
2. Di terminal Ubuntu: `cd ~/projects/caraka`, lalu `scripts/mulai-demo.sh`.
   Pertama kali, atau setelah mengubah tampilan: `scripts/mulai-demo.sh --bangun`.
   Tanpa HP: `scripts/mulai-demo.sh --tanpa-tunnel`.
3. Skrip mencetak "SIAP" (atau menyebut masalahnya) dan alamat untuk HP warga.
4. Dashboard: `http://localhost`. Login sebagai dispatcher, dan responder di jendela lain.
5. Bila ingin mendemokan sendirian: `sail artisan demo:isi` (enam laporan fiktif).

## Sesudah demo
1. `scripts/akhiri-demo.sh` (menghentikan tunnel dan Reverb).
2. `sail artisan demo:isi --hapus` bila memakai data fiktif.
3. Untuk gladi yang bersih: `sail artisan gladi:ekspor`, `scripts/cadangan-db.sh`, lalu `sail artisan gladi:reset --yakin`.

## Jika ada masalah
| Gejala | Tindakan |
|---|---|
| "Docker tidak terjangkau" | Buka Docker Desktop, tunggu hijau. Bila bandel: `wsl --shutdown` di PowerShell |
| `demo:periksa` menyebut KRITIS | Ikuti perintah yang tertulis pada barisnya |
| HP menampilkan halaman rusak | `rm -f public/hot`, lalu `scripts/mulai-demo.sh --bangun` |
| Alamat tunnel tidak muncul | Lihat `~/.caraka-demo/tunnel.log`, atau jalankan ulang skrip |
| Real-time tidak aktif | Dashboard tetap memperbarui tiap 30 detik. Lihat `~/.caraka-demo/reverb.log` |

## Peringatan
- Data `DEMO` fiktif dan tidak boleh dipakai sebagai bukti uji. Statistik memberi peringatan bila ada data demo.
- Selama tunnel menyala, seluruh aplikasi bisa dibuka siapa pun yang tahu alamatnya. Matikan setelah demo.
