<?php

namespace Database\Factories;

use App\Models\ContractTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractTemplateFactory extends Factory
{
    protected $model = ContractTemplate::class;

    public function definition(): array
    {
        return [
            'code' => 'CTR-' . fake()->unique()->bothify('??###'),
            'name' => fake()->randomElement(['Conditions Générales', 'SLA Entreprise', 'Contrat Provider', 'NDA']),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(['tos', 'sla', 'client_agreement', 'provider_agreement', 'nda']),
            'role' => fake()->randomElement(['client', 'provider', 'enterprise', 'all']),
            'version' => 1,
            'body_markdown' => "# {{ contract_name }}\n\nDate: {{ date }}\n\n## Article 1\n\n{{ company_name }} s'engage...",
            'variables' => ['contract_name', 'date', 'company_name', 'client_name'],
            'is_active' => true,
            'valid_from' => now()->toDateString(),
            'metadata' => [],
        ];
    }
}
