<?php

namespace Database\Factories;

use App\Models\PeerClaim;
use App\Models\PeerRental;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PeerClaim> */
class PeerClaimFactory extends Factory
{
    protected $model = PeerClaim::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'peer_rental_id' => PeerRental::factory(),
            'opened_by' => User::factory(),
            'kind' => PeerClaim::MOTIF_DOMMAGE,
            'amount_cents' => 15000,
            'status' => PeerClaim::STATUT_OUVERTE,
            'description' => $this->faker->sentence(10),
        ];
    }
}
