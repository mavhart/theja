<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'actual_delivery_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'pos_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function labSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'lab_supplier_id');
    }

    public function rightLensProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'lens_right_product_id');
    }

    public function leftLensProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'lens_left_product_id');
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}