<?php

namespace Tests\Feature\Payments;

use App\Jobs\Payments\ProcessStripeWebhookJob;
use App\Models\Booking;
use App\Models\ProviderWalletTransaction;
use App\Services\Payments\ProviderWalletService;
use App\Services\Payments\Webhooks\StripeWebhookHandlers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/** DEUX DÉFAUTS D'ARGENT QUI SE TIENNENT PAR LA MAIN. 1. */
class RepriseEtAcompteTest extends TestCase
{
    use RefreshDatabase;

    // ── La reprise ───────────────────────────────────────────────────────

    public function test_sans_gain_enregistre_aucune_reprise_nest_possible(): void
    {
        $scenario = SpineScenario::make()->build();

        $reprise = app(ProviderWalletService::class)
            ->recordRefundClawback($scenario->booking, 40.0, 're_test_1');

        $this->assertNull($reprise, 'On ne reprend pas ce qu’on n’a jamais versé.');
        $this->assertSame(0, ProviderWalletTransaction::query()->count());
    }

    public function test_la_reprise_est_plafonnee_au_montant_reellement_credite(): void
    {
        $scenario = $this->reservationCreditee(providerCents: 8000);

        $reprise = app(ProviderWalletService::class)
            ->recordRefundClawback($scenario->booking, 120.0, 're_test_2');

        $this->assertNotNull($reprise);
        $this->assertSame(80.0, (float) $reprise->amount, 'Jamais plus que les 80 € versés.');
    }

    /** TÉMOIN — une reprise légitime passe toujours, à son montant exact. */
    public function test_une_reprise_legitime_passe_a_son_montant(): void
    {
        $scenario = $this->reservationCreditee(providerCents: 8000);

        $reprise = app(ProviderWalletService::class)
            ->recordRefundClawback($scenario->booking, 30.0, 're_test_3');

        $this->assertNotNull($reprise);
        $this->assertSame(30.0, (float) $reprise->amount);
    }

    /** Deux remboursements partiels reprennent chacun leur part, sans dépasser le total versé. */
    public function test_deux_reprises_successives_ne_depassent_pas_le_total(): void
    {
        $scenario = $this->reservationCreditee(providerCents: 8000);
        $service = app(ProviderWalletService::class);

        $service->recordRefundClawback($scenario->booking, 50.0, 're_a');
        $seconde = $service->recordRefundClawback($scenario->booking, 50.0, 're_b');

        $this->assertNotNull($seconde);
        $this->assertSame(30.0, (float) $seconde->amount, 'Il ne restait que 30 € reprenables.');
    }

    // ── L'acompte ────────────────────────────────────────────────────────

    /** LE PIÈGE 4 : rembourser l'acompte ne doit pas condamner le solde. */
    public function test_rembourser_lacompte_ne_touche_pas_au_statut_du_solde(): void
    {
        $scenario = $this->reservationAvecAcompte();

        app(StripeWebhookHandlers::class)->handleChargeRefunded([
            'id' => 'ch_acompte',
            'payment_intent' => 'pi_acompte',
            'amount' => 3000,
            'amount_refunded' => 3000,
            'refunds' => ['data' => []],
        ]);

        $this->assertSame(
            'authorized',
            $scenario->booking->refresh()->payment_status,
            'Le solde reste capturable : son statut ne parle pas de l’acompte.',
        );
    }

    /** TÉMOIN : sur le SOLDE, le même webhook fait bien basculer le statut. */
    public function test_temoin_rembourser_le_solde_change_bien_le_statut(): void
    {
        $scenario = $this->reservationAvecAcompte();

        app(StripeWebhookHandlers::class)->handleChargeRefunded([
            'id' => 'ch_solde',
            'payment_intent' => 'pi_solde',
            'amount' => 9000,
            'amount_refunded' => 9000,
            'refunds' => ['data' => []],
        ]);

        $this->assertSame('refunded', $scenario->booking->refresh()->payment_status);
    }

    /** L'ACOMPTE EST RECONNU, PAS IGNORÉ — mais il ne crédite rien. */
    public function test_lacompte_est_reconnu_sans_crediter_le_portefeuille(): void
    {
        $scenario = $this->reservationAvecAcompte();

        $resultat = app(StripeWebhookHandlers::class)->handlePaymentIntentSucceeded([
            'id' => 'pi_acompte',
            'amount' => 3000,
            'currency' => 'eur',
        ]);

        $this->assertSame('processed', $resultat['status'], 'L’événement concerne une réservation connue.');
        $this->assertSame('deposit', $resultat['details']['volet'] ?? null);
        $this->assertSame(0, ProviderWalletTransaction::query()->count());
        $this->assertSame('authorized', $scenario->booking->refresh()->payment_status);
    }

    /** Un acompte en échec ne fait pas passer pour perdu un solde parfaitement autorisé. */
    public function test_un_acompte_en_echec_ne_condamne_pas_le_solde(): void
    {
        $scenario = $this->reservationAvecAcompte();

        app(StripeWebhookHandlers::class)->handlePaymentIntentFailed([
            'id' => 'pi_acompte',
            'last_payment_error' => ['message' => 'carte refusée'],
        ]);

        $this->assertSame('authorized', $scenario->booking->refresh()->payment_status);
    }

    // ── Le rejeu des webhooks ────────────────────────────────────────────

    /** UN ÉVÉNEMENT STRIPE PERDU NE REVIENT PAS. */
    public function test_le_traitement_dun_webhook_est_rejoue(): void
    {
        $job = new ProcessStripeWebhookJob(1);

        $this->assertGreaterThan(1, $job->tries);
        $this->assertNotEmpty($job->backoff, 'Réessayer dans la même seconde échouerait pour la même raison.');
    }

    // ─────────────────────────────────────────────────────────────────────

    private function reservationCreditee(int $providerCents): SpineScenario
    {
        $scenario = SpineScenario::make()->build();

        $scenario->booking->forceFill([
            'provider_amount_cents' => $providerCents,
            'payment_amount_cents' => 10000,
            'stripe_payment_intent_id' => 'pi_credite',
        ])->save();

        app(ProviderWalletService::class)->recordEarning($scenario->booking->refresh());

        return $scenario;
    }

    private function reservationAvecAcompte(): SpineScenario
    {
        $scenario = SpineScenario::make()->build();

        Booking::query()->whereKey($scenario->booking->getKey())->update([
            'stripe_payment_intent_id' => 'pi_solde',
            'deposit_payment_intent_id' => 'pi_acompte',
            'deposit_amount_cents' => 3000,
            'payment_amount_cents' => 12000,
            'provider_amount_cents' => 9600,
            'payment_status' => 'authorized',
        ]);

        $scenario->booking->refresh();

        return $scenario;
    }
}
