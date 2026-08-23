<?php

namespace Tests\Feature\Api\Provider;

use App\Models\ProviderProfile;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Coverage batch 7 — drives ProviderWalletController through its HTTP routes: - GET /api/provider/wallet/balance (currency query, provider guard) - GET /api/provider/wallet/transactions (mapping, type filter, validation) - POST /api/provider/wallet/withdraw (201 success, 422 service error) - abortIfNotProvider guard (employe without ProviderProfile -> 403) */
class ProviderWalletControllerCoverageBatch7Test extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(bool $stripeConnected = false): User
    {
        $user = User::factory()->employe()->create();

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'stripe_connect_account_id' => $stripeConnected ? 'acct_cov_batch7' : null,
            'stripe_connect_status' => $stripeConnected ? 'active' : 'pending',
        ]);

        return $user;
    }

    private function seedCredit(User $provider, float $amount, string $type = ProviderWalletTransaction::TYPE_EARNING, string $currency = 'EUR'): ProviderWalletTransaction
    {
        return ProviderWalletTransaction::create([
            'provider_user_id' => $provider->id,
            'type' => $type,
            'direction' => ProviderWalletTransaction::DIRECTION_CREDIT,
            'amount' => $amount,
            'currency' => $currency,
            'status' => ProviderWalletTransaction::STATUS_AVAILABLE,
            'source_type' => 'booking',
            'source_id' => random_int(1, 99999),
            'idempotency_key' => 'cov7:'.uniqid('', true),
            'description' => 'Coverage seed '.$type,
            'occurred_at' => now(),
        ]);
    }

    public function test_balance_returns_aggregated_wallet_for_provider(): void
    {
        $provider = $this->makeProvider();
        $this->seedCredit($provider, 120.0);
        Sanctum::actingAs($provider);

        $r = $this->getJson('/api/provider/wallet/balance');

        $r->assertOk()
            ->assertJson([
                'currency' => 'EUR',
                'available' => 120.0,
                'pending' => 0.0,
                'total' => 120.0,
            ]);
    }

    public function test_balance_honours_currency_query_parameter(): void
    {
        $provider = $this->makeProvider();
        $this->seedCredit($provider, 75.0, currency: 'USD');
        Sanctum::actingAs($provider);

        $r = $this->getJson('/api/provider/wallet/balance?currency=USD');

        $r->assertOk()->assertJson([
            'currency' => 'USD',
            'available' => 75.0,
        ]);
    }

    public function test_transactions_returns_mapped_payload(): void
    {
        $provider = $this->makeProvider();
        $this->seedCredit($provider, 40.0, ProviderWalletTransaction::TYPE_EARNING);
        $this->seedCredit($provider, 10.0, ProviderWalletTransaction::TYPE_TIP);
        Sanctum::actingAs($provider);

        $r = $this->getJson('/api/provider/wallet/transactions');

        $r->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'type', 'direction', 'amount', 'currency', 'status', 'description', 'occurred_at', 'source_type', 'source_id'],
                ],
            ]);
    }

    public function test_transactions_filters_by_type(): void
    {
        $provider = $this->makeProvider();
        $this->seedCredit($provider, 40.0, ProviderWalletTransaction::TYPE_EARNING);
        $this->seedCredit($provider, 10.0, ProviderWalletTransaction::TYPE_TIP);
        Sanctum::actingAs($provider);

        $r = $this->getJson('/api/provider/wallet/transactions?type='.ProviderWalletTransaction::TYPE_TIP);

        $r->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(ProviderWalletTransaction::TYPE_TIP, $r->json('data.0.type'));
    }

    public function test_transactions_rejects_out_of_range_limit(): void
    {
        $provider = $this->makeProvider();
        Sanctum::actingAs($provider);

        $this->getJson('/api/provider/wallet/transactions?limit=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors('limit');
    }

    public function test_withdraw_creates_payout_and_returns_201(): void
    {
        $provider = $this->makeProvider(stripeConnected: true);
        $this->seedCredit($provider, 100.0);
        Sanctum::actingAs($provider);

        $r = $this->postJson('/api/provider/wallet/withdraw', [
            'amount' => 50,
            'currency' => 'eur',
        ]);

        $r->assertStatus(201)
            ->assertJson([
                'ok' => true,
                'amount' => 50.0,
                'currency' => 'EUR',
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('provider_payouts', [
            'provider_user_id' => $provider->id,
            'amount' => 50.0,
            'status' => 'pending',
        ]);
    }

    public function test_withdraw_returns_422_on_insufficient_balance(): void
    {
        $provider = $this->makeProvider(stripeConnected: true);
        Sanctum::actingAs($provider);

        $r = $this->postJson('/api/provider/wallet/withdraw', [
            'amount' => 50,
        ]);

        $r->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertArrayHasKey('amount', $r->json('errors'));
    }

    public function test_withdraw_validates_minimum_amount(): void
    {
        $provider = $this->makeProvider(stripeConnected: true);
        Sanctum::actingAs($provider);

        $this->postJson('/api/provider/wallet/withdraw', ['amount' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_employe_without_provider_profile_is_forbidden(): void
    {
        $user = User::factory()->employe()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/provider/wallet/balance')->assertForbidden();
    }
}
