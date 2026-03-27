<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $table = 'invoices';

    protected $appends = ['formatted_number'];

    protected function casts(): array
    {
        return [
            'invoice_date'         => 'date',
            'customer_fiscal_code' => 'encrypted',

            'subtotal'   => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total'      => 'decimal:2',

            'sdi_sent_at'     => 'datetime',
            'sdi_response_at' => 'datetime',
        ];
    }

    // ─── Relazioni ──────────────────────────────────────────────

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'pos_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    // ─── Scope ──────────────────────────────────────────────────

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', 'issued');
    }

    public function scopeSentSdi(Builder $query): Builder
    {
        return $query->where('status', 'sent_sdi');
    }

    // ─── Accessor ───────────────────────────────────────────────

    public function getFormattedNumberAttribute(): string
    {
        return (string) $this->invoice_number;
    }
}

