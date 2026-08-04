<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\Booking;
use App\Services\Booking\SmartDispatchService;

/**
 * Les réservations en attente d’affectation.
 *
 * DÉCLENCHER un dispatch depuis ici serait tentant, et c’est précisément ce qu’il ne faut pas
 * faire : le dispatch propose un prestataire avec un score et une explication, et on valide
 * APRÈS avoir vu la proposition. Un bouton de liste validerait sans montrer.
 *
 * @extends EloquentResource<Booking>
 */
class DispatchResource extends EloquentResource
{
    public function key(): string
    {
        return 'ia-dispatch';
    }

    protected function model(): string
    {
        return Booking::class;
    }

    protected function columnSpec(): array
    {
        return [
            'booking_reference' => ['Référence'],
            'status' => ['Statut', Column::TYPE_BADGE],
            'scheduled_date' => ['Date', Column::TYPE_DATE],
            'city' => ['Ville'],
            'priority' => ['Priorité', Column::TYPE_BADGE],
        ];
    }

    protected function searchable(): array
    {
        return ['booking_reference', 'city'];
    }

    protected function searchLabel(): string
    {
        return 'Référence ou ville';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['Statut', 'status', [
                ['value' => 'en_attente', 'label' => 'En attente'],
                ['value' => 'confirme', 'label' => 'Confirmé'],
                ['value' => 'en_route', 'label' => 'En route'],
                ['value' => 'sur_place', 'label' => 'Sur place'],
                ['value' => 'termine', 'label' => 'Terminé'],
                ['value' => 'annule', 'label' => 'Annulé'],
                ['value' => 'refuse', 'label' => 'Refusé'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'asap_requested_at' => 'Demande immédiate',
            'asap_deadline_at' => 'Échéance immédiate',
            'matched_at' => 'Affectée le',
            'provider_type_preference' => 'Préférence prestataire',
        ];
    }

    public function actions(): array
    {
        return [
            /*
             * Affecter le meilleur prestataire disponible. Le service pèse distance, charge,
             * compétences et disponibilité — le refaire ici produirait un second classement, et
             * deux affectations différentes selon l'écran d'où l'on part.
             *
             * Quand il ne trouve personne, on le DIT : « affecté » sur une mission sans
             * intervenant se découvrirait le jour de la prestation.
             */
            Action::make('assign-best', 'Affecter le meilleur prestataire', function (Booking $rdv) {
                $employe = app(SmartDispatchService::class)->assignBestEmployee($rdv);

                if (! $employe) {
                    return ['ok' => false, 'message' => 'Aucun prestataire disponible pour cette mission.'];
                }

                return ['ok' => true, 'assigned_to' => $employe->id];
            }),
        ];
    }
}
