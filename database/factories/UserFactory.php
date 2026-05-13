<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'remember_token' => Str::random(10),
            'profile_photo_path' => null,
            'current_team_id' => null,
            'role' => 'client',
            'tva_number' => null,
            'duree_creneau' => 90,
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
            'organization_account_id' => null,
            'postal_code_id' => null,
            'primary_service_zone_id' => null,
            'phone' => null,
            'locale' => 'fr_BE',
            'timezone' => 'Europe/Brussels',
            'status' => 'active',
            'is_active' => true,
            'metadata' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'tva_number' => null,
            'duree_creneau' => 90,
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
        ]);
    }

    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'client',
            'tva_number' => null,
            'duree_creneau' => 90,
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
        ]);
    }

    public function premiumClient(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'client',
            'plan_type' => 'premium',
            'plan_status' => 'active',
            'premium_started_at' => now()->subMonth(),
            'premium_renewal_at' => now()->addMonth(),
        ]);
    }

    public function employe(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'employe',
            'tva_number' => null,
            'duree_creneau' => 90,
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
        ]);
    }

    public function entreprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'entreprise',
            'tva_number' => fake()->unique()->numerify('BE0#########'),
            'duree_creneau' => 90,
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);
    }

    public function withPersonalTeam(callable $callback = null): static
    {
        $teamClass = '\\App\\Models\\Team';

        if (! Features::hasTeamFeatures() || ! class_exists($teamClass) || ! method_exists($teamClass, 'factory')) {
            return $this->state([]);
        }

        return $this->has(
            $teamClass::factory()
                ->state(fn (array $attributes, User $user) => [
                    'name' => $user->name . '\'s Team',
                    'user_id' => $user->id,
                    'personal_team' => true,
                ])
                ->when(is_callable($callback), $callback),
            'ownedTeams'
        );
    }
}
