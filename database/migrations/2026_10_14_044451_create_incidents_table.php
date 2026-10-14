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
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->constrained();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_agency_id')->nullable()->constrained('agencies')->nullOnDelete();
            $table->foreignId('suggested_facility_id')->nullable()->constrained('facilities')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('landmark_text')->nullable();
            $table->geography('location', subtype: 'point');
            $table->float('location_accuracy_m')->nullable();
            $table->string('status')->default('baru');
            $table->unsignedTinyInteger('trust_score')->default(50);
            $table->unsignedTinyInteger('priority_score')->default(3);
            $table->boolean('in_pilot_area')->default(true);
            $table->string('photo_path')->nullable();
            $table->timestamp('reported_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('reported_at');
        });

        DB::statement('CREATE INDEX incidents_location_gist ON incidents USING GIST (location)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
