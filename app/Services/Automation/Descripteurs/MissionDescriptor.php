<?php

namespace App\Services\Automation\Descripteurs;

use App\Models\Mission;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Les missions, vues par une regle d'automatisation.
 *
 * `debut_prevu`/`fin_prevue` visent le planifie (`planned_*`), pas le reel (`actual_*`) : rempli
 * des la creation de la mission, c'est deja la colonne que lit `AdminAlertService::lateMissions()`.
 */
class MissionDescriptor implements EntityDescriptor
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
            'statut' => FieldBinding::colonne('missions.status'),
            'reservation_id' => FieldBinding::colonne('missions.booking_id'),
            // « Qui intervient » n'est PAS expose : Mission::intervenantId() fait autorite en
            // coalescant deux colonnes, et FieldBinding ne sait lier qu'une colonne ou une jointure.
            'debut_prevu' => FieldBinding::colonne('missions.planned_start_at'),
            'fin_prevue' => FieldBinding::colonne('missions.planned_end_at'),
            'cree_le' => FieldBinding::colonne('missions.created_at'),
        ];
    }

    /** @return list<string> */
    public function operators(): array
    {
        return RuleTreeEvaluator::OPERATEURS_CONNUS;
    }

    /** L'invariance des generiques Eloquent interdit `Mission::query()` ici — voir le lot 1. */
    protected function modele(): Model
    {
        return new Mission;
    }
}
