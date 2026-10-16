<?php

namespace Tests\Feature;

use App\Exceptions\InvalidWorkflowAction;
use App\Models\User;
use App\Services\IncidentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IncidentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoryId = DB::table('categories')->insertGetId([
            'name' => 'Kategori Uji',
            'slug' => 'uji',
            'base_priority' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function workflow(): IncidentWorkflow
    {
        return app(IncidentWorkflow::class);
    }

    private function incident(string $status = 'baru'): int
    {
        return DB::table('incidents')->insertGetId([
            'category_id' => $this->categoryId,
            'location' => null,
            'location_source' => 'manual',
            'reference' => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
            'status' => $status,
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function agency(string $name = 'Instansi Uji'): int
    {
        return DB::table('agencies')->insertGetId([
            'name' => $name,
            'type' => 'medis',
            'is_demo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function statusLaporan(int $id): string
    {
        return (string) DB::table('incidents')->where('id', $id)->value('status');
    }

    private function assertRejected(callable $aksi): void
    {
        try {
            $aksi();
        } catch (InvalidWorkflowAction) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Aksi seharusnya ditolak, tetapi berhasil.');
    }

    public function test_alur_normal_dari_baru_sampai_selesai(): void
    {
        $user = User::factory()->create(['role' => 'dispatcher']);
        $id = $this->incident();
        $agency = $this->agency();
        $w = $this->workflow();

        $w->transition($id, 'diverifikasi', $user->id);
        $w->assignAgency($id, $agency, 'utama', $user->id);
        $w->transition($id, 'ditugaskan', $user->id);
        $w->transition($id, 'ditangani', $user->id);
        $w->transition($id, 'selesai', $user->id, 'Selesai ditangani');

        $this->assertSame('selesai', $this->statusLaporan($id));
        $this->assertNotNull(DB::table('incidents')->where('id', $id)->value('closed_at'));
        $this->assertSame($agency, (int) DB::table('incidents')->where('id', $id)->value('assigned_agency_id'));
        $this->assertSame(5, DB::table('incident_logs')->where('incident_id', $id)->count());
    }

    public function test_perpindahan_terlarang_ditolak_dan_tidak_mengubah_data(): void
    {
        $id = $this->incident('baru');

        $this->assertRejected(fn () => $this->workflow()->transition($id, 'selesai', null));

        $this->assertSame('baru', $this->statusLaporan($id));
        $this->assertSame(0, DB::table('incident_logs')->where('incident_id', $id)->count());
    }

    public function test_semua_kombinasi_terlarang_ditolak(): void
    {
        $semua = array_keys(IncidentWorkflow::TRANSITIONS);
        $tujuan = array_merge($semua, ['status-ngawur']);

        foreach ($semua as $dari) {
            foreach ($tujuan as $ke) {
                if (in_array($ke, IncidentWorkflow::TRANSITIONS[$dari], true)) {
                    continue;
                }

                $id = $this->incident($dari);

                $this->assertRejected(fn () => $this->workflow()->transition($id, $ke, null));
                $this->assertSame($dari, $this->statusLaporan($id), "{$dari} berubah setelah percobaan ke {$ke}");
            }
        }
    }

    public function test_penugasan_membutuhkan_instansi_utama(): void
    {
        $id = $this->incident('diverifikasi');
        $w = $this->workflow();

        $this->assertRejected(fn () => $w->transition($id, 'ditugaskan', null));

        $w->assignAgency($id, $this->agency('Pendukung'), 'pendukung', null);
        $this->assertRejected(fn () => $w->transition($id, 'ditugaskan', null));

        $w->assignAgency($id, $this->agency('Utama'), 'utama', null);
        $w->transition($id, 'ditugaskan', null);

        $this->assertSame('ditugaskan', $this->statusLaporan($id));
    }

    public function test_closed_at_hanya_terisi_pada_status_akhir(): void
    {
        $dibatalkan = $this->incident('baru');
        $diverifikasi = $this->incident('baru');

        $this->workflow()->transition($dibatalkan, 'dibatalkan', null);
        $this->workflow()->transition($diverifikasi, 'diverifikasi', null);

        $this->assertNotNull(DB::table('incidents')->where('id', $dibatalkan)->value('closed_at'));
        $this->assertNull(DB::table('incidents')->where('id', $diverifikasi)->value('closed_at'));
    }

    public function test_instansi_utama_baru_menggantikan_yang_lama(): void
    {
        $id = $this->incident();
        $a = $this->agency('A');
        $b = $this->agency('B');

        $this->workflow()->assignAgency($id, $a, 'utama', null);
        $this->workflow()->assignAgency($id, $b, 'utama', null);

        $peran = DB::table('incident_agencies')->where('incident_id', $id)->pluck('role', 'agency_id')->all();

        $this->assertSame('pendukung', $peran[$a]);
        $this->assertSame('utama', $peran[$b]);
        $this->assertSame($b, (int) DB::table('incidents')->where('id', $id)->value('assigned_agency_id'));
    }

    public function test_instansi_utama_tidak_bisa_diturunkan_lewat_penugasan_pendukung(): void
    {
        $id = $this->incident();
        $a = $this->agency('A');

        $this->workflow()->assignAgency($id, $a, 'utama', null);

        $this->assertRejected(fn () => $this->workflow()->assignAgency($id, $a, 'pendukung', null));
    }

    public function test_penugasan_instansi_ditolak_pada_laporan_yang_sudah_ditutup(): void
    {
        $id = $this->incident('selesai');

        $this->assertRejected(fn () => $this->workflow()->assignAgency($id, $this->agency(), 'utama', null));
    }

    public function test_peran_instansi_tidak_valid_ditolak(): void
    {
        $id = $this->incident();

        $this->assertRejected(fn () => $this->workflow()->assignAgency($id, $this->agency(), 'bos', null));
    }

    public function test_instansi_tidak_dikenal_ditolak(): void
    {
        $id = $this->incident();

        $this->assertRejected(fn () => $this->workflow()->assignAgency($id, 999999, 'utama', null));
    }

    public function test_setiap_aksi_tercatat_di_log_dengan_pelaku(): void
    {
        $user = User::factory()->create(['role' => 'dispatcher']);
        $id = $this->incident();
        $agency = $this->agency('Instansi Log');

        $this->workflow()->transition($id, 'diverifikasi', $user->id, 'catatan uji');
        $this->workflow()->assignAgency($id, $agency, 'utama', $user->id);

        $ubah = DB::table('incident_logs')->where('incident_id', $id)->where('action', 'ubah_status')->first();
        $tugas = DB::table('incident_logs')->where('incident_id', $id)->where('action', 'penugasan_instansi')->first();

        $this->assertSame($user->id, (int) $ubah->user_id);
        $this->assertSame('baru', $ubah->from_status);
        $this->assertSame('diverifikasi', $ubah->to_status);
        $this->assertSame('catatan uji', $ubah->note);
        $this->assertSame($user->id, (int) $tugas->user_id);
        $this->assertStringContainsString('Instansi Log', $tugas->note);
    }

    public function test_laporan_yang_tidak_ada_ditolak(): void
    {
        $this->assertRejected(fn () => $this->workflow()->transition(999999, 'diverifikasi', null));
    }
}
