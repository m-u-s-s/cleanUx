<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal d'audit, registre « deja agi » et file des propositions — le meme objet. */
class AutomationAction extends Model
{
    public $timestamps = false;

    public const RESULTAT_SIMULEE = 'simulee';

    public const RESULTAT_EXECUTEE = 'executee';

    public const RESULTAT_PROPOSEE = 'proposee';

    public const RESULTAT_VALIDEE = 'validee';

    public const RESULTAT_REFUSEE = 'refusee';

    public const RESULTAT_ECHOUEE = 'echouee';

    public const RESULTAT_EXPIREE = 'expiree';

    protected $fillable = [
        'automation_rule_id', 'automation_run_id', 'entite_type', 'entite_id', 'action_cle',
        'parametres', 'mode', 'resultat', 'decide_par', 'decide_le', 'motif', 'etape',
        'message', 'pose_le',
    ];

    protected $casts = [
        'parametres' => 'array',
        'entite_id' => 'integer',
        'etape' => 'integer',
        'decide_le' => 'datetime',
        'pose_le' => 'datetime',
    ];

    /** @return BelongsTo<AutomationRule, $this> */
    public function regle(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }
}
