<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UN TRANSFERT DE SIÈGE, ARMÉ PUIS EFFECTIF.
 *
 * Il n'y a rien à « mettre à jour » ici : une ligne s'arme, puis se confirme ou s'annule. Un
 * transfert qu'on pourrait réécrire perdrait sa valeur de preuve.
 */
class PlatformSeatTransfer extends Model
{
    protected $fillable = [
        'from_user_id', 'to_user_id',
        'armed_at', 'effective_at', 'confirmed_at', 'cancelled_at', 'cancelled_reason',
        'armed_ip', 'armed_user_agent',
    ];

    protected $casts = [
        'armed_at' => 'datetime',
        'effective_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function from(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function to(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->whereNull('confirmed_at')->whereNull('cancelled_at');
    }

    public function estMur(): bool
    {
        return $this->effective_at->isPast();
    }
}
