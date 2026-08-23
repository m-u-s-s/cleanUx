<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingEntry;
use App\Models\Booking;
use App\Services\Payments\MissionPaymentService;
use App\Services\Payments\Webhooks\StripeWebhookHandlers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/** DES FRAIS D'ANNULATION SONT DE L'ARGENT ENCAISSÉ — ILS DOIVENT ENTRER DANS UN LIVRE. LE DÉFAUT. */
class FraisDAnnulationAuGrandLivreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('accounting_v2.auto_post_enabled', true);
    }

    /** LE DÉFAUT LUI-MÊME : les frais atteignent le compte 708. */
    public function test_des_frais_encaisses_produisent_une_ecriture(): void
    {
        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);

        // ON SOMME LE PRODUIT ET LA TVA, et ce n'était pas mon premier réflexe.
        $this->assertSame(
            2400,
            $this->creditSur('708') + $this->creditSur('4457'),
            'Les frais d’annulation n’entrent dans aucun livre : on prélève de l’argent réel sur la '
            .'carte du client et le grand livre l’ignore.',
        );
    }

    /** L'ÉCRITURE EST ÉQUILIBRÉE — sans quoi elle n'existerait tout simplement pas. */
    public function test_lecriture_respecte_la_partie_double(): void
    {
        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);

        $lignes = $this->lignesDeLEcriture();

        $this->assertNotEmpty($lignes, 'Aucune écriture : rien à équilibrer, le test ne mesure rien.');
        $this->assertSame(
            (int) $lignes->sum('debit_cents'),
            (int) $lignes->sum('credit_cents'),
        );
    }

    /** LA TVA EST SÉPARÉE DU PRODUIT, au taux du pays de la réservation. */
    public function test_la_tva_est_isolee_sur_son_propre_compte(): void
    {
        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);

        $this->assertSame(1983, $this->creditSur('708'));
        $this->assertSame(417, $this->creditSur('4457'));
    }

    /** « HORS CHAMP DE LA TVA » EST UNE POSITION FISCALE, ET ZÉRO N'EST PAS UNE ABSENCE. */
    public function test_un_taux_a_zero_est_respecte_et_non_confondu_avec_labsence(): void
    {
        config()->set('accounting_v2.marketplace.cancellation_fee_vat_rate', 0);

        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);

        $this->assertSame(2400, $this->creditSur('708'), 'Tout le montant est un produit.');
        $this->assertSame(0, $this->creditSur('4457'), 'Aucune TVA n’est due si les frais sont hors champ.');
    }

    /** LE PARTAGE SE LIT DANS LA CHARGE, IL NE SE SUPPOSE PAS. */
    public function test_la_part_reellement_transferee_devient_une_dette_prestataire(): void
    {
        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 480]);

        $this->assertSame(1920, $this->creditSur('467'), 'Ces euros ne sont pas à nous.');
        $this->assertSame(480, $this->creditSur('708') + $this->creditSur('4457'));
    }

    /** TÉMOIN INVERSE — sans champ de commission, on n'invente aucune dette. */
    public function test_temoin_sans_commission_declaree_aucune_dette_nest_inventee(): void
    {
        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, []);

        $this->assertSame(0, $this->creditSur('467'));
        $this->assertSame(2400, $this->creditSur('708') + $this->creditSur('4457'));
    }

    /** LE WEBHOOK REJOUÉ N'ÉCRIT PAS DEUX FOIS. Stripe redélivre. */
    public function test_un_webhook_rejoue_ne_double_pas_lecriture(): void
    {
        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);
        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);

        $this->assertSame(1983, $this->creditSur('708'), 'Le produit a été compté deux fois.');
        $this->assertSame(417, $this->creditSur('4457'), 'La TVA déclarée a doublé.');
    }

    /** LE PIÈGE QUE CE FICHIER DOCUMENTE DÉJÀ POUR `recordEarning`. */
    public function test_lecriture_a_lieu_meme_si_le_statut_etait_deja_pose(): void
    {
        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->assertSame(
            MissionPaymentService::STATUT_FRAIS_CAPTURES,
            $reservation->refresh()->payment_status,
            'Garde-fou du test : si le statut n’était pas déjà posé, on ne mesurerait pas le piège.',
        );

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);

        $this->assertGreaterThan(0, $this->creditSur('708'));
    }

    /** TÉMOIN — LE CHEMIN HISTORIQUE N'A PAS BOUGÉ. */
    public function test_temoin_un_encaissement_normal_ecrit_toujours_sa_ligne(): void
    {
        $scenario = SpineScenario::make()->build();

        Booking::query()->whereKey($scenario->booking->getKey())->update([
            'stripe_payment_intent_id' => 'pi_normal',
            'payment_amount_cents' => 12000,
            'payment_status' => 'captured',
            'estimated_price' => 120,
        ]);

        app(StripeWebhookHandlers::class)->handlePaymentIntentSucceeded([
            'id' => 'pi_normal',
            'amount' => 12000,
            'currency' => 'eur',
        ]);

        $this->assertGreaterThan(
            0,
            AccountingEntry::query()->where('source_type', 'Booking.payment')->count(),
            'L’encaissement ordinaire n’écrit plus rien : on a remplacé un trou par un autre.',
        );
    }

    /** TÉMOIN DE PORTÉE — le réglage coupé coupe vraiment. */
    public function test_le_postage_coupe_nequit_rien(): void
    {
        config()->set('accounting_v2.auto_post_enabled', false);

        $reservation = $this->reservationDontLesFraisSontCaptures(fraisCents: 2400);

        $this->recevoirLeWebhook($reservation, ['application_fee_amount' => 2400]);

        $this->assertSame(0, AccountingEntry::query()->count());
    }

    // ─────────────────────────────────────────────────────────────────────

    /** L'état exact que laisse `capturerLesFraisDAnnulation()` : le statut distinct, et la trace du montant réellement pris dans les métadonnées. */
    private function reservationDontLesFraisSontCaptures(int $fraisCents): Booking
    {
        $scenario = SpineScenario::make()->build();

        Booking::query()->whereKey($scenario->booking->getKey())->update([
            'stripe_payment_intent_id' => 'pi_empreinte',
            'payment_amount_cents' => 12000,
            'provider_amount_cents' => 9600,
            'payment_status' => MissionPaymentService::STATUT_FRAIS_CAPTURES,
            'estimated_price' => 120,
            'metadata' => ['frais_annulation' => [
                'dus_cents' => $fraisCents,
                'acompte_deja_debite_cents' => 0,
                'captures_cents' => $fraisCents,
            ]],
        ]);

        return $scenario->booking->refresh();
    }

    /** @param  array<string, mixed>  $enPlus */
    private function recevoirLeWebhook(Booking $reservation, array $enPlus): void
    {
        app(StripeWebhookHandlers::class)->handlePaymentIntentSucceeded(array_merge([
            'id' => (string) $reservation->stripe_payment_intent_id,
            'amount' => 12000,
            'amount_received' => 2400,
            'currency' => 'eur',
        ], $enPlus));
    }

    private function creditSur(string $compte): int
    {
        return (int) AccountingEntry::query()
            ->where('source_type', 'Booking.cancellation_fee')
            ->where('account_code', $compte)
            ->sum('credit_cents');
    }

    /** @return Collection<int, AccountingEntry> */
    private function lignesDeLEcriture(): Collection
    {
        return AccountingEntry::query()
            ->where('source_type', 'Booking.cancellation_fee')
            ->get();
    }
}
