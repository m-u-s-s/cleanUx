<?php

namespace Database\Factories;

use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TradeFactory extends Factory
{
    protected $model = Trade::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'code' => strtoupper(Str::substr(Str::slug($name, ''), 0, 3)),
            'description' => fake()->sentence(),
            'icon' => fake()->randomElement(['wrench', 'paint-brush', 'bolt', 'home', 'sparkles']),
            'is_active' => true,
            'booking_form_schema' => null,
            'metadata' => null,
        ];
    }
}
