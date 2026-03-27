<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockMovementResource;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockMovementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'pos_id'     => ['nullable', 'uuid'],
            'product_id' => ['nullable', 'uuid'],
        ]);

        $query = StockMovement::query()->orderByDesc('created_at');
        if ($request->filled('pos_id')) {
            $query->where('pos_id', $request->input('pos_id'));
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        return StockMovementResource::collection($query->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function store(Request $request): StockMovementResource
    {
        $data = $request->validate([
            'pos_id'         => ['required', 'uuid'],
            'product_id'     => ['required', 'uuid', 'exists:products,id'],
            'type'           => ['required', 'string', 'in:carico,scarico,rettifica,trasferimento_in,trasferimento_out,vendita,reso'],
            'quantity'       => ['required', 'integer', 'min:1'],
            'ddt_number'     => ['nullable', 'string', 'max:64'],
            'ddt_date'       => ['nullable', 'date'],
            'supplier_id'    => ['nullable', 'uuid', 'exists:suppliers,id'],
            'reference'      => ['nullable', 'string', 'max:255'],
            'lot'            => ['nullable', 'string', 'max:255'],
            'expiry_date'    => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric'],
            'sale_price'     => ['nullable', 'numeric'],
            'notes'          => ['nullable', 'string'],
        ]);

        Product::where('id', $data['product_id'])->firstOrFail();

        $item = InventoryItem::firstOrCreate(
            ['pos_id' => $data['pos_id'], 'product_id' => $data['product_id']],
            ['quantity' => 0]
        );

        $before = (int) $item->quantity;
        $signedQty = in_array($data['type'], ['carico', 'trasferimento_in', 'reso'], true)
            ? (int) $data['quantity']
            : -1 * (int) $data['quantity'];
        $after = $before + $signedQty;

        $item->quantity = $after;
        if ($signedQty > 0) {
            $item->last_purchase_date = now()->format('Y-m-d');
        } else {
            $item->last_sale_date = now()->format('Y-m-d');
        }
        $item->save();

        $movement = StockMovement::create([
            'pos_id'          => $data['pos_id'],
            'product_id'      => $data['product_id'],
            'user_id'         => $request->user()->id,
            'type'            => $data['type'],
            'quantity'        => $signedQty,
            'quantity_before' => $before,
            'quantity_after'  => $after,
            'ddt_number'      => $data['ddt_number'] ?? null,
            'ddt_date'        => $data['ddt_date'] ?? null,
            'supplier_id'     => $data['supplier_id'] ?? null,
            'reference'       => $data['reference'] ?? null,
            'lot'             => $data['lot'] ?? null,
            'expiry_date'     => $data['expiry_date'] ?? null,
            'purchase_price'  => $data['purchase_price'] ?? null,
            'sale_price'      => $data['sale_price'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'created_at'      => now(),
        ]);

        return new StockMovementResource($movement);
    }
}
