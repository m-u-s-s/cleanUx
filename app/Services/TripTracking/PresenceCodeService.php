<?php

namespace App\Services\TripTracking;

use App\Models\TripTrackingSession;
use App\Models\User;
use App\Services\GeolocationV2\DistanceCalculator;
use App\Services\Missions\MissionVerificationCodeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Confirmation de présence du prestataire, par code affiché chez le client.
 *
 * La géo-barrière atteste d'une proximité, pas d'une présence : un téléphone à 100 m de la porte
 * la franchit. Le client affiche donc un code à usage unique, que le prestataire scanne sur place.
 *
 * Mais un code atteste d'une POSSESSION, pas d'une présence : photographié puis envoyé par
 * messagerie, ou simplement dicté au téléphone, il se valide depuis n'importe où pendant ses dix
 * minutes de vie. Les deux preuves sont donc croisées — le code ET la position au moment du scan.
 * Séparément, chacune se contourne ; ensemble, il faut être là.
 *
 * Les garde-fous du code sont ceux de {@see MissionVerificationCodeService}, dont ce mécanisme est
 * le pendant côté suivi : empreinte plutôt que clair, péremption courte, et plafond de tentatives —
 * six chiffres se devinent en un million d'essais, ce qui n'est rien pour une machine.
 */
class PresenceCodeService
{
    /** Durée de vie d'un code, en minutes. Assez pour scanner, trop peu pour transmettre. */
    public const TTL_MINUTES = 10;

    /** Au-delà, le code est brûlé et il faut en demander un neuf. */
    public const MAX_ATTEMPTS = 5;

    /** Position fournie et située dans le rayon accepté. */
    public const GEO_PASSED = 'passed';

    /** Contrôle désactivé par configuration : la position est notée, elle ne décide de rien. */
    public const GEO_SKIPPED_DISABLED = 'skipped_disabled';

    /** Le lieu de l'intervention n'a pas de coordonnées : il n'y a rien à comparer. */
    public const GEO_SKIPPED_NO_DESTINATION = 'skipped_no_destination';

    /** Aucune position transmise, et la configuration n'en exige pas. */
    public const GEO_SKIPPED_NO_POSITION = 'skipped_no_position';

    /** Au-delà, les refus les plus anciens sont oubliés : une série d'essais ne doit pas gonfler la ligne sans fin. */
    protected const MAX_REJECTIONS_KEPT = 10;

    public function __construct(
        protected DistanceCalculator $distance,
    ) {}

    /**
     * Délivre le code courant du client, ou en forge un neuf s'il n'y en a pas d'exploitable.
     *
     * Idempotent tant que le code vaut : rouvrir l'écran ne doit pas invalider le code que le
     * prestataire est en train de scanner.
     *
     * @return array{code: string, expires_at: Carbon}
     */
    public function issueFor(TripTrackingSession $session): array
    {
        if ($session->presence_confirmed_at !== null) {
            throw ValidationException::withMessages([
                'presence' => ['La présence est déjà confirmée pour cette intervention.'],
            ]);
        }

        // Un code en clair n'est jamais relisible : le renouveler est la seule option quand
        // l'empreinte existante n'est plus exploitable.
        $plain = $this->generatePlainCode();

        $session->update([
            'presence_code_hash' => Hash::make($plain),
            'presence_code_expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'presence_code_attempts' => 0,
        ]);

        return [
            'code' => $plain,
            'expires_at' => $session->fresh()->presence_code_expires_at,
        ];
    }

    /**
     * Valide le code scanné par le prestataire et grave la confirmation.
     *
     * La position est celle relevée AU MOMENT DU SCAN, transmise avec l'appel. Celle de la session
     * ne conviendrait pas : elle vient du dernier relevé reçu, qu'il suffit de cesser d'émettre en
     * quittant les lieux pour figer sur une valeur flatteuse.
     *
     * @param  float|null  $lat  Latitude relevée au scan.
     * @param  float|null  $lng  Longitude relevée au scan.
     * @param  float|null  $accuracyM  Précision annoncée par l'appareil, en mètres.
     * @param  bool  $mocked  L'appareil signale une position simulée (Android).
     *
     * @throws ValidationException
     */
    public function confirm(
        TripTrackingSession $session,
        string $plainCode,
        User $provider,
        ?float $lat = null,
        ?float $lng = null,
        ?float $accuracyM = null,
        bool $mocked = false,
    ): TripTrackingSession {
        /**
         * Le refus est RENVOYÉ, jamais levé depuis la transaction : une exception la ferait
         * annuler, et avec elle l'incrément du compteur comme la trace du refus. Le plafond
         * anti-force-brute ne compterait alors jamais — un essai raté ne laisserait aucune trace.
         *
         * @var array<string, list<string>>|null $failure
         */
        $failure = null;

        $confirmed = DB::transaction(function () use ($session, $plainCode, $provider, $lat, $lng, $accuracyM, $mocked, &$failure) {
            /** @var TripTrackingSession $locked */
            $locked = TripTrackingSession::query()->lockForUpdate()->findOrFail($session->id);

            // Idempotence : un double scan ne doit pas être traité comme un échec. La position
            // n'est pas réexaminée — la présence est un fait déjà gravé, le second appel ne peut
            // que le répéter.
            if ($locked->presence_confirmed_at !== null) {
                return $locked;
            }

            /**
             * Le contrôle de position passe AVANT le compteur de tentatives, délibérément.
             *
             * Être au mauvais endroit n'est pas se tromper de code : le prestataire dont le
             * relevé dérive ne doit pas y consommer les cinq essais dont il aura besoin une fois
             * la position retrouvée. Et cela n'affaiblit rien — qui est loin ne peut pas
             * confirmer, quel que soit le nombre d'essais qu'on lui laisse.
             */
            $geo = $this->evaluatePosition($locked, $lat, $lng, $accuracyM, $mocked);
            if ($geo['failure'] !== null) {
                $this->recordRejection($locked, $lat, $lng, $accuracyM, $geo);
                $failure = $geo['failure'];

                return null;
            }

            if ($locked->presence_code_hash === null) {
                $failure = ['code' => ["Aucun code en cours. Demandez au client d'afficher le sien."]];

                return null;
            }

            $locked->increment('presence_code_attempts');
            $locked->refresh();

            // Le plafond est vérifié AVANT l'empreinte : une bonne réponse tardive ne doit pas
            // racheter une série d'essais.
            if ($locked->presence_code_attempts > self::MAX_ATTEMPTS) {
                $locked->update(['presence_code_hash' => null]);
                $failure = ['code' => ['Trop de tentatives. Demandez au client un nouveau code.']];

                return null;
            }

            if ($locked->presence_code_expires_at !== null && $locked->presence_code_expires_at->isPast()) {
                $failure = ['code' => ['Ce code a expiré. Demandez au client de le rafraîchir.']];

                return null;
            }

            if (! Hash::check(trim($plainCode), $locked->presence_code_hash)) {
                $failure = ['code' => ['Code invalide.']];

                return null;
            }

            $locked->update([
                'presence_confirmed_at' => now(),
                'presence_confirmed_by_user_id' => $provider->id,
                // Le code a servi : il ne doit plus pouvoir l'être.
                'presence_code_hash' => null,
                'presence_code_expires_at' => null,
                // Ce que valait la preuve, gravé avec elle : un litige se tranche sur une distance
                // mesurée, pas sur une conviction.
                'presence_confirmed_lat' => $lat,
                'presence_confirmed_lng' => $lng,
                'presence_confirmed_accuracy_m' => $accuracyM,
                'presence_confirmed_distance_m' => $geo['distance_m'],
                'presence_geo_verdict' => $geo['verdict'],
            ]);

            return $locked->fresh();
        });

        if ($failure !== null) {
            throw ValidationException::withMessages($failure);
        }

        return $confirmed;
    }

    /**
     * Confronte la position du scan au lieu de l'intervention.
     *
     * Ne lève rien : le refus est renvoyé pour que l'appelant le porte hors de la transaction.
     *
     * @return array{failure: array<string, list<string>>|null, verdict: string|null, distance_m: int|null}
     */
    protected function evaluatePosition(
        TripTrackingSession $session,
        ?float $lat,
        ?float $lng,
        ?float $accuracyM,
        bool $mocked,
    ): array {
        $pass = fn (string $verdict, ?int $distance = null): array => [
            'failure' => null, 'verdict' => $verdict, 'distance_m' => $distance,
        ];
        $refuse = fn (string $message, ?int $distance = null): array => [
            'failure' => ['position' => [$message]], 'verdict' => null, 'distance_m' => $distance,
        ];

        if (! Config::get('trip_tracking.presence_geo_check', true)) {
            return $pass(self::GEO_SKIPPED_DISABLED);
        }

        if ($mocked && Config::get('trip_tracking.presence_reject_mocked', true)) {
            return $refuse(
                'Votre téléphone signale une position simulée. Désactivez les positions fictives pour confirmer votre présence.'
            );
        }

        if ($lat === null || $lng === null) {
            // Sans exigence, on note l'absence plutôt que de la taire : une confirmation non
            // appuyée par une position doit rester reconnaissable après coup.
            return Config::get('trip_tracking.presence_require_position', true)
                ? $refuse('Position indisponible. Activez la localisation pour confirmer votre présence sur place.')
                : $pass(self::GEO_SKIPPED_NO_POSITION);
        }

        [$destLat, $destLng] = $this->resolveDestination($session);
        if ($destLat === null || $destLng === null) {
            // Rien à comparer : refuser ici punirait le prestataire d'une lacune du dossier.
            return $pass(self::GEO_SKIPPED_NO_DESTINATION);
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

        return $pass(self::GEO_PASSED, $distanceM);
    }

    /**
     * Rayon effectivement toléré, élargi si l'appareil annonce un relevé imprécis.
     *
     * Un relevé honnête mais mauvais mérite d'être jugé sur la précision qu'il annonce plutôt que
     * refusé sèchement. Mais cette valeur vient de l'appareil, donc de la partie contrôlée : sans
     * plafond, annoncer une précision de 50 km rouvrirait la porte en grand.
     */
    protected function allowedRadiusM(?float $accuracyM): int
    {
        $base = (int) Config::get('trip_tracking.presence_max_distance_m', 250);

        if ($accuracyM === null || $accuracyM <= 0) {
            return $base;
        }

        $cap = (int) Config::get('trip_tracking.presence_max_accuracy_allowance_m', 500);

        return max($base, min((int) round($accuracyM), $cap));
    }

    /**
     * Coordonnées du lieu de l'intervention.
     *
     * L'instantané de la session prime : c'est l'adresse telle qu'elle était au départ. Les
     * sessions ouvertes avant le remplissage des coordonnées de réservation l'ont vide ; la
     * réservation, elle, a pu être géocodée depuis. La relire évite de neutraliser le contrôle sur
     * ces sessions-là.
     *
     * @return array{0: float|null, 1: float|null}
     */
    protected function resolveDestination(TripTrackingSession $session): array
    {
        if ($session->destination_lat !== null && $session->destination_lng !== null) {
            return [(float) $session->destination_lat, (float) $session->destination_lng];
        }

        $booking = $session->booking;
        if (! $booking) {
            return [null, null];
        }

        $lat = $booking->getAttribute('destination_lat');
        $lng = $booking->getAttribute('destination_lng');

        return [
            $lat !== null ? (float) $lat : null,
            $lng !== null ? (float) $lng : null,
        ];
    }

    /**
     * Consigne une confirmation refusée pour position.
     *
     * Pas dans les colonnes `presence_confirmed_*` : leur nom dit que la présence a été établie,
     * y écrire un essai repoussé ferait mentir la ligne. Une tentative depuis 40 km est pourtant
     * exactement ce qu'une revue de fraude cherche — elle vit donc dans les métadonnées.
     *
     * @param  array{failure: array<string, list<string>>|null, verdict: string|null, distance_m: int|null}  $geo
     */
    protected function recordRejection(
        TripTrackingSession $session,
        ?float $lat,
        ?float $lng,
        ?float $accuracyM,
        array $geo,
    ): void {
        $meta = $session->metadata ?? [];
        $rejections = $meta['presence_geo_rejections'] ?? [];

        $rejections[] = [
            'at' => now()->toIso8601String(),
            'lat' => $lat,
            'lng' => $lng,
            'accuracy_m' => $accuracyM,
            'distance_m' => $geo['distance_m'],
            'reason' => $geo['failure']['position'][0] ?? null,
        ];

        $meta['presence_geo_rejections'] = array_slice($rejections, -self::MAX_REJECTIONS_KEPT);

        $session->update(['metadata' => $meta]);
    }

    /** Une distance ne se lit pas en mètres au-delà du kilomètre. */
    protected function humanDistance(int $meters): string
    {
        return $meters >= 1000
            ? str_replace('.', ',', (string) round($meters / 1000, 1)).' km'
            : $meters.' m';
    }

    protected function generatePlainCode(): string
    {
        return (string) random_int(100000, 999999);
    }
}
