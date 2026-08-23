<?php

namespace App\Services\Rental;

use App\Models\RentalBooking;
use App\Models\RentalVehicle;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** QUELLES VOITURES SONT RÉELLEMENT DISPONIBLES — c'est la règle qui décide de tout le catalogue. */
class RentalAvailability
{
    /**
     * Les véhicules proposables, filtrés et triés.
     *
     * @param  array{category?: string|null, transmission?: string|null, fuel?: string|null, seats_min?: int|null, price_max_cents?: int|null}  $filtres
     * @return Collection<int, RentalVehicle>
     */
    public function catalogue(?CarbonInterface $debut = null, ?CarbonInterface $fin = null, array $filtres = []): Collection
    {
        return $this->requeteDuCatalogue($debut, $fin, $filtres)
            ->with(['galerie', 'rotation360', 'pickupPoint'])
            ->get();
    }

    /** Combien de voitures sont proposables — la question que pose l'entrée du catalogue. */
    public function combienDeVehiculesProposables(?CarbonInterface $debut = null, ?CarbonInterface $fin = null): int
    {
        return $this->requeteDuCatalogue($debut, $fin)->count();
    }

    /** Ce véhicule précis est-il libre sur cette période ? */
    public function estLibre(RentalVehicle $vehicule, CarbonInterface $debut, CarbonInterface $fin, ?int $saufReservationId = null): bool
    {
        return ! RentalBooking::query()
            ->where('rental_vehicle_id', $vehicule->id)
            ->quiBloque()
            ->when($saufReservationId, fn (Builder $q) => $q->whereKeyNot($saufReservationId))
            ->where('starts_at', '<', $fin)
            ->where('ends_at', '>', $debut)
            ->exists();
    }

    /**
     * Les valeurs de filtre qui ont réellement des voitures derrière elles.
     *
     * @return array{categories: list<string>, transmissions: list<string>, fuels: list<string>, prix_max_cents: int}
     */
    public function optionsDeFiltre(?CarbonInterface $debut = null, ?CarbonInterface $fin = null): array
    {
        $vehicules = $this->requeteDuCatalogue($debut, $fin)
            ->get(['category', 'transmission', 'fuel', 'daily_price_cents']);

        return [
            'categories' => $vehicules->pluck('category')->filter()->unique()->sort()->values()->all(),
            'transmissions' => $vehicules->pluck('transmission')->filter()->unique()->sort()->values()->all(),
            'fuels' => $vehicules->pluck('fuel')->filter()->unique()->sort()->values()->all(),
            'prix_max_cents' => (int) ($vehicules->max('daily_price_cents') ?? 0),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * LA REQUÊTE UNIQUE DONT TOUT DÉCOULE.
     *
     * @param  array<string, mixed>  $filtres
     * @return Builder<RentalVehicle>
     */
    private function requeteDuCatalogue(?CarbonInterface $debut, ?CarbonInterface $fin, array $filtres = []): Builder
    {
        return RentalVehicle::query()
            ->actif()
            ->libreEntre($debut, $fin)
            ->when($filtres['category'] ?? null, fn (Builder $q, $v) => $q->where('category', $v))
            ->when($filtres['transmission'] ?? null, fn (Builder $q, $v) => $q->where('transmission', $v))
            ->when($filtres['fuel'] ?? null, fn (Builder $q, $v) => $q->where('fuel', $v))
            ->when($filtres['seats_min'] ?? null, fn (Builder $q, $v) => $q->where('seats', '>=', (int) $v))
            // ZÉRO EST UNE VALEUR, ET C'EST LE PIÈGE HABITUEL DE CE DÉPÔT.
            ->when(($filtres['price_max_cents'] ?? 0) > 0,
                fn (Builder $q) => $q->where('daily_price_cents', '<=', (int) $filtres['price_max_cents']))
            ->ordonne();
    }
}
