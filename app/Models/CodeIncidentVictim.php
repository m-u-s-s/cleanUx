<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * QUI A VU L'ERREUR.
 *
 * Compter les occurrences ne suffit pas : cent fois une personne et une fois cent personnes
 * n'appellent pas la même réaction.
 */
class CodeIncidentVictim extends Model
{
    protected $fillable = ['code_incident_id', 'user_id'];

    /** @return BelongsTo<CodeIncident, $this> */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(CodeIncident::class, 'code_incident_id');
    }
}
