<?php

namespace Tests\Feature;

use App\Services\NearestFacilityFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NearestFacilityFinderTest extends TestCase
{
    use RefreshDatabase;

    // Titik laporan uji (bujur, lintang).
    private const LNG = 110.182;
    private const LAT = -7.320;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeFacility('Puskesmas A',  'puskesmas',   110.180, -7.320);
        $this->makeFacility('RS B',         'rumah_sakit', 110.190, -7.320, ['has_igd' => true, 'is_24h' => true]);
        $this->makeFacility('Pos Damkar C', 'pos_damkar',  110.180, -7.340, ['is_24h' => true]);
        $this->makeFacility('Polsek D',     'polsek',      110.220, -7.320, ['is_24h' => true]);
        $this->makeFacility('RS E',         'rumah_sakit', 110.100, -7.250, ['has_igd' => true, 'is_24h' => true]);
        $this->makeFacility('Luar Wilayah', 'rumah_sakit', 110.500, -7.000, ['has_igd' => true, 'is_24h' => true]);
    }

    private function makeFacility(string $name, string $type, float $lng, float $lat, array $overrides = []): void
    {
        DB::table('facilities')->insert(array_merge([
            'name' => $name,
            'type' => $type,
            'region' => 'UJI',
            'location' => DB::raw(sprintf('ST_SetSRID(ST_MakePoint(%F, %F), 4326)::geography', $lng, $lat)),
            'is_24h' => false,
            'has_igd' => false,
            'is_active' => true,
            'is_demo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function finder(): NearestFacilityFinder
    {
        return app(NearestFacilityFinder::class);
    }

    public function test_hasil_diurutkan_dari_yang_terdekat(): void
    {
        $hasil = $this->finder()->find(self::LNG, self::LAT, [], 5000);

        $this->assertSame(
            ['Puskesmas A', 'RS B', 'Pos Damkar C', 'Polsek D'],
            $hasil->pluck('name')->all()
        );
    }

    public function test_jarak_dalam_meter_masuk_akal(): void
    {
        $hasil = $this->finder()->find(self::LNG, self::LAT, [], 5000);

        $this->assertEqualsWithDelta(221, (float) $hasil[0]->distance_m, 5);
        $this->assertEqualsWithDelta(883, (float) $hasil[1]->distance_m, 5);
        $this->assertEqualsWithDelta(2223, (float) $hasil[2]->distance_m, 10);
        $this->assertEqualsWithDelta(4196, (float) $hasil[3]->distance_m, 10);
    }

    public function test_radius_membatasi_hasil(): void
    {
        $dekat = $this->finder()->find(self::LNG, self::LAT, [], 5000, 10);
        $jauh = $this->finder()->find(self::LNG, self::LAT, [], 15000, 10);

        $this->assertCount(4, $dekat);
        $this->assertCount(5, $jauh);
        $this->assertNotContains('Luar Wilayah', $jauh->pluck('name')->all());
    }

    public function test_filter_jenis_fasilitas(): void
    {
        $hasil = $this->finder()->find(self::LNG, self::LAT, ['rumah_sakit'], 15000);

        $this->assertSame(['RS B', 'RS E'], $hasil->pluck('name')->all());
    }

    public function test_filter_wajib_igd(): void
    {
        $hasil = $this->finder()->find(self::LNG, self::LAT, [], 15000, 10, true);

        $this->assertSame(['RS B', 'RS E'], $hasil->pluck('name')->all());
    }

    public function test_fasilitas_nonaktif_tidak_ikut(): void
    {
        DB::table('facilities')->where('name', 'RS B')->update(['is_active' => false]);

        $hasil = $this->finder()->find(self::LNG, self::LAT, [], 5000);

        $this->assertNotContains('RS B', $hasil->pluck('name')->all());
    }

    public function test_batas_jumlah_hasil(): void
    {
        $hasil = $this->finder()->find(self::LNG, self::LAT, [], 15000, 2);

        $this->assertCount(2, $hasil);
    }
}
