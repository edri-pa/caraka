<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDrillData extends Command
{
    protected $signature = 'gladi:reset {--yakin : Jalankan penghapusan (tanpa opsi ini hanya menampilkan jumlah)} {--paksa : Izinkan di lingkungan production}';

    protected $description = 'Kosongkan laporan uji sebelum gladi (data acuan, fasilitas, dan akun tetap)';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('paksa')) {
            $this->error('Ditolak: ini lingkungan production. Gunakan --paksa hanya bila Anda benar-benar yakin.');

            return self::FAILURE;
        }

        $laporan = DB::table('incidents')->count();
        $log = DB::table('incident_logs')->count();
        $penugasan = DB::table('incident_agencies')->count();

        $this->line("Laporan: {$laporan}, catatan audit: {$log}, penugasan instansi: {$penugasan}.");
        $this->line('Tidak disentuh: akun, fasilitas, instansi, kategori, dan kecamatan.');

        if (! $this->option('yakin')) {
            $this->warn('Belum ada yang dihapus. Buat cadangan (scripts/cadangan-db.sh), lalu tambahkan --yakin.');

            return self::SUCCESS;
        }

        DB::transaction(function () {
            DB::table('incident_logs')->delete();
            DB::table('incident_agencies')->delete();
            DB::table('incidents')->delete();
        });

        $this->info('Data laporan sudah dikosongkan.');

        return self::SUCCESS;
    }
}
