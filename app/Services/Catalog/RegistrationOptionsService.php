<?php

namespace App\Services\Catalog;

use App\Models\Country;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\TradeZonePricing;
use Illuminate\Support\Collection;

/**
 * CE QU'ON PROPOSE À UN PRESTATAIRE QUI S'INSCRIT — depuis le catalogue, et rien d'autre.
 *
 * L'inscription offrait une liste de métiers tirée de `trades` sans filtre de zone, et AUCUNE liste
 * de zones. Un prestataire déclarait donc « peinture » sans dire où, et le dispatch devait deviner
 * son périmètre à partir de sa position du jour. Quand l'administration ouvrait un métier dans une
 * nouvelle zone, personne ne pouvait s'y déclarer sans déploiement.
 *
 * DEUX LISTES, UNE SOURCE. Les métiers sont ceux du catalogue, groupés par secteur comme côté
 * client — ce sont les mêmes objets, il serait absurde qu'ils s'appellent autrement d'un côté et de
 * l'autre. Les zones sont celles où le métier est RÉELLEMENT VENDU : une ligne `trade_zone_pricing`
 * active. Proposer une zone où le métier est fermé ferait déclarer une couverture qui ne peut
 * produire aucune mission — et le prestataire attendrait des offres qui ne viendraient jamais.
 *
 * PUBLIC ET SANS JETON. Le formulaire d'inscription les appelle AVANT que le compte existe.
 */
class RegistrationOptionsService
{
    /**
     * Les secteurs, leurs métiers ouverts quelque part, et les zones qui les vendent.
     *
     * @return array{country: array<string, mixed>|null, sectors: list<array<string, mixed>>, zones: list<array<string, mixed>>}
     */
    public function forCountry(?string $countryIso = null): array
    {
        $country = $countryIso
            ? Country::query()->where('iso_code', strtoupper($countryIso))->first()
            : null;

        $zones = ServiceZone::query()
            ->where('status', 'active')
            ->when($country, fn ($q) => $q->where('country_id', $country->id))
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        /*
         * Les ouvertures (métier, zone) en UNE requête.
         *
         * Une requête par métier ferait N+1 sur un écran d'inscription — le premier écran que voit
         * un prestataire, et celui qu'il abandonne le plus vite.
         */
        $ouvertures = TradeZonePricing::query()
            ->where('is_active', true)
            ->whereIn('service_zone_id', $zones->pluck('id'))
            ->get(['trade_id', 'service_zone_id'])
            ->groupBy('trade_id')
            ->map(fn (Collection $lignes) => $lignes->pluck('service_zone_id')->map(fn ($id) => (int) $id)->unique()->values()->all());

        $secteurs = Sector::query()
            ->where('is_active', true)
            ->ordered()
            ->with(['trades' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->get()
            ->map(function (Sector $secteur) use ($ouvertures) {
                $metiers = $secteur->trades
                    ->filter(fn ($metier) => ! empty($ouvertures->get($metier->id)))
                    ->map(fn ($metier) => [
                        'id' => (int) $metier->id,
                        'name' => $metier->name,
                        'slug' => $metier->slug,
                        'icon' => $metier->icon,
                        // LES ZONES OÙ CE MÉTIER EST VENDU. Sans elles, l'écran ne peut pas
                        // restreindre le second choix au premier, et laisse déclarer une couverture
                        // vide.
                        'zone_ids' => $ouvertures->get($metier->id, []),
                        'allows_asap' => (bool) $metier->allows_asap,
                    ])
                    ->values()
                    ->all();

                return [
                    'id' => (int) $secteur->id,
                    'name' => $secteur->name,
                    'slug' => $secteur->slug,
                    'trades' => $metiers,
                ];
            })
            // Un secteur dont aucun métier n'est vendu nulle part n'a rien à proposer : l'afficher
            // vide ferait cliquer dans le vide.
            ->filter(fn (array $secteur) => $secteur['trades'] !== [])
            ->values()
            ->all();

        return [
            'country' => $country ? [
                'id' => (int) $country->id,
                'iso_code' => $country->iso_code,
                'name' => $country->name,
            ] : null,
            'sectors' => $secteurs,
            'zones' => $zones->map(fn (ServiceZone $zone) => [
                'id' => (int) $zone->id,
                'name' => $zone->name,
                'slug' => $zone->slug,
                'code' => $zone->code,
            ])->values()->all(),
        ];
    }
}
