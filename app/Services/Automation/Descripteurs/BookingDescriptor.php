<?php

namespace App\Services\Automation\Descripteurs;

use App\Models\Booking;
use App\Services\Conditions\EntityDescriptor;
use App\Services\Conditions\FieldBinding;
use App\Services\Conditions\RuleTreeEvaluator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Les reservations, vues par une regle d'automatisation.
 *
 * « Qui intervient » n'est PAS expose : la reponse fait autorite dans `missions`, pas dans
 * `bookings.employe_id`. Un champ nomme ainsi tromperait. Il viendra a une phase ulterieure.
 */
class BookingDescriptor implements EntityDescriptor
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
            'statut' => FieldBinding::colonne('bookings.status'),
            'priorite' => FieldBinding::colonne('bookings.priorite'),
            'date' => FieldBinding::colonne('bookings.date'),
            'ville' => FieldBinding::colonne('bookings.ville'),
            'code_postal' => FieldBinding::colonne('bookings.code_postal'),
            'zone_id' => FieldBinding::colonne('bookings.service_zone_id'),
            'prix_estime' => FieldBinding::colonne('bookings.estimated_price'),
            'cree_le' => FieldBinding::colonne('bookings.created_at'),
        ];
    }

    /** @return list<string> */
    public function operators(): array
    {
        return RuleTreeEvaluator::OPERATEURS_CONNUS;
    }

    /** L'invariance des generiques Eloquent interdit `Booking::query()` ici — voir le lot 1. */
    protected function modele(): Model
    {
        return new Booking;
    }
}
