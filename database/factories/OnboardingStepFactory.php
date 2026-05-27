<?php

namespace Database\Factories;

use App\Models\OnboardingJourney;
use App\Models\OnboardingStep;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OnboardingStep>
 */
class OnboardingStepFactory extends Factory
{
    protected $model = OnboardingStep::class;

    public function definition(): array
    {
        $type = fake()->randomElement([
            OnboardingStep::TYPE_FORM,
            OnboardingStep::TYPE_KYC_CHECK,
            OnboardingStep::TYPE_PROFILE_COMPLETE,
            OnboardingStep::TYPE_DOCUMENT_UPLOAD,
        ]);

        return [
            'journey_id'       => OnboardingJourney::factory(),
            'position'         => fake()->numberBetween(1, 10),
            'code'             => Str::slug(fake()->unique()->words(3, true)),
            'label'            => fake()->sentence(3),
            'description'      => fake()->sentence(),
            'step_type'        => $type,
            'required'         => true,
            'validator_class'  => null,
            'depends_on'       => null,
            'is_skippable'     => false,
            'metadata'         => null,
        ];
    }

    public function skippable(): static
    {
        return $this->state(['is_skippable' => true, 'required' => false]);
    }
}
