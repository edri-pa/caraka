<?php

namespace App\Console\Commands;

use App\Services\IncidentTrustScorer;
use App\Services\ReporterIdentity;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDemoIncidents extends Command
{
    private const PREFIX = 'DEMO';

    protected $signature = 'demo:isi {--ulang : Hapus data demo lama lalu isi ulang} {--hapus : Hanya hapus data demo} {--paksa : Izinkan di lingkungan production}';

    protected $description = 'Isi atau hapus laporan DEMO fiktif agar dashboard tidak kosong saat demo';

    public function handle(IncidentTrustScorer $scorer, ReporterIdentity $identity): int
    {
        if (app()->environment('production') && ! $this->option('paksa')) {
            $this->error('Ditolak: ini lingkungan production. Gunakan --paksa hanya bila Anda benar-benar yakin.');

            return self::FAILURE;
        }

        if ($this->option('hapus')) {
            $this->info($this->hapus().' laporan demo dihapus.');

            return self::SUCCESS;
        }

        $ada = DB::table('incidents')->where('reference', 'like', self::PREFIX.'%')->exists();

        if ($ada && ! $this->option('ulang')) {
            $this->error('Data demo sudah ada. Gunakan --ulang untuk menggantinya, atau --hapus untuk menghapusnya.');

            return self::FAILURE;
        }

        if ($ada) {
            $this->hapus();
        }

        $dibuat = 0;

        foreach ($this->skenario() as $s) {
            if ($this->buat($s, $scorer, $identity)) {
                $dibuat++;
            } else {
                $this->warn("Dilewati {$s['ref']}: kategori tidak ditemukan.");
            }
        }

        $this->info("{$dibuat} laporan demo dibuat (semuanya FIKTIF).");
        $this->warn('Jangan memakai data demo sebagai bukti uji. Hapus dengan: sail artisan demo:isi --hapus');

        return self::SUCCESS;
    }

    private function hapus(): int
    {
        $ids = DB::table('incidents')->where('reference', 'like', self::PREFIX.'%')->pluck('id');

        DB::transaction(function () use ($ids) {
            DB::table('incident_logs')->whereIn('incident_id', $ids)->delete();
            DB::table('incident_agencies')->whereIn('incident_id', $ids)->delete();
            DB::table('incidents')->whereIn('id', $ids)->delete();
        });

        return $ids->count();
    }

    /** @return array<int, array<string, mixed>> */
    private function skenario(): array
    {
        return [
            [
                'ref' => 'DEMO000001', 'slugs' => ['kecelakaan-lalu-lintas'], 'korban' => true,
                'lat' => -7.318, 'lng' => 110.178, 'akurasi' => 18,
                'desc' => 'Kecelakaan motor di perempatan, satu orang terluka.', 'phone' => '08000000001',
                'status' => 'selesai', 'menit' => 180,
                'tahap' => ['diverifikasi' => 40, 'ditugaskan' => 100, 'ditangani' => 420, 'selesai' => 1500],
                'tutup' => 1500, 'instansi' => [['medis', 'utama'], ['polisi', 'pendukung']],
            ],
            [
                'ref' => 'DEMO000002', 'slugs' => ['kebakaran'], 'korban' => false,
                'lat' => -7.311, 'lng' => 110.171, 'akurasi' => 25,
                'desc' => 'Kebakaran dapur rumah warga, api mulai padam.', 'phone' => '08000000002',
                'status' => 'ditangani', 'menit' => 95,
                'tahap' => ['diverifikasi' => 60, 'ditugaskan' => 150, 'ditangani' => 600],
                'instansi' => [['damkar', 'utama']],
            ],
            [
                'ref' => 'DEMO000003', 'slugs' => ['longsor', 'bencana-alam'], 'korban' => null,
                'lat' => -7.286, 'lng' => 110.152, 'akurasi' => 35,
                'desc' => 'Tanah longsor menutup sebagian jalan desa.', 'phone' => null,
                'status' => 'ditugaskan', 'menit' => 50,
                'tahap' => ['diverifikasi' => 120, 'ditugaskan' => 300],
                'instansi' => [['bpbd', 'utama'], ['medis', 'pendukung']],
            ],
            [
                'ref' => 'DEMO000004', 'slugs' => ['lainnya'], 'korban' => null, 'sos' => true,
                'lat' => -7.322, 'lng' => 110.183, 'akurasi' => 22,
                'desc' => 'LAPORAN SOS (satu ketukan)', 'phone' => null,
                'status' => 'baru', 'menit' => 4, 'tahap' => [], 'instansi' => [],
            ],
            [
                'ref' => 'DEMO000005', 'slugs' => ['medis'], 'korban' => true, 'manual' => true,
                'landmark' => 'Dekat balai desa', 'lat' => null, 'lng' => null, 'akurasi' => null,
                'desc' => 'Warga pingsan di pasar.', 'phone' => '08000000003',
                'status' => 'diverifikasi', 'menit' => 25, 'tahap' => ['diverifikasi' => 90], 'instansi' => [],
            ],
            [
                'ref' => 'DEMO000006', 'slugs' => ['kriminal'], 'korban' => false,
                'lat' => -7.305, 'lng' => 110.165, 'akurasi' => 40,
                'desc' => 'Laporan palsu yang diperankan untuk simulasi.', 'phone' => '08000000004',
                'status' => 'tidak_terbukti', 'menit' => 70, 'tahap' => ['diverifikasi' => 80],
                'tutup' => 420, 'instansi' => [],
                'flag' => ['sengaja_palsu', 'Simulasi: lokasi tidak ada dan nomor menolak dihubungi.', 360],
            ],
        ];
    }

    private function buat(array $s, IncidentTrustScorer $scorer, ReporterIdentity $identity): bool
    {
        $kategori = null;

        foreach ($s['slugs'] as $slug) {
            $kategori = DB::table('categories')->where('slug', $slug)->first(['id', 'base_priority']);

            if ($kategori !== null) {
                break;
            }
        }

        if ($kategori === null) {
            return false;
        }

        $hasGps = $s['lat'] !== null && $s['lng'] !== null;
        $sos = ! empty($s['sos']);
        $trust = $scorer->score($hasGps, $hasGps ? (float) $s['akurasi'] : null, $s['phone'] !== null, 0);
        $trust = $sos ? max(10, $trust - 20) : $trust;
        $prioritas = $sos ? 5 : ($s['korban'] === true ? min(5, (int) $kategori->base_priority + 1) : (int) $kategori->base_priority);
        $lapor = now()->subMinutes($s['menit']);
        $waktu = fn (int $detik) => (clone $lapor)->addSeconds($detik)->format('Y-m-d H:i:s');
        $distrik = ! empty($s['manual']) ? DB::table('districts')->orderBy('id')->value('id') : null;

        DB::transaction(function () use ($s, $kategori, $hasGps, $trust, $prioritas, $lapor, $waktu, $distrik, $identity) {
            $id = DB::table('incidents')->insertGetId([
                'reference' => $s['ref'],
                'category_id' => $kategori->id,
                'district_id' => $distrik,
                'description' => $s['desc'],
                'landmark_text' => $s['landmark'] ?? null,
                'reporter_phone' => $s['phone'],
                'reporter_hash' => $identity->hash($s['phone']),
                'location' => $hasGps
                    ? DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)::geography', $s['lng'], $s['lat']))
                    : null,
                'location_accuracy_m' => $hasGps ? $s['akurasi'] : null,
                'location_source' => $hasGps ? 'gps' : 'manual',
                'has_casualty' => $s['korban'],
                'status' => $s['status'],
                'trust_score' => $trust,
                'priority_score' => $prioritas,
                'flag_reason' => isset($s['flag']) ? $s['flag'][0] : null,
                'flag_note' => isset($s['flag']) ? $s['flag'][1] : null,
                'flagged_at' => isset($s['flag']) ? $waktu($s['flag'][2]) : null,
                'closed_at' => isset($s['tutup']) ? $waktu($s['tutup']) : null,
                'reported_at' => $lapor->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('incident_logs')->insert([
                'incident_id' => $id, 'user_id' => null, 'action' => 'dilaporkan',
                'from_status' => null, 'to_status' => 'baru',
                'note' => 'Data DEMO (fiktif).', 'created_at' => $lapor->format('Y-m-d H:i:s'),
            ]);

            foreach ($s['tahap'] as $status => $detik) {
                DB::table('incident_logs')->insert([
                    'incident_id' => $id, 'user_id' => null, 'action' => 'ubah_status',
                    'from_status' => null, 'to_status' => $status, 'note' => null, 'created_at' => $waktu($detik),
                ]);
            }

            if (isset($s['flag'])) {
                DB::table('incident_logs')->insert([
                    'incident_id' => $id, 'user_id' => null, 'action' => 'penandaan',
                    'from_status' => $s['status'], 'to_status' => $s['status'],
                    'note' => 'Ditandai: Sengaja palsu - '.$s['flag'][1], 'created_at' => $waktu($s['flag'][2]),
                ]);
            }

            foreach ($s['instansi'] as [$jenis, $peran]) {
                $agensi = DB::table('agencies')->where('type', $jenis)->orderBy('id')->value('id');

                if ($agensi === null) {
                    continue;
                }

                DB::table('incident_agencies')->insert([
                    'incident_id' => $id, 'agency_id' => $agensi, 'role' => $peran,
                    'assigned_by' => null, 'assigned_at' => $waktu(120),
                ]);

                if ($peran === 'utama') {
                    DB::table('incidents')->where('id', $id)->update(['assigned_agency_id' => $agensi]);
                }
            }
        });

        return true;
    }
}
