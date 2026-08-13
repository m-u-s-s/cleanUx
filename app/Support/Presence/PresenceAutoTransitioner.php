<?php

namespace App\Support\Presence;

use App\Models\Booking;
use App\Models\ProviderPresence;
use App\Models\User;
use App\Services\Presence\ProviderPresenceService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Helper soft-fail pour auto-transitionner la presence du provider
 * en réaction aux changements de status booking.
 *
 * Workflow :
 *   - Booking → en_cours (provider start mission)   → presence::goBusy
 *   - Booking → termine/cancelled                   → presence::goOnline (si auto_online_on_mission_complete)
 *
 * Appelé depuis BookingObserver::saved. Skip silencieusement si :
 *   - module Presence pas installé (table absente)
 *   - feature désactivée (config)
 *   - provider n'a pas de presence record (pas online actuellement)
 */
class PresenceAutoTransitioner
{
    public static function bookingStarted(Booking $booking): void
    {
        if (! self::isEnabled()) {
            return;
        }
        $provider = self::resolveProvider($booking);
        if (! $provider) {
            return;
        }

        try {
            $presence = ProviderPresence::query()
                ->where('provider_user_id', $provider->id)
                ->first();
            // Ne transition que si actuellement online (sinon ça serait reactivate un provider offline)
            if (! $presence || $presence->status !== ProviderPresence::STATUS_ONLINE) {
                return;
            }
            app(ProviderPresenceService::class)->goBusy($provider);
        } catch (\Throwable $e) {
            Log::warning('[presence_auto] bookingStarted failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function bookingEnded(Booking $booking): void
    {
        if (! self::isEnabled()) {
            return;
        }
        if (! (bool) Config::get('presence.auto_online_on_mission_complete', true)) {
            return;
        }
        $provider = self::resolveProvider($booking);
        if (! $provider) {
            return;
        }

        try {
            $presence = ProviderPresence::query()
                ->where('provider_user_id', $provider->id)
                ->first();
            // Ne transitionner busy→online (laisser break/offline tels quels)
            if (! $presence || $presence->status !== ProviderPresence::STATUS_BUSY) {
                return;
            }
            app(ProviderPresenceService::class)->goOnline($provider);
        } catch (\Throwable $e) {
            Log::warning('[presence_auto] bookingEnded failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected static function isEnabled(): bool
    {
        if (! Schema::hasTable('provider_presence')) {
            return false;
        }

        return (bool) Config::get('presence.enabled', true);
    }

    /**
     * L'INTERVENANT VIT SUR LA MISSION, pas seulement sur la réservation.
     *
     * Cette résolution ne lisait que des colonnes de `bookings`. Or une mission confiée à une
     * SOCIÉTÉ prestataire, ou réassignée après coup, porte son responsable sur
     * `missions.lead_provider_user_id` sans que la réservation en sache rien : le module de
     * présence ne trouvait alors personne et se taisait — dans les deux sens.
     *
     * La conséquence n'était pas cosmétique. `bookingEnded()` fait repasser `busy → online`, et
     * `CandidateFinder` exige `online` : un prestataire basculé en occupé par `DispatchEngine`
     * mais introuvable ici restait occupé indéfiniment, et cessait donc en silence de recevoir la
     * moindre offre — après sa PREMIÈRE course.
     *
     * LA MISSION PASSE DEVANT, et ce n'est pas un détail d'ordre.
     *
     * `bookings.employe_id` est le prestataire de la COMMANDE ; `missions.lead_provider_user_id`
     * est celui qui INTERVIENT. Les deux divergent dès qu'une mission est réassignée — la
     * réservation garde l'ancien nom — et dès qu'une société confie le travail à l'un de ses
     * salariés. Lire la réservation d'abord basculait alors la présence de quelqu'un qui n'y va
     * pas, en laissant occupé celui qui y est vraiment.
     *
     * Les colonnes de la réservation restent en repli : quand la mission ne désigne personne,
     * elles sont la seule information disponible, et le comportement des parcours qui n'ont jamais
     * divergé est inchangé. Cet ordre est celui de `Booking::intervenantId()`, et n'est pas
     * recopié ici : une règle d'identité qui existe en deux exemplaires finit toujours par
     * diverger.
     */
    protected static function resolveProvider(Booking $booking): ?User
    {
        return $booking->intervenant();
    }
}
