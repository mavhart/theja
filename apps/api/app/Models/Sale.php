<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $appends = ['remaining_amount', 'is_fully_paid', 'status_label'];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'delivery_date' => 'date',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'pos_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function order(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function afterSaleEvents(): HasMany
    {
        return $this->hasMany(AfterSaleEvent::class);
    }

    public function getRemainingAmountAttribute(): string
    {
        return number_format((float) $this->total_amount - (float) $this->paid_amount, 2, '.', '');
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return ((float) $this->paid_amount + 0.0001) >= (float) $this->total_amount;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'quote' => 'Preventivo',
            'confirmed' => 'Confermata',
            'delivered' => 'Consegnata',
            'cancelled' => 'Annullata',
            default => ucfirst((string) $this->status),
        };
    }
}