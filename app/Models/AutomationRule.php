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

    /** Cle de cadence => minutes. Source unique : ExecuterLAutomatisation la lisait en prive. */
    public const CADENCES = [
        'chaque_minute' => 1,
        'quart_heure' => 15,
        'heure' => 60,
        'jour' => 1440,
    ];

    /** Les trois politiques que RuleRunner sait interpreter (defaut : une_fois). */
    public const POLITIQUES_REPRISE = ['une_fois', 'chaque_passage', 'une_fois_par_jour'];

    protected $attributes = [
        'declencheur' => 'cadence',
        'politique_reprise' => 'une_fois',
        'etat' => 'brouillon',
        'quota_par_passage' => 50,
        'plafond_journalier' => 500,
        'plafonds_consecutifs' => 0,
        'echecs_consecutifs' => 0,
    ];

    // `cle_de_reference` est VOLONTAIREMENT absente : identité d'un seeder, jamais du produit —
    // aucun formulaire admin ne doit pouvoir la poser. Seul `forceCreate`/`forceFill` l'écrit.
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
