<?php

namespace App\Services;

use App\Exceptions\InvalidWorkflowAction;
use Illuminate\Support\Facades\DB;

class IncidentWorkflow
{
    public const TRANSITIONS = [
        'baru' => ['diverifikasi', 'tidak_terbukti', 'dibatalkan'],
        'diverifikasi' => ['ditugaskan', 'tidak_terbukti', 'dibatalkan'],
        'ditugaskan' => ['ditangani', 'diverifikasi', 'dibatalkan'],
        'ditangani' => ['selesai', 'dibatalkan'],
        'selesai' => [],
        'tidak_terbukti' => [],
        'dibatalkan' => [],
    ];

    public const TERMINAL = ['selesai', 'tidak_terbukti', 'dibatalkan'];

    public const AGENCY_ROLES = ['utama', 'pendukung'];

    public function allowedFrom(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public function transition(int $incidentId, string $to, ?int $userId, ?string $note = null): void
    {
        DB::transaction(function () use ($incidentId, $to, $userId, $note) {
            $incident = $this->lock($incidentId);
            $from = $incident->status;

            if (! in_array($to, $this->allowedFrom($from), true)) {
                throw new InvalidWorkflowAction("Status tidak boleh berpindah dari {$from} ke {$to}.");
            }

            $hasMainAgency = DB::table('incident_agencies')
                ->where('incident_id', $incidentId)
                ->where('role', 'utama')
                ->exists();

            if ($to === 'ditugaskan' && ! $hasMainAgency) {
                throw new InvalidWorkflowAction('Tentukan instansi utama sebelum menugaskan.');
            }

            DB::table('incidents')->where('id', $incidentId)->update([
                'status' => $to,
                'closed_at' => in_array($to, self::TERMINAL, true) ? now() : null,
                'updated_at' => now(),
            ]);

            $this->log($incidentId, $userId, 'ubah_status', $from, $to, $note);
        });
    }

    public function assignAgency(int $incidentId, int $agencyId, string $role, ?int $userId): void
    {
        if (! in_array($role, self::AGENCY_ROLES, true)) {
            throw new InvalidWorkflowAction('Peran instansi tidak valid.');
        }

        DB::transaction(function () use ($incidentId, $agencyId, $role, $userId) {
            $incident = $this->lock($incidentId);

            if (in_array($incident->status, self::TERMINAL, true)) {
                throw new InvalidWorkflowAction('Laporan sudah ditutup, instansi tidak bisa diubah.');
            }

            $agency = DB::table('agencies')->where('id', $agencyId)->first();

            if ($agency === null) {
                throw new InvalidWorkflowAction('Instansi tidak ditemukan.');
            }

            $current = DB::table('incident_agencies')
                ->where('incident_id', $incidentId)
                ->where('agency_id', $agencyId)
                ->first();

            if ($role === 'pendukung' && $current !== null && $current->role === 'utama') {
                throw new InvalidWorkflowAction('Instansi ini sudah menjadi instansi utama.');
            }

            if ($role === 'utama') {
                DB::table('incident_agencies')
                    ->where('incident_id', $incidentId)
                    ->where('role', 'utama')
                    ->update(['role' => 'pendukung']);
            }

            DB::table('incident_agencies')->updateOrInsert(
                ['incident_id' => $incidentId, 'agency_id' => $agencyId],
                ['role' => $role, 'assigned_by' => $userId, 'assigned_at' => now()]
            );

            if ($role === 'utama') {
                DB::table('incidents')->where('id', $incidentId)->update([
                    'assigned_agency_id' => $agencyId,
                    'updated_at' => now(),
                ]);
            }

            $this->log(
                $incidentId,
                $userId,
                'penugasan_instansi',
                $incident->status,
                $incident->status,
                "Instansi {$agency->name} ({$role})"
            );
        });
    }

    private function lock(int $incidentId): object
    {
        $incident = DB::table('incidents')->where('id', $incidentId)->lockForUpdate()->first();

        if ($incident === null) {
            throw new InvalidWorkflowAction('Laporan tidak ditemukan.');
        }

        return $incident;
    }

    private function log(int $incidentId, ?int $userId, string $action, ?string $from, ?string $to, ?string $note): void
    {
        DB::table('incident_logs')->insert([
            'incident_id' => $incidentId,
            'user_id' => $userId,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    /**
     * Terapkan saran instansi secara atomik: laporan dikunci, saran DIHITUNG ULANG di dalam kunci,
     * lalu diterapkan. Saran yang sudah basi (petugas lain baru menetapkan instansi) tidak bisa
     * menimpa penetapan itu.
     *
     * @return int jumlah instansi yang ditetapkan (0 bila tidak ada lagi yang bisa diterapkan)
     */
    public function applySuggestions(int $incidentId, ?int $userId, \App\Services\AgencyRoutingSuggester $suggester): int
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($incidentId, $userId, $suggester) {
            $laporan = \Illuminate\Support\Facades\DB::table('incidents')
                ->where('id', $incidentId)
                ->lockForUpdate()
                ->first(['id', 'status']);

            if ($laporan === null) {
                throw new \App\Exceptions\InvalidWorkflowAction('Laporan tidak ditemukan.');
            }

            if (in_array($laporan->status, self::TERMINAL, true)) {
                throw new \App\Exceptions\InvalidWorkflowAction('Laporan sudah ditutup; saran tidak bisa diterapkan.');
            }

            $saran = $suggester->suggest($incidentId);

            foreach ($saran as $s) {
                $this->assignAgency($incidentId, $s['agency_id'], $s['role'], $userId);
            }

            return count($saran);
        });
    }
}
