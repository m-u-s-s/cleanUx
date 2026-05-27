<?php

namespace Database\Factories;

use App\Models\ContractDocument;
use App\Models\ContractSignature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractSignatureFactory extends Factory
{
    protected $model = ContractSignature::class;

    public function definition(): array
    {
        $body   = '<p>Contract body for test.</p>';
        $name   = fake()->name();
        $date   = now()->toDateString();

        return [
            'document_id'           => ContractDocument::factory(),
            'signer_user_id'        => User::factory(),
            'signer_name'           => $name,
            'signer_email_hash'     => hash('sha256', fake()->email()),
            'signature_data'        => base64_encode($name . ' signed'),
            'signature_hash'        => hash('sha256', $body . $name . $date),
            'ip_hash'               => hash('sha256', fake()->ipv4()),
            'user_agent_short'      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0)',
            'terms_version'         => '2024-01-01',
            'country_code'          => fake()->randomElement(['BE', 'FR', 'NL']),
            'geolocation'           => ['lat' => fake()->latitude(49, 51), 'lng' => fake()->longitude(2, 6)],
            'signed_at'             => now()->subHours(fake()->numberBetween(1, 48)),
            'expires_at'            => now()->addYear(),
            'is_invalidated'        => false,
            'invalidated_by_user_id' => null,
            'invalidated_at'        => null,
            'invalidation_reason'   => null,
            'metadata'              => [],
        ];
    }

    public function invalidated(): static
    {
        return $this->state(fn () => [
            'is_invalidated'        => true,
            'invalidated_at'        => now()->subHour(),
            'invalidation_reason'   => 'superseded',
        ]);
    }
}
