<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            if (! Schema::hasColumn('incidents', 'has_casualty')) {
                $table->boolean('has_casualty')->nullable(); // null = tidak diketahui
            }
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            if (Schema::hasColumn('incidents', 'has_casualty')) {
                $table->dropColumn('has_casualty');
            }
        });
    }
};
