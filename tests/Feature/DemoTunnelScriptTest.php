<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoTunnelScriptTest extends TestCase
{
    public function test_pencarian_alamat_tunnel_melewati_alamat_api_cloudflare(): void
    {
        $skrip = file_get_contents(base_path('scripts/mulai-demo.sh'));

        $this->assertSame(1, preg_match('/url=\$\((grep -o .+)\)\s*$/m', $skrip, $cocok), 'Baris pencarian alamat tunnel tidak ditemukan.');

        $jalankan = function (string $isiLog) use ($cocok): string {
            $log = tempnam(sys_get_temp_dir(), 'tunnel');
            file_put_contents($log, $isiLog);

            $perintah = str_replace('"$STATE/tunnel.log"', escapeshellarg($log), $cocok[1]);
            $hasil = trim((string) shell_exec($perintah));

            unlink($log);

            return $hasil;
        };

        // Tunnel belum siap: hanya alamat API yang ada di log, jadi hasilnya harus kosong (skrip terus menunggu).
        $this->assertSame('', $jalankan("INF Requesting new quick Tunnel on trycloudflare.com...\nINF https://api.trycloudflare.com/tunnel\n"));

        // Tunnel siap: yang diambil adalah alamat tunnel, bukan alamat API.
        $this->assertSame(
            'https://silver-forest-demo-words.trycloudflare.com',
            $jalankan("INF https://api.trycloudflare.com/tunnel\nINF |  https://silver-forest-demo-words.trycloudflare.com  |\n")
        );
    }
}
