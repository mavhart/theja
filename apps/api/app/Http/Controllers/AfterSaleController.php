<?php

namespace App\Http\Controllers;

use App\Http\Resources\AfterSaleEventResource;
use App\Models\AfterSaleEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AfterSaleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'sale_id' => ['required', 'uuid', 'exists:sales,id'],
        ]);

        $q = AfterSaleEvent::query()
            ->where('sale_id', $request->string('sale_id'))
            ->orderByDesc('opened_at');

        return AfterSaleEventResource::collection($q->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function store(Request $request): AfterSaleEventResource
    {
        $data = $request->validate([
            'sale_id' => ['required', 'uuid', 'exists:sales,id'],
            'sale_item_id' => ['nullable', 'uuid', 'exists:sale_items,id'],
            'type' => ['required', 'in:riparazione,garanzia,reso,adattamento,altro'],
            'description' => ['required', 'string'],
            'status' => ['nullable', 'in:aperto,inviato_lab,rientrato,chiuso'],
            'opened_at' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date'],
            'cost' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        $event = AfterSaleEvent::create([
            ...$data,
            'status' => $data['status'] ?? 'aperto',
            'opened_at' => $data['opened_at'] ?? now(),
        ]);

        return new AfterSaleEventResource($event);
    }

    public function updateStatus(Request $request, AfterSaleEvent $afterSaleEvent): AfterSaleEventResource
    {
        $data = $request->validate([
            'status' => ['required', 'in:aperto,inviato_lab,rientrato,chiuso'],
            'notes' => ['nullable', 'string'],
            'cost' => ['nullable', 'numeric'],
            'closed_at' => ['nullable', 'date'],
        ]);

        if ($data['status'] === 'chiuso' && empty($data['closed_at'])) {
            $data['closed_at'] = now();
        }

        $afterSaleEvent->update($data);

        return new AfterSaleEventResource($afterSaleEvent->fresh());
    }
}