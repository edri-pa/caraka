<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class SecurityCheck extends Command
{
    protected $signature = 'keamanan:periksa';

    protected $description = 'Periksa pengaturan keamanan sebelum aplikasi dibuka ke luar';

    public function handle(): int
    {
        $kritis = [];
        $peringatan = [];

        if (blank(config('app.key'))) {
            $kritis[] = 'APP_KEY belum diisi.';
        }

        if (config('app.debug')) {
            $kritis[] = 'APP_DEBUG menyala. Matikan (false) di server publik.';
        }

        $sandiDb = (string) config('database.connections.pgsql.password');
        if (in_array($sandiDb, ['', 'password', 'secret'], true) || strlen($sandiDb) < 12) {
            $kritis[] = 'Kata sandi database lemah atau bawaan (minimal 12 karakter, bukan "password" atau "secret").';
        }

        foreach (Route::getRoutes()->getRoutes() as $rute) {
            if (str_starts_with($rute->uri(), 'uji-') || str_contains($rute->uri(), '/uji-')) {
                $kritis[] = 'Rute sementara masih ada: /'.$rute->uri();
            }
        }

        if (config('app.env') !== 'production') {
            $peringatan[] = 'APP_ENV bukan production.';
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $peringatan[] = 'APP_URL tidak memakai https.';
        }

        if (! config('session.secure')) {
            $peringatan[] = 'SESSION_SECURE_COOKIE belum true (cookie sesi bisa terkirim lewat HTTP).';
        }

        if (filled(config('caraka.demo_staff_password'))) {
            $peringatan[] = 'DEMO_STAFF_PASSWORD masih ada di lingkungan. Hapus setelah akun dibuat.';
        }

        if (DB::table('users')->where('email', 'like', '%@caraka.test')->exists()) {
            $peringatan[] = 'Akun demo (@caraka.test) masih ada. Ganti atau hapus sebelum dipublikasikan.';
        }

        if (DB::table('facilities')->where('region', 'UJI')->exists()) {
            $peringatan[] = 'Data fasilitas uji (region UJI) masih ada.';
        }

        foreach ($kritis as $pesan) {
            $this->error('KRITIS: '.$pesan);
        }

        foreach ($peringatan as $pesan) {
            $this->warn('PERINGATAN: '.$pesan);
        }

        $this->line('Periksa manual: percayai proxy hanya dari alamat yang sebenarnya (bukan "*"), port database dan Redis tidak terbuka ke internet, dan cadangan sudah diuji.');

        if ($kritis !== []) {
            $this->error(count($kritis).' masalah kritis. Jangan buka ke publik sebelum diperbaiki.');

            return self::FAILURE;
        }

        $this->info('Tidak ada masalah kritis.');

        return self::SUCCESS;
    }
}
