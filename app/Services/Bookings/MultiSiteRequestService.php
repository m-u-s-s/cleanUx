<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** UNE DEMANDE COUVRANT PLUSIEURS SITES. */
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
        // Les identifiants viennent du client : on ne retient que les sites actifs appartenant à l'organisation.
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
