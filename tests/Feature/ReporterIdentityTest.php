<?php

namespace Tests\Feature;

use App\Services\ReporterIdentity;
use Tests\TestCase;

class ReporterIdentityTest extends TestCase
{
    private function identitas(): ReporterIdentity
    {
        return new ReporterIdentity();
    }

    public function test_nomor_dinormalkan_ke_format_62(): void
    {
        foreach (['08123456789', '+628123456789', '628123456789', '0812-3456-789', '0812 3456 789'] as $nomor) {
            $this->assertSame('628123456789', $this->identitas()->normalize($nomor), $nomor);
        }
    }

    public function test_nomor_kosong_menghasilkan_null(): void
    {
        $this->assertNull($this->identitas()->normalize(null));
        $this->assertNull($this->identitas()->normalize(''));
        $this->assertNull($this->identitas()->normalize('abc'));
        $this->assertNull($this->identitas()->hash(null));
    }

    public function test_hash_konsisten_untuk_nomor_sama_dan_berbeda_untuk_nomor_lain(): void
    {
        $this->assertSame(
            $this->identitas()->hash('08123456789'),
            $this->identitas()->hash('+628123456789')
        );
        $this->assertNotSame(
            $this->identitas()->hash('08123456789'),
            $this->identitas()->hash('08123456780')
        );
    }

    public function test_hash_bukan_nomor_asli(): void
    {
        $hash = $this->identitas()->hash('08123456789');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
        $this->assertStringNotContainsString('8123456789', $hash);
    }

    public function test_hash_bergantung_pada_kunci_aplikasi(): void
    {
        $awal = $this->identitas()->hash('08123456789');

        config(['app.key' => 'kunci-lain-untuk-uji']);

        $this->assertNotSame($awal, $this->identitas()->hash('08123456789'));
    }
}
