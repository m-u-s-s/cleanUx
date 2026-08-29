<?php

namespace Database\Factories;

use App\Models\PeerInspection;
use App\Models\PeerInspectionPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PeerInspectionPhoto> */
class PeerInspectionPhotoFactory extends Factory
{
    protected $model = PeerInspectionPhoto::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'peer_inspection_id' => PeerInspection::factory(),
            'path' => 'peer-inspections/'.$this->faker->uuid().'.jpg',
            'angle' => 'front',
            'taken_at' => now(),
        ];
    }
}
