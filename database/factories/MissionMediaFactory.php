<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MissionMediaFactory extends Factory
{
    protected $model = MissionMedia::class;

    public function definition(): array
    {
        return [
            'mission_id'          => fn () => Mission::factory()->create()->id,
            'uploaded_by_user_id' => fn () => User::factory()->create()->id,
            'media_type'          => fake()->randomElement(['photo', 'video', 'document']),
            'path'                => 'missions/' . fake()->uuid() . '.jpg',
            'caption'             => null,
            'taken_at'            => now(),
            'lat'                 => fake()->latitude(49.5, 51.5),
            'lng'                 => fake()->longitude(2.5, 6.5),
            'meta'                => null,
        ];
    }
}
