<?php

namespace Tests\Feature;

use App\Events\IncidentUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class IncidentBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoryId = DB::table('categories')->insertGetId([
            'name' => 'Kategori Uji',
            'slug' => 'uji',
            'base_priority' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buatLaporan(string $status = 'baru'): int
    {
        return DB::table('incidents')->insertGetId([
            'category_id' => $this->categoryId,
            'location' => null,
            'location_source' => 'manual',
            'reference' => strtoupper(substr(md5(uniqid('', true)), 0, 10)),
            'status' => $status,
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buatInstansi(): int
    {
        return DB::table('agencies')->insertGetId([
            'name' => 'Instansi Uji',
            'type' => 'medis',
            'is_demo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function masukSebagai(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        return $user;
    }

    private function payloadLaporan(): array
    {
        return [
            'category_id' => $this->categoryId,
            'latitude' => -7.32,
            'longitude' => 110.18,
            'accuracy' => 20,
        ];
    }

    public function test_event_menyiarkan_ke_channel_dispatch_dan_channel_tiap_instansi(): void
    {
        $event = new IncidentUpdated(5, 'status', [3, 3, 7]);

        $nama = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

        $this->assertSame(['private-dispatch', 'private-agency.3', 'private-agency.7'], $nama);
    }

    public function test_pesan_hanya_berisi_id_dan_jenis(): void
    {
        $event = new IncidentUpdated(5, 'status', [3]);

        $this->assertSame(['incident_id' => 5, 'type' => 'status'], $event->broadcastWith());
        $this->assertSame('incident.updated', $event->broadcastAs());
    }

    public function test_laporan_baru_dari_warga_menyiarkan_event(): void
    {
        Event::fake([IncidentUpdated::class]);

        $this->post('/lapor', $this->payloadLaporan())->assertRedirect();

        $id = (int) DB::table('incidents')->value('id');

        Event::assertDispatched(
            IncidentUpdated::class,
            fn ($e) => $e->type === 'baru' && $e->incidentId === $id
        );
    }

    public function test_perubahan_status_menyiarkan_event(): void
    {
        Event::fake([IncidentUpdated::class]);
        $id = $this->buatLaporan();
        $this->masukSebagai('dispatcher');

        $this->post("/staf/laporan/{$id}/status", ['to' => 'diverifikasi'])->assertRedirect();

        Event::assertDispatched(
            IncidentUpdated::class,
            fn ($e) => $e->type === 'status' && $e->incidentId === $id
        );
    }

    public function test_perpindahan_terlarang_tidak_menyiarkan_apa_pun(): void
    {
        Event::fake([IncidentUpdated::class]);
        $id = $this->buatLaporan();
        $this->masukSebagai('dispatcher');

        $this->post("/staf/laporan/{$id}/status", ['to' => 'selesai']);

        Event::assertNotDispatched(IncidentUpdated::class);
    }

    public function test_penetapan_instansi_menyiarkan_ke_channel_instansi(): void
    {
        Event::fake([IncidentUpdated::class]);
        $id = $this->buatLaporan();
        $agency = $this->buatInstansi();
        $this->masukSebagai('dispatcher');

        $this->post("/staf/laporan/{$id}/instansi", ['agency_id' => $agency, 'role' => 'utama'])->assertRedirect();

        Event::assertDispatched(
            IncidentUpdated::class,
            fn ($e) => $e->type === 'instansi' && in_array($agency, $e->agencyIds, true)
        );
    }

    public function test_gagal_siaran_tidak_menggagalkan_laporan_warga(): void
    {
        Event::listen(IncidentUpdated::class, function () {
            throw new \RuntimeException('Reverb mati (simulasi)');
        });

        $this->post('/lapor', $this->payloadLaporan())->assertRedirect();

        $this->assertDatabaseCount('incidents', 1);
        $this->assertDatabaseCount('incident_logs', 1);
    }

    public function test_gagal_siaran_tidak_menggagalkan_perubahan_status(): void
    {
        Event::listen(IncidentUpdated::class, function () {
            throw new \RuntimeException('Reverb mati (simulasi)');
        });
        $id = $this->buatLaporan();
        $this->masukSebagai('dispatcher');

        $this->post("/staf/laporan/{$id}/status", ['to' => 'diverifikasi'])
            ->assertRedirect(route('staf.laporan.show', $id));

        $this->assertSame('diverifikasi', (string) DB::table('incidents')->where('id', $id)->value('status'));
    }
}
