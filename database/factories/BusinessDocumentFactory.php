<?php

namespace Database\Factories;

use App\Models\BusinessDocument;
use App\Models\BusinessEntity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessDocumentFactory extends Factory
{
    protected $model = BusinessDocument::class;

    public function definition(): array
    {
        return [
            'entity_id' => fn () => BusinessEntity::factory()->create()->id,
            'document_type' => fake()->randomElement(['kbis', 'statutes', 'id_card', 'bank_rib']),
            'file_path' => 'documents/' . fake()->uuid() . '.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(10000, 5000000),
            'uploaded_at' => now(),
            'uploaded_by_user_id' => fn () => User::factory()->create()->id,
            'status' => BusinessDocument::STATUS_PENDING,
            'expires_at' => null,
            'reviewed_at' => null,
            'reviewed_by_user_id' => null,
            'rejection_reason' => null,
            'metadata' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => BusinessDocument::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }
}
