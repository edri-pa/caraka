<?php

return [
    // Hanya untuk menyemai akun demo (UserSeeder). Hapus dari lingkungan setelah akun dibuat.
    'demo_staff_password' => env('DEMO_STAFF_PASSWORD'),

    // Nomor versi dibaca dari berkas VERSION di akar proyek.
    'versi' => trim((string) @file_get_contents(base_path('VERSION'))),
];
