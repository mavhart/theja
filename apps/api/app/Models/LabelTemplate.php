<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabelTemplate extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'label_width_mm'  => 'decimal:1',
            'label_height_mm' => 'decimal:1',
            'cols'            => 'integer',
            'rows'            => 'integer',
            'margin_top_mm'   => 'decimal:1',
            'margin_left_mm'  => 'decimal:1',
            'spacing_h_mm'    => 'decimal:1',
            'spacing_v_mm'    => 'decimal:1',
            'fields'          => 'array',
            'is_default'      => 'boolean',
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
