<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('caraka.demo_staff_password');

        if (blank($password) || strlen($password) < 12) {
            throw new \RuntimeException('Isi DEMO_STAFF_PASSWORD di .env (minimal 12 karakter) sebelum menjalankan UserSeeder.');
        }

        $medisId = DB::table('agencies')->where('type', 'medis')->value('id');

        $users = [
            ['name' => 'Dispatcher Demo', 'email' => 'dispatcher@caraka.test', 'role' => 'dispatcher', 'agency_id' => null],
            ['name' => 'Responder Demo',  'email' => 'responder@caraka.test',  'role' => 'responder',  'agency_id' => $medisId],
            ['name' => 'Admin Demo',      'email' => 'admin@caraka.test',      'role' => 'admin',      'agency_id' => null],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                $user + [
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
