<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\MissionVerificationCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class MissionVerificationCodeFactory extends Factory
{
    protected $model = MissionVerificationCode::class;

    public function definition(): array
    {
        return [
            'mission_id' => Mission::factory(),
            'code_type' => 'start',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(20),
            'validated_by_user_id' => null,
            'validated_at' => null,
            'attempts' => 0,
            'is_consumed' => false,
        ];
    }

    public function startCode(): static
    {
        return $this->state(fn () => [
            'code_type' => 'start',
        ]);
    }

    public function endCode(): static
    {
        return $this->state(fn () => [
            'code_type' => 'end',
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn () => [
            'is_consumed' => true,
            'validated_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinutes(10),
        ]);
    }
}