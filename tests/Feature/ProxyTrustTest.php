<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProxyTrustTest extends TestCase
{
    use RefreshDatabase;

    public function test_di_belakang_proxy_https_alamat_formulir_lapor_memakai_https(): void
    {
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/lapor')
            ->assertOk()
            ->assertSee('action="https://', false);
    }

    public function test_di_belakang_proxy_https_alamat_formulir_login_memakai_https(): void
    {
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/login')
            ->assertOk()
            ->assertSee('action="https://', false);
    }

    public function test_tanpa_proxy_alamat_formulir_tetap_http(): void
    {
        $this->get('/lapor')
            ->assertOk()
            ->assertSee('action="http://', false);
    }
}
