<?php

namespace App\Services\SistemaTS;

use App\Models\Invoice;

class XmlBuilder
{
    /**
     * Costruisce un "record" semplificato per tracciato XML MEF (tessera sanitaria).
     * Stub completo: in Fase 7 verrà mappato sul formato ministeriale effettivo.
     *
     * Campi obbligatori (minimi):
     * - codice fiscale paziente
     * - data
     * - importo
     * - tipo spesa (SR occhiali/lenti, AD altri dispositivi)
     * - flag opposizione
     *
     * @return array<string, mixed>
     */
    public function buildRecord(Invoice $invoice): array
    {
        $patientCf = $invoice->customer_fiscal_code;

        // In Fase 5/6 non abbiamo ancora un modello "opposizione": impostiamo placeholder false.
        $flagOpposizione = false;

        $tipoSpesa = $this->resolveTipoSpesa($invoice);

        return [
            'codice_fiscale_paziente' => $patientCf,
            'data'                   => $invoice->invoice_date?->format('Y-m-d'),
            'importo'                => (float) $invoice->total,
            'tipo_spesa'             => $tipoSpesa, // SR | AD
            'flag_opposizione'       => $flagOpposizione,
        ];
    }

    private function resolveTipoSpesa(Invoice $invoice): string
    {
        $items = $invoice->relationLoaded('items') ? $invoice->items : $invoice->items()->get();

        foreach ($items as $item) {
            $d = mb_strtolower((string) $item->description);
            if (str_contains($d, 'lente') || str_contains($d, 'occhial')) {
                return 'SR';
            }
        }

        return 'AD';
    }
}

