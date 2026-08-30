<?php

namespace App\Services\Automation\Descripteurs;

use App\Models\AlerteMetier;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Les alertes metier, vues par une regle.
 *
 * `contexte` n'est PAS expose : son contenu change d'une alerte a l'autre, et MySQL reordonne
 * les cles JSON. Un champ qui promettrait de le filtrer mentirait.
 */
class AlerteDescriptor implements EntityDescriptor
{
    /** @var array<string, FieldBinding>|null */
    protected ?array $champs = null;

    /** @return Builder<Model> */
    public function baseQuery(): Builder
    {
        return $this->modele()->newQuery();
    }

    /** @return array<string, FieldBinding> */
    public function fields(): array
    {
        return $this->champs ??= [
            'cle' => FieldBinding::colonne('business_alertes.cle'),
            'niveau' => FieldBinding::colonne('business_alertes.niveau'),
            'entite_type' => FieldBinding::colonne('business_alertes.entite_type'),
            'levee_le' => FieldBinding::colonne('business_alertes.levee_le'),
        ];
    }

    /** @return list<string> */
    public function operators(): array
    {
        return RuleTreeEvaluator::OPERATEURS_CONNUS;
    }

    /** L'invariance des generiques Eloquent interdit `AlerteMetier::query()` ici — voir le lot 1. */
    protected function modele(): Model
    {
        return new AlerteMetier;
    }
}
