<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $appends = ['duration_label'];

    protected function casts(): array
    {
        return [
            'start_at'         => 'datetime',
            'end_at'           => 'datetime',
            'duration_minutes' => 'integer',
            'reminder_sent_at' => 'datetime',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_at', '>=', now())->whereIn('status', ['scheduled', 'confirmed']);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('start_at', now()->toDateString());
    }

    public function scopeByType(Builder $query, ?string $type): Builder
    {
        if (! $type) {
            return $query;
        }

        return $query->where('type', $type);
    }

    public function getDurationLabelAttribute(): string
    {
        $m = (int) ($this->duration_minutes ?? 0);
        if ($m < 60) {
            return $m.' min';
        }

        $hours = intdiv($m, 60);
        $minutes = $m % 60;

        return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
    }
}

