<?php

namespace Tests\Unit;

use App\Services\IncidentTrustScorer;
use PHPUnit\Framework\TestCase;

class IncidentTrustScorerTest extends TestCase
{
    private function scorer(): IncidentTrustScorer
    {
        return new IncidentTrustScorer();
    }

    public function test_gps_akurat_dengan_nomor_hp_mendapat_skor_awal_tertinggi(): void
    {
        $this->assertSame(90, $this->scorer()->score(true, 20.0, true));
    }

    public function test_tanpa_gps_dan_tanpa_nomor_tetap_punya_skor_dasar(): void
    {
        $this->assertSame(30, $this->scorer()->score(false, null, false));
    }

    public function test_laporan_manual_dengan_nomor_hp(): void
    {
        $this->assertSame(40, $this->scorer()->score(false, null, true));
    }

    public function test_akurasi_sedang_menurunkan_skor(): void
    {
        $this->assertSame(80, $this->scorer()->score(true, 100.0, true));
    }

    public function test_akurasi_sangat_buruk_dikurangi(): void
    {
        $this->assertSame(50, $this->scorer()->score(true, 1500.0, false));
    }

    public function test_laporan_berulang_dari_nomor_sama_menurunkan_skor(): void
    {
        $this->assertSame(70, $this->scorer()->score(true, 20.0, true, 3));
    }

    public function test_skor_selalu_antara_0_dan_100(): void
    {
        foreach ([true, false] as $gps) {
            foreach ([null, 0.0, 10.0, 100.0, 500.0, 5000.0] as $akurasi) {
                foreach ([true, false] as $hp) {
                    foreach ([0, 5] as $ulang) {
                        $skor = $this->scorer()->score($gps, $akurasi, $hp, $ulang);

                        $this->assertGreaterThanOrEqual(0, $skor);
                        $this->assertLessThanOrEqual(100, $skor);
                    }
                }
            }
        }
    }

    public function test_skor_awal_tidak_pernah_mencapai_100(): void
    {
        $maks = 0;

        foreach ([null, 0.0, 10.0, 50.0] as $akurasi) {
            $maks = max($maks, $this->scorer()->score(true, $akurasi, true, 0));
        }

        $this->assertLessThan(100, $maks);
    }
}
