<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LacSupplySchedule extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'supply_date'              => 'date',
            'quantity'                 => 'integer',
            'estimated_duration_days'  => 'integer',
            'estimated_end_date'       => 'date',
            'reminder_sent_at'         => 'datetime',
            'created_at'               => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'pos_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function calculateEndDate(): string
    {
        return $this->supply_date
            ->copy()
            ->addDays((int) $this->estimated_duration_days)
            ->format('Y-m-d');
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        $start = now()->startOfDay()->format('Y-m-d');
        $end = now()->addDays($days)->endOfDay()->format('Y-m-d');

        return $query->whereBetween('estimated_end_date', [$start, $end]);
    }
}
