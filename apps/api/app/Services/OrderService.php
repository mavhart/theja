<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PointOfSale;

class OrderService
{
    public function createOrder(array $data): Order
    {
        if (empty($data['job_code']) && ! empty($data['pos_id'])) {
            $pos = PointOfSale::findOrFail($data['pos_id']);
            $data['job_code'] = $this->generateJobCode($pos);
        }

        return Order::create($data);
    }

    public function updateStatus(Order $order, string $status): Order
    {
        $order->status = $status;
        if ($status === 'ready') {
            $order->actual_delivery_date = null;
            // Placeholder notifica paziente (Fase 6 comunicazioni automatiche)
        }
        if ($status === 'delivered') {
            $order->actual_delivery_date = now()->format('Y-m-d');
        }
        $order->save();

        return $order->fresh();
    }

    public function generateJobCode(PointOfSale $pos): string
    {
        $prefix = now()->format('ym');
        $count = Order::where('pos_id', $pos->id)
            ->where('job_code', 'like', $prefix.'-%')
            ->count() + 1;

        return $prefix.'-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}