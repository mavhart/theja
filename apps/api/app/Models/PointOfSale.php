<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointOfSale extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'points_of_sale';

    protected $fillable = [
        'organization_id',
        'name',
        'address',
        'city',
        'fiscal_code',
        'vat_number',
        'has_local_manager',
        'virtual_cash_register_enabled',
        'rt_provider',
        'rt_credentials',
        'sumup_api_key',
        'cash_register_hardware_configured',
        'ai_analysis_enabled',
        'max_concurrent_web_sessions',
        'max_mobile_devices',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'has_local_manager'               => 'boolean',
            'virtual_cash_register_enabled'   => 'boolean',
            'rt_credentials'                  => 'encrypted',
            'sumup_api_key'                   => 'encrypted',
            'cash_register_hardware_configured' => 'boolean',
            'ai_analysis_enabled'             => 'boolean',
            'max_concurrent_web_sessions'     => 'integer',
            'max_mobile_devices'              => 'integer',
            'is_active'                       => 'boolean',
        ];
    }

    // ─── Relazioni ────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
