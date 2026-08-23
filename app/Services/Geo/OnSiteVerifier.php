<?php

namespace App\Services\Geo;

use App\Services\GeolocationV2\DistanceCalculator;
use Illuminate\Support\Facades\Config;

/** Confronte la position relevée au moment d'un geste au lieu où ce geste est censé avoir lieu. */
class OnSiteVerifier
{
    /** Position fournie et située dans le rayon accepté. */
    public const PASSED = 'passed';

    /** Contrôle désactivé par configuration : la position est notée, elle ne décide de rien. */
    public const SKIPPED_DISABLED = 'skipped_disabled';

    /** Le lieu n'a pas de coordonnées : il n'y a rien à comparer. */
    public const SKIPPED_NO_DESTINATION = 'skipped_no_destination';

    /** Aucune position transmise, et l'appelant n'en exigeait pas. */
    public const SKIPPED_NO_POSITION = 'skipped_no_position';

    public function __construct(
        protected DistanceCalculator $distance,
    ) {}

    /**
     * @param  bool|null  $requirePosition  `null` s'en remet à la configuration. Les appelants pour
     *                                      lesquels aucune position n'existe — une clôture depuis
     *                                      un tableau de bord web, par exemple — passent `false` :
     *                                      leur geste est alors seulement ESTAMPILLÉ comme non
     *                                      appuyé par une position, pas refusé.
     * @return array{failure: array<string, list<string>>|null, verdict: string|null, distance_m: int|null}
     */
    public function verify(
        ?float $lat,
        ?float $lng,
        ?float $destLat,
        ?float $destLng,
        ?float $accuracyM = null,
        bool $mocked = false,
        ?bool $requirePosition = null,
    ): array {
        $pass = fn (string $verdict, ?int $distance = null): array => [
            'failure' => null, 'verdict' => $verdict, 'distance_m' => $distance,
        ];
        $refuse = fn (string $message, ?int $distance = null): array => [
            'failure' => ['position' => [$message]], 'verdict' => null, 'distance_m' => $distance,
        ];

        if (! Config::get('trip_tracking.presence_geo_check', true)) {
            return $pass(self::SKIPPED_DISABLED);
        }

        if ($mocked && Config::get('trip_tracking.presence_reject_mocked', true)) {
            return $refuse(
                'Votre téléphone signale une position simulée. Désactivez les positions fictives pour continuer.'
            );
        }

        // Le lieu est examiné AVANT d'exiger une position, et l'ordre compte.
        if ($destLat === null || $destLng === null) {
            return $pass(self::SKIPPED_NO_DESTINATION);
        }

        if ($lat === null || $lng === null) {
            $required = $requirePosition ?? (bool) Config::get('trip_tracking.presence_require_position', true);

            // Sans exigence, on note l'absence plutôt que de la taire : un geste non appuyé par une
            // position doit rester reconnaissable après coup.
            return $required
                ? $refuse('Position indisponible. Activez la localisation pour confirmer votre présence sur place.')
                : $pass(self::SKIPPED_NO_POSITION);
        }

        $distanceM = (int) round($this->distance->distanceMeters($lat, $lng, $destLat, $destLng));

        if ($distanceM > $this->allowedRadiusM($accuracyM)) {
            return $refuse(
                sprintf(
                    'Vous semblez être à %s du lieu de l’intervention. Rapprochez-vous puis réessayez.',
                    $this->humanDistance($distanceM),
                ),
                $distanceM,
            );
        }

        return $pass(self::PASSED, $distanceM);
    }

    /** Rayon effectivement toléré, élargi si l'appareil annonce un relevé imprécis. */
    public function allowedRadiusM(?float $accuracyM): int
    {
        $base = (int) Config::get('trip_tracking.presence_max_distance_m', 250);

        if ($accuracyM === null || $accuracyM <= 0) {
            return $base;
        }

        $cap = (int) Config::get('trip_tracking.presence_max_accuracy_allowance_m', 500);

        return max($base, min((int) round($accuracyM), $cap));
    }

    /** Une distance ne se lit pas en mètres au-delà du kilomètre. */
    protected function humanDistance(int $meters): string
    {
        return $meters >= 1000
            ? str_replace('.', ',', (string) round($meters / 1000, 1)).' km'
            : $meters.' m';
    }
}
