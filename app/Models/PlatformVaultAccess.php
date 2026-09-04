<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CHAQUE OUVERTURE DU COFFRE, RÉUSSIE OU NON.
 *
 * Les REFUS comptent autant que les réussites : une série de codes faux est le premier signe
 * qu'on essaie d'entrer, et c'est le seul moment où l'on peut encore réagir.
 */
class PlatformVaultAccess extends Model
{
    public const OUVERT = 'ouvert';

    public const REFUSE = 'refuse';

    public const MODIFIE = 'modifie';

    protected $fillable = [
        'action', 'actor_id', 'actor_ip', 'actor_user_agent', 'platform_bank_account_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function acteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
