<?php

namespace App\Services\Presence;

use App\Models\ProviderPresence;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\FaceCheck\Exceptions\FaceCheckRequiredException;
use App\Services\FaceCheck\FaceCheckGate;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Service de gestion de la présence provider (Uber-style online/offline).
 *
 * Workflow :
 *  - goOnline : provider démarre sa session (heartbeat starts ticking)
 *  - heartbeat : ping toutes les 30s-2min depuis l'app provider
 *  - goBusy : déclenché auto quand provider accepte une mission
 *  - goBreak : pause manuelle (lunch, etc.)
 *  - goOffline : fin de session manuelle ou auto-stale après N min
 *
 * Idempotent : transitions multiples vers même status sont no-op (mise à jour heartbeat seulement).
 */
class ProviderPresenceService
{
    public function presenceFor(User $provider): ProviderPresence
    {
        return ProviderPresence::query()->firstOrCreate(
            ['provider_user_id' => $provider->id],
            [
                'status' => ProviderPresence::STATUS_OFFLINE,
            ],
        );
    }

    public function goOnline(
        User $provider,
        ?float $lat = null,
        ?float $lng = null,
        ?int $radiusKm = null,
        ?string $deviceInfo = null,
    ): ProviderPresence {

        /*
         * LA PORTE DE LA MISE EN LIGNE — celle que Bolt a choisie, et c'est la bonne.
         *
         * « Passer en ligne » est le moment ou le prestataire declare vouloir travailler : c'est
         * la qu'il faut savoir qui il est, pas a la connexion. Se connecter pour consulter ses
         * revenus n'engage personne ; se declarer disponible envoie quelqu'un chez un client.
         *
         * `deviceInfo` sert de signal d'appareil : un telephone jamais vu declenche un controle
         * hors cadence, exactement comme Uber le fait sur les comptes multi-appareils.
         */
        $verdict = app(FaceCheckGate::class)->inspectProvider($provider, $deviceInfo);

        if (! $verdict->allowed()) {
            throw new FaceCheckRequiredException($verdict);
        }

        $presence = $this->presenceFor($provider);
        $now = now();

        $updates = [
            'status' => ProviderPresence::STATUS_ONLINE,
            'heartbeat_at' => $now,
            'last_online_at' => $now,
            'device_info' => $deviceInfo,
        ];

        if ($presence->status !== ProviderPresence::STATUS_ONLINE) {
            $updates['last_status_change_at'] = $now;
        }

        if ($lat !== null) {
            $updates['current_lat'] = $lat;
            $updates['current_lng'] = $lng;
        }
        if ($radiusKm !== null) {
            $updates['available_radius_km'] = $radiusKm;
        }

        $presence->update($updates);

        // F2 — keep the legacy ProviderProfile.is_online flag in sync. Dispatch/matching
        // (MatchingV2Service, AiDispatchService) filter ASAP candidates on is_online, so a
        // provider who goes online via presence-v2 must also flip is_online to be dispatchable.
        $this->syncLegacyOnlineFlag($provider, true);

        return $presence->fresh();
    }

    public function heartbeat(User $provider, ?float $lat = null, ?float $lng = null): ProviderPresence
    {
        $presence = $this->presenceFor($provider);

        // Heartbeat seulement valide si provider est online/busy/on_break
        if ($presence->status === ProviderPresence::STATUS_OFFLINE) {
            throw ValidationException::withMessages([
                'status' => ['Heartbeat impossible — provider offline. Appeler goOnline d\'abord.'],
            ]);
        }

        $now = now();
        $updates = ['heartbeat_at' => $now];

        // Compute minutes since last heartbeat → ajoute au compteur online si status online
        if ($presence->status === ProviderPresence::STATUS_ONLINE && $presence->heartbeat_at) {
            $minutesSince = (int) min(10, $presence->heartbeat_at->diffInMinutes($now));
            if ($minutesSince > 0) {
                $updates['online_minutes_today'] = (int) $presence->online_minutes_today + $minutesSince;
                $updates['online_minutes_week'] = (int) $presence->online_minutes_week + $minutesSince;
            }
        }

        if ($lat !== null) {
            $updates['current_lat'] = $lat;
            $updates['current_lng'] = $lng;
        }

        $presence->update($updates);

        return $presence->fresh();
    }

    public function goBusy(User $provider): ProviderPresence
    {
        return $this->transition($provider, ProviderPresence::STATUS_BUSY);
    }

    public function goBreak(User $provider): ProviderPresence
    {
        return $this->transition($provider, ProviderPresence::STATUS_ON_BREAK);
    }

    public function goOffline(User $provider): ProviderPresence
    {
        return $this->transition($provider, ProviderPresence::STATUS_OFFLINE);
    }

    /**
     * Auto-marque offline les providers actifs sans heartbeat depuis N minutes.
     * Retourne le nombre de providers transitionnés.
     */
    public function scanStale(?int $thresholdMinutes = null): int
    {
        $threshold = $thresholdMinutes ?? (int) Config::get('presence.stale_after_minutes', 5);
        $cutoff = now()->subMinutes($threshold);

        return DB::transaction(function () use ($cutoff) {
            $stales = ProviderPresence::query()
                ->whereIn('status', [
                    ProviderPresence::STATUS_ONLINE,
                    ProviderPresence::STATUS_BUSY,
                    ProviderPresence::STATUS_ON_BREAK,
                ])
                ->where(function ($q) use ($cutoff) {
                    $q->where('heartbeat_at', '<', $cutoff)
                        ->orWhereNull('heartbeat_at');
                })
                ->get();

            foreach ($stales as $presence) {
                $presence->update([
                    'status' => ProviderPresence::STATUS_OFFLINE,
                    'last_status_change_at' => now(),
                    'metadata' => array_merge($presence->metadata ?? [], [
                        'auto_offline_reason' => 'stale_heartbeat',
                        'auto_offline_at' => now()->toIso8601String(),
                    ]),
                ]);
            }

            // F2 — mirror auto-offline into the legacy is_online flag so stale providers
            // stop being dispatched.
            $staleIds = $stales->pluck('provider_user_id')->all();
            if ($staleIds && Schema::hasColumn('provider_profiles', 'is_online')) {
                ProviderProfile::query()->whereIn('user_id', $staleIds)->update(['is_online' => false]);
            }

            return $stales->count();
        });
    }

    /**
     * Helper pour dispatch matching : retourne les providers online disponibles.
     * Acceptés : status=online ET heartbeat récent (< stale threshold).
     */
    public function availableProviderIds(?int $thresholdMinutes = null): array
    {
        $threshold = $thresholdMinutes ?? (int) Config::get('presence.stale_after_minutes', 5);
        $cutoff = now()->subMinutes($threshold);

        return ProviderPresence::query()
            ->where('status', ProviderPresence::STATUS_ONLINE)
            ->where('heartbeat_at', '>=', $cutoff)
            ->pluck('provider_user_id')
            ->toArray();
    }

    protected function transition(User $provider, string $newStatus): ProviderPresence
    {
        $presence = $this->presenceFor($provider);

        if ($presence->status === $newStatus) {
            return $presence;
        }

        $updates = [
            'status' => $newStatus,
            'last_status_change_at' => now(),
        ];

        if ($newStatus !== ProviderPresence::STATUS_OFFLINE) {
            $updates['heartbeat_at'] = now();
        }

        $presence->update($updates);

        // F2 — only the 'online' status is dispatchable in the legacy model; busy / on_break /
        // offline all map to is_online = false.
        $this->syncLegacyOnlineFlag($provider, $newStatus === ProviderPresence::STATUS_ONLINE);

        return $presence->fresh();
    }

    /**
     * Mirror the v2 presence state into the legacy ProviderProfile.is_online flag that the
     * dispatch/matching path reads. No-op if the provider has no profile or the column is
     * absent. (F2)
     */
    protected function syncLegacyOnlineFlag(User $provider, bool $online): void
    {
        if (! Schema::hasColumn('provider_profiles', 'is_online')) {
            return;
        }

        ProviderProfile::query()
            ->where('user_id', $provider->id)
            ->update(['is_online' => $online]);
    }
}
