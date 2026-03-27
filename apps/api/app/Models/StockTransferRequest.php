<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferRequest extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity'      => 'integer',
            'requested_at'  => 'datetime',
            'resolved_at'   => 'datetime',
            'completed_at'  => 'datetime',
        ];
    }

    public function fromPos(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'from_pos_id');
    }

    public function toPos(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'to_pos_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
