<?php

namespace Database\Factories;

use App\Models\ProviderWalletTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProviderWalletTransactionFactory extends Factory
{
    protected $model = ProviderWalletTransaction::class;

    public function definition(): array
    {
        $type      = fake()->randomElement([
            ProviderWalletTransaction::TYPE_EARNING,
            ProviderWalletTransaction::TYPE_PAYOUT,
            ProviderWalletTransaction::TYPE_PLATFORM_FEE,
        ]);

        $direction = in_array($type, [
            ProviderWalletTransaction::TYPE_EARNING,
            ProviderWalletTransaction::TYPE_BONUS,
        ]) ? ProviderWalletTransaction::DIRECTION_CREDIT
           : ProviderWalletTransaction::DIRECTION_DEBIT;

        $amount = number_format(fake()->randomFloat(2, 5.00, 500.00), 2);

        return [
            'provider_user_id'         => User::factory(),
            'type'                     => $type,
            'direction'                => $direction,
            'amount'                   => $amount,
            'currency'                 => 'EUR',
            'balance_after'            => number_format(fake()->randomFloat(2, 0, 2000), 2),
            'status'                   => ProviderWalletTransaction::STATUS_AVAILABLE,
            'source_type'              => 'App\\Models\\Booking',
            'source_id'                => fake()->numberBetween(1, 500),
            'stripe_payment_intent_id' => 'pi_' . fake()->md5(),
            'stripe_transfer_id'       => null,
            'stripe_payout_id'         => null,
            'stripe_refund_id'         => null,
            'idempotency_key'          => Str::uuid()->toString(),
            'description'              => ucfirst($type) . ' for booking #' . fake()->numberBetween(1, 500),
            'metadata'                 => [],
            'occurred_at'              => now()->subHours(fake()->numberBetween(1, 72)),
        ];
    }

    public function earning(): static
    {
        return $this->state(fn () => [
            'type'      => ProviderWalletTransaction::TYPE_EARNING,
            'direction' => ProviderWalletTransaction::DIRECTION_CREDIT,
        ]);
    }

    public function payout(): static
    {
        return $this->state(fn () => [
            'type'             => ProviderWalletTransaction::TYPE_PAYOUT,
            'direction'        => ProviderWalletTransaction::DIRECTION_DEBIT,
            'stripe_payout_id' => 'po_' . fake()->md5(),
        ]);
    }
}
