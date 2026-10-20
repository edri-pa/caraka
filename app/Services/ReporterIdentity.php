<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReporterIdentity
{
    /** Status yang menunjukkan laporan sudah diperiksa petugas. */
    public const CONFIRMED = ['diverifikasi', 'ditugaskan', 'ditangani', 'selesai'];

    /** Samakan 0812..., +62812..., dan 62812... menjadi 62812... */
    public function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        return '62'.$digits;
    }

    /**
     * Hash HMAC dari nomor yang sudah dinormalkan. Tetap data pribadi (pseudonim):
     * jangan ditampilkan ke publik, dan jaga kerahasiaan APP_KEY.
     */
    public function hash(?string $phone): ?string
    {
        $normalized = $this->normalize($phone);

        if ($normalized === null) {
            return null;
        }

        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }

    /**
     * Ringkasan riwayat untuk satu hash. Hanya angka, tanpa data pelapor lain.
     *
     * @return array{total: int, confirmed: int, false_flags: int, other_flags: int}
     */
    public function historyFor(?string $hash, ?int $excludeIncidentId = null): array
    {
        if ($hash === null) {
            return ['total' => 0, 'confirmed' => 0, 'false_flags' => 0, 'other_flags' => 0];
        }

        $query = DB::table('incidents')->where('reporter_hash', $hash);

        if ($excludeIncidentId !== null) {
            $query->where('id', '!=', $excludeIncidentId);
        }

        $rows = $query->get(['status', 'flag_reason']);

        return [
            'total' => $rows->count(),
            'confirmed' => $rows->filter(
                fn ($r) => $r->flag_reason === null && in_array($r->status, self::CONFIRMED, true)
            )->count(),
            'false_flags' => $rows->where('flag_reason', 'sengaja_palsu')->count(),
            'other_flags' => $rows->filter(
                fn ($r) => $r->flag_reason !== null && $r->flag_reason !== 'sengaja_palsu'
            )->count(),
        ];
    }
}
