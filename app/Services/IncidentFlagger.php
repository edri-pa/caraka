<?php

namespace App\Services;

use App\Exceptions\InvalidWorkflowAction;
use Illuminate\Support\Facades\DB;

class IncidentFlagger
{
    public const REASONS = [
        'sengaja_palsu' => 'Sengaja palsu',
        'itikad_baik_keliru' => 'Keliru dengan itikad baik',
        'duplikat' => 'Duplikat',
        'tidak_ditemukan' => 'Tidak ditemukan di lokasi',
    ];

    /**
     * Penandaan hanya informasi: tidak mengubah status dan tidak mengubah skor.
     */
    public function flag(int $incidentId, string $reason, ?string $note, int $userId): void
    {
        if (! array_key_exists($reason, self::REASONS)) {
            throw new InvalidWorkflowAction('Alasan penandaan tidak valid.');
        }

        $note = $note === null ? null : trim($note);

        if ($reason === 'sengaja_palsu' && mb_strlen((string) $note) < 10) {
            throw new InvalidWorkflowAction('Penandaan "sengaja palsu" wajib disertai catatan minimal 10 karakter.');
        }

        DB::transaction(function () use ($incidentId, $reason, $note, $userId) {
            $incident = DB::table('incidents')->where('id', $incidentId)->lockForUpdate()->first();

            if ($incident === null) {
                throw new InvalidWorkflowAction('Laporan tidak ditemukan.');
            }

            if ($incident->flag_reason !== null) {
                throw new InvalidWorkflowAction('Laporan sudah ditandai. Penandaan hanya bisa dihapus oleh admin.');
            }

            DB::table('incidents')->where('id', $incidentId)->update([
                'flag_reason' => $reason,
                'flag_note' => $note === '' ? null : $note,
                'flagged_by' => $userId,
                'flagged_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('incident_logs')->insert([
                'incident_id' => $incidentId,
                'user_id' => $userId,
                'action' => 'penandaan',
                'from_status' => $incident->status,
                'to_status' => $incident->status,
                'note' => 'Ditandai: '.self::REASONS[$reason].($note ? ' - '.$note : ''),
                'created_at' => now(),
            ]);
        });
    }

    public function clear(int $incidentId, int $userId): void
    {
        DB::transaction(function () use ($incidentId, $userId) {
            $incident = DB::table('incidents')->where('id', $incidentId)->lockForUpdate()->first();

            if ($incident === null) {
                throw new InvalidWorkflowAction('Laporan tidak ditemukan.');
            }

            if ($incident->flag_reason === null) {
                throw new InvalidWorkflowAction('Laporan ini tidak sedang ditandai.');
            }

            DB::table('incidents')->where('id', $incidentId)->update([
                'flag_reason' => null,
                'flag_note' => null,
                'flagged_by' => null,
                'flagged_at' => null,
                'updated_at' => now(),
            ]);

            DB::table('incident_logs')->insert([
                'incident_id' => $incidentId,
                'user_id' => $userId,
                'action' => 'penandaan_dihapus',
                'from_status' => $incident->status,
                'to_status' => $incident->status,
                'note' => 'Penandaan dihapus (sebelumnya: '.(self::REASONS[$incident->flag_reason] ?? $incident->flag_reason).')',
                'created_at' => now(),
            ]);
        });
    }
}
