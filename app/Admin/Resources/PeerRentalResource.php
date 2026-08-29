<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\PeerVehicle;
use App\Models\PeerVehicleDocument;

/**
 * LA LOCATION ENTRE MEMBRES — les annonces, vues depuis la console mobile.
 *
 * Ce n'est pas {@see RentalVehicleResource} : celui-la montre la flotte DE LA PLATEFORME.
 * Ici, chaque ligne appartient a un membre, et le seul geste qui merite d'etre mobile est
 * celui de la moderation — publier ou suspendre une annonce depuis le terrain.
 *
 * @extends EloquentResource<PeerVehicle>
 */
class PeerRentalResource extends EloquentResource
{
    public function key(): string
    {
        return 'peer-rentals';
    }

    protected function model(): string
    {
        return PeerVehicle::class;
    }

    protected function columnSpec(): array
    {
        return [
            'brand' => ['Marque'],
            'model' => ['Modèle'],
            'city' => ['Ville'],
            'status' => ['État', Column::TYPE_BADGE],
            'daily_price_cents' => ['Prix/jour (cents)', Column::TYPE_NUMBER],
        ];
    }

    protected function searchable(): array
    {
        return ['brand', 'model', 'plate', 'city', 'reference'];
    }

    protected function searchLabel(): string
    {
        return 'Marque, modèle, plaque, ville ou référence';
    }

    protected function selectFilters(): array
    {
        return [
            'status' => ['État', 'status', [
                ['value' => PeerVehicle::STATUT_EN_REVUE, 'label' => 'En vérification'],
                ['value' => PeerVehicle::STATUT_PUBLIE, 'label' => 'En ligne'],
                ['value' => PeerVehicle::STATUT_EN_PAUSE, 'label' => 'En pause'],
                ['value' => PeerVehicle::STATUT_REFUSE, 'label' => 'Refusée'],
                ['value' => PeerVehicle::STATUT_BROUILLON, 'label' => 'Brouillon'],
            ]],
            'category' => ['Catégorie', 'category', [
                ['value' => 'citadine', 'label' => 'Citadine'],
                ['value' => 'berline', 'label' => 'Berline'],
                ['value' => 'suv', 'label' => 'SUV'],
                ['value' => 'monospace', 'label' => 'Monospace'],
                ['value' => 'utilitaire', 'label' => 'Utilitaire'],
                ['value' => 'cabriolet', 'label' => 'Cabriolet'],
            ]],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'reference' => 'Référence',
            'plate' => 'Plaque',
            'year' => 'Année',
            'fuel' => 'Énergie',
            'transmission' => 'Boîte',
            'seats' => 'Places',
            'deposit_cents' => 'Caution (cents)',
            'included_km_per_day' => 'Km inclus par jour',
            'min_driver_age' => 'Âge minimum',
            'min_license_years' => 'Permis (années)',
            'cancellation_policy' => 'Barème d’annulation',
            'rejection_reason' => 'Motif de refus',
        ];
    }

    public function actions(): array
    {
        return [
            /*
             * PUBLIER — et seulement si les papiers suivent.
             *
             * La console mobile ne doit pas pouvoir ce que le web refuse : une annonce
             * publiee sans carte grise valide serait une promesse que la plateforme ne
             * peut pas tenir en cas de dommage.
             */
            Action::make('publier', 'Publier l’annonce', function (PeerVehicle $vehicule, array $valeurs) {
                foreach (PeerVehicleDocument::TYPES_REQUIS as $type) {
                    $valide = $vehicule->documents
                        ->where('document_type', $type)
                        ->contains(fn (PeerVehicleDocument $document): bool => $document->estValide());

                    if (! $valide) {
                        return ['erreur' => 'Les papiers du véhicule ne sont pas tous validés.'];
                    }
                }

                $vehicule->forceFill([
                    'status' => PeerVehicle::STATUT_PUBLIE,
                    'published_at' => $vehicule->published_at ?? now(),
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'rejection_reason' => null,
                ])->save();

                return ['status' => $vehicule->status];
            }),

            /* SUSPENDRE — les locations en cours continuent, l'annonce sort de la recherche. */
            Action::make('suspendre', 'Suspendre l’annonce', function (PeerVehicle $vehicule, array $valeurs) {
                $vehicule->forceFill(['status' => PeerVehicle::STATUT_EN_PAUSE])->save();

                return ['status' => $vehicule->status];
            }),
        ];
    }
}
