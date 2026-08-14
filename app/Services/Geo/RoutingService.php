<?php

namespace App\Services\Geo;

use App\Services\GeolocationV2\GeocodingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DE A À B : combien, combien de temps, et par où.
 *
 * La plateforme savait déjà mesurer une distance — `GeocodingService::distance()` interroge Google
 * ou Mapbox, et retombe sur la haversine. Elle ne savait pas dire PAR OÙ : aucun service ne rendait
 * de géométrie, et il n'y avait donc rien à tracer sur une carte. Un client qui suit sa course voit
 * la voiture avancer sans savoir où elle va ; un conducteur voit un point, pas une route.
 *
 * CE SERVICE NE REFAIT PAS LA MESURE. Distance et durée viennent de `GeocodingService`, qui porte
 * déjà les fournisseurs, le cache en base et le repli. Ce qu'on ajoute ici, c'est la GÉOMÉTRIE, et
 * elle seule.
 *
 * TOUT EST SOFT-FAIL, et c'est la règle du parcours de commande : le géocodage y échoue déjà en
 * silence, parce qu'une adresse mal orthographiée ne doit pas empêcher de commander. Un itinéraire
 * indisponible fait perdre un tracé, jamais une course. Le repli est la ligne droite, et il
 * s'ANNONCE (`source`) : une ligne droite qui se ferait passer pour un trajet routier promettrait
 * un temps de course qu'aucun conducteur ne tiendra.
 */
class RoutingService
{
    /** Repli : les deux extrémités, sans rien entre elles. */
    public const SOURCE_STRAIGHT = 'straight_line';

    public function __construct(
        protected GeocodingService $geocoding,
    ) {}

    /**
     * La route entre deux points.
     *
     * Rend TOUJOURS un résultat — au pire une ligne droite. Rendre `null` obligerait chaque
     * appelant à gérer l'absence, et le premier qui l'oublierait afficherait « 0 km » pour une
     * course de quinze kilomètres.
     */
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): RouteResult
    {
        $mesure = $this->geocoding->distance($fromLat, $fromLng, $toLat, $toLng, 'driving');

        $points = $this->geometry($fromLat, $fromLng, $toLat, $toLng);

        return new RouteResult(
            distanceMeters: $mesure->distanceMeters,
            durationSeconds: $mesure->durationSeconds,
            source: $points === null ? self::SOURCE_STRAIGHT : $mesure->provider,
            points: $points ?? $this->straightLine($fromLat, $fromLng, $toLat, $toLng),
        );
    }

    /**
     * La suite de coordonnées que la carte trace, ou `null` si personne ne sait la donner.
     *
     * Mise en cache : deux personnes qui suivent la même course demandent la même route, et un
     * itinéraire routier ne change pas d'une minute à l'autre — au contraire d'un ETA, qui, lui,
     * dépend de la position courante et se recalcule à chaque relevé.
     *
     * @return list<array{lat: float, lng: float}>|null
     */
    public function geometry(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $cle = sprintf('route:geom:%.5f,%.5f:%.5f,%.5f', $fromLat, $fromLng, $toLat, $toLng);
        $ttl = (int) Config::get('geolocation_v2.distance_cache_ttl_minutes', 360) * 60;

        /** @var list<array{lat: float, lng: float}>|null */
        return Cache::remember($cle, $ttl, function () use ($fromLat, $fromLng, $toLat, $toLng) {
            return match ((string) Config::get('geolocation_v2.provider', 'mock')) {
                'mapbox' => $this->mapboxGeometry($fromLat, $fromLng, $toLat, $toLng),
                'google' => $this->googleGeometry($fromLat, $fromLng, $toLat, $toLng),
                default => null,
            };
        });
    }

    /**
     * Mapbox Directions en GeoJSON — les coordonnées arrivent telles quelles, sans décodage.
     *
     * L'endpoint configuré est celui de la matrice de distances (`directions-matrix`), qui ne rend
     * PAS de géométrie : on vise l'API Directions, sa voisine. Les deux partagent le même jeton.
     *
     * @return list<array{lat: float, lng: float}>|null
     */
    protected function mapboxGeometry(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $jeton = (string) Config::get('geolocation_v2.providers.mapbox.access_token');

        if ($jeton === '') {
            return null;
        }

        try {
            $reponse = Http::timeout((int) Config::get('geolocation_v2.timeout_seconds', 8))
                ->get(
                    sprintf(
                        'https://api.mapbox.com/directions/v5/mapbox/driving/%F,%F;%F,%F',
                        $fromLng, $fromLat, $toLng, $toLat,
                    ),
                    ['access_token' => $jeton, 'geometries' => 'geojson', 'overview' => 'simplified'],
                );

            if (! $reponse->successful()) {
                return null;
            }

            $coordonnees = (array) data_get($reponse->json(), 'routes.0.geometry.coordinates', []);

            // GeoJSON range en [longitude, latitude] — l'inverse de tout le reste de ce dépôt.
            // Inverser ici plutôt que chez l'appelant : une carte qui affiche la Somalie pour une
            // course bruxelloise est le symptôme classique de cette confusion.
            $points = [];

            foreach ($coordonnees as $paire) {
                if (is_array($paire) && count($paire) >= 2) {
                    $points[] = ['lat' => (float) $paire[1], 'lng' => (float) $paire[0]];
                }
            }

            return $points !== [] ? $points : null;
        } catch (\Throwable $e) {
            Log::warning('[routing] géométrie mapbox indisponible', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Google Directions — la géométrie arrive encodée en polyligne, il faut la décoder.
     *
     * @return list<array{lat: float, lng: float}>|null
     */
    protected function googleGeometry(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $cle = (string) Config::get('geolocation_v2.providers.google.api_key');

        if ($cle === '') {
            return null;
        }

        try {
            $reponse = Http::timeout((int) Config::get('geolocation_v2.timeout_seconds', 8))
                ->get('https://maps.googleapis.com/maps/api/directions/json', [
                    'origin' => sprintf('%F,%F', $fromLat, $fromLng),
                    'destination' => sprintf('%F,%F', $toLat, $toLng),
                    'mode' => 'driving',
                    'key' => $cle,
                ]);

            if (! $reponse->successful() || data_get($reponse->json(), 'status') !== 'OK') {
                return null;
            }

            $encodee = (string) data_get($reponse->json(), 'routes.0.overview_polyline.points', '');

            if ($encodee === '') {
                return null;
            }

            $points = $this->decodePolyline($encodee);

            return $points !== [] ? $points : null;
        } catch (\Throwable $e) {
            Log::warning('[routing] géométrie google indisponible', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Décode l'algorithme de polyligne encodée de Google.
     *
     * Format documenté et stable depuis quinze ans : chaque valeur est une DIFFÉRENCE avec la
     * précédente, encodée en base 64 par groupes de cinq bits. Le décodeur tient en vingt lignes ;
     * ajouter une dépendance pour cela coûterait plus cher à maintenir.
     *
     * @return list<array{lat: float, lng: float}>
     */
    protected function decodePolyline(string $encodee): array
    {
        $points = [];
        $index = 0;
        $longueur = strlen($encodee);
        $lat = 0;
        $lng = 0;

        while ($index < $longueur) {
            foreach (['lat', 'lng'] as $axe) {
                $resultat = 0;
                $decalage = 0;

                do {
                    if ($index >= $longueur) {
                        return $points;
                    }

                    $octet = ord($encodee[$index++]) - 63;
                    $resultat |= ($octet & 0x1F) << $decalage;
                    $decalage += 5;
                } while ($octet >= 0x20);

                $delta = ($resultat & 1) ? ~($resultat >> 1) : ($resultat >> 1);

                if ($axe === 'lat') {
                    $lat += $delta;
                } else {
                    $lng += $delta;
                }
            }

            $points[] = ['lat' => $lat / 1e5, 'lng' => $lng / 1e5];
        }

        return $points;
    }

    /**
     * @return list<array{lat: float, lng: float}>
     */
    protected function straightLine(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        return [
            ['lat' => $fromLat, 'lng' => $fromLng],
            ['lat' => $toLat, 'lng' => $toLng],
        ];
    }
}
