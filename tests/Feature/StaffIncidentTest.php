<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffIncidentTest extends TestCase
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

    private function makeIncident(array $overrides = []): int
    {
        return DB::table('incidents')->insertGetId(array_merge([
            'category_id' => $this->categoryId,
            'location' => null,
            'location_source' => 'manual',
            'landmark_text' => 'Depan pasar',
            'reference' => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
            'status' => 'baru',
            'trust_score' => 50,
            'priority_score' => 3,
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function makeAgency(string $name = 'Instansi Uji'): int
    {
        return DB::table('agencies')->insertGetId([
            'name' => $name,
            'type' => 'medis',
            'is_demo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignAgencyDirect(int $incidentId, int $agencyId, string $role = 'utama'): void
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

    private function loginAs(string $role, ?int $agencyId = null): User
    {
        $user = User::factory()->create(['role' => $role, 'agency_id' => $agencyId]);
        $this->actingAs($user);

        return $user;
    }

    private function statusDiDb(int $id): string
    {
        return (string) DB::table('incidents')->where('id', $id)->value('status');
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $id = $this->makeIncident();

        $this->get("/staf/laporan/{$id}")->assertRedirect(route('login'));
    }

    public function test_warga_ditolak(): void
    {
        $id = $this->makeIncident();
        $this->loginAs('warga');

        $this->get("/staf/laporan/{$id}")->assertForbidden();
    }

    public function test_dispatcher_melihat_data_lengkap(): void
    {
        $id = $this->makeIncident([
            'reference' => 'ABCDE23456',
            'reporter_phone' => '08000000003',
            'description' => 'Deskripsi lengkap uji',
        ]);
        DB::table('incident_logs')->insert([
            'incident_id' => $id,
            'action' => 'dilaporkan',
            'to_status' => 'baru',
            'created_at' => now(),
        ]);
        $this->loginAs('dispatcher');

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSee('ABCDE23456')
            ->assertSee('08000000003')
            ->assertSee('Deskripsi lengkap uji')
            ->assertSee('dilaporkan');
    }

    public function test_admin_boleh_membuka_detail(): void
    {
        $id = $this->makeIncident();
        $this->loginAs('admin');

        $this->get("/staf/laporan/{$id}")->assertOk();
    }

    public function test_laporan_tidak_ada_menghasilkan_404(): void
    {
        $this->loginAs('dispatcher');

        $this->get('/staf/laporan/999999')->assertNotFound();
    }

    public function test_id_bukan_angka_menghasilkan_404(): void
    {
        $this->loginAs('dispatcher');

        $this->get('/staf/laporan/abc')->assertNotFound();
    }

    public function test_dispatcher_mengubah_status_dan_tercatat_di_log(): void
    {
        $id = $this->makeIncident();
        $user = $this->loginAs('dispatcher');

        $this->post("/staf/laporan/{$id}/status", ['to' => 'diverifikasi', 'note' => 'Sudah dicek'])
            ->assertRedirect(route('staf.laporan.show', $id));

        $this->assertSame('diverifikasi', $this->statusDiDb($id));
        $this->assertDatabaseHas('incident_logs', [
            'incident_id' => $id,
            'user_id' => $user->id,
            'action' => 'ubah_status',
            'to_status' => 'diverifikasi',
        ]);
    }

    public function test_perpindahan_terlarang_menampilkan_galat_dan_tidak_mengubah_data(): void
    {
        $id = $this->makeIncident();
        $this->loginAs('dispatcher');

        $this->post("/staf/laporan/{$id}/status", ['to' => 'selesai'])
            ->assertSessionHasErrors('aksi');

        $this->assertSame('baru', $this->statusDiDb($id));
    }

    public function test_status_tidak_dikenal_ditolak_validasi(): void
    {
        $id = $this->makeIncident();
        $this->loginAs('dispatcher');

        $this->post("/staf/laporan/{$id}/status", ['to' => 'ngawur'])
            ->assertSessionHasErrors('to');

        $this->assertSame('baru', $this->statusDiDb($id));
    }

    public function test_dispatcher_hanya_melihat_pilihan_status_yang_sah(): void
    {
        $id = $this->makeIncident();
        $this->loginAs('dispatcher');

        $this->get("/staf/laporan/{$id}")
            ->assertSee('value="diverifikasi"', false)
            ->assertDontSee('value="selesai"', false);
    }

    public function test_laporan_selesai_tidak_punya_formulir_ubah_status(): void
    {
        $id = $this->makeIncident(['status' => 'selesai']);
        $this->loginAs('dispatcher');

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertDontSee('Ubah status')
            ->assertDontSee('Tetapkan instansi');
    }

    public function test_dispatcher_menetapkan_instansi_utama(): void
    {
        $id = $this->makeIncident();
        $agency = $this->makeAgency();
        $this->loginAs('dispatcher');

        $this->post("/staf/laporan/{$id}/instansi", ['agency_id' => $agency, 'role' => 'utama'])
            ->assertRedirect(route('staf.laporan.show', $id));

        $this->assertDatabaseHas('incident_agencies', [
            'incident_id' => $id,
            'agency_id' => $agency,
            'role' => 'utama',
        ]);
        $this->assertSame($agency, (int) DB::table('incidents')->where('id', $id)->value('assigned_agency_id'));
    }

    public function test_penetapan_instansi_divalidasi(): void
    {
        $id = $this->makeIncident();
        $agency = $this->makeAgency();
        $this->loginAs('dispatcher');

        $this->post("/staf/laporan/{$id}/instansi", ['agency_id' => 999999, 'role' => 'utama'])
            ->assertSessionHasErrors('agency_id');

        $this->post("/staf/laporan/{$id}/instansi", ['agency_id' => $agency, 'role' => 'bos'])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseCount('incident_agencies', 0);
    }

    public function test_responder_tanpa_penugasan_mendapat_404(): void
    {
        $id = $this->makeIncident();
        $this->loginAs('responder', $this->makeAgency());

        $this->get("/staf/laporan/{$id}")->assertNotFound();
    }

    public function test_responder_instansi_lain_tidak_bisa_melihat_atau_mengubah(): void
    {
        $id = $this->makeIncident(['status' => 'ditugaskan']);
        $milik = $this->makeAgency('Milik');
        $lain = $this->makeAgency('Lain');
        $this->assignAgencyDirect($id, $milik);
        $this->loginAs('responder', $lain);

        $this->get("/staf/laporan/{$id}")->assertNotFound();
        $this->post("/staf/laporan/{$id}/status", ['to' => 'ditangani'])->assertNotFound();

        $this->assertSame('ditugaskan', $this->statusDiDb($id));
    }

    public function test_responder_yang_ditugaskan_melihat_detail_dan_pilihan_terbatas(): void
    {
        $id = $this->makeIncident(['status' => 'ditugaskan']);
        $agency = $this->makeAgency();
        $this->assignAgencyDirect($id, $agency);
        $this->loginAs('responder', $agency);

        $this->get("/staf/laporan/{$id}")
            ->assertOk()
            ->assertSee('value="ditangani"', false)
            ->assertDontSee('value="diverifikasi"', false)
            ->assertDontSee('Tetapkan instansi');
    }

    public function test_responder_menandai_ditangani_lalu_selesai(): void
    {
        $id = $this->makeIncident(['status' => 'ditugaskan']);
        $agency = $this->makeAgency();
        $this->assignAgencyDirect($id, $agency);
        $this->loginAs('responder', $agency);

        $this->post("/staf/laporan/{$id}/status", ['to' => 'ditangani'])->assertRedirect();
        $this->assertSame('ditangani', $this->statusDiDb($id));

        $this->post("/staf/laporan/{$id}/status", ['to' => 'selesai'])->assertRedirect();
        $this->assertSame('selesai', $this->statusDiDb($id));
    }

    public function test_responder_tidak_boleh_mengubah_ke_status_lain(): void
    {
        $id = $this->makeIncident(['status' => 'ditugaskan']);
        $agency = $this->makeAgency();
        $this->assignAgencyDirect($id, $agency);
        $this->loginAs('responder', $agency);

        $this->post("/staf/laporan/{$id}/status", ['to' => 'diverifikasi'])->assertForbidden();

        $this->assertSame('ditugaskan', $this->statusDiDb($id));
    }

    public function test_responder_tidak_boleh_menetapkan_instansi(): void
    {
        $id = $this->makeIncident(['status' => 'ditugaskan']);
        $agency = $this->makeAgency('Milik');
        $lain = $this->makeAgency('Lain');
        $this->assignAgencyDirect($id, $agency);
        $this->loginAs('responder', $agency);

        $this->post("/staf/laporan/{$id}/instansi", ['agency_id' => $lain, 'role' => 'utama'])
            ->assertForbidden();

        $this->assertDatabaseMissing('incident_agencies', ['agency_id' => $lain]);
    }

    public function test_isian_publik_di_escape_pada_halaman_detail(): void
    {
        $id = $this->makeIncident(['description' => '<script>alert(1)</script>']);
        $this->loginAs('dispatcher');

        $this->get("/staf/laporan/{$id}")
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_daftar_dispatcher_memuat_tautan_ke_detail(): void
    {
        $id = $this->makeIncident();
        $this->loginAs('dispatcher');

        $this->get('/dispatcher/laporan')->assertSee("/staf/laporan/{$id}", false);
    }
}
