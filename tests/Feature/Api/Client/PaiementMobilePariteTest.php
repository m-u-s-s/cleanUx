<?php

namespace Tests\Feature\Api\Client;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\TestCase;

/** LE PAIEMENT MOBILE DOIT ÊTRE LE MÊME PAIEMENT QUE LE WEB (B4, M2). */
class PaiementMobilePariteTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cashier.secret' => 'sk_test_fake']);
        Stripe::setApiKey('sk_test_fake');

        $this->stripe = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    /** @return array{0: User, 1: Booking} */
    private function reservationPayable(): array
    {
        $client = User::factory()->client()->create(['stripe_id' => 'cus_test_client']);

        // `canReceiveStripeConnectPayments()` exige un compte ET la preuve qu'il aboutit :
        // un compte seul existe dès la première étape du parcours Stripe et ne reçoit rien.
        $prestataire = User::factory()->employe()->create([
            'stripe_connect_account_id' => 'acct_test_prestataire',
            'stripe_connect_status' => 'active',
        ]);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $prestataire->id,
            'devis_estime' => 120.00,
            'status' => 'confirmed',
        ]);

        return [$client, $booking];
    }

    /** Les paramètres réellement envoyés à Stripe pour la création de l'intent. */
    private function chargeEnvoyee(): array
    {
        foreach ($this->stripe->requests() as $requete) {
            if ($requete['key'] === 'POST /v1/payment_intents') {
                return $requete['params'];
            }
        }

        $this->fail('Aucun PaymentIntent n’a été créé : le test ne mesure rien.');
    }

    #[Test]
    public function la_charge_mobile_porte_un_destinataire_et_une_commission(): void
    {
        [$client, $booking] = $this->reservationPayable();

        $this->stripe->stub('POST', '/v1/payment_intents', [
            'id' => 'pi_test_mobile',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_mobile_secret_abc',
            'status' => 'requires_confirmation',
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/payment-intent")
            ->assertOk()
            ->assertJsonStructure(['client_secret', 'payment_intent_id', 'amount_cents']);

        $charge = $this->chargeEnvoyee();

        $this->assertSame(
            'acct_test_prestataire',
            $charge['transfer_data']['destination'] ?? null,
            'Sans destinataire, la plateforme encaisse la totalité et le prestataire n’est jamais payé.'
        );

        $this->assertArrayHasKey(
            'application_fee_amount',
            $charge,
            'Sans commission déclarée, Stripe ne sait pas quoi retenir : le split n’existe pas.'
        );
        $this->assertGreaterThan(0, (int) $charge['application_fee_amount']);

        $this->assertSame(
            'manual',
            $charge['capture_method'] ?? null,
            'Une capture automatique prend l’argent à la commande, avant que le travail soit fait.'
        );
    }

    /** L'INTENT DOIT ÊTRE RATTACHÉ À LA RÉSERVATION, SINON LE WEBHOOK EST AVEUGLE. */
    #[Test]
    public function l_intent_est_rattache_a_la_reservation_et_repris_dans_les_metadonnees(): void
    {
        [$client, $booking] = $this->reservationPayable();

        $this->stripe->stub('POST', '/v1/payment_intents', [
            'id' => 'pi_test_rattache',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_rattache_secret',
            'status' => 'requires_confirmation',
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/payment-intent")
            ->assertOk();

        $this->assertSame('pi_test_rattache', $booking->fresh()->stripe_payment_intent_id);

        // Le filet de secours : même si l'écriture ci-dessus manquait, le webhook peut retrouver la
        // réservation par cette métadonnée.
        $this->assertSame(
            (string) $booking->id,
            $this->chargeEnvoyee()['metadata']['booking_id'] ?? null
        );
    }

    /** M2 — UN SECOND INTENT EST UN SECOND DÉBIT. */
    #[Test]
    public function un_second_appel_ne_cree_pas_un_second_paiement(): void
    {
        [$client, $booking] = $this->reservationPayable();

        $this->stripe->stub('POST', '/v1/payment_intents', [
            'id' => 'pi_test_unique',
            'object' => 'payment_intent',
            'client_secret' => 'pi_test_unique_secret',
            'status' => 'requires_confirmation',
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/payment-intent")
            ->assertOk();

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/payment-intent")
            ->assertStatus(409)
            ->assertJsonPath('error', 'payment_already_initiated');

        $creations = array_filter(
            $this->stripe->calls(),
            static fn (string $appel): bool => $appel === 'POST /v1/payment_intents'
        );

        $this->assertCount(1, $creations, 'Deux empreintes capturables valent deux prélèvements.');
    }

    /** SANS PRESTATAIRE ASSIGNÉ, ON REFUSE — ON N'ENCAISSE PAS « EN ATTENDANT ». */
    #[Test]
    public function sans_prestataire_assigne_le_paiement_est_refuse_sans_rien_encaisser(): void
    {
        $client = User::factory()->client()->create(['stripe_id' => 'cus_test_client']);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => null,
            'devis_estime' => 120.00,
            'status' => 'pending',
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/payment-intent")
            ->assertStatus(409)
            ->assertJsonPath('error', 'provider_not_ready');

        $this->assertNotContains(
            'POST /v1/payment_intents',
            $this->stripe->calls(),
            'Aucune charge ne doit partir tant qu’on ne sait pas à qui la reverser.'
        );
        $this->assertNull($booking->fresh()->stripe_payment_intent_id);
    }

    #[Test]
    public function la_reservation_d_un_autre_client_reste_inatteignable(): void
    {
        [, $booking] = $this->reservationPayable();

        $intrus = User::factory()->client()->create();

        $this->actingAs($intrus, 'sanctum')
            ->postJson("/api/client/bookings/{$booking->id}/payment-intent")
            ->assertStatus(403);

        $this->assertNotContains('POST /v1/payment_intents', $this->stripe->calls());
    }
}
