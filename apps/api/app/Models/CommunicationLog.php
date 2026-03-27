<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationLog extends Model
{
    use HasUuids;

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sent_at'    => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'pos_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}

