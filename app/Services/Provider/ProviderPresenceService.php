<?php

namespace App\Services\Provider;

use App\Events\Dispatch\ProviderPresenceChanged;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 11 — Service de gestion de la presence prestataire.
 *
 * Inspiré d'Uber : un driver passe ONLINE pour recevoir des courses, OFFLINE
 * pour ne plus en recevoir. La position GPS est mise à jour à chaque heartbeat.
 *
 * Workflow :
 *   1. App mobile → POST /api/provider/presence/online (lat, lng)
 *   2. Service met is_online=true, went_online_at=now, broadcast event
 *   3. Toutes les 30s → POST /api/provider/presence/heartbeat (lat, lng)
 *   4. Service met à jour current_lat/lng + last_heartbeat_at
 *   5. Si pas de heartbeat depuis 5 min → CleanStaleOnlineProvidersCommand
 *      met automatiquement is_online=false (l'app a probablement crashé/perdu réseau)
 *
 * Configuration :
 *   - HEARTBEAT_TIMEOUT_MINUTES (défaut 5) : seuil d'auto-offline
 */
class ProviderPresenceService
{
    public const HEARTBEAT_TIMEOUT_MINUTES = 5;

    /**
     * Le prestataire passe online et déclare sa position courante.
     */
    public function goOnline(User $user, float $lat, float $lng, array $meta = []): ProviderProfile
    {
        $profile = $this->ensureProfile($user);

        return DB::transaction(function () use ($profile, $lat, $lng, $meta) {
            $wasOnline = (bool) $profile->is_online;

            $profile->update([
                'is_online'         => true,
                'went_online_at'    => $wasOnline && $profile->went_online_at
                    ? $profile->went_online_at
                    : now(),
                'last_heartbeat_at' => now(),
                'current_lat'       => $lat,
                'current_lng'       => $lng,
                'last_location_at'  => now(),
                'presence_meta'     => $meta ?: null,
            ]);

            if (! $wasOnline) {
                event(new ProviderPresenceChanged($profile->user_id, true));
            }

            return $profile->fresh();
        });
    }

    /**
     * Le prestataire passe offline volontairement.
     */
    public function goOffline(User $user): ProviderProfile
    {
        $profile = $this->ensureProfile($user);

        return DB::transaction(function () use ($profile) {
            $wasOnline = (bool) $profile->is_online;

            $profile->update([
                'is_online'        => false,
                'went_offline_at'  => now(),
            ]);

            if ($wasOnline) {
                event(new ProviderPresenceChanged($profile->user_id, false));
            }

            return $profile->fresh();
        });
    }

    /**
     * Heartbeat : signale "je suis toujours là" et met à jour la position GPS.
     *
     * Si l'utilisateur n'est PAS online, on ne fait rien (un heartbeat sur un
     * profil offline est suspect — il faut explicitement go_online d'abord).
     */
    public function heartbeat(User $user, float $lat, float $lng, array $meta = []): ?ProviderProfile
    {
        $profile = $this->ensureProfile($user);

        if (! $profile->is_online) {
            return null;
        }

        $profile->update([
            'last_heartbeat_at' => now(),
            'current_lat'       => $lat,
            'current_lng'       => $lng,
            'last_location_at'  => now(),
            'presence_meta'     => $meta ?: $profile->presence_meta,
        ]);

        return $profile->fresh();
    }

    /**
     * Désactive automatiquement les prestataires "online" qui n'ont pas envoyé
     * de heartbeat depuis HEARTBEAT_TIMEOUT_MINUTES. Appelé par cron via
     * CleanStaleOnlinePresenceCommand.
     *
     * @return int Nombre de profils basculés en offline.
     */
    public function cleanStalePresence(): int
    {
        $threshold = now()->subMinutes(self::HEARTBEAT_TIMEOUT_MINUTES);

        $stale = ProviderProfile::query()
            ->where('is_online', true)
            ->where(function (Builder $q) use ($threshold) {
                $q->whereNull('last_heartbeat_at')
                    ->orWhere('last_heartbeat_at', '<', $threshold);
            })
            ->get();

        foreach ($stale as $profile) {
            $profile->update([
                'is_online'       => false,
                'went_offline_at' => now(),
            ]);

            event(new ProviderPresenceChanged($profile->user_id, false));

            Log::info('Provider auto-offline (stale heartbeat)', [
                'provider_user_id'    => $profile->user_id,
                'last_heartbeat_at'   => $profile->last_heartbeat_at?->toIso8601String(),
            ]);
        }

        return $stale->count();
    }

    /**
     * Retourne les prestataires online dans un rayon (en km) autour d'un point.
     * Utilise la formule haversine en SQL pour scale jusqu'à ~10K profils
     * sans recourir à PostGIS.
     */
    public function findOnlineNear(float $lat, float $lng, float $radiusKm = 50)
    {
        $query = ProviderProfile::query()
            ->where('is_online', true)
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng');

        if (DB::connection()->getDriverName() === 'sqlite') {
            return $query
                ->get()
                ->filter(function (ProviderProfile $profile) use ($lat, $lng, $radiusKm) {
                    return $this->distanceKm(
                        $lat,
                        $lng,
                        (float) $profile->current_lat,
                        (float) $profile->current_lng
                    ) <= $radiusKm;
                })
                ->values();
        }

        return $query
            ->whereRaw(
                '(6371 * acos(cos(radians(?)) * cos(radians(current_lat)) * cos(radians(current_lng) - radians(?)) + sin(radians(?)) * sin(radians(current_lat)))) <= ?',
                [$lat, $lng, $lat, $radiusKm]
            )
            ->get();
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    protected function ensureProfile(User $user): ProviderProfile
    {
        $profile = ProviderProfile::where('user_id', $user->id)->first();

        if (! $profile) {
            throw new \DomainException(
                "L'utilisateur {$user->id} n'a pas de ProviderProfile. " .
                    "Il doit être prestataire pour utiliser le système de presence."
            );
        }

        return $profile;
    }
}
