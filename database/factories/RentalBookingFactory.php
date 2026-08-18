<?php

namespace Database\Factories;

use App\Models\RentalBooking;
use App\Models\RentalVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RentalBooking> */
class RentalBookingFactory extends Factory
{
    protected $model = RentalBooking::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $debut = now()->addDays(3)->setTime(9, 0);

        return [
            'reference' => RentalBooking::genererUneReference(),
            'rental_vehicle_id' => fn () => RentalVehicle::factory(),
            'starts_at' => $debut,
            'ends_at' => $debut->copy()->addDays(3),
            'days' => 3,
            'driver_first_name' => fake()->firstName(),
            'driver_last_name' => fake()->lastName(),
            'driver_birthdate' => now()->subYears(35)->toDateString(),
            'driver_email' => fake()->safeEmail(),
            'license_number' => strtoupper(fake()->bothify('??######')),
            'license_country' => 'BE',
            'license_issued_at' => now()->subYears(10)->toDateString(),
            'protection' => RentalVehicle::PROTECTION_SANS,
            'daily_price_cents' => 5000,
            'subtotal_cents' => 15000,
            'waiver_total_cents' => 0,
            'total_cents' => 15000,
            'deposit_cents' => 80000,
            'currency' => 'EUR',
            'status' => RentalBooking::STATUT_CONFIRMEE,
            'confirmed_at' => now(),
        ];
    }

    public function brouillon(): static
    {
        return $this->state(fn () => ['status' => RentalBooking::STATUT_BROUILLON, 'confirmed_at' => null]);
    }

    public function annulee(): static
    {
        return $this->state(fn () => ['status' => RentalBooking::STATUT_ANNULEE, 'cancelled_at' => now()]);
    }
}
