<?php

namespace Database\Factories;

use App\Models\OrganizationAccount;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_account_id' => OrganizationAccount::factory(),
            'user_id' => User::factory(),
            'starts_at' => now()->startOfDay()->addHours(8),
            'ends_at' => now()->startOfDay()->addHours(17),
            // Publié par défaut : un shift qui ne rend personne assignable ne sert à rien dans un
            // test qui mesure la disponibilité.
            'status' => Shift::STATUS_PUBLISHED,
        ];
    }

    public function planifie(): static
    {
        return $this->state(fn () => ['status' => Shift::STATUS_PLANNED]);
    }
}
