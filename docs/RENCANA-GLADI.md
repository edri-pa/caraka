# Rencana gladi (uji coba skala kecil)

Gladi adalah simulasi di lingkungan terbatas (rumah atau kantor), bukan penerapan pada kejadian nyata.

## Prinsip
- Semua kejadian **fiktif**. Peserta tidak diminta data pribadi asli.
- Peserta diberi tahu bahwa ini simulasi dan Caraka tidak menggantikan layanan darurat resmi. Catat persetujuan (lisan atau tertulis).
- Jangan mengganggu operasi nyata, dan jangan menghubungi layanan darurat sungguhan.
- Kontak instansi berupa penanda simulasi. Tombol telepon tidak ada.

## Peran
Warga (2 sampai 4 orang, memakai HP), dispatcher (1 orang), responder (1 sampai 2 orang), dan pengamat yang mencatat waktu dan kendala (1 orang, sebaiknya bukan penyusun).

## Persiapan
- Akun peserta: `pengguna:buat` (kata sandi berbeda tiap orang), dihapus setelah gladi.
- Laptop, tunnel, dan Reverb menyala; `sail npm run build` dijalankan bila dashboard dibuka dari perangkat lain.
- Buat cadangan, lalu jalankan `sail artisan gladi:reset --yakin` agar statistik hanya memuat gladi ini.
- Siapkan cadangan database sebelum dan sesudah.

## Skenario (contoh)
1. Kecelakaan lalu lintas dengan korban luka, lokasi GPS.
2. Kebakaran kecil, lokasi manual (GPS dimatikan).
3. Longsor, pencarian orang, dihubungkan ke BPBD.
4. SOS satu ketukan tanpa keterangan, lalu keterangan tambahan.
5. Laporan palsu (diperankan), ditandai dispatcher dengan catatan.

## Yang dicatat
| Data | Cara |
|---|---|
| Skenario, tanggal, jumlah peserta | Lembar catatan |
| Laporan terkirim dan gagal | Dashboard dan pengamatan |
| Waktu sampai diverifikasi, ditugaskan, ditangani | Halaman statistik |
| Akurasi GPS (dalam dan luar ruangan) dan waktu ambil | Pengamatan dan data laporan |
| Jenis sinyal dan operator | Catatan peserta |
| Waktu mengisi formulir dan titik yang membingungkan | Pengamat |
| Kendala teknis | Catatan, tangkapan layar |
| Tanggapan peserta | Kuesioner singkat |
| Pembanding: waktu yang sama tanpa Caraka (telepon dan pesan antarpihak) | Skenario sama, dicatat adil |

## Setelah gladi
Hapus akun dan laporan uji, simpan catatan dan tangkapan layar sebagai bukti, tulis temuan dan perbaikan, dan jangan mengklaim hasil sebagai penerapan nyata atau cakupan seluruh wilayah.
