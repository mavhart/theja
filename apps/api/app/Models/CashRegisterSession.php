<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegisterSession extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $appends = ['total_sales_amount'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'closing_amount' => 'decimal:2',
            'total_sales' => 'decimal:2',
            'total_cash' => 'decimal:2',
            'total_card' => 'decimal:2',
            'total_other' => 'decimal:2',
        ];
    }

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'pos_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fiscalReceipts(): HasMany
    {
        return $this->hasMany(FiscalReceipt::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function getTotalSalesAttribute(): string
    {
        return number_format((float) $this->attributes['total_sales'] ?? 0, 2, '.', '');
    }

    public function getTotalSalesAmountAttribute(): string
    {
        return number_format((float) $this->total_sales, 2, '.', '');
    }
}

