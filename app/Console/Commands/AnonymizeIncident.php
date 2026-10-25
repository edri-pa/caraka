<?php

namespace App\Console\Commands;

use App\Services\IncidentStatistics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnonymizeIncident extends Command
{
    protected $signature = 'laporan:anonimkan {referensi} {--yakin : Jalankan (tanpa opsi ini hanya menampilkan ringkasan)}';

    protected $description = 'Hapus data pribadi pada satu laporan berdasarkan nomor referensi (laporan dan audit tetap tercatat)';

    public function handle(): int
    {
        $referensi = strtoupper(trim((string) $this->argument('referensi')));
        $laporan = DB::table('incidents')->where('reference', $referensi)->first();

        if ($laporan === null) {
            $this->error('Laporan tidak ditemukan.');

            return self::FAILURE;
        }

        $this->line("Laporan {$referensi}: status {$laporan->status}.");
        $this->line('Dihapus: nomor HP, hash nomor, keterangan, patokan lokasi, koordinat, akurasi, catatan penandaan, foto.');

        if (! $this->option('yakin')) {
            $this->warn('Belum ada yang diubah. Tambahkan --yakin untuk menjalankan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($laporan) {
            $sos = str_starts_with((string) $laporan->description, IncidentStatistics::SOS_PREFIX);

            DB::table('incidents')->where('id', $laporan->id)->update([
                'reporter_phone' => null,
                'reporter_hash' => null,
                'description' => $sos ? $laporan->description : '[dihapus atas permintaan]',
                'landmark_text' => null,
                'location' => null,
                'location_accuracy_m' => null,
                'location_source' => 'manual',
                'flag_note' => null,
                'photo_path' => null,
                'updated_at' => now(),
            ]);

            DB::table('incident_logs')->insert([
                'incident_id' => $laporan->id,
                'user_id' => null,
                'action' => 'anonimisasi',
                'from_status' => $laporan->status,
                'to_status' => $laporan->status,
                'note' => 'Data pribadi dihapus atas permintaan pelapor.',
                'created_at' => now(),
            ]);
        });

        $this->info('Data pribadi pada laporan itu sudah dihapus.');

        return self::SUCCESS;
    }
}
