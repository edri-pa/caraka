<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type'); // rumah_sakit, puskesmas, pos_damkar, polsek, polres, kantor_bpbd, lainnya
            $table->string('region')->default('Temanggung');
            $table->geography('location', subtype: 'point');
            $table->string('phone')->nullable();
            $table->boolean('is_24h')->default(false);
            $table->boolean('has_igd')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });
        DB::statement('CREATE INDEX facilities_location_gist ON facilities USING GIST (location)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
