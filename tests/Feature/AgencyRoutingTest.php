<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AgencyRoutingSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgencyRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function buatKategori(string $slug, int $prioritas = 3, ?int $parentId = null): int
    {
        return DB::table('categories')->insertGetId([
            'parent_id' => $parentId,
            'name' => 'Kategori '.$slug,
            'slug' => $slug,
            'base_priority' => $prioritas,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buatInstansi(string $type): int
    {
        return DB::table('agencies')->insertGetId([
            'name' => 'Instansi '.$type,
            'type' => $type,
            'is_demo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buatLaporan(int $categoryId, ?bool $korban = null, string $status = 'baru'): int
    {
        return DB::table('incidents')->insertGetId([
            'category_id' => $categoryId,
            'location' => null,
            'location_source' => 'manual',
            'reference' => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
            'has_casualty' => $korban,
            'status' => $status,
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function tugaskan(int $incidentId, int $agencyId, string $role): void
    {
        DB::table('incident_agencies')->insert([
            'incident_id' => $incidentId,
            'agency_id' => $agencyId,
            'role' => $role,
            'assigned_at' => now(),
        ]);

        if ($role === 'utama') {
            DB::table('incidents')->where('id', $incidentId)->update(['assigned_agency_id' => $agencyId]);
        }
    }

    private function masuk(string $role, ?int $agencyId = null): User
    {
        $user = User::factory()->create(['role' => $role, 'agency_id' => $agencyId]);
        $this->actingAs($user);

        return $user;
    }

    private function saranUntuk(int $incidentId): array
    {
        return array_map(
            fn ($saran) => [$saran['agency_id'], $saran['role']],
            app(AgencyRoutingSuggester::class)->suggest($incidentId)
        );
    }

    private function payloadLaporan(int $categoryId, array $extra = []): array
    {
        return array_merge([
            'category_id' => $categoryId,
            'latitude' => -7.32,
            'longitude' => 110.18,
            'accuracy' => 20,
        ], $extra);
    }

    public function test_saran_menunjuk_instansi_sesuai_jenis_dan_korban(): void
    {
        $medis = $this->buatInstansi('medis');
        $polisi = $this->buatInstansi('polisi');
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas', 4), true);

        $this->assertSame([[$medis, 'utama'], [$polisi, 'pendukung']], $this->saranUntuk($id));
    }

    public function test_saran_melewati_instansi_yang_sudah_ditugaskan(): void
    {
        $medis = $this->buatInstansi('medis');
        $polisi = $this->buatInstansi('polisi');
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas', 4), true);
        $this->tugaskan($id, $polisi, 'pendukung');

        $this->assertSame([[$medis, 'utama']], $this->saranUntuk($id));
    }

    public function test_saran_tidak_menawarkan_instansi_utama_kedua(): void
    {
        $this->buatInstansi('medis');
        $polisi = $this->buatInstansi('polisi');
        $damkar = $this->buatInstansi('damkar');
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas', 4), true);
        $this->tugaskan($id, $damkar, 'utama');

        $this->assertSame([[$polisi, 'pendukung']], $this->saranUntuk($id));
    }

    public function test_sub_kategori_bencana_memakai_aturan_induknya(): void
    {
        $bpbd = $this->buatInstansi('bpbd');
        $induk = $this->buatKategori('bencana-alam', 4);
        $anak = $this->buatKategori('longsor', 4, $induk);
        $id = $this->buatLaporan($anak, false);

        $this->assertSame([[$bpbd, 'utama']], $this->saranUntuk($id));
    }

    public function test_jenis_instansi_yang_tidak_ada_dilewati(): void
    {
        $polisi = $this->buatInstansi('polisi');
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas', 4), true);

        $this->assertSame([[$polisi, 'pendukung']], $this->saranUntuk($id));
    }

    public function test_halaman_detail_dispatcher_menampilkan_saran(): void
    {
        $this->buatInstansi('medis');
        $this->buatInstansi('polisi');
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas', 4), true);
        $this->masuk('dispatcher');

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSee('Saran instansi')
            ->assertSee('Terapkan saran')
            ->assertSee('Instansi medis')
            ->assertSee('Instansi polisi');
    }

    public function test_laporan_selesai_tidak_menampilkan_saran(): void
    {
        $this->buatInstansi('medis');
        $id = $this->buatLaporan($this->buatKategori('medis', 5), true, 'selesai');
        $this->masuk('dispatcher');

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertDontSee('Saran instansi');
    }

    public function test_dispatcher_menerapkan_saran(): void
    {
        $medis = $this->buatInstansi('medis');
        $polisi = $this->buatInstansi('polisi');
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas', 4), true);
        $this->masuk('dispatcher');

        $this->post("/staf/laporan/{$id}/saran")->assertRedirect(route('staf.laporan.show', $id));

        $this->assertDatabaseHas('incident_agencies', ['incident_id' => $id, 'agency_id' => $medis, 'role' => 'utama']);
        $this->assertDatabaseHas('incident_agencies', ['incident_id' => $id, 'agency_id' => $polisi, 'role' => 'pendukung']);
        $this->assertSame($medis, (int) DB::table('incidents')->where('id', $id)->value('assigned_agency_id'));
        $this->assertSame(2, DB::table('incident_logs')->where('incident_id', $id)->where('action', 'penugasan_instansi')->count());
    }

    public function test_menerapkan_saran_tidak_menimpa_instansi_utama_yang_sudah_ada(): void
    {
        $medis = $this->buatInstansi('medis');
        $polisi = $this->buatInstansi('polisi');
        $damkar = $this->buatInstansi('damkar');
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas', 4), true);
        $this->tugaskan($id, $damkar, 'utama');
        $this->masuk('dispatcher');

        $this->post("/staf/laporan/{$id}/saran")->assertRedirect();

        $this->assertDatabaseHas('incident_agencies', ['incident_id' => $id, 'agency_id' => $damkar, 'role' => 'utama']);
        $this->assertDatabaseHas('incident_agencies', ['incident_id' => $id, 'agency_id' => $polisi, 'role' => 'pendukung']);
        $this->assertDatabaseMissing('incident_agencies', ['incident_id' => $id, 'agency_id' => $medis]);
        $this->assertSame($damkar, (int) DB::table('incidents')->where('id', $id)->value('assigned_agency_id'));
    }

    public function test_responder_tidak_boleh_menerapkan_saran(): void
    {
        $medis = $this->buatInstansi('medis');
        $this->buatInstansi('polisi');
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas', 4), true, 'ditugaskan');
        $this->tugaskan($id, $medis, 'utama');
        $this->masuk('responder', $medis);

        $this->post("/staf/laporan/{$id}/saran")->assertForbidden();
    }

    public function test_laporan_dengan_korban_luka_menaikkan_prioritas(): void
    {
        $kategori = $this->buatKategori('kecelakaan-lalu-lintas', 4);

        $this->post('/lapor', $this->payloadLaporan($kategori, ['has_casualty' => '1']))->assertRedirect();

        $laporan = DB::table('incidents')->first();

        $this->assertSame(5, (int) $laporan->priority_score);
        $this->assertTrue((bool) $laporan->has_casualty);
    }

    public function test_laporan_tanpa_korban_atau_tidak_tahu_tidak_menaikkan_prioritas(): void
    {
        $kategori = $this->buatKategori('kecelakaan-lalu-lintas', 4);

        $this->post('/lapor', $this->payloadLaporan($kategori, ['has_casualty' => '0']))->assertRedirect();
        $this->post('/lapor', $this->payloadLaporan($kategori, ['has_casualty' => '']))->assertRedirect();

        $tidakAda = DB::table('incidents')->orderBy('id')->first();
        $tidakTahu = DB::table('incidents')->orderByDesc('id')->first();

        $this->assertSame(4, (int) $tidakAda->priority_score);
        $this->assertFalse((bool) $tidakAda->has_casualty);
        $this->assertNotNull($tidakAda->has_casualty);
        $this->assertSame(4, (int) $tidakTahu->priority_score);
        $this->assertNull($tidakTahu->has_casualty);
    }

    public function test_nilai_korban_luka_tidak_valid_ditolak(): void
    {
        $kategori = $this->buatKategori('kecelakaan-lalu-lintas', 4);

        $this->post('/lapor', $this->payloadLaporan($kategori, ['has_casualty' => 'mungkin']))
            ->assertSessionHasErrors('has_casualty');

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_detail_menampilkan_status_korban_luka(): void
    {
        $id = $this->buatLaporan($this->buatKategori('kecelakaan-lalu-lintas', 4), true);
        $this->masuk('dispatcher');

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSeeInOrder(['Korban luka', 'Ya']);
    }
}
