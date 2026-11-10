<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResponderPhoneAccessTest extends TestCase
{
    use RefreshDatabase;

    private const NOMOR = '08123456789';

    private int $kategoriId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kategoriId = DB::table('categories')->insertGetId([
            'name' => 'Uji', 'slug' => 'uji', 'base_priority' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function buatInstansi(string $nama = 'Instansi Uji'): int
    {
        return DB::table('agencies')->insertGetId([
            'name' => $nama, 'type' => 'medis', 'is_demo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function buatTugas(int $instansi, string $status = 'ditugaskan', ?string $nomor = self::NOMOR): int
    {
        $id = DB::table('incidents')->insertGetId([
            'category_id' => $this->kategoriId,
            'description' => 'Keterangan uji',
            'reporter_phone' => $nomor,
            'reporter_hash' => $nomor === null ? null : str_repeat('h', 64),
            'location' => null,
            'location_source' => 'manual',
            'reference' => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
            'status' => $status,
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('incident_agencies')->insert([
            'incident_id' => $id, 'agency_id' => $instansi, 'role' => 'utama', 'assigned_at' => now(),
        ]);

        return $id;
    }

    private function masukSebagaiResponder(int $instansi): User
    {
        $user = User::factory()->create(['role' => 'responder', 'agency_id' => $instansi]);
        $this->actingAs($user);

        return $user;
    }

    private function jumlahCatatanAkses(int $id): int
    {
        return DB::table('incident_logs')->where('incident_id', $id)->where('action', 'nomor_dilihat')->count();
    }

    public function test_responder_yang_ditugaskan_melihat_nomor_selama_tugas_berjalan(): void
    {
        $instansi = $this->buatInstansi();
        $this->masukSebagaiResponder($instansi);

        foreach (['ditugaskan', 'ditangani'] as $status) {
            $id = $this->buatTugas($instansi, $status);

            $this->get("/staf/laporan/{$id}")
                ->assertOk()
                ->assertSee(self::NOMOR)
                ->assertSee('akses tercatat')
                ->assertSee('menemukan lokasi');
        }
    }

    public function test_nomor_disembunyikan_dari_responder_setelah_tugas_ditutup(): void
    {
        $instansi = $this->buatInstansi();
        $this->masukSebagaiResponder($instansi);

        foreach (['selesai', 'dibatalkan', 'tidak_terbukti'] as $status) {
            $id = $this->buatTugas($instansi, $status);

            $this->get("/staf/laporan/{$id}")
                ->assertOk()
                ->assertDontSee(self::NOMOR)
                ->assertSee('tugas sudah ditutup');

            $this->assertSame(0, $this->jumlahCatatanAkses($id));
        }
    }

    public function test_akses_tercatat_sekali_per_responder_tanpa_menyimpan_nomor(): void
    {
        $instansi = $this->buatInstansi();
        $id = $this->buatTugas($instansi);

        $pertama = $this->masukSebagaiResponder($instansi);
        $this->get("/staf/laporan/{$id}")->assertOk();
        $this->get("/staf/laporan/{$id}")->assertOk();

        $this->assertSame(1, $this->jumlahCatatanAkses($id));
        $this->assertDatabaseHas('incident_logs', [
            'incident_id' => $id, 'user_id' => $pertama->id, 'action' => 'nomor_dilihat',
        ]);
        $this->assertStringNotContainsString(
            self::NOMOR,
            (string) DB::table('incident_logs')->where('action', 'nomor_dilihat')->value('note')
        );

        $this->masukSebagaiResponder($instansi);
        $this->get("/staf/laporan/{$id}")->assertOk();

        $this->assertSame(2, $this->jumlahCatatanAkses($id));
    }

    public function test_dispatcher_dan_admin_tidak_menimbulkan_catatan_akses(): void
    {
        $id = $this->buatTugas($this->buatInstansi());

        foreach (['dispatcher', 'admin'] as $peran) {
            $this->actingAs(User::factory()->create(['role' => $peran]));

            $this->get("/staf/laporan/{$id}")->assertOk()->assertSee(self::NOMOR);
        }

        $this->assertSame(0, $this->jumlahCatatanAkses($id));
    }

    public function test_responder_instansi_lain_tidak_melihat_apa_pun_dan_tidak_tercatat(): void
    {
        $id = $this->buatTugas($this->buatInstansi('Milik'));
        $this->masukSebagaiResponder($this->buatInstansi('Lain'));

        $this->get("/staf/laporan/{$id}")->assertNotFound();

        $this->assertSame(0, $this->jumlahCatatanAkses($id));
    }

    public function test_laporan_tanpa_nomor_tidak_menimbulkan_catatan(): void
    {
        $instansi = $this->buatInstansi();
        $id = $this->buatTugas($instansi, 'ditugaskan', null);
        $this->masukSebagaiResponder($instansi);

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSee('tidak tersedia');

        $this->assertSame(0, $this->jumlahCatatanAkses($id));
    }

    public function test_hash_pelapor_tidak_pernah_sampai_ke_responder(): void
    {
        $instansi = $this->buatInstansi();
        $id = $this->buatTugas($instansi);
        $this->masukSebagaiResponder($instansi);

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertDontSee(str_repeat('h', 64));
    }

    public function test_kebijakan_privasi_menjelaskan_akses_responder_dan_pencatatannya(): void
    {
        $this->get('/privasi')
            ->assertOk()
            ->assertSee('selama tugas berjalan')
            ->assertSee('Setiap akses tercatat');
    }
}
