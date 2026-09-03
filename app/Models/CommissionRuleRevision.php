<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LA TRACE D'UN CHANGEMENT DE TAUX.
 *
 * Elle SURVIT à la règle : c'est tout son intérêt. Quand la règle disparaît, la clé devient nulle
 * et l'instantané reste — sinon supprimer une règle effacerait la preuve de ce qu'elle a facturé.
 */
class CommissionRuleRevision extends Model
{
    protected $fillable = [
        'commission_rule_id', 'action', 'percent_before', 'percent_after',
        'snapshot', 'actor_id', 'actor_ip',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'percent_before' => 'float',
        'percent_after' => 'float',
    ];

    /** @return BelongsTo<CommissionRule, $this> */
    public function regle(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
