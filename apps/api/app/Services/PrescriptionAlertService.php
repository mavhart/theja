<?php

namespace App\Services;

use App\Models\Prescription;

class PrescriptionAlertService
{
    /**
     * @return 'none'|'warning'|'expired'
     */
    public static function resolve(?Prescription $latest): string
    {
        if ($latest === null) {
            return 'none';
        }

        $today = now()->startOfDay();

        if ($latest->next_recall_at !== null) {
            $recall = $latest->next_recall_at->copy()->startOfDay();
            if ($recall->lt($today)) {
                return 'expired';
            }
        }

        $visit = $latest->visit_date->copy()->startOfDay();
        $m12 = $visit->copy()->addMonths(12);
        $m18 = $visit->copy()->addMonths(18);

        if ($today->lt($m12)) {
            return 'none';
        }
        if ($today->lt($m18)) {
            return 'warning';
        }

        return 'expired';
    }
}
