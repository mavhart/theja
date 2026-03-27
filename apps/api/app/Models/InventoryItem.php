<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity'          => 'integer',
            'quantity_arriving' => 'integer',
            'quantity_reserved' => 'integer',
            'quantity_sold'     => 'integer',
            'min_stock'         => 'integer',
            'max_stock'         => 'integer',
            'last_purchase_date'=> 'date',
            'last_sale_date'    => 'date',
        ];
    }

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'pos_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
