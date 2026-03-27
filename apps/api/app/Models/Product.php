<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_polarized'   => 'boolean',
            'is_ce'          => 'boolean',
            'attributes'     => 'array',
            'purchase_price' => 'encrypted',
            'markup_percent' => 'decimal:2',
            'net_price'      => 'decimal:2',
            'list_price'     => 'decimal:2',
            'sale_price'     => 'decimal:2',
            'vat_rate'       => 'decimal:2',
            'inserted_at'    => 'date',
            'date_start'     => 'date',
            'date_end'       => 'date',
            'is_active'      => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function lacSupplySchedules(): HasMany
    {
        return $this->hasMany(LacSupplySchedule::class);
    }

    public function scopeMontature($query)
    {
        return $query->where('category', 'montatura');
    }

    public function scopeLentiOftalmiche($query)
    {
        return $query->where('category', 'lente_oftalmica');
    }

    public function scopeLentiContatto($query)
    {
        return $query->where('category', 'lente_contatto');
    }

    public function scopeLiquidiAccessori($query)
    {
        return $query->where('category', 'liquido_accessorio');
    }
}
