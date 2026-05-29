<?php

namespace Database\Factories;

use App\Models\ContractDocument;
use App\Models\ContractTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ContractDocumentFactory extends Factory
{
    protected $model = ContractDocument::class;

    public function definition(): array
    {
        return [
            'template_id' => ContractTemplate::factory(),
            'code' => 'DOC-'.Str::upper(Str::random(8)),
            'user_id' => User::factory(),
            'body_rendered_html' => '<p>Contract body rendered for testing.</p>',
            'pdf_path' => null,
            'status' => ContractDocument::STATUS_SIGNED,
            'generated_at' => now()->subHour(),
            'expires_at' => now()->addYear(),
            'metadata' => [],
        ];
    }

    public function pendingSignature(): static
    {
        return $this->state(fn () => [
            'status' => ContractDocument::STATUS_PENDING_SIGNATURE,
        ]);
    }
}
