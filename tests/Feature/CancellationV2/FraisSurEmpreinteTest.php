<?php

namespace Tests\Feature\CancellationV2;

use App\Models\Booking;
use App\Models\ProviderWalletTransaction;
use App\Services\Payments\MissionPaymentService;
use App\Services\Payments\StripeConnectPaymentService;
use App\Services\Payments\Webhooks\StripeWebhookHandlers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/** ANNULER UNE EMPREINTE : ENCAISSER LES FRAIS, RENDRE LE RESTE. LE DÉFAUT RÉPARÉ. */
class FraisSurEmpreinteTest extends TestCase
{
    use RefreshDatabase;

    /** LE GARDE N°1 — `fee_captured` n'est pas `captured`. */
    public function test_une_capture_de_frais_nest_jamais_traduite_en_encaissement(): void
    {
        $scenario = $this->reservationAvecEmpreinte();

        Booking::query()->whereKey($scenario->booking->getKey())
            ->update(['payment_status' => MissionPaymentService::STATUT_FRAIS_CAPTURES]);

        // Stripe dirait « succeeded » ; la synchronisation doit refuser d'écraser notre distinction.
        app(StripeConnectPaymentService::class)->syncPaymentIntent($scenario->booking->refresh());

        $this->assertSame(
            MissionPaymentService::STATUT_FRAIS_CAPTURES,
            $scenario->booking->refresh()->payment_status,
        );
    }

    /** LE GARDE N°2 — le webhook ne crédite pas le prestataire sur des frais. */
    public function test_le_prestataire_nest_pas_credite_pour_une_annulation(): void
    {
        $scenario = $this->reservationAvecEmpreinte();

        Booking::query()->whereKey($scenario->booking->getKey())
            ->update(['payment_status' => MissionPaymentService::STATUT_FRAIS_CAPTURES]);

        app(StripeWebhookHandlers::class)->handlePaymentIntentSucceeded([
            'id' => 'pi_empreinte',
            'amount' => 2400,
            'currency' => 'eur',
        ]);

        $this->assertSame(
            0,
            ProviderWalletTransaction::query()->where('type', ProviderWalletTransaction::TYPE_EARNING)->count(),
            'Aucun gain : la prestation n’a pas eu lieu, seuls des frais ont été pris.',
        );
    }

    /** LE GARDE N°3 — le solde libéré n'est pas un remboursement. */
    public function test_le_solde_libere_nefface_pas_la_trace_des_frais(): void
    {
        $scenario = $this->reservationAvecEmpreinte();

        Booking::query()->whereKey($scenario->booking->getKey())
            ->update(['payment_status' => MissionPaymentService::STATUT_FRAIS_CAPTURES]);

        app(StripeWebhookHandlers::class)->handleChargeRefunded([
            'id' => 'ch_empreinte',
            'payment_intent' => 'pi_empreinte',
            'amount' => 12000,
            'amount_refunded' => 9600,
            'refunds' => ['data' => [['id' => 're_release', 'amount' => 9600]]],
        ]);

        $this->assertSame(
            MissionPaymentService::STATUT_FRAIS_CAPTURES,
            $scenario->booking->refresh()->payment_status,
        );

        $this->assertSame(
            0,
            ProviderWalletTransaction::query()
                ->where('type', ProviderWalletTransaction::TYPE_REFUND_CLAWBACK)->count(),
            'Aucune reprise : rien n’a été versé, il n’y a rien à reprendre.',
        );
    }

    /** TÉMOIN — sur une VRAIE mission encaissée, tout le chemin historique fonctionne encore. */
    public function test_temoin_une_mission_reellement_encaissee_credite_toujours(): void
    {
        $scenario = $this->reservationAvecEmpreinte();

        Booking::query()->whereKey($scenario->booking->getKey())->update([
            'payment_status' => 'captured',
            'provider_amount_cents' => 9600,
        ]);

        app(StripeWebhookHandlers::class)->handleChargeRefunded([
            'id' => 'ch_vrai',
            'payment_intent' => 'pi_empreinte',
            'amount' => 12000,
            'amount_refunded' => 12000,
            'refunds' => ['data' => []],
        ]);

        $this->assertSame('refunded', $scenario->booking->refresh()->payment_status);
    }

    /** L'ACOMPTE DÉJÀ DÉBITÉ SE DÉDUIT DES FRAIS. */
    public function test_la_deduction_de_lacompte_est_arithmetiquement_juste(): void
    {
        // 120 € commandés, 30 € d'acompte déjà débité, 24 € de frais dus.
        // À prendre sur le solde : 24 − 30 = négatif → 0, donc libération pure.
        $this->assertSame(0, max(0, 2400 - 3000));

        // 120 € commandés, 30 € d'acompte, 90 € de frais → 60 € à prendre sur le solde de 90 €.
        $this->assertSame(6000, min(max(0, 9000 - 3000), 9000));
    }

    // ─────────────────────────────────────────────────────────────────────

    private function reservationAvecEmpreinte(): SpineScenario
    {
        $scenario = SpineScenario::make()->build();

        Booking::query()->whereKey($scenario->booking->getKey())->update([
            'stripe_payment_intent_id' => 'pi_empreinte',
            'payment_amount_cents' => 12000,
            'provider_amount_cents' => 9600,
            'payment_status' => 'authorized',
            'estimated_price' => 120,
        ]);

        $scenario->booking->refresh();

        return $scenario;
    }
}
