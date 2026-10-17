<?php

namespace App\Console\Commands;

use App\Services\FacilityCsvImporter;
use Illuminate\Console\Command;

class ImportFacilities extends Command
{
    protected $signature = 'fasilitas:impor {file : Jalur berkas CSV} {--simpan : Simpan ke database (tanpa opsi ini hanya memeriksa)}';

    protected $description = 'Periksa dan impor daftar fasilitas dari berkas CSV';

    public function handle(FacilityCsvImporter $importer): int
    {
        $hasil = $importer->parse((string) $this->argument('file'));

        foreach ($hasil['warnings'] as $peringatan) {
            $this->warn($peringatan);
        }

        if ($hasil['errors'] !== []) {
            foreach ($hasil['errors'] as $galat) {
                $this->error($galat);
            }

            $this->error(count($hasil['errors']).' kesalahan ditemukan. Tidak ada yang disimpan.');

            return self::FAILURE;
        }

        $this->info(count($hasil['rows']).' baris valid.');

        if (! $this->option('simpan')) {
            $this->line('Mode periksa saja. Tambahkan --simpan untuk menyimpan ke database.');

            return self::SUCCESS;
        }

        $ringkasan = $importer->save($hasil['rows']);
        $this->info("Tersimpan: {$ringkasan['dibuat']} baru, {$ringkasan['diperbarui']} diperbarui.");

        return self::SUCCESS;
    }
}
