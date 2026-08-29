<?php

namespace Database\Factories;

use App\Models\PeerCode;
use App\Models\PeerRental;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<PeerCode> */
class PeerCodeFactory extends Factory
{
    protected $model = PeerCode::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'peer_rental_id' => PeerRental::factory(),
            'phase' => PeerCode::PHASE_REMISE,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addHours(12),
            'attempts' => 0,
        ];
    }
}
