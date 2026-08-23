<?php

namespace Tests\Feature\Payments;

use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Payments\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\TestCase;

/** Ce que `syncAccountStatus()` enregistre réellement. */
class StripeConnectSyncPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $fakeStripe;

    protected function setUp(): void
    {
        parent::setUp();

        Stripe::setApiKey('sk_test_fake');
        $this->fakeStripe = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->fakeStripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    public function test_a_fully_enabled_account_persists_all_its_timestamps(): void
    {
        $user = $this->provider('acct_complet');
        $this->stubAccount('acct_complet', charges: true, payouts: true);

        $this->service()->syncAccountStatus($user);

        $user->refresh();
        $this->assertSame('active', $user->stripe_connect_status);
        $this->assertNotNull($user->stripe_connect_onboarded_at, "la date d'aboutissement doit être écrite");
        $this->assertNotNull($user->stripe_connect_charges_enabled_at);
        $this->assertNotNull($user->stripe_connect_payouts_enabled_at);
    }

    /** Un compte qui n'encaisse pas encore ne doit pas être marqué abouti. */
    public function test_a_partially_enabled_account_records_only_what_is_true(): void
    {
        $user = $this->provider('acct_partiel');
        $this->stubAccount('acct_partiel', charges: true, payouts: false);

        $this->service()->syncAccountStatus($user);

        $user->refresh();
        $this->assertSame('pending', $user->stripe_connect_status);
        $this->assertNull($user->stripe_connect_onboarded_at);
        $this->assertNotNull($user->stripe_connect_charges_enabled_at);
        $this->assertNull($user->stripe_connect_payouts_enabled_at);
    }

    /** La date d'aboutissement marque la PREMIÈRE fois : une synchronisation ultérieure ne doit pas la repousser, sans quoi la piste d'audit dirait n'importe quoi. */
    public function test_the_onboarded_date_is_not_pushed_forward_on_a_later_sync(): void
    {
        $user = $this->provider('acct_stable');
        $this->stubAccount('acct_stable', charges: true, payouts: true);

        $this->service()->syncAccountStatus($user);
        $first = $user->refresh()->stripe_connect_onboarded_at;

        $this->travel(2)->days();
        $this->service()->syncAccountStatus($user);

        $this->assertTrue(
            $first->equalTo($user->refresh()->stripe_connect_onboarded_at),
            "la date d'aboutissement doit rester celle de la première activation"
        );
    }

    /** Garde de bout en bout du chemin monétaire : après une synchronisation complète, le prestataire doit pouvoir être payé. */
    public function test_a_synced_account_can_be_paid(): void
    {
        $user = $this->provider('acct_paiement');
        $this->stubAccount('acct_paiement', charges: true, payouts: true);

        $this->service()->syncAccountStatus($user);

        $this->assertTrue($user->refresh()->canReceiveStripeConnectPayments());
    }

    private function provider(string $accountId): User
    {
        $user = User::factory()->employe()->create([
            'stripe_connect_account_id' => $accountId,
            'stripe_connect_status' => 'pending',
        ]);
        ProviderProfile::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function stubAccount(string $id, bool $charges, bool $payouts): void
    {
        $this->fakeStripe->stub('GET', "/v1/accounts/{$id}", [
            'id' => $id,
            'object' => 'account',
            'type' => 'express',
            'charges_enabled' => $charges,
            'payouts_enabled' => $payouts,
            'email' => 'p@example.test',
        ]);
    }

    private function service(): StripeConnectService
    {
        $service = app(StripeConnectService::class);
        Stripe::setApiKey('sk_test_fake');

        return $service;
    }
}
