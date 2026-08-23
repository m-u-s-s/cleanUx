<?php

namespace Tests\Feature\Payments;

use App\Models\User;
use App\Services\Payments\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\TestCase;

/** Coverage for App\Services\Payments\StripeConnectService. */
class StripeConnectServiceCoverageBatch7Test extends TestCase
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

    private function service(): StripeConnectService
    {
        $service = app(StripeConnectService::class);

        // The constructor calls Stripe::setApiKey(config('cashier.secret')),
        // which is null in tests; re-set a fake key for the static SDK calls.
        Stripe::setApiKey('sk_test_fake');

        return $service;
    }

    private static function accountBody(string $id, bool $charges = false, bool $payouts = false): array
    {
        return [
            'id' => $id,
            'object' => 'account',
            'type' => 'express',
            'charges_enabled' => $charges,
            'payouts_enabled' => $payouts,
            'email' => 'p@example.test',
        ];
    }

    // ──────────────────────────────────────────────
    // createOrGetAccount — early return when already linked
    // ──────────────────────────────────────────────

    public function test_create_or_get_account_returns_existing_id_without_calling_stripe(): void
    {
        $user = User::factory()->employe()->create(['stripe_connect_account_id' => 'acct_existing']);

        $result = $this->service()->createOrGetAccount($user);

        $this->assertSame('acct_existing', $result);
        $this->assertSame([], $this->fakeStripe->calls());
    }

    // ──────────────────────────────────────────────
    // createOrGetAccount — creates new account and persists it
    // ──────────────────────────────────────────────

    public function test_create_or_get_account_creates_and_stores_new_account(): void
    {
        $user = User::factory()->employe()->create(['stripe_connect_account_id' => null]);
        $this->fakeStripe->stub('POST', '/v1/accounts', self::accountBody('acct_new_1'));

        $result = $this->service()->createOrGetAccount($user);

        $this->assertSame('acct_new_1', $result);
        $this->assertContains('POST /v1/accounts', $this->fakeStripe->calls());

        $user->refresh();
        $this->assertSame('acct_new_1', $user->stripe_connect_account_id);
        $this->assertSame('pending', $user->stripe_connect_status);
    }

    // ──────────────────────────────────────────────
    // onboardingLink — creates account then account link
    // ──────────────────────────────────────────────

    public function test_onboarding_link_returns_url(): void
    {
        $user = User::factory()->employe()->create(['stripe_connect_account_id' => null]);
        $this->fakeStripe->stub('POST', '/v1/accounts', self::accountBody('acct_link_1'));
        $this->fakeStripe->stub('POST', '/v1/account_links', [
            'object' => 'account_link',
            'url' => 'https://connect.stripe.com/setup/e/onboard_abc',
            'expires_at' => time() + 3600,
        ]);

        $url = $this->service()->onboardingLink($user);

        $this->assertSame('https://connect.stripe.com/setup/e/onboard_abc', $url);
        $this->assertContains('POST /v1/account_links', $this->fakeStripe->calls());
    }

    // ──────────────────────────────────────────────
    // syncAccountStatus — early return when no account id
    // ──────────────────────────────────────────────

    public function test_sync_account_status_noops_without_account_id(): void
    {
        $user = User::factory()->employe()->create(['stripe_connect_account_id' => null]);

        $this->service()->syncAccountStatus($user);

        $this->assertSame([], $this->fakeStripe->calls());
        $this->assertNull($user->fresh()->stripe_connect_account_id);
    }

    // ──────────────────────────────────────────────
    // retrieveAccount
    // ──────────────────────────────────────────────

    public function test_retrieve_account_returns_stripe_object(): void
    {
        $this->fakeStripe->stub('GET', '/v1/accounts/acct_ret_1', self::accountBody('acct_ret_1', true, true));

        $account = $this->service()->retrieveAccount('acct_ret_1');

        $this->assertSame('acct_ret_1', $account->id);
        $this->assertTrue((bool) $account->charges_enabled);
        $this->assertTrue((bool) $account->payouts_enabled);
    }

    // ──────────────────────────────────────────────
    // createExpressAccount
    // ──────────────────────────────────────────────

    public function test_create_express_account_creates_and_stores(): void
    {
        $user = User::factory()->employe()->create(['stripe_connect_account_id' => null]);
        $this->fakeStripe->stub('POST', '/v1/accounts', self::accountBody('acct_express_1'));

        $id = $this->service()->createExpressAccount($user);

        $this->assertSame('acct_express_1', $id);
        $user->refresh();
        $this->assertSame('acct_express_1', $user->stripe_connect_account_id);
        $this->assertSame('pending', $user->stripe_connect_status);
    }

    // ──────────────────────────────────────────────
    // createAccountLink
    // ──────────────────────────────────────────────

    public function test_create_account_link_returns_link_object(): void
    {
        $this->fakeStripe->stub('POST', '/v1/account_links', [
            'object' => 'account_link',
            'url' => 'https://connect.stripe.com/setup/e/onboard_xyz',
            'expires_at' => time() + 7200,
        ]);

        $link = $this->service()->createAccountLink(
            'acct_link_obj',
            'https://app.test/refresh',
            'https://app.test/return',
        );

        $this->assertSame('https://connect.stripe.com/setup/e/onboard_xyz', $link->url);
    }

    // ──────────────────────────────────────────────
    // listPayouts — default and with starting_after
    // ──────────────────────────────────────────────

    public function test_list_payouts_returns_collection_without_cursor(): void
    {
        $this->fakeStripe->stub('GET', '/v1/payouts', [
            'object' => 'list',
            'url' => '/v1/payouts',
            'has_more' => false,
            'data' => [
                ['id' => 'po_1', 'object' => 'payout', 'amount' => 5000, 'currency' => 'eur'],
            ],
        ]);

        $payouts = $this->service()->listPayouts('acct_payouts_1');

        $this->assertCount(1, $payouts->data);
        $this->assertSame('po_1', $payouts->data[0]->id);
        $this->assertContains('GET /v1/payouts', $this->fakeStripe->calls());
    }

    public function test_list_payouts_passes_starting_after_cursor(): void
    {
        $this->fakeStripe->stub('GET', '/v1/payouts', [
            'object' => 'list',
            'url' => '/v1/payouts',
            'has_more' => false,
            'data' => [],
        ]);

        $payouts = $this->service()->listPayouts('acct_payouts_2', 5, 'po_cursor');

        $this->assertSame([], $payouts->data);

        $request = collect($this->fakeStripe->requests())
            ->firstWhere('key', 'GET /v1/payouts');
        $this->assertNotNull($request);
        $this->assertSame('po_cursor', $request['params']['starting_after'] ?? null);
        $this->assertSame(5, $request['params']['limit'] ?? null);
    }

    // ──────────────────────────────────────────────
    // createLoginLink
    // ──────────────────────────────────────────────

    public function test_create_login_link_returns_login_link_object(): void
    {
        $this->fakeStripe->stub('POST', '/v1/accounts/acct_login_1/login_links', [
            'object' => 'login_link',
            'url' => 'https://connect.stripe.com/express/acct_login_1/abc',
            'created' => time(),
        ]);

        $link = $this->service()->createLoginLink('acct_login_1');

        $this->assertSame('https://connect.stripe.com/express/acct_login_1/abc', $link->url);
    }
}
