<?php

namespace Database\Factories;

use App\Models\ContractDocument;
use App\Models\ContractSignature;
use App\Models\ContractSignatureAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractSignatureAuditFactory extends Factory
{
    protected $model = ContractSignatureAudit::class;

    public function definition(): array
    {
        return [
            'signature_id' => fn () => ContractSignature::factory()->create()->id,
            'document_id' => fn () => ContractDocument::factory()->create()->id,
            'user_id' => fn () => User::factory()->create()->id,
            'event' => ContractSignatureAudit::EVENT_SIGNED,
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent_short' => 'Mozilla/5.0',
            'occurred_at' => now(),
            'metadata' => null,
        ];
    }
}
