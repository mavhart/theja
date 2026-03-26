<?php

namespace App\Http\Controllers;

use App\Models\LacExam;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicalPdfController extends Controller
{
    public function prescriptionPdf(Request $request, Patient $patient, Prescription $prescription, PdfService $pdf): JsonResponse
    {
        $this->assertPrescriptionBelongsToPatient($patient, $prescription);

        $request->validate([
            'type' => ['required', 'string', 'in:referto,certificato'],
        ]);

        $type = $request->query('type');

        if ($type === 'referto') {
            $b64 = $pdf->generateReferto($prescription, $patient);
            $name = 'referto_visita_'.$prescription->id.'.pdf';
        } else {
            $b64 = $pdf->generateCertificatoIdoneita($prescription, $patient);
            $name = 'certificato_idoneita_'.$prescription->id.'.pdf';
        }

        return response()->json([
            'filename'   => $name,
            'pdf_base64' => $b64,
        ]);
    }

    public function lacExamPdf(Patient $patient, LacExam $lacExam, PdfService $pdf): JsonResponse
    {
        $this->assertLacExamBelongsToPatient($patient, $lacExam);

        $b64 = $pdf->generateSchedaLac($lacExam, $patient);

        return response()->json([
            'filename'   => 'scheda_lac_'.$lacExam->id.'.pdf',
            'pdf_base64' => $b64,
        ]);
    }

    private function assertPrescriptionBelongsToPatient(Patient $patient, Prescription $prescription): void
    {
        abort_if($prescription->patient_id !== $patient->id, 404);
    }

    private function assertLacExamBelongsToPatient(Patient $patient, LacExam $lacExam): void
    {
        abort_if($lacExam->patient_id !== $patient->id, 404);
    }
}
