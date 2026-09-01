<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ce que l'administrateur autorise, action par action. Absence de ligne = a valider. */
class AutomationActionSetting extends Model
{
    public $timestamps = false;

    protected $fillable = ['action_cle', 'autonome', 'domaine_au_moment_du_reglage', 'modifie_par', 'modifie_le'];

    protected $casts = [
        'autonome' => 'boolean',
        'domaine_au_moment_du_reglage' => 'boolean',
        'modifie_le' => 'datetime',
    ];
}
