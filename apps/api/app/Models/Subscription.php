<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'status',
        'plan_base_pos_count',
        'monthly_total',
        'trial_ends_at',
        'current_period_end',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'plan_base_pos_count' => 'integer',
            'monthly_total'       => 'decimal:2',
            'trial_ends_at'       => 'datetime',
            'current_period_end'  => 'datetime',
            'cancelled_at'        => 'datetime',
        ];
    }

    // ─── Relazioni ────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function addOns(): HasMany
    {
        return $this->hasMany(SubscriptionAddOn::class, 'organization_id', 'organization_id')
            ->where('is_active', true);
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function isActive(): bool
    {
        return in_array($this->status, ['trialing', 'active']);
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }
}
