<?php

namespace App\Services;

use App\Models\LacExam;
use App\Models\Patient;
use App\Models\Prescription;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    public function generateReferto(Prescription $prescription, Patient $patient): string
    {
        $prescription->loadMissing('pointOfSale', 'optician');

        $pdf = Pdf::loadView('pdf.referto_visita', [
            'patient'      => $patient,
            'prescription' => $prescription,
            'pos'          => $prescription->pointOfSale,
        ]);
        $pdf->setPaper('a4');

        return base64_encode($pdf->output());
    }

    public function generateSchedaLac(LacExam $lacExam, Patient $patient): string
    {
        $lacExam->loadMissing('pointOfSale', 'optician');

        $pdf = Pdf::loadView('pdf.scheda_lac', [
            'patient' => $patient,
            'exam'    => $lacExam,
            'pos'     => $lacExam->pointOfSale,
        ]);
        $pdf->setPaper('a4');

        return base64_encode($pdf->output());
    }

    public function generateCertificatoIdoneita(Prescription $prescription, Patient $patient): string
    {
        $prescription->loadMissing('pointOfSale', 'optician');

        $pdf = Pdf::loadView('pdf.certificato_idoneita', [
            'patient'      => $patient,
            'prescription' => $prescription,
            'pos'          => $prescription->pointOfSale,
        ]);
        $pdf->setPaper('a4');

        return base64_encode($pdf->output());
    }
}
