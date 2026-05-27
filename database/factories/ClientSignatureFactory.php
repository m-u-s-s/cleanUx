<?php

namespace Database\Factories;

use App\Models\ClientSignature;
use App\Models\MissionQualityInspection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientSignatureFactory extends Factory
{
    protected $model = ClientSignature::class;

    public function definition(): array
    {
        return [
            'inspection_id' => fn () => MissionQualityInspection::factory()->create()->id,
            'signer_user_id' => fn () => User::factory()->client()->create()->id,
            'signer_name' => fake()->name(),
            'signer_email_hash' => hash('sha256', fake()->safeEmail()),
            'signature_data' => 'data:image/png;base64,' . base64_encode(fake()->text(50)),
            'signed_at' => now(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent_short' => 'Mozilla/5.0',
            'terms_version' => '1.0',
            'metadata' => null,
        ];
    }
}
