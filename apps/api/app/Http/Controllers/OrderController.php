<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'string'],
            'lab_supplier_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $q = Order::query()->with(['patient', 'sale', 'labSupplier'])->orderByDesc('order_date');
        if ($request->filled('status')) $q->where('status', $request->string('status'));
        if ($request->filled('lab_supplier_id')) $q->where('lab_supplier_id', $request->string('lab_supplier_id'));
        if ($request->filled('date_from')) $q->whereDate('order_date', '>=', $request->string('date_from'));
        if ($request->filled('date_to')) $q->whereDate('order_date', '<=', $request->string('date_to'));

        return OrderResource::collection($q->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function store(Request $request, OrderService $service): OrderResource
    {
        $data = $request->validate([
            'pos_id' => ['required', 'uuid'],
            'sale_id' => ['nullable', 'uuid', 'exists:sales,id'],
            'patient_id' => ['nullable', 'uuid', 'exists:patients,id'],
            'lab_supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'status' => ['nullable', 'in:draft,sent,in_progress,ready,delivered,cancelled'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'actual_delivery_date' => ['nullable', 'date'],
            'job_code' => ['nullable', 'string', 'max:64'],
            'frame_barcode' => ['nullable', 'string', 'max:128'],
            'frame_description' => ['nullable', 'string', 'max:255'],
            'lens_right_product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'lens_left_product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'lens_right_description' => ['nullable', 'string', 'max:255'],
            'lens_left_description' => ['nullable', 'string', 'max:255'],
            'prescription_id' => ['nullable', 'uuid', 'exists:prescriptions,id'],
            'mounting_type' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'total_amount' => ['nullable', 'numeric'],
        ]);

        $order = $service->createOrder([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => $data['status'] ?? 'draft',
        ]);

        return new OrderResource($order->load(['patient', 'sale', 'labSupplier']));
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['patient', 'sale', 'labSupplier', 'rightLensProduct', 'leftLensProduct', 'prescription']));
    }

    public function updateStatus(Request $request, Order $order, OrderService $service): OrderResource
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,sent,in_progress,ready,delivered,cancelled'],
        ]);

        return new OrderResource($service->updateStatus($order, $data['status']));
    }

    public function pending(): JsonResponse
    {
        $all = Order::query()->whereIn('status', ['sent', 'in_progress', 'ready'])->get();

        return response()->json([
            'data' => [
                'sent' => $all->where('status', 'sent')->count(),
                'in_progress' => $all->where('status', 'in_progress')->count(),
                'ready' => $all->where('status', 'ready')->count(),
                'rows' => $all->values(),
            ],
        ]);
    }
}