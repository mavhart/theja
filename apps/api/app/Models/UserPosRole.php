<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

/**
 * Assegna a un utente un ruolo specifico per un POS.
 * La PK composita (user_id, pos_id) garantisce un solo ruolo per utente/POS.
 */
class UserPosRole extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $table = 'user_pos_roles';

    protected $fillable = [
        'user_id',
        'pos_id',
        'role_id',
        'can_see_purchase_prices',
    ];

    protected $casts = [
        'can_see_purchase_prices' => 'boolean',
        'user_id'                 => 'integer',
        'role_id'                 => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pointOfSale(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'pos_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
