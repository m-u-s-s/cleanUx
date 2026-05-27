<?php

namespace Database\Factories;

use App\Models\ProviderOnboardingDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderOnboardingDocumentFactory extends Factory
{
    protected $model = ProviderOnboardingDocument::class;

    public function definition(): array
    {
        return [
            'user_id' => fn () => User::factory()->employe()->create()->id,
            'document_type' => fake()->randomElement([
                ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
                ProviderOnboardingDocument::TYPE_INSURANCE,
                ProviderOnboardingDocument::TYPE_DIPLOMA,
            ]),
            'status' => ProviderOnboardingDocument::STATUS_PENDING,
            'file_path' => 'onboarding/' . fake()->uuid() . '.pdf',
            'file_name' => fake()->slug(2) . '.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(10000, 2000000),
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'expires_at' => null,
            'metadata' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ProviderOnboardingDocument::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ProviderOnboardingDocument::STATUS_REJECTED,
            'rejection_reason' => 'Document not readable',
            'reviewed_at' => now(),
        ]);
    }
}
