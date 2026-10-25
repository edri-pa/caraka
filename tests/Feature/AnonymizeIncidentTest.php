<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnonymizeIncidentTest extends TestCase
{
    use RefreshDatabase;

    private function buatLaporan(array $ubah = []): int
    {
        $kategori = DB::table('categories')->insertGetId([
            'name' => 'Uji', 'slug' => 'uji', 'base_priority' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('incidents')->insertGetId(array_merge([
            'category_id' => $kategori,
            'reference' => 'ABCDE23456',
            'description' => 'Keterangan pribadi',
            'landmark_text' => 'Depan rumah Pak X',
            'reporter_phone' => '08123456789',
            'reporter_hash' => str_repeat('a', 64),
            'location' => DB::raw('ST_SetSRID(ST_MakePoint(110.18, -7.32), 4326)::geography'),
            'location_accuracy_m' => 20,
            'location_source' => 'gps',
            'flag_reason' => 'duplikat',
            'flag_note' => 'catatan pribadi',
            'status' => 'ditangani',
            'trust_score' => 70,
            'priority_score' => 4,
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $ubah));
    }

    public function test_data_pribadi_dihapus_tetapi_status_dan_metadata_tetap(): void
    {
        $id = $this->buatLaporan();

        $this->artisan('laporan:anonimkan', ['referensi' => 'abcde23456', '--yakin' => true])->assertExitCode(0);

        $laporan = DB::table('incidents')->where('id', $id)->first();

        $this->assertNull($laporan->reporter_phone);
        $this->assertNull($laporan->reporter_hash);
        $this->assertNull($laporan->landmark_text);
        $this->assertNull($laporan->location);
        $this->assertNull($laporan->location_accuracy_m);
        $this->assertNull($laporan->flag_note);
        $this->assertSame('[dihapus atas permintaan]', $laporan->description);
        $this->assertSame('manual', $laporan->location_source);

        $this->assertSame('ditangani', $laporan->status);
        $this->assertSame(70, (int) $laporan->trust_score);
        $this->assertSame(4, (int) $laporan->priority_score);
        $this->assertSame('duplikat', $laporan->flag_reason);
    }

    public function test_tanpa_opsi_yakin_tidak_ada_yang_berubah(): void
    {
        $id = $this->buatLaporan();

        $this->artisan('laporan:anonimkan', ['referensi' => 'ABCDE23456'])->assertExitCode(0);

        $this->assertSame('08123456789', DB::table('incidents')->where('id', $id)->value('reporter_phone'));
        $this->assertDatabaseMissing('incident_logs', ['incident_id' => $id, 'action' => 'anonimisasi']);
    }

    public function test_referensi_tidak_dikenal_ditolak(): void
    {
        $this->artisan('laporan:anonimkan', ['referensi' => 'TIDAKADA12', '--yakin' => true])->assertExitCode(1);
    }

    public function test_tercatat_di_audit_tanpa_data_pribadi_dan_penanda_sos_dipertahankan(): void
    {
        $id = $this->buatLaporan(['description' => 'LAPORAN SOS (satu ketukan)']);

        $this->artisan('laporan:anonimkan', ['referensi' => 'ABCDE23456', '--yakin' => true])->assertExitCode(0);

        $log = DB::table('incident_logs')->where('incident_id', $id)->where('action', 'anonimisasi')->first();

        $this->assertNotNull($log);
        $this->assertStringNotContainsString('08123456789', (string) $log->note);
        $this->assertSame('LAPORAN SOS (satu ketukan)', DB::table('incidents')->where('id', $id)->value('description'));
    }
}
