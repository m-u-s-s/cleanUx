<?php

namespace Database\Factories;

use App\Models\PeerStay;
use App\Models\PeerStayMedium;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PeerStayMedium> */
class PeerStayMediumFactory extends Factory
{
    protected $model = PeerStayMedium::class;

    public function definition(): array
    {
        return [
            'peer_stay_id' => PeerStay::factory(),
            'path' => 'peer-stays/'.$this->faker->uuid().'.jpg',
            'caption' => null,
            'position' => 0,
        ];
    }
}
