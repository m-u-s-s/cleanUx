<?php

namespace Database\Factories;

use App\Models\OnboardingJourney;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OnboardingJourney>
 */
class OnboardingJourneyFactory extends Factory
{
    protected $model = OnboardingJourney::class;

    public function definition(): array
    {
        $role = fake()->randomElement([
            OnboardingJourney::ROLE_CLIENT,
            OnboardingJourney::ROLE_PROVIDER,
            OnboardingJourney::ROLE_ENTERPRISE,
        ]);
        $name = ucfirst($role).' onboarding '.fake()->unique()->numerify('v###');

        return [
            'code' => Str::slug($name),
            'name' => $name,
            'description' => fake()->sentence(),
            'role' => $role,
            'is_active' => true,
            'version' => 1,
            'applies_to_country' => null,
            'metadata' => null,
        ];
    }

    public function forProvider(): static
    {
        return $this->state(['role' => OnboardingJourney::ROLE_PROVIDER]);
    }

    public function forClient(): static
    {
        return $this->state(['role' => OnboardingJourney::ROLE_CLIENT]);
    }
}
