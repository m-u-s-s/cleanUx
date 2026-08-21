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
 * L'écran web (`App\Livewire\Admin\AnalyticsCenter`) croise les réservations par zone,
 * service, intervenant, marché et période, avec chiffre d'affaires, marge et note moyenne.
 * Il n'avait aucun équivalent natif : le module était déclaré « à venir » dans
 * `config/admin_console.php`.
 *
 * CE DESCRIPTEUR SERT LA LISTE FILTRABLE, PAS LES INDICATEURS. Le moteur de console
 * générique rend une liste, un détail et des actions ; les tuiles chiffrées relèvent d'une
 * couverture `report`, et un module n'en déclare qu'une. On sert donc d'abord ce que le
 * moteur sait faire — explorer les réservations par zone et par service — plutôt que de
 * promettre un écran qui ressemblerait au web sans en avoir la substance.
 *
 * CE QUI RESTE AU WEB, ET POURQUOI :
 *   - les indicateurs (CA, marge, note) — ils demandent `FinanceDocumentService` par ligne ;
 *   - le filtre « marché », qui n'est pas une égalité mais la PRÉSENCE d'une organisation
 *     (`organization_account_id` nul ou non) ; le moteur filtre par valeur, pas par nullité.
 *
 * Les options de zone et de service se lisent en base plutôt que d'être recopiées : une
 * liste figée ici divergerait du catalogue dès la première zone ouverte.
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
            /*
             * Les valeurs FRANÇAISES du domaine, comme `BookingResource` : la colonne
             * `bookings.status` porte les deux formes selon l'ancienneté de la ligne, et
             * proposer les anglaises donnerait des filtres qui ne rendent rien.
             */
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
     * `pluck` plutôt qu'une collection de modèles : on ne veut que deux colonnes, et le
     * type rendu — identifiant vers libellé — se lit sans qu'on ait à promettre à
     * l'analyseur statique quelles propriétés porte un modèle passé par son nom.
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
