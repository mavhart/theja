<?php

namespace App\Http\Controllers;

use App\Http\Resources\InventoryItemResource;
use App\Http\Resources\StockMovementResource;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'pos_id'     => ['nullable', 'uuid'],
            'product_id' => ['nullable', 'uuid'],
        ]);

        if (! $request->filled('pos_id') && ! $request->filled('product_id')) {
            abort(422, 'Specificare almeno pos_id o product_id.');
        }

        $query = InventoryItem::query()
            ->with(['product', 'pointOfSale'])
            ->orderByDesc('updated_at');
        if ($request->filled('pos_id')) {
            $query->where('pos_id', $request->input('pos_id'));
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        return InventoryItemResource::collection($query->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function updateStock(Request $request): InventoryItemResource
    {
        $data = $request->validate([
            'pos_id'       => ['required', 'uuid'],
            'product_id'   => ['required', 'uuid', 'exists:products,id'],
            'quantity'     => ['required', 'integer'],
            'reference'    => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string'],
        ]);

        Product::where('id', $data['product_id'])->firstOrFail();

        $item = InventoryItem::firstOrCreate(
            ['pos_id' => $data['pos_id'], 'product_id' => $data['product_id']],
            ['quantity' => 0]
        );

        $before = (int) $item->quantity;
        $after = (int) $data['quantity'];
        $delta = $after - $before;

        $item->quantity = $after;
        $item->save();

        StockMovement::create([
            'pos_id'          => $data['pos_id'],
            'product_id'      => $data['product_id'],
            'user_id'         => $request->user()->id,
            'type'            => 'rettifica',
            'quantity'        => $delta,
            'quantity_before' => $before,
            'quantity_after'  => $after,
            'reference'       => $data['reference'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'created_at'      => now(),
        ]);

        return new InventoryItemResource($item->fresh()->load('product'));
    }

    public function movements(Request $request): AnonymousResourceCollection
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
}
