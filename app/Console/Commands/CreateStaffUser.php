<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateStaffUser extends Command
{
    protected $signature = 'pengguna:buat {email} {nama}
        {--peran=dispatcher : dispatcher, responder, atau admin}
        {--instansi= : ID instansi (wajib untuk responder)}
        {--perbarui : Izinkan mengganti akun yang sudah ada}';

    protected $description = 'Buat atau perbarui akun staf (kata sandi diminta secara tertutup)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $nama = trim((string) $this->argument('nama'));
        $peran = (string) $this->option('peran');
        $instansi = $this->option('instansi');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email tidak valid.');

            return self::FAILURE;
        }

        if ($nama === '') {
            $this->error('Nama wajib diisi.');

            return self::FAILURE;
        }

        if (! in_array($peran, ['dispatcher', 'responder', 'admin'], true)) {
            $this->error('Peran harus dispatcher, responder, atau admin.');

            return self::FAILURE;
        }

        $agencyId = null;

        if ($peran === 'responder') {
            $valid = $instansi !== null
                && ctype_digit((string) $instansi)
                && DB::table('agencies')->where('id', (int) $instansi)->exists();

            if (! $valid) {
                $this->error('Responder wajib memakai --instansi=ID yang valid. Lihat daftar ID di tabel agencies.');

                return self::FAILURE;
            }

            $agencyId = (int) $instansi;
        }

        $sudahAda = DB::table('users')->where('email', $email)->exists();

        if ($sudahAda && ! $this->option('perbarui')) {
            $this->error('Akun sudah ada. Tambahkan --perbarui untuk mengganti nama, peran, dan kata sandi.');

            return self::FAILURE;
        }

        $sandi = (string) $this->secret('Kata sandi (minimal 12 karakter)');

        if (strlen($sandi) < 12) {
            $this->error('Kata sandi minimal 12 karakter.');

            return self::FAILURE;
        }

        $ulang = (string) $this->secret('Ulangi kata sandi');

        if (! hash_equals($sandi, $ulang)) {
            $this->error('Kata sandi tidak sama.');

            return self::FAILURE;
        }

        $nilai = [
            'name' => $nama,
            'password' => Hash::make($sandi),
            'role' => $peran,
            'agency_id' => $agencyId,
            'email_verified_at' => now(),
            'updated_at' => now(),
        ];

        if ($sudahAda) {
            DB::table('users')->where('email', $email)->update($nilai);
        } else {
            DB::table('users')->insert($nilai + ['email' => $email, 'created_at' => now()]);
        }

        $this->info(($sudahAda ? 'Akun diperbarui: ' : 'Akun dibuat: ')."{$email} ({$peran})");

        return self::SUCCESS;
    }
}
