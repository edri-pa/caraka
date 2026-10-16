<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['incident_agencies', 'incident_logs', 'reporter_flags'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("Tabel {$table} berisi data. Periksa manual sebelum membangun ulang.");
            }
        }

        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('reporter_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('confirmed_count')->default(0);
            $table->unsignedInteger('false_count')->default(0);
            $table->timestamp('restricted_until')->nullable();
            $table->timestamps();
        });

        Schema::create('incident_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained(); // tanpa cascade: jejak audit tidak boleh ikut terhapus
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // kosong = tindakan sistem
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent(); // sengaja tanpa updated_at: log tidak diubah

            $table->index(['incident_id', 'created_at']);
        });

        Schema::create('incident_agencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agency_id')->constrained();
            $table->string('role')->default('pendukung'); // utama, pendukung
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();

            $table->unique(['incident_id', 'agency_id']);
        });
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
