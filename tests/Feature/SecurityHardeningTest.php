<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function amankanKonfigurasi(): void
    {
        config([
            'app.debug' => false,
            'database.connections.pgsql.password' => 'sandi-uji-yang-panjang-1',
        ]);
    }

    public function test_header_keamanan_ada_di_halaman_publik(): void
    {
        foreach (['/', '/lapor', '/sos', '/login'] as $alamat) {
            $this->get($alamat)
                ->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    public function test_geolokasi_diizinkan_hanya_untuk_situs_sendiri(): void
    {
        $this->get('/sos')->assertHeader('Permissions-Policy', 'geolocation=(self), camera=(), microphone=()');
    }

    public function test_referrer_tidak_dihapus_agar_peta_tetap_berfungsi(): void
    {
        $this->assertNotSame('no-referrer', $this->get('/')->headers->get('Referrer-Policy'));
    }

    public function test_hsts_hanya_untuk_permintaan_aman(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/')
            ->assertHeader('Strict-Transport-Security');
    }

    public function test_halaman_galat_juga_membawa_header_keamanan(): void
    {
        $this->get('/alamat-yang-tidak-ada')
            ->assertNotFound()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_login_dibatasi_setelah_lima_percobaan_gagal(): void
    {
        $user = User::factory()->create(['role' => 'dispatcher']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'salah-terus-menerus']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'salah-terus-menerus'])
            ->assertStatus(429);
    }

    public function test_peran_tidak_bisa_diisi_lewat_pengisian_massal(): void
    {
        $user = new User();

        try {
            $user->fill(['name' => 'Uji', 'role' => 'admin', 'agency_id' => 5]);
        } catch (MassAssignmentException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertNull($user->role);
        $this->assertNull($user->agency_id);
    }

    public function test_cookie_sesi_httponly_dan_samesite(): void
    {
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);
    }

    public function test_perintah_lolos_bila_konfigurasi_aman(): void
    {
        $this->amankanKonfigurasi();

        $this->artisan('keamanan:periksa')
            ->expectsOutputToContain('Tidak ada masalah kritis')
            ->assertExitCode(0);
    }

    public function test_perintah_gagal_bila_debug_menyala(): void
    {
        $this->amankanKonfigurasi();
        config(['app.debug' => true]);

        $this->artisan('keamanan:periksa')
            ->expectsOutputToContain('APP_DEBUG')
            ->assertExitCode(1);
    }

    public function test_perintah_gagal_bila_sandi_database_lemah(): void
    {
        $this->amankanKonfigurasi();
        config(['database.connections.pgsql.password' => 'password']);

        $this->artisan('keamanan:periksa')
            ->expectsOutputToContain('Kata sandi database lemah')
            ->assertExitCode(1);
    }

    public function test_perintah_gagal_bila_masih_ada_rute_sementara(): void
    {
        $this->amankanKonfigurasi();
        Route::get('/uji-sementara', fn () => 'x');

        $this->artisan('keamanan:periksa')
            ->expectsOutputToContain('/uji-sementara')
            ->assertExitCode(1);
    }

    public function test_peringatan_tidak_menggagalkan_perintah(): void
    {
        $this->amankanKonfigurasi();
        config(['app.url' => 'http://localhost']);

        $this->artisan('keamanan:periksa')
            ->expectsOutputToContain('APP_URL')
            ->assertExitCode(0);
    }

    public function test_data_uji_dan_akun_demo_dilaporkan_sebagai_peringatan(): void
    {
        $this->amankanKonfigurasi();

        DB::table('facilities')->insert([
            'name' => 'UJI Lama', 'type' => 'puskesmas', 'region' => 'UJI',
            'location' => DB::raw('ST_SetSRID(ST_MakePoint(110.18, -7.32), 4326)::geography'),
            'is_active' => true, 'is_demo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        User::factory()->create(['email' => 'demo@caraka.test']);

        $this->artisan('keamanan:periksa')
            ->expectsOutputToContain('region UJI')
            ->expectsOutputToContain('@caraka.test')
            ->assertExitCode(0);
    }
}
