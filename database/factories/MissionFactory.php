<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Mission;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionFactory extends Factory
{
    protected $model = Mission::class;

    public function definition(): array
    {
        return [
            'rendez_vous_id' => function () {
                return Booking::factory()->create()->id;
            },

            'organization_account_id' => function (array $attributes) {
                return Booking::query()->find($attributes['rendez_vous_id'])?->organization_account_id;
            },

            'organization_site_id' => function (array $attributes) {
                return Booking::query()->find($attributes['rendez_vous_id'])?->organization_site_id;
            },

            'service_catalog_id' => function (array $attributes) {
                return Booking::query()->find($attributes['rendez_vous_id'])?->service_catalog_id;
            },

            'service_zone_id' => function (array $attributes) {
                return Booking::query()->find($attributes['rendez_vous_id'])?->service_zone_id;
            },

            'lead_employee_id' => function (array $attributes) {
                return Booking::query()->find($attributes['rendez_vous_id'])?->employe_id;
            },

            'status' => function (array $attributes) {
                $rdv = Booking::query()->find($attributes['rendez_vous_id']);

                return $rdv?->employe_id ? 'assigned' : 'planned';
            },

            'mission_type' => function (array $attributes) {
                $rdv = Booking::query()->find($attributes['rendez_vous_id']);

                return $rdv?->organization_account_id ? 'enterprise' : 'standard';
            },

            'planned_start_at' => function (array $attributes) {
                $rdv = Booking::query()->find($attributes['rendez_vous_id']);

                if (! $rdv?->date || ! $rdv?->heure) {
                    return now()->addDay()->setTime(9, 0);
                }

                return self::combine($rdv->date, $rdv->heure) ?? now()->addDay()->setTime(9, 0);
            },

            'planned_end_at' => function (array $attributes) {
                $rdv = Booking::query()->find($attributes['rendez_vous_id']);

                if (! $rdv?->date || ! $rdv?->heure) {
                    return now()->addDay()->setTime(11, 0);
                }

                $debut = self::combine($rdv->date, $rdv->heure);

                if ($debut === null) {
                    return now()->addDay()->setTime(11, 0);
                }

                $minutes = (int) ($rdv->duree_estimee ?? $rdv->duree ?? 120);

                return date('Y-m-d H:i:s', strtotime('+'.$minutes.' minutes', strtotime($debut)));
            },

            'actual_start_at' => null,
            'actual_end_at' => null,
            'requires_start_code' => true,
            'requires_end_code' => true,
            'client_presence_confirmed' => false,
            'started_by_user_id' => null,
            'closed_by_user_id' => null,
            'start_lat' => null,
            'start_lng' => null,
            'end_lat' => null,
            'end_lng' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function planned(): static
    {
        return $this->state(fn () => [
            'status' => 'planned',
            'actual_start_at' => null,
            'actual_end_at' => null,
        ]);
    }

    public function assigned(): static
    {
        return $this->state(fn () => [
            'status' => 'assigned',
        ]);
    }

    public function enRoute(): static
    {
        return $this->state(fn () => [
            'status' => 'en_route',
        ]);
    }

    public function arrived(): static
    {
        return $this->state(fn () => [
            'status' => 'arrived',
        ]);
    }

    public function started(): static
    {
        return $this->state(fn () => [
            'status' => 'started',
            'actual_start_at' => now(),
            'client_presence_confirmed' => true,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'actual_start_at' => now()->subHours(2),
            'actual_end_at' => now()->subHour(),
            'client_presence_confirmed' => true,
        ]);
    }

    public function enterprise(): static
    {
        return $this->state(fn () => [
            'mission_type' => 'enterprise',
        ]);
    }

    /**
     * Assemble une date et une heure venues de la réservation.
     *
     * `date` est casté en Carbon : le convertir en chaîne rend « 2026-08-17 00:00:00 », et le
     * coller à « 14:30:00 » produisait « 2026-08-17 00:00:00 14:30:00 » — illisible pour
     * `strtotime`, qui rend `false`. Or `date(..., false)` fabrique 1970-01-01, une valeur hors
     * des bornes d'une colonne TIMESTAMP MySQL : toutes les missions de test échouaient à
     * l'insertion, et seulement sur MySQL. SQLite acceptait l'époque sans broncher.
     *
     * On ne garde donc que la PARTIE date, exactement comme le service de production.
     */
    private static function combine(mixed $date, mixed $heure): ?string
    {
        if (! $date || ! $heure) {
            return null;
        }

        $partieDate = $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : substr((string) $date, 0, 10);

        $partieHeure = $heure instanceof \DateTimeInterface
            ? $heure->format('H:i:s')
            : substr((string) $heure, 0, 8);

        $timestamp = strtotime($partieDate.' '.$partieHeure);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }
}
