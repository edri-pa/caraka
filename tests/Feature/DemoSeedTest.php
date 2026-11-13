<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    private array $berkas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $induk = DB::table('categories')->insertGetId([
            'name' => 'Bencana alam', 'slug' => 'bencana-alam', 'base_priority' => 4,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            ['Kecelakaan lalu lintas', 'kecelakaan-lalu-lintas', 4, null],
            ['Kebakaran', 'kebakaran', 5, null],
            ['Tanah longsor', 'longsor', 4, $induk],
            ['Medis', 'medis', 5, null],
            ['Kriminal', 'kriminal', 3, null],
            ['Lainnya', 'lainnya', 2, null],
        ] as [$nama, $slug, $prioritas, $parent]) {
            DB::table('categories')->insert([
                'parent_id' => $parent, 'name' => $nama, 'slug' => $slug, 'base_priority' => $prioritas,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (['medis', 'damkar', 'polisi', 'bpbd'] as $jenis) {
            DB::table('agencies')->insert([
                'name' => 'Instansi '.$jenis, 'type' => $jenis, 'is_demo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('districts')->insert(['name' => 'Kecamatan Uji', 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        foreach ($this->berkas as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    private function jumlahDemo(): int
    {
        return DB::table('incidents')->where('reference', 'like', 'DEMO%')->count();
    }

    public function test_membuat_enam_laporan_demo_dengan_status_beragam(): void
    {
        $this->artisan('demo:isi')->assertExitCode(0);

        $status = DB::table('incidents')->where('reference', 'like', 'DEMO%')->pluck('status')->sort()->values()->all();

        $this->assertSame(6, $this->jumlahDemo());
        $this->assertSame(['baru', 'ditangani', 'ditugaskan', 'diverifikasi', 'selesai', 'tidak_terbukti'], $status);
    }

    public function test_data_demo_lengkap_dengan_log_dan_penugasan(): void
    {
        $this->artisan('demo:isi')->assertExitCode(0);

        $selesai = DB::table('incidents')->where('reference', 'DEMO000001')->first();

        $this->assertSame(4, DB::table('incident_logs')->where('incident_id', $selesai->id)->where('action', 'ubah_status')->count());
        $this->assertSame(1, DB::table('incident_logs')->where('incident_id', $selesai->id)->where('action', 'dilaporkan')->count());
        $this->assertNotNull($selesai->closed_at);

        $medis = DB::table('agencies')->where('type', 'medis')->value('id');
        $this->assertDatabaseHas('incident_agencies', ['incident_id' => $selesai->id, 'agency_id' => $medis, 'role' => 'utama']);
        $this->assertSame($medis, (int) $selesai->assigned_agency_id);

        $palsu = DB::table('incidents')->where('reference', 'DEMO000006')->first();
        $this->assertSame('sengaja_palsu', $palsu->flag_reason);
        $this->assertSame('tidak_terbukti', $palsu->status);
    }

    public function test_tidak_menggandakan_tanpa_opsi_ulang(): void
    {
        $this->artisan('demo:isi')->assertExitCode(0);
        $this->artisan('demo:isi')->assertExitCode(1);

        $this->assertSame(6, $this->jumlahDemo());
    }

    public function test_opsi_ulang_menggantikan_data_demo_lama(): void
    {
        $this->artisan('demo:isi')->assertExitCode(0);
        $this->artisan('demo:isi', ['--ulang' => true])->assertExitCode(0);

        $this->assertSame(6, $this->jumlahDemo());
        $this->assertSame(6, DB::table('incident_logs')->where('action', 'dilaporkan')->count());
    }

    public function test_opsi_hapus_hanya_menghapus_data_demo(): void
    {
        $kategori = DB::table('categories')->value('id');
        DB::table('incidents')->insert([
            'category_id' => $kategori, 'reference' => 'ABCDE23456', 'location' => null,
            'location_source' => 'manual', 'status' => 'baru', 'reported_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('demo:isi')->assertExitCode(0);
        $this->artisan('demo:isi', ['--hapus' => true])->assertExitCode(0);

        $this->assertSame(0, $this->jumlahDemo());
        $this->assertDatabaseHas('incidents', ['reference' => 'ABCDE23456']);
        $this->assertSame(0, DB::table('incident_logs')->count());
    }

    public function test_menolak_di_production_tanpa_paksa(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('demo:isi')->assertExitCode(1);

        $this->assertSame(0, $this->jumlahDemo());
    }

    public function test_nomor_telepon_demo_bukan_nomor_nyata(): void
    {
        $this->artisan('demo:isi')->assertExitCode(0);

        foreach (DB::table('incidents')->where('reference', 'like', 'DEMO%')->pluck('reporter_phone') as $nomor) {
            if ($nomor !== null) {
                $this->assertMatchesRegularExpression('/^08000000\d{3}$/', $nomor);
            }
        }
    }

    public function test_halaman_status_publik_data_demo_tampil_tanpa_data_pribadi(): void
    {
        $this->artisan('demo:isi')->assertExitCode(0);

        $this->get('/lapor/DEMO000001')
            ->assertOk()
            ->assertSee('DEMO000001')
            ->assertDontSee('08000000001');
    }

    public function test_statistik_memperingatkan_bila_ada_data_demo(): void
    {
        $this->artisan('demo:isi')->assertExitCode(0);
        $this->actingAs(User::factory()->create(['role' => 'dispatcher']));

        $this->get('/statistik')
            ->assertOk()
            ->assertSee('Data demo')
            ->assertSee('6 laporan DEMO');
    }

    public function test_statistik_tanpa_data_demo_tidak_menampilkan_peringatan(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'dispatcher']));

        $this->get('/statistik')->assertOk()->assertDontSee('laporan DEMO');
    }

    public function test_ekspor_bukti_mengabaikan_data_demo(): void
    {
        $this->artisan('demo:isi')->assertExitCode(0);

        $dasar = tempnam(sys_get_temp_dir(), 'ekspor');
        $path = $dasar.'.csv';
        $this->berkas = [$dasar, $path];

        $this->artisan('gladi:ekspor', ['--berkas' => $path])->assertExitCode(0);

        $isi = trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($path)));

        $this->assertCount(1, explode("\n", $isi));
    }

    public function test_laporan_sos_demo_dihitung_sebagai_sos_dengan_prioritas_tertinggi(): void
    {
        $this->artisan('demo:isi')->assertExitCode(0);

        $sos = DB::table('incidents')->where('reference', 'DEMO000004')->first();

        $this->assertStringStartsWith('LAPORAN SOS', $sos->description);
        $this->assertSame(5, (int) $sos->priority_score);
        $this->assertSame('baru', $sos->status);
    }
}
