<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un passage d'une regle : ce qu'elle a vu, ce qu'elle a pose, pourquoi elle s'est arretee. */
class AutomationRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'automation_rule_id', 'mode', 'demarre_le', 'termine_le',
        'entites_vues', 'entites_eligibles', 'entites_traitees', 'actions_posees', 'statut', 'message',
    ];

    protected $casts = [
        'demarre_le' => 'datetime',
        'termine_le' => 'datetime',
        'entites_vues' => 'integer',
        'entites_eligibles' => 'integer',
        'entites_traitees' => 'array',
        'actions_posees' => 'integer',
    ];

    /** @return BelongsTo<AutomationRule, $this> */
    public function regle(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }
}
