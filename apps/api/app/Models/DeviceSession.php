<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceSession extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'pos_id',
        'sanctum_token_id',
        'device_fingerprint',
        'device_name',
        'platform',
        'ip_address',
        'last_active_at',
        'is_active',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'last_active_at' => 'datetime',
        'created_at'     => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'pos_id');
    }

    public function sanctumToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'sanctum_token_id');
    }

    /** Scope: solo sessioni attive. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Scope: sessioni scadute (inattive da più di N ore). */
    public function scopeExpiredSince($query, int $hours = 8)
    {
        return $query->where('last_active_at', '<', now()->subHours($hours));
    }
}
