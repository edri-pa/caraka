<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DemoCheck extends Command
{
    protected $signature = 'demo:periksa';

    protected $description = 'Periksa apakah aplikasi siap didemokan (jalankan sebelum demo)';

    private const TABEL = ['users', 'categories', 'agencies', 'districts', 'facilities', 'incidents', 'incident_logs', 'incident_agencies'];

    public function handle(): int
    {
        $ok = [];
        $catatan = [];
        $kritis = [];

        try {
            DB::connection()->getPdo();
            $ok[] = 'Database tersambung.';
        } catch (Throwable) {
            $kritis[] = 'Database tidak tersambung. Jalankan "sail up -d" dan tunggu pgsql berstatus healthy.';

            return $this->laporkan($ok, $catatan, $kritis);
        }

        $hilang = array_values(array_filter(self::TABEL, fn ($t) => ! Schema::hasTable($t)));

        if ($hilang !== []) {
            $kritis[] = 'Tabel belum ada: '.implode(', ', $hilang).'. Jalankan: sail artisan migrate';

            return $this->laporkan($ok, $catatan, $kritis);
        }

        $berkas = count(glob(database_path('migrations/*.php')) ?: []);
        $tercatat = Schema::hasTable('migrations') ? DB::table('migrations')->count() : 0;

        if ($berkas > $tercatat) {
            $kritis[] = ($berkas - $tercatat).' migrasi belum dijalankan. Jalankan: sail artisan migrate';
        } else {
            $ok[] = 'Semua migrasi sudah dijalankan.';
        }

        foreach ([
            'categories' => 'Kategori kosong. Jalankan: sail artisan db:seed',
            'agencies' => 'Instansi kosong. Jalankan: sail artisan db:seed',
            'districts' => 'Kecamatan kosong. Jalankan: sail artisan db:seed',
        ] as $tabel => $pesan) {
            if (DB::table($tabel)->count() === 0) {
                $kritis[] = $pesan;
            }
        }

        foreach (['dispatcher', 'responder', 'admin'] as $peran) {
            if (DB::table('users')->where('role', $peran)->count() === 0) {
                $kritis[] = "Belum ada akun {$peran}. Buat dengan: sail artisan pengguna:buat <email> <nama> --peran={$peran}";
            }
        }

        $adaResponder = DB::table('users')->where('role', 'responder')->exists();
        $responderBerinstansi = DB::table('users')->where('role', 'responder')->whereNotNull('agency_id')->exists();

        if ($adaResponder && ! $responderBerinstansi) {
            $kritis[] = 'Tidak ada akun responder yang terhubung ke instansi (tugasnya tidak akan muncul). Gunakan pengguna:buat --instansi=ID';
        }

        $nyata = DB::table('facilities')->where('region', '!=', 'UJI')->count();

        if ($nyata === 0) {
            $catatan[] = 'Belum ada fasilitas nyata (bukan UJI): peta dan saran fasilitas akan kosong. Impor lewat: sail artisan fasilitas:impor';
        } else {
            $ok[] = "{$nyata} fasilitas nyata tersedia.";
        }

        if (DB::table('facilities')->where('region', 'UJI')->exists()) {
            $catatan[] = 'Data fasilitas uji (region UJI) masih ada dan akan ikut tampil di saran.';
        }

        $lama = DB::table('incidents')->where('reference', 'not like', 'DEMO%')->count();
        $demo = DB::table('incidents')->where('reference', 'like', 'DEMO%')->count();

        if ($lama > 0) {
            $catatan[] = "Ada {$lama} laporan lama. Untuk gladi yang bersih: sail artisan gladi:ekspor, lalu gladi:reset --yakin.";
        }

        if ($demo > 0) {
            $ok[] = "Data demo fiktif: {$demo} laporan (hapus dengan: sail artisan demo:isi --hapus).";
        }

        $hot = is_file(public_path('hot'));
        $manifest = is_file(public_path('build/manifest.json'));

        if ($manifest && ! $hot) {
            $ok[] = 'Aset hasil build ada (bisa dibuka dari HP lewat tunnel).';
        } elseif ($manifest && $hot) {
            $catatan[] = 'public/hot ada: halaman memakai server Vite. Jika Vite tidak menyala, dashboard rusak dan HP tidak bisa membukanya. Jalankan: rm -f public/hot';
        } elseif ($hot) {
            $catatan[] = 'Mode pengembangan (Vite menyala, belum ada build). Cukup untuk laptop. Untuk tunnel: rm -f public/hot && sail npm run build';
        } else {
            $kritis[] = 'Aset belum dibangun dan Vite tidak menyala, dashboard akan galat. Jalankan: sail npm run build';
        }

        $opsi = (array) config('broadcasting.connections.reverb.options', []);
        $alamat = ($opsi['scheme'] ?? 'http').'://'.($opsi['host'] ?? 'localhost').':'.($opsi['port'] ?? 8080);

        try {
            Http::timeout(2)->get($alamat);
            $ok[] = 'Reverb (real-time) menjawab.';
        } catch (Throwable) {
            $catatan[] = 'Reverb tidak menjawab: dashboard tetap berjalan lewat pengecekan berkala 30 detik. Nyalakan: sail artisan reverb:start';
        }

        if (blank(config('app.key'))) {
            $kritis[] = 'APP_KEY kosong. Jalankan: sail artisan key:generate';
        }

        if (! is_writable(storage_path('logs'))) {
            $kritis[] = 'Folder storage/logs tidak bisa ditulis.';
        }

        if (config('app.debug')) {
            $catatan[] = 'APP_DEBUG menyala (wajar di laptop). Jangan dibuka ke publik dalam keadaan ini.';
        }

        return $this->laporkan($ok, $catatan, $kritis);
    }

    private function laporkan(array $ok, array $catatan, array $kritis): int
    {
        $this->line('Pemeriksaan kesiapan demo');

        foreach ($ok as $pesan) {
            $this->line('  [OK]      '.$pesan);
        }

        foreach ($catatan as $pesan) {
            $this->warn('  [CATATAN] '.$pesan);
        }

        foreach ($kritis as $pesan) {
            $this->error('  [KRITIS]  '.$pesan);
        }

        $this->newLine();

        if ($kritis !== []) {
            $this->error('BELUM SIAP: '.count($kritis).' masalah kritis.');

            return self::FAILURE;
        }

        if ($catatan !== []) {
            $this->warn('SIAP DENGAN CATATAN: '.count($catatan).' hal perlu diperhatikan.');

            return self::SUCCESS;
        }

        $this->info('SIAP.');

        return self::SUCCESS;
    }
}
