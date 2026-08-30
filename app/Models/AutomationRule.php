<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Une regle d'automatisation : un declencheur, des conditions, des actions. */
class AutomationRule extends Model
{
    public const ETAT_BROUILLON = 'brouillon';

    public const ETAT_OBSERVATION = 'observation';

    public const ETAT_ARMEE = 'armee';

    public const ETAT_SUSPENDUE = 'suspendue';

    public const ETAT_DESACTIVEE = 'desactivee';

    protected $attributes = [
        'etat' => 'brouillon',
        'politique_reprise' => 'une_fois',
        'quota_par_passage' => 50,
        'plafond_journalier' => 500,
        'plafonds_consecutifs' => 0,
        'echecs_consecutifs' => 0,
    ];

    protected $fillable = [
        'nom', 'description', 'entite', 'declencheur', 'cadence', 'conditions', 'actions',
        'politique_reprise', 'etat', 'quota_par_passage', 'plafond_journalier',
        'plafonds_consecutifs', 'echecs_consecutifs', 'dernier_passage_le', 'cree_par',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'quota_par_passage' => 'integer',
        'plafond_journalier' => 'integer',
        'plafonds_consecutifs' => 'integer',
        'echecs_consecutifs' => 'integer',
        'dernier_passage_le' => 'datetime',
    ];

    /** @return HasMany<AutomationRun, $this> */
    public function passages(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    /** @return HasMany<AutomationAction, $this> */
    public function actionsPosees(): HasMany
    {
        return $this->hasMany(AutomationAction::class);
    }
}
