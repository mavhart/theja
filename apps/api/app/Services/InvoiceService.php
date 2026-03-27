<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSequence;
use App\Models\PointOfSale;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Patient;
use App\Services\CashRegister\RtService;
use Barryvdh\DomPDF\Facade\Pdf;
use DOMDocument;
use DOMElement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    public function __construct(private readonly RtService $rtService)
    {
    }

    public function createFromSale(Sale $sale): Invoice
    {
        $sale->loadMissing(['pointOfSale', 'patient', 'items']);

        $pos = $sale->pointOfSale;
        $patient = $sale->patient;

        if (! $pos) {
            abort(422, 'POS non trovato per la vendita.');
        }

        $year = (int) ($sale->sale_date?->format('Y') ?? now()->format('Y'));
        $invoiceNumber = $this->generateNumber($pos, $year);

        return DB::transaction(function () use ($sale, $pos, $patient, $invoiceNumber) {
            $items = $sale->items ?? collect();

            $subtotal = 0.0;
            $vatAmount = 0.0;
            $total = 0.0;

            $invoice = Invoice::create([
                'pos_id'            => $pos->id,
                'organization_id'  => $pos->organization_id,
                'sale_id'          => $sale->id,
                'patient_id'       => $patient?->id,
                'invoice_number'   => $invoiceNumber,
                'invoice_date'     => $sale->sale_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'type'             => 'fattura',
                'status'           => 'draft',

                'customer_fiscal_code' => $patient?->fiscal_code,
                'customer_vat_number'  => $patient?->vat_number,
                'customer_name'         => trim(($patient?->first_name ?? '') . ' ' . ($patient?->last_name ?? '')) ?: 'Cliente',

                'customer_address'   => $patient?->billing_address ?? $patient?->address,
                'customer_city'      => $patient?->billing_city ?? $patient?->city,
                'customer_cap'       => $patient?->billing_cap ?? $patient?->cap,
                'customer_province'  => $patient?->billing_province ?? $patient?->province,
                'customer_country'   => $patient?->billing_country ?? $patient?->country ?? 'IT',
                'customer_pec'       => $patient?->email_pec,
                'customer_fe_code'  => $patient?->fe_recipient_code,

                'subtotal'   => 0,
                'vat_amount' => 0,
                'total'      => 0,

                'payment_method' => null,
                'payment_terms'  => null,
                'notes'          => $sale->notes,
            ]);

            foreach ($items as $saleItem) {
                $this->appendInvoiceItemFromSaleItem($invoice, $saleItem, $subtotal, $vatAmount, $total);
            }

            $invoice->update([
                'subtotal'   => round($subtotal, 2),
                'vat_amount' => round($vatAmount, 2),
                'total'      => round($total, 2),
            ]);

            return $invoice->fresh(['items']);
        });
    }

    /**
     * Creazione manuale (supporto UI "inserimento manuale").
     *
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $items
     */
    public function createManual(array $data, array $items): Invoice
    {
        $pos = PointOfSale::query()->findOrFail($data['pos_id']);
        $patient = isset($data['patient_id']) ? Patient::query()->findOrFail($data['patient_id']) : null;

        $year = (int) (isset($data['invoice_date']) ? date('Y', strtotime((string) $data['invoice_date'])) : now()->format('Y'));
        $invoiceNumber = $this->generateNumber($pos, $year);

        return DB::transaction(function () use ($pos, $patient, $data, $items, $invoiceNumber, $year) {
            $invoice = Invoice::create([
                'pos_id'            => $pos->id,
                'organization_id'  => $pos->organization_id,
                'sale_id'          => null,
                'patient_id'       => $patient?->id,
                'invoice_number'   => $invoiceNumber,
                'invoice_date'     => isset($data['invoice_date']) ? (string) $data['invoice_date'] : now()->format('Y-m-d'),
                'type'             => (string) ($data['type'] ?? 'fattura'),
                'status'           => 'draft',

                'customer_fiscal_code' => $patient?->fiscal_code,
                'customer_vat_number'  => $patient?->vat_number,
                'customer_name'         => trim(($patient?->first_name ?? '') . ' ' . ($patient?->last_name ?? '')) ?: 'Cliente',
                'customer_address'      => $patient?->billing_address ?? $patient?->address,
                'customer_city'         => $patient?->billing_city ?? $patient?->city,
                'customer_cap'          => $patient?->billing_cap ?? $patient?->cap,
                'customer_province'     => $patient?->billing_province ?? $patient?->province,
                'customer_country'      => $patient?->billing_country ?? $patient?->country ?? 'IT',
                'customer_pec'          => $patient?->email_pec,
                'customer_fe_code'      => $patient?->fe_recipient_code,

                'payment_method' => $data['payment_method'] ?? null,
                'payment_terms'  => $data['payment_terms'] ?? null,
                'notes'          => $data['notes'] ?? null,

                'subtotal'   => 0,
                'vat_amount' => 0,
                'total'      => 0,
            ]);

            $subtotal = 0.0;
            $vatAmount = 0.0;
            $totalAmount = 0.0;

            foreach ($items as $row) {
                $quantity = (float) ($row['quantity'] ?? 1);
                $unitPrice = (float) ($row['unit_price'] ?? 0);
                $discountPercent = (float) ($row['discount_percent'] ?? 0);
                $vatRate = (float) ($row['vat_rate'] ?? 0);

                $lineSub = $quantity * $unitPrice;
                $lineSubAfterDiscount = max(0, $lineSub - ($lineSub * $discountPercent / 100));
                $lineVat = $lineSubAfterDiscount * $vatRate / 100;

                $invoice->items()->create([
                    'description'     => (string) ($row['description'] ?? ''),
                    'quantity'        => $quantity,
                    'unit_price'      => $unitPrice,
                    'discount_percent'=> $discountPercent,
                    'subtotal'        => round($lineSubAfterDiscount, 2),
                    'vat_rate'        => $vatRate,
                    'vat_amount'      => round($lineVat, 2),
                    'total'           => round($lineSubAfterDiscount + $lineVat, 2),
                    'sts_code'        => $row['sts_code'] ?? null,
                ]);

                $subtotal += $lineSubAfterDiscount;
                $vatAmount += $lineVat;
                $totalAmount += $lineSubAfterDiscount + $lineVat;
            }

            $invoice->update([
                'subtotal'   => round($subtotal, 2),
                'vat_amount' => round($vatAmount, 2),
                'total'      => round($totalAmount, 2),
            ]);

            return $invoice->fresh(['items']);
        });
    }

    /**
     * Applica override lato API (utilizzato quando la fattura nasce da una vendita ma
     * l'interfaccia permette di cambiare alcuni campi come tipo/data/pagamento).
     *
     * @param array<string, mixed> $data
     */
    public function applyOverrides(Invoice $invoice, array $data): Invoice
    {
        $patch = [];

        if (array_key_exists('invoice_date', $data)) {
            $patch['invoice_date'] = (string) $data['invoice_date'];
        }

        if (array_key_exists('type', $data) && $data['type']) {
            $patch['type'] = (string) $data['type'];
        }

        if (array_key_exists('payment_method', $data)) {
            $patch['payment_method'] = $data['payment_method'];
        }

        if (array_key_exists('payment_terms', $data)) {
            $patch['payment_terms'] = $data['payment_terms'];
        }

        if (array_key_exists('notes', $data)) {
            $patch['notes'] = $data['notes'];
        }

        if (! empty($patch)) {
            $invoice->update($patch);
        }

        return $invoice->fresh(['items', 'pointOfSale', 'patient', 'sale']);
    }

    public function generateNumber(PointOfSale $pos, int $year): string
    {
        return DB::transaction(function () use ($pos, $year) {
            $seq = InvoiceSequence::query()
                ->where('pos_id', $pos->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                try {
                    $seq = InvoiceSequence::query()->create([
                        'pos_id'       => $pos->id,
                        'year'         => $year,
                        'last_number' => 0,
                        'prefix'       => 'FAT',
                    ]);
                } catch (QueryException $e) {
                    // Concorrenza: la riga potrebbe essere stata creata da un altro thread.
                    $seq = InvoiceSequence::query()
                        ->where('pos_id', $pos->id)
                        ->where('year', $year)
                        ->lockForUpdate()
                        ->firstOrFail();
                }
            }

            $next = (int) ($seq->last_number ?? 0) + 1;
            $seq->last_number = $next;
            $seq->save();

            $prefix = $seq->prefix ?: 'FAT';

            return sprintf('%s%s/%06d', (string) $prefix, (string) $year, $next);
        });
    }

    public function generatePdf(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'pointOfSale', 'patient', 'sale']);

        $pdf = Pdf::loadView('pdf.fattura_pa', [
            'invoice' => $invoice,
            'pos'     => $invoice->pointOfSale,
            'patient' => $invoice->patient,
        ]);
        $pdf->setPaper('a4');

        return base64_encode($pdf->output());
    }

    public function generateXml(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'pointOfSale', 'patient', 'sale']);

        $pos = $invoice->pointOfSale;
        $patient = $invoice->patient;

        $codiceDestinatario = $invoice->sdi_identifier ?: (string) env('SDI_CODICE_DESTINATARIO', '0000000');
        $formatoTrasmissione = match ($invoice->type) {
            'fattura_pa' => 'FPA12',
            default      => 'FPR12',
        };

        $ns = 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2';
        $xsi = 'http://www.w3.org/2001/XMLSchema-instance';

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS($ns, 'p:FatturaElettronica');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $xsi);
        $root->setAttributeNS($xsi, 'xsi:schemaLocation', $ns . ' ' . $ns);

        $dom->appendChild($root);

        $header = $dom->createElementNS($ns, 'p:FatturaElettronicaHeader');
        $root->appendChild($header);

        // DatiTrasmissione
        $datiTrasmissione = $dom->createElementNS($ns, 'p:DatiTrasmissione');
        $header->appendChild($datiTrasmissione);

        $idTrasm = $dom->createElementNS($ns, 'p:IdTrasmittente');
        $datiTrasmissione->appendChild($idTrasm);
        $idPaese = $dom->createElementNS($ns, 'p:IdPaese', 'IT');
        $idTrasm->appendChild($idPaese);
        $idCodice = $dom->createElementNS($ns, 'p:IdCodice', (string) ($pos?->fiscal_code ?: '00000000000'));
        $idTrasm->appendChild($idCodice);

        $progressivoInvio = $dom->createElementNS($ns, 'p:ProgressivoInvio', (string) $invoice->id);
        $datiTrasmissione->appendChild($progressivoInvio);

        $formatoTras = $dom->createElementNS($ns, 'p:FormatoTrasmissione', $formatoTrasmissione);
        $datiTrasmissione->appendChild($formatoTras);

        $codDest = $dom->createElementNS($ns, 'p:CodiceDestinatario', $codiceDestinatario);
        $datiTrasmissione->appendChild($codDest);

        // CedentePrestatore
        $cedente = $dom->createElementNS($ns, 'p:CedentePrestatore');
        $header->appendChild($cedente);

        $cedDatiAnag = $dom->createElementNS($ns, 'p:DatiAnagrafici');
        $cedente->appendChild($cedDatiAnag);
        if ($pos?->fiscal_code) {
            $cedCF = $dom->createElementNS($ns, 'p:CodiceFiscale', (string) $pos->fiscal_code);
            $cedDatiAnag->appendChild($cedCF);
        }

        $cedNome = $dom->createElementNS($ns, 'p:Anagrafica', (string) ($pos?->name ?: ''));
        $cedDatiAnag->appendChild($cedNome);

        $cedIdFisc = $dom->createElementNS($ns, 'p:IdFiscaleIVA');
        $cedente->appendChild($cedIdFisc);
        $cedIdPaese = $dom->createElementNS($ns, 'p:IdPaese', 'IT');
        $cedIdFisc->appendChild($cedIdPaese);
        $cedIdCodice = $dom->createElementNS($ns, 'p:IdCodice', (string) ($pos?->vat_number ?: $pos?->fiscal_code ?: ''));
        $cedIdFisc->appendChild($cedIdCodice);

        // CessionarioCommittente
        $cessionario = $dom->createElementNS($ns, 'p:CessionarioCommittente');
        $header->appendChild($cessionario);

        $cesDatiAnag = $dom->createElementNS($ns, 'p:DatiAnagrafici');
        $cessionario->appendChild($cesDatiAnag);

        $cf = $patient?->fiscal_code ?? $invoice->customer_fiscal_code;
        $cesCF = $dom->createElementNS($ns, 'p:CodiceFiscale', (string) ($cf ?: ''));
        $cesDatiAnag->appendChild($cesCF);

        $cesNome = $dom->createElementNS($ns, 'p:Anagrafica', (string) ($invoice->customer_name ?: ''));
        $cesDatiAnag->appendChild($cesNome);

        if ($invoice->customer_vat_number) {
            $cesIdFisc = $dom->createElementNS($ns, 'p:IdFiscaleIVA');
            $cessionario->appendChild($cesIdFisc);
            $cesIdPaese = $dom->createElementNS($ns, 'p:IdPaese', 'IT');
            $cesIdFisc->appendChild($cesIdPaese);
            $cesIdCodice = $dom->createElementNS($ns, 'p:IdCodice', (string) $invoice->customer_vat_number);
            $cesIdFisc->appendChild($cesIdCodice);
        }

        // Body
        $body = $dom->createElementNS($ns, 'p:FatturaElettronicaBody');
        $root->appendChild($body);

        $datiGenerali = $dom->createElementNS($ns, 'p:DatiGenerali');
        $body->appendChild($datiGenerali);

        $datiDoc = $dom->createElementNS($ns, 'p:DatiGeneraliDocumento');
        $datiGenerali->appendChild($datiDoc);

        $tipoDocumento = match ($invoice->type) {
            'fattura_pa' => 'TD01',
            'ricevuta'   => 'TD01',
            default      => 'TD01',
        };
        $datiDoc->appendChild($dom->createElementNS($ns, 'p:TipoDocumento', $tipoDocumento));
        $datiDoc->appendChild($dom->createElementNS($ns, 'p:Divisa', 'EUR'));
        $datiDoc->appendChild($dom->createElementNS($ns, 'p:Data', $invoice->invoice_date?->format('Y-m-d') ?? now()->format('Y-m-d')));
        $datiDoc->appendChild($dom->createElementNS($ns, 'p:Numero', (string) $invoice->invoice_number));

        $datiDoc->appendChild($dom->createElementNS($ns, 'p:ImportoTotaleDocumento', (string) number_format((float) $invoice->total, 2, '.', '')));

        $datiBeniServizi = $dom->createElementNS($ns, 'p:DatiBeniServizi');
        $body->appendChild($datiBeniServizi);

        $datiBeniServizi->appendChild($dom->createElementNS($ns, 'p:DettaglioPagamento', ''));
        $detLinee = $dom->createElementNS($ns, 'p:DettaglioLinee');
        foreach ($invoice->items as $i => $item) {
            $line = $dom->createElementNS($ns, 'p:DettaglioLinee');
            $datiBeniServizi->appendChild($line);

            $line->appendChild($dom->createElementNS($ns, 'p:NumeroLinea', (string) ($i + 1)));
            $line->appendChild($dom->createElementNS($ns, 'p:Descrizione', (string) $item->description));
            $line->appendChild($dom->createElementNS($ns, 'p:Quantita', (string) number_format((float) $item->quantity, 3, '.', '')));
            $line->appendChild($dom->createElementNS($ns, 'p:PrezzoUnitario', (string) number_format((float) $item->unit_price, 2, '.', '')));
            $line->appendChild($dom->createElementNS($ns, 'p:PrezzoTotale', (string) number_format((float) $item->total, 2, '.', '')));
            $line->appendChild($dom->createElementNS($ns, 'p:AliquotaIVA', (string) number_format((float) $item->vat_rate, 2, '.', '')));
        }

        // Riepilogo IVA
        $group = [];
        foreach ($invoice->items as $item) {
            $rate = (string) $item->vat_rate;
            $group[$rate] = $group[$rate] ?? ['imponibile' => 0.0, 'imposta' => 0.0];
            $group[$rate]['imponibile'] += (float) $item->subtotal;
            $group[$rate]['imposta'] += (float) $item->vat_amount;
        }

        foreach ($group as $rate => $vals) {
            $r = $dom->createElementNS($ns, 'p:DatiRiepilogo');
            $datiBeniServizi->appendChild($r);
            $r->appendChild($dom->createElementNS($ns, 'p:AliquotaIVA', (string) number_format((float) $rate, 2, '.', '')));
            $r->appendChild($dom->createElementNS($ns, 'p:ImponibileImporto', (string) number_format($vals['imponibile'], 2, '.', '')));
            $r->appendChild($dom->createElementNS($ns, 'p:Imposta', (string) number_format($vals['imposta'], 2, '.', '')));
            $r->appendChild($dom->createElementNS($ns, 'p:EsigibilitaIVA', 'I'));
            $r->appendChild($dom->createElementNS($ns, 'p:Natura', 'N2.2'));
        }

        $datiPagamento = $dom->createElementNS($ns, 'p:DatiPagamento');
        $body->appendChild($datiPagamento);

        $datiPagamento->appendChild($dom->createElementNS($ns, 'p:ModalitaPagamento', (string) ($invoice->payment_method ?: 'MP01')));
        $datiPagamento->appendChild($dom->createElementNS($ns, 'p:DataScadenzaPagamento', $invoice->invoice_date?->format('Y-m-d') ?? now()->format('Y-m-d')));
        $datiPagamento->appendChild($dom->createElementNS($ns, 'p:ImportoPagamento', (string) number_format((float) $invoice->total, 2, '.', '')));

        return $dom->saveXML();
    }

    public function sendToSdi(Invoice $invoice): bool
    {
        Log::info('[SDI] sendToSdi stub', [
            'invoice_id' => $invoice->id,
            'invoice_no' => $invoice->invoice_number,
            'env'         => app()->environment(),
        ]);

        return true;
    }

    public function issueInvoice(Invoice $invoice): Invoice
    {
        $invoice->loadMissing(['sale']);
        if ($invoice->status !== 'issued') {
            $invoice->status = 'issued';
            $invoice->save();
        }

        if ($invoice->sale_id && $invoice->sale) {
            $rtType = match ($invoice->type) {
                'ricevuta'   => 'ricevuta',
                'fattura_pa' => 'fattura',
                default      => 'fattura',
            };
            $this->rtService->sendDocument($invoice->sale, $rtType);
        }

        return $invoice->fresh(['items', 'pointOfSale', 'patient', 'sale']);
    }

    public function sendSdiInvoice(Invoice $invoice): Invoice
    {
        $ok = $this->sendToSdi($invoice);
        if ($ok) {
            $invoice->status = 'sent_sdi';
            $invoice->sdi_sent_at = now();
            $invoice->save();
        }

        return $invoice->fresh(['items', 'pointOfSale', 'patient', 'sale']);
    }

    private function appendInvoiceItemFromSaleItem(Invoice $invoice, SaleItem $saleItem, float &$subtotal, float &$vatAmount, float &$total): void
    {
        $lineSub = (float) $saleItem->total;
        $vatRate = (float) $saleItem->vat_rate;
        $lineVat = $lineSub * $vatRate / 100;
        $lineTotal = $lineSub + $lineVat;

        $invoice->items()->create([
            'description'      => (string) $saleItem->description,
            'quantity'         => (float) $saleItem->quantity,
            'unit_price'       => (float) $saleItem->unit_price,
            'discount_percent' => (float) ($saleItem->discount_percent ?? 0),
            'subtotal'         => round($lineSub, 2),
            'vat_rate'         => (float) $vatRate,
            'vat_amount'       => round($lineVat, 2),
            'total'            => round($lineTotal, 2),
            'sts_code'         => $saleItem->sts_code,
        ]);

        $subtotal += $lineSub;
        $vatAmount += $lineVat;
        $total += $lineTotal;
    }
}

