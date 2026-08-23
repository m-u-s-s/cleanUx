<?php

namespace App\Services\TripTracking;

use App\Models\TripTrackingSession;
use App\Models\User;
use App\Services\Geo\OnSiteVerifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Confirmation de présence du prestataire, par code affiché chez le client. */
class PresenceCodeService
{
    /** Durée de vie d'un code, en minutes. Assez pour scanner, trop peu pour transmettre. */
    public const TTL_MINUTES = 10;

    /** Au-delà, le code est brûlé et il faut en demander un neuf. */
    public const MAX_ATTEMPTS = 5;

    /** Verdicts, repris de {@see OnSiteVerifier}. */
    public const GEO_PASSED = OnSiteVerifier::PASSED;

    public const GEO_SKIPPED_DISABLED = OnSiteVerifier::SKIPPED_DISABLED;

    public const GEO_SKIPPED_NO_DESTINATION = OnSiteVerifier::SKIPPED_NO_DESTINATION;

    public const GEO_SKIPPED_NO_POSITION = OnSiteVerifier::SKIPPED_NO_POSITION;

    /** Au-delà, les refus les plus anciens sont oubliés : une série d'essais ne doit pas gonfler la ligne sans fin. */
    protected const MAX_REJECTIONS_KEPT = 10;

    public function __construct(
        protected OnSiteVerifier $onSite,
    ) {}

    /**
     * Délivre le code courant du client, ou en forge un neuf s'il n'y en a pas d'exploitable.
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
         * Le refus est RENVOYÉ, jamais levé depuis la transaction : une exception la ferait annuler, et avec elle l'incrément du compteur comme la trace du refus.
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

            /** Le contrôle de position passe AVANT le compteur de tentatives, délibérément. */
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
     * @return array{failure: array<string, list<string>>|null, verdict: string|null, distance_m: int|null}
     */
    protected function evaluatePosition(
        TripTrackingSession $session,
        ?float $lat,
        ?float $lng,
        ?float $accuracyM,
        bool $mocked,
    ): array {
        [$destLat, $destLng] = $this->resolveDestination($session);

        return $this->onSite->verify($lat, $lng, $destLat, $destLng, $accuracyM, $mocked);
    }

    /**
     * Coordonnées du lieu de l'intervention.
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

    protected function generatePlainCode(): string
    {
        return (string) random_int(100000, 999999);
    }
}
