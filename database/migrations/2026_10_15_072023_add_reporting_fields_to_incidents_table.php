<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('reference', 12)->nullable()->unique();
            $table->string('reporter_phone', 20)->nullable();
            $table->string('location_source', 10)->default('gps'); // gps, manual
        });

        // Laporan manual (tanpa GPS) tetap harus bisa disimpan.
        DB::statement('ALTER TABLE incidents ALTER COLUMN location DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['reference', 'reporter_phone', 'location_source']);
        });

        // Kolom location sengaja dibiarkan boleh kosong: mengembalikan NOT NULL
        // akan gagal jika sudah ada laporan manual.
    }
};
