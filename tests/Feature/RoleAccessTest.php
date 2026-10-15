<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserHasRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', EnsureUserHasRole::class.':dispatcher,admin'])
            ->get('/_tes/dispatcher', fn () => 'ok');
        Route::middleware(['web', 'auth', EnsureUserHasRole::class.':responder,admin'])
            ->get('/_tes/responder', fn () => 'ok');
        Route::middleware(['web', 'auth', EnsureUserHasRole::class.':admin'])
            ->get('/_tes/admin', fn () => 'ok');
    }

    public static function matriks(): array
    {
        return [
            'dispatcher ke halaman dispatcher' => ['dispatcher', '/_tes/dispatcher', 200],
            'dispatcher ke halaman responder'  => ['dispatcher', '/_tes/responder', 403],
            'dispatcher ke halaman admin'      => ['dispatcher', '/_tes/admin', 403],
            'responder ke halaman dispatcher'  => ['responder', '/_tes/dispatcher', 403],
            'responder ke halaman responder'   => ['responder', '/_tes/responder', 200],
            'responder ke halaman admin'       => ['responder', '/_tes/admin', 403],
            'admin ke halaman dispatcher'      => ['admin', '/_tes/dispatcher', 200],
            'admin ke halaman responder'       => ['admin', '/_tes/responder', 200],
            'admin ke halaman admin'           => ['admin', '/_tes/admin', 200],
            'warga ke halaman dispatcher'      => ['warga', '/_tes/dispatcher', 403],
            'warga ke halaman admin'           => ['warga', '/_tes/admin', 403],
        ];
    }

    #[DataProvider('matriks')]
    public function test_akses_sesuai_peran(string $role, string $path, int $status): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get($path)->assertStatus($status);
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get('/_tes/dispatcher')->assertRedirect(route('login'));
    }

    public static function pengarah(): array
    {
        return [
            'dispatcher' => ['dispatcher', '/uji-peran/dispatcher'],
            'responder'  => ['responder', '/uji-peran/responder'],
            'admin'      => ['admin', '/uji-peran/admin'],
        ];
    }

    #[DataProvider('pengarah')]
    public function test_dashboard_mengarahkan_sesuai_peran(string $role, string $tujuan): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect($tujuan);
    }

    public function test_dashboard_menolak_peran_tanpa_tujuan(): void
    {
        $user = User::factory()->create(['role' => 'warga']);

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }
}
