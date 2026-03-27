<?php

namespace App\Events;

use App\Models\StockTransferRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PosTransferUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $posId,
        public StockTransferRequest $transfer
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("pos.{$this->posId}");
    }

    public function broadcastAs(): string
    {
        return 'StockTransferUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'transfer_id' => $this->transfer->id,
            'status'      => $this->transfer->status,
            'from_pos_id' => $this->transfer->from_pos_id,
            'to_pos_id'   => $this->transfer->to_pos_id,
        ];
    }
}
