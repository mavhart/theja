<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Sale;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function index(Request $request, InvoiceService $service): AnonymousResourceCollection
    {
        $request->validate([
            'status'     => ['nullable', 'string', 'in:draft,issued,sent_sdi,accepted,rejected,cancelled'],
            'patient_id' => ['nullable', 'uuid'],
            'date_from'  => ['nullable', 'date'],
            'date_to'    => ['nullable', 'date'],
        ]);

        $q = Invoice::query()
            ->with(['patient'])
            ->orderByDesc('invoice_date');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('patient_id')) {
            $q->where('patient_id', $request->string('patient_id'));
        }
        if ($request->filled('date_from')) {
            $q->whereDate('invoice_date', '>=', $request->string('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->whereDate('invoice_date', '<=', $request->string('date_to'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        return InvoiceResource::collection($q->paginate($perPage));
    }

    public function store(Request $request, InvoiceService $service): InvoiceResource
    {
        $data = $request->validate([
            'sale_id' => ['nullable', 'uuid', 'exists:sales,id'],

            'pos_id' => ['required_without:sale_id', 'uuid', 'exists:points_of_sale,id'],
            'patient_id' => ['required_without:sale_id', 'uuid', 'exists:patients,id'],

            'invoice_date' => ['nullable', 'date'],
            'type' => ['nullable', 'in:fattura,ricevuta,fattura_pa'],
            'payment_method' => ['nullable', 'string', 'max:64'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],

            'items' => ['required_without:sale_id', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.vat_rate' => ['required', 'numeric', 'min:0'],
            'items.*.sts_code' => ['nullable', 'string', 'max:64'],
        ]);

        if (! empty($data['sale_id'])) {
            $sale = Sale::query()->with(['items', 'pointOfSale', 'patient'])->findOrFail($data['sale_id']);
            $invoice = $service->createFromSale($sale);
            $invoice = $service->applyOverrides($invoice, $data);
        } else {
            $invoice = $service->createManual($data, $data['items'] ?? []);
        }

        return (new InvoiceResource($invoice->loadMissing(['items', 'pointOfSale', 'patient'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load(['items', 'pointOfSale', 'patient', 'sale']));
    }

    public function update(Request $request, Invoice $invoice): InvoiceResource
    {
        $data = $request->validate([
            'invoice_date' => ['sometimes', 'date'],
            'type' => ['sometimes', 'in:fattura,ricevuta,fattura_pa'],
            'status' => ['sometimes', 'in:draft,issued,sent_sdi,accepted,rejected,cancelled'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:64'],
            'payment_terms' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'sdi_identifier' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $invoice->update($data);

        return new InvoiceResource($invoice->fresh(['items', 'pointOfSale', 'patient', 'sale']));
    }

    public function issue(Invoice $invoice, InvoiceService $service): InvoiceResource
    {
        $updated = $service->issueInvoice($invoice);
        return new InvoiceResource($updated->load(['items', 'pointOfSale', 'patient', 'sale']));
    }

    public function sendSdi(Invoice $invoice, InvoiceService $service): InvoiceResource
    {
        $updated = $service->sendSdiInvoice($invoice);
        return new InvoiceResource($updated->load(['items', 'pointOfSale', 'patient', 'sale']));
    }

    public function pdf(Invoice $invoice, InvoiceService $service): JsonResponse
    {
        $b64 = $service->generatePdf($invoice);

        return response()->json([
            'filename'   => 'fattura_' . $invoice->invoice_number . '.pdf',
            'pdf_base64' => $b64,
        ]);
    }

    public function xml(Invoice $invoice, InvoiceService $service): JsonResponse
    {
        $xml = $service->generateXml($invoice);

        return response()->json([
            'filename' => 'fattura_' . $invoice->invoice_number . '.xml',
            'xml'       => $xml,
        ]);
    }
}

