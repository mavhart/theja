<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function createSale(array $data, array $items): Sale
    {
        return DB::transaction(function () use ($data, $items) {
            $sale = Sale::create($data);

            $total = 0.0;
            foreach ($items as $item) {
                $qty = (int) ($item['quantity'] ?? 1);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $discountPercent = (float) ($item['discount_percent'] ?? 0);
                $discountAmount = (float) ($item['discount_amount'] ?? 0);
                $lineSub = $qty * $unitPrice;
                $lineTotal = max(0, $lineSub - ($lineSub * $discountPercent / 100) - $discountAmount);

                $row = SaleItem::create([
                    ...$item,
                    'sale_id' => $sale->id,
                    'quantity' => $qty,
                    'total' => round($lineTotal, 2),
                ]);

                $total += $lineTotal;

                if (! empty($row->product_id)) {
                    $inv = InventoryItem::firstOrCreate(
                        ['pos_id' => $sale->pos_id, 'product_id' => $row->product_id],
                        ['quantity' => 0]
                    );
                    $before = (int) $inv->quantity;
                    $after = $before - $qty;
                    $inv->quantity = $after;
                    $inv->last_sale_date = now()->format('Y-m-d');
                    $inv->save();

                    StockMovement::create([
                        'pos_id' => $sale->pos_id,
                        'product_id' => $row->product_id,
                        'user_id' => $sale->user_id,
                        'type' => 'vendita',
                        'quantity' => -$qty,
                        'quantity_before' => $before,
                        'quantity_after' => $after,
                        'reference' => 'sale:'.$sale->id,
                        'sale_price' => $row->unit_price,
                        'notes' => $row->description,
                        'created_at' => now(),
                    ]);
                }
            }

            $sale->discount_amount = (float) ($sale->discount_amount ?? 0);
            $sale->total_amount = round(max(0, $total - (float) $sale->discount_amount), 2);
            $sale->save();

            return $sale->fresh(['items', 'payments']);
        });
    }

    public function addPayment(Sale $sale, array $data): Payment
    {
        return DB::transaction(function () use ($sale, $data) {
            $payment = Payment::create([
                ...$data,
                'sale_id' => $sale->id,
                'is_scheduled' => (bool) ($data['is_scheduled'] ?? false),
                'paid_at' => Arr::get($data, 'is_scheduled') ? null : now(),
            ]);

            if (! $payment->is_scheduled) {
                $sale->paid_amount = round((float) $sale->paid_amount + (float) $payment->amount, 2);
                if ((float) $sale->paid_amount >= (float) $sale->total_amount && $sale->status === 'quote') {
                    $sale->status = 'confirmed';
                }
                $sale->save();
            }

            return $payment;
        });
    }

    public function schedulePayments(Sale $sale, array $schedule): array
    {
        $created = [];
        foreach ($schedule as $line) {
            $created[] = $this->addPayment($sale, [
                'amount' => $line['amount'],
                'method' => $line['method'] ?? 'altro',
                'payment_date' => $line['scheduled_date'] ?? now()->format('Y-m-d'),
                'is_scheduled' => true,
                'scheduled_date' => $line['scheduled_date'] ?? null,
                'notes' => $line['notes'] ?? null,
            ]);
        }

        return $created;
    }

    public function deliverSale(Sale $sale): Sale
    {
        $sale->status = 'delivered';
        $sale->delivery_date = now()->format('Y-m-d');
        $sale->save();

        $order = $sale->order()->latest('created_at')->first();
        if ($order && $order->status !== 'delivered') {
            $order->status = 'delivered';
            $order->actual_delivery_date = now()->format('Y-m-d');
            $order->save();
        }

        return $sale->fresh(['items', 'payments']);
    }

    public function cancelSale(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            if ($sale->status === 'cancelled') {
                return $sale;
            }

            foreach ($sale->items as $item) {
                if (! $item->product_id) {
                    continue;
                }
                $qty = (int) $item->quantity;
                $inv = InventoryItem::firstOrCreate(
                    ['pos_id' => $sale->pos_id, 'product_id' => $item->product_id],
                    ['quantity' => 0]
                );
                $before = (int) $inv->quantity;
                $after = $before + $qty;
                $inv->quantity = $after;
                $inv->save();

                StockMovement::create([
                    'pos_id' => $sale->pos_id,
                    'product_id' => $item->product_id,
                    'user_id' => $sale->user_id,
                    'type' => 'reso',
                    'quantity' => $qty,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'reference' => 'sale_cancel:'.$sale->id,
                    'created_at' => now(),
                ]);
            }

            $sale->status = 'cancelled';
            $sale->save();

            return $sale->fresh(['items', 'payments']);
        });
    }
}