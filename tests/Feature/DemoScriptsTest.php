<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoScriptsTest extends TestCase
{
    public function test_skrip_demo_ada_dapat_dijalankan_dan_memakai_perintah_yang_benar(): void
    {
        foreach (['scripts/mulai-demo.sh', 'scripts/akhiri-demo.sh'] as $berkas) {
            $this->assertFileExists(base_path($berkas));
            $this->assertTrue(is_executable(base_path($berkas)), $berkas.' belum dapat dijalankan (chmod +x)');
        }

        $mulai = file_get_contents(base_path('scripts/mulai-demo.sh'));

        foreach (['sail up -d', 'reverb:start', 'rm -f public/hot', 'demo:periksa', 'cloudflared', '--tanpa-tunnel'] as $kunci) {
            $this->assertStringContainsString($kunci, $mulai, $kunci);
        }

        $this->assertStringContainsString('pkill', file_get_contents(base_path('scripts/akhiri-demo.sh')));
    }
}
