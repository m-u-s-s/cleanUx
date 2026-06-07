<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\User;
use App\Services\Payments\MissionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\TestCase;

/**
 * Smoke tests pour MissionPaymentService. Stripe SDK n'est PAS appelé
 * (l'authorize() throw RuntimeException si provider pas onboarded en Stripe Connect).
 *
 * Couvre les guards :
 *   - Refuse si employé n'a pas de compte Stripe Connect prêt
 *   - Refuse si pas de client Stripe customer associable
 *
 * Les flows réels Stripe::PaymentIntent::create sont testés via webhooks mocks
 * dans Tests/Feature/Payments/StripeWebhookSyncTest et StripeReconciliationServiceTest.
 */
class MissionPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_blocks_when_employee_has_no_stripe_connect_account(): void
    {
        $client = User::factory()->client()->create();
        $employe = User::factory()->employe()->create();
        // Pas de stripe_connect_account_id set sur employe
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $employe->id,
            'devis_estime' => 100.00,
        ]);

        // M24 — assert the PRECISE guard exception and prove no PaymentIntent is created.
        config(['cashier.secret' => 'sk_test_fake']);
        Stripe::setApiKey('sk_test_fake');
        $stripe = new FakeStripeHttpClient; // no stubs: any Stripe call would throw "no stub"
        ApiRequestor::setHttpClient($stripe);

        try {
            app(MissionPaymentService::class)->authorize($booking, 'pm_card_visa_fake');
            $this->fail('authorize() must throw when the provider is not Stripe Connect ready');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Stripe Connect', $e->getMessage());
        } finally {
            ApiRequestor::setHttpClient(null);
        }

        $this->assertNotContains(
            'POST /v1/payment_intents',
            $stripe->calls(),
            'No PaymentIntent must be created when the provider is not onboarded'
        );
    }
}
