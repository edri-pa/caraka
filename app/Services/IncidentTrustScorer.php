<?php

namespace App\Services;

class IncidentTrustScorer
{
    public const BASE = 30;

    /**
     * Skor kepercayaan awal 0-100. Hanya SARAN untuk dispatcher,
     * tidak pernah dipakai untuk menolak laporan.
     */
    public function score(
        bool $hasGps,
        ?float $accuracyMeters,
        bool $hasPhone,
        int $recentReportsFromSamePhone = 0,
    ): int {
        $score = self::BASE;

        if ($hasGps) {
            $score += 30;

            if ($accuracyMeters !== null) {
                if ($accuracyMeters <= 50) {
                    $score += 20;
                } elseif ($accuracyMeters <= 150) {
                    $score += 10;
                } elseif ($accuracyMeters > 1000) {
                    $score -= 10;
                }
            }
        }

        if ($hasPhone) {
            $score += 10;
        }

        if ($recentReportsFromSamePhone >= 3) {
            $score -= 20;
        }

        return max(0, min(100, $score));
    }
}
