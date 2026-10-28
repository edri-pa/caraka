# Naskah demo (sekitar 5 menit)

## Persiapan
- Laptop dengan Docker, Reverb, dan Vite menyala; dispatcher dibuka di `http://localhost`.
- HP warga membuka alamat tunnel (jalankan `sail npm run build` dulu). Real-time tetap sampai ke dispatcher karena aplikasi dan Reverb berjalan di laptop yang sama.
- Data fasilitas dan akun demo sudah masuk. Cadangan: video rekaman dan tangkapan layar.
- Sebut di awal: ini prototype, data simulasi, dan bukan layanan darurat resmi.

## Alur
1. **Masalah (30 dtk).** Tunjukkan infografis nomor penting resmi pemkab: belasan nomor dengan format berbeda. Contoh: kecelakaan dengan korban luka, polisi atau medis dulu?
2. **Warga (45 dtk).** Di HP: tekan **SOS** (satu ketukan, lokasi terkirim). Lalu **/lapor** dengan pertanyaan "ada korban luka?". Tunjukkan nomor referensi dan halaman status yang tidak menampilkan data pribadi.
3. **Dispatcher (45 dtk).** Laporan muncul tanpa muat ulang. SOS berada di urutan atas (prioritas 5, skor kepercayaan rendah, perlu diperiksa).
4. **Detail (60 dtk).** Peta, saran instansi (korban luka: medis utama, polisi pendukung), saran fasilitas terdekat dengan label "belum diverifikasi", lalu **Terapkan saran**.
5. **Alur (45 dtk).** Diverifikasi, ditugaskan. Di perangkat kedua, responder membuka tugasnya dan menandai ditangani, lalu selesai.
6. **Statistik (30 dtk).** Waktu sampai ditangani pada skenario simulasi, dengan peringatan sampel kecil.
7. **Kepercayaan dan privasi (30 dtk).** Penandaan wajib catatan, banding oleh admin, riwayat berbasis hash, kebijakan privasi, dan anonimisasi.
8. **Penutup (20 dtk).** Keterbatasan dan rencana, termasuk verifikasi pelapor dan kerja sama instansi.

## Kemungkinan pertanyaan dan jawaban jujur

| Pertanyaan | Jawaban |
|---|---|
| Apa bedanya dengan 112? | Caraka tidak menggantikan nomor darurat. Ia melengkapi dengan lokasi otomatis, laporan ke beberapa instansi sekaligus, pelacakan status, dan catatan kejadian bersama. Status layanan 112 di Temanggung belum saya verifikasi. |
| Bagaimana kalau GPS dipalsukan? | GPS hanya satu sinyal dalam skor. Skor hanya saran, tidak pernah menolak laporan, dan petugas memutuskan setelah memeriksa. |
| Bagaimana dengan laporan tunggal? | Tetap diterima. Laporan asli yang tertunda lebih berbahaya daripada laporan palsu yang diperiksa. |
| Apakah terhubung ke instansi? | Belum. Instansi di sistem adalah simulasi, dan integrasi membutuhkan kerja sama resmi. |
| Datanya akurat? | Data fasilitas dari sumber umum dan belum diverifikasi, diberi label dan tanggal pengecekan. |
| Siapa yang mengoperasikan? | Belum ada. Prototype ini belum dipakai pemerintah daerah, dan keberlanjutan perlu dirancang bersama pengelola layanan. |
| Bagaimana privasi pelapor? | Nomor opsional, hanya terlihat petugas yang berhak, riwayat memakai hash, dan data dapat dianonimkan atas permintaan. |
| Berapa biaya? | Prototype ini berjalan tanpa biaya server. Perkiraan biaya operasional nyata (server, domain, verifikasi) belum saya hitung. |
| Mengapa tidak ada OTP? | OTP tanpa SMS sungguhan hanya tiruan. Verifikasi akan ditambahkan setelah ada jalur yang nyata. |
| Bagaimana jika internet putus? | Telepon suara tetap kanal utama. Dashboard memakai pengecekan berkala bila WebSocket putus. |
