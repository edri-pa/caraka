<?php

require __DIR__.'/../vendor/autoload.php';

// Pengaman: tes yang memakai RefreshDatabase mengosongkan database yang dituju.
// Hentikan semuanya bila database bukan "testing".
$database = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE');

if ($database !== 'testing') {
    fwrite(STDERR, "Tes dibatalkan: DB_DATABASE harus 'testing', bukan ".var_export($database, true).".\n");
    exit(1);
}
