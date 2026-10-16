<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IncidentReportTest extends TestCase
{
    use RefreshDatabase;

    private const NOMOR = '08000000001';

    private function category(int $priority = 4): int
    {
        return DB::table('categories')->insertGetId([
            'name' => 'Kategori Uji',
            'slug' => 'uji-'.uniqid(),
            'base_priority' => $priority,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function district(): int
    {
        return DB::table('districts')->insertGetId([
            'name' => 'Kecamatan Uji',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function gpsPayload(array $extra = []): array
    {
        return array_merge([
            'category_id' => $this->category(),
            'latitude' => -7.32,
            'longitude' => 110.18,
            'accuracy' => 20,
            'reporter_phone' => self::NOMOR,
        ], $extra);
    }

    public function test_halaman_form_tampil_untuk_tamu(): void
    {
        $this->get('/lapor')
            ->assertOk()
            ->assertSee('Ambil lokasi GPS')
            ->assertSee('112');
    }

    public function test_laporan_dengan_gps_tersimpan_lengkap(): void
    {
        $response = $this->post('/lapor', $this->gpsPayload(['description' => 'Uji laporan']));

        $incident = DB::table('incidents')->first();

        $this->assertNotNull($incident);
        $this->assertSame(10, strlen($incident->reference));
        $this->assertSame('baru', $incident->status);
        $this->assertSame('gps', $incident->location_source);
        $this->assertSame(90, (int) $incident->trust_score);
        $this->assertSame(4, (int) $incident->priority_score);
        $response->assertRedirect(route('lapor.show', $incident->reference));

        $titik = DB::selectOne('select ST_X(location::geometry) as lng, ST_Y(location::geometry) as lat from incidents');
        $this->assertEqualsWithDelta(110.18, (float) $titik->lng, 0.0001);
        $this->assertEqualsWithDelta(-7.32, (float) $titik->lat, 0.0001);

        $this->assertDatabaseHas('incident_logs', [
            'incident_id' => $incident->id,
            'action' => 'dilaporkan',
            'to_status' => 'baru',
        ]);
    }

    public function test_laporan_manual_tanpa_gps_tetap_diterima(): void
    {
        $this->post('/lapor', [
            'category_id' => $this->category(),
            'district_id' => $this->district(),
            'landmark_text' => 'Depan pasar',
        ])->assertRedirect();

        $incident = DB::table('incidents')->first();

        $this->assertNotNull($incident);
        $this->assertSame('manual', $incident->location_source);
        $this->assertNull($incident->location);
        $this->assertSame(30, (int) $incident->trust_score);
    }

    public function test_tanpa_gps_dan_tanpa_kecamatan_ditolak_validasi(): void
    {
        $this->post('/lapor', ['category_id' => $this->category()])
            ->assertSessionHasErrors(['district_id', 'landmark_text']);

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_koordinat_di_luar_rentang_ditolak(): void
    {
        $this->post('/lapor', $this->gpsPayload(['latitude' => 120]))
            ->assertSessionHasErrors('latitude');

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_hanya_satu_koordinat_ditolak(): void
    {
        $this->post('/lapor', [
            'category_id' => $this->category(),
            'district_id' => $this->district(),
            'landmark_text' => 'Depan pasar',
            'latitude' => -7.32,
        ])->assertSessionHasErrors('longitude');
    }

    public function test_kategori_tidak_valid_ditolak(): void
    {
        $this->post('/lapor', $this->gpsPayload(['category_id' => 999999]))
            ->assertSessionHasErrors('category_id');
    }

    public function test_nomor_hp_tidak_valid_ditolak(): void
    {
        $this->post('/lapor', $this->gpsPayload(['reporter_phone' => 'abc']))
            ->assertSessionHasErrors('reporter_phone');
    }

    public function test_laporan_berulang_dari_nomor_sama_menurunkan_skor(): void
    {
        $kategori = $this->category();

        for ($i = 0; $i < 3; $i++) {
            $this->post('/lapor', $this->gpsPayload(['category_id' => $kategori]))->assertRedirect();
        }

        $this->post('/lapor', $this->gpsPayload(['category_id' => $kategori]))->assertRedirect();

        $skor = DB::table('incidents')->orderBy('id')->pluck('trust_score')->map(fn ($s) => (int) $s)->all();

        $this->assertSame([90, 90, 90, 70], $skor);
    }

    public function test_halaman_status_tidak_membocorkan_data_pribadi(): void
    {
        $this->post('/lapor', $this->gpsPayload(['description' => 'rahasia-deskripsi']));

        $reference = DB::table('incidents')->value('reference');

        $this->get('/lapor/'.$reference)
            ->assertOk()
            ->assertSee($reference)
            ->assertSee('Diterima')
            ->assertDontSee(self::NOMOR)
            ->assertDontSee('rahasia-deskripsi')
            ->assertDontSee('110.18');
    }

    public function test_referensi_tidak_dikenal_atau_salah_format_menghasilkan_404(): void
    {
        $this->get('/lapor/ABCDE23456')->assertNotFound();
        $this->get('/lapor/abc')->assertNotFound();
    }

    public function test_pengiriman_dibatasi_lajunya(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/lapor', []);
        }

        $this->post('/lapor', [])->assertStatus(429);
    }
}
