<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PointOfSale;
use App\Services\CommunicationService;

class OrderService
{
    public function __construct(private readonly CommunicationService $communicationService)
    {
    }

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
            $order->loadMissing('patient');
            if ($order->patient) {
                // Notifica opzionale via modulo comunicazioni (trigger: order_ready)
                // Tipo sms/email deriva dai template disponibili.
                $this->communicationService->send('sms', $order->patient, 'order_ready', [
                    'paziente_nome' => trim(($order->patient->first_name ?? '').' '.($order->patient->last_name ?? '')),
                    'data' => now()->format('d/m/Y'),
                ]);
            }
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