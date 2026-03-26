<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prescription extends Model
{
    use HasUuids;

    /** Tutti i campi optometrici sono assegnabili; l'ID è protetto. */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'visit_date'       => 'date',
            'is_international' => 'boolean',
            'glasses_in_use'   => 'boolean',
            'prescribed_at'    => 'date',
            'next_recall_at'   => 'date',
            'next_recall2_at'  => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'pos_id');
    }

    public function optician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'optician_user_id');
    }
}
