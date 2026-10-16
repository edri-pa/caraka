<?php

namespace Tests\Feature;

use App\Broadcasting\IncidentChannelAccess;
use App\Models\User;
use Tests\TestCase;

class IncidentChannelAccessTest extends TestCase
{
    private function pengguna(string $role, ?int $agencyId = null): User
    {
        return User::factory()->make(['role' => $role, 'agency_id' => $agencyId]);
    }

    private function akses(): IncidentChannelAccess
    {
        return new IncidentChannelAccess();
    }

    public function test_dispatcher_dan_admin_boleh_channel_dispatch(): void
    {
        $this->assertTrue($this->akses()->dispatch($this->pengguna('dispatcher')));
        $this->assertTrue($this->akses()->dispatch($this->pengguna('admin')));
    }

    public function test_responder_dan_warga_tidak_boleh_channel_dispatch(): void
    {
        $this->assertFalse($this->akses()->dispatch($this->pengguna('responder', 3)));
        $this->assertFalse($this->akses()->dispatch($this->pengguna('warga')));
    }

    public function test_responder_boleh_channel_instansinya_sendiri(): void
    {
        $this->assertTrue($this->akses()->agency($this->pengguna('responder', 3), 3));
    }

    public function test_responder_tidak_boleh_channel_instansi_lain(): void
    {
        $this->assertFalse($this->akses()->agency($this->pengguna('responder', 3), 4));
    }

    public function test_responder_tanpa_instansi_tidak_boleh_channel_instansi_mana_pun(): void
    {
        $this->assertFalse($this->akses()->agency($this->pengguna('responder', null), 0));
        $this->assertFalse($this->akses()->agency($this->pengguna('responder', null), 3));
    }

    public function test_dispatcher_dan_warga_tidak_boleh_channel_instansi(): void
    {
        $this->assertFalse($this->akses()->agency($this->pengguna('dispatcher', 3), 3));
        $this->assertFalse($this->akses()->agency($this->pengguna('warga', 3), 3));
    }

    public function test_channel_terdaftar_di_routes_channels(): void
    {
        $isi = file_get_contents(base_path('routes/channels.php'));

        $this->assertStringContainsString("channel('dispatch'", $isi);
        $this->assertStringContainsString("channel('agency.{agencyId}'", $isi);
    }
}
