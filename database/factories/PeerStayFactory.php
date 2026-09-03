<?php

namespace Database\Factories;

use App\Models\PeerStay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PeerStay> */
class PeerStayFactory extends Factory
{
    protected $model = PeerStay::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'status' => PeerStay::STATUT_BROUILLON,
            'title' => 'Appartement '.$this->faker->streetName(),
            'description' => $this->faker->sentence(12),
            'property_type' => 'appartement',
            'space_type' => 'entire',
            'max_guests' => 4,
            'bedrooms' => 2,
            'beds' => 2,
            'bathrooms' => 1,
            'surface_m2' => 65,
            'amenities' => ['wifi', 'cuisine', 'lave-linge'],
            'nightly_price_cents' => 9000,
            'currency' => 'EUR',
            'cleaning_fee_cents' => 2500,
            'guests_included' => 2,
            'extra_guest_price_cents' => 1500,
            'discount_7_days_percent' => 10,
            'discount_28_days_percent' => 25,
            'deposit_cents' => 20000,
            'min_nights' => 2,
            'max_nights' => 60,
            'instant_booking' => false,
            'cancellation_policy' => 'moderee',
            'address_line' => $this->faker->streetAddress(),
            'postal_code' => '1000',
            'city' => 'Bruxelles',
            'country_code' => 'BE',
        ];
    }

    /** Une annonce en ligne, visible des voyageurs. */
    public function publiee(): static
    {
        return $this->state(fn () => [
            'status' => PeerStay::STATUT_PUBLIE,
            'published_at' => now(),
        ]);
    }

    /** Une annonce qui attend la revue d'un administrateur. */
    public function enRevue(): static
    {
        return $this->state(fn () => ['status' => PeerStay::STATUT_EN_REVUE]);
    }

    public function instantanee(): static
    {
        return $this->state(fn () => ['instant_booking' => true]);
    }
}
