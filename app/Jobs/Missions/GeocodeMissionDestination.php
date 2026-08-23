<?php

namespace App\Jobs\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Services\Geocoding\GeocodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Résout les coordonnées de destination d'une mission depuis l'adresse de sa réservation. */
class GeocodeMissionDestination implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Issues possibles d'une exécution, exploitées par missions:backfill-destinations. */
    public const OUTCOME_MISSING = 'missing';

    public const OUTCOME_ALREADY_SET = 'already_set';

    public const OUTCOME_NO_BOOKING = 'no_booking';

    public const OUTCOME_COPIED = 'copied';

    public const OUTCOME_UNRESOLVED = 'unresolved';

    public const OUTCOME_GEOCODED = 'geocoded';

    public int $tries = 3;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    public function __construct(
        public int $missionId,
    ) {}

    /** Renvoie l'issue plutôt que void : la file l'ignore, mais le rattrapage de masse s'en sert pour ne temporiser qu'après un appel réseau réel, et pour compter ce qu'il a résolu. */
    public function handle(GeocodingService $geocoding): string
    {
        $mission = Mission::query()
            ->with(['booking.postalCode.country'])
            ->find($this->missionId);

        if (! $mission) {
            Log::info('GeocodeMissionDestination: mission introuvable', ['mission_id' => $this->missionId]);

            return self::OUTCOME_MISSING;
        }

        if ($mission->destination_lat !== null && $mission->destination_lng !== null) {
            return self::OUTCOME_ALREADY_SET;
        }

        $booking = $mission->booking;

        if (! $booking) {
            Log::info('GeocodeMissionDestination: aucune réservation rattachée', ['mission_id' => $mission->id]);

            return self::OUTCOME_NO_BOOKING;
        }

        // Le client a pu fournir la position (Google Places sur le web) : pas d'appel réseau.
        if ($booking->destination_lat !== null && $booking->destination_lng !== null) {
            $mission->update([
                'destination_lat' => $booking->destination_lat,
                'destination_lng' => $booking->destination_lng,
            ]);

            return self::OUTCOME_COPIED;
        }

        $postalCode = $booking->postal_code ?: $booking->code_postal;
        $city = $booking->city ?: $booking->ville;
        $countryCode = $this->countryCodeFor($booking);

        $destination = $geocoding->resolve(
            $booking->address ?: $booking->adresse,
            $postalCode,
            $city,
            $countryCode,
        );

        if (! $destination) {
            // Nominatim ne rend rien sur le format « 10 Rue des Arts » (numéro en tête), alors
            // qu'il résout « Rue des Arts » — or la saisie libre produit couramment le premier.
            // Sans ce repli, toute une classe d'adresses réelles reste sans marqueur. Le centroïde
            // code postal + ville est approximatif, mais l'adresse exacte reste affichée en texte
            // sur l'offre : un marqueur au bon quartier vaut mieux qu'une carte vide.
            $destination = $geocoding->resolve(null, $postalCode, $city, $countryCode);

            if ($destination) {
                Log::info('GeocodeMissionDestination: repli sur le centroïde code postal', [
                    'mission_id' => $mission->id,
                    'booking_id' => $booking->id,
                ]);
            }
        }

        if (! $destination) {
            // Adresse non résolvable : réessayer ne changerait rien, on sort sans échouer.
            Log::info('GeocodeMissionDestination: adresse non résolue', [
                'mission_id' => $mission->id,
                'booking_id' => $booking->id,
            ]);

            return self::OUTCOME_UNRESOLVED;
        }

        $mission->update([
            'destination_lat' => $destination['lat'],
            'destination_lng' => $destination['lng'],
        ]);

        return self::OUTCOME_GEOCODED;
    }

    /** Même chaîne de repli que MissionFromRendezVousSyncService. */
    protected function countryCodeFor(Booking $booking): string
    {
        return strtoupper((string) (
            $booking->postalCode?->country?->iso_code
            ?? data_get($booking->zone_snapshot, 'postal_code.country_code')
            ?? 'BE'
        ));
    }

    /** Tag pour Horizon. */
    public function tags(): array
    {
        return ['missions', 'geocode-mission-'.$this->missionId];
    }
}
