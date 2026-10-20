<?php

namespace Tests\Feature;

use App\Events\IncidentUpdated;
use App\Models\User;
use App\Services\ReporterIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class IncidentFlagTest extends TestCase
{
    use RefreshDatabase;

    private const NOMOR = '08123456789';

    private int $kategoriId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kategoriId = DB::table('categories')->insertGetId([
            'name' => 'Kategori Uji',
            'slug' => 'uji',
            'base_priority' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function hash(string $nomor): string
    {
        return app(ReporterIdentity::class)->hash($nomor);
    }

    private function buatLaporan(array $ubah = []): int
    {
        return DB::table('incidents')->insertGetId(array_merge([
            'category_id' => $this->kategoriId,
            'location' => null,
            'location_source' => 'manual',
            'reference' => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
            'status' => 'baru',
            'trust_score' => 70,
            'priority_score' => 3,
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $ubah));
    }

    private function masuk(string $role, ?int $instansiId = null): User
    {
        $user = User::factory()->create(['role' => $role, 'agency_id' => $instansiId]);
        $this->actingAs($user);

        return $user;
    }

    private function laporanDenganResponder(): array
    {
        $instansi = DB::table('agencies')->insertGetId([
            'name' => 'Instansi Uji',
            'type' => 'medis',
            'is_demo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $id = $this->buatLaporan(['status' => 'ditugaskan']);
        DB::table('incident_agencies')->insert([
            'incident_id' => $id,
            'agency_id' => $instansi,
            'role' => 'utama',
            'assigned_at' => now(),
        ]);

        return [$id, $instansi];
    }

    public function test_laporan_warga_menyimpan_hash_nomor(): void
    {
        $this->post('/lapor', [
            'category_id' => $this->kategoriId,
            'latitude' => -7.32,
            'longitude' => 110.18,
            'accuracy' => 20,
            'reporter_phone' => self::NOMOR,
        ])->assertRedirect();

        $laporan = DB::table('incidents')->first();

        $this->assertSame(self::NOMOR, $laporan->reporter_phone);
        $this->assertSame($this->hash(self::NOMOR), $laporan->reporter_hash);
    }

    public function test_laporan_tanpa_nomor_tidak_punya_hash(): void
    {
        $this->post('/lapor', [
            'category_id' => $this->kategoriId,
            'latitude' => -7.32,
            'longitude' => 110.18,
            'accuracy' => 20,
        ])->assertRedirect();

        $this->assertNull(DB::table('incidents')->value('reporter_hash'));
    }

    public function test_riwayat_menghitung_laporan_dari_nomor_sama_saja(): void
    {
        $hash = $this->hash(self::NOMOR);
        $this->buatLaporan(['reporter_hash' => $hash, 'status' => 'selesai']);
        $this->buatLaporan(['reporter_hash' => $hash, 'status' => 'baru']);
        $this->buatLaporan(['reporter_hash' => $hash, 'status' => 'diverifikasi', 'flag_reason' => 'sengaja_palsu']);
        $this->buatLaporan(['reporter_hash' => $hash, 'status' => 'selesai', 'flag_reason' => 'duplikat']);
        $this->buatLaporan(['reporter_hash' => $this->hash('08999999999'), 'status' => 'selesai']);

        $riwayat = app(ReporterIdentity::class)->historyFor($hash);

        $this->assertSame(['total' => 4, 'confirmed' => 1, 'false_flags' => 1, 'other_flags' => 1], $riwayat);
    }

    public function test_dispatcher_menandai_laporan_dan_tercatat_di_log(): void
    {
        $id = $this->buatLaporan();
        $user = $this->masuk('dispatcher');

        $this->post("/staf/laporan/{$id}/tanda", ['reason' => 'duplikat', 'note' => 'Sama dengan laporan lain'])
            ->assertRedirect(route('staf.laporan.show', $id));

        $this->assertDatabaseHas('incidents', ['id' => $id, 'flag_reason' => 'duplikat', 'flagged_by' => $user->id]);

        $log = DB::table('incident_logs')->where('incident_id', $id)->where('action', 'penandaan')->first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, (int) $log->user_id);
        $this->assertStringContainsString('Duplikat', $log->note);
    }

    public function test_sengaja_palsu_wajib_memakai_catatan(): void
    {
        $id = $this->buatLaporan();
        $this->masuk('dispatcher');

        $this->post("/staf/laporan/{$id}/tanda", ['reason' => 'sengaja_palsu', 'note' => ''])
            ->assertSessionHasErrors('aksi');
        $this->post("/staf/laporan/{$id}/tanda", ['reason' => 'sengaja_palsu', 'note' => 'pendek'])
            ->assertSessionHasErrors('aksi');

        $this->assertNull(DB::table('incidents')->where('id', $id)->value('flag_reason'));

        $this->post("/staf/laporan/{$id}/tanda", ['reason' => 'sengaja_palsu', 'note' => 'Lokasi tidak ada dan nomor menolak dihubungi'])
            ->assertSessionHasNoErrors();

        $this->assertSame('sengaja_palsu', DB::table('incidents')->where('id', $id)->value('flag_reason'));
    }

    public function test_alasan_tidak_dikenal_ditolak(): void
    {
        $id = $this->buatLaporan();
        $this->masuk('dispatcher');

        $this->post("/staf/laporan/{$id}/tanda", ['reason' => 'ngawur'])->assertSessionHasErrors('reason');

        $this->assertNull(DB::table('incidents')->where('id', $id)->value('flag_reason'));
    }

    public function test_penandaan_tidak_mengubah_status_atau_skor(): void
    {
        $id = $this->buatLaporan(['status' => 'diverifikasi', 'trust_score' => 70]);
        $this->masuk('dispatcher');

        $this->post("/staf/laporan/{$id}/tanda", ['reason' => 'duplikat'])->assertRedirect();

        $laporan = DB::table('incidents')->where('id', $id)->first();

        $this->assertSame('diverifikasi', $laporan->status);
        $this->assertSame(70, (int) $laporan->trust_score);
    }

    public function test_laporan_yang_sudah_ditandai_tidak_bisa_ditandai_lagi(): void
    {
        $id = $this->buatLaporan();
        $this->masuk('dispatcher');

        $this->post("/staf/laporan/{$id}/tanda", ['reason' => 'duplikat'])->assertRedirect();
        $this->post("/staf/laporan/{$id}/tanda", ['reason' => 'tidak_ditemukan'])->assertSessionHasErrors('aksi');

        $this->assertSame('duplikat', DB::table('incidents')->where('id', $id)->value('flag_reason'));
    }

    public function test_responder_tidak_boleh_menandai(): void
    {
        [$id, $instansi] = $this->laporanDenganResponder();
        $this->masuk('responder', $instansi);

        $this->post("/staf/laporan/{$id}/tanda", ['reason' => 'duplikat'])->assertForbidden();

        $this->assertNull(DB::table('incidents')->where('id', $id)->value('flag_reason'));
    }

    public function test_hanya_admin_boleh_menghapus_penandaan(): void
    {
        $id = $this->buatLaporan(['flag_reason' => 'duplikat', 'flagged_at' => now()]);

        $this->masuk('dispatcher');
        $this->post("/staf/laporan/{$id}/tanda/hapus")->assertForbidden();
        $this->assertSame('duplikat', DB::table('incidents')->where('id', $id)->value('flag_reason'));

        $admin = $this->masuk('admin');
        $this->post("/staf/laporan/{$id}/tanda/hapus")->assertRedirect(route('staf.laporan.show', $id));

        $this->assertNull(DB::table('incidents')->where('id', $id)->value('flag_reason'));
        $this->assertDatabaseHas('incident_logs', [
            'incident_id' => $id,
            'user_id' => $admin->id,
            'action' => 'penandaan_dihapus',
        ]);
    }

    public function test_halaman_detail_menampilkan_riwayat_tanpa_membuka_nomor_lain(): void
    {
        $hash = $this->hash(self::NOMOR);
        $ini = $this->buatLaporan(['reporter_phone' => self::NOMOR, 'reporter_hash' => $hash]);
        $this->buatLaporan(['reporter_phone' => self::NOMOR, 'reporter_hash' => $hash, 'status' => 'selesai']);
        $this->buatLaporan(['reporter_phone' => '08999999999', 'reporter_hash' => $this->hash('08999999999')]);
        $this->masuk('dispatcher');

        $this->get("/staf/laporan/{$ini}")
            ->assertOk()
            ->assertSee('Laporan lain dari nomor yang sama: 1')
            ->assertSee('belum memengaruhi skor')
            ->assertDontSee('08999999999');
    }

    public function test_responder_tidak_melihat_riwayat_pelapor(): void
    {
        [$id, $instansi] = $this->laporanDenganResponder();
        DB::table('incidents')->where('id', $id)->update(['reporter_hash' => $this->hash(self::NOMOR)]);
        $this->masuk('responder', $instansi);

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertDontSee('Penandaan dan riwayat pelapor');
    }

    public function test_penandaan_menyiarkan_event(): void
    {
        Event::fake([IncidentUpdated::class]);
        $id = $this->buatLaporan();
        $this->masuk('dispatcher');

        $this->post("/staf/laporan/{$id}/tanda", ['reason' => 'duplikat'])->assertRedirect();

        Event::assertDispatched(IncidentUpdated::class, fn ($e) => $e->incidentId === $id);
    }

    public function test_laporan_tanpa_nomor_menampilkan_penjelasan(): void
    {
        $id = $this->buatLaporan();
        $this->masuk('dispatcher');

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSee('tidak ada riwayat');
    }
}
