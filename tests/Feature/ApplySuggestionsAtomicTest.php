<?php

namespace Tests\Feature;

use App\Exceptions\InvalidWorkflowAction;
use App\Models\User;
use App\Services\AgencyRoutingSuggester;
use App\Services\IncidentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApplySuggestionsAtomicTest extends TestCase
{
    use RefreshDatabase;

    private int $medis;

    private int $polisi;

    private int $damkar;

    private int $kategoriId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->medis = $this->buatInstansi('medis');
        $this->polisi = $this->buatInstansi('polisi');
        $this->damkar = $this->buatInstansi('damkar');

        $this->kategoriId = DB::table('categories')->insertGetId([
            'name' => 'Kecelakaan lalu lintas', 'slug' => 'kecelakaan-lalu-lintas', 'base_priority' => 4,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function buatInstansi(string $jenis): int
    {
        return DB::table('agencies')->insertGetId([
            'name' => 'Instansi '.$jenis.' uji', 'type' => $jenis, 'is_demo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function buatLaporan(string $status = 'baru'): int
    {
        return DB::table('incidents')->insertGetId([
            'category_id' => $this->kategoriId,
            'reference' => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
            'location' => null,
            'location_source' => 'manual',
            'has_casualty' => true,
            'status' => $status,
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function dispatcher(): User
    {
        return User::factory()->create(['role' => 'dispatcher']);
    }

    public function test_penerapan_normal_menetapkan_semua_saran_dan_mengembalikan_jumlahnya(): void
    {
        $id = $this->buatLaporan();

        $jumlah = app(IncidentWorkflow::class)->applySuggestions($id, $this->dispatcher()->id, app(AgencyRoutingSuggester::class));

        $this->assertSame(2, $jumlah);
        $this->assertDatabaseHas('incident_agencies', ['incident_id' => $id, 'agency_id' => $this->medis, 'role' => 'utama']);
        $this->assertDatabaseHas('incident_agencies', ['incident_id' => $id, 'agency_id' => $this->polisi, 'role' => 'pendukung']);
    }

    public function test_saran_dihitung_di_dalam_kunci_sehingga_utama_baru_tidak_ditimpa(): void
    {
        $id = $this->buatLaporan();
        $a = $this->dispatcher();
        $b = $this->dispatcher();
        $workflow = app(IncidentWorkflow::class);

        // Saran yang basi menunjuk medis sebagai utama.
        $basi = app(AgencyRoutingSuggester::class)->suggest($id);
        $this->assertSame([$this->medis], collect($basi)->where('role', 'utama')->pluck('agency_id')->map(fn ($v) => (int) $v)->all());

        // Petugas lain menetapkan damkar sebagai utama tepat saat kunci laporan diambil.
        $sudah = false;
        DB::listen(function ($query) use (&$sudah, $id, $workflow, $b) {
            if ($sudah || ! str_contains(strtolower($query->sql), 'for update')) {
                return;
            }

            $sudah = true;
            $workflow->assignAgency($id, $this->damkar, 'utama', $b->id);
        });

        $jumlah = $workflow->applySuggestions($id, $a->id, app(AgencyRoutingSuggester::class));

        $this->assertTrue($sudah, 'Kunci laporan tidak pernah diambil.');
        $this->assertGreaterThanOrEqual(1, $jumlah);

        $utama = DB::table('incident_agencies')->where('incident_id', $id)->where('role', 'utama')->pluck('agency_id')->map(fn ($v) => (int) $v)->all();
        $this->assertSame([$this->damkar], $utama);
        $this->assertDatabaseHas('incident_agencies', ['incident_id' => $id, 'agency_id' => $this->polisi]);
    }

    public function test_laporan_yang_sudah_ditutup_ditolak_dan_tidak_meninggalkan_sisa(): void
    {
        $id = $this->buatLaporan('selesai');

        try {
            app(IncidentWorkflow::class)->applySuggestions($id, $this->dispatcher()->id, app(AgencyRoutingSuggester::class));
            $this->fail('Seharusnya ditolak.');
        } catch (InvalidWorkflowAction $e) {
            $this->assertStringContainsString('ditutup', $e->getMessage());
        }

        $this->assertDatabaseCount('incident_agencies', 0);
        $this->assertSame(0, DB::table('incident_logs')->where('incident_id', $id)->count());
    }

    public function test_laporan_tidak_ada_ditolak(): void
    {
        $this->expectException(InvalidWorkflowAction::class);

        app(IncidentWorkflow::class)->applySuggestions(999999, $this->dispatcher()->id, app(AgencyRoutingSuggester::class));
    }

    public function test_pengendali_menolak_dengan_pesan_jelas_bila_petugas_lain_sudah_menerapkan_saran(): void
    {
        $id = $this->buatLaporan();
        $a = $this->dispatcher();
        $b = $this->dispatcher();
        $workflow = app(IncidentWorkflow::class);
        $sudah = false;

        DB::listen(function ($query) use (&$sudah, $id, $workflow, $b) {
            if ($sudah || ! str_contains(strtolower($query->sql), 'for update')) {
                return;
            }

            $sudah = true;
            $workflow->assignAgency($id, $this->medis, 'utama', $b->id);
            $workflow->assignAgency($id, $this->polisi, 'pendukung', $b->id);
        });

        $this->actingAs($a)
            ->post("/staf/laporan/{$id}/saran")
            ->assertRedirect(route('staf.laporan.show', $id))
            ->assertSessionHasErrors(['aksi' => 'Saran sudah diterapkan oleh petugas lain, atau tidak ada lagi yang bisa diterapkan.']);

        $this->assertTrue($sudah, 'Kunci laporan tidak pernah diambil.');
        $this->assertDatabaseCount('incident_agencies', 2);
    }
}
