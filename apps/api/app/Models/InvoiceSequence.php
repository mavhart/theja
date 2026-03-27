<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSequence extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $table = 'invoice_sequences';

    protected function casts(): array
    {
        return [
            'year'        => 'integer',
            'last_number' => 'integer',
        ];
    }

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'pos_id');
    }
}

