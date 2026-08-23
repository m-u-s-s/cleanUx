<?php

namespace App\Services\Availability;

use App\Models\AvailabilitySlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** LA DISPONIBILITÉ QU'ON A EN SORTANT DE L'INSCRIPTION. */
class DefaultAvailabilityProvisioner
{
    public const DEFAULT_START = '08:00:00';

    public const DEFAULT_END = '17:00:00';

    /**
     * Les sept jours, dans la convention de `AvailabilitySlot` : 0 = dimanche … 6 = samedi.
     *
     * @var list<int>
     */
    public const DEFAULT_WEEKDAYS = [
        AvailabilitySlot::WEEKDAY_MONDAY,
        AvailabilitySlot::WEEKDAY_TUESDAY,
        AvailabilitySlot::WEEKDAY_WEDNESDAY,
        AvailabilitySlot::WEEKDAY_THURSDAY,
        AvailabilitySlot::WEEKDAY_FRIDAY,
        AvailabilitySlot::WEEKDAY_SATURDAY,
        AvailabilitySlot::WEEKDAY_SUNDAY,
    ];

    /**
     * Dote un prestataire de ses créneaux par défaut.
     *
     * @return int le nombre de créneaux créés — 0 si le prestataire avait déjà une semaine à lui
     */
    public function provision(User $provider): int
    {
        if (! $provider->isEmploye()) {
            return 0;
        }

        return DB::transaction(function () use ($provider): int {
            // Verrou de lecture sur les créneaux existants : deux inscriptions rejouées en parallèle — un double clic, un webhook réémis — verraient toutes deux « aucun créneau » et en créeraient quatorze.
            $dejaChoisi = AvailabilitySlot::query()
                ->where('provider_user_id', $provider->id)
                ->lockForUpdate()
                ->exists();

            if ($dejaChoisi) {
                return 0;
            }

            $fuseau = (string) config('availability.default_timezone', config('app.timezone', 'Europe/Brussels'));
            $maintenant = now();

            $lignes = [];

            foreach (self::DEFAULT_WEEKDAYS as $jour) {
                $lignes[] = [
                    'provider_user_id' => $provider->id,
                    'weekday' => $jour,
                    'start_time' => self::DEFAULT_START,
                    'end_time' => self::DEFAULT_END,
                    'valid_from' => null,
                    'valid_until' => null,
                    'timezone' => $fuseau,
                    'is_active' => true,
                    // Tracé : distinguer plus tard un horaire choisi d'un horaire hérité du défaut.
                    'metadata' => json_encode(['source' => 'default_on_registration']),
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ];
            }

            AvailabilitySlot::query()->insert($lignes);

            return count($lignes);
        });
    }
}
