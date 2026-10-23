<?php

namespace Tests\Feature;

use App\Events\IncidentUpdated;
use App\Models\User;
use App\Services\ReporterIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SosTest extends TestCase
{
    use RefreshDatabase;

    private const GPS = ['latitude' => -7.32, 'longitude' => 110.18, 'accuracy' => 20];

    private function laporanTerakhir(): object
    {
        return DB::table('incidents')->orderByDesc('id')->first();
    }

    private function sesiPemilik(string $referensi, ?int $detik = null): array
    {
        return ['sos_reference' => $referensi, 'sos_at' => $detik ?? now()->timestamp];
    }

    public function test_halaman_sos_tampil_untuk_tamu(): void
    {
        $this->get('/sos')
            ->assertOk()
            ->assertSee('SOS')
            ->assertSee('tidak menggantikan layanan darurat resmi');
    }

    public function test_sos_dengan_gps_tersimpan_dengan_prioritas_tinggi_dan_skor_rendah(): void
    {
        $this->post('/sos', self::GPS)->assertRedirect();

        $laporan = $this->laporanTerakhir();

        $this->assertSame(5, (int) $laporan->priority_score);
        $this->assertSame(60, (int) $laporan->trust_score);
        $this->assertSame('gps', $laporan->location_source);
        $this->assertNotNull($laporan->location);
        $this->assertSame('baru', $laporan->status);
        $this->assertStringContainsString('SOS', (string) $laporan->description);
        $this->assertSame('lainnya', DB::table('categories')->where('id', $laporan->category_id)->value('slug'));
        $this->assertDatabaseHas('incident_logs', [
            'incident_id' => $laporan->id,
            'action' => 'dilaporkan',
            'to_status' => 'baru',
        ]);
    }

    public function test_sos_tanpa_koordinat_tetap_diterima(): void
    {
        $this->post('/sos')->assertRedirect();

        $laporan = $this->laporanTerakhir();

        $this->assertNull($laporan->location);
        $this->assertSame('manual', $laporan->location_source);
        $this->assertSame(10, (int) $laporan->trust_score);
        $this->assertSame(5, (int) $laporan->priority_score);
    }

    public function test_koordinat_tidak_valid_diabaikan_bukan_ditolak(): void
    {
        $this->post('/sos', ['latitude' => 999, 'longitude' => 110.18, 'accuracy' => 20])->assertRedirect();

        $this->assertDatabaseCount('incidents', 1);
        $this->assertNull($this->laporanTerakhir()->location);
    }

    public function test_hanya_satu_koordinat_diabaikan(): void
    {
        $this->post('/sos', ['latitude' => -7.32])->assertRedirect();

        $this->assertNull($this->laporanTerakhir()->location);
    }

    public function test_klik_ganda_tidak_membuat_laporan_ganda(): void
    {
        $this->post('/sos', self::GPS)->assertRedirect();
        $referensi = $this->laporanTerakhir()->reference;

        $this->withSession($this->sesiPemilik($referensi))
            ->post('/sos', self::GPS)
            ->assertRedirect(route('sos.sent', $referensi));

        $this->assertDatabaseCount('incidents', 1);
    }

    public function test_halaman_terkirim_hanya_untuk_sesi_pemilik(): void
    {
        $this->post('/sos', self::GPS);
        $referensi = $this->laporanTerakhir()->reference;

        $this->withSession($this->sesiPemilik($referensi))
            ->get("/sos/terkirim/{$referensi}")
            ->assertOk()
            ->assertSee($referensi)
            ->assertSee('SOS terkirim');

        $this->flushSession();

        $this->get("/sos/terkirim/{$referensi}")->assertRedirect(route('lapor.show', $referensi));
    }

    public function test_peringatan_lokasi_tampil_bila_sos_tanpa_lokasi(): void
    {
        $this->post('/sos');
        $referensi = $this->laporanTerakhir()->reference;

        $this->withSession($this->sesiPemilik($referensi))
            ->get("/sos/terkirim/{$referensi}")
            ->assertSee('Lokasi tidak terkirim');
    }

    public function test_keterangan_tambahan_ditolak_tanpa_sesi_pemilik(): void
    {
        $this->post('/sos', self::GPS);
        $referensi = $this->laporanTerakhir()->reference;

        $this->flushSession();

        $this->post("/sos/terkirim/{$referensi}", ['has_casualty' => '1'])->assertForbidden();

        $this->assertNull(DB::table('incidents')->where('reference', $referensi)->value('has_casualty'));
    }

    public function test_keterangan_tambahan_kedaluwarsa_setelah_30_menit(): void
    {
        $this->post('/sos', self::GPS);
        $referensi = $this->laporanTerakhir()->reference;

        $this->withSession($this->sesiPemilik($referensi, now()->subMinutes(31)->timestamp))
            ->post("/sos/terkirim/{$referensi}", ['has_casualty' => '1'])
            ->assertForbidden();
    }

    public function test_keterangan_tambahan_menyimpan_korban_patokan_dan_nomor(): void
    {
        $this->post('/sos', self::GPS);
        $referensi = $this->laporanTerakhir()->reference;

        $this->withSession($this->sesiPemilik($referensi))
            ->post("/sos/terkirim/{$referensi}", [
                'has_casualty' => '1',
                'landmark_text' => 'Depan balai desa',
                'reporter_phone' => '0812-3456-789',
            ])
            ->assertRedirect(route('sos.sent', $referensi));

        $laporan = DB::table('incidents')->where('reference', $referensi)->first();

        $this->assertTrue((bool) $laporan->has_casualty);
        $this->assertSame('Depan balai desa', $laporan->landmark_text);
        $this->assertSame('08123456789', $laporan->reporter_phone);
        $this->assertSame(app(ReporterIdentity::class)->hash('08123456789'), $laporan->reporter_hash);
        $this->assertSame(70, (int) $laporan->trust_score);
        $this->assertDatabaseHas('incident_logs', ['incident_id' => $laporan->id, 'action' => 'sos_tambahan']);
    }

    public function test_keterangan_tambahan_divalidasi(): void
    {
        $this->post('/sos', self::GPS);
        $referensi = $this->laporanTerakhir()->reference;

        $this->withSession($this->sesiPemilik($referensi))
            ->post("/sos/terkirim/{$referensi}", ['reporter_phone' => 'abc'])
            ->assertSessionHasErrors('reporter_phone');

        $this->withSession($this->sesiPemilik($referensi))
            ->post("/sos/terkirim/{$referensi}", ['has_casualty' => 'x'])
            ->assertSessionHasErrors('has_casualty');
    }

    public function test_sos_menyiarkan_event_baru(): void
    {
        Event::fake([IncidentUpdated::class]);

        $this->post('/sos', self::GPS)->assertRedirect();

        $id = (int) $this->laporanTerakhir()->id;

        Event::assertDispatched(IncidentUpdated::class, fn ($e) => $e->type === 'baru' && $e->incidentId === $id);
    }

    public function test_gagal_siaran_tidak_menggagalkan_sos(): void
    {
        Event::listen(IncidentUpdated::class, function () {
            throw new \RuntimeException('Reverb mati (simulasi)');
        });

        $this->post('/sos', self::GPS)->assertRedirect();

        $this->assertDatabaseCount('incidents', 1);
    }

    public function test_pembatas_laju_sos(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/sos');
        }

        $this->post('/sos')->assertStatus(429);
    }

    public function test_sos_tampil_paling_atas_di_daftar_dispatcher(): void
    {
        $kategori = DB::table('categories')->insertGetId([
            'name' => 'Kategori Uji',
            'slug' => 'uji',
            'base_priority' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('incidents')->insert([
            'category_id' => $kategori,
            'location' => null,
            'location_source' => 'manual',
            'reference' => 'AAAAAAAAAA',
            'status' => 'baru',
            'trust_score' => 50,
            'priority_score' => 3,
            'reported_at' => now()->subMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post('/sos', self::GPS);
        $referensi = $this->laporanTerakhir()->reference;

        $this->flushSession();
        $this->actingAs(User::factory()->create(['role' => 'dispatcher']));

        $this->get('/dispatcher/laporan')->assertSeeInOrder([$referensi, 'AAAAAAAAAA']);
    }

    public function test_kategori_lainnya_dibuat_bila_belum_ada(): void
    {
        $this->assertDatabaseCount('categories', 0);

        $this->post('/sos', self::GPS)->assertRedirect();

        $this->assertDatabaseHas('categories', ['slug' => 'lainnya']);
    }

    public function test_status_publik_tidak_menampilkan_data_pribadi_hasil_keterangan_tambahan(): void
    {
        $this->post('/sos', self::GPS);
        $referensi = $this->laporanTerakhir()->reference;

        $this->withSession($this->sesiPemilik($referensi))
            ->post("/sos/terkirim/{$referensi}", [
                'landmark_text' => 'Rahasia patokan',
                'reporter_phone' => '08123456789',
            ]);

        $this->flushSession();

        $this->get("/lapor/{$referensi}")
            ->assertOk()
            ->assertDontSee('08123456789')
            ->assertDontSee('Rahasia patokan');
    }

    public function test_halaman_lapor_menautkan_ke_sos(): void
    {
        $this->get('/lapor')->assertOk()->assertSee('/sos', false);
    }
}
