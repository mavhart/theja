<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionAddOn extends Model
{
    use HasUuids;

    protected $table = 'subscription_add_ons';

    protected $fillable = [
        'organization_id',
        'pos_id',
        'feature_key',
        'quantity',
        'stripe_item_id',
        'unit_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quantity'   => 'integer',
            'unit_price' => 'decimal:2',
            'is_active'  => 'boolean',
        ];
    }

    // ─── Relazioni ────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'pos_id');
    }
}
