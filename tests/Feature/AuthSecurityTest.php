<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_pendaftaran_publik_tidak_ada(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_pendaftaran_lewat_post_tidak_membuat_akun(): void
    {
        $this->post('/register', [
            'name' => 'Penyusup',
            'email' => 'penyusup@example.test',
            'password' => 'kata-sandi-panjang-123',
            'password_confirmation' => 'kata-sandi-panjang-123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'penyusup@example.test']);
    }

    public function test_halaman_lupa_sandi_tidak_ada(): void
    {
        $this->get('/forgot-password')->assertNotFound();
    }

    public function test_login_berhasil_dengan_kredensial_benar(): void
    {
        $user = User::factory()->create(['role' => 'dispatcher']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_gagal_dengan_sandi_salah(): void
    {
        $user = User::factory()->create(['role' => 'dispatcher']);

        $this->post('/login', ['email' => $user->email, 'password' => 'salah-total'])
            ->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_dashboard_menolak_tamu(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
