<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'vat_number',
        'billing_email',
        'stripe_customer_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ─── Relazioni ────────────────────────────────────────────

    public function pointsOfSale(): HasMany
    {
        return $this->hasMany(PointOfSale::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function subscriptionAddOns(): HasMany
    {
        return $this->hasMany(SubscriptionAddOn::class);
    }

    // ─── Helpers ─────────────────────────────────────────────

    /**
     * Nome dello schema PostgreSQL per questo tenant.
     * Es: tenant_550e8400e29b41d4a716446655440000
     */
    public function getTenantSchemaName(): string
    {
        return 'tenant_' . str_replace('-', '', $this->id);
    }
}
