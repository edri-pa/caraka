<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DashboardLiveTest extends TestCase
{
    use RefreshDatabase;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoryId = DB::table('categories')->insertGetId([
            'name' => 'Kategori Uji',
            'slug' => 'uji',
            'base_priority' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buatLaporan(): int
    {
        return DB::table('incidents')->insertGetId([
            'category_id' => $this->categoryId,
            'location' => null,
            'location_source' => 'manual',
            'reference' => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
            'status' => 'ditugaskan',
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function masukSebagai(string $role, ?int $agencyId = null): User
    {
        $user = User::factory()->create(['role' => $role, 'agency_id' => $agencyId]);
        $this->actingAs($user);

        return $user;
    }

    public function test_ringkasan_mengarahkan_tamu_ke_login(): void
    {
        $this->get('/dispatcher/laporan/ringkasan')->assertRedirect(route('login'));
    }

    public function test_ringkasan_ditolak_untuk_responder(): void
    {
        $this->masukSebagai('responder');

        $this->get('/dispatcher/laporan/ringkasan')->assertForbidden();
    }

    public function test_ringkasan_untuk_dispatcher_berupa_json_tanpa_data_pribadi(): void
    {
        $this->masukSebagai('dispatcher');
        DB::table('incidents')->where('id', $this->buatLaporan())->update(['reporter_phone' => '08000000009']);

        $respons = $this->getJson('/dispatcher/laporan/ringkasan');

        $respons->assertOk()->assertJsonStructure(['signature']);
        $this->assertStringNotContainsString('08000000009', $respons->getContent());
    }

    public function test_tanda_ringkasan_berubah_saat_ada_aktivitas_baru(): void
    {
        $this->masukSebagai('dispatcher');

        $sebelum = $this->getJson('/dispatcher/laporan/ringkasan')->json('signature');

        $id = $this->buatLaporan();
        DB::table('incident_logs')->insert([
            'incident_id' => $id,
            'action' => 'dilaporkan',
            'to_status' => 'baru',
            'created_at' => now(),
        ]);

        $sesudah = $this->getJson('/dispatcher/laporan/ringkasan')->json('signature');

        $this->assertNotSame($sebelum, $sesudah);
    }

    public function test_halaman_daftar_memuat_pengaturan_real_time(): void
    {
        $this->masukSebagai('dispatcher');

        $this->get('/dispatcher/laporan')
            ->assertOk()
            ->assertSee('data-channel="dispatch"', false)
            ->assertSee('data-ringkasan-url', false)
            ->assertSee('csrf-token', false);
    }

    public function test_tanda_di_halaman_sama_dengan_tanda_di_endpoint(): void
    {
        $this->masukSebagai('dispatcher');
        $this->buatLaporan();

        $html = $this->get('/dispatcher/laporan')->getContent();
        preg_match('/data-signature="([^"]*)"/', $html, $cocok);

        $this->assertNotEmpty($cocok);
        $this->assertSame(
            $this->getJson('/dispatcher/laporan/ringkasan')->json('signature'),
            html_entity_decode($cocok[1])
        );
    }

    public function test_detail_untuk_dispatcher_berlangganan_channel_dispatch(): void
    {
        $id = $this->buatLaporan();
        $this->masukSebagai('dispatcher');

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSee('data-channel="dispatch"', false);
    }

    public function test_detail_untuk_responder_berlangganan_channel_instansinya(): void
    {
        $id = $this->buatLaporan();
        $agency = DB::table('agencies')->insertGetId([
            'name' => 'Instansi Uji',
            'type' => 'medis',
            'is_demo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('incident_agencies')->insert([
            'incident_id' => $id,
            'agency_id' => $agency,
            'role' => 'utama',
            'assigned_at' => now(),
        ]);
        $this->masukSebagai('responder', $agency);

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSee('data-channel="agency.'.$agency.'"', false)
            ->assertDontSee('data-channel="dispatch"', false);
    }

    public function test_rute_otorisasi_channel_terdaftar(): void
    {
        $ada = collect(Route::getRoutes()->getRoutes())
            ->contains(fn ($rute) => $rute->uri() === 'broadcasting/auth');

        $this->assertTrue($ada, 'Rute broadcasting/auth tidak terdaftar.');
    }
}
