<?php

namespace App\Console\Commands;

use App\Services\IncidentStatistics;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportDrillData extends Command
{
    public const HEADER = [
        'no', 'kategori', 'sumber_lokasi', 'akurasi_m', 'korban_luka', 'sos', 'status_akhir', 'ditandai',
        'prioritas', 'skor_sinyal', 'waktu_lapor_wib',
        'detik_diverifikasi', 'detik_ditugaskan', 'detik_ditangani', 'detik_selesai',
    ];

    private const STAGES = ['diverifikasi', 'ditugaskan', 'ditangani', 'selesai'];

    protected $signature = 'gladi:ekspor {--berkas= : Jalur berkas keluaran (bawaan: storage/app/ekspor-gladi-<waktu>.csv)}';

    protected $description = 'Ekspor data gladi ke CSV tanpa data pribadi (untuk bukti dan analisis)';

    public function handle(): int
    {
        $berkas = (string) ($this->option('berkas') ?: storage_path('app/ekspor-gladi-'.now()->format('Ymd-His').'.csv'));
        $folder = dirname($berkas);

        if (! is_dir($folder) && ! mkdir($folder, 0755, true) && ! is_dir($folder)) {
            $this->error("Folder tujuan tidak bisa dibuat: {$folder}");

            return self::FAILURE;
        }

        $laporan = DB::table('incidents')
            ->join('categories', 'categories.id', '=', 'incidents.category_id')
            ->where('incidents.reference', 'not like', 'DEMO%')
            ->orderBy('incidents.reported_at')
            ->orderBy('incidents.id')
            ->selectRaw(
                'incidents.id, categories.name as kategori, incidents.location_source, incidents.location_accuracy_m,
                 incidents.has_casualty, (incidents.description LIKE ?) as is_sos, incidents.status,
                 incidents.flag_reason, incidents.priority_score, incidents.trust_score, incidents.reported_at',
                [IncidentStatistics::SOS_PREFIX.'%']
            )
            ->get();

        $pertama = DB::table('incident_logs')
            ->where('action', 'ubah_status')
            ->whereIn('to_status', self::STAGES)
            ->groupBy('incident_id', 'to_status')
            ->selectRaw('incident_id, to_status, min(created_at) as first_at')
            ->get()
            ->groupBy('incident_id');

        $handle = fopen($berkas, 'w');

        if ($handle === false) {
            $this->error("Berkas tidak bisa ditulis: {$berkas}");

            return self::FAILURE;
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::HEADER, ',', '"', '');

        foreach ($laporan as $urutan => $l) {
            $tahap = $pertama->get($l->id, collect())->keyBy('to_status');
            $detik = function (string $status) use ($tahap, $l) {
                $baris = $tahap->get($status);

                if ($baris === null) {
                    return '';
                }

                $selisih = Carbon::parse($baris->first_at)->getTimestamp() - Carbon::parse($l->reported_at)->getTimestamp();

                return $selisih >= 0 ? $selisih : '';
            };

            fputcsv($handle, [
                $urutan + 1,
                $l->kategori,
                $l->location_source,
                $l->location_accuracy_m === null ? '' : (int) round((float) $l->location_accuracy_m),
                $l->has_casualty === null ? 'tidak tahu' : ($this->ya($l->has_casualty) ? 'ya' : 'tidak'),
                $this->ya($l->is_sos) ? 'ya' : 'tidak',
                $l->status,
                $l->flag_reason ?? '',
                $l->priority_score,
                $l->trust_score,
                Carbon::parse($l->reported_at, config('app.timezone'))->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                $detik('diverifikasi'),
                $detik('ditugaskan'),
                $detik('ditangani'),
                $detik('selesai'),
            ], ',', '"', '');
        }

        fclose($handle);

        $this->info(count($laporan).' laporan diekspor ke: '.$berkas);
        $this->line('Berkas ini tidak memuat nomor HP, keterangan, patokan lokasi, koordinat, maupun nomor referensi.');

        return self::SUCCESS;
    }

    private function ya(mixed $nilai): bool
    {
        return in_array($nilai, [true, 't', 'true', 1, '1'], true);
    }
}
