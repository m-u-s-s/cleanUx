<?php

namespace Tests\Feature\OrderEngine;

use App\Models\Booking;
use App\Models\OrderDraft;
use App\Models\Trade;
use App\Models\User;
use App\Services\OrderEngine\BundleComposer;
use App\Services\OrderEngine\OrderConfirmationService;
use App\Services\OrderEngine\OrderDraftManager;
use App\Support\Domain\OrderDraftStatus;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * La confirmation : le panier devient une réservation.
 *
 * Trois garanties. Elle est IDEMPOTENTE — un double-clic ne doit pas produire deux réservations
 * et deux empreintes bancaires. Le devis est FIGÉ — le prix accepté engage, pas celui qu'un
 * recalcul donnerait demain. Et l'identité n'est demandée qu'ICI, au dernier moment.
 */
class OrderConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private OrderConfirmationService $confirmation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        $this->confirmation = app(OrderConfirmationService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_confirming_turns_the_basket_into_a_booking(): void
    {
        [$draft, $client] = $this->readyDraft();

        $confirmed = $this->confirmation->confirm($draft, $client);

        $this->assertSame(OrderDraftStatus::CONVERTED, $confirmed->status);
        $this->assertSame(1, Booking::count());

        $booking = Booking::firstOrFail();
        $this->assertSame($client->id, $booking->client_id);
        $this->assertSame('Rue de la Loi 1, 1000 Bruxelles', $booking->address);
    }

    /**
     * LA garantie du double envoi.
     *
     * Un double-clic, un rechargement, un retour arrière du navigateur : sans garde, le client se
     * retrouverait avec deux réservations et deux pré-autorisations pour une seule intervention —
     * et c'est lui qui découvrirait le doublon sur son relevé.
     */
    public function test_confirming_twice_creates_a_single_booking(): void
    {
        [$draft, $client] = $this->readyDraft();

        $first = $this->confirmation->confirm($draft, $client);
        $second = $this->confirmation->confirm($first->fresh(), $client);

        $this->assertSame(1, Booking::count());
        $this->assertSame($first->converted_booking_id, $second->converted_booking_id);
    }

    /**
     * Le DEVIS EST FIGÉ.
     *
     * Le prix affiché au moment du clic est celui qui engage. Recalculer plus tard exposerait le
     * client à un montant différent de celui qu'il a accepté, parce qu'un administrateur aura
     * modifié une grille entre-temps.
     */
    public function test_the_quote_is_frozen_at_the_moment_of_the_click(): void
    {
        [$draft, $client] = $this->readyDraft();

        $confirmed = $this->confirmation->confirm($draft, $client);
        $frozen = $confirmed->total_cents;
        $bookingPrice = (float) Booking::firstOrFail()->estimated_price;

        // Le catalogue change après coup : le devis accepté ne doit pas bouger.
        $this->peinture()->update(['base_price_cents' => 99000]);

        $this->assertSame($frozen, $confirmed->fresh()->total_cents);
        $this->assertEquals($bookingPrice, (float) Booking::firstOrFail()->estimated_price);
    }

    /** L'instantané du devis voyage avec la réservation, explicable ligne par ligne. */
    public function test_the_booking_carries_an_explainable_snapshot(): void
    {
        [$draft, $client] = $this->readyDraft();

        $this->confirmation->confirm($draft, $client);
        $booking = Booking::firstOrFail();

        $snapshot = $booking->pricing_snapshot;
        $this->assertNotEmpty($snapshot['lines']);
        $this->assertSame($draft->reference, $snapshot['order_draft_reference']);
        $this->assertSame($snapshot['min_cents'], collect($snapshot['lines'])->sum('min_cents'));

        // Les réponses aussi, avec leur libellé tel que le client l'a vu.
        $answers = collect($booking->trade_form_answers);
        $this->assertTrue($answers->contains('answer', 'Murs et plafonds'));
    }

    /** Le montant retenu est le PLANCHER : on n'engage jamais le client sur le haut d'une fourchette. */
    public function test_the_committed_amount_is_the_floor_of_the_range(): void
    {
        [$draft, $client] = $this->readyDraft(['etendue' => ['unknown' => true]]);

        $confirmed = $this->confirmation->confirm($draft, $client);

        $this->assertLessThan($confirmed->estimate_max_cents, $confirmed->estimate_min_cents);
        $this->assertSame($confirmed->estimate_min_cents, $confirmed->total_cents);
    }

    /** En multi-services, une réservation PAR métier : chacune s'assigne et se suit séparément. */
    public function test_a_bundle_produces_one_booking_per_trade(): void
    {
        $client = User::factory()->client()->create();
        $draft = app(OrderDraftManager::class)->resumeOrCreate('jeton', null, OrderMode::BUNDLE);
        $draft->update(['address' => 'Rue de la Loi 1, 1000 Bruxelles', 'lat' => 50.8467, 'lng' => 4.3525]);

        foreach (['plumbing', 'electrical'] as $slug) {
            app(BundleComposer::class)->addTrade($draft->fresh(), Trade::where('slug', $slug)->firstOrFail());
        }

        $this->confirmation->confirm($draft->fresh(), $client);

        $this->assertSame(2, Booking::count());
        // Une seule commande côté client, malgré deux réservations.
        $this->assertSame(1, OrderDraft::where('status', OrderDraftStatus::CONVERTED)->count());
    }

    // ─── Ce qui bloque ───────────────────────────────────────────────────────────────────────

    /**
     * Les blocages sont RENDUS, pas levés.
     *
     * L'écran doit pouvoir griser son bouton et dire pourquoi, au lieu de laisser le client
     * cliquer pour découvrir un refus.
     */
    public function test_blockers_are_returned_so_the_screen_can_explain(): void
    {
        $draft = app(OrderDraftManager::class)->resumeOrCreate('jeton');

        $blockers = $this->confirmation->blockers($draft);

        $this->assertCount(2, $blockers);
        $this->assertStringContainsString('Aucun service', $blockers[0]);
        $this->assertStringContainsString('adresse', $blockers[1]);
    }

    public function test_confirming_without_an_address_is_refused(): void
    {
        $client = User::factory()->client()->create();
        $draft = app(OrderDraftManager::class)->resumeOrCreate('jeton');
        app(OrderDraftManager::class)->itemFor($draft, $this->peinture());

        $this->expectException(ValidationException::class);
        $this->confirmation->confirm($draft->fresh(), $client);
    }

    // ─── Paiement ────────────────────────────────────────────────────────────────────────────

    /**
     * Sans prestataire assigné, le paiement ne peut pas être pré-autorisé — et on le DIT.
     *
     * La charge Stripe est une « destination charge » vers le compte du professionnel : sans
     * destination, il n'y a nulle part où envoyer l'argent. Avec l'attribution automatique,
     * personne n'est désigné à la confirmation.
     */
    public function test_payment_waits_for_a_provider_and_says_so(): void
    {
        [$draft, $client] = $this->readyDraft();
        $this->confirmation->confirm($draft, $client);

        $readiness = $this->confirmation->paymentReadiness(Booking::firstOrFail());

        $this->assertFalse($readiness['ready']);
        $this->assertStringContainsString('dès qu’un professionnel', $readiness['reason']);
    }

    /** Un prestataire sans Connect configuré est signalé par SA raison, pas par un message vague. */
    public function test_an_unconfigured_provider_gets_its_own_reason(): void
    {
        [$draft, $client] = $this->readyDraft();
        $this->confirmation->confirm($draft, $client);

        $booking = Booking::firstOrFail();
        $booking->update(['employe_id' => User::factory()->create(['role' => 'employe'])->id]);

        $readiness = $this->confirmation->paymentReadiness($booking->fresh());

        $this->assertFalse($readiness['ready']);
        $this->assertStringContainsString('configuration de ses paiements', $readiness['reason']);
    }

    /**
     * Aucun soft-fail sur le paiement.
     *
     * Une réservation confirmée sans autorisation valide est une intervention qu'on enverra faire
     * sans garantie d'être payé.
     */
    public function test_authorising_without_a_ready_provider_is_refused_loudly(): void
    {
        [$draft, $client] = $this->readyDraft();
        $this->confirmation->confirm($draft, $client);

        $this->expectException(ValidationException::class);
        $this->confirmation->authorizePayment(Booking::firstOrFail(), 'pm_test');
    }

    /** Rejouer une autorisation ne crée pas une seconde empreinte sur la carte du client. */
    public function test_an_already_authorised_payment_is_not_charged_twice(): void
    {
        [$draft, $client] = $this->readyDraft();
        $this->confirmation->confirm($draft, $client);

        $booking = Booking::firstOrFail();
        $booking->update(['payment_status' => 'authorized', 'stripe_payment_intent_id' => 'pi_existant']);

        $result = $this->confirmation->authorizePayment($booking->fresh(), 'pm_test');

        $this->assertSame('pi_existant', $result->stripe_payment_intent_id);
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array{0: OrderDraft, 1: User}
     */
    private function readyDraft(array $answers = ['surface_m2' => 40, 'etendue' => 'murs_plafonds']): array
    {
        $client = User::factory()->client()->create();
        $manager = app(OrderDraftManager::class);

        $draft = $manager->resumeOrCreate('jeton-'.uniqid());
        $draft->update([
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'lat' => 50.8467,
            'lng' => 4.3525,
            'scheduled_at' => Carbon::parse('2026-09-02 09:00:00'),
        ]);

        $trade = $this->peinture();
        $item = $manager->itemFor($draft, $trade);
        $manager->saveAnswers($item, $trade->questions()->with(['options', 'conditions'])->get(), $answers);

        return [$draft->fresh(), $client];
    }
}
