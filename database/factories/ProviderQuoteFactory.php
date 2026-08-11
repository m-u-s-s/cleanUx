<?php

namespace Database\Factories;

use App\Models\OrganizationAccount;
use App\Models\ProviderQuote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderQuote>
 */
class ProviderQuoteFactory extends Factory
{
    protected $model = ProviderQuote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_account_id' => OrganizationAccount::factory(),
            'client_user_id' => User::factory(),
            'reference' => ProviderQuote::genererUneReference(),
            'title' => 'Remise en état des communs',
            'status' => ProviderQuote::STATUS_DRAFT,
            'total_cents' => 0,
            'currency' => 'EUR',
            'valid_until' => now()->addDays(30)->toDateString(),
        ];
    }

    /** Envoyé : le montant est figé et le client peut répondre. */
    public function envoye(): static
    {
        return $this->state(fn () => [
            'status' => ProviderQuote::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }
}
