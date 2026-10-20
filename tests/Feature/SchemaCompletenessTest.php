<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchemaCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public static function tabel(): array
    {
        return [
            'agencies' => ['agencies', ['id', 'name', 'type', 'phone', 'is_demo']],
            'categories' => ['categories', ['id', 'parent_id', 'name', 'slug', 'base_priority', 'default_agency_type']],
            'districts' => ['districts', ['id', 'name', 'code', 'boundary']],
            'facilities' => ['facilities', ['id', 'agency_id', 'district_id', 'name', 'type', 'region', 'location', 'phone', 'is_24h', 'has_igd', 'is_active', 'is_demo', 'source', 'verified_at']],
            'incidents' => ['incidents', [
                'id', 'reporter_id', 'category_id', 'district_id', 'assigned_agency_id', 'suggested_facility_id',
                'description', 'landmark_text', 'location', 'location_accuracy_m', 'status', 'trust_score',
                'priority_score', 'in_pilot_area', 'photo_path', 'reported_at', 'closed_at',
                'reference', 'reporter_phone', 'location_source', 'has_casualty',
                'reporter_hash', 'flag_reason', 'flagged_by', 'flagged_at', 'flag_note',
            ]],
            'incident_logs' => ['incident_logs', ['id', 'incident_id', 'user_id', 'action', 'from_status', 'to_status', 'note', 'created_at']],
            'incident_agencies' => ['incident_agencies', ['id', 'incident_id', 'agency_id', 'role', 'assigned_by', 'assigned_at']],
            'users' => ['users', ['id', 'name', 'email', 'password', 'role', 'phone', 'phone_verified_at', 'agency_id']],
        ];
    }

    #[DataProvider('tabel')]
    public function test_semua_kolom_wajib_ada(string $tabel, array $kolom): void
    {
        $this->assertTrue(Schema::hasTable($tabel), "Tabel {$tabel} tidak ada.");

        $hilang = array_values(array_filter($kolom, fn ($k) => ! Schema::hasColumn($tabel, $k)));

        $this->assertSame([], $hilang, "Kolom hilang di {$tabel}: ".implode(', ', $hilang));
    }
}
