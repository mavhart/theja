<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LacExam extends Model
{
    use HasUuids;

    protected $table = 'lac_exams';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'exam_date'      => 'date',
            'tabs_completed' => 'array',
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
