<?php

namespace App\Admin\Resources;

use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Models\Booking;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use Illuminate\Database\Eloquent\Model;

/**
 * L'EXPLORATION MÉTIER, servie à la console mobile.
 *
 * @extends EloquentResource<Booking>
 */
class AnalyticsExplorationResource extends EloquentResource
{
    public function key(): string
    {
        return 'analytics-exploration';
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
            'estimated_price' => ['Prix estimé', Column::TYPE_MONEY],
        ];
    }

    protected function eagerLoad(): array
    {
        return ['serviceZone:id,name', 'serviceCatalog:id,name'];
    }

    protected function searchable(): array
    {
        return ['booking_reference', 'city', 'address', 'contact_name'];
    }

    protected function searchLabel(): string
    {
        return 'Référence, ville, adresse ou contact';
    }

    protected function selectFilters(): array
    {
        return [
            // Les valeurs FRANÇAISES du domaine, comme `BookingResource` : la colonne `bookings.status` porte les deux formes selon l'ancienneté de la ligne, et proposer les anglaises donnerait des filtres qui ne rendent rien.
            'status' => ['Statut', 'status', [
                ['value' => 'en_attente', 'label' => 'En attente'],
                ['value' => 'confirme', 'label' => 'Confirmé'],
                ['value' => 'en_route', 'label' => 'En route'],
                ['value' => 'sur_place', 'label' => 'Sur place'],
                ['value' => 'termine', 'label' => 'Terminé'],
                ['value' => 'annule', 'label' => 'Annulé'],
                ['value' => 'refuse', 'label' => 'Refusé'],
            ]],

            'service_zone_id' => ['Zone', 'service_zone_id', $this->optionsDe(ServiceZone::class)],

            'service_catalog_id' => ['Service', 'service_catalog_id', $this->optionsDe(ServiceCatalog::class)],
        ];
    }

    protected function detailSpec(): array
    {
        return [
            'contact_name' => 'Contact',
            'address' => 'Adresse',
            'booking_mode' => 'Mode',
            'customer_comment' => 'Commentaire client',
        ];
    }

    /**
     * Les options d'un filtre, lues en base.
     *
     * @param  class-string<Model>  $modele
     * @return array<int, array{value: int|string, label: string}>
     */
    private function optionsDe(string $modele): array
    {
        /** @var array<int|string, string> $lignes */
        $lignes = $modele::query()
            ->orderBy('name')
            ->limit(200)
            ->pluck('name', 'id')
            ->all();

        $options = [];

        foreach ($lignes as $id => $libelle) {
            $options[] = ['value' => $id, 'label' => (string) $libelle];
        }

        return $options;
    }
}
