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
            if (! Schema::hasColumn('incidents', 'reporter_hash')) {
                $table->string('reporter_hash', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('incidents', 'flag_reason')) {
                $table->string('flag_reason', 30)->nullable();
            }
            if (! Schema::hasColumn('incidents', 'flagged_by')) {
                $table->foreignId('flagged_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('incidents', 'flagged_at')) {
                $table->timestamp('flagged_at')->nullable();
            }
            if (! Schema::hasColumn('incidents', 'flag_note')) {
                $table->string('flag_note', 500)->nullable();
            }
        });

        if (Schema::hasTable('reporter_flags')) {
            if (DB::table('reporter_flags')->exists()) {
                throw new RuntimeException('Tabel reporter_flags berisi data. Periksa manual sebelum menghapusnya.');
            }

            Schema::drop('reporter_flags');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('reporter_flags')) {
            Schema::create('reporter_flags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->unsignedInteger('confirmed_count')->default(0);
                $table->unsignedInteger('false_count')->default(0);
                $table->timestamp('restricted_until')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('incidents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('flagged_by');
            $table->dropColumn(['reporter_hash', 'flag_reason', 'flagged_at', 'flag_note']);
        });
    }
};
