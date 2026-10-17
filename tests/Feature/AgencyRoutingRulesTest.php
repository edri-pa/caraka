<?php

namespace Tests\Feature;

use App\Services\AgencyRoutingSuggester;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AgencyRoutingRulesTest extends TestCase
{
    public static function kasus(): array
    {
        return [
            'medis' => ['medis', null, ['medis:utama']],
            'kecelakaan dengan korban luka' => ['kecelakaan-lalu-lintas', true, ['medis:utama', 'polisi:pendukung']],
            'kecelakaan korban tidak diketahui' => ['kecelakaan-lalu-lintas', null, ['polisi:utama', 'medis:pendukung']],
            'kecelakaan tanpa korban' => ['kecelakaan-lalu-lintas', false, ['polisi:utama']],
            'kebakaran' => ['kebakaran', null, ['damkar:utama', 'medis:pendukung']],
            'kebakaran tanpa korban' => ['kebakaran', false, ['damkar:utama']],
            'bencana dengan korban' => ['bencana-alam', true, ['bpbd:utama', 'medis:pendukung']],
            'bencana tanpa korban' => ['bencana-alam', false, ['bpbd:utama']],
            'kriminal dengan korban' => ['kriminal', true, ['polisi:utama', 'medis:pendukung']],
            'kriminal korban tidak diketahui' => ['kriminal', null, ['polisi:utama']],
            'lainnya' => ['lainnya', null, []],
            'kategori tidak dikenal' => ['ngawur', true, []],
            'tanpa kategori' => [null, true, []],
        ];
    }

    #[DataProvider('kasus')]
    public function test_aturan_saran(?string $slug, ?bool $korban, array $diharapkan): void
    {
        $hasil = array_map(
            fn ($saran) => $saran['type'].':'.$saran['role'],
            (new AgencyRoutingSuggester())->rulesFor($slug, $korban)
        );

        $this->assertSame($diharapkan, $hasil);
    }

    public function test_tidak_ada_aturan_yang_menyarankan_dua_instansi_utama(): void
    {
        $slugs = ['medis', 'kecelakaan-lalu-lintas', 'kebakaran', 'bencana-alam', 'kriminal', 'lainnya'];

        foreach ($slugs as $slug) {
            foreach ([true, false, null] as $korban) {
                $utama = array_filter(
                    (new AgencyRoutingSuggester())->rulesFor($slug, $korban),
                    fn ($saran) => $saran['role'] === 'utama'
                );

                $this->assertLessThanOrEqual(1, count($utama), "{$slug} menyarankan lebih dari satu instansi utama");
            }
        }
    }

    public function test_pencarian_dan_pertolongan_ditangani_bpbd_bukan_instansi_sar_terpisah(): void
    {
        foreach (['medis', 'kecelakaan-lalu-lintas', 'kebakaran', 'bencana-alam', 'kriminal'] as $slug) {
            foreach ([true, false, null] as $korban) {
                foreach ((new AgencyRoutingSuggester())->rulesFor($slug, $korban) as $saran) {
                    $this->assertNotSame('sar', $saran['type']);
                }
            }
        }
    }
}
