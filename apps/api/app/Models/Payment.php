<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Payment extends Model
{
    use HasUuids;

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'is_scheduled' => 'boolean',
            'scheduled_date' => 'date',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('is_scheduled', true);
    }

    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_scheduled', false);
    }
}