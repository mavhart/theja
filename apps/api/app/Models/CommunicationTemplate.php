<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationTemplate extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
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
}

