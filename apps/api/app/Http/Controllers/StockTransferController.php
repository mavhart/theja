<?php

namespace App\Http\Controllers;

use App\Events\PosTransferUpdated;
use App\Http\Resources\StockTransferRequestResource;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransferRequest;
use App\Services\DdtService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockTransferController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'pos_id' => ['nullable', 'uuid'],
        ]);

        $query = StockTransferRequest::query()->orderByDesc('requested_at');
        if ($request->filled('pos_id')) {
            $pos = (string) $request->input('pos_id');
            $query->where(function ($q) use ($pos) {
                $q->where('from_pos_id', $pos)->orWhere('to_pos_id', $pos);
            });
        }

        return StockTransferRequestResource::collection($query->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function requestTransfer(Request $request): StockTransferRequestResource
    {
        $data = $request->validate([
            'from_pos_id' => ['required', 'uuid'],
            'to_pos_id'   => ['required', 'uuid', 'different:from_pos_id'],
            'product_id'  => ['required', 'uuid', 'exists:products,id'],
            'quantity'    => ['required', 'integer', 'min:1'],
        ]);

        Product::where('id', $data['product_id'])->firstOrFail();

        $transfer = StockTransferRequest::create([
            ...$data,
            'requested_by_user_id' => $request->user()->id,
            'status'               => 'requested',
            'requested_at'         => now(),
        ]);

        broadcast(new PosTransferUpdated($transfer->to_pos_id, $transfer));

        return new StockTransferRequestResource($transfer);
    }

    public function accept(Request $request, StockTransferRequest $transfer, DdtService $ddtService): StockTransferRequestResource
    {
        if ($transfer->status !== 'requested') {
            abort(422, 'Lo stato del trasferimento non consente accettazione.');
        }

        $fromItem = InventoryItem::firstOrCreate(
            ['pos_id' => $transfer->from_pos_id, 'product_id' => $transfer->product_id],
            ['quantity' => 0]
        );
        if ($fromItem->quantity < $transfer->quantity) {
            abort(422, 'Quantità insufficiente nel POS mittente.');
        }

        $fromBefore = (int) $fromItem->quantity;
        $fromAfter = $fromBefore - (int) $transfer->quantity;
        $fromItem->quantity = $fromAfter;
        $fromItem->quantity_reserved = (int) $fromItem->quantity_reserved + (int) $transfer->quantity;
        $fromItem->save();

        StockMovement::create([
            'pos_id'          => $transfer->from_pos_id,
            'product_id'      => $transfer->product_id,
            'user_id'         => $request->user()->id,
            'type'            => 'trasferimento_out',
            'quantity'        => -1 * (int) $transfer->quantity,
            'quantity_before' => $fromBefore,
            'quantity_after'  => $fromAfter,
            'notes'           => 'Accettazione trasferimento '.$transfer->id,
            'created_at'      => now(),
        ]);

        $transfer->status = 'accepted';
        $transfer->resolved_at = now();
        $transfer->save();

        $ddtService->generate($transfer);

        broadcast(new PosTransferUpdated($transfer->from_pos_id, $transfer));
        broadcast(new PosTransferUpdated($transfer->to_pos_id, $transfer));

        return new StockTransferRequestResource($transfer->fresh());
    }

    public function reject(Request $request, StockTransferRequest $transfer): StockTransferRequestResource
    {
        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string'],
        ]);

        if ($transfer->status !== 'requested') {
            abort(422, 'Lo stato del trasferimento non consente rifiuto.');
        }

        $transfer->status = 'rejected';
        $transfer->rejection_reason = $data['rejection_reason'] ?? null;
        $transfer->resolved_at = now();
        $transfer->save();

        broadcast(new PosTransferUpdated($transfer->from_pos_id, $transfer));
        broadcast(new PosTransferUpdated($transfer->to_pos_id, $transfer));

        return new StockTransferRequestResource($transfer);
    }

    public function complete(Request $request, StockTransferRequest $transfer): StockTransferRequestResource
    {
        if (! in_array($transfer->status, ['accepted', 'in_transit'], true)) {
            abort(422, 'Lo stato del trasferimento non consente completamento.');
        }

        $toItem = InventoryItem::firstOrCreate(
            ['pos_id' => $transfer->to_pos_id, 'product_id' => $transfer->product_id],
            ['quantity' => 0]
        );
        $toBefore = (int) $toItem->quantity;
        $toAfter = $toBefore + (int) $transfer->quantity;
        $toItem->quantity = $toAfter;
        $toItem->save();

        $fromItem = InventoryItem::firstOrCreate(
            ['pos_id' => $transfer->from_pos_id, 'product_id' => $transfer->product_id],
            ['quantity' => 0]
        );
        $fromItem->quantity_reserved = max(0, (int) $fromItem->quantity_reserved - (int) $transfer->quantity);
        $fromItem->save();

        StockMovement::create([
            'pos_id'          => $transfer->to_pos_id,
            'product_id'      => $transfer->product_id,
            'user_id'         => $request->user()->id,
            'type'            => 'trasferimento_in',
            'quantity'        => (int) $transfer->quantity,
            'quantity_before' => $toBefore,
            'quantity_after'  => $toAfter,
            'ddt_number'      => $transfer->ddt_number,
            'notes'           => 'Completamento trasferimento '.$transfer->id,
            'created_at'      => now(),
        ]);

        $transfer->status = 'completed';
        $transfer->completed_at = now();
        $transfer->save();

        broadcast(new PosTransferUpdated($transfer->from_pos_id, $transfer));
        broadcast(new PosTransferUpdated($transfer->to_pos_id, $transfer));

        return new StockTransferRequestResource($transfer);
    }
}
