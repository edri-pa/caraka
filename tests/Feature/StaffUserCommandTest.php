<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffUserCommandTest extends TestCase
{
    use RefreshDatabase;

    private const SANDI = 'sandi-uji-panjang-1';

    private function buat(array $argumen, string $sandi = self::SANDI, ?string $ulang = null)
    {
        return $this->artisan('pengguna:buat', $argumen)
            ->expectsQuestion('Kata sandi (minimal 12 karakter)', $sandi)
            ->expectsQuestion('Ulangi kata sandi', $ulang ?? $sandi);
    }

    private function buatInstansi(): int
    {
        return DB::table('agencies')->insertGetId([
            'name' => 'Instansi Uji',
            'type' => 'medis',
            'is_demo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_membuat_dispatcher_dengan_sandi_ter_hash(): void
    {
        $this->buat(['email' => 'uji1@caraka.test', 'nama' => 'Uji Satu', '--peran' => 'dispatcher'])
            ->assertExitCode(0);

        $user = DB::table('users')->where('email', 'uji1@caraka.test')->first();

        $this->assertSame('dispatcher', $user->role);
        $this->assertNull($user->agency_id);
        $this->assertTrue(Hash::check(self::SANDI, $user->password));
        $this->assertNotSame(self::SANDI, $user->password);
    }

    public function test_responder_dengan_instansi_valid_terhubung_ke_instansi(): void
    {
        $instansi = $this->buatInstansi();

        $this->buat(['email' => 'uji2@caraka.test', 'nama' => 'Uji Dua', '--peran' => 'responder', '--instansi' => $instansi])
            ->assertExitCode(0);

        $this->assertSame($instansi, (int) DB::table('users')->where('email', 'uji2@caraka.test')->value('agency_id'));
    }

    public function test_responder_tanpa_instansi_atau_instansi_tidak_ada_ditolak(): void
    {
        $this->artisan('pengguna:buat', ['email' => 'uji3@caraka.test', 'nama' => 'Uji Tiga', '--peran' => 'responder'])
            ->assertExitCode(1);
        $this->artisan('pengguna:buat', ['email' => 'uji3@caraka.test', 'nama' => 'Uji Tiga', '--peran' => 'responder', '--instansi' => '999999'])
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'uji3@caraka.test']);
    }

    public function test_peran_dan_email_tidak_valid_ditolak(): void
    {
        $this->artisan('pengguna:buat', ['email' => 'uji4@caraka.test', 'nama' => 'Uji Empat', '--peran' => 'bos'])
            ->assertExitCode(1);
        $this->artisan('pengguna:buat', ['email' => 'bukan-email', 'nama' => 'Uji Lima'])
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_sandi_pendek_ditolak(): void
    {
        $this->artisan('pengguna:buat', ['email' => 'uji5@caraka.test', 'nama' => 'Uji Lima'])
            ->expectsQuestion('Kata sandi (minimal 12 karakter)', 'pendek')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'uji5@caraka.test']);
    }

    public function test_konfirmasi_sandi_berbeda_ditolak(): void
    {
        $this->buat(['email' => 'uji6@caraka.test', 'nama' => 'Uji Enam'], self::SANDI, 'sandi-lain-panjang-2')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'uji6@caraka.test']);
    }

    public function test_akun_yang_sudah_ada_tidak_tertimpa_tanpa_opsi_perbarui(): void
    {
        $user = User::factory()->create(['email' => 'ada@caraka.test', 'name' => 'Nama Lama', 'role' => 'admin']);

        $this->artisan('pengguna:buat', ['email' => 'ada@caraka.test', 'nama' => 'Nama Baru', '--peran' => 'dispatcher'])
            ->assertExitCode(1);

        $this->assertSame('Nama Lama', DB::table('users')->where('id', $user->id)->value('name'));
        $this->assertSame('admin', DB::table('users')->where('id', $user->id)->value('role'));
    }

    public function test_opsi_perbarui_mengganti_peran_dan_sandi(): void
    {
        User::factory()->create(['email' => 'ada@caraka.test', 'role' => 'admin']);

        $this->buat(['email' => 'ada@caraka.test', 'nama' => 'Nama Baru', '--peran' => 'dispatcher', '--perbarui' => true])
            ->assertExitCode(0);

        $user = DB::table('users')->where('email', 'ada@caraka.test')->first();

        $this->assertSame('dispatcher', $user->role);
        $this->assertSame('Nama Baru', $user->name);
        $this->assertTrue(Hash::check(self::SANDI, $user->password));
    }

    public function test_kata_sandi_tidak_tercetak_di_keluaran(): void
    {
        $this->buat(['email' => 'uji7@caraka.test', 'nama' => 'Uji Tujuh'])
            ->doesntExpectOutputToContain(self::SANDI)
            ->assertExitCode(0);
    }

    public function test_akun_hasil_perintah_bisa_login_dan_diarahkan_sesuai_peran(): void
    {
        $this->buat(['email' => 'login@caraka.test', 'nama' => 'Uji Login', '--peran' => 'dispatcher'])
            ->assertExitCode(0);

        $this->post('/login', ['email' => 'login@caraka.test', 'password' => self::SANDI])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    }
}
