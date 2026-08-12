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

/**
 * LE PAIEMENT MOBILE DOIT ÊTRE LE MÊME PAIEMENT QUE LE WEB (B4, M2).
 *
 * CE CHEMIN EST VIVANT, contrairement à ce qu'on pourrait croire. L'application mobile réserve par
 * la WebView `/commander` et non par l'API native — `useCreateBooking` existe mais aucun écran ne
 * l'appelle. Le PAIEMENT, lui, est bien natif : `BookingDetailScreen` navigue vers
 * `PaymentCheckout`, qui appelle `POST /api/client/bookings/{booking}/payment-intent`. Un client
 * qui réserve puis appuie sur « Payer » passe donc ici, avec de l'argent réel.
 *
 * QUATRE DÉFAUTS TENAIENT SUR CE SEUL POINT D'ENTRÉE, et chacun a son test ci-dessous :
 *   1. aucun `transfer_data.destination` — la plateforme encaissait 100 % de la course ;
 *   2. aucun `application_fee_amount` — donc aucun split commission/prestataire ;
 *   3. `capture_method: automatic` — l'argent partait à la commande, avant le travail ;
 *   4. `stripe_payment_intent_id` jamais écrit — le webhook ne retrouvait pas la réservation,
 *      donc aucun statut de paiement ne remontait jamais.
 *
 * ON MESURE LA CHARGE ENVOYÉE À STRIPE, pas la forme du code. Le client HTTP factice enregistre les
 * paramètres réellement transmis : c'est la seule façon de distinguer « le service a été appelé »
 * de « la charge porte bien un destinataire ».
 */
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

    /**
     * L'INTENT DOIT ÊTRE RATTACHÉ À LA RÉSERVATION, SINON LE WEBHOOK EST AVEUGLE.
     *
     * `StripeWebhookHandlers` retrouve la réservation par `stripe_payment_intent_id`. Tant que cette
     * colonne restait nulle, chaque notification Stripe arrivait sans destinataire : le paiement
     * réussissait chez Stripe et la réservation restait éternellement « en attente de paiement ».
     */
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

    /**
     * M2 — UN SECOND INTENT EST UN SECOND DÉBIT.
     *
     * Rien n'empêchait de rappeler ce point d'entrée : double appui, retour arrière puis nouvel
     * appui, reprise de connexion. Chaque appel créait une empreinte de plus sur la carte, et deux
     * empreintes capturables valent deux prélèvements.
     */
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

    /**
     * SANS PRESTATAIRE ASSIGNÉ, ON REFUSE — ON N'ENCAISSE PAS « EN ATTENDANT ».
     *
     * L'écran mobile propose « Payer » dès le statut `pending`, c'est-à-dire avant qu'un prestataire
     * soit assigné. Or une charge à destination exige un compte destinataire. Le seul intent
     * possible dans ce cas encaisserait tout sur la plateforme : exactement le défaut qu'on ferme.
     *
     * Le refus porte un 409 et non un 500 : c'est une règle métier, pas une panne. Un 500 ferait
     * retenter l'application et remonterait comme incident.
     */
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
