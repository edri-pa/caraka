<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PasskeyDisabledTest extends TestCase
{
    public function test_fitur_passkey_tidak_aktif_dan_tidak_punya_rute(): void
    {
        $fitur = strtolower((string) json_encode(config('fortify.features', [])));

        $this->assertStringNotContainsString('passkey', $fitur);
        $this->assertStringNotContainsString('webauthn', $fitur);

        $rute = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->uri())
            ->filter(fn ($uri) => preg_match('/passkey|webauthn/i', $uri) === 1)
            ->values()
            ->all();

        $this->assertSame([], $rute, "Rute passkey atau webauthn terdaftar:\n".implode("\n", $rute));
    }
}
