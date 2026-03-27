<?php

namespace App\Http\Controllers;

use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'string'],
            'patient_id' => ['nullable', 'uuid'],
            'type' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $q = Sale::query()->with(['items', 'payments', 'patient', 'user'])->orderByDesc('sale_date');
        if ($request->filled('status')) $q->where('status', $request->string('status'));
        if ($request->filled('patient_id')) $q->where('patient_id', $request->string('patient_id'));
        if ($request->filled('type')) $q->where('type', $request->string('type'));
        if ($request->filled('date_from')) $q->whereDate('sale_date', '>=', $request->string('date_from'));
        if ($request->filled('date_to')) $q->whereDate('sale_date', '<=', $request->string('date_to'));

        return SaleResource::collection($q->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function store(Request $request, SaleService $service): SaleResource
    {
        $data = $request->validate([
            'pos_id' => ['required', 'uuid'],
            'patient_id' => ['nullable', 'uuid', 'exists:patients,id'],
            'status' => ['nullable', 'in:quote,confirmed,delivered,cancelled'],
            'type' => ['required', 'in:occhiale_vista,occhiale_sole,sostituzione_lenti,sostituzione_montatura,lac,accessorio,servizio,generico'],
            'sale_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'discount_amount' => ['nullable', 'numeric'],
            'prescription_id' => ['nullable', 'uuid', 'exists:prescriptions,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric'],
            'items.*.purchase_price' => ['nullable', 'numeric'],
            'items.*.discount_percent' => ['nullable', 'numeric'],
            'items.*.discount_amount' => ['nullable', 'numeric'],
            'items.*.vat_rate' => ['nullable', 'numeric'],
            'items.*.vat_code' => ['nullable', 'string', 'max:32'],
            'items.*.sts_code' => ['nullable', 'string', 'max:64'],
            'items.*.lot' => ['nullable', 'string', 'max:128'],
            'items.*.item_type' => ['nullable', 'in:montatura,lente_dx,lente_sx,lente_contatto,accessorio,servizio,altro'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $sale = $service->createSale([
            'pos_id' => $data['pos_id'],
            'patient_id' => $data['patient_id'] ?? null,
            'user_id' => $request->user()->id,
            'status' => $data['status'] ?? 'quote',
            'type' => $data['type'],
            'sale_date' => $data['sale_date'],
            'delivery_date' => $data['delivery_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'prescription_id' => $data['prescription_id'] ?? null,
        ], $data['items']);

        return new SaleResource($sale->load(['items', 'payments', 'patient', 'user', 'prescription']));
    }

    public function show(Sale $sale): SaleResource
    {
        return new SaleResource($sale->load(['items.product', 'payments', 'patient', 'user', 'order', 'afterSaleEvents', 'prescription']));
    }

    public function update(Request $request, Sale $sale): SaleResource
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:quote,confirmed,delivered,cancelled'],
            'type' => ['sometimes', 'in:occhiale_vista,occhiale_sole,sostituzione_lenti,sostituzione_montatura,lac,accessorio,servizio,generico'],
            'sale_date' => ['sometimes', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'discount_amount' => ['nullable', 'numeric'],
            'total_amount' => ['nullable', 'numeric'],
            'paid_amount' => ['nullable', 'numeric'],
            'prescription_id' => ['nullable', 'uuid', 'exists:prescriptions,id'],
        ]);

        $sale->update($data);

        return new SaleResource($sale->fresh()->load(['items', 'payments', 'patient', 'user', 'prescription']));
    }

    public function destroy(Sale $sale): JsonResponse
    {
        $sale->delete();

        return response()->json(['message' => 'Vendita eliminata.']);
    }

    public function addPayment(Request $request, Sale $sale, SaleService $service): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:contanti,carta,bonifico,assegno,altro'],
            'payment_date' => ['required', 'date'],
            'is_scheduled' => ['nullable', 'boolean'],
            'scheduled_date' => ['nullable', 'date'],
            'receipt_number' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $service->addPayment($sale, $data);

        return response()->json(['data' => $payment]);
    }

    public function deliver(Sale $sale, SaleService $service): SaleResource
    {
        return new SaleResource($service->deliverSale($sale));
    }

    public function cancel(Sale $sale, SaleService $service): SaleResource
    {
        return new SaleResource($service->cancelSale($sale));
    }

    public function paymentSummary(Sale $sale): JsonResponse
    {
        $sale->load('payments');

        return response()->json([
            'data' => [
                'sale_id' => $sale->id,
                'total_amount' => $sale->total_amount,
                'paid_amount' => $sale->paid_amount,
                'remaining_amount' => $sale->remaining_amount,
                'is_fully_paid' => $sale->is_fully_paid,
                'payments' => $sale->payments,
            ],
        ]);
    }

    public function schedulePayments(Request $request, Sale $sale, SaleService $service): JsonResponse
    {
        $data = $request->validate([
            'schedule' => ['required', 'array', 'min:1'],
            'schedule.*.amount' => ['required', 'numeric', 'min:0.01'],
            'schedule.*.scheduled_date' => ['required', 'date'],
            'schedule.*.method' => ['nullable', 'in:contanti,carta,bonifico,assegno,altro'],
            'schedule.*.notes' => ['nullable', 'string'],
        ]);

        $payments = $service->schedulePayments($sale, $data['schedule']);

        return response()->json(['data' => $payments]);
    }
}