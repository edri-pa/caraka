# Arsitektur dan keputusan

## Gambaran

Satu aplikasi Laravel (modular monolith). Halaman warga berupa HTML ringan tanpa Vite supaya tetap terbuka di sinyal lemah. Dashboard petugas memakai Blade dan JavaScript biasa, dengan Echo untuk real-time.

    Warga (web)  -->  Laravel  -->  PostgreSQL + PostGIS
    Petugas (web) <-- Laravel  -->  Redis (cache/antrean), Reverb (WebSocket)
                                    Peta: tile OpenStreetMap (publik)

## Model data

| Tabel | Isi |
|---|---|
| `incidents` | Laporan: kategori, lokasi (`geography(Point)`), status, skor, prioritas, korban luka, penandaan, hash pelapor |
| `incident_logs` | Audit log (tanpa pembaruan, tanpa cascade) |
| `incident_agencies` | Instansi utama dan pendukung per laporan |
| `categories`, `agencies`, `districts`, `facilities` | Data acuan (20 kecamatan, kategori, instansi, fasilitas dengan koordinat dan asal data) |
| `users` | Petugas: peran (`dispatcher`, `responder`, `admin`) dan instansi |

Warga tidak punya akun. Identitas pelapor hanya nomor HP opsional, disimpan sebagai teks (untuk dihubungi petugas) dan hash HMAC (untuk mengenali laporan berulang).

## Aturan (draf, belum divalidasi praktisi)

**Alur status:** baru, diverifikasi, ditugaskan, ditangani, selesai, dengan cabang tidak_terbukti dan dibatalkan. Perpindahan dibatasi di `IncidentWorkflow`; menugaskan mensyaratkan instansi utama; satu instansi utama per laporan; setiap tindakan masuk audit log.

**Saran instansi** (`AgencyRoutingSuggester`, hanya saran):

| Kejadian | Utama | Pendukung |
|---|---|---|
| Medis | Medis | - |
| Kecelakaan, ada korban luka | Medis | Polisi |
| Kecelakaan, korban tidak diketahui | Polisi | Medis |
| Kecelakaan, tanpa korban | Polisi | - |
| Kebakaran | Pemadam | Medis (kecuali pasti tanpa korban) |
| Bencana alam dan pencarian (SAR) | BPBD | Medis (kecuali pasti tanpa korban) |
| Kriminal | Polisi | Medis bila ada korban |

Menurut keterangan penyusun, SAR di Temanggung ditangani BPBD. Basarnas tidak disarankan otomatis dan dapat ditambahkan manual bila instansinya tersedia.

**Skor kepercayaan awal** (hanya saran, tidak pernah menolak laporan): dasar 30; GPS +30; akurasi sampai 50 m +20, sampai 150 m +10, lebih dari 1.000 m -10; nomor HP +10; tiga laporan atau lebih dari nomor yang sama -20. SOS dikurangi 20 lagi (minimal 10).

**Prioritas:** dari kategori; naik satu tingkat bila ada korban luka; SOS selalu 5.

**Penandaan laporan:** alasan sengaja_palsu (wajib catatan sepuluh karakter atau lebih), itikad_baik_keliru, duplikat, tidak_ditemukan. Hanya informasi: tidak mengubah status atau skor. Hanya admin yang dapat menghapus tanda. Riwayat pelapor ditampilkan ke dispatcher tetapi sengaja belum memengaruhi skor, karena nomor belum diverifikasi dan bisa dipakai pihak lain.

**Saran fasilitas:** jarak garis lurus dari PostGIS, per kelompok (RS ber-IGD, puskesmas dan klinik, pos pemadam, kantor polisi, BPBD) menurut jenis kejadian, tiga terdekat per kelompok.

**Statistik:** waktu dari laporan masuk sampai pertama kali tahap diverifikasi, ditugaskan, dan ditangani; ukuran utama adalah sampai ditangani. Memakai median; laporan yang ditandai tidak dihitung; peringatan bila sampel kurang dari sepuluh laporan.

## Keputusan penting

| Keputusan | Alasan |
|---|---|
| Sistem hanya menyarankan, petugas yang memutuskan | Kesalahan routing pada keadaan darurat berisiko nyawa |
| Laporan tidak pernah ditolak karena skor rendah | Laporan asli yang tertunda lebih mahal daripada laporan palsu yang diproses |
| Tanpa OTP pada prototype | Tanpa SMS sungguhan verifikasi hanya tiruan; penalti otomatis ditunda sampai identitas terverifikasi |
| Kegagalan siaran real-time tidak menggagalkan laporan | Reverb mati tidak boleh menutup pintu bagi pelapor |
| Pengecekan berkala 30 detik sebagai cadangan WebSocket | Dashboard tetap hidup saat koneksi putus |
| Lokasi manual tetap diterima | Pelapor tanpa GPS tetap bisa melapor |
| Akses responder hanya untuk tugas instansinya, laporan lain dijawab 404 | Tidak membocorkan keberadaan laporan |
| Data fasilitas dari internet diberi label belum diverifikasi | Kejujuran bagi dispatcher |
| Server berbayar hanya mendekati lomba | Proyek pribadi tanpa anggaran |

## Keterbatasan (harus disampaikan secara terbuka)

1. **Tidak terhubung ke sistem instansi mana pun.** Instansi di sistem adalah simulasi; integrasi nyata membutuhkan kerja sama resmi.
2. **Data fasilitas dari sumber umum dan belum diverifikasi.** Bisa usang atau keliru; IGD dan jam buka tidak terkonfirmasi.
3. **Kontak instansi simulasi.**
4. **Aturan saran dan prioritas adalah draf**, belum divalidasi PSC 119, Polres, atau BPBD.
5. **Jarak berupa garis lurus.** Di medan pegunungan jarak tempuh bisa jauh lebih panjang. Perutean jalan belum ada.
6. **Pelapor tidak terverifikasi.** Nomor HP bisa dipakai pihak lain; GPS bisa dipalsukan. Karena itu skor hanya saran dan petugas memutuskan.
7. **Butuh internet.** Telepon suara tetap kanal paling andal; Caraka pelengkap. GPS di HP butuh HTTPS. Di lereng gunung sinyal bisa lemah.
8. **Peta memakai server tile publik OpenStreetMap**, tanpa jaminan ketersediaan dan bukan untuk penggunaan berat.
9. **Statistik berasal dari simulasi** dan sampelnya kecil.
10. **Belum diuji pada server nyata, beban tinggi, atau dengan banyak pengguna.** Belum ada pemantauan.
11. **Belum ada Content-Security-Policy.** Proxy dipercaya dengan `*` untuk pengujian lewat tunnel dan harus dibatasi di server publik.
12. **Kebijakan privasi belum ditinjau ahli hukum**, dan kebijakan retensi data belum ditetapkan.
13. **Belum ada audit keamanan independen**, uji aksesibilitas, atau versi bahasa lain.
14. **Siaran real-time dikirim langsung (sinkron).** Di server perlu dialihkan ke antrean.

## Peta jalan

Verifikasi pelapor (OTP atau panggilan balik), integrasi dengan instansi, jarak dan waktu tempuh berbasis jalan, foto bukti dengan penghapusan metadata, penyedia tile sendiri, antrean untuk siaran, pemantauan, Content-Security-Policy, dan kebijakan retensi data.
