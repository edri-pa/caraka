<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            if (! Schema::hasColumn('facilities', 'source')) {
                $table->string('source')->nullable();
            }
            if (! Schema::hasColumn('facilities', 'verified_at')) {
                $table->date('verified_at')->nullable();
            }
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->unique(['name', 'region'], 'facilities_name_region_unique');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropUnique('facilities_name_region_unique');
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn(['source', 'verified_at']);
        });
    }
};
