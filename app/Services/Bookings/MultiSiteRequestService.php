<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * UNE DEMANDE COUVRANT PLUSIEURS SITES.
 *
 * `bookings.organization_site_id` est singulier : une réservation ne concerne qu'un local. Une
 * société multi-sites devait donc répéter la même demande site par site, sans rien pour les relier
 * ensuite.
 *
 * On s'appuie sur `bookings.parent_booking_id`, présent depuis la migration initiale mais resté
 * DORMANT : aucune relation sur le modèle, aucun écrivain dans le code, zéro ligne en base
 * (vérifié). La récurrence passant par `recurring_series_id`, ce champ était bien un lien de
 * parenté générique disponible. L'activer évite une table et une migration structurelle.
 *
 * Forme retenue : une réservation MÈRE porte l'intention commune (métier, date, demandeur) et
 * chaque site reçoit sa FILLE. Les traitements existants — matching, facturation, litiges — voient
 * des réservations ordinaires et continuent de fonctionner sans les connaître.
 */
class MultiSiteRequestService
{
    /**
     * @param  list<int>  $siteIds  identifiants reçus du navigateur, donc non fiables
     * @param  array<string, mixed>  $options  duree_estimee, devis_estime, commentaire_client…
     * @return Booking|null la demande mère, ou `null` si aucun site recevable
     */
    public function creer(
        User $demandeur,
        OrganizationAccount $organisation,
        Trade $trade,
        array $siteIds,
        Carbon $quand,
        array $options = [],
    ): ?Booking {
        /*
         * Les identifiants viennent du client : on ne retient que les sites actifs appartenant à
         * l'organisation. Sans ce filtre, une société pourrait faire intervenir un prestataire
         * chez une autre — et lire son adresse au passage.
         */
        $sites = OrganizationSite::query()
            ->where('organization_account_id', $organisation->id)
            ->whereIn('id', $siteIds)
            ->get();

        if ($sites->isEmpty()) {
            // Une mère sans fille serait une coquille vide : invisible dans les listes, jamais
            // traitée, mais bien présente en base. On préfère ne rien créer.
            return null;
        }

        return DB::transaction(function () use ($demandeur, $organisation, $trade, $sites, $quand, $options) {
            $mere = $this->creerReservation($demandeur, $organisation, $trade, $quand, $options, null, null);

            foreach ($sites as $site) {
                $this->creerReservation($demandeur, $organisation, $trade, $quand, $options, $site, $mere->id);
            }

            return $mere;
        });
    }

    /** @param  array<string, mixed>  $options */
    private function creerReservation(
        User $demandeur,
        OrganizationAccount $organisation,
        Trade $trade,
        Carbon $quand,
        array $options,
        ?OrganizationSite $site,
        ?int $parentId,
    ): Booking {
        return Booking::query()->create([
            'client_id' => $demandeur->id,
            'customer_organization_id' => $organisation->id,
            'trade_id' => $trade->id,
            'parent_booking_id' => $parentId,
            'organization_site_id' => $site?->id,
            'service_zone_id' => $site?->service_zone_id,
            'destination_lat' => $site?->latitude,
            'destination_lng' => $site?->longitude,
            'date' => $quand->toDateString(),
            'heure' => $quand->format('H:i:s'),
            'duree_estimee' => (int) ($options['duree_estimee'] ?? 60),
            'devis_estime' => (float) ($options['devis_estime'] ?? 0),
            'status' => 'en_attente',
            'commentaire_client' => $options['commentaire_client'] ?? null,
            'booking_channel' => 'b2b_multi_site',
            'address_components' => $site ? [
                'site_id' => $site->id,
                'site_code' => $site->site_code,
                'address_line_1' => $site->address_line_1,
            ] : null,
        ]);
    }
}
